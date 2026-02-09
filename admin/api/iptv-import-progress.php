<?php
/**
 * API Endpoint for IPTV Import Progress Tracking
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? 'get';

if ($action === 'get') {
    $progress = $_SESSION['iptv_import_progress'] ?? null;
    if ($progress) {
        ob_clean();
        echo json_encode([
            'success' => true,
            'progress' => $progress
        ]);
    } else {
        ob_clean();
        echo json_encode([
            'success' => false,
            'message' => 'No import in progress'
        ]);
    }
    exit;
} elseif ($action === 'clear') {
    unset($_SESSION['iptv_import_progress']);
    unset($_SESSION['iptv_import_data']);
    ob_clean();
    echo json_encode(['success' => true]);
    exit;
}

ob_clean();
echo json_encode(['success' => false, 'error' => 'Invalid action']);
exit;
