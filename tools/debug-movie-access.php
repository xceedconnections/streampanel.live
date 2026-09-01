<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../admin/includes/functions.php';
require __DIR__ . '/../includes/movie_helpers.php';

$c = getDBConnection();
echo "login_required_movies: " . var_export(getSetting($c, 'login_required_movies', 'NOT_SET'), true) . "\n";
echo "isMoviesLoginRequiredSetting: " . (isMoviesLoginRequiredSetting($c) ? 'true' : 'false') . "\n\n";

$m = $c->query("SELECT id, title, slug, is_free, is_premium FROM movies WHERE id=4 OR slug LIKE '%awarapan%'")->fetch_all(MYSQLI_ASSOC);
foreach ($m as $row) {
    echo "Movie: " . json_encode($row) . "\n";
    echo "isMoviePremiumContent: " . (isMoviePremiumContent($row) ? 'true' : 'false') . "\n";
    echo "getMovieAccess (guest): " . json_encode(getMovieAccess($c, $row)) . "\n\n";
}
