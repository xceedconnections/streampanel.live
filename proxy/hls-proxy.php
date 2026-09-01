<?php
/**
 * Simple HLS/DASH HTTPS proxy
 * - Accepts a base64-encoded upstream URL via ?u=
 * - Fetches the content server-side to avoid browser mixed-content blocking
 * - For M3U8 playlists, rewrites inner URLs to go back through this proxy
 *
 * IMPORTANT: This is restricted to http/https URLs only.
 */

require_once __DIR__ . '/../config/config.php';

// Increase timeouts for slow streams
set_time_limit(60);

// Helper: send error and exit
function proxy_error($code, $message) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

// Get and decode upstream URL
$encoded = $_GET['u'] ?? '';
if ($encoded === '') {
    proxy_error(400, 'Missing parameter');
}

$decoded = base64_decode($encoded, true);
if ($decoded === false) {
    proxy_error(400, 'Invalid URL encoding');
}

$upstreamUrl = trim($decoded);

// Basic validation - only allow http/https
if (!preg_match('#^https?://#i', $upstreamUrl)) {
    proxy_error(400, 'Only http/https URLs are allowed');
}

// Optional: you can add host whitelisting here if you want to restrict which origins are allowed.
// Example:
// $allowedHosts = ['103.78.149.54', 'example.com'];
// $host = parse_url($upstreamUrl, PHP_URL_HOST);
// if (!in_array($host, $allowedHosts, true)) { proxy_error(403, 'Origin not allowed'); }

// Fetch upstream
$ch = curl_init($upstreamUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_USERAGENT => 'StreamPanel-HLS-Proxy/1.0',
    CURLOPT_HEADER => true,
]);

$response = curl_exec($ch);

if ($response === false) {
    $err = curl_error($ch);
    curl_close($ch);
    proxy_error(502, 'Upstream error: ' . $err);
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$headerPart = substr($response, 0, $headerSize);
$bodyPart = substr($response, $headerSize);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
curl_close($ch);

// Normalize content-type and detect m3u8 based on both headers and actual body content
if (stripos($contentType, 'application/vnd.apple.mpegurl') !== false ||
    stripos($contentType, 'application/x-mpegurl') !== false ||
    stripos($contentType, 'audio/mpegurl') !== false ||
    stripos($contentType, 'video/x-mpegurl') !== false) {
    $isM3U8 = true;
} else {
    // Some servers send text/plain or even text/html for m3u8 but still include #EXTM3U
    $isM3U8 = (strpos($bodyPart, '#EXTM3U') !== false);
}

// Some IPTV servers return 4xx (even 404) for probe requests but still serve a valid playlist.
// If we clearly see an m3u8 playlist in the body, treat it as OK regardless of HTTP status code.
if (!$isM3U8 && $statusCode >= 400) {
    proxy_error($statusCode, 'Upstream HTTP ' . $statusCode);
}

// If it's not a playlist, just proxy bytes through
if (!$isM3U8) {
    header('Content-Type: ' . $contentType);
    // Avoid caching by default
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $bodyPart;
    exit;
}

// For m3u8 playlists, rewrite inner URLs to go back through this proxy
header('Content-Type: application/vnd.apple.mpegurl');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Build base URL for resolving relative paths
$urlParts = parse_url($upstreamUrl);
$scheme = $urlParts['scheme'] ?? 'http';
$host = $urlParts['host'] ?? '';
$port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
$baseRoot = $scheme . '://' . $host . $port;
$path = $urlParts['path'] ?? '/';
$dir = rtrim(substr($path, 0, strrpos($path, '/') + 1), '/');

// Helper to resolve relative URLs
function resolve_url($baseRoot, $dir, $ref) {
    // Absolute URL
    if (preg_match('#^https?://#i', $ref)) {
        return $ref;
    }
    // Protocol-relative
    if (strpos($ref, '//') === 0) {
        return 'http:' . $ref;
    }
    // Root-relative
    if (strpos($ref, '/') === 0) {
        return $baseRoot . $ref;
    }
    // Directory-relative
    $basePath = $dir === '' ? '/' : $dir . '/';
    return $baseRoot . $basePath . $ref;
}

$lines = preg_split("/(\r\n|\n|\r)/", $bodyPart);

foreach ($lines as $line) {
    $trim = trim($line);
    if ($trim === '' || strpos($trim, '#') === 0) {
        // Comment or tag line, pass through unchanged
        echo $line . "\n";
        continue;
    }

    // Treat as URI line
    $resolved = resolve_url($baseRoot, $dir, $trim);
    $proxied = proxyUrl(urlencode(base64_encode($resolved)));
    echo $proxied . "\n";
}

exit;

