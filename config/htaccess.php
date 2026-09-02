<?php
/**
 * Auto-generate a path-agnostic .htaccess that works at domain root
 * (https://streampanel.live/) and in a subdirectory (http://localhost/stream/).
 */

function generateHtaccessContent(string $basePath = ''): string
{
    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');

    $diagScripts = 'fix_user_login_check|_db_check|reset_admin_password|force_fix_tv_login|check_tv_login_setting|test_tv_login|fix_tv_login_setting|debug_login_settings|fix_login_settings|test_tv_shows_login|test_login_debug|test_viewer_tracking|test_tv_show_detail';
    $cleanPages = 'live-tv|movies|movie-detail|tv-shows|profile|login|register|watch|manage-devices|tv-show-detail|report|logout|search|about-us|contact|careers|terms-of-use|privacy-policy|cookie-policy';

    $lines = [];
    $lines[] = 'RewriteEngine On';
    $lines[] = '# Path-agnostic rules: works at / and in any subdirectory (e.g. /stream/)';
    $lines[] = "# APP_BASE: {$basePath}";
    $lines[] = '# No RewriteBase — Apache resolves relative to this folder automatically';
    $lines[] = '';
    $lines[] = '# Movie listing MUST come before the directory pass-through.';
    $lines[] = '# A physical movies/ folder exists on disk; without this, /movies/ 404s.';
    $lines[] = 'RewriteRule ^movies/?$ movies.php [L,QSA]';
    $lines[] = '';
    $lines[] = '# API endpoints without .php';
    $lines[] = 'RewriteRule ^tv/api/viewer_tracker/?$ tv/api/viewer_tracker.php [L,QSA]';
    $lines[] = 'RewriteRule ^shows/api/viewer_tracker/?$ shows/api/viewer_tracker.php [L,QSA]';
    $lines[] = 'RewriteRule ^movies/api/viewer_tracker/?$ movies/api/viewer_tracker.php [L,QSA]';
    $lines[] = 'RewriteRule ^api/search-suggest/?$ api/search-suggest.php [L,QSA]';
    $lines[] = '';
    $lines[] = '# Sitemap XML -> PHP';
    $lines[] = 'RewriteRule ^sitemap-tv-channels\\.xml$ sitemap-tv-channels.php [L,QSA]';
    $lines[] = '';
    $lines[] = '# Pass through real files and directories (except handled routes above)';
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -f [OR]';
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} -d';
    $lines[] = 'RewriteRule ^ - [L]';
    $lines[] = '';
    $lines[] = '# Redirect old .php URLs to clean URLs (keeps current install path)';
    $lines[] = 'RewriteCond %{THE_REQUEST} \\s/+(.+?)\\.php([?\\s]) [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/index\\.php[?\\s] [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/admin/ [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/api/ [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/tv/api/ [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/shows/api/ [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/movies/api/ [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/embed-source\\.php [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/embed-movie-source\\.php [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/countdown\\.php [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/sitemap [NC]';
    $lines[] = 'RewriteCond %{THE_REQUEST} !/proxy/ [NC]';
    foreach (explode('|', $diagScripts) as $script) {
        $lines[] = "RewriteCond %{THE_REQUEST} !/{$script}\\.php [NC]";
    }
    $lines[] = 'RewriteRule ^ /%1 [R=301,L,NE]';
    $lines[] = '';
    $lines[] = '# Home';
    $lines[] = 'RewriteRule ^$ index.php [L]';
    $lines[] = '';
    $lines[] = '# Pretty routes';
    $lines[] = 'RewriteRule ^watch-live-tv/([a-z0-9-]+)/?$ tv/tv-channel.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^tv/([a-z0-9-]+)/?$ tv/channel-info.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^tv-show/([a-z0-9-]+)/?$ tv-show-detail.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^watch-tv-show/([a-z0-9-]+)/(s[0-9]+e[0-9]+)/?$ watch.php?type=tv_episode&show_slug=$1&episode_info=$2 [L,QSA]';
    $lines[] = 'RewriteRule ^countdown/([a-z0-9-]+)/?$ countdown.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^actors/([a-z0-9-]+)/?$ actor.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^movies/([a-z0-9-]+)/watch/?$ movies/movie-watch.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^movies/([a-z0-9-]+)/?$ movie-detail.php?slug=$1 [L,QSA]';
    $lines[] = 'RewriteRule ^movie/([a-z0-9-]+)/?$ movies/$1 [R=301,L]';
    $lines[] = 'RewriteRule ^watch-movie/([a-z0-9-]+)/?$ movies/$1/watch [R=301,L]';
    $lines[] = '';
    $lines[] = '# Clean page URLs without .php';
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
    $lines[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
    $lines[] = "RewriteRule ^({$cleanPages})/?$ \$1.php [L,QSA]";
    $lines[] = '';
    $lines[] = 'Options -Indexes';
    $lines[] = '';
    $lines[] = '<FilesMatch "\\.(sql|md|log)$">';
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
    $portableMarker = '# Path-agnostic rules:';
    $current = is_file($htaccessPath) ? (string) file_get_contents($htaccessPath) : '';

    if (strpos($current, $expectedMarker) !== false && strpos($current, $portableMarker) !== false) {
        return;
    }

    $content = generateHtaccessContent($basePath);
    if (is_writable($htaccessPath) || (!is_file($htaccessPath) && is_writable(dirname($htaccessPath)))) {
        @file_put_contents($htaccessPath, $content);
    }
}
