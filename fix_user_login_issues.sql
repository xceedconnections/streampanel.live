-- Fix User Login Issues
-- Run this to check and fix common login problems

USE streaming_portal;

-- 1. Check if users table exists
SHOW TABLES LIKE 'users';

-- 2. Check users table structure
SHOW COLUMNS FROM users;

-- 3. Check all users and their banned status (simplified query)
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
    LENGTH(password) as password_length,
    CASE 
        WHEN password IS NULL OR password = '' THEN 'NO PASSWORD'
        WHEN password LIKE '$2y$%' THEN 'BCRYPT HASH'
        WHEN password LIKE '$2a$%' THEN 'BCRYPT HASH'
        ELSE 'OTHER FORMAT'
    END as password_type
FROM users;

-- 4. Check if banned column exists, if not add it
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'banned';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN banned TINYINT(1) DEFAULT 0 NOT NULL',
    'SELECT "banned column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Fix NULL banned values (set to 0 if NULL)
UPDATE users SET banned = 0 WHERE banned IS NULL;

-- 6. Ensure banned column is TINYINT(1) with default 0
ALTER TABLE users 
MODIFY COLUMN banned TINYINT(1) DEFAULT 0 NOT NULL;

-- 7. Check if max_devices column exists, if not add it
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'users' 
AND COLUMN_NAME = 'max_devices';

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN max_devices INT DEFAULT 2',
    'SELECT "max_devices column already exists" as message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 8. Check for users with empty or NULL passwords (these cannot login)
SELECT id, username, email 
FROM users 
WHERE password IS NULL OR password = '';

-- 9. Verify user_sessions table exists
SHOW TABLES LIKE 'user_sessions';

-- 10. Create user_sessions table if it doesn't exist
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

-- 11. Check for any users that might have issues
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
