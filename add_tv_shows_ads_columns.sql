-- SQL Script to Add Advertisement Columns to tv_shows Table
-- Run this script once to add all necessary columns for TV show ads

-- Add ad selection fields to tv_shows table
ALTER TABLE tv_shows 
ADD COLUMN pre_roll_ad_id INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN mid_roll_ad_id INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN end_roll_ad_id INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN loop_ad_id INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN loop_interval INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN banner_ad_id INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN popup_ad_id INT NULL;

ALTER TABLE tv_shows 
ADD COLUMN intro_ad_id INT NULL;

-- Optional: Add comments to document the columns
ALTER TABLE tv_shows 
MODIFY COLUMN pre_roll_ad_id INT NULL COMMENT 'Pre-roll ad ID (plays before episode starts)';

ALTER TABLE tv_shows 
MODIFY COLUMN mid_roll_ad_id INT NULL COMMENT 'Mid-roll ad ID (plays during episode)';

ALTER TABLE tv_shows 
MODIFY COLUMN end_roll_ad_id INT NULL COMMENT 'End-roll ad ID (plays after episode ends)';

ALTER TABLE tv_shows 
MODIFY COLUMN loop_ad_id INT NULL COMMENT 'Loop ad ID (plays every N seconds during playback)';

ALTER TABLE tv_shows 
MODIFY COLUMN loop_interval INT NULL COMMENT 'Loop ad interval in seconds (if not set, uses ad duration)';

ALTER TABLE tv_shows 
MODIFY COLUMN banner_ad_id INT NULL COMMENT 'Banner ad ID (displays as overlay)';

ALTER TABLE tv_shows 
MODIFY COLUMN popup_ad_id INT NULL COMMENT 'Popup ad ID (displays as modal)';

ALTER TABLE tv_shows 
MODIFY COLUMN intro_ad_id INT NULL COMMENT 'Intro ad ID (plays to everyone before episode)';
