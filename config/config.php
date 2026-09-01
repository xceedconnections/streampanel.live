<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
}

/**
 * Web path of this app relative to the document root ('' at domain root, '/stream' in a subfolder).
 */
function getAppBasePath(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $appRoot = str_replace('\\', '/', realpath(APP_ROOT) ?: APP_ROOT);
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docRoot = str_replace('\\', '/', $docRoot);

    if ($docRoot !== '') {
        $docRootReal = str_replace('\\', '/', realpath($docRoot) ?: $docRoot);
        $appCmp = $appRoot;
        $docCmp = rtrim($docRootReal, '/');
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            $appCmp = strtolower($appCmp);
            $docCmp = strtolower($docCmp);
        }
        if ($docCmp !== '' && strpos($appCmp, $docCmp) === 0) {
            $path = substr($appRoot, strlen(rtrim($docRootReal, '/')));
            $path = str_replace('\\', '/', $path);
            $base = rtrim($path, '/');
            return $base;
        }
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $path = dirname($script);
    foreach (['/admin', '/api', '/tv', '/shows', '/proxy', '/tools', '/cron'] as $dir) {
        $pos = strrpos(strtolower($path), $dir);
        if ($pos !== false && $pos === strlen($path) - strlen($dir)) {
            $path = substr($path, 0, $pos);
        }
        $path = str_ireplace($dir . '/', '/', $path);
    }
    if ($path === '/' || $path === '\\' || $path === '.') {
        $base = '';
    } elseif (preg_match('#^[A-Za-z]:#', $path) || (isset($path[0]) && $path[0] !== '/')) {
        // Never treat filesystem paths as web base paths (e.g. CLI scripts).
        $base = '';
    } else {
        $base = rtrim($path, '/');
    }

    return $base;
}

function getRequestProtocol(): string
{
    $httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    return $httpsOn ? 'https://' : 'http://';
}

function getBaseUrl(): string
{
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        $host = 'localhost';
        $basePath = getAppBasePath();
        $url = 'http://' . $host;
        return $basePath !== '' ? $url . $basePath : $url;
    }

    $basePath = getAppBasePath();
    $url = rtrim(getRequestProtocol() . $_SERVER['HTTP_HOST'], '/');
    return $basePath !== '' ? $url . $basePath : $url;
}

function basePath(): string
{
    return getAppBasePath();
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = rtrim(getBaseUrl(), '/');
    return $path === '' ? $base : $base . '/' . $path;
}

/** Build an API endpoint URL under the current install path. */
function apiUrl(string $path = ''): string
{
    return url($path);
}

/** Build the HLS/DASH proxy URL, optionally with an encoded upstream target. */
function proxyUrl(string $encodedUpstream = ''): string
{
    $proxy = url('proxy/hls-proxy.php');
    return $encodedUpstream === '' ? $proxy : $proxy . '?u=' . $encodedUpstream;
}

/**
 * Resolve a DB-stored or relative asset path to a full URL for the current install.
 */
function assetUrl(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }

    $path = trim($path);
    if (preg_match('#^https?://#i', $path)) {
        if (preg_match('#/(uploads/.+)$#i', $path, $matches)) {
            return url($matches[1]);
        }
        return $path;
    }

    if (isset($path[0]) && $path[0] === '/') {
        return url(ltrim($path, '/'));
    }

    return url($path);
}

/**
 * Store uploads as relative paths in the database.
 */
function normalizeUploadPath(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }

    $path = trim($path);
    if (preg_match('#/(uploads/.+)$#i', $path, $matches)) {
        return $matches[1];
    }

    return ltrim($path, '/');
}

/**
 * Resolve a channel logo value to a local uploads/tv-logos relative path, or null if external-only.
 */
function getLocalTvLogoRelativePath(?string $logo): ?string
{
    if ($logo === null || trim($logo) === '') {
        return null;
    }

    $logo = trim($logo);
    if (preg_match('#/(uploads/tv-logos/.+)$#i', $logo, $matches)) {
        return $matches[1];
    }
    if (preg_match('#^uploads/tv-logos/.+#i', $logo)) {
        return ltrim(str_replace('\\', '/', $logo), '/');
    }
    if (!preg_match('#^https?://#i', $logo)) {
        $basename = basename(str_replace('\\', '/', $logo));
        if ($basename !== '' && $basename !== '.' && $basename !== '..') {
            return 'uploads/tv-logos/' . $basename;
        }
    }

    return null;
}

/**
 * Whether a channel logo exists on disk (or is a non-local external URL we cannot verify here).
 */
function channelLogoFileExists(?string $logo): bool
{
    if ($logo === null || trim($logo) === '') {
        return false;
    }

    $logo = trim($logo);
    $relative = getLocalTvLogoRelativePath($logo);
    if ($relative !== null) {
        $fullPath = APP_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        return is_file($fullPath);
    }

    // External URL not stored under uploads/tv-logos — treat as present.
    return preg_match('#^https?://#i', $logo) === 1;
}

/** Reason string when a channel has no usable logo. */
function channelMissingLogoReason(?string $logo): string
{
    if ($logo === null || trim($logo) === '') {
        return 'empty_in_database';
    }

    $relative = getLocalTvLogoRelativePath($logo);
    if ($relative === null) {
        return '';
    }

    $fullPath = APP_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($fullPath)) {
        return 'file_missing_on_disk';
    }

    return '';
}

function channelHasNoLogo(?string $logo): bool
{
    return !channelLogoFileExists($logo);
}

function persistDetectedSiteUrl(string $url): void
{
    if ($url === '' || php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return;
    }

    try {
        require_once __DIR__ . '/database.php';
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url'");
        if (!$stmt) {
            return;
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $current = trim($row['setting_value'] ?? '');
        if ($current === $url) {
            return;
        }
        $update = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'site_url'");
        if ($update) {
            $update->bind_param('s', $url);
            $update->execute();
        }
    } catch (Exception $e) {
        // ignore
    }
}

define('APP_BASE_PATH', getAppBasePath());
define('BASE_URL', getBaseUrl());
define('SITE_URL', BASE_URL);
define('UPLOAD_DIR', APP_ROOT . '/uploads/');
define('IMAGE_BASE_URL', 'https://image.tmdb.org/t/p');
define('FALLBACK_POSTER', 'https://via.placeholder.com/500x750?text=No+Image');
define('SITE_NAME', 'StreamFlix');

require_once __DIR__ . '/htaccess.php';
syncHtaccess(APP_BASE_PATH);
persistDetectedSiteUrl(BASE_URL);

date_default_timezone_set('UTC');
