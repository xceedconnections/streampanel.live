<?php
/**
 * Mobile bottom navigation bar markup.
 * Requires BASE_URL and section flags ($enable_movies, etc.).
 */
if (!empty($mobile_footer_nav_rendered)) {
    return;
}

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

if (!isset($conn)) {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../admin/includes/functions.php';
    $conn = getDBConnection();
}

if (!isset($enable_movies) && function_exists('isSectionEnabled')) {
    $enable_movies = isSectionEnabled($conn, 'movies');
}
if (!isset($enable_tv_shows) && function_exists('isSectionEnabled')) {
    $enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
}
if (!isset($enable_live_tv) && function_exists('isSectionEnabled')) {
    $enable_live_tv = isSectionEnabled($conn, 'live_tv');
}

$enable_movies = $enable_movies ?? true;
$enable_tv_shows = $enable_tv_shows ?? true;
$enable_live_tv = $enable_live_tv ?? true;

if (!isset($isAndroidTV)) {
    $isAndroidTV = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android TV|AFT|BRAVIA|MiTV|SmartTV|GoogleTV|Tizen|Web0S|HbbTV)/i', $_SERVER['HTTP_USER_AGENT']);
}

if ($isAndroidTV) {
    return;
}

if (!isset($mobile_nav_path)) {
    $mobile_nav_path = $_SERVER['REQUEST_URI'] ?? '';
}
if (!isset($current_page)) {
    $current_page = basename($_SERVER['PHP_SELF']);
}

$mobile_footer_nav_rendered = true;
?>
<nav class="mobile-footer-nav" id="mobile-footer-nav" aria-label="Mobile navigation">
    <a href="<?php echo BASE_URL; ?>/" class="nav-item <?php echo ($current_page === 'index.php' || preg_match('#/(index\.php)?(\?|$)#', $mobile_nav_path)) ? 'active' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span>Home</span>
    </a>
    <?php if ($enable_movies): ?>
    <a href="<?php echo BASE_URL; ?>/movies" class="nav-item <?php echo ($current_page === 'movies.php' || strpos($mobile_nav_path, '/movies') !== false || $current_page === 'movie-detail.php' || $current_page === 'movie-watch.php') ? 'active' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
        <span>Movies</span>
    </a>
    <?php endif; ?>
    <?php if ($enable_tv_shows): ?>
    <a href="<?php echo BASE_URL; ?>/tv-shows" class="nav-item <?php echo ($current_page === 'tv-shows.php' || strpos($mobile_nav_path, '/tv-shows') !== false || strpos($mobile_nav_path, '/tv-show/') !== false || $current_page === 'tv-show-detail.php') ? 'active' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
        </svg>
        <span>TV Shows</span>
    </a>
    <?php endif; ?>
    <?php if ($enable_live_tv): ?>
    <a href="<?php echo BASE_URL; ?>/live-tv" class="nav-item <?php echo ($current_page === 'live-tv.php' || strpos($mobile_nav_path, '/live-tv') !== false || strpos($mobile_nav_path, '/tv/') !== false || $current_page === 'tv-channel.php') ? 'active' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="6" width="20" height="12" rx="2"></rect>
            <circle cx="12" cy="12" r="2"></circle>
            <path d="M6 12h.01M18 12h.01"></path>
        </svg>
        <span>Live TV</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo BASE_URL; ?>/search" class="nav-item <?php echo ($current_page === 'search.php' || strpos($mobile_nav_path, '/search') !== false) ? 'active' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
        <span>Search</span>
    </a>
</nav>
