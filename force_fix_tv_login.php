<?php
/**
 * COMPREHENSIVE FIX for TV Channel Login Requirement
 * This script will:
 * 1. Check current setting value
 * 2. Force set it to '0' (login NOT required)
 * 3. Verify the setting was saved correctly
 * 4. Test the logic that tv-channel.php uses
 * 
 * Access via: https://streampanel.live/force_fix_tv_login.php
 * DELETE THIS FILE AFTER USE FOR SECURITY
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$conn = getDBConnection();
$results = [];

// Step 1: Check current setting
$results['step1_current'] = 'NOT_SET';
$check_query = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'login_required_tv_channels' LIMIT 1");
if ($check_query && $check_query->num_rows > 0) {
    $row = $check_query->fetch_assoc();
    $results['step1_current'] = $row['setting_value'];
    $results['step1_id'] = $row['id'] ?? 'N/A';
} else {
    $results['step1_current'] = 'DOES_NOT_EXIST';
}

// Step 2: Force delete and insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])) {
    // Delete any existing
    $delete_result = $conn->query("DELETE FROM settings WHERE setting_key = 'login_required_tv_channels'");
    $results['step2_delete'] = $delete_result ? 'SUCCESS' : 'FAILED: ' . $conn->error;
    
    // Insert with '0'
    $insert_stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('login_required_tv_channels', '0')");
    if ($insert_stmt->execute()) {
        $results['step2_insert'] = 'SUCCESS';
    } else {
        $results['step2_insert'] = 'FAILED: ' . $conn->error;
    }
    
    // Verify it was saved
    $verify_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_tv_channels' LIMIT 1");
    if ($verify_query && $verify_query->num_rows > 0) {
        $verify_row = $verify_query->fetch_assoc();
        $results['step2_verified'] = $verify_row['setting_value'];
    } else {
        $results['step2_verified'] = 'NOT_FOUND';
    }
    
    // Test the exact logic from tv-channel.php
    $test_value = $results['step2_verified'];
    $test_login_required = false;
    if (is_string($test_value)) {
        $test_value = trim($test_value);
        $test_login_required = ($test_value === '1' || $test_value === 'true' || $test_value === 'yes');
    } else {
        $test_login_required = ($test_value === 1 || $test_value === true);
    }
    
    if (empty($test_value) || $test_value === '0' || $test_value === 0 || $test_value === false) {
        $test_login_required = false;
    }
    
    $results['step2_test_logic'] = $test_login_required ? 'WOULD_REQUIRE_LOGIN' : 'WOULD_ALLOW_ACCESS';
}

// Step 3: Test getSetting function
$test_getSetting = getSetting($conn, 'login_required_tv_channels', '0');
$results['step3_getSetting'] = $test_getSetting;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Force Fix TV Channel Login</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
            background: #1a1a1a;
            color: #fff;
            line-height: 1.6;
        }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { background: #2a2a2a; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #4CAF50; }
        .step { background: #2a2a2a; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #2196F3; }
        button {
            background: #28a745;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            margin: 10px 5px;
        }
        button:hover { background: #218838; }
        code {
            background: #000;
            padding: 2px 6px;
            border-radius: 3px;
            color: #4CAF50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #444;
        }
        th {
            background: #333;
            color: #fff;
        }
    </style>
</head>
<body>
    <h1>🔧 Force Fix TV Channel Login Setting</h1>
    
    <div class="info">
        <h3>Step 1: Current Setting Status</h3>
        <p><strong>Current Value:</strong> <code><?php echo htmlspecialchars($results['step1_current']); ?></code></p>
        <?php if (isset($results['step1_id'])): ?>
            <p><strong>Setting ID:</strong> <?php echo htmlspecialchars($results['step1_id']); ?></p>
        <?php endif; ?>
    </div>
    
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix'])): ?>
        <div class="step">
            <h3>Step 2: Fix Applied</h3>
            <table>
                <tr>
                    <th>Action</th>
                    <th>Result</th>
                </tr>
                <tr>
                    <td>Delete Existing Setting</td>
                    <td class="<?php echo strpos($results['step2_delete'], 'SUCCESS') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($results['step2_delete']); ?>
                    </td>
                </tr>
                <tr>
                    <td>Insert New Setting (value='0')</td>
                    <td class="<?php echo strpos($results['step2_insert'], 'SUCCESS') !== false ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($results['step2_insert']); ?>
                    </td>
                </tr>
                <tr>
                    <td>Verified Value</td>
                    <td><code><?php echo htmlspecialchars($results['step2_verified']); ?></code></td>
                </tr>
                <tr>
                    <td>Logic Test (tv-channel.php logic)</td>
                    <td class="<?php echo $results['step2_test_logic'] === 'WOULD_ALLOW_ACCESS' ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($results['step2_test_logic']); ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="step">
            <h3>Step 3: getSetting() Function Test</h3>
            <p><strong>getSetting() Result:</strong> <code><?php echo htmlspecialchars($results['step3_getSetting']); ?></code></p>
        </div>
        
        <?php if ($results['step2_test_logic'] === 'WOULD_ALLOW_ACCESS'): ?>
            <div class="info">
                <h3>✅ SUCCESS!</h3>
                <p>The setting has been fixed. TV channels should now be accessible without login.</p>
                <p><strong>Test URL:</strong> <a href="/watch-live-tv/titanic-tv" target="_blank" style="color: #4CAF50;">/watch-live-tv/titanic-tv</a></p>
            </div>
        <?php else: ?>
            <div class="warning">
                <h3>⚠️ WARNING</h3>
                <p>The logic test shows login would still be required. Please check the error logs for more details.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="warning">
            <h3>⚠️ Warning</h3>
            <p>This will <strong>force delete</strong> the existing setting and create a new one with value <code>'0'</code> (Login NOT Required).</p>
        </div>
        
        <form method="POST">
            <input type="hidden" name="fix" value="1">
            <button type="submit">🔧 FORCE FIX - Set to '0' (Login NOT Required)</button>
        </form>
    <?php endif; ?>
    
    <hr>
    <div class="info">
        <h3>Additional Information</h3>
        <p><strong>Database:</strong> <?php echo htmlspecialchars(DB_NAME); ?></p>
        <p><strong>Table:</strong> <code>settings</code></p>
        <p><strong>Setting Key:</strong> <code>login_required_tv_channels</code></p>
        <p><strong>Expected Value:</strong> <code>'0'</code> (string zero)</p>
    </div>
    
    <hr>
    <p><a href="/test_tv_login.php" style="color: #4CAF50;">Test Page</a> | 
       <a href="/check_tv_login_setting.php" style="color: #4CAF50;">Check Setting</a> | 
       <a href="/watch-live-tv/titanic-tv" style="color: #4CAF50;">Test TV Channel</a></p>
    <p><em style="color: #888;">⚠️ DELETE THIS FILE AFTER USE FOR SECURITY</em></p>
</body>
</html>
