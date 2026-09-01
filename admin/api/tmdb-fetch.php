<?php
/**
 * Fetch movie metadata from TMDB by ID or URL.
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
if ($input === '') {
    echo json_encode(['success' => false, 'error' => 'Enter a TMDB ID or URL']);
    exit;
}

$tmdbId = parseTmdbIdFromInput($input);
if (!$tmdbId) {
    echo json_encode(['success' => false, 'error' => 'Invalid TMDB ID or URL']);
    exit;
}

$data = fetchTmdbMovieData($conn, $tmdbId);
if (isset($data['error'])) {
    echo json_encode(['success' => false, 'error' => $data['error']]);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => $data,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
