-- Run once in phpMyAdmin or: mysql -u USER -p DATABASE < admin/sql/sliders_schema.sql

CREATE TABLE IF NOT EXISTS sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT '',
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    display_on_home TINYINT(1) DEFAULT 0,
    display_on_movies TINYINT(1) DEFAULT 0,
    display_on_tv_shows TINYINT(1) DEFAULT 0,
    display_on_live_tv TINYINT(1) DEFAULT 0,
    auto_rotate TINYINT(1) DEFAULT 1,
    rotate_interval INT DEFAULT 5000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS slider_slides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slider_id INT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT,
    image_url VARCHAR(500) NOT NULL DEFAULT '',
    link_type ENUM('movie', 'tv_show', 'live_tv', 'external') DEFAULT 'external',
    link_id INT NULL,
    link_url VARCHAR(500) DEFAULT NULL,
    display_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slider_id (slider_id),
    INDEX idx_display_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
