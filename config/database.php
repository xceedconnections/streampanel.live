<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'streaming_portal');

// Session configuration (must be set before session_start())
if (session_status() === PHP_SESSION_NONE) {
    // Set session cookie to persist for 1 year (31536000 seconds)
    ini_set('session.cookie_lifetime', 31536000); // 1 year
    ini_set('session.gc_maxlifetime', 31536000); // 1 year - how long session data is stored on server
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax'); // Prevent CSRF while allowing normal usage
    
    // Start session with custom cookie parameters
    session_set_cookie_params([
        'lifetime' => 31536000, // 1 year
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_start();
}

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        // Set charset to UTF-8 to support special characters
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}
?>
