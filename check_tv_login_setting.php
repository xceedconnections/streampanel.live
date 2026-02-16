<?php
/**
 * Quick verification script to check login_required_tv_channels setting
 * Access via: https://streampanel.live/check_tv_login_setting.php
 * Delete this file after verification for security
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$setting_value = getSetting($conn, 'login_required_tv_channels', 'NOT_SET');

echo "<h2>TV Channel Login Setting Check</h2>";
echo "<p><strong>Setting Key:</strong> login_required_tv_channels</p>";
echo "<p><strong>Setting Value:</strong> " . htmlspecialchars($setting_value) . "</p>";
echo "<p><strong>Raw Value Type:</strong> " . gettype($setting_value) . "</p>";
echo "<p><strong>Interpretation:</strong> ";
if ($setting_value === '1' || $setting_value === 1 || trim($setting_value) === '1') {
    echo "<span style='color: red; font-weight: bold;'>LOGIN REQUIRED (users must log in to view TV channels)</span>";
} else {
    echo "<span style='color: green; font-weight: bold;'>LOGIN NOT REQUIRED (anyone can view TV channels)</span>";
}
echo "</p>";

// Add quick fix button
echo "<hr><h3>Quick Fix:</h3>";
echo "<form method='POST' style='margin: 20px 0;'>";
echo "<input type='hidden' name='action' value='set_login_not_required'>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;'>Set Login NOT Required (Value = '0')</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_login_not_required') {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_required_tv_channels', '0') ON DUPLICATE KEY UPDATE setting_value = '0'");
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>✓ Setting updated successfully! Refresh this page to verify.</p>";
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
    } else {
        echo "<p style='color: red;'>✗ Error updating setting: " . htmlspecialchars($conn->error) . "</p>";
    }
}

// Show all settings for debugging
echo "<hr><h3>All Settings (for debugging):</h3>";
$result = $conn->query("SELECT setting_key, setting_value FROM settings ORDER BY setting_key");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Setting Key</th><th>Setting Value</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr><td>" . htmlspecialchars($row['setting_key']) . "</td><td>" . htmlspecialchars($row['setting_value']) . "</td></tr>";
}
echo "</table>";

echo "<hr><p><em>Delete this file after verification for security.</em></p>";
?>
