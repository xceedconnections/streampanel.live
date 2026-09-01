<?php
/**
 * Auto-generate .htaccess based on detected app base path.
 */

function generateHtaccessContent(string $basePath): string
{
    $basePath = rtrim($basePath, '/');
    $rewriteBase = ($basePath === '') ? '/' : $basePath . '/';
    $uriPrefix = ($basePath === '') ? '' : $basePath;
    $uri = preg_quote($uriPrefix, '#');

    $diagScripts = 'fix_user_login_check|_db_check|reset_admin_password|force_fix_tv_login|check_tv_login_setting|test_tv_login|fix_tv_login_setting|debug_login_settings|fix_login_settings|test_tv_shows_login|test_login_debug|test_viewer_tracking|test_tv_show_detail';
    $cleanPages = 'live-tv|movies|movie-detail|tv-shows|profile|login|register|watch|manage-devices|tv-show-detail|report|logout|search|about-us|contact|careers|terms-of-use|privacy-policy|cookie-policy';

    $lines = [];
    $lines[] = 'RewriteEngine On';
    $lines[] = "# APP_BASE: {$basePath}";
    $lines[] = "RewriteBase {$rewriteBase}";
    $lines[] = '';
    $lines[] = '# IMPORTANT: Handle API endpoints FIRST - rewrite requests without .php back to .php';
    $lines[] = '# This must be before the redirect rule to prevent .php from being stripped';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/tv/api/viewer_tracker(\\?.*)?\$ [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
    $lines[] = 'RewriteRule ^tv/api/viewer_tracker$ tv/api/viewer_tracker.php [L,QSA]';
    $lines[] = '';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/shows/api/viewer_tracker(\\?.*)?\$ [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
    $lines[] = 'RewriteRule ^shows/api/viewer_tracker$ shows/api/viewer_tracker.php [L,QSA]';
    $lines[] = '';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/movies/api/viewer_tracker(\\?.*)?\$ [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
    $lines[] = 'RewriteRule ^movies/api/viewer_tracker$ movies/api/viewer_tracker.php [L,QSA]';
    $lines[] = '';
    $lines[] = '# Allow API endpoints with .php extension to work directly';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/tv/api/.*\\.php [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/shows/api/.*\\.php [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/movies/api/.*\\.php [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Allow embed-source.php to work directly (needed for HTML embed sources)';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/embed-source\\.php [NC]";
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Allow embed-movie-source.php to work directly (needed for movie HTML embed sources)';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/embed-movie-source\\.php [NC]";
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Allow proxy scripts to work directly (needed for HLS proxy)';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/proxy/.*\\.php [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Handle sitemap XML requests - rewrite .xml to .php';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/sitemap-tv-channels\\.xml\$ [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
    $lines[] = 'RewriteRule ^sitemap-tv-channels\\.xml$ sitemap-tv-channels.php [L,QSA]';
    $lines[] = '';
    $lines[] = '# Allow sitemap PHP files to work directly';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/sitemap.*\\.php [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Allow diagnostic/utility scripts with .php extension to work directly';
    $lines[] = "RewriteCond %{REQUEST_URI} ^{$uri}/({$diagScripts})\\.php [NC]";
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Redirect old .php URLs to clean URLs (301 permanent redirect)';
    $lines[] = "RewriteCond %{THE_REQUEST} \\s{$uri}/([^?\\s]*?)\\.php(\\?[^\\s]*)?\\s [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/index\\.php [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/tv/api/viewer_tracker [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/shows/api/viewer_tracker [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/movies/api/viewer_tracker [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/admin/ [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/api/ [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/embed-source\\.php [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/embed-movie-source\\.php [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/countdown\\.php [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/sitemap [NC]";
    $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/proxy/ [NC]";
    foreach (explode('|', $diagScripts) as $script) {
        $lines[] = "RewriteCond %{THE_REQUEST} !\\s{$uri}/{$script}\\.php [NC]";
    }
    $lines[] = 'RewriteRule ^ %1%2 [R=301,L]';
    $lines[] = '';
    $lines[] = '# Handle root URL (index.php)';
    $lines[] = 'RewriteRule ^$ index.php [L]';
    $lines[] = '';
    $lines[] = '# Redirect /watch-live-tv/{slug} to tv-channel.php (full player) with slug parameter';
    $lines[] = 'RewriteRule ^watch-live-tv/([a-z0-9-]+)/?$ tv/tv-channel.php?slug=$1 [L,QSA]';
    $lines[] = '';
    $lines[] = '# Redirect /tv/{slug} to channel info page with slug parameter';
    $lines[] = 'RewriteRule ^tv/([a-z0-9-]+)/?$ tv/channel-info.php?slug=$1 [L,QSA]';
    $lines[] = '';
    $lines[] = '# Redirect /tv-show/{slug} to tv-show-detail.php with slug parameter';
    $lines[] = 'RewriteRule ^tv-show/([a-z0-9-]+)/?$ tv-show-detail.php?slug=$1 [L,QSA]';
    $lines[] = '';
    $lines[] = '# Redirect /watch-tv-show/{show-slug}/{episode-info} to watch.php';
    $lines[] = 'RewriteRule ^watch-tv-show/([a-z0-9-]+)/(s[0-9]+e[0-9]+)/?$ watch.php?type=tv_episode&show_slug=$1&episode_info=$2 [L,QSA]';
    $lines[] = '';
    $lines[] = '# Countdown pages: /countdown/{slug}';
    $lines[] = 'RewriteRule ^countdown/([a-z0-9-]+)/?$ countdown.php?slug=$1 [L,QSA]';
    $lines[] = '';
    $lines[] = '# Movie listing page (must be before slug routes — movies/ folder exists on disk)';
    $lines[] = 'RewriteRule ^movies/?$ movies.php [L,QSA]';
    $lines[] = '';
    $lines[] = '# Movie pages: /movies/{slug} and /movies/{slug}/watch';
    $lines[] = 'RewriteRule ^movies/([a-z0-9-]+)/watch/?$ movies/movie-watch.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^movies/([a-z0-9-]+)/?$ movie-detail.php?slug=$1 [L,QSA]';
    $lines[] = '';
    $lines[] = '# Legacy movie URL redirects';
    $lines[] = 'RewriteRule ^movie/([a-z0-9-]+)/?$ movies/$1 [R=301,L]';
    $lines[] = 'RewriteRule ^watch-movie/([a-z0-9-]+)/?$ movies/$1/watch [R=301,L]';
    $lines[] = '';
    $lines[] = '# Handle clean URLs without .php extension';
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
    $lines[] = "RewriteRule ^({$cleanPages})\$ \$1.php [L,QSA]";
    $lines[] = '';
    $lines[] = '# Prevent directory listing';
    $lines[] = 'Options -Indexes';
    $lines[] = '';
    $lines[] = '# Protect sensitive files';
    $lines[] = '<FilesMatch "\\.(sql|md|log)\$">';
    $lines[] = '    Require all denied';
    $lines[] = '</FilesMatch>';

    return implode("\n", $lines) . "\n";
}

function syncHtaccess(string $basePath): void
{
    if (php_sapi_name() === 'cli') {
        return;
    }

    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    if ($basePath !== '' && ($basePath[0] !== '/' || preg_match('#^[A-Za-z]:#', $basePath))) {
        return;
    }

    $htaccessPath = dirname(__DIR__) . '/.htaccess';
    $expectedMarker = '# APP_BASE: ' . $basePath;
    $current = is_file($htaccessPath) ? (string) file_get_contents($htaccessPath) : '';

    if (strpos($current, $expectedMarker) !== false) {
        return;
    }

    $content = generateHtaccessContent($basePath);
    if (is_writable($htaccessPath) || (!is_file($htaccessPath) && is_writable(dirname($htaccessPath)))) {
        @file_put_contents($htaccessPath, $content);
    }
}
