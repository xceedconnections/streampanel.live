<?php
/**
 * Movie Watch Page — same layout/player as live TV (tv/tv-channel.php).
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/movie_helpers.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$conn = getDBConnection();

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
    $poster_url = '';
    $movie_title = 'Movie';
    $movie_year = 0;
    $movie_category = '';
    $footer_heading = '';
}

$suggested_movies = [];
if ($movie && !$error) {
    $movieId = (int) $movie['id'];
    $stmt = $conn->prepare('SELECT id, title, slug, poster, thumbnail, release_year, rating, is_premium, is_free
        FROM movies WHERE id != ? AND is_active = 1 ORDER BY RAND() LIMIT 8');
    if ($stmt) {
        $stmt->bind_param('i', $movieId);
        $stmt->execute();
        $suggested_movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords); ?>">
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
.tv-channel-page { min-height: 100vh; background: #000; color: #fff; font-family: system-ui, -apple-system, sans-serif; padding-bottom: 60px; }
@media (min-width: 768px) { .tv-channel-page { padding-bottom: 0; } }
.sticky-header { position: sticky; top: 0; z-index: 40; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); border-bottom: 1px solid rgba(255,255,255,0.1); padding: 0.75rem 1rem; }
@media (min-width: 768px) { .sticky-header { padding: 1rem 3rem; } }
.mobile-header-row1 { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
.mobile-header-row2 { display: flex; align-items: center; gap: 0.5rem; }
@media (min-width: 768px) { .mobile-header-row1, .mobile-header-row2 { display: none; } }
.desktop-header { display: none; align-items: center; gap: 1rem; }
@media (min-width: 768px) { .desktop-header { display: flex; } }
.header-back-btn { padding: 0.5rem; background: transparent; border: none; color: #fff; cursor: pointer; border-radius: 0.5rem; transition: background 0.2s; display: flex; align-items: center; justify-content: center; }
.header-back-btn:hover { background: rgba(255,255,255,0.1); }
.channel-logo-header { width: 2.5rem; height: 2.5rem; object-fit: cover; border-radius: 0.25rem; flex-shrink: 0; }
.channel-info-header { flex: 1; min-width: 0; }
.channel-info-header h1 { font-size: 1.125rem; font-weight: 700; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
@media (min-width: 768px) { .channel-info-header h1 { font-size: 1.25rem; } }
.channel-info-header p { font-size: 0.75rem; color: #9ca3af; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
@media (min-width: 768px) { .channel-info-header p { font-size: 0.875rem; } }
.viewer-count-header { display: flex; align-items: center; gap: 0.25rem; font-size: 0.875rem; color: #4ade80; flex-shrink: 0; }
.viewer-count-header svg { width: 18px; height: 18px; color: #4ade80; }
.viewer-count-header span { font-weight: 600; }
.fullscreen-btn-header { padding: 0.5rem; background: transparent; border: none; color: #fff; cursor: pointer; border-radius: 0.5rem; transition: background 0.2s; display: flex; align-items: center; justify-content: center; }
.fullscreen-btn-header:hover { background: rgba(255,255,255,0.1); }
.player-container { width: 100%; background: #000; }
.player-container-mobile { height: calc(100vh - 80px - 220px); min-height: 250px; max-height: calc(100vh - 300px); }
@media (max-width: 480px) { .player-container-mobile { height: calc(100vh - 80px - 380px); min-height: 180px; max-height: calc(100vh - 460px); } }
@media (min-width: 481px) and (max-width: 768px) { .player-container-mobile { height: calc(100vh - 80px - 300px); min-height: 220px; max-height: calc(100vh - 380px); } }
.player-container-androidtv { position: fixed; inset: 0; z-index: 40; width: 100%; background: #000; }
.video-player-wrapper { position: relative; width: 100%; height: 100%; background: #000; overflow: hidden; }
.video-player { width: 100%; height: 100%; object-fit: contain; background: #000; position: relative; z-index: 1; }
#html-embed-container { width: 100%; height: 100%; position: absolute; top: 0; left: 0; z-index: 2; }
#html-embed-container iframe { width: 100%; height: 100%; border: 0; }
.try-another-source-section { padding: 1rem; text-align: center; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3); }
@media (min-width: 768px) { .try-another-source-section { padding: 1.5rem; } }
.try-another-source-text { color: #9ca3af; font-size: 0.875rem; margin-bottom: 0.75rem; }
.try-another-source-links { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: center; align-items: center; }
.try-source-link { display: inline-block; padding: 0.5rem 1rem; background: rgba(229,9,20,0.8); color: #fff; text-decoration: none; border-radius: 0.25rem; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
.try-source-link:hover { background: rgba(229,9,20,1); transform: scale(1.05); text-decoration: none; color: #fff; }
.channel-description-section { padding: 1.5rem 1rem; border-top: 1px solid rgba(255,255,255,0.1); }
@media (min-width: 768px) { .channel-description-section { padding: 2rem 3rem; } }
.channel-description-section h3 { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; color: #fff; }
.channel-description-section p { color: #9ca3af; line-height: 1.6; }
.suggested-channels-section { padding: 2rem 1rem; border-top: 1px solid rgba(255,255,255,0.1); }
@media (min-width: 768px) { .suggested-channels-section { padding: 2rem 3rem; } }
.suggested-channels-title { font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem; color: #fff; }
.suggested-channels-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
@media (min-width: 640px) { .suggested-channels-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 768px) { .suggested-channels-grid { grid-template-columns: repeat(4, 1fr); } }
.suggested-channel-card { text-decoration: none; color: inherit; display: block; }
.suggested-channel-logo { position: relative; aspect-ratio: 2/3; background: #1f2937; border-radius: 0.5rem; overflow: hidden; }
.suggested-channel-logo .movie-card-badges { position: absolute; top: 0.35rem; left: 0.35rem; right: 0.35rem; z-index: 2; display: flex; flex-wrap: wrap; gap: 0.25rem; pointer-events: none; }
.movie-card-badges { display: flex; flex-wrap: wrap; gap: 0.25rem; pointer-events: none; }
.movie-badge { font-size: 0.6rem; font-weight: 700; padding: 0.12rem 0.35rem; border-radius: 0.2rem; text-transform: uppercase; line-height: 1.2; }
.movie-badge-quality { background: #e50914; color: #fff; }
.movie-badge-tag { background: rgba(0,0,0,0.75); color: #fbbf24; border: 1px solid rgba(251,191,36,0.4); }
.suggested-channel-logo img { width: 100%; height: 100%; object-fit: cover; }
.suggested-channel-info { padding: 0.5rem 0; }
.suggested-channel-name { font-size: 0.875rem; font-weight: 600; color: #fff; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.suggested-channel-meta { font-size: 0.75rem; color: #9ca3af; }
.error-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #000; color: #fff; padding: 2rem; }
.error-content { text-align: center; max-width: 28rem; }
.error-content h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.error-content p { color: #9ca3af; margin-bottom: 1.5rem; }
.error-actions a { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: #e50914; color: #fff; text-decoration: none; border-radius: 0.375rem; font-weight: 600; }
    </style>
</head>
<body class="bg-black text-white">

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
                <div id="html-embed-container" style="<?php echo $is_iframe_or_embed ? 'display: block;' : 'display: none;'; ?>">
                    <?php if ($embedMode['mode'] === 'embed_proxy'): ?>
                    <iframe id="embedFrame"
                        src="<?php echo htmlspecialchars($embed_source_url, ENT_QUOTES, 'UTF-8'); ?>"
                        allowfullscreen
                        allow="autoplay; encrypted-media; picture-in-picture"
                        loading="eager"></iframe>
                    <?php elseif ($embedMode['mode'] === 'iframe_url' && $iframe_direct_url !== ''): ?>
                    <iframe id="embedFrame"
                        src="<?php echo $iframe_direct_url; ?>"
                        allowfullscreen
                        allow="autoplay; encrypted-media; picture-in-picture"
                        loading="eager"></iframe>
                    <?php endif; ?>
                </div>
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

    function loadStream() {
        if (embedMode === 'iframe_url' || embedMode === 'embed_proxy') return;
        const video = document.getElementById('videoPlayer');
        const youtubeIframe = document.getElementById('youtubePlayer');
        const htmlEmbedContainer = document.getElementById('html-embed-container');
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
<div class="container mx-auto px-4 py-8 text-center">
    <h1 class="text-2xl md:text-3xl font-bold text-white mb-4"><?php echo htmlspecialchars($footer_heading); ?></h1>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
