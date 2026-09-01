<?php
/**
 * Backfill missing TV logos from Excel file (finaliptv-format.xlsx)
 * Usage: php tools/backfill-logos-from-xlsx.php [path-to-xlsx]
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$xlsxFile = $argv[1] ?? 'C:/Users/Canada/Desktop/finaliptv-format.xlsx';
if (!is_file($xlsxFile)) {
    fwrite(STDERR, "Excel file not found: {$xlsxFile}\n");
    exit(1);
}

function normalizeChannelKey(string $name): string
{
    $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $name = strtolower(trim($name));
    $name = preg_replace('/\s*(hd|sd|uhd|4k|fhd)\s*$/i', '', $name);
    return preg_replace('/[^a-z0-9]/', '', $name);
}

function colToIndex(string $letters): int
{
    $index = 0;
    for ($i = 0; $i < strlen($letters); $i++) {
        $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
    }
    return $index - 1;
}

function parseXlsxRows(string $file): array
{
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        throw new RuntimeException('Could not open Excel file');
    }

    $sharedStrings = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml) {
        $sxml = simplexml_load_string($xml);
        foreach ($sxml->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = (string) $si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    $text .= (string) $r->t;
                }
                $sharedStrings[] = $text;
            } else {
                $sharedStrings[] = '';
            }
        }
    }

    $ws = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$ws) {
        $zip->close();
        throw new RuntimeException('Could not read worksheet');
    }

    $sxml = simplexml_load_string($ws);
    $rows = [];
    foreach ($sxml->sheetData->row as $row) {
        $rowData = [];
        foreach ($row->c as $cell) {
            $ref = (string) $cell['r'];
            preg_match('/^([A-Z]+)/', $ref, $m);
            $col = colToIndex($m[1]);
            $type = (string) $cell['t'];
            $val = (string) $cell->v;
            if ($type === 's') {
                $val = $sharedStrings[(int) $val] ?? $val;
            } elseif ($type === 'inlineStr') {
                $val = (string) $cell->is->t;
            }
            $rowData[$col] = $val;
        }
        if (!empty($rowData)) {
            $max = max(array_keys($rowData));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $rowData[$i] ?? '';
            }
            $rows[] = $line;
        }
    }

    $zip->close();
    return $rows;
}

function extensionFromBytes(string $data): string
{
    if (strncmp($data, "\x89PNG", 4) === 0) return 'png';
    if (strncmp($data, 'GIF8', 4) === 0) return 'gif';
    if (strncmp($data, "\xFF\xD8\xFF", 3) === 0) return 'jpg';
    if (strncmp($data, 'RIFF', 4) === 0 && strpos(substr($data, 0, 16), 'WEBP') !== false) return 'webp';
    if (strpos(substr($data, 0, 200), '<svg') !== false) return 'svg';
    return 'png';
}

function downloadLogoToPath(string $url, string $destBase): ?string
{
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
        CURLOPT_MAXREDIRS => 8,
    ]);
    $imageData = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($imageData) || strlen($imageData) < 100) {
        return null;
    }

    $ext = extensionFromBytes($imageData);
    $destPath = $destBase . '.' . $ext;
    $dir = dirname($destPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_put_contents($destPath, $imageData) === false) {
        return null;
    }

    return $destPath;
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

function findExcelMatch(array $channel, array $byKey): ?array
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

    foreach ($candidates as $key) {
        if (strlen($key) < 6) continue;
        foreach ($byKey as $m3uKey => $entry) {
            if (strlen($m3uKey) < 6) continue;
            if (strpos($m3uKey, $key) !== false || strpos($key, $m3uKey) !== false) {
                return $entry;
            }
        }
    }

    return null;
}

echo "Parsing Excel: {$xlsxFile}\n";
$rows = parseXlsxRows($xlsxFile);
if (count($rows) < 2) {
    fwrite(STDERR, "Excel has no data rows\n");
    exit(1);
}

$headers = array_map(static fn($h) => strtolower(trim($h)), $rows[0]);
$nameIdx = array_search('channel name', $headers, true);
if ($nameIdx === false) {
    $nameIdx = array_search('channel_name', $headers, true);
}
if ($nameIdx === false) {
    $nameIdx = array_search('name', $headers, true);
}
$logoIdx = array_search('logo', $headers, true);

if ($nameIdx === false || $logoIdx === false) {
    fwrite(STDERR, 'Required columns not found. Headers: ' . implode(', ', $headers) . PHP_EOL);
    exit(1);
}

$byKey = [];
for ($i = 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    $name = trim($row[$nameIdx] ?? '');
    $logo = trim($row[$logoIdx] ?? '');
    if ($name === '' || $logo === '' || !preg_match('#^https?://#i', $logo)) {
        continue;
    }
    $key = normalizeChannelKey($name);
    if ($key === '') {
        continue;
    }
    if (!isset($byKey[$key])) {
        $byKey[$key] = ['name' => $name, 'logo' => $logo];
    }
}

echo 'Excel channels with logos: ' . number_format(count($byKey)) . "\n";

$conn = getDBConnection();
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
    $match = findExcelMatch($channel, $byKey);
    if ($match === null) {
        $stats['no_match']++;
        continue;
    }

    $stats['matched']++;
    $destBase = resolveDestBase($channel);
    $savedPath = downloadLogoToPath($match['logo'], $destBase);
    if ($savedPath === null) {
        $stats['download_failed']++;
        echo "FAIL #{$channel['id']} {$channel['name']} <- {$match['logo']}\n";
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
