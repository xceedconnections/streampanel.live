-- Create channel_viewers table if it doesn't exist
CREATE TABLE IF NOT EXISTS channel_viewers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_id INT NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(255) NOT NULL,
    last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_viewer (channel_id, session_id),
    INDEX idx_channel (channel_id),
    INDEX idx_user (user_id),
    INDEX idx_last_ping (last_ping),
    FOREIGN KEY (channel_id) REFERENCES live_tv_channels(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If table exists but has old structure, migrate it:
-- Step 1: Add last_ping column if it doesn't exist
ALTER TABLE channel_viewers 
ADD COLUMN IF NOT EXISTS last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER session_id;

-- Step 2: If last_seen exists but last_ping doesn't, copy data
UPDATE channel_viewers 
SET last_ping = last_seen 
WHERE last_ping IS NULL AND last_seen IS NOT NULL;

-- Step 3: Add session_id column if it doesn't exist
ALTER TABLE channel_viewers 
ADD COLUMN IF NOT EXISTS session_id VARCHAR(255) NOT NULL DEFAULT '' AFTER user_id;

-- Step 4: Update existing records with unique session_id if they're empty
UPDATE channel_viewers 
SET session_id = CONCAT('session_', COALESCE(user_id, 0), '_', id, '_', UNIX_TIMESTAMP()) 
WHERE session_id = '';

-- Step 5: Make user_id nullable if it's not already
ALTER TABLE channel_viewers 
MODIFY COLUMN user_id INT NULL;

-- Step 6: Drop old unique key if it exists (may need to check first)
-- ALTER TABLE channel_viewers DROP INDEX IF EXISTS unique_viewer;

-- Step 7: Add new unique key with session_id
ALTER TABLE channel_viewers 
ADD UNIQUE KEY IF NOT EXISTS unique_viewer (channel_id, session_id);
