<?php
/**
 * Embed renderer for movie watch sources (same pattern as embed-source.php for live TV).
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';

$conn = getDBConnection();
$slug = $_GET['slug'] ?? null;
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$index = isset($_GET['source']) ? (int) $_GET['source'] : 0;

$movie = null;
if ($id > 0) {
    $movie = getMovieById($conn, $id);
} elseif ($slug) {
    $stmt = $conn->prepare('SELECT * FROM movies WHERE slug = ?');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
}

header('Content-Type: text/html; charset=utf-8');

if (!$movie) {
    echo 'Movie not found';
    exit;
}

$sources = getActiveWatchSources($movie);
$embedHtml = '';

if (!empty($sources)) {
    if ($index < 0 || $index >= count($sources)) {
        $index = 0;
    }
    $selected = $sources[$index] ?? null;
    if ($selected && !empty($selected['url'])) {
        $type = strtolower($selected['type'] ?? 'embed');
        if (in_array($type, ['embed', 'html-embed', 'html', 'iframe-only'], true)) {
            $embedHtml = $selected['url'];
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($movie['title'] ?? 'Movie'); ?> - Source <?php echo $index + 1; ?></title>
    <style>
        html, body { margin: 0; padding: 0; width: 100%; height: 100%; background: #000; overflow: hidden; }
        iframe { width: 100%; height: 100%; border: 0; }
    </style>
</head>
<body>
<?php
if ($embedHtml !== '') {
    $trimmed = trim($embedHtml);
    if (preg_match('#^https?://#i', $trimmed)) {
        echo '<iframe src="' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8') . '" allowfullscreen allow="autoplay; encrypted-media; picture-in-picture"></iframe>';
    } else {
        echo $embedHtml;
    }
} else {
    echo 'Movie source not available.';
}
?>
</body>
</html>
