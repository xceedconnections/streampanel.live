<?php
/**
 * Full site sitemap (movies, TV shows, live TV, static pages).
 * Access: /sitemap.xml or /sitemap.php
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$conn = getDBConnection();
$today = date('Y-m-d');
$base = rtrim(BASE_URL, '/');

$urls = [];

$add = static function (string $loc, string $lastmod, string $changefreq, string $priority) use (&$urls) {
    if ($loc === '') {
        return;
    }
    $urls[] = [
        'loc' => $loc,
        'lastmod' => $lastmod,
        'changefreq' => $changefreq,
        'priority' => $priority,
    ];
};

$add($base . '/', $today, 'daily', '1.0');
$add($base . '/movies', $today, 'daily', '0.9');
$add($base . '/tv-shows', $today, 'daily', '0.9');
$add($base . '/live-tv', $today, 'daily', '0.9');
$add($base . '/search', $today, 'weekly', '0.5');
$add($base . '/about-us', $today, 'monthly', '0.3');
$add($base . '/contact', $today, 'monthly', '0.3');
$add($base . '/privacy-policy', $today, 'yearly', '0.2');
$add($base . '/terms-of-use', $today, 'yearly', '0.2');
$add($base . '/cookie-policy', $today, 'yearly', '0.2');

try {
    if (isSectionEnabled($conn, 'movies')) {
        ensureMoviesSchema($conn);
        $result = $conn->query("SELECT id, title, slug, updated_at, created_at FROM movies WHERE (is_active = 1 OR is_active IS NULL) ORDER BY id DESC");
        if ($result) {
            while ($movie = $result->fetch_assoc()) {
                $lastmod = !empty($movie['updated_at']) ? date('Y-m-d', strtotime($movie['updated_at'])) : (!empty($movie['created_at']) ? date('Y-m-d', strtotime($movie['created_at'])) : $today);
                $detail = getMovieDetailUrl($movie, $conn);
                $watch = getMovieWatchUrl($movie, 0, $conn);
                $add($detail, $lastmod, 'weekly', '0.8');
                $add($watch, $lastmod, 'weekly', '0.7');
            }
        }
    }
} catch (Throwable $e) {
    error_log('Sitemap movies error: ' . $e->getMessage());
}

try {
    if (isSectionEnabled($conn, 'tv_shows')) {
        $result = $conn->query("SELECT id, slug, updated_at, created_at FROM tv_shows ORDER BY id DESC");
        if ($result) {
            while ($show = $result->fetch_assoc()) {
                $lastmod = !empty($show['updated_at']) ? date('Y-m-d', strtotime($show['updated_at'])) : (!empty($show['created_at']) ? date('Y-m-d', strtotime($show['created_at'])) : $today);
                if (!empty($show['slug'])) {
                    $add($base . '/tv-show/' . rawurlencode($show['slug']), $lastmod, 'weekly', '0.8');
                } else {
                    $add($base . '/tv-show-detail?id=' . (int) $show['id'], $lastmod, 'weekly', '0.7');
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('Sitemap tv shows error: ' . $e->getMessage());
}

try {
    if (isSectionEnabled($conn, 'live_tv')) {
        $result = $conn->query("SELECT id, slug, name FROM live_tv_channels WHERE is_active = 1 ORDER BY name ASC");
        if ($result) {
            while ($channel = $result->fetch_assoc()) {
                if (!empty($channel['slug'])) {
                    $add($base . '/watch-live-tv/' . rawurlencode($channel['slug']), $today, 'daily', '0.8');
                    $add($base . '/tv/' . rawurlencode($channel['slug']), $today, 'weekly', '0.6');
                } else {
                    $add($base . '/tv/tv-channel.php?id=' . (int) $channel['id'], $today, 'daily', '0.7');
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log('Sitemap live tv error: ' . $e->getMessage());
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $entry) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1, 'UTF-8') . "</loc>\n";
    echo '    <lastmod>' . htmlspecialchars($entry['lastmod'], ENT_XML1, 'UTF-8') . "</lastmod>\n";
    echo '    <changefreq>' . htmlspecialchars($entry['changefreq'], ENT_XML1, 'UTF-8') . "</changefreq>\n";
    echo '    <priority>' . htmlspecialchars($entry['priority'], ENT_XML1, 'UTF-8') . "</priority>\n";
    echo "  </url>\n";
}
echo '</urlset>' . "\n";
