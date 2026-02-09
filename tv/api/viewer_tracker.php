<?php
/**
 * Real-time Viewer Tracker API
 * Tracks concurrent viewers watching TV channels
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

// Require login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$user_id = $_SESSION['user_id'];
$session_id = session_id();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Create table if it doesn't exist (track by session_id to count unique browser sessions)
try {
    $conn->query("CREATE TABLE IF NOT EXISTS channel_viewers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        channel_id INT NOT NULL,
        user_id INT NULL,
        session_id VARCHAR(255) NOT NULL,
        last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_viewer (channel_id, session_id),
        INDEX idx_channel (channel_id),
        INDEX idx_user (user_id),
        INDEX idx_last_ping (last_ping)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // Table might already exist, that's okay
    error_log("Table creation note: " . $e->getMessage());
}

// Migrate existing table if needed
try {
    // Check if last_ping column exists, if not add it
    $result = $conn->query("SHOW COLUMNS FROM channel_viewers LIKE 'last_ping'");
    if ($result && $result->num_rows == 0) {
        // Check if last_seen exists (old column name)
        $result2 = $conn->query("SHOW COLUMNS FROM channel_viewers LIKE 'last_seen'");
        if ($result2 && $result2->num_rows > 0) {
            // Rename last_seen to last_ping
            @$conn->query("ALTER TABLE channel_viewers CHANGE COLUMN last_seen last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        } else {
            // Add last_ping column
            @$conn->query("ALTER TABLE channel_viewers ADD COLUMN last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER session_id");
        }
    }
    
    // Check if session_id column exists
    $result = $conn->query("SHOW COLUMNS FROM channel_viewers LIKE 'session_id'");
    if ($result && $result->num_rows == 0) {
        // Add session_id column
        @$conn->query("ALTER TABLE channel_viewers ADD COLUMN session_id VARCHAR(255) NOT NULL DEFAULT '' AFTER user_id");
        // Update existing records with unique session_id
        @$conn->query("UPDATE channel_viewers SET session_id = CONCAT('session_', COALESCE(user_id, 0), '_', id, '_', UNIX_TIMESTAMP()) WHERE session_id = ''");
        // Try to drop old unique key (ignore errors if it doesn't exist)
        @$conn->query("ALTER TABLE channel_viewers DROP INDEX IF EXISTS unique_viewer");
        // Add new unique key with session_id
        @$conn->query("ALTER TABLE channel_viewers ADD UNIQUE KEY unique_viewer (channel_id, session_id)");
    }
    
    // Make user_id nullable if it's not already
    $result = $conn->query("SHOW COLUMNS FROM channel_viewers WHERE Field = 'user_id'");
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['Null'] === 'NO') {
            @$conn->query("ALTER TABLE channel_viewers MODIFY COLUMN user_id INT NULL");
        }
    }
} catch (Exception $e) {
    // Table might not exist yet or migration failed, that's okay
    error_log("Viewer tracker migration error: " . $e->getMessage());
}

if ($action === 'ping') {
    // User is watching - update their last_seen timestamp
    $channel_id = intval($_POST['channel_id'] ?? $_GET['channel_id'] ?? 0);
    
    // Debug logging
    error_log("Viewer Tracker Ping: channel_id=$channel_id, user_id=$user_id, session_id=$session_id");
    
    if ($channel_id > 0) {
        // Clean up viewers who haven't pinged in 30 seconds first
        try {
            $conn->query("DELETE FROM channel_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            // Try with last_seen if last_ping doesn't exist yet
            try {
                $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                error_log("Error cleaning up viewers: " . $e2->getMessage());
            }
        }
        
        // Insert or update viewer record (track by session_id to count unique browser sessions)
        $insert_success = false;
        $insert_error = '';
        
        try {
            $stmt = $conn->prepare("INSERT INTO channel_viewers (channel_id, user_id, session_id, last_ping) 
                                    VALUES (?, ?, ?, NOW()) 
                                    ON DUPLICATE KEY UPDATE last_ping = NOW()");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("iis", $channel_id, $user_id, $session_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $insert_success = true;
            error_log("Successfully inserted/updated viewer: channel_id=$channel_id, user_id=$user_id, session_id=$session_id");
        } catch (Exception $e) {
            $insert_error = $e->getMessage();
            error_log("Error inserting viewer with last_ping: " . $insert_error);
            // Try with last_seen if last_ping doesn't exist yet
            try {
                $stmt = $conn->prepare("INSERT INTO channel_viewers (channel_id, user_id, session_id, last_seen) 
                                        VALUES (?, ?, ?, NOW()) 
                                        ON DUPLICATE KEY UPDATE last_seen = NOW()");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $stmt->bind_param("iis", $channel_id, $user_id, $session_id);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $insert_success = true;
                error_log("Successfully inserted/updated viewer with last_seen fallback");
            } catch (Exception $e2) {
                error_log("Error inserting viewer with last_seen: " . $e2->getMessage());
                $insert_error = $e2->getMessage();
            }
        }
        
        // Get current viewer count for this channel
        try {
            $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) as count FROM channel_viewers WHERE channel_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            $stmt->bind_param("i", $channel_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $viewer_count = $result->fetch_assoc()['count'] ?? 0;
            
            // Debug: Check all viewers for this channel
            $debug_stmt = $conn->prepare("SELECT channel_id, user_id, session_id, last_ping, last_seen FROM channel_viewers WHERE channel_id = ?");
            if ($debug_stmt) {
                $debug_stmt->bind_param("i", $channel_id);
                $debug_stmt->execute();
                $debug_result = $debug_stmt->get_result();
                $all_viewers = [];
                while ($row = $debug_result->fetch_assoc()) {
                    $all_viewers[] = $row;
                }
                error_log("Viewer count for channel $channel_id: $viewer_count. All viewers: " . json_encode($all_viewers));
            }
            
            echo json_encode([
                'viewers' => (int)$viewer_count, 
                'success' => $insert_success, 
                'insert_error' => $insert_error,
                'debug' => [
                    'channel_id' => $channel_id,
                    'user_id' => $user_id,
                    'session_id' => $session_id,
                    'viewer_count' => $viewer_count
                ]
            ]);
        } catch (Exception $e) {
            error_log("Error getting viewer count: " . $e->getMessage());
            echo json_encode([
                'viewers' => 0, 
                'success' => false, 
                'error' => $e->getMessage(),
                'insert_error' => $insert_error
            ]);
        }
    } else {
        echo json_encode(['viewers' => 0, 'success' => false, 'error' => 'Invalid channel_id']);
    }
    
} elseif ($action === 'get') {
    // Get viewer count for a channel
    $channel_id = intval($_POST['channel_id'] ?? $_GET['channel_id'] ?? 0);
    
    if ($channel_id > 0) {
        // Clean up old viewers first
        try {
            $conn->query("DELETE FROM channel_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            try {
                $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                error_log("Error cleaning up viewers: " . $e2->getMessage());
            }
        }
        
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) as count FROM channel_viewers WHERE channel_id = ?");
        $stmt->bind_param("i", $channel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $viewer_count = $result->fetch_assoc()['count'] ?? 0;
        
        echo json_encode(['viewers' => $viewer_count, 'success' => true]);
    } else {
        echo json_encode(['viewers' => 0, 'success' => false]);
    }
    
} elseif ($action === 'leave') {
    // User left the channel
    $channel_id = intval($_POST['channel_id'] ?? $_GET['channel_id'] ?? 0);
    
    if ($channel_id > 0) {
        // Remove by session_id to remove only this browser session
        $stmt = $conn->prepare("DELETE FROM channel_viewers WHERE channel_id = ? AND session_id = ?");
        $stmt->bind_param("is", $channel_id, $session_id);
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
    
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
}
