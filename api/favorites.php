<?php
/**
 * Favorites API Endpoint
 * Handles adding/removing favorites
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Ensure favorites table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content_type ENUM('movie', 'tv_show', 'live_tv') NOT NULL,
        content_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, content_type, content_id),
        INDEX idx_user_id (user_id)
    )");
} catch (Exception $e) {
    // Table might already exist
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Add to favorites
    $data = json_decode(file_get_contents('php://input'), true);
    $content_type = $data['content_type'] ?? $_POST['content_type'] ?? '';
    $content_id = intval($data['content_id'] ?? $_POST['content_id'] ?? 0);
    
    if (empty($content_type) || $content_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    if (!in_array($content_type, ['movie', 'tv_show', 'live_tv'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid content type']);
        exit;
    }
    
    // Check if already favorited
    $check = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND content_type = ? AND content_id = ?");
    $check->bind_param("isi", $user_id, $content_type, $content_id);
    $check->execute();
    
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Already in favorites', 'is_favorite' => true]);
        exit;
    }
    
    // Add to favorites
    $stmt = $conn->prepare("INSERT INTO favorites (user_id, content_type, content_id) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $user_id, $content_type, $content_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Added to favorites', 'is_favorite' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add to favorites']);
    }
    
} elseif ($method === 'DELETE') {
    // Remove from favorites
    $data = json_decode(file_get_contents('php://input'), true);
    $content_type = $data['content_type'] ?? $_GET['content_type'] ?? '';
    $content_id = intval($data['content_id'] ?? $_GET['content_id'] ?? 0);
    
    if (empty($content_type) || $content_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND content_type = ? AND content_id = ?");
    $stmt->bind_param("isi", $user_id, $content_type, $content_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Removed from favorites', 'is_favorite' => false]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove from favorites']);
    }
    
} elseif ($method === 'GET') {
    // Check if item is favorited
    $content_type = $_GET['content_type'] ?? '';
    $content_id = intval($_GET['content_id'] ?? 0);
    
    if (empty($content_type) || $content_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND content_type = ? AND content_id = ?");
    $stmt->bind_param("isi", $user_id, $content_type, $content_id);
    $stmt->execute();
    
    $is_favorite = $stmt->get_result()->num_rows > 0;
    echo json_encode(['success' => true, 'is_favorite' => $is_favorite]);
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
