<?php
/**
 * Test script to verify viewer tracking is working
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$conn = getDBConnection();

echo "<h2>Viewer Tracking Test</h2>";

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'channel_viewers'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✓ Table 'channel_viewers' exists</p>";
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $conn->query("SHOW COLUMNS FROM channel_viewers");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($col = $columns->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show current viewers
    echo "<h3>Current Viewers:</h3>";
    $viewers = $conn->query("SELECT * FROM channel_viewers ORDER BY last_ping DESC, last_seen DESC");
    if ($viewers && $viewers->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Channel ID</th><th>User ID</th><th>Session ID</th><th>Last Ping</th><th>Last Seen</th></tr>";
        while ($row = $viewers->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['channel_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['user_id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['session_id'] ?? '', 0, 30)) . "...</td>";
            echo "<td>" . htmlspecialchars($row['last_ping'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($row['last_seen'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No viewers currently tracked</p>";
    }
    
    // Show viewer counts by channel
    echo "<h3>Viewer Counts by Channel:</h3>";
    $counts = $conn->query("
        SELECT 
            cv.channel_id,
            ltc.name as channel_name,
            COUNT(DISTINCT cv.session_id) as concurrent_viewers
        FROM channel_viewers cv
        LEFT JOIN live_tv_channels ltc ON cv.channel_id = ltc.id
        GROUP BY cv.channel_id, ltc.name
        ORDER BY concurrent_viewers DESC
    ");
    if ($counts && $counts->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Channel ID</th><th>Channel Name</th><th>Concurrent Viewers</th></tr>";
        while ($row = $counts->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['channel_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['channel_name'] ?? 'Unknown') . "</td>";
            echo "<td style='font-weight: bold; color: green;'>" . htmlspecialchars($row['concurrent_viewers']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>No active viewers</p>";
    }
    
} else {
    echo "<p style='color: red;'>✗ Table 'channel_viewers' does NOT exist</p>";
    echo "<p>Creating table...</p>";
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
        echo "<p style='color: green;'>✓ Table created successfully</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error creating table: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Check session
echo "<h3>Session Info:</h3>";
echo "<p>Session ID: " . htmlspecialchars(session_id()) . "</p>";
if (isset($_SESSION['user_id'])) {
    echo "<p>User ID: " . htmlspecialchars($_SESSION['user_id']) . "</p>";
} else {
    echo "<p style='color: red;'>✗ Not logged in - viewer tracking requires login</p>";
}
