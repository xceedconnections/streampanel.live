-- Movies TMDB upgrade
USE streaming_portal;

ALTER TABLE movies ADD COLUMN IF NOT EXISTS cast_data TEXT NULL COMMENT 'JSON cast from TMDB';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS director VARCHAR(255) NULL;
ALTER TABLE movies ADD COLUMN IF NOT EXISTS genres TEXT NULL COMMENT 'JSON genre names';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS tags TEXT NULL COMMENT 'JSON tags e.g. Hindi Dubbed';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS quality_label VARCHAR(50) NULL COMMENT 'Badge on poster e.g. HD';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS backdrop VARCHAR(500) NULL COMMENT 'TMDB banner/backdrop URL';
ALTER TABLE movies ADD COLUMN IF NOT EXISTS download_links TEXT NULL COMMENT 'JSON download links';

INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('tmdb_api_key', '', 'text', 'TMDB API Key for movie metadata fetch')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
