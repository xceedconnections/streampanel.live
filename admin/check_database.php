<?php
/**
 * Database Column Checker
 * Run this to check if all required columns exist
 */
require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();

echo "<h2>Database Column Check</h2>";

$tables_to_check = [
    'movies' => ['sources', 'is_active', 'is_free', 'is_premium', 'tmdb_id', 'slug', 'show_in_slider'],
    'tv_shows' => ['sources', 'is_active', 'is_free', 'is_premium', 'tmdb_id', 'slug', 'show_in_slider'],
    'tv_episodes' => ['sources'],
    'live_tv_channels' => ['sources', 'country', 'language', 'is_active', 'is_free', 'is_premium', 'current_viewers', 'play_count', 'slug', 'show_in_slider'],
    'users' => ['subscription_expires_at', 'subscription_started_at'],
    'categories' => ['description', 'tmdb_genre_id', 'display_order', 'is_active']
];

$missing_columns = [];

foreach ($tables_to_check as $table => $columns) {
    echo "<h3>Table: $table</h3>";
    $result = $conn->query("SHOW COLUMNS FROM `$table`");
    $existing_columns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
        }
    }
    
    foreach ($columns as $column) {
        if (!in_array($column, $existing_columns)) {
            $missing_columns[] = "ALTER TABLE `$table` ADD COLUMN IF NOT EXISTS `$column` ";
            echo "<span style='color:red;'>❌ Missing: $column</span><br>";
        } else {
            echo "<span style='color:green;'>✅ Exists: $column</span><br>";
        }
    }
}

if (!empty($missing_columns)) {
    echo "<h3>Missing Columns - Run admin_tables.sql to fix</h3>";
    echo "<p>Please run the admin_tables.sql file to add missing columns.</p>";
} else {
    echo "<h3 style='color:green;'>✅ All columns exist!</h3>";
}
