-- Additional tables for admin panel features
-- Run this after the main database.sql

USE streaming_portal;

-- Coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    duration_days INT DEFAULT 30,
    max_uses INT DEFAULT 1,
    used_count INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Coupon redemptions table
CREATE TABLE IF NOT EXISTS coupon_redemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Ads table
CREATE TABLE IF NOT EXISTS ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    type ENUM('pre-roll', 'mid-roll', 'post-roll', 'banner', 'popup') NOT NULL,
    content TEXT NOT NULL,
    duration INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    start_date DATETIME NULL,
    end_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sliders table
CREATE TABLE IF NOT EXISTS sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(500) NOT NULL,
    link_url VARCHAR(500),
    link_type ENUM('movie', 'tv_show', 'live_tv', 'external') DEFAULT 'external',
    link_id INT NULL,
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Reports table for broken links/content
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    content_type ENUM('movie', 'tv_show', 'tv_episode', 'live_tv') NOT NULL,
    content_id INT NOT NULL,
    source_id VARCHAR(100) NULL,
    issue_type ENUM('broken_link', 'wrong_content', 'quality_issue', 'other') DEFAULT 'broken_link',
    description TEXT,
    status ENUM('pending', 'resolved', 'dismissed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Update users table to add subscription fields
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS subscription_expires_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS subscription_started_at DATETIME NULL;

-- Update live_tv_channels to support multiple sources and premium content
ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS sources TEXT NULL COMMENT 'JSON array of stream sources',
ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT 'US',
ADD COLUMN IF NOT EXISTS language VARCHAR(10) DEFAULT 'en',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS is_free BOOLEAN DEFAULT TRUE COMMENT 'Free content available to all logged-in users',
ADD COLUMN IF NOT EXISTS is_premium BOOLEAN DEFAULT FALSE COMMENT 'Premium content requires subscription',
ADD COLUMN IF NOT EXISTS current_viewers INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS play_count INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS show_in_slider BOOLEAN DEFAULT FALSE COMMENT 'Show in homepage slider';

-- Update movies to support multiple sources and premium content
ALTER TABLE movies 
ADD COLUMN IF NOT EXISTS sources TEXT NULL COMMENT 'JSON array of stream sources',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS is_free BOOLEAN DEFAULT TRUE COMMENT 'Free content available to all logged-in users',
ADD COLUMN IF NOT EXISTS is_premium BOOLEAN DEFAULT FALSE COMMENT 'Premium content requires subscription',
ADD COLUMN IF NOT EXISTS tmdb_id INT NULL,
ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS show_in_slider BOOLEAN DEFAULT FALSE COMMENT 'Show in homepage slider';

-- Update tv_shows to support multiple sources and premium content
ALTER TABLE tv_shows 
ADD COLUMN IF NOT EXISTS sources TEXT NULL COMMENT 'JSON array of stream sources',
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS is_free BOOLEAN DEFAULT TRUE COMMENT 'Free content available to all logged-in users',
ADD COLUMN IF NOT EXISTS is_premium BOOLEAN DEFAULT FALSE COMMENT 'Premium content requires subscription',
ADD COLUMN IF NOT EXISTS tmdb_id INT NULL,
ADD COLUMN IF NOT EXISTS slug VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS show_in_slider BOOLEAN DEFAULT FALSE COMMENT 'Show in homepage slider';

-- Update tv_episodes to support multiple sources
ALTER TABLE tv_episodes 
ADD COLUMN IF NOT EXISTS sources TEXT NULL COMMENT 'JSON array of stream sources';

-- Update categories to support more fields
ALTER TABLE categories 
ADD COLUMN IF NOT EXISTS description TEXT NULL,
ADD COLUMN IF NOT EXISTS tmdb_genre_id INT NULL,
ADD COLUMN IF NOT EXISTS display_order INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'StreamFlix', 'text', 'Site Name'),
('site_description', 'Netflix-style streaming portal', 'text', 'Site Description'),
('maintenance_mode', '0', 'boolean', 'Maintenance Mode'),
('registration_enabled', '1', 'boolean', 'Allow User Registration'),
('shaka_player_config', '{}', 'json', 'Shaka Player Configuration')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
