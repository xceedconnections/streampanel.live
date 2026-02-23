<?php
/**
 * Quick Fix Script for Login Requirement Settings
 * This will force set all login requirements to '0' (NOT required)
 * Access via: https://streampanel.live/fix_login_settings.php
 * DELETE THIS FILE AFTER USE FOR SECURITY
 */

require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();

$settings = [
    'login_required_tv_channels',
    'login_required_tv_shows',
    'login_required_movies'
];

echo "<h2>Fixing Login Requirement Settings</h2>";
echo "<style>body { font-family: Arial; padding: 20px; background: #1a1a1a; color: #fff; } .success { color: #4ade80; } .error { color: #f87171; }</style>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($settings as $key) {
        // Delete existing setting
        $conn->query("DELETE FROM settings WHERE setting_key = '$key'");
        
        // Insert with value '0'
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, '0')");
        $stmt->bind_param("s", $key);
        $stmt->execute();
        
        echo "<p class='success'>✓ Fixed: $key = '0'</p>";
    }
    
    echo "<p class='success'><strong>All settings fixed! The pages should now work without requiring login.</strong></p>";
    echo "<p><a href='tv-shows.php' style='color: #60a5fa;'>Test TV Shows Page</a> | <a href='movies.php' style='color: #60a5fa;'>Test Movies Page</a></p>";
} else {
    echo "<p>This script will set all login requirement settings to '0' (NOT required).</p>";
    echo "<form method='POST'>";
    echo "<button type='submit' style='background: #28a745; color: white; padding: 15px 30px; border: none; cursor: pointer; border-radius: 5px; font-size: 16px;'>Fix All Login Settings</button>";
    echo "</form>";
    
    // Show current values
    echo "<h3>Current Values:</h3>";
    foreach ($settings as $key) {
        $result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $value = $row['setting_value'];
            echo "<p>$key = <strong>" . htmlspecialchars($value) . "</strong></p>";
        } else {
            echo "<p>$key = <strong>NOT SET</strong></p>";
        }
    }
}

echo "<p style='color: #fbbf24; margin-top: 30px;'><strong>⚠️ DELETE THIS FILE AFTER USE FOR SECURITY</strong></p>";
?>
