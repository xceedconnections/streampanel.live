-- Create User History and Favorites Tables
-- Run this to ensure tables exist for user history and favorites

USE streaming_portal;

-- Watch History table (if not exists)
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_type ENUM('movie', 'tv_episode', 'tv_show', 'live_tv') NOT NULL,
    content_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    progress INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_content (user_id, content_type, content_id),
    INDEX idx_watched_at (watched_at)
);

-- Favorites table (if not exists)
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    content_type ENUM('movie', 'tv_show', 'live_tv') NOT NULL,
    content_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, content_type, content_id),
    INDEX idx_user_id (user_id)
);

-- Add content_type 'tv_show' to watch_history if it doesn't exist
-- Note: This might require dropping and recreating the table if the ENUM doesn't include 'tv_show'
-- For now, we'll use ALTER TABLE to modify the ENUM if possible
