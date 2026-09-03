<?php
/**
 * Viewport-pinned bottom navigation (phones, tablets, smart TVs).
 *
 * Modes:
 *   $stream_mobile_nav_mode = 'css'     → styles for <head>
 *   $stream_mobile_nav_mode = 'markup'  → nav at start of <body>
 *   default / 'full'                    → both (footer fallback)
 *
 * Uses 100svh / window.innerHeight (not bottom:0). Mobile Chrome/Safari
 * keep bottom:0 below the URL bar until the user scrolls.
 */
if (!defined('BASE_URL')) {
    return;
}
if (!empty($hide_mobile_bottom_nav)) {
    return;
}

$stream_mobile_nav_mode = $stream_mobile_nav_mode ?? 'full';

if ($stream_mobile_nav_mode !== 'css' && defined('STREAM_MOBILE_NAV_RENDERED')) {
    return;
}

$enable_movies = $enable_movies ?? (function_exists('isSectionEnabled') && isset($conn) ? isSectionEnabled($conn, 'movies') : true);
$enable_tv_shows = $enable_tv_shows ?? (function_exists('isSectionEnabled') && isset($conn) ? isSectionEnabled($conn, 'tv_shows') : true);
$enable_live_tv = $enable_live_tv ?? (function_exists('isSectionEnabled') && isset($conn) ? isSectionEnabled($conn, 'live_tv') : true);

$nav_script = $_SERVER['SCRIPT_NAME'] ?? '';
$nav_uri = $_SERVER['REQUEST_URI'] ?? '';
$is_nav_home = (basename($nav_script) === 'index.php' && strpos($nav_uri, '/movies') === false && strpos($nav_uri, '/tv-shows') === false && strpos($nav_uri, '/live-tv') === false && strpos($nav_uri, '/search') === false);
$is_nav_live_tv = (strpos($nav_uri, '/live-tv') !== false || strpos($nav_uri, '/watch-live-tv') !== false || preg_match('#/tv/[^/]+#', $nav_uri));
$is_nav_movies = (strpos($nav_uri, '/movies') !== false);
$is_nav_tv_shows = (strpos($nav_uri, '/tv-shows') !== false || strpos($nav_uri, '/tv-show/') !== false || strpos($nav_uri, '/watch-tv-show/') !== false);
$is_nav_search = (strpos($nav_uri, '/search') !== false);

if ($stream_mobile_nav_mode === 'css' || $stream_mobile_nav_mode === 'full'):
?>
<style id="stream-mobile-nav-css">
html {
    --stream-nav-h: 56px;
}
html.has-stream-mobile-nav,
body.has-stream-mobile-nav {
    padding-bottom: var(--stream-nav-h);
}
#stream-mobile-nav {
    display: flex !important;
    flex-direction: row;
    position: fixed !important;
    left: 0 !important;
    right: 0 !important;
    width: 100% !important;
    max-width: none !important;
    margin: 0 !important;
    top: auto !important;
    bottom: 0 !important;
    transform: none !important;
    -webkit-transform: none !important;
    z-index: 2147483000 !important;
    height: var(--stream-nav-h) !important;
    min-height: var(--stream-nav-h);
    padding: 0;
    align-items: stretch;
    justify-content: space-around;
    background: #0a0a0a !important;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    box-sizing: border-box !important;
    -webkit-backface-visibility: hidden;
    backface-visibility: hidden;
}
@supports not (height: 100svh) {
    #stream-mobile-nav {
        bottom: 0 !important;
        top: auto !important;
    }
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
    padding: 0 2px;
    -webkit-tap-highlight-color: transparent;
}
#stream-mobile-nav a svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
}
#stream-mobile-nav a.is-active,
#stream-mobile-nav a:hover,
#stream-mobile-nav a:focus {
    color: #fff;
    outline: none;
}
#stream-mobile-nav a.is-active span {
    color: #e50914;
}
@media (min-width: 1280px) and (hover: hover) and (pointer: fine) {
    html:not(.is-smart-tv) #stream-mobile-nav {
        display: none !important;
    }
    html:not(.is-smart-tv).has-stream-mobile-nav,
    html:not(.is-smart-tv) body.has-stream-mobile-nav {
        padding-bottom: 0 !important;
    }
}
</style>
<?php
endif;

if ($stream_mobile_nav_mode === 'css') {
    return;
}

define('STREAM_MOBILE_NAV_RENDERED', true);
?>
<nav id="stream-mobile-nav" aria-label="Site navigation">
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
    if (document.body && nav.parentNode !== document.body) {
        document.body.appendChild(nav);
    }
    var ua = navigator.userAgent || '';
    if (/SmartTV|SMART-TV|Tizen|Web0S|WebOS|BRAVIA|Android TV|AFT|HbbTV|Viera|VIDAA|Roku|CrKey|GoogleTV|MiTV|PlayStation|Xbox/i.test(ua)) {
        document.documentElement.classList.add('is-smart-tv');
        if (document.body) document.body.classList.add('is-smart-tv');
    }
    document.documentElement.classList.add('has-stream-mobile-nav');
    if (document.body) document.body.classList.add('has-stream-mobile-nav');

    var ticking = false;
    function pinNav() {
        var navH = 56;
        nav.style.setProperty('height', navH + 'px', 'important');
        nav.style.setProperty('padding-bottom', '0px', 'important');
        nav.style.setProperty('top', 'auto', 'important');

        var vv = window.visualViewport;
        var layoutH = window.innerHeight || document.documentElement.clientHeight || 0;
        var inset = 0;
        if (vv && layoutH) {
            /* Keep the bar on the VISIBLE bottom as the URL bar shows/hides on scroll */
            inset = Math.max(0, Math.round(layoutH - (vv.height + (vv.offsetTop || 0))));
        }
        nav.style.setProperty('bottom', inset + 'px', 'important');
    }
    function requestPin() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function () {
            ticking = false;
            pinNav();
        });
    }
    pinNav();
    window.addEventListener('pageshow', pinNav);
    window.addEventListener('orientationchange', function () {
        setTimeout(pinNav, 250);
    });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', requestPin);
        window.visualViewport.addEventListener('scroll', requestPin);
    }
    window.addEventListener('resize', requestPin);
})();
</script>
