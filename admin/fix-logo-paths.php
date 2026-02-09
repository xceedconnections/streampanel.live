<?php
/**
 * Fix incorrect logo paths in live_tv_channels table
 * Removes /api from logo paths that were incorrectly saved
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdminLogin();

$conn = getDBConnection();

// Get all channels with logo paths containing /api
$channels = $conn->query("SELECT id, name, logo FROM live_tv_channels WHERE logo LIKE '%/api/%'");

$fixed = 0;
$errors = [];

while ($channel = $channels->fetch_assoc()) {
    $old_logo = $channel['logo'];
    // Remove /api from the path
    $new_logo = str_replace('/api/', '/', $old_logo);
    $new_logo = str_replace('/api', '', $new_logo); // Also handle /api at the end
    
    // Update the channel
    $update = $conn->prepare("UPDATE live_tv_channels SET logo = ? WHERE id = ?");
    $update->bind_param("si", $new_logo, $channel['id']);
    
    if ($update->execute()) {
        $fixed++;
    } else {
        $errors[] = "Failed to fix channel '{$channel['name']}': " . $update->error;
    }
}

echo "<h1>Logo Path Fix</h1>";
echo "<p>Fixed $fixed channel(s).</p>";
if (!empty($errors)) {
    echo "<h2>Errors:</h2>";
    echo "<ul>";
    foreach ($errors as $error) {
        echo "<li>" . htmlspecialchars($error) . "</li>";
    }
    echo "</ul>";
}
echo "<p><a href='?tab=live-tv'>Back to Live TV Channels</a></p>";
