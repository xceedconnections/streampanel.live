-- SQL Script to Add loop_interval Column to ads Table
-- This column is used for loop ads to specify how often the ad appears (separate from duration)
-- Duration = how long the ad plays
-- loop_interval = how often the ad appears (e.g., 60 = every 1 minute)

-- Add loop_interval column if it doesn't exist
ALTER TABLE ads 
ADD COLUMN IF NOT EXISTS loop_interval INT NULL COMMENT 'For loop ads: how often to show (in seconds). Duration is how long ad plays.';

-- Optional: Update existing loop ads to have a default interval of 60 seconds if they don't have one
-- UPDATE ads SET loop_interval = 60 WHERE type = 'loop' AND loop_interval IS NULL;
