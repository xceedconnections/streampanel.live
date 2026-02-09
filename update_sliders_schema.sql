-- Update Sliders System Schema
-- Run this to update the sliders system to support multiple slides per slider

USE streaming_portal;

-- Update sliders table to support display pages and auto-rotate
ALTER TABLE sliders 
ADD COLUMN IF NOT EXISTS display_on_home BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS display_on_movies BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS display_on_tv_shows BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS display_on_live_tv BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS auto_rotate BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS rotate_interval INT DEFAULT 5000;

-- Create slider_slides table for slides within sliders
CREATE TABLE IF NOT EXISTS slider_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slider_id INT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    image_url VARCHAR(500) NOT NULL,
    link_type ENUM('movie', 'tv_show', 'live_tv', 'external') DEFAULT 'external',
    link_id INT NULL,
    link_url VARCHAR(500),
    display_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (slider_id) REFERENCES sliders(id) ON DELETE CASCADE,
    INDEX idx_slider_id (slider_id),
    INDEX idx_display_order (display_order)
);

-- Migrate existing sliders data (if any) - convert single slider items to slides
-- This is optional, only if you have existing slider data
