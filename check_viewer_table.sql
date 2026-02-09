-- Check if channel_viewers table exists
SHOW TABLES LIKE 'channel_viewers';

-- Show table structure (columns)
SHOW COLUMNS FROM channel_viewers;

-- Alternative: Describe table structure
DESCRIBE channel_viewers;

-- Show full table creation statement
SHOW CREATE TABLE channel_viewers;

-- Check current data in the table
SELECT * FROM channel_viewers ORDER BY last_ping DESC, last_seen DESC LIMIT 20;

-- Count total viewers
SELECT COUNT(*) as total_viewers FROM channel_viewers;

-- Count viewers by channel
SELECT 
    cv.channel_id,
    ltc.name as channel_name,
    COUNT(DISTINCT cv.session_id) as concurrent_viewers,
    MAX(cv.last_ping) as last_ping,
    MAX(cv.last_seen) as last_seen
FROM channel_viewers cv
LEFT JOIN live_tv_channels ltc ON cv.channel_id = ltc.id
GROUP BY cv.channel_id, ltc.name
ORDER BY concurrent_viewers DESC;

-- Check for old viewers (not pinged in last 30 seconds)
SELECT 
    COUNT(*) as old_viewers,
    MIN(last_ping) as oldest_ping,
    MIN(last_seen) as oldest_seen
FROM channel_viewers 
WHERE (last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND) OR last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND));

-- Check table indexes
SHOW INDEXES FROM channel_viewers;
