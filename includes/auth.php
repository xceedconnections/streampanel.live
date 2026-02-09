<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Initialize database structure (ensure required tables and columns exist)
function initializeDatabase() {
    try {
        $conn = getDBConnection();
        
        // Check if banned column exists in users table, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'banned'");
        if ($check_column && $check_column->num_rows == 0) {
            @$conn->query("ALTER TABLE users ADD COLUMN banned BOOLEAN DEFAULT FALSE");
        }
        
        // Check if max_devices column exists in users table, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'max_devices'");
        if ($check_column && $check_column->num_rows == 0) {
            @$conn->query("ALTER TABLE users ADD COLUMN max_devices INT DEFAULT 2");
        }
        
        // Check if subscription_expires_at column exists in users table, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_expires_at'");
        if ($check_column && $check_column->num_rows == 0) {
            @$conn->query("ALTER TABLE users ADD COLUMN subscription_expires_at DATETIME NULL");
        }
        
        // Check if subscription_started_at column exists in users table, if not add it
        $check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'subscription_started_at'");
        if ($check_column && $check_column->num_rows == 0) {
            @$conn->query("ALTER TABLE users ADD COLUMN subscription_started_at DATETIME NULL");
        }
        
        // Create user_sessions table if it doesn't exist
        $check_table = $conn->query("SHOW TABLES LIKE 'user_sessions'");
        if ($check_table && $check_table->num_rows == 0) {
            @$conn->query("CREATE TABLE user_sessions (
                id VARCHAR(128) PRIMARY KEY,
                user_id INT NOT NULL,
                session_id VARCHAR(128) NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                device_name VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id),
                INDEX idx_session_id (session_id)
            )");
        }
    } catch (Exception $e) {
        // Silently fail - don't break login if initialization fails
        error_log("Database initialization error: " . $e->getMessage());
    }
}

// Validate user session against database
function validateUserSession() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // If this is a temp session (device limit scenario), allow it to continue
    if (isset($_SESSION['temp_session']) && $_SESSION['temp_session'] === true) {
        return true;
    }
    
    try {
    $conn = getDBConnection();
    $session_id = session_id();
    $user_id = $_SESSION['user_id'];
    
    // Check if session exists in database
    $stmt = $conn->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
        if (!$stmt) {
            // Table might not exist yet, allow session to continue
            return true;
        }
    $stmt->bind_param("si", $session_id, $user_id);
        if (!$stmt->execute()) {
            // Query failed, allow session to continue
            return true;
        }
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
            // Session record doesn't exist in database, but PHP session is still valid
            // This could happen if:
            // 1. Database was cleared
            // 2. Session was manually deleted (device limit)
            // 3. Session expired on server but PHP session is still valid
            
            // If user_id is still in session, recreate the session record
            // This allows users to stay logged in unless explicitly logged out
            if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                try {
                    // Recreate the session record in database
                    createUserSession($_SESSION['user_id'], $session_id, '');
                    // Continue with validation
                    $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ? AND user_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("si", $session_id, $user_id);
                        $stmt->execute();
                    }
                    return true;
                } catch (Exception $e) {
                    // If recreation fails, only then logout
                    error_log("Failed to recreate session record: " . $e->getMessage());
                    $_SESSION = array();
                    if (isset($_COOKIE[session_name()]) && !headers_sent()) {
                        setcookie(session_name(), '', time()-3600, '/');
                    }
                    session_destroy();
                    return false;
                }
            } else {
                // No user_id in session, session is invalid
        $_SESSION = array();
        if (isset($_COOKIE[session_name()]) && !headers_sent()) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        session_destroy();
        return false;
            }
    }
    
        // Update last activity - this keeps the session alive
    $stmt = $conn->prepare("UPDATE user_sessions SET last_activity = NOW() WHERE session_id = ? AND user_id = ?");
        if ($stmt) {
    $stmt->bind_param("si", $session_id, $user_id);
    $stmt->execute();
        }
        
        // Also update the PHP session cookie expiration to extend it
        // Only set cookie if headers haven't been sent yet
        if (isset($_COOKIE[session_name()]) && !headers_sent()) {
            setcookie(session_name(), session_id(), time() + 31536000, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
        }
    
    return true;
    } catch (Exception $e) {
        // If validation fails due to missing table, allow session to continue
        error_log("Session validation error: " . $e->getMessage());
        return true;
    }
}

// Check if user is logged in
function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    // If this is a temp session (device limit scenario), consider user logged in
    // Don't validate against database as temp sessions aren't in database yet
    if (isset($_SESSION['temp_session']) && $_SESSION['temp_session'] === true) {
        return true;
    }
    
    // Validate session against database on every check
    if (!validateUserSession()) {
        return false;
    }
    
    return true;
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_id']);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        // Store the current page to redirect back after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Normalize BASE_URL to remove /tv or /api if present (for correct redirect)
        $login_url = BASE_URL;
        $login_url = rtrim($login_url, '/');
        // Remove /tv and /api from the path to ensure we redirect to root login
        $login_url = preg_replace('#/(tv|api)(/|$)#', '', $login_url);
        $login_url = rtrim($login_url, '/') . '/login.php';
        
        header('Location: ' . $login_url);
        exit();
    }
    
    // If user has exceeded device limit (temp session), redirect to device management
    if (isset($_SESSION['temp_session']) && $_SESSION['temp_session'] === true) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'manage-devices.php') {
            header('Location: ' . BASE_URL . '/manage-devices.php?device_limit=1');
        exit();
        }
    }
}

// Require admin login
function requireAdminLogin() {
    if (!isAdminLoggedIn()) {
        require_once __DIR__ . '/../config/config.php';
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit();
    }
}

// Get user's active sessions count
function getUserActiveSessionsCount($user_id) {
    try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_sessions WHERE user_id = ?");
        if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'] ?? 0;
        }
    } catch (Exception $e) {
        // If table doesn't exist or query fails, return 0 (no active sessions)
        error_log("Get active sessions count error: " . $e->getMessage());
    }
    return 0;
}

// Create or update user session
function createUserSession($user_id, $session_id, $device_name = '') {
    $conn = getDBConnection();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // Use provided device name, or try to get from user agent, or use default
    if (empty($device_name)) {
        // Try to get device name from user agent
        if (preg_match('/(iPhone|iPad|Android|Windows|Mac|Linux)/i', $user_agent, $matches)) {
            $device_name = $matches[1];
        } else {
            $device_name = 'Unknown Device';
        }
    }
    
    // Sanitize device name
    $device_name = htmlspecialchars(strip_tags(trim($device_name)));
    if (empty($device_name)) {
        $device_name = 'Unknown Device';
    }
    
    // Check if session already exists
    $stmt = $conn->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
    if (!$stmt) {
        error_log("Failed to prepare session check: " . $conn->error);
        throw new Exception("Failed to check existing session");
    }
    
    $stmt->bind_param("si", $session_id, $user_id);
    if (!$stmt->execute()) {
        error_log("Failed to execute session check: " . $stmt->error);
        throw new Exception("Failed to check existing session");
    }
    
    $existing = $stmt->get_result()->fetch_assoc();
    
    if ($existing) {
        // Update existing session (update device name if provided)
        if (!empty($device_name)) {
            $stmt = $conn->prepare("UPDATE user_sessions SET ip_address = ?, user_agent = ?, device_name = ?, last_activity = NOW() WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE user_sessions SET ip_address = ?, user_agent = ?, last_activity = NOW() WHERE id = ?");
        }
        
        if (!$stmt) {
            error_log("Failed to prepare session update: " . $conn->error);
            throw new Exception("Failed to update session");
        }
        
        if (!empty($device_name)) {
            $stmt->bind_param("ssss", $ip_address, $user_agent, $device_name, $existing['id']);
        } else {
            $stmt->bind_param("sss", $ip_address, $user_agent, $existing['id']);
        }
        
        if (!$stmt->execute()) {
            error_log("Failed to execute session update: " . $stmt->error);
            throw new Exception("Failed to update session");
        }
    } else {
        // Create new session
        $session_record_id = uniqid('sess_', true);
        $stmt = $conn->prepare("INSERT INTO user_sessions (id, user_id, session_id, ip_address, user_agent, device_name) VALUES (?, ?, ?, ?, ?, ?)");
        
        if (!$stmt) {
            error_log("Failed to prepare session insert: " . $conn->error);
            throw new Exception("Failed to create session");
        }
        
        $stmt->bind_param("sissss", $session_record_id, $user_id, $session_id, $ip_address, $user_agent, $device_name);
        
        if (!$stmt->execute()) {
            error_log("Failed to execute session insert: " . $stmt->error);
            throw new Exception("Failed to create session: " . $stmt->error);
        }
    }
}

// User login
function loginUser($username, $password, $device_name = '') {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, email, password, full_name, subscription_type, max_devices, banned FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            error_log("Login error: Failed to prepare statement - " . $conn->error);
            return false;
        }
    $stmt->bind_param("ss", $username, $username);
        if (!$stmt->execute()) {
            error_log("Login error: Failed to execute statement - " . $stmt->error);
            return false;
        }
    $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            error_log("Login failed: User not found - Username/Email: " . $username);
            return false;
        }
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
            // Check if user is banned (MySQL BOOLEAN returns 0 or 1, check explicitly)
            // Handle both integer and string values from MySQL
            $banned_value = $user['banned'] ?? 0;
            $is_banned = ($banned_value == 1 || $banned_value === '1' || $banned_value === true);
            
            if ($is_banned) {
            // Delete all user sessions from all devices
            removeAllUserSessions($user['id']);
            // Destroy current session
            $_SESSION = array();
            if (isset($_COOKIE[session_name()]) && !headers_sent()) {
                setcookie(session_name(), '', time()-3600, '/');
            }
            session_destroy();
            return 'banned';
        }
        
            // Verify password
            if (!isset($user['password']) || empty($user['password'])) {
                error_log("Login error: User has no password set - User ID: " . $user['id']);
                return false;
            }
            
            // Verify password
            $password_verified = password_verify($password, $user['password']);
            
            if (!$password_verified) {
                error_log("Login failed: Password verification failed for user: " . $username . " (ID: " . $user['id'] . ")");
                return false;
            }
            
            // Password is correct, proceed with login
            $max_devices = $user['max_devices'] ?? 2;
            $current_session_id = session_id();
            
            // Check if current session already exists in database
            $stmt = $conn->prepare("SELECT id FROM user_sessions WHERE session_id = ? AND user_id = ?");
            $existing_session = null;
            if ($stmt) {
            $stmt->bind_param("si", $current_session_id, $user['id']);
                if ($stmt->execute()) {
            $existing_session = $stmt->get_result()->fetch_assoc();
                }
            }
            
            // Count active sessions BEFORE creating new one (excluding current if exists)
            $active_sessions_before = getUserActiveSessionsCount($user['id']);
            
            // Check if this is a new session and we're at/over the limit
            $is_new_session = !$existing_session;
            $at_device_limit = $active_sessions_before >= $max_devices;
            
            // If it's a new session and we're at the limit, we still need to log them in
            // so they can access the device management page, but we'll flag it
            // However, we should NOT create a new session record if we're already at the limit
            if ($is_new_session && $at_device_limit) {
                // Ensure session is started
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                
                // Log them in (set session variables) but DON'T create a new session record
                // This way they can access manage-devices page
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['subscription_type'] = $user['subscription_type'] ?? 'free';
                $_SESSION['device_limit_exceeded'] = true;
                $_SESSION['device_limit_max'] = $max_devices;
                $_SESSION['temp_session'] = true; // Mark as temporary session (not in database)
                $_SESSION['temp_device_name'] = $device_name; // Store device name for later
                
                error_log("Device limit reached for user: " . $username . " (ID: " . $user['id'] . ") - Active sessions: " . $active_sessions_before . ", Max: " . $max_devices . " - Logged in with temp session. Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
                return 'device_limit';
            }
            
            // Create/update session and login the user
            // Only reaches here if: existing session OR under device limit
            try {
            createUserSession($user['id'], $current_session_id, $device_name);
            } catch (Exception $e) {
                error_log("ERROR: Could not create user session: " . $e->getMessage());
                // Don't continue if session creation fails - this is critical
                return false;
            }
            
            // Set session variables AFTER session is created in database
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'] ?? '';
            $_SESSION['subscription_type'] = $user['subscription_type'] ?? 'free';
            
            // Clear any temp session flag
            unset($_SESSION['temp_session']);
            
            // Set session cookie to persist for 1 year (only if headers haven't been sent)
            if (!headers_sent()) {
                setcookie(session_name(), session_id(), time() + 31536000, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
            }
            
            error_log("Login successful: User " . $username . " (ID: " . $user['id'] . ") logged in successfully");
            return true;
        }
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
    return false;
}

// Check if user has active subscription
function hasActiveSubscription() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $conn = getDBConnection();
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT subscription_type, subscription_expires_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $db_subscription_type = $user['subscription_type'] ?? 'free';
        $subscription_expires_at = $user['subscription_expires_at'] ?? null;
        
        // Check if subscription expired
        $has_active_expiry = false;
        if ($subscription_expires_at) {
            $expires_at = new DateTime($subscription_expires_at);
            $now = new DateTime();
            $has_active_expiry = $expires_at > $now;
    }
    
        // User is premium only if subscription_type is 'premium' AND (no expiry OR expiry is in future)
        $is_premium = ($db_subscription_type === 'premium') && ($subscription_expires_at === null || $has_active_expiry);
        
        // If user is no longer premium, invalidate session and update session data
        if (!$is_premium) {
            // Update session to reflect free status
            $_SESSION['subscription_type'] = 'free';
            
            // Invalidate all user sessions if subscription expired or changed to free
            if ($db_subscription_type !== 'premium' || ($subscription_expires_at && !$has_active_expiry)) {
                // Delete all sessions except current one (user will need to re-login)
                $current_session_id = session_id();
                $delete_stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ? AND session_id != ?");
                $delete_stmt->bind_param("is", $user_id, $current_session_id);
                $delete_stmt->execute();
            }
        } else {
            // Update session to reflect premium status
            $_SESSION['subscription_type'] = 'premium';
        }
        
        return $is_premium;
    }
    
    // Fallback: check session (but this should be updated by above logic)
    $session_sub_type = $_SESSION['subscription_type'] ?? 'free';
    if ($session_sub_type !== 'premium') {
        $_SESSION['subscription_type'] = 'free';
    }
    return $session_sub_type === 'premium';
}

// Admin login
function loginAdmin($username, $password) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, username, email, password, full_name FROM admins WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_full_name'] = $admin['full_name'];
            return true;
        }
    }
    return false;
}

// User registration
function registerUser($username, $email, $password, $full_name = '') {
    try {
    $conn = getDBConnection();
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if (!$stmt) {
            error_log("Registration error: Failed to prepare statement - " . $conn->error);
            return false;
        }
    $stmt->bind_param("ss", $username, $email);
        if (!$stmt->execute()) {
            error_log("Registration error: Failed to execute statement - " . $stmt->error);
            return false;
        }
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // User already exists
    }
    
        // Get max devices default from settings (with fallback if table doesn't exist)
    $max_devices_default = 2;
        try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'max_devices_default'");
            if ($stmt && $stmt->execute()) {
    $setting_result = $stmt->get_result();
    if ($setting_row = $setting_result->fetch_assoc()) {
        $max_devices_default = intval($setting_row['setting_value']);
        if ($max_devices_default < 1) $max_devices_default = 2;
                }
            }
        } catch (Exception $e) {
            // Settings table might not exist, use default value
            error_log("Settings table query error (using default): " . $e->getMessage());
    }
    
    // Hash password and insert user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, max_devices) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            error_log("Registration error: Failed to prepare insert statement - " . $conn->error);
            return false;
        }
    $stmt->bind_param("ssssi", $username, $email, $hashed_password, $full_name, $max_devices_default);
    
    if ($stmt->execute()) {
        return true;
        } else {
            error_log("Registration error: Failed to execute insert - " . $stmt->error);
            return false;
    }
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
    return false;
    }
}

// Remove user session from database
function removeUserSession($session_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
    $stmt->bind_param("s", $session_id);
    $stmt->execute();
}

// Remove all user sessions (for banned users)
function removeAllUserSessions($user_id) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("DELETE FROM user_sessions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}

// Logout
function logout() {
    if (isset($_SESSION['user_id']) && isset($_SESSION)) {
        $session_id = session_id();
        removeUserSession($session_id);
    }
    session_destroy();
    require_once __DIR__ . '/../config/config.php';
    header('Location: ' . BASE_URL . '/');
    exit();
}
?>
