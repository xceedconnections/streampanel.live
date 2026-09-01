<?php
require __DIR__ . '/../config/database.php';
$c = getDBConnection();
$row = $c->query("SELECT id,title,category_id,tmdb_id,LENGTH(cast_data) as clen FROM movies WHERE id=4")->fetch_assoc();
var_export($row);

$category_id = null;
$tmdb_id = null;
$id = 4;
$title = 'test';
$description = 'd';
$thumbnail = '';
$poster = '';
$backdrop = '';
$duration = 0;
$release_year = 2026;
$rating = 0.0;
$featured = 0;
$is_active = 1;
$is_free = 1;
$is_premium = 0;
$show_in_slider = 0;
$slug = 'test';
$sourcesJson = '[]';
$downloadLinksJson = '[]';
$director = '';
$tags = '[]';
$quality_label = '';
$cast_data = '[]';
$genres = '[]';

$stmt = $c->prepare("UPDATE movies SET title=?, description=?, thumbnail=?, poster=?, backdrop=?, duration=?, release_year=?, rating=?, category_id=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, tmdb_id=?, slug=?, sources=?, download_links=?, director=?, tags=?, quality_label=?, cast_data=?, genres=? WHERE id=?");
try {
    $stmt->bind_param("sssssiidiiiiiiiisssssssi", $title, $description, $thumbnail, $poster, $backdrop, $duration, $release_year, $rating, $category_id, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $tmdb_id, $slug, $sourcesJson, $downloadLinksJson, $director, $tags, $quality_label, $cast_data, $genres, $id);
    echo "\nbind ok\n";
} catch (Throwable $e) {
    echo "\nbind error: ".$e->getMessage()."\n";
}
