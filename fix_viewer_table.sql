-- Fix viewer table structure - Run these queries one by one

-- 1. Check current structure
SHOW COLUMNS FROM channel_viewers;

-- 2. Add last_ping column if missing (and last_seen exists)
ALTER TABLE channel_viewers 
ADD COLUMN IF NOT EXISTS last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- If the above doesn't work (MySQL < 8.0), use this instead:
-- Check if column exists first, then add:
-- ALTER TABLE channel_viewers ADD COLUMN last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- 3. Copy data from last_seen to last_ping if needed
UPDATE channel_viewers 
SET last_ping = last_seen 
WHERE last_ping IS NULL AND last_seen IS NOT NULL;

-- 4. Add session_id if missing
ALTER TABLE channel_viewers 
ADD COLUMN IF NOT EXISTS session_id VARCHAR(255) NOT NULL DEFAULT '';

-- 5. Update empty session_ids
UPDATE channel_viewers 
SET session_id = CONCAT('session_', COALESCE(user_id, 0), '_', id, '_', UNIX_TIMESTAMP()) 
WHERE session_id = '' OR session_id IS NULL;

-- 6. Make user_id nullable
ALTER TABLE channel_viewers 
MODIFY COLUMN user_id INT NULL;

-- 7. Remove old unique constraint if it exists (check first)
SHOW INDEXES FROM channel_viewers WHERE Key_name = 'unique_viewer';

-- If unique_viewer exists without session_id, drop it:
-- ALTER TABLE channel_viewers DROP INDEX unique_viewer;

-- 8. Add new unique constraint with session_id
ALTER TABLE channel_viewers 
ADD UNIQUE KEY unique_viewer (channel_id, session_id);

-- 9. Verify final structure
SHOW COLUMNS FROM channel_viewers;
SHOW INDEXES FROM channel_viewers;
