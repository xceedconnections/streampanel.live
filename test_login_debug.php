<?php
/**
 * Debug Login Issues
 * This will help identify why login/registration is failing
 */

define('SKIP_SESSION', true);
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'streaming_portal');

function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Login Debug</title>";
echo "<style>body{font-family:Arial;margin:20px;background:#1a1a1a;color:#fff;}";
echo ".success{color:#4CAF50;padding:10px;background:#1e3a1e;margin:10px 0;}";
echo ".error{color:#f44336;padding:10px;background:#3a1e1e;margin:10px 0;}";
echo ".info{color:#2196F3;padding:10px;background:#1e2a3a;margin:10px 0;}";
echo "table{border-collapse:collapse;width:100%;margin:20px 0;background:#2a2a2a;}";
echo "th,td{border:1px solid #444;padding:12px;}";
echo "th{background:#333;}</style></head><body>";
echo "<h1>Login Debug Tool</h1>";

try {
    $conn = getDBConnection();
    echo "<div class='success'>✓ Database connected</div>";
    
    // Test 1: Check users
    echo "<h2>Test 1: All Users</h2>";
    $result = $conn->query("SELECT id, username, email, banned, max_devices, 
                           LENGTH(password) as pwd_len,
                           LEFT(password, 10) as pwd_start
                           FROM users");
    if ($result && $result->num_rows > 0) {
        echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Banned</th><th>Pwd Length</th><th>Pwd Start</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['username']}</td><td>{$row['email']}</td>";
            echo "<td>{$row['banned']}</td><td>{$row['pwd_len']}</td><td>{$row['pwd_start']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Test 2: Try to login with first user
    echo "<h2>Test 2: Test Login Function</h2>";
    $test_user = $conn->query("SELECT * FROM users LIMIT 1")->fetch_assoc();
    if ($test_user) {
        echo "<div class='info'>Testing with user: {$test_user['username']} (ID: {$test_user['id']})</div>";
        echo "<div class='info'>Password hash starts with: " . substr($test_user['password'], 0, 10) . "...</div>";
        echo "<div class='info'>Banned status: " . ($test_user['banned'] ?? 'NULL') . "</div>";
        echo "<div class='info'>Max devices: " . ($test_user['max_devices'] ?? 'NULL') . "</div>";
        
        // Test password_verify with a test password
        if (isset($_GET['test_password'])) {
            $test_pwd = $_GET['test_password'];
            $verified = password_verify($test_pwd, $test_user['password']);
            if ($verified) {
                echo "<div class='success'>✓ Password verification SUCCESSFUL!</div>";
            } else {
                echo "<div class='error'>✗ Password verification FAILED</div>";
            }
        } else {
            echo "<div class='info'>Add ?test_password=YOURPASSWORD to test password verification</div>";
        }
    }
    
    // Test 3: Check registerUser function
    echo "<h2>Test 3: Test Registration</h2>";
    if (isset($_GET['test_register'])) {
        require_once __DIR__ . '/includes/auth.php';
        $test_username = 'test_' . time();
        $test_email = 'test_' . time() . '@test.com';
        $test_password = 'test123456';
        
        echo "<div class='info'>Attempting to register: {$test_username}</div>";
        $result = registerUser($test_username, $test_email, $test_password, 'Test User');
        if ($result) {
            echo "<div class='success'>✓ Registration SUCCESSFUL!</div>";
            // Clean up
            $conn->query("DELETE FROM users WHERE username = '$test_username'");
            echo "<div class='info'>Test user deleted</div>";
        } else {
            echo "<div class='error'>✗ Registration FAILED</div>";
            echo "<div class='error'>Error: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='info'>Add ?test_register=1 to test registration</div>";
    }
    
    // Test 4: Check loginUser function
    echo "<h2>Test 4: Test Login</h2>";
    if (isset($_GET['test_login_user']) && isset($_GET['test_login_pwd'])) {
        require_once __DIR__ . '/includes/auth.php';
        $test_user = $_GET['test_login_user'];
        $test_pwd = $_GET['test_login_pwd'];
        
        echo "<div class='info'>Attempting to login: {$test_user}</div>";
        $result = loginUser($test_user, $test_pwd, 'Test Device');
        if ($result === true) {
            echo "<div class='success'>✓ Login SUCCESSFUL!</div>";
        } elseif ($result === 'banned') {
            echo "<div class='error'>✗ User is BANNED</div>";
        } elseif ($result === 'device_limit') {
            echo "<div class='error'>✗ Device limit reached</div>";
        } else {
            echo "<div class='error'>✗ Login FAILED</div>";
        }
    } else {
        echo "<div class='info'>Add ?test_login_user=USERNAME&test_login_pwd=PASSWORD to test login</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
