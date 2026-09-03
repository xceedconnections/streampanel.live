<?php
/**
 * Sticky mobile bottom navigation — CSS only, flush to bottom.
 * No visualViewport / scroll listeners (those caused jump/glitch).
 * No env(safe-area) on layout height (dynamic on some Android browsers).
 */
if (!empty($minimal_site_footer) || !empty($hide_mobile_bottom_nav)) {
    return;
}
if (!defined('BASE_URL')) {
    return;
}

$enable_movies = $enable_movies ?? (function_exists('isSectionEnabled') && isset($conn) ? isSectionEnabled($conn, 'movies') : true);
$enable_tv_shows = $enable_tv_shows ?? (function_exists('isSectionEnabled') && isset($conn) ? isSectionEnabled($conn, 'tv_shows') : true);
$enable_live_tv = $enable_live_tv ?? (function_exists('isSectionEnabled') && isset($conn) ? isSectionEnabled($conn, 'live_tv') : true);

$nav_script = $_SERVER['SCRIPT_NAME'] ?? '';
$nav_uri = $_SERVER['REQUEST_URI'] ?? '';
$is_nav_home = (basename($nav_script) === 'index.php' && strpos($nav_uri, '/movies') === false && strpos($nav_uri, '/tv-shows') === false && strpos($nav_uri, '/live-tv') === false && strpos($nav_uri, '/search') === false);
$is_nav_movies = (strpos($nav_uri, '/movies') !== false);
$is_nav_tv_shows = (strpos($nav_uri, '/tv-shows') !== false);
$is_nav_live_tv = (strpos($nav_uri, '/live-tv') !== false || strpos($nav_uri, '/watch-live-tv') !== false);
$is_nav_search = (strpos($nav_uri, '/search') !== false);
?>
<style id="stream-mobile-nav-css">
@media (max-width: 767px) {
    html.has-stream-mobile-nav,
    body.has-stream-mobile-nav {
        padding-bottom: 56px !important;
    }
    #stream-mobile-nav {
        display: flex !important;
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        top: auto !important;
        margin: 0 !important;
        transform: none !important;
        -webkit-transform: none !important;
        z-index: 2147483000 !important;
        height: 56px !important;
        min-height: 56px !important;
        max-height: 56px !important;
        padding: 0 !important;
        align-items: stretch;
        justify-content: space-around;
        background: #0a0a0a !important;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        box-sizing: border-box !important;
        -webkit-backface-visibility: hidden;
        backface-visibility: hidden;
    }
    #stream-mobile-nav a {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        color: #9ca3af;
        text-decoration: none;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.02em;
        line-height: 1;
        padding: 0;
    }
    #stream-mobile-nav a svg {
        width: 20px;
        height: 20px;
        flex-shrink: 0;
    }
    #stream-mobile-nav a.is-active,
    #stream-mobile-nav a:hover {
        color: #fff;
    }
    #stream-mobile-nav a.is-active span {
        color: #e50914;
    }
}
@media (min-width: 768px) {
    #stream-mobile-nav {
        display: none !important;
    }
}
</style>
<nav id="stream-mobile-nav" aria-label="Mobile navigation">
    <a href="<?php echo BASE_URL; ?>/" class="<?php echo $is_nav_home ? 'is-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/></svg>
        <span>Home</span>
    </a>
    <?php if ($enable_movies): ?>
    <a href="<?php echo BASE_URL; ?>/movies" class="<?php echo $is_nav_movies ? 'is-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m10 9 5 3-5 3z"/></svg>
        <span>Movies</span>
    </a>
    <?php endif; ?>
    <?php if ($enable_tv_shows): ?>
    <a href="<?php echo BASE_URL; ?>/tv-shows" class="<?php echo $is_nav_tv_shows ? 'is-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="13" rx="2"/><path d="M8 3h8M12 3v4"/></svg>
        <span>TV Shows</span>
    </a>
    <?php endif; ?>
    <?php if ($enable_live_tv): ?>
    <a href="<?php echo BASE_URL; ?>/live-tv" class="<?php echo $is_nav_live_tv ? 'is-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M5 5a10 10 0 0 1 0 14M19 5a10 10 0 0 0 0 14"/></svg>
        <span>Live TV</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo BASE_URL; ?>/search" class="<?php echo $is_nav_search ? 'is-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3-3"/></svg>
        <span>Search</span>
    </a>
</nav>
<script>
(function () {
    var nav = document.getElementById('stream-mobile-nav');
    if (!nav) return;
    // Keep nav as a direct body child so no ancestor transform breaks position:fixed
    if (nav.parentNode !== document.body) {
        document.body.appendChild(nav);
    }
    document.documentElement.classList.add('has-stream-mobile-nav');
    document.body.classList.add('has-stream-mobile-nav');
})();
</script>
