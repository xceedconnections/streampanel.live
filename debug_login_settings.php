<?php
/**
 * Debug script to check login requirement settings
 * Access via: https://streampanel.live/debug_login_settings.php
 * DELETE THIS FILE AFTER DEBUGGING FOR SECURITY
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();

echo "<h2>Login Requirement Settings Debug</h2>";
echo "<style>body { font-family: Arial; padding: 20px; background: #1a1a1a; color: #fff; } table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #444; padding: 10px; text-align: left; } th { background: #333; } .value-0 { color: #4ade80; } .value-1 { color: #f87171; }</style>";

// Check all login requirement settings
$settings_to_check = [
    'login_required_tv_channels',
    'login_required_tv_shows',
    'login_required_movies'
];

echo "<table>";
echo "<tr><th>Setting Key</th><th>Database Value</th><th>Type</th><th>Interpretation</th><th>getSetting() Result</th></tr>";

foreach ($settings_to_check as $key) {
    // Direct database query
    $direct_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key' LIMIT 1");
    $db_value = 'NOT SET';
    $db_type = 'N/A';
    
    if ($direct_query && $direct_query->num_rows > 0) {
        $row = $direct_query->fetch_assoc();
        $db_value = $row['setting_value'];
        $db_type = gettype($db_value);
    }
    
    // getSetting function result
    $getSetting_result = getSetting($conn, $key, 'NOT_SET');
    $getSetting_type = gettype($getSetting_result);
    
    // Interpretation
    $interpretation = 'LOGIN NOT REQUIRED';
    $class = 'value-0';
    if ($db_value === '1' || $db_value === 1 || trim($db_value) === '1') {
        $interpretation = 'LOGIN REQUIRED';
        $class = 'value-1';
    }
    
    echo "<tr>";
    echo "<td><strong>$key</strong></td>";
    echo "<td class='$class'>" . htmlspecialchars(var_export($db_value, true)) . "</td>";
    echo "<td>$db_type</td>";
    echo "<td class='$class'><strong>$interpretation</strong></td>";
    echo "<td>" . htmlspecialchars(var_export($getSetting_result, true)) . " ($getSetting_type)</td>";
    echo "</tr>";
}

echo "</table>";

// Test the actual check logic
echo "<h3>Test Check Logic</h3>";
echo "<table>";
echo "<tr><th>Setting</th><th>Value</th><th>Normalized</th><th>Login Required?</th></tr>";

foreach ($settings_to_check as $key) {
    $value = getSetting($conn, $key, '0');
    $normalized = (string)$value;
    $normalized = trim($normalized);
    $login_required = ($normalized === '1');
    
    echo "<tr>";
    echo "<td>$key</td>";
    echo "<td>" . htmlspecialchars(var_export($value, true)) . "</td>";
    echo "<td>" . htmlspecialchars(var_export($normalized, true)) . "</td>";
    echo "<td class='" . ($login_required ? 'value-1' : 'value-0') . "'>" . ($login_required ? 'YES' : 'NO') . "</td>";
    echo "</tr>";
}

echo "</table>";

// Quick fix buttons
echo "<h3>Quick Fix</h3>";
echo "<form method='POST' style='margin: 20px 0;'>";
echo "<button type='submit' name='fix_all' value='0' style='background: #28a745; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px; margin-right: 10px;'>Set All to NOT Required (0)</button>";
echo "<button type='submit' name='fix_all' value='1' style='background: #dc3545; color: white; padding: 10px 20px; border: none; cursor: pointer; border-radius: 5px;'>Set All to REQUIRED (1)</button>";
echo "</form>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_all'])) {
    $value = $_POST['fix_all'];
    foreach ($settings_to_check as $key) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
    }
    echo "<p style='color: #4ade80;'>Settings updated! Refresh the page to see changes.</p>";
    echo "<script>setTimeout(function(){ location.reload(); }, 1000);</script>";
}

echo "<p style='color: #fbbf24; margin-top: 30px;'><strong>⚠️ DELETE THIS FILE AFTER DEBUGGING FOR SECURITY</strong></p>";
?>
