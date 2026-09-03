<?php
/**
 * Mobile bottom navigation (single include — place before </body>).
 */
if (!empty($stream_mobile_nav_rendered)) {
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

$nav_path_only = parse_url($mobile_nav_path, PHP_URL_PATH) ?? '/';
$nav_path_norm = rtrim($nav_path_only, '/');
if ($nav_path_norm === '') {
    $nav_path_norm = '/';
}

$is_nav_home = ($nav_path_norm === '/' || $nav_path_norm === '/index.php');
$is_nav_movies = (
    strpos($nav_path_only, '/movies') === 0
    || in_array($current_page, ['movies.php', 'movie-detail.php', 'movie-watch.php'], true)
);
$is_nav_tv_shows = (
    strpos($nav_path_only, '/tv-shows') === 0
    || strpos($nav_path_only, '/tv-show/') === 0
    || $current_page === 'tv-show-detail.php'
);
$is_nav_live_tv = (
    strpos($nav_path_only, '/live-tv') === 0
    || strpos($nav_path_only, '/watch-live-tv/') === 0
    || (strpos($nav_path_only, '/tv/') === 0 && strpos($nav_path_only, '/tv-shows') !== 0)
    || in_array($current_page, ['live-tv.php', 'tv-channel.php'], true)
);
$is_nav_search = (
    strpos($nav_path_only, '/search') === 0
    || $current_page === 'search.php'
);

$stream_mobile_nav_rendered = true;
?>
<style id="stream-mobile-nav-css">
@media (max-width: 767px) {
    :root {
        --stream-mobile-nav-h: 60px;
    }
    body.stream-mobile-nav-active {
        padding-bottom: calc(var(--stream-mobile-nav-h) + env(safe-area-inset-bottom, 0px));
    }
    body.stream-mobile-nav-active .site-footer-desktop {
        display: none !important;
    }
    #stream-mobile-nav {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        height: calc(var(--stream-mobile-nav-h) + env(safe-area-inset-bottom, 0px));
        margin: 0;
        padding: 0 0 env(safe-area-inset-bottom, 0px);
        box-sizing: border-box;
        display: flex;
        flex-direction: row;
        justify-content: space-around;
        align-items: center;
        background: #141414;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
        box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.4);
        z-index: 2147483646;
        -webkit-tap-highlight-color: transparent;
    }
    #stream-mobile-nav a {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
        flex: 1;
        max-width: 96px;
        padding: 0.35rem 0.5rem;
        color: #9ca3af;
        text-decoration: none;
        border-radius: 0.5rem;
        font-size: 0.65rem;
        font-weight: 500;
        line-height: 1.1;
    }
    #stream-mobile-nav a svg {
        width: 22px;
        height: 22px;
        stroke-width: 2;
    }
    #stream-mobile-nav a.stream-mobile-nav-active,
    #stream-mobile-nav a:hover {
        color: #e50914;
        background: rgba(229, 9, 20, 0.12);
    }
    #stream-mobile-nav a.stream-mobile-nav-active span {
        font-weight: 600;
    }
}
@media (min-width: 768px) {
    #stream-mobile-nav {
        display: none !important;
    }
}
</style>
<nav id="stream-mobile-nav" aria-label="Mobile navigation">
    <a href="<?php echo BASE_URL; ?>/" class="<?php echo $is_nav_home ? 'stream-mobile-nav-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
            <polyline points="9 22 9 12 15 12 15 22"></polyline>
        </svg>
        <span>Home</span>
    </a>
    <?php if ($enable_movies): ?>
    <a href="<?php echo BASE_URL; ?>/movies" class="<?php echo $is_nav_movies ? 'stream-mobile-nav-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
            <line x1="8" y1="21" x2="16" y2="21"></line>
            <line x1="12" y1="17" x2="12" y2="21"></line>
        </svg>
        <span>Movies</span>
    </a>
    <?php endif; ?>
    <?php if ($enable_tv_shows): ?>
    <a href="<?php echo BASE_URL; ?>/tv-shows" class="<?php echo $is_nav_tv_shows ? 'stream-mobile-nav-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
        </svg>
        <span>TV Shows</span>
    </a>
    <?php endif; ?>
    <?php if ($enable_live_tv): ?>
    <a href="<?php echo BASE_URL; ?>/live-tv" class="<?php echo $is_nav_live_tv ? 'stream-mobile-nav-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <rect x="2" y="6" width="20" height="12" rx="2"></rect>
            <circle cx="12" cy="12" r="2"></circle>
            <path d="M6 12h.01M18 12h.01"></path>
        </svg>
        <span>Live TV</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo BASE_URL; ?>/search" class="<?php echo $is_nav_search ? 'stream-mobile-nav-active' : ''; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
        <span>Search</span>
    </a>
</nav>
<script>
(function () {
    var nav = document.getElementById('stream-mobile-nav');
    if (!nav) {
        return;
    }

    var mq = window.matchMedia('(max-width: 767px)');
    var frame = 0;

    function viewportOffset() {
        var vv = window.visualViewport;
        if (!vv) {
            return 0;
        }
        return Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop));
    }

    function apply() {
        if (!mq.matches) {
            document.body.classList.remove('stream-mobile-nav-active');
            nav.style.transform = '';
            return;
        }

        document.body.classList.add('stream-mobile-nav-active');

        if (nav.parentElement !== document.body) {
            document.body.appendChild(nav);
        }

        var offset = viewportOffset();
        nav.style.transform = offset > 0 ? ('translate3d(0,' + (-offset) + 'px,0)') : '';
    }

    function schedule() {
        if (frame) {
            return;
        }
        frame = window.requestAnimationFrame(function () {
            frame = 0;
            apply();
        });
    }

    apply();
    schedule();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', schedule);
    }
    window.addEventListener('load', schedule);
    window.addEventListener('pageshow', schedule);
    window.addEventListener('resize', schedule);
    window.addEventListener('orientationchange', schedule);

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', schedule);
        window.visualViewport.addEventListener('scroll', schedule);
    }

    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', schedule);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(schedule);
    }
})();
</script>
