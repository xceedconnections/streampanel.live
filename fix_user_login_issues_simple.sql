-- Fix User Login Issues - Simple Version
-- Run these queries ONE AT A TIME in phpMyAdmin

-- Step 1: Check if users table exists
SHOW TABLES LIKE 'users';

-- Step 2: Check users table structure
SHOW COLUMNS FROM users;

-- Step 3: Check all users (run this query separately)
SELECT 
    id, 
    username, 
    email, 
    banned,
    CASE 
        WHEN banned IS NULL THEN 'NULL'
        WHEN banned = 0 THEN 'NOT BANNED'
        WHEN banned = 1 THEN 'BANNED'
        ELSE 'UNKNOWN'
    END as ban_status,
    LENGTH(password) as password_length
FROM users;

-- Step 4: Add banned column if it doesn't exist (check first with SHOW COLUMNS)
-- Only run this if banned column is missing:
ALTER TABLE users ADD COLUMN banned TINYINT(1) DEFAULT 0;

-- Step 5: Fix NULL banned values
UPDATE users SET banned = 0 WHERE banned IS NULL;

-- Step 6: Make banned column NOT NULL
ALTER TABLE users MODIFY COLUMN banned TINYINT(1) DEFAULT 0 NOT NULL;

-- Step 7: Add max_devices column if it doesn't exist
-- Only run this if max_devices column is missing:
ALTER TABLE users ADD COLUMN max_devices INT DEFAULT 2;

-- Step 8: Check for users with empty passwords
SELECT id, username, email 
FROM users 
WHERE password IS NULL OR password = '';

-- Step 9: Create user_sessions table if it doesn't exist
CREATE TABLE IF NOT EXISTS user_sessions (
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
);

-- Step 10: Check user sessions (optional)
SELECT 
    u.id, 
    u.username, 
    u.email, 
    u.banned, 
    u.max_devices,
    COUNT(us.id) as active_sessions
FROM users u
LEFT JOIN user_sessions us ON u.id = us.user_id
GROUP BY u.id, u.username, u.email, u.banned, u.max_devices;
