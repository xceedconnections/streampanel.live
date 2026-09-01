<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/movie_helpers.php';
$c = getDBConnection();
$row = $c->query('SELECT id, title, slug, sources FROM movies WHERE id=4')->fetch_assoc();
$sources = getActiveWatchSources($row);
echo "Movie: {$row['title']}\n\n";
foreach ($sources as $i => $s) {
    echo "Source " . ($i + 1) . ":\n";
    echo json_encode($s, JSON_PRETTY_PRINT) . "\n\n";
}
