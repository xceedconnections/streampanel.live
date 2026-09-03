<?php
/**
 * Dynamic robots.txt
 */
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$base = rtrim(BASE_URL, '/');

echo "User-agent: *\n";
echo "Allow: /\n\n";
echo "Disallow: /admin/\n";
echo "Disallow: /cron/\n";
echo "Disallow: /tools/\n";
echo "Disallow: /config/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /embed-source.php\n";
echo "Disallow: /embed-movie-source.php\n\n";
echo "Sitemap: {$base}/sitemap.xml\n";
echo "Sitemap: {$base}/sitemap-tv-channels.xml\n";
