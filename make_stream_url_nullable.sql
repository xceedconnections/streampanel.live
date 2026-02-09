-- Make stream_url nullable in live_tv_channels table
-- This allows adding channels without streaming sources (sources can be added later)

ALTER TABLE live_tv_channels 
MODIFY COLUMN stream_url VARCHAR(500) NULL;
