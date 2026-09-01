<?php
/**
 * Real-time viewer tracker for movie watch pages.
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

$conn = getDBConnection();
$user_id = $_SESSION['user_id'] ?? null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Each browser tab must have its own viewer identity (PHP session_id is shared across tabs).
 */
function resolveMovieViewerToken(): string
{
    $token = trim((string) ($_POST['viewer_token'] ?? $_GET['viewer_token'] ?? ''));
    if ($token !== '' && preg_match('/^[a-zA-Z0-9_\-]{8,191}$/', $token)) {
        return $token;
    }

    $session_id = session_id();
    if ($session_id !== '') {
        return $session_id;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return 'guest_' . md5($ip . $user_agent . microtime(true)) . '_' . uniqid('', true);
}

$session_id = resolveMovieViewerToken();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $conn->query("CREATE TABLE IF NOT EXISTS movie_viewers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        movie_id INT NOT NULL,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL,
        last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_movie_viewer (movie_id, session_id),
        INDEX idx_movie (movie_id),
        INDEX idx_last_ping (last_ping)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    error_log('movie_viewers table: ' . $e->getMessage());
}

function cleanupMovieViewers($conn): void
{
    try {
        $conn->query('DELETE FROM movie_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)');
    } catch (Exception $e) {
        error_log('movie viewer cleanup: ' . $e->getMessage());
    }
}

function getMovieViewerCount($conn, int $movieId): int
{
    cleanupMovieViewers($conn);
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT session_id) AS count FROM movie_viewers WHERE movie_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $movieId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int) ($row['count'] ?? 0);
}

function upsertMovieViewer($conn, int $movieId, $user_id, string $session_id): bool
{
    $stmt = $conn->prepare('INSERT INTO movie_viewers (movie_id, user_id, session_id, last_ping)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE last_ping = NOW()');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('iis', $movieId, $user_id, $session_id);
    return $stmt->execute();
}

$movie_id = (int) ($_POST['movie_id'] ?? $_GET['movie_id'] ?? 0);

if ($action === 'ping' && $movie_id > 0) {
    $ok = upsertMovieViewer($conn, $movie_id, $user_id, $session_id);
    echo json_encode([
        'viewers' => getMovieViewerCount($conn, $movie_id),
        'success' => $ok,
    ]);
    exit;
}

if ($action === 'get' && $movie_id > 0) {
    echo json_encode([
        'viewers' => getMovieViewerCount($conn, $movie_id),
        'success' => true,
    ]);
    exit;
}

if ($action === 'leave' && $movie_id > 0) {
    $stmt = $conn->prepare('DELETE FROM movie_viewers WHERE movie_id = ? AND session_id = ?');
    if ($stmt) {
        $stmt->bind_param('is', $movie_id, $session_id);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid action', 'success' => false, 'viewers' => 0]);
