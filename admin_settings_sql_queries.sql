-- SQL Queries for Admin Settings
-- Use these queries to manually set or check the login requirement settings

-- ============================================
-- CHECK CURRENT SETTINGS
-- ============================================
-- View all login requirement settings
SELECT setting_key, setting_value 
FROM settings 
WHERE setting_key IN ('login_required_tv_channels', 'login_required_tv_shows', 'login_required_movies')
ORDER BY setting_key;

-- ============================================
-- SET LOGIN NOT REQUIRED (Value = '0')
-- ============================================

-- Set TV Channels login NOT required
INSERT INTO settings (setting_key, setting_value) 
VALUES ('login_required_tv_channels', '0')
ON DUPLICATE KEY UPDATE setting_value = '0';

-- Set TV Shows login NOT required
INSERT INTO settings (setting_key, setting_value) 
VALUES ('login_required_tv_shows', '0')
ON DUPLICATE KEY UPDATE setting_value = '0';

-- Set Movies login NOT required
INSERT INTO settings (setting_key, setting_value) 
VALUES ('login_required_movies', '0')
ON DUPLICATE KEY UPDATE setting_value = '0';

-- ============================================
-- SET LOGIN REQUIRED (Value = '1')
-- ============================================

-- Set TV Channels login REQUIRED
INSERT INTO settings (setting_key, setting_value) 
VALUES ('login_required_tv_channels', '1')
ON DUPLICATE KEY UPDATE setting_value = '1';

-- Set TV Shows login REQUIRED
INSERT INTO settings (setting_key, setting_value) 
VALUES ('login_required_tv_shows', '1')
ON DUPLICATE KEY UPDATE setting_value = '1';

-- Set Movies login REQUIRED
INSERT INTO settings (setting_key, setting_value) 
VALUES ('login_required_movies', '1')
ON DUPLICATE KEY UPDATE setting_value = '1';

-- ============================================
-- DELETE SETTINGS (will use default '0')
-- ============================================

-- Delete TV Channels login requirement setting
DELETE FROM settings WHERE setting_key = 'login_required_tv_channels';

-- Delete TV Shows login requirement setting
DELETE FROM settings WHERE setting_key = 'login_required_tv_shows';

-- Delete Movies login requirement setting
DELETE FROM settings WHERE setting_key = 'login_required_movies';

-- ============================================
-- FIX ALL SETTINGS TO NOT REQUIRE LOGIN
-- ============================================
-- Run this to ensure all login requirements are disabled
INSERT INTO settings (setting_key, setting_value) VALUES 
('login_required_tv_channels', '0'),
('login_required_tv_shows', '0'),
('login_required_movies', '0')
ON DUPLICATE KEY UPDATE setting_value = '0';

-- ============================================
-- FORCE FIX - DELETE AND RECREATE (if above doesn't work)
-- ============================================
-- If the above doesn't work, try deleting and recreating:
DELETE FROM settings WHERE setting_key IN ('login_required_tv_channels', 'login_required_tv_shows', 'login_required_movies');
INSERT INTO settings (setting_key, setting_value) VALUES 
('login_required_tv_channels', '0'),
('login_required_tv_shows', '0'),
('login_required_movies', '0');
