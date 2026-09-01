<?php
/**
 * Backfill missing TV logos from iptv-org index.m3u
 * Usage: php tools/backfill-logos-from-iptv-m3u.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$m3uFile = __DIR__ . '/../_iptv_index.m3u';
if (!is_file($m3uFile)) {
    fwrite(STDERR, "M3U file not found at {$m3uFile}\n");
    exit(1);
}

function normalizeChannelKey(string $name): string
{
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = strtolower(trim($name));
    $name = preg_replace('/\s*(hd|sd|uhd|4k|fhd)\s*$/i', '', $name);
    $name = preg_replace('/[^a-z0-9]/', '', $name);
    return $name;
}

function parseM3uLogoMap(string $file): array
{
    $byKey = [];
    $byTvgId = [];
    $handle = fopen($file, 'r');
    if (!$handle) {
        return ['byKey' => $byKey, 'byTvgId' => $byTvgId];
    }

    while (($line = fgets($handle)) !== false) {
        if (strpos($line, '#EXTINF:') !== 0) {
            continue;
        }
        if (!preg_match('/tvg-logo="([^"]*)"/', $line, $logoMatch)) {
            continue;
        }
        $logoUrl = trim($logoMatch[1]);
        if ($logoUrl === '' || !preg_match('#^https?://#i', $logoUrl)) {
            continue;
        }

        $tvgId = '';
        if (preg_match('/tvg-id="([^"]*)"/', $line, $idMatch)) {
            $tvgId = strtolower(trim($idMatch[1]));
            $tvgId = preg_replace('/@[^@]+$/', '', $tvgId);
            $tvgId = preg_replace('/[^a-z0-9]/', '', $tvgId);
        }

        if (!preg_match('/,(.+)$/', $line, $nameMatch)) {
            continue;
        }

        $displayName = trim($nameMatch[1]);
        $displayName = preg_replace('/\s*\[[^\]]*\]\s*$/', '', $displayName);
        $displayName = preg_replace('/\s*\([^)]*\)\s*$/', '', $displayName);
        $key = normalizeChannelKey($displayName);
        if ($key === '') {
            continue;
        }

        $entry = ['name' => $displayName, 'logo' => $logoUrl, 'key' => $key];

        if (!isset($byKey[$key])) {
            $byKey[$key] = $entry;
        }
        if ($tvgId !== '' && !isset($byTvgId[$tvgId])) {
            $byTvgId[$tvgId] = $entry;
        }
    }

    fclose($handle);
    return ['byKey' => $byKey, 'byTvgId' => $byTvgId];
}

function findM3uMatch(array $channel, array $byKey, array $byTvgId): ?array
{
    $candidates = array_unique(array_filter([
        normalizeChannelKey($channel['name'] ?? ''),
        normalizeChannelKey(html_entity_decode($channel['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        normalizeChannelKey(str_replace('-', ' ', $channel['slug'] ?? '')),
        normalizeChannelKey($channel['slug'] ?? ''),
    ]));

    foreach ($candidates as $key) {
        if ($key !== '' && isset($byKey[$key])) {
            return $byKey[$key];
        }
    }

    // Partial match: channel key contained in m3u key or vice versa (min 6 chars)
    foreach ($candidates as $key) {
        if (strlen($key) < 6) {
            continue;
        }
        foreach ($byKey as $m3uKey => $entry) {
            if (strlen($m3uKey) < 6) {
                continue;
            }
            if (strpos($m3uKey, $key) !== false || strpos($key, $m3uKey) !== false) {
                return $entry;
            }
        }
    }

    return null;
}

function extensionFromUrl(string $url): string
{
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) {
        return $ext === 'jpeg' ? 'jpg' : $ext;
    }
    return 'png';
}

function extensionFromBytes(string $data): string
{
    if (strncmp($data, "\x89PNG", 4) === 0) {
        return 'png';
    }
    if (strncmp($data, 'GIF8', 4) === 0) {
        return 'gif';
    }
    if (strncmp($data, "\xFF\xD8\xFF", 3) === 0) {
        return 'jpg';
    }
    if (strncmp($data, 'RIFF', 4) === 0 && strpos(substr($data, 0, 16), 'WEBP') !== false) {
        return 'webp';
    }
    if (strpos(substr($data, 0, 200), '<svg') !== false) {
        return 'svg';
    }
    return 'png';
}

function downloadLogoToPath(string $url, string $destPath): bool
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
        CURLOPT_MAXREDIRS => 8,
    ]);
    $imageData = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($imageData) || strlen($imageData) < 100) {
        return false;
    }

    $ext = extensionFromBytes($imageData);
    $destPath = preg_replace('/\.[^.]+$/', '.' . $ext, $destPath);
    if (!preg_match('/\.(jpg|jpeg|png|gif|webp|svg)$/i', $destPath)) {
        $destPath .= '.' . $ext;
    }

    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (file_put_contents($destPath, $imageData) === false) {
        return false;
    }

    return true;
}

function resolveDestBase(array $channel): string
{
    $relative = getLocalTvLogoRelativePath($channel['logo'] ?? '');
    if ($relative !== null) {
        $full = APP_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        return preg_replace('/\.[^.]+$/', '', $full);
    }
    return APP_ROOT . '/uploads/tv-logos/' . generateChannelLogoBaseName($channel['name'] ?? '');
}

function fixExtensionMismatch(mysqli $conn): int
{
    $fixed = 0;
    $result = $conn->query("SELECT id, logo FROM live_tv_channels WHERE logo LIKE '%uploads/tv-logos/%'");
    while ($row = $result->fetch_assoc()) {
        $relative = getLocalTvLogoRelativePath($row['logo']);
        if ($relative === null) {
            continue;
        }
        $full = APP_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (is_file($full)) {
            continue;
        }
        $base = preg_replace('/\.[^.]+$/', '', $full);
        foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $ext) {
            $alt = $base . '.' . $ext;
            if (is_file($alt)) {
                $newRel = 'uploads/tv-logos/' . basename(str_replace('\\', '/', $alt));
                $stmt = $conn->prepare('UPDATE live_tv_channels SET logo = ? WHERE id = ?');
                $stmt->bind_param('si', $newRel, $row['id']);
                if ($stmt->execute()) {
                    $fixed++;
                    echo "FIX ext #{$row['id']} -> {$newRel}\n";
                }
                $stmt->close();
                break;
            }
        }
    }
    return $fixed;
}

echo "Parsing M3U logo map...\n";
$maps = parseM3uLogoMap($m3uFile);
$byKey = $maps['byKey'];
echo 'M3U channels with logos: ' . number_format(count($byKey)) . "\n";

$conn = getDBConnection();

echo "Fixing extension mismatches in DB...\n";
$extFixed = fixExtensionMismatch($conn);
echo "Extension fixes: {$extFixed}\n\n";

$stats = [
    'missing' => 0,
    'matched' => 0,
    'downloaded' => 0,
    'updated_db' => 0,
    'no_match' => 0,
    'download_failed' => 0,
];

$result = $conn->query('SELECT id, name, logo, slug FROM live_tv_channels ORDER BY id ASC');
while ($channel = $result->fetch_assoc()) {
    if (!channelHasNoLogo($channel['logo'] ?? '')) {
        continue;
    }

    $stats['missing']++;
    $match = findM3uMatch($channel, $byKey, $maps['byTvgId']);

    if ($match === null) {
        $stats['no_match']++;
        continue;
    }

    $stats['matched']++;
    $destBase = resolveDestBase($channel);
    $destPath = $destBase . '.' . extensionFromUrl($match['logo']);

    if (!downloadLogoToPath($match['logo'], $destPath)) {
        $stats['download_failed']++;
        echo "FAIL #{$channel['id']} {$channel['name']} <- {$match['logo']}\n";
        continue;
    }

    // Resolve actual saved path (extension may differ from URL)
    $savedPath = null;
    foreach (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'] as $ext) {
        $try = $destBase . '.' . $ext;
        if (is_file($try)) {
            $savedPath = $try;
            break;
        }
    }
    if ($savedPath === null) {
        $stats['download_failed']++;
        continue;
    }

    $relative = 'uploads/tv-logos/' . basename(str_replace('\\', '/', $savedPath));
    $stmt = $conn->prepare('UPDATE live_tv_channels SET logo = ? WHERE id = ?');
    $stmt->bind_param('si', $relative, $channel['id']);
    if ($stmt->execute()) {
        $stats['downloaded']++;
        $stats['updated_db']++;
        echo "OK #{$channel['id']} {$channel['name']} -> {$relative}\n";
    }
    $stmt->close();
}

echo "\nSummary:\n";
foreach ($stats as $key => $value) {
    echo str_pad($key, 18) . ': ' . number_format($value) . "\n";
}

$stillMissing = 0;
$r = $conn->query('SELECT logo FROM live_tv_channels');
while ($row = $r->fetch_assoc()) {
    if (channelHasNoLogo($row['logo'] ?? '')) {
        $stillMissing++;
    }
}
echo "still_missing       : " . number_format($stillMissing) . "\n";
