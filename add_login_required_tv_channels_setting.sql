-- Add login_required_tv_channels setting
-- This is optional - the setting will be created automatically when saved in admin panel
-- Run this only if you want to pre-initialize the setting

USE streaming_portal;

INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('login_required_tv_channels', '0', 'boolean', 'Login Required to View TV Channels')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
