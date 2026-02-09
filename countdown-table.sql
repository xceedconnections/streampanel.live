-- SQL Query to create countdowns table
-- Run this query in your database

CREATE TABLE IF NOT EXISTS `countdowns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `target_datetime` datetime NOT NULL COMMENT 'Target date and time in Pakistan Standard Time (PKT)',
  `slug` varchar(255) NOT NULL UNIQUE COMMENT 'Unique slug for the countdown URL',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_slug` (`slug`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
