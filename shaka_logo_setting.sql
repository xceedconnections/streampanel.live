-- SQL Query to manually set Shaka Player Logo
-- This is optional - the logo is automatically stored when uploaded via admin panel
-- Use this only if you need to manually set or update the logo path

-- Insert or update the Shaka Player logo setting
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES ('shaka_player_logo', 'uploads/shaka-player/your-logo.png', 'text', 'Shaka Player Logo Path')
ON DUPLICATE KEY UPDATE 
    setting_value = 'uploads/shaka-player/your-logo.png',
    updated_at = CURRENT_TIMESTAMP;

-- To remove the logo, set it to empty string:
-- UPDATE settings SET setting_value = '' WHERE setting_key = 'shaka_player_logo';

-- To check current logo setting:
-- SELECT setting_value FROM settings WHERE setting_key = 'shaka_player_logo';
