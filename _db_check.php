<?php
/**
 * User Login Issues Fixer and Checker
 * Access this file directly: http://localhost/stream/_db_check.php
 */

// Don't start session for this diagnostic script
define('SKIP_SESSION', true);

// Database configuration (direct, without session)
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'streaming_portal');

// Create database connection function
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>User Login Issues Fixer</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #1a1a1a; color: #fff; }
        .success { color: #4CAF50; padding: 10px; background: #1e3a1e; border-left: 4px solid #4CAF50; margin: 10px 0; }
        .error { color: #f44336; padding: 10px; background: #3a1e1e; border-left: 4px solid #f44336; margin: 10px 0; }
        .info { color: #2196F3; padding: 10px; background: #1e2a3a; border-left: 4px solid #2196F3; margin: 10px 0; }
        .warning { color: #ff9800; padding: 10px; background: #3a2e1e; border-left: 4px solid #ff9800; margin: 10px 0; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; background: #2a2a2a; }
        th, td { border: 1px solid #444; padding: 12px; text-align: left; }
        th { background: #333; color: #fff; }
        tr:nth-child(even) { background: #222; }
        h1 { color: #e50914; }
        h2 { color: #fff; margin-top: 30px; }
        pre { background: #000; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 User Login Issues Fixer & Checker</h1>";

try {
    $conn = getDBConnection();
    echo "<div class='success'>✓ Connected to database successfully</div>";
    
    // Step 1: Check if users table exists
    echo "<h2>Step 1: Checking if users table exists</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'users'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ Users table exists</div>";
    } else {
        echo "<div class='error'>✗ Users table does NOT exist! Please run database.sql first.</div>";
        exit;
    }
    
    // Step 2: Check users table structure
    echo "<h2>Step 2: Checking users table structure</h2>";
    $result = $conn->query("SHOW COLUMNS FROM users");
    $columns = [];
    $has_banned = false;
    $has_max_devices = false;
    
    if ($result) {
        echo "<table><tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
            if ($row['Field'] == 'banned') $has_banned = true;
            if ($row['Field'] == 'max_devices') $has_max_devices = true;
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";
    }
    
    // Step 3: Add banned column if missing
    echo "<h2>Step 3: Checking banned column</h2>";
    if (!$has_banned) {
        echo "<div class='warning'>⚠ banned column missing, adding it...</div>";
        if ($conn->query("ALTER TABLE users ADD COLUMN banned TINYINT(1) DEFAULT 0")) {
            echo "<div class='success'>✓ Added banned column successfully</div>";
            $has_banned = true;
        } else {
            echo "<div class='error'>✗ Failed to add banned column: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ banned column exists</div>";
    }
    
    // Step 4: Fix NULL banned values
    echo "<h2>Step 4: Fixing NULL banned values</h2>";
    $result = $conn->query("UPDATE users SET banned = 0 WHERE banned IS NULL");
    if ($result) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            echo "<div class='success'>✓ Fixed {$affected} user(s) with NULL banned values</div>";
        } else {
            echo "<div class='info'>ℹ No NULL banned values found</div>";
        }
    } else {
        echo "<div class='error'>✗ Failed to fix NULL values: " . $conn->error . "</div>";
    }
    
    // Step 5: Ensure banned column is NOT NULL
    if ($has_banned) {
        echo "<h2>Step 5: Ensuring banned column is NOT NULL</h2>";
        if ($conn->query("ALTER TABLE users MODIFY COLUMN banned TINYINT(1) DEFAULT 0 NOT NULL")) {
            echo "<div class='success'>✓ banned column is now NOT NULL</div>";
        } else {
            echo "<div class='warning'>⚠ Could not modify banned column: " . $conn->error . "</div>";
        }
    }
    
    // Step 6: Add max_devices column if missing
    echo "<h2>Step 6: Checking max_devices column</h2>";
    if (!$has_max_devices) {
        echo "<div class='warning'>⚠ max_devices column missing, adding it...</div>";
        if ($conn->query("ALTER TABLE users ADD COLUMN max_devices INT DEFAULT 2")) {
            echo "<div class='success'>✓ Added max_devices column successfully</div>";
            $has_max_devices = true;
        } else {
            echo "<div class='error'>✗ Failed to add max_devices column: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ max_devices column exists</div>";
    }
    
    // Step 7: Check all users
    echo "<h2>Step 7: Checking all users</h2>";
    $result = $conn->query("SELECT id, username, email, banned, max_devices, 
                           LENGTH(password) as password_length,
                           CASE 
                               WHEN password IS NULL OR password = '' THEN 'NO PASSWORD'
                               WHEN password LIKE '\$2y\$%' THEN 'BCRYPT HASH'
                               WHEN password LIKE '\$2a\$%' THEN 'BCRYPT HASH'
                               ELSE 'OTHER FORMAT'
                           END as password_type
                           FROM users");
    
    if ($result && $result->num_rows > 0) {
        echo "<table><tr><th>ID</th><th>Username</th><th>Email</th><th>Banned</th><th>Max Devices</th><th>Password Length</th><th>Password Type</th></tr>";
        $users_with_issues = [];
        while ($row = $result->fetch_assoc()) {
            $has_issue = false;
            $issues = [];
            
            if ($row['password_type'] == 'NO PASSWORD') {
                $has_issue = true;
                $issues[] = "No password";
            }
            if ($row['banned'] == 1) {
                $issues[] = "Banned";
            }
            
            $row_class = $has_issue ? "style='background: #3a1e1e;'" : "";
            echo "<tr {$row_class}>
                    <td>{$row['id']}</td>
                    <td>{$row['username']}</td>
                    <td>{$row['email']}</td>
                    <td>" . ($row['banned'] == 1 ? 'YES' : 'NO') . "</td>
                    <td>{$row['max_devices']}</td>
                    <td>{$row['password_length']}</td>
                    <td>{$row['password_type']}</td>
                  </tr>";
            
            if ($has_issue) {
                $users_with_issues[] = $row['username'] . " (" . implode(", ", $issues) . ")";
            }
        }
        echo "</table>";
        
        if (count($users_with_issues) > 0) {
            echo "<div class='warning'>⚠ Users with issues: " . implode(", ", $users_with_issues) . "</div>";
        } else {
            echo "<div class='success'>✓ All users look good!</div>";
        }
    } else {
        echo "<div class='info'>ℹ No users found in database</div>";
    }
    
    // Step 8: Check user_sessions table
    echo "<h2>Step 8: Checking user_sessions table</h2>";
    $result = $conn->query("SHOW TABLES LIKE 'user_sessions'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'>✓ user_sessions table exists</div>";
    } else {
        echo "<div class='warning'>⚠ user_sessions table missing, creating it...</div>";
        $create_table = "CREATE TABLE IF NOT EXISTS user_sessions (
            id VARCHAR(128) PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(128) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            device_name VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_session_id (session_id)
        )";
        
        if ($conn->query($create_table)) {
            echo "<div class='success'>✓ Created user_sessions table successfully</div>";
        } else {
            echo "<div class='error'>✗ Failed to create user_sessions table: " . $conn->error . "</div>";
        }
    }
    
    // Step 9: Check active sessions
    echo "<h2>Step 9: Checking active sessions</h2>";
    $result = $conn->query("SELECT 
        u.id, 
        u.username, 
        u.email, 
        u.banned, 
        u.max_devices,
        COUNT(us.id) as active_sessions
    FROM users u
    LEFT JOIN user_sessions us ON u.id = us.user_id
    GROUP BY u.id, u.username, u.email, u.banned, u.max_devices");
    
    if ($result && $result->num_rows > 0) {
        echo "<table><tr><th>User ID</th><th>Username</th><th>Email</th><th>Banned</th><th>Max Devices</th><th>Active Sessions</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['username']}</td>
                    <td>{$row['email']}</td>
                    <td>" . ($row['banned'] == 1 ? 'YES' : 'NO') . "</td>
                    <td>{$row['max_devices']}</td>
                    <td>{$row['active_sessions']}</td>
                  </tr>";
        }
        echo "</table>";
    }
    
    // Final summary
    echo "<h2>✅ Summary</h2>";
    echo "<div class='success'>All checks and fixes completed!</div>";
    echo "<div class='info'>You can now try logging in. If issues persist, check the PHP error logs.</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>
