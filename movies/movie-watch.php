<?php
/**
 * Movie Watch Page — same layout/player as live TV (tv/tv-channel.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/movie_helpers.php';
require_once __DIR__ . '/../includes/content_ads.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$conn = getDBConnection();
ensureMoviesSchema($conn);
ensureContentAdColumns($conn, 'movies');

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$source_index = isset($_GET['source']) ? (int) $_GET['source'] : null;
$isAndroidTV = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android TV|AFT|BRAVIA|MiTV|SmartTV|GoogleTV|Tizen|Web0S|HbbTV)/i', $_SERVER['HTTP_USER_AGENT']);

$movie = null;
$error = '';
$showPremiumGate = false;
$movieAccess = null;
$sources = [];
$selected_source = null;
$current_source_index = 0;
$has_valid_sources = false;

if ($id > 0) {
    $movie = getMovieById($conn, $id);
} elseif ($slug !== '') {
    $stmt = $conn->prepare('SELECT * FROM movies WHERE slug = ?');
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
}

if (!$movie) {
    $error = 'Movie not found';
} else {
    $slug = ensureMovieSlug($conn, $movie);
    $movieAccess = enforceMovieWatchAccess($conn, $movie);

    if (!$movieAccess['allowed'] && $movieAccess['reason'] === 'premium') {
        $showPremiumGate = true;
    }

    $sources = getActiveWatchSources($movie);
    foreach ($sources as $source) {
        $source_url = trim($source['url'] ?? '');
        if ($source_url !== '' && strlen($source_url) > 3) {
            $has_valid_sources = true;
            break;
        }
    }

    if (!$has_valid_sources) {
        $error = 'This movie is not available to watch right now';
    } else {
        $resolved_index = movieSourceIndexFromUrlParam($source_index, count($sources));
        $selected_source = $sources[$resolved_index];
        $current_source_index = $resolved_index;

        if ($movieAccess['allowed'] && !$showPremiumGate) {
            $movieId = (int) $movie['id'];
            $conn->query("UPDATE movies SET views = views + 1 WHERE id = {$movieId}");
        }
    }
}

$embedMode = $selected_source ? resolveMovieSourceEmbedMode($selected_source, $conn) : ['mode' => 'video', 'url' => ''];
$is_iframe_or_embed = in_array($embedMode['mode'], ['iframe_url', 'embed_proxy'], true);

$query_params = ['source' => $current_source_index];
if ($slug !== '') {
    $query_params['slug'] = $slug;
} else {
    $query_params['id'] = (int) $movie['id'];
}
$embed_source_url = url('embed-movie-source.php?' . http_build_query($query_params, '', '&', PHP_QUERY_RFC3986));
$iframe_direct_url = ($embedMode['mode'] === 'iframe_url' && !empty($embedMode['url']))
    ? htmlspecialchars($embedMode['url'], ENT_QUOTES, 'UTF-8')
    : '';

$site_name = getSetting($conn, 'site_name', 'StreamFlix');
if ($movie) {
    $seo = buildMovieSeoMeta($conn, $movie, 'watch');
    $pageTitle = $seo['page_title'];
    $metaDescription = $seo['meta_description'];
    $metaKeywords = $seo['meta_keywords'];
    $canonical_url = $seo['canonical_url'];
    $seo_json_ld = $seo['seo_json_ld'] ?? null;
    $poster_url = moviePosterUrl($movie);
    $movie_title = $movie['title'] ?? 'Movie';
    $movie_year = !empty($movie['release_year']) ? (int) $movie['release_year'] : 0;
    $movie_category = trim($movie['category_name'] ?? '');
    $footer_heading = "Watch {$movie_title} Full Movie Online - HD Streaming";
} else {
    $pageTitle = "Movie Not Found - {$site_name}";
    $metaDescription = "Requested movie could not be found on {$site_name}.";
    $metaKeywords = 'movies, streaming';
    $canonical_url = url('movies');
    $seo_json_ld = null;
    $poster_url = '';
    $movie_title = 'Movie';
    $movie_year = 0;
    $movie_category = '';
    $footer_heading = '';
}

$suggested_movies = [];
$movie_ads_loaded = [
    'show_ads' => false,
    'has_subscription' => false,
    'intro_ad' => null,
    'ads' => [],
];
if ($movie && !$error) {
    $movieId = (int) $movie['id'];
    $stmt = $conn->prepare('SELECT id, title, slug, poster, thumbnail, release_year, rating, is_premium, is_free
        FROM movies WHERE id != ? AND is_active = 1 ORDER BY RAND() LIMIT 8');
    if ($stmt) {
        $stmt->bind_param('i', $movieId);
        $stmt->execute();
        $suggested_movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    $movie_ads_loaded = loadContentAds($conn, $movie);
}
$adsDataPayload = contentAdsJsPayload($movie_ads_loaded);
?>
<?php
$current_page = 'movie-watch.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords); ?>">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
    <?php if ($movie): ?>
    <meta property="og:type" content="video.movie">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical_url); ?>">
    <?php if ($poster_url): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($poster_url); ?>">
    <?php endif; ?>
    <?php endif; ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">
    <?php
    if (!empty($seo_json_ld)) {
        renderSeoJsonLd($seo_json_ld);
    }
    $custom_css = getPublicCustomCode($conn, 'custom_css');
    if ($custom_css !== '') {
        echo "<style id=\"site-custom-css\">\n" . $custom_css . "\n</style>\n";
    }
    renderPublicCustomCode($conn, 'custom_code_head');
    ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style><?php include __DIR__ . '/../includes/watch-player-layout-styles.php'; ?></style>
    <style><?php include __DIR__ . '/../includes/content-ad-styles.php'; ?></style>
</head>
<body class="bg-black text-white">
<?php
renderPublicCustomCode($conn, 'custom_code_body');
renderPublicCustomCode($conn, 'custom_code_after_header');
?>

<?php if ($error || !$movie): ?>
    <div class="error-page">
        <div class="error-content">
            <h2>Movie Not Available</h2>
            <p><?php echo htmlspecialchars($error ?: 'Movie not found'); ?></p>
            <div class="error-actions">
                <a href="<?php echo htmlspecialchars(url('movies')); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path></svg>
                    Back to Movies
                </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="tv-channel-page">
        <?php if (!$isAndroidTV): ?>
        <div class="sticky-header">
            <div class="mobile-header-row1">
                <button class="header-back-btn" onclick="handleBackToMovies()" aria-label="Back to Movies">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path></svg>
                </button>
                <?php if ($poster_url): ?>
                <img src="<?php echo htmlspecialchars($poster_url); ?>" alt="<?php echo htmlspecialchars($movie_title); ?>" class="channel-logo-header">
                <?php endif; ?>
                <div class="channel-info-header">
                    <h1><?php echo htmlspecialchars($movie_title); ?></h1>
                    <p><?php echo $movie_year > 0 ? $movie_year : 'Movie'; ?><?php echo $movie_category !== '' ? ' | ' . htmlspecialchars($movie_category) : ''; ?></p>
                </div>
                <div class="viewer-count-header" id="viewer-count-mobile">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <span id="viewer-count-mobile-text">0</span>
                </div>
                <button class="fullscreen-btn-header" id="fullscreen-button-mobile" onclick="toggleFullscreen()" title="Enter Fullscreen">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>
                </button>
            </div>
            <div class="desktop-header">
                <button class="header-back-btn" onclick="handleBackToMovies()" aria-label="Back to Movies">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"></path><path d="m12 19-7-7 7-7"></path></svg>
                </button>
                <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0;">
                    <?php if ($poster_url): ?>
                    <img src="<?php echo htmlspecialchars($poster_url); ?>" alt="<?php echo htmlspecialchars($movie_title); ?>" class="channel-logo-header">
                    <?php endif; ?>
                    <div class="channel-info-header">
                        <h1><?php echo htmlspecialchars($movie_title); ?></h1>
                        <p><?php echo $movie_year > 0 ? $movie_year : 'Movie'; ?><?php echo $movie_category !== '' ? ' | ' . htmlspecialchars($movie_category) : ''; ?></p>
                    </div>
                    <div class="viewer-count-header" id="viewer-count-desktop">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <span id="viewer-count-desktop-text">0</span>
                    </div>
                    <button class="fullscreen-btn-header" id="fullscreen-button-desktop" onclick="toggleFullscreen()" title="Enter Fullscreen">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($showPremiumGate): ?>
        <?php
            $premium_gate_back_url = getMovieDetailUrl($movie, $conn);
            $premium_gate_back_label = 'Back to Movie';
            $premium_gate_message = 'This movie requires a Premium subscription. Please upgrade to watch.';
            include __DIR__ . '/../includes/premium-gate.php';
        ?>
        <?php elseif ($has_valid_sources && !empty($selected_source)): ?>
        <div id="player-container" class="<?php echo $isAndroidTV ? 'player-container-androidtv' : 'player-container player-container-mobile'; ?>">
            <div class="video-player-wrapper" id="video-wrapper">
                <video id="videoPlayer" class="video-player" controls autoplay playsinline muted style="<?php echo $is_iframe_or_embed ? 'display: none;' : ''; ?>"></video>
                <iframe id="youtubePlayer" class="video-player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="display: none;"></iframe>
                <div id="html-embed-container" style="<?php echo $is_iframe_or_embed ? 'display: none;' : 'display: none;'; ?>">
                    <?php if ($embedMode['mode'] === 'embed_proxy'): ?>
                    <iframe id="embedFrame"
                        src=""
                        data-src="<?php echo htmlspecialchars($embed_source_url, ENT_QUOTES, 'UTF-8'); ?>"
                        allowfullscreen
                        allow="autoplay; encrypted-media; picture-in-picture"
                        loading="eager"></iframe>
                    <?php elseif ($embedMode['mode'] === 'iframe_url' && $iframe_direct_url !== ''): ?>
                    <iframe id="embedFrame"
                        src=""
                        data-src="<?php echo $iframe_direct_url; ?>"
                        allowfullscreen
                        allow="autoplay; encrypted-media; picture-in-picture"
                        loading="eager"></iframe>
                    <?php endif; ?>
                </div>
                <?php include __DIR__ . '/../includes/content-ad-markup.php'; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (count($sources) > 1): ?>
        <div class="try-another-source-section">
            <p class="try-another-source-text">If stream not playing video, Try Another Source:</p>
            <div class="try-another-source-links">
                <?php foreach ($sources as $idx => $source_item): ?>
                    <?php if ($idx !== $current_source_index): ?>
                    <a href="<?php echo htmlspecialchars(getMovieWatchUrl($movie, $idx, $conn)); ?>" class="try-source-link"><?php echo htmlspecialchars(getMovieSourceDisplayLabel($source_item, $idx)); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($movie['description'])): ?>
        <div class="channel-description-section">
            <h3>About</h3>
            <p><?php echo nl2br(htmlspecialchars($movie['description'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if (!empty($suggested_movies)): ?>
        <div class="suggested-channels-section">
            <h3 class="suggested-channels-title">More movies you might like</h3>
            <div class="suggested-channels-grid">
                <?php foreach ($suggested_movies as $suggested): ?>
                <a href="<?php echo htmlspecialchars(getMovieDetailUrl($suggested, $conn)); ?>" class="suggested-channel-card">
                    <div class="suggested-channel-logo">
                        <?php renderMoviePosterBadges($suggested); ?>
                        <img src="<?php echo htmlspecialchars(moviePosterUrl($suggested)); ?>" alt="<?php echo htmlspecialchars($suggested['title']); ?>" onerror="this.style.display='none'">
                    </div>
                    <div class="suggested-channel-info">
                        <h4 class="suggested-channel-name"><?php echo htmlspecialchars($suggested['title']); ?></h4>
                        <div class="suggested-channel-meta"><?php echo !empty($suggested['release_year']) ? (int) $suggested['release_year'] : ''; ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
    const adsData = <?php echo json_encode($adsDataPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <?php include __DIR__ . '/../includes/content-ad-player.js.php'; ?>
    <script>
    const movieId = <?php echo (int) $movie['id']; ?>;
    const streamUrl = <?php echo json_encode($selected_source['url'] ?? ''); ?>;
    const streamType = <?php echo json_encode(strtolower($selected_source['type'] ?? 'embed')); ?>;
    const embedMode = <?php echo json_encode($embedMode['mode']); ?>;
    const viewerApiUrl = <?php echo json_encode(url('movies/api/viewer_tracker')); ?>;
    let hlsInstance = null;
    let viewerPingInterval = null;
    let isFullscreen = false;

    function getMovieViewerToken(id) {
        const storageKey = 'movie_viewer_token_' + id;
        let token = sessionStorage.getItem(storageKey);
        if (!token) {
            token = 'mv_' + id + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 12);
            sessionStorage.setItem(storageKey, token);
        }
        return token;
    }
    const viewerToken = getMovieViewerToken(movieId);

    function handleBackToMovies() {
        window.location.href = <?php echo json_encode(getMovieDetailUrl($movie, $conn)); ?>;
    }

    function toggleFullscreen() {
        const container = document.getElementById('player-container');
        if (!container) return;
        if (!document.fullscreenElement) {
            (container.requestFullscreen || container.webkitRequestFullscreen || container.mozRequestFullScreen || container.msRequestFullscreen).call(container);
        } else {
            (document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen).call(document);
        }
    }

    document.addEventListener('fullscreenchange', () => {
        isFullscreen = !!document.fullscreenElement;
        const maximizeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>';
        const minimizeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>';
        const btnMobile = document.getElementById('fullscreen-button-mobile');
        const btnDesktop = document.getElementById('fullscreen-button-desktop');
        if (btnMobile) btnMobile.innerHTML = isFullscreen ? minimizeIcon : maximizeIcon;
        if (btnDesktop) btnDesktop.innerHTML = isFullscreen ? minimizeIcon : maximizeIcon;
    });

    function updateViewerDisplay(count) {
        ['viewer-count-mobile-text', 'viewer-count-desktop-text'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = count.toLocaleString();
        });
    }

    function pingViewer() {
        const formData = new FormData();
        formData.append('action', 'ping');
        formData.append('movie_id', movieId);
        formData.append('viewer_token', viewerToken);
        fetch(viewerApiUrl, { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => { if (data.success && data.viewers !== undefined) updateViewerDisplay(parseInt(data.viewers, 10) || 0); })
            .catch(() => {});
    }

    function playActualStream() {
        const htmlEmbedContainer = document.getElementById('html-embed-container');
        const embedFrame = document.getElementById('embedFrame');
        if (embedMode === 'iframe_url' || embedMode === 'embed_proxy') {
            if (htmlEmbedContainer) htmlEmbedContainer.style.display = 'block';
            if (embedFrame && !embedFrame.src && embedFrame.getAttribute('data-src')) {
                embedFrame.src = embedFrame.getAttribute('data-src');
            }
            return;
        }
        const video = document.getElementById('videoPlayer');
        const youtubeIframe = document.getElementById('youtubePlayer');
        if (!video || !streamUrl) return;

        if (htmlEmbedContainer) {
            htmlEmbedContainer.style.display = 'none';
        }

        const isYouTube = /youtube\.com|youtu\.be/i.test(streamUrl);
        if (isYouTube) {
            let embedSrc = streamUrl;
            const match = streamUrl.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/);
            if (match) embedSrc = 'https://www.youtube.com/embed/' + match[1] + '?autoplay=1&rel=0';
            video.style.display = 'none';
            youtubeIframe.style.display = 'block';
            youtubeIframe.src = embedSrc;
            return;
        }

        video.style.display = 'block';
        if (youtubeIframe) youtubeIframe.style.display = 'none';

        const isHls = streamType === 'hls' || /\.m3u8(\?|$)/i.test(streamUrl);
        if (isHls && window.Hls && Hls.isSupported()) {
            if (hlsInstance) { hlsInstance.destroy(); hlsInstance = null; }
            hlsInstance = new Hls();
            hlsInstance.loadSource(streamUrl);
            hlsInstance.attachMedia(video);
            hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => video.play().catch(() => {}));
            return;
        }
        if (isHls && video.canPlayType('application/vnd.apple.mpegurl')) {
            video.src = streamUrl;
            video.play().catch(() => {});
            return;
        }
        video.src = streamUrl;
        video.play().catch(() => {});
    }

    function loadStream() {
        if (typeof runPrerollThen === 'function') {
            runPrerollThen(playActualStream);
            return;
        }
        playActualStream();
    }

    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!$showPremiumGate): ?>
        loadStream();
        pingViewer();
        viewerPingInterval = setInterval(pingViewer, 30000);
        <?php endif; ?>
    });

    window.addEventListener('beforeunload', function() {
        if (viewerPingInterval) clearInterval(viewerPingInterval);
        const formData = new FormData();
        formData.append('action', 'leave');
        formData.append('movie_id', movieId);
        formData.append('viewer_token', viewerToken);
        navigator.sendBeacon(viewerApiUrl, formData);
        if (hlsInstance) { try { hlsInstance.destroy(); } catch (e) {} }
    });
    </script>
<?php endif; ?>

<?php if ($movie && $footer_heading !== ''): ?>
<div class="watch-seo-heading container mx-auto px-4 py-8 text-center">
    <h1 class="text-2xl md:text-3xl font-bold text-white mb-4"><?php echo htmlspecialchars($footer_heading); ?></h1>
</div>
<?php endif; ?>

<?php
$minimal_site_footer = true;
include __DIR__ . '/../includes/footer.php';
