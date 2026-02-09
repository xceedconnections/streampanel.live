<?php
/**
 * Lightweight embed renderer for TV channel sources.
 * Loads ONLY the raw embed HTML for a specific source inside an iframe.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$conn = getDBConnection();

$slug  = $_GET['slug'] ?? null;
$id    = isset($_GET['id']) ? intval($_GET['id']) : null;
$index = isset($_GET['source']) ? intval($_GET['source']) : 0;

$channel = null;

if ($id) {
    $stmt = $conn->prepare("SELECT * FROM live_tv_channels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $channel = $stmt->get_result()->fetch_assoc();
} elseif ($slug) {
    $stmt = $conn->prepare("SELECT * FROM live_tv_channels WHERE slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $channel = $stmt->get_result()->fetch_assoc();
}

if (!$channel) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'Channel not found';
    exit;
}

// Parse sources and pick the requested active/visible one
$sources = parseSources($channel['sources'] ?? '[]');
$embedHtml = '';

if (!empty($sources)) {
    $active_sources = array_filter($sources, function($s) {
        return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true);
    });
    $active_sources = array_values($active_sources);

    if (!empty($active_sources)) {
        if ($index < 0 || $index >= count($active_sources)) {
            $index = 0;
        }
        $selected_source = $active_sources[$index] ?? null;
        if ($selected_source && !empty($selected_source['url']) &&
            in_array($selected_source['type'] ?? 'embed', ['embed', 'html-embed', 'iframe-only'], true)
        ) {
            $embedHtml = $selected_source['url'];
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($channel['name'] ?? 'Stream'); ?> - Source <?php echo (int)$index + 1; ?></title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background: #000;
            color: #fff;
            overflow: hidden;
        }
    </style>
</head>
<body>
<?php
// Output raw embed HTML as-is
if (!empty($embedHtml)) {
    echo $embedHtml;
} else {
    echo 'No embed available for this source.';
}
?>
</body>
</html>

