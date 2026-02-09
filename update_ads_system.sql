-- SQL Script to Update Database for Ad System
-- Run this script once to add all necessary columns and update the ads table

-- Update ads table to add intro-ad type, loop type, and skipable field
ALTER TABLE ads 
MODIFY COLUMN type ENUM('pre-roll', 'mid-roll', 'post-roll', 'banner', 'popup', 'intro-ad', 'loop') NOT NULL;

ALTER TABLE ads 
ADD COLUMN IF NOT EXISTS skipable BOOLEAN DEFAULT TRUE;

-- Ensure logo and content_type columns exist (if not already added)
ALTER TABLE ads 
ADD COLUMN IF NOT EXISTS logo VARCHAR(500) NULL;

ALTER TABLE ads 
ADD COLUMN IF NOT EXISTS content_type ENUM('image', 'video', 'html') DEFAULT 'html';

-- Add loop_interval column for loop ads (how often to show, separate from duration)
ALTER TABLE ads 
ADD COLUMN IF NOT EXISTS loop_interval INT NULL COMMENT 'For loop ads: how often to show (in seconds). Duration is how long ad plays.';

-- Add ad selection fields to live_tv_channels table
ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS pre_roll_ad_id INT NULL;

ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS mid_roll_ad_id INT NULL;

ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS end_roll_ad_id INT NULL;

ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS loop_ad_id INT NULL;

ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS loop_interval INT NULL;

ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS banner_ad_id INT NULL;

ALTER TABLE live_tv_channels 
ADD COLUMN IF NOT EXISTS popup_ad_id INT NULL;

-- Set default skipable to false for intro-ads (if any exist)
UPDATE ads SET skipable = 0 WHERE type = 'intro-ad';

-- Optional: Add foreign key constraints (commented out - uncomment if you want referential integrity)
-- ALTER TABLE live_tv_channels 
-- ADD CONSTRAINT fk_pre_roll_ad FOREIGN KEY (pre_roll_ad_id) REFERENCES ads(id) ON DELETE SET NULL;

-- ALTER TABLE live_tv_channels 
-- ADD CONSTRAINT fk_mid_roll_ad FOREIGN KEY (mid_roll_ad_id) REFERENCES ads(id) ON DELETE SET NULL;

-- ALTER TABLE live_tv_channels 
-- ADD CONSTRAINT fk_end_roll_ad FOREIGN KEY (end_roll_ad_id) REFERENCES ads(id) ON DELETE SET NULL;

-- ALTER TABLE live_tv_channels 
-- ADD CONSTRAINT fk_loop_ad FOREIGN KEY (loop_ad_id) REFERENCES ads(id) ON DELETE SET NULL;
