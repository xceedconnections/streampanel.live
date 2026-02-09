-- Fix incorrect logo paths in live_tv_channels table
-- Removes /api from logo paths that were incorrectly saved during Excel import

UPDATE live_tv_channels 
SET logo = REPLACE(REPLACE(logo, '/api/', '/'), '/api', '')
WHERE logo LIKE '%/api/%' OR logo LIKE '%/api';
