<?php
/**
 * TV Channels Sitemap Generator
 * Generates XML sitemap for all active TV channels
 * 
 * Access via: https://streampanel.live/sitemap-tv-channels.xml
 * or: https://streampanel.live/sitemap-tv-channels.php
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();

// Get ALL active TV channels (not filtering by sources)
$query = "SELECT id, slug, name 
          FROM live_tv_channels 
          WHERE is_active = 1 
          ORDER BY name ASC";

$channels = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Set XML content type
header('Content-Type: application/xml; charset=utf-8');

// Generate sitemap XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($channels as $channel) {
    // Build channel URL
    if (!empty($channel['slug'])) {
        $channel_url = BASE_URL . '/tv/' . htmlspecialchars($channel['slug'], ENT_XML1, 'UTF-8');
    } else {
        $channel_url = BASE_URL . '/tv/tv-channel.php?id=' . intval($channel['id']);
    }
    
    // Use current date as last modified (since updated_at column doesn't exist)
    $lastmod = date('Y-m-d');
    
    // Channel name for reference (in comment)
    $channel_name = htmlspecialchars($channel['name'] ?? 'TV Channel', ENT_XML1, 'UTF-8');
    
    // Output URL entry with channel name in comment
    echo "  <!-- " . $channel_name . " -->\n";
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($channel_url, ENT_XML1, 'UTF-8') . "</loc>\n";
    echo "    <lastmod>" . htmlspecialchars($lastmod, ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo "    <changefreq>daily</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
?>
