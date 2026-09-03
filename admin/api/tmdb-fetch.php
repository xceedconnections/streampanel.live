<?php
/**
 * Fetch movie or TV metadata from TMDB by ID or URL.
 * Query: input=...&type=movie|tv (default movie; auto from /tv/ URL)
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/tmdb.php';
require_once __DIR__ . '/../../includes/movies_schema.php';

requireAdminLogin();
header('Content-Type: application/json');

$conn = getDBConnection();
ensureMoviesSchema($conn);

$input = trim($_GET['input'] ?? $_POST['input'] ?? '');
$type = strtolower(trim($_GET['type'] ?? $_POST['type'] ?? ''));

if ($input === '') {
    echo json_encode(['success' => false, 'error' => 'Enter a TMDB ID or URL']);
    exit;
}

if ($type !== 'tv' && $type !== 'movie') {
    if (preg_match('#(?:themoviedb|tmdb)\.org/tv/#i', $input)) {
        $type = 'tv';
    } else {
        $type = 'movie';
    }
}

$tmdbId = parseTmdbIdFromInput($input, $type);
if (!$tmdbId) {
    echo json_encode(['success' => false, 'error' => 'Invalid TMDB ID or URL']);
    exit;
}

$data = ($type === 'tv')
    ? fetchTmdbTvData($conn, $tmdbId)
    : fetchTmdbMovieData($conn, $tmdbId);

if (isset($data['error'])) {
    echo json_encode(['success' => false, 'error' => $data['error']]);
    exit;
}

echo json_encode([
    'success' => true,
    'type' => $type,
    'data' => $data,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
