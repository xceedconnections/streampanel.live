<?php
/**
 * Direct test script to check if TV channel login requirement is working
 * Access via: https://streampanel.live/test_tv_login.php
 * Delete this file after testing
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$conn = getDBConnection();

echo "<h2>TV Channel Login Test</h2>";

// Get setting value
$setting_value = getSetting($conn, 'login_required_tv_channels', 'NOT_SET');
echo "<p><strong>Setting Value:</strong> " . htmlspecialchars($setting_value) . " (type: " . gettype($setting_value) . ")</p>";

// Test the same logic as tv-channel.php
$login_required = false;
if (is_string($setting_value)) {
    $setting_value = trim($setting_value);
    $login_required = ($setting_value === '1' || $setting_value === 'true' || $setting_value === 'yes');
} else {
    $login_required = ($setting_value === 1 || $setting_value === true);
}

echo "<p><strong>Login Required (calculated):</strong> " . ($login_required ? 'YES' : 'NO') . "</p>";

// Test if user is logged in
$is_logged_in = isLoggedIn();
echo "<p><strong>Currently Logged In:</strong> " . ($is_logged_in ? 'YES' : 'NO') . "</p>";

// Test the actual URL
echo "<hr><h3>Test URL:</h3>";
echo "<p><a href='/watch-live-tv/colors-rishtey' target='_blank'>/watch-live-tv/colors-rishtey</a></p>";

// Direct database query
echo "<hr><h3>Direct Database Query:</h3>";
$result = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'login_required_tv_channels'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "<p><strong>From DB:</strong> " . htmlspecialchars($row['setting_value']) . "</p>";
} else {
    echo "<p><strong>From DB:</strong> Setting does not exist (will use default '0')</p>";
}

// Force set to '0' button
echo "<hr><h3>Force Fix:</h3>";
echo "<form method='POST' style='margin: 20px 0;'>";
echo "<input type='hidden' name='action' value='force_set_zero'>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;'>Force Set to '0' (Login NOT Required)</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'force_set_zero') {
    // Delete existing setting
    $conn->query("DELETE FROM settings WHERE setting_key = 'login_required_tv_channels'");
    // Insert with '0'
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_required_tv_channels', '0')");
    if ($stmt->execute()) {
        echo "<p style='color: green; font-weight: bold;'>✓ Setting forced to '0'! Refresh this page to verify.</p>";
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
    } else {
        echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($conn->error) . "</p>";
    }
}

echo "<hr><p><em>Delete this file after testing for security.</em></p>";
?>
