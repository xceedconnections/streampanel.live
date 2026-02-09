<?php
/**
 * Script to generate slugs for existing TV channels that don't have slugs
 * Run this once to update all existing channels
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();

// Get all channels without slugs
$channels = $conn->query("SELECT id, name FROM live_tv_channels WHERE slug IS NULL OR slug = ''")->fetch_all(MYSQLI_ASSOC);

$updated = 0;
foreach ($channels as $channel) {
    $slug = getUniqueSlug($conn, 'live_tv_channels', $channel['name'], $channel['id']);
    $stmt = $conn->prepare("UPDATE live_tv_channels SET slug = ? WHERE id = ?");
    $stmt->bind_param("si", $slug, $channel['id']);
    if ($stmt->execute()) {
        $updated++;
        echo "Updated channel '{$channel['name']}' with slug: {$slug}\n";
    }
}

echo "\nTotal channels updated: {$updated}\n";
