<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/includes/functions.php';
require_once __DIR__ . '/../includes/movie_helpers.php';
require_once __DIR__ . '/../includes/cast_helpers.php';

$conn = getDBConnection();
$query = trim($_GET['q'] ?? '');
$scope = $_GET['scope'] ?? 'all';

$response = [
    'query' => $query,
    'movies' => [],
    'actors' => [],
    'tv_shows' => [],
    'live_tv' => [],
];

if (mb_strlen($query) < 2) {
    echo json_encode($response);
    exit;
}

$searchParam = '%' . strtolower($query) . '%';

if ($scope === 'all' || $scope === 'movies') {
    $movies = searchMoviesWithCast($conn, $query, 8);
    foreach ($movies as $movie) {
        $response['movies'][] = [
            'id' => (int) $movie['id'],
            'title' => $movie['title'],
            'year' => (int) ($movie['release_year'] ?? 0),
            'poster' => moviePosterUrl($movie),
            'url' => getMovieDetailUrl($movie, $conn),
        ];
    }

    $response['actors'] = searchActors($conn, $query, 8);
}

if ($scope === 'all') {
    $tvStmt = $conn->prepare(
        "SELECT t.id, t.title, t.slug, t.poster, t.thumbnail, t.release_year
         FROM tv_shows t
         WHERE t.is_active = 1
         AND (LOWER(t.title) LIKE ? OR LOWER(COALESCE(t.description, '')) LIKE ?)
         ORDER BY t.featured DESC, t.views DESC
         LIMIT 6"
    );
    $tvStmt->bind_param('ss', $searchParam, $searchParam);
    $tvStmt->execute();
    $tvShows = $tvStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($tvShows as $show) {
        $showUrl = !empty($show['slug'])
            ? url('tv-show/' . $show['slug'])
            : url('tv-show-detail?id=' . (int) $show['id']);
        $response['tv_shows'][] = [
            'id' => (int) $show['id'],
            'title' => $show['title'],
            'year' => (int) ($show['release_year'] ?? 0),
            'poster' => assetUrl($show['poster'] ?? $show['thumbnail'] ?? '') ?: FALLBACK_POSTER,
            'url' => $showUrl,
        ];
    }

    $channelStmt = $conn->prepare(
        "SELECT id, name, slug, logo, category
         FROM live_tv_channels
         WHERE is_active = 1
         AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
         AND (sources LIKE '%\"url\"%' OR stream_url IS NOT NULL AND stream_url != '')
         AND (LOWER(name) LIKE ? OR LOWER(COALESCE(description, '')) LIKE ? OR LOWER(COALESCE(category, '')) LIKE ?)
         ORDER BY featured DESC, views DESC
         LIMIT 6"
    );
    $channelStmt->bind_param('sss', $searchParam, $searchParam, $searchParam);
    $channelStmt->execute();
    $channels = $channelStmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($channels as $channel) {
        if (countActiveSources($channel) <= 0) {
            continue;
        }
        $channelUrl = !empty($channel['slug'])
            ? url('tv/' . $channel['slug'])
            : url('tv/tv-channel.php?id=' . (int) $channel['id']);
        $response['live_tv'][] = [
            'id' => (int) $channel['id'],
            'title' => $channel['name'],
            'category' => $channel['category'] ?? '',
            'logo' => assetUrl($channel['logo'] ?? ''),
            'url' => $channelUrl,
        ];
    }
}

echo json_encode($response);
