<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../admin/includes/functions.php';
require __DIR__ . '/../includes/movie_helpers.php';

$c = getDBConnection();
ensureMoviesSchema($c);
$row = $c->query("SELECT * FROM movies WHERE id=4")->fetch_assoc();
if (!$row) { echo "Movie 4 not found\n"; exit(1); }

$title = $row['title'];
$description = $row['description'];
$thumbnail = $row['thumbnail'] ?? '';
$poster = $row['poster'] ?? '';
$backdrop = $row['backdrop'] ?? '';
$duration = (int)$row['duration'];
$release_year = (int)$row['release_year'];
$rating = (float)$row['rating'];
$category_id = $row['category_id'] ? (int)$row['category_id'] : null;
$featured = (int)$row['featured'];
$is_active = (int)$row['is_active'];
$is_free = (int)$row['is_free'];
$is_premium = (int)$row['is_premium'];
$show_in_slider = (int)$row['show_in_slider'];
$tmdb_id = $row['tmdb_id'] ? (int)$row['tmdb_id'] : null;
$slug = $row['slug'];
$sourcesJson = $row['sources'] ?? '[]';
$downloadLinksJson = $row['download_links'] ?? '[]';
$director = $row['director'] ?? '';
$tags = $row['tags'] ?? '[]';
$quality_label = $row['quality_label'] ?? '';
$cast_data = $row['cast_data'] ?? '[]';
$genres = $row['genres'] ?? '[]';
$id = 4;

$stmt = $c->prepare("UPDATE movies SET title=?, description=?, thumbnail=?, poster=?, backdrop=?, duration=?, release_year=?, rating=?, category_id=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, tmdb_id=?, slug=?, sources=?, download_links=?, director=?, tags=?, quality_label=?, cast_data=?, genres=? WHERE id=?");
if (!$stmt) { echo "prepare fail: ".$c->error."\n"; exit(1); }

$types = "sssssiidiiiiiiiisssssssi";
echo "type len=".strlen($types)." expected 24\n";

try {
    $stmt->bind_param($types, $title, $description, $thumbnail, $poster, $backdrop, $duration, $release_year, $rating, $category_id, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $tmdb_id, $slug, $sourcesJson, $downloadLinksJson, $director, $tags, $quality_label, $cast_data, $genres, $id);
    if ($stmt->execute()) {
        echo "UPDATE OK\n";
    } else {
        echo "execute fail: ".$stmt->error."\n";
    }
} catch (Throwable $e) {
    echo "Exception: ".$e->getMessage()."\n";
}
