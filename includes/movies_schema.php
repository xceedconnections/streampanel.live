<?php
/**
 * Runtime schema upgrades for movies TMDB features.
 */

function ensureMoviesSchema($conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $columns = [
        'cast_data' => "TEXT NULL COMMENT 'JSON cast from TMDB'",
        'director' => "VARCHAR(255) NULL",
        'genres' => "TEXT NULL COMMENT 'JSON genre names'",
        'tags' => "TEXT NULL COMMENT 'JSON tags e.g. Hindi Dubbed'",
        'quality_label' => "VARCHAR(50) NULL COMMENT 'Badge on poster e.g. HD'",
        'backdrop' => "VARCHAR(500) NULL COMMENT 'TMDB banner/backdrop URL'",
        'download_links' => "TEXT NULL COMMENT 'JSON download links'",
        'trailer_url' => "VARCHAR(500) NULL COMMENT 'YouTube or embed trailer URL'",
        'show_in_slider' => "TINYINT(1) DEFAULT 0 COMMENT 'Show in homepage slider'",
        'featured' => "TINYINT(1) DEFAULT 0",
        'is_active' => "TINYINT(1) DEFAULT 1",
        'is_free' => "TINYINT(1) DEFAULT 1",
        'is_premium' => "TINYINT(1) DEFAULT 0",
        'pre_roll_ad_id' => "INT NULL",
        'mid_roll_ad_id' => "INT NULL",
        'end_roll_ad_id' => "INT NULL",
        'loop_ad_id' => "INT NULL",
        'loop_interval' => "INT NULL",
        'banner_ad_id' => "INT NULL",
        'popup_ad_id' => "INT NULL",
        'intro_ad_id' => "INT NULL",
    ];

    foreach ($columns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM movies LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE movies ADD COLUMN `$column` $definition");
        }
    }

    $done = true;

    repairInvalidMovieSlugs($conn);
}

function repairInvalidMovieSlugs($conn): void
{
    if (!function_exists('getUniqueSlug')) {
        require_once __DIR__ . '/../admin/includes/functions.php';
    }

    $result = $conn->query("SELECT id, title, slug FROM movies WHERE slug IS NULL OR slug = '' OR slug = '0'");
    if (!$result) {
        return;
    }

    while ($row = $result->fetch_assoc()) {
        $slug = getUniqueSlug($conn, 'movies', $row['title'], (int) $row['id']);
        $stmt = $conn->prepare('UPDATE movies SET slug = ? WHERE id = ?');
        if ($stmt) {
            $id = (int) $row['id'];
            $stmt->bind_param('si', $slug, $id);
            $stmt->execute();
        }
    }
}
