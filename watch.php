<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

requireLogin();

$page_title = "Watch";
$conn = getDBConnection();

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? 0;

// Redirect live TV to dedicated channel page
if ($type === 'live_tv') {
    // Get channel slug
    $channel_stmt = $conn->prepare("SELECT slug FROM live_tv_channels WHERE id = ?");
    $channel_stmt->bind_param("i", $id);
    $channel_stmt->execute();
    $channel_result = $channel_stmt->get_result()->fetch_assoc();
    if (!empty($channel_result['slug'])) {
        header('Location: tv/' . htmlspecialchars($channel_result['slug']));
    } else {
        header('Location: tv/tv-channel.php?id=' . $id);
    }
    exit();
}

$content = null;
$title = '';
$sources = [];
$selectedSource = null;

if ($type === 'movie') {
    $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $content = $stmt->get_result()->fetch_assoc();
    $title = $content['title'] ?? 'Movie';
    
    // Update views
    if ($content) {
        $conn->query("UPDATE movies SET views = views + 1 WHERE id = $id");
    }
} elseif ($type === 'tv_episode') {
    $stmt = $conn->prepare("SELECT e.*, t.title as show_title FROM tv_episodes e JOIN tv_shows t ON e.tv_show_id = t.id WHERE e.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $content = $stmt->get_result()->fetch_assoc();
    $title = ($content['show_title'] ?? 'TV Show') . ' - ' . ($content['title'] ?? 'Episode');
    
    // Parse sources for TV episodes
    if ($content && !empty($content['sources'])) {
        $sources = parseSources($content['sources']);
        // Filter active and visible sources, sort by priority
        $activeSources = array_filter($sources, function($s) {
            return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true);
        });
        usort($activeSources, function($a, $b) {
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });
        $sources = $activeSources;
        // Get the first (default) source
        $selectedSource = !empty($sources) ? $sources[0] : null;
    }
    
    // Update views
    if ($content) {
        $conn->query("UPDATE tv_episodes SET views = views + 1 WHERE id = $id");
    }
}

if (!$content) {
    header('Location: index.php');
    exit();
}

$page_title = $title;

// Save to watch history (update timestamp if exists, insert if new)
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $content_type = $type === 'tv_episode' ? 'tv_episode' : ($type === 'live_tv' ? 'live_tv' : 'movie');
    $content_id = $content['id'];
    
    // Check if history exists
    $history_check = $conn->prepare("SELECT id FROM watch_history WHERE user_id = ? AND content_type = ? AND content_id = ?");
    $history_check->bind_param("isi", $user_id, $content_type, $content_id);
    $history_check->execute();
    
    if ($history_check->get_result()->num_rows > 0) {
        // Update timestamp
        $history_update = $conn->prepare("UPDATE watch_history SET watched_at = NOW() WHERE user_id = ? AND content_type = ? AND content_id = ?");
        $history_update->bind_param("isi", $user_id, $content_type, $content_id);
        $history_update->execute();
    } else {
        // Insert new record
        $history_insert = $conn->prepare("INSERT INTO watch_history (user_id, content_type, content_id) VALUES (?, ?, ?)");
        $history_insert->bind_param("isi", $user_id, $content_type, $content_id);
        $history_insert->execute();
    }
}

include 'includes/header.php';
?>

<?php if ($type === 'tv_episode' && !empty($sources)): ?>
<!-- Streaming libraries: HLS.js and dash.js -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>
<?php endif; ?>

<style>
.watch-page {
    min-height: 100vh;
    background: #000;
    padding: 2rem 0;
}
.watch-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1.5rem;
}
@media (min-width: 768px) {
    .watch-container {
        padding: 0 3rem;
    }
}
.video-wrapper {
    background: #000;
    border-radius: 0.5rem;
    overflow: hidden;
    margin-bottom: 2rem;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
}
.video-wrapper video {
    width: 100%;
    height: auto;
    display: block;
}
.watch-info {
    color: #fff;
}
.watch-title {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 1rem;
}
@media (min-width: 768px) {
    .watch-title {
        font-size: 3rem;
    }
}
.watch-description {
    color: #d1d5db;
    margin-bottom: 1.5rem;
    line-height: 1.6;
}
.watch-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    color: #9ca3af;
    font-size: 0.875rem;
}
.back-btn {
    display: inline-flex;
    align-items: center;
    color: #9ca3af;
    text-decoration: none;
    margin-bottom: 1.5rem;
    transition: color 0.2s;
}
.back-btn:hover {
    color: #fff;
}
</style>

<div class="watch-page animate-in fade-in">
    <div class="watch-container">
        <a href="javascript:history.back()" class="back-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 0.5rem;">
                <polyline points="15 18 9 12 15 6"></polyline>
            </svg>
            Back
        </a>
        
        <div class="video-wrapper">
            <?php if ($type === 'tv_episode' && !empty($sources)): ?>
                <!-- TV Episode Player with Multiple Sources -->
                <div id="player-container" style="position: relative; width: 100%; padding-bottom: 56.25%; background: #000;">
                    <!-- YouTube iframe (hidden by default) -->
                    <iframe id="youtubePlayer" 
                            style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none; border: none;"
                            allow="autoplay; encrypted-media; picture-in-picture" 
                            allowfullscreen></iframe>
                    
                    <!-- HTML Embed Container (hidden by default) -->
                    <div id="htmlEmbedContainer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none;"></div>
                    
                    <!-- Native Video Player (hidden by default) -->
                    <video id="videoPlayer" 
                           style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: none;"
                           controls autoplay playsinline>
                        Your browser does not support the video tag.
                    </video>
                    
                    <!-- Source Selector (if multiple sources) -->
                    <?php if (count($sources) > 1): ?>
                    <div style="position: absolute; top: 10px; right: 10px; z-index: 10;">
                        <select id="sourceSelector" style="background: rgba(0,0,0,0.8); color: #fff; padding: 8px; border: 1px solid #333; border-radius: 4px;">
                            <?php foreach ($sources as $idx => $src): ?>
                            <option value="<?php echo $idx; ?>" data-url="<?php echo htmlspecialchars($src['url'] ?? ''); ?>" data-type="<?php echo htmlspecialchars($src['type'] ?? 'direct'); ?>">
                                <?php echo htmlspecialchars($src['label'] ?? 'Source ' . ($idx + 1)); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Movie Player (direct video) -->
                <video id="videoPlayer" controls autoplay>
                    <source src="<?php echo htmlspecialchars($content['video_url'] ?? $content['stream_url'] ?? ''); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </div>
        
        <div class="watch-info">
            <div class="flex items-center justify-between mb-4">
                <h1 class="watch-title"><?php echo htmlspecialchars($title); ?></h1>
                <?php if (isLoggedIn()): ?>
                <button id="favoriteBtn" onclick="toggleFavorite()" 
                        class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded flex items-center gap-2 transition">
                    <i class="fas fa-heart" id="favoriteIcon"></i>
                    <span id="favoriteText">Add to Favorites</span>
                </button>
                <?php endif; ?>
            </div>
            <?php if (isset($content['description'])): ?>
            <p class="watch-description"><?php echo nl2br(htmlspecialchars($content['description'])); ?></p>
            <?php endif; ?>
            
            <div class="watch-meta">
                <?php if ($type === 'movie'): ?>
                <span><i class="fas fa-calendar mr-2"></i><?php echo $content['release_year']; ?></span>
                <span><i class="fas fa-clock mr-2"></i><?php echo $content['duration']; ?> min</span>
                <span><i class="fas fa-star mr-2"></i><?php echo number_format($content['rating'], 1); ?></span>
                <span><i class="fas fa-eye mr-2"></i><?php echo number_format($content['views']); ?> views</span>
                <?php elseif ($type === 'tv_episode'): ?>
                <span>Season <?php echo $content['season_number']; ?>, Episode <?php echo $content['episode_number']; ?></span>
                <span><i class="fas fa-clock mr-2"></i><?php echo $content['duration']; ?> min</span>
                <span><i class="fas fa-eye mr-2"></i><?php echo number_format($content['views']); ?> views</span>
                <?php elseif ($type === 'live_tv'): ?>
                <span><i class="fas fa-broadcast-tower mr-2"></i>Live</span>
                <span><i class="fas fa-eye mr-2"></i><?php echo number_format($content['views']); ?> views</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Check favorite status and update button
<?php if (isLoggedIn()): ?>
const contentType = '<?php echo $type === "tv_episode" ? "tv_show" : ($type === "live_tv" ? "live_tv" : "movie"); ?>';
const contentId = <?php echo $type === "tv_episode" ? ($content['tv_show_id'] ?? $content['id']) : $content['id']; ?>;

async function checkFavorite() {
    try {
        const response = await fetch(`<?php echo BASE_URL; ?>/api/favorites.php?content_type=${contentType}&content_id=${contentId}`);
        const data = await response.json();
        if (data.success && data.is_favorite) {
            document.getElementById('favoriteIcon').classList.add('text-red-500');
            document.getElementById('favoriteText').textContent = 'Remove from Favorites';
        }
    } catch (error) {
        console.error('Error checking favorite:', error);
    }
}

async function toggleFavorite() {
    const btn = document.getElementById('favoriteBtn');
    const icon = document.getElementById('favoriteIcon');
    const text = document.getElementById('favoriteText');
    const isFavorite = icon.classList.contains('text-red-500');
    
    try {
        const url = `<?php echo BASE_URL; ?>/api/favorites.php`;
        const method = isFavorite ? 'DELETE' : 'POST';
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                content_type: contentType,
                content_id: contentId
            })
        });
        
        const data = await response.json();
        if (data.success) {
            if (data.is_favorite) {
                icon.classList.add('text-red-500');
                text.textContent = 'Remove from Favorites';
            } else {
                icon.classList.remove('text-red-500');
                text.textContent = 'Add to Favorites';
            }
        }
    } catch (error) {
        console.error('Error toggling favorite:', error);
        alert('Failed to update favorites');
    }
}

// Check favorite status on page load
checkFavorite();
<?php endif; ?>

// TV Episode Player Logic
<?php if ($type === 'tv_episode' && !empty($sources)): ?>
const episodeSources = <?php echo json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
let currentSourceIndex = 0;

// Helper: wrap http URLs behind local HTTPS proxy to avoid mixed-content blocking
function getProxiedStreamUrl(originalUrl) {
    try {
        if (!originalUrl) return originalUrl;
        if (window.location.protocol === 'https:' && /^http:\/\//i.test(originalUrl)) {
            const encoded = btoa(originalUrl);
            return `${window.location.origin}/proxy/hls-proxy.php?u=${encodeURIComponent(encoded)}`;
        }
    } catch (e) {
        console.warn('[Watch] Failed to build proxied URL, using original:', e);
    }
    return originalUrl;
}

// Convert YouTube URL to embed format
function convertYouTubeUrl(url) {
    if (!url) return null;
    
    let embedUrl = null;
    
    // youtube.com/watch?v=...
    if (url.includes('youtube.com/watch')) {
        const match = url.match(/[?&]v=([^&]+)/);
        if (match && match[1]) {
            embedUrl = `https://www.youtube.com/embed/${match[1]}?autoplay=1&rel=0&modestbranding=1`;
        }
    }
    // youtu.be/...
    else if (url.includes('youtu.be/')) {
        const match = url.match(/youtu\.be\/([^?&]+)/);
        if (match && match[1]) {
            embedUrl = `https://www.youtube.com/embed/${match[1]}?autoplay=1&rel=0&modestbranding=1`;
        }
    }
    // youtube.com/embed/...
    else if (url.includes('youtube.com/embed/')) {
        embedUrl = url.includes('autoplay') ? url : `${url}${url.includes('?') ? '&' : '?'}autoplay=1&rel=0&modestbranding=1`;
    }
    
    return embedUrl;
}

// Load source
function loadSource(sourceIndex) {
    if (sourceIndex < 0 || sourceIndex >= episodeSources.length) return;
    
    const source = episodeSources[sourceIndex];
    const originalStreamUrl = source.url || '';
    const streamType = source.type || 'direct';
    
    const video = document.getElementById('videoPlayer');
    const youtubeIframe = document.getElementById('youtubePlayer');
    const htmlEmbedContainer = document.getElementById('htmlEmbedContainer');
    
    // Hide all players first
    if (video) video.style.display = 'none';
    if (youtubeIframe) youtubeIframe.style.display = 'none';
    if (htmlEmbedContainer) htmlEmbedContainer.style.display = 'none';
    
    // Check if YouTube
    const isYouTube = streamType === 'youtube' || originalStreamUrl.includes('youtube.com') || originalStreamUrl.includes('youtu.be');
    
    if (isYouTube) {
            console.log('[Watch] Loading YouTube source');
        if (youtubeIframe) {
            youtubeIframe.style.display = 'block';
            const embedUrl = convertYouTubeUrl(originalStreamUrl);
            if (embedUrl) {
                youtubeIframe.src = embedUrl;
            } else {
                console.error('[Watch] Invalid YouTube URL:', streamUrl);
            }
        }
        return;
    }
    
    // Check for HTML embed
    const isHtmlEmbed = streamType === 'embed' || streamType === 'html-embed' || streamType === 'html';
    if (isHtmlEmbed) {
            console.log('[Watch] Loading HTML embed source');
        if (htmlEmbedContainer) {
            htmlEmbedContainer.style.display = 'block';
            htmlEmbedContainer.innerHTML = originalStreamUrl;
        }
        return;
    }
    
    // Check for open-window
    if (streamType === 'open-window') {
        console.log('[Watch] Opening in new window');
        window.open(originalStreamUrl, '_blank', 'noopener,noreferrer');
        return;
    }
    
    // For HLS, DASH, or direct video
    if (video) {
        video.style.display = 'block';

        const streamUrl = getProxiedStreamUrl(originalStreamUrl);
        
        // Check if HLS
        const isHLS = streamType === 'm3u8' || streamType === 'hls' || originalStreamUrl.includes('.m3u8');
        
        if (isHLS && typeof Hls !== 'undefined' && Hls.isSupported()) {
            console.log('[Watch] Loading HLS stream', streamUrl);
            if (window.hlsInstance) {
                window.hlsInstance.destroy();
            }
            window.hlsInstance = new Hls();
            window.hlsInstance.loadSource(streamUrl);
            window.hlsInstance.attachMedia(video);
            window.hlsInstance.on(Hls.Events.MANIFEST_PARSED, function() {
                video.play().catch(e => console.log('Autoplay prevented:', e));
            });
        } else if (isHLS && video.canPlayType('application/vnd.apple.mpegurl')) {
            // Native HLS support (Safari)
            console.log('[Watch] Loading HLS stream (native)');
            video.src = streamUrl;
            video.play().catch(e => console.log('Autoplay prevented:', e));
        } else if (streamType === 'dash' || streamUrl.includes('.mpd')) {
            // DASH stream
            console.log('[Watch] Loading DASH stream');
            if (typeof dashjs !== 'undefined') {
                if (window.dashPlayer) {
                    window.dashPlayer.reset();
                }
                window.dashPlayer = dashjs.MediaPlayer().create();
                window.dashPlayer.initialize(video, streamUrl, true);
            } else {
                console.error('[Watch] dash.js not loaded');
            }
        } else {
            // Direct video (MP4, etc.)
            console.log('[Watch] Loading direct video');
            video.src = streamUrl;
            video.load();
            video.play().catch(e => console.log('Autoplay prevented:', e));
        }
    }
}

// Source selector change handler
const sourceSelector = document.getElementById('sourceSelector');
if (sourceSelector) {
    sourceSelector.addEventListener('change', function() {
        currentSourceIndex = parseInt(this.value);
        loadSource(currentSourceIndex);
    });
}

// Load initial source
loadSource(0);

<?php else: ?>
// Video player controls (for movies)
const video = document.getElementById('videoPlayer');
if (video) {
    video.addEventListener('play', function() {
        // Track play event if needed
    });
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
