<?php
/**
 * API endpoint to get concurrent viewers for multiple channels
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$conn = getDBConnection();

// Get channel IDs from query parameter
$channel_ids = [];
if (isset($_GET['channels']) && !empty($_GET['channels'])) {
    $ids = explode(',', $_GET['channels']);
    foreach ($ids as $id) {
        $id = intval(trim($id));
        if ($id > 0) {
            $channel_ids[] = $id;
        }
    }
}

$viewers = [];

if (!empty($channel_ids)) {
    // Clean up old viewers first
    try {
        $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
    } catch (Exception $e) {
        // Table might not exist, ignore
    }
    
    // Get viewer counts for each channel
    foreach ($channel_ids as $channel_id) {
        try {
            $viewers[$channel_id] = getConcurrentViewers($conn, $channel_id);
        } catch (Exception $e) {
            $viewers[$channel_id] = 0;
        }
    }
}

echo json_encode([
    'success' => true,
    'viewers' => $viewers,
    'timestamp' => time()
]);
