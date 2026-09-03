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

/**
 * Runtime schema upgrades for TV shows image/TMDB fields.
 */
function ensureTvShowsSchema($conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $columns = [
        'backdrop' => "VARCHAR(500) NULL COMMENT 'TMDB banner/backdrop URL'",
        'tmdb_id' => "INT NULL",
        'trailer_url' => "VARCHAR(500) NULL",
        'tags' => "TEXT NULL COMMENT 'JSON tags e.g. Hindi Dubbed'",
        'quality_label' => "VARCHAR(50) NULL COMMENT 'Badge on poster e.g. HD'",
    ];

    foreach ($columns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM tv_shows LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE tv_shows ADD COLUMN `$column` $definition");
        }
    }

    // Widen image URL columns so full TMDB links are stored
    foreach (['poster', 'thumbnail', 'backdrop'] as $col) {
        $info = $conn->query("SHOW COLUMNS FROM tv_shows LIKE '" . $conn->real_escape_string($col) . "'");
        $row = $info ? $info->fetch_assoc() : null;
        $type = strtolower($row['Type'] ?? '');
        if ($type !== '' && (strpos($type, 'varchar(255)') !== false || strpos($type, 'varchar(191)') !== false)) {
            $conn->query("ALTER TABLE tv_shows MODIFY COLUMN `$col` VARCHAR(500) NULL");
        }
    }

    $done = true;
}

function ensureLiveTvChannelsSchema($conn): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $columns = [
        'tags' => "TEXT NULL COMMENT 'JSON tags shown on channel card e.g. HD, Sports'",
        'quality_label' => "VARCHAR(50) NULL COMMENT 'Channel quality badge e.g. HD, FHD, 4K'",
    ];

    foreach ($columns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM live_tv_channels LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE live_tv_channels ADD COLUMN `$column` $definition");
        }
    }

    $done = true;
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
