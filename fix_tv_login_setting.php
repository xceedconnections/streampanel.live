<?php
/**
 * Force fix script for login_required_tv_channels setting
 * This will directly set the setting to '0' (login NOT required)
 * Access via: https://streampanel.live/fix_tv_login_setting.php
 * Delete this file after use for security
 */

require_once __DIR__ . '/config/database.php';

$conn = getDBConnection();
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    // Delete any existing setting
    $conn->query("DELETE FROM settings WHERE setting_key = 'login_required_tv_channels'");
    
    // Insert with '0' value
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_required_tv_channels', '0')");
    
    if ($stmt->execute()) {
        $success = true;
        $message = "✓ Setting successfully set to '0' (Login NOT Required)";
    } else {
        $message = "✗ Error: " . htmlspecialchars($conn->error);
    }
}

// Check current value
$current_value = 'NOT_SET';
$result = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_tv_channels' LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_value = $row['setting_value'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix TV Channel Login Setting</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .info { background: #2a2a2a; padding: 15px; border-radius: 5px; margin: 20px 0; }
        button {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 5px;
        }
        button:hover { background: #218838; }
        .warning { background: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>Fix TV Channel Login Setting</h1>
    
    <div class="info">
        <p><strong>Current Setting Value:</strong> <?php echo htmlspecialchars($current_value); ?></p>
        <p><strong>Action:</strong> This will set the setting to '0' (Login NOT Required)</p>
    </div>
    
    <?php if ($message): ?>
        <div class="<?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $message; ?>
        </div>
        <?php if ($success): ?>
            <script>
                setTimeout(function() {
                    window.location.href = '/watch-live-tv/titanic-tv';
                }, 2000);
            </script>
            <p>Redirecting to test page in 2 seconds...</p>
        <?php endif; ?>
    <?php else: ?>
        <div class="warning">
            <strong>Warning:</strong> This will force the setting to '0', allowing anyone to view TV channels without login.
        </div>
        
        <form method="POST">
            <input type="hidden" name="confirm" value="1">
            <button type="submit">Force Set to '0' (Login NOT Required)</button>
        </form>
    <?php endif; ?>
    
    <hr>
    <p><a href="/test_tv_login.php" style="color: #4CAF50;">Go to Test Page</a> | <a href="/check_tv_login_setting.php" style="color: #4CAF50;">Check Setting</a></p>
    <p><em style="color: #888;">Delete this file after use for security.</em></p>
</body>
</html>
