<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';

$page_title = "Watch";
$conn = getDBConnection();
ensureMoviesSchema($conn);

// Get enabled sections for navigation (needed for footer)
$enable_movies = isSectionEnabled($conn, 'movies');
$enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
$enable_live_tv = isSectionEnabled($conn, 'live_tv');

// Check if login is required for TV Shows (only for TV episodes)
$login_required_tv_shows = '0';
$type = $_GET['type'] ?? '';
$movie_slug = trim((string) ($_GET['movie_slug'] ?? ''));
if ($type === '' && $movie_slug !== '') {
    $type = 'movie';
}
$is_tv_episode = ($type === 'tv_episode');
$is_movie = ($type === 'movie' || $movie_slug !== '');

// Login requirements differ by content type (handled after content is loaded for movies).
$showPremiumGate = false;
$movieAccess = ['allowed' => true, 'reason' => ''];

if ($is_tv_episode) {
    // Check login requirement for TV shows
    try {
        $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        if ($stmt) {
            $key = 'login_required_tv_shows';
            $stmt->bind_param("s", $key);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $login_required_tv_shows = $row['setting_value'] ?? '0';
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $login_required_tv_shows = '0';
    }
    
    // Normalize the value
    $login_required_tv_shows = trim((string)$login_required_tv_shows);
    $login_required = ($login_required_tv_shows === '1');
    
    // Only require login if explicitly set to '1'
    if ($login_required === true) {
        requireLogin();
    }
} elseif (!$is_movie) {
    requireLogin();
}

$type = $_GET['type'] ?? '';
if ($type === '' && $movie_slug !== '') {
    $type = 'movie';
}
$id = $_GET['id'] ?? 0;
$show_slug = $_GET['show_slug'] ?? '';
$episode_info = $_GET['episode_info'] ?? '';
$current_source_index = 0;

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
    if ($movie_slug !== '') {
        $stmt = $conn->prepare("SELECT * FROM movies WHERE slug = ?");
        $stmt->bind_param("s", $movie_slug);
        $stmt->execute();
        $content = $stmt->get_result()->fetch_assoc();
        if ($content) {
            $id = (int) $content['id'];
        }
    } else {
        $stmt = $conn->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $content = $stmt->get_result()->fetch_assoc();
    }
    $title = $content['title'] ?? 'Movie';

    if ($content) {
        $movieAccess = enforceMovieWatchAccess($conn, $content);
        if (!$movieAccess['allowed'] && $movieAccess['reason'] === 'premium') {
            $showPremiumGate = true;
        }

        if ($movieAccess['allowed']) {
            $conn->query("UPDATE movies SET views = views + 1 WHERE id = $id");
        }

        $sources = getActiveWatchSources($content);
        $source_param = isset($_GET['source']) ? intval($_GET['source']) : null;
        $resolved_index = movieSourceIndexFromUrlParam($source_param, count($sources));
        if (!empty($sources)) {
            $selectedSource = $sources[$resolved_index];
            $current_source_index = $resolved_index;
        } else {
            $selectedSource = null;
            $current_source_index = 0;
        }
        if ($movie_slug === '' && movieHasSlug($content) && isset($_GET['id'])) {
            header('Location: ' . getMovieWatchUrl($content, $current_source_index, $conn));
            exit();
        }
    }
} elseif ($type === 'tv_episode') {
    // Handle slug-based episode URLs: /watch-tv-show/{show-slug}/s{season}e{episode}
    if (!empty($show_slug) && !empty($episode_info)) {
        // Parse episode_info format: s{season}e{episode} (e.g., s01e01, s2e5)
        if (preg_match('/^s(\d+)e(\d+)$/i', $episode_info, $matches)) {
            $season_number = intval($matches[1]);
            $episode_number = intval($matches[2]);
            
            // Query episode by show slug, season, and episode number
            $stmt = $conn->prepare("SELECT e.*, t.title as show_title FROM tv_episodes e 
                                    JOIN tv_shows t ON e.tv_show_id = t.id 
                                    WHERE t.slug = ? AND e.season_number = ? AND e.episode_number = ?");
            $stmt->bind_param("sii", $show_slug, $season_number, $episode_number);
            $stmt->execute();
            $content = $stmt->get_result()->fetch_assoc();
            
            if ($content) {
                $id = $content['id']; // Set ID for views update
            }
        }
    } else {
        // Fallback to ID-based query
    $stmt = $conn->prepare("SELECT e.*, t.title as show_title FROM tv_episodes e JOIN tv_shows t ON e.tv_show_id = t.id WHERE e.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $content = $stmt->get_result()->fetch_assoc();
    }
    
    if ($content) {
    $title = ($content['show_title'] ?? 'TV Show') . ' - ' . ($content['title'] ?? 'Episode');
        
        // Get TV show slug for navigation
        $tv_show_slug = '';
        if (!empty($show_slug)) {
            $tv_show_slug = $show_slug;
        } else {
            // Get slug from TV show
            $show_slug_stmt = $conn->prepare("SELECT slug FROM tv_shows WHERE id = ?");
            $show_slug_stmt->bind_param("i", $content['tv_show_id']);
            $show_slug_stmt->execute();
            $show_slug_result = $show_slug_stmt->get_result()->fetch_assoc();
            if ($show_slug_result && !empty($show_slug_result['slug'])) {
                $tv_show_slug = $show_slug_result['slug'];
            }
        }
        
        // Get next and previous episodes
        $next_episode = null;
        $prev_episode = null;
        $current_season = $content['season_number'] ?? 1;
        $current_episode = $content['episode_number'] ?? 1;
        $tv_show_id = $content['tv_show_id'] ?? 0;
        
        // Get next episode (same season, next episode number, or next season first episode)
        $next_stmt = $conn->prepare("SELECT * FROM tv_episodes WHERE tv_show_id = ? 
                                    AND ((season_number = ? AND episode_number > ?) OR (season_number > ?))
                                    ORDER BY season_number ASC, episode_number ASC LIMIT 1");
        $next_stmt->bind_param("iiii", $tv_show_id, $current_season, $current_episode, $current_season);
        $next_stmt->execute();
        $next_result = $next_stmt->get_result();
        if ($next_result->num_rows > 0) {
            $next_episode = $next_result->fetch_assoc();
        }
        
        // Get previous episode (same season, previous episode number, or previous season last episode)
        $prev_stmt = $conn->prepare("SELECT * FROM tv_episodes WHERE tv_show_id = ? 
                                    AND ((season_number = ? AND episode_number < ?) OR (season_number < ?))
                                    ORDER BY season_number DESC, episode_number DESC LIMIT 1");
        $prev_stmt->bind_param("iiii", $tv_show_id, $current_season, $current_episode, $current_season);
        $prev_stmt->execute();
        $prev_result = $prev_stmt->get_result();
        if ($prev_result->num_rows > 0) {
            $prev_episode = $prev_result->fetch_assoc();
        }
    
    // Parse sources for TV episodes
        $current_source_index = 0;
        if (!empty($content['sources'])) {
        $sources = parseSources($content['sources']);
        // Filter active and visible sources, sort by priority
        $activeSources = array_filter($sources, function($s) {
            return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true);
        });
            $activeSources = array_values($activeSources);
        usort($activeSources, function($a, $b) {
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });
        $sources = $activeSources;
            
            // Get source index from URL parameter (like live TV)
            $source_index = isset($_GET['source']) ? intval($_GET['source']) : null;
            
            // Select source based on URL parameter or default to first
            if ($source_index !== null && $source_index >= 0 && $source_index < count($sources)) {
                $selectedSource = $sources[$source_index];
                $current_source_index = $source_index;
            } else {
        $selectedSource = !empty($sources) ? $sources[0] : null;
                $current_source_index = 0;
            }
    }
    
    // Update views
        if ($id > 0) {
        $conn->query("UPDATE tv_episodes SET views = views + 1 WHERE id = $id");
        }
        
        // Get TV show data for ad settings
        $tv_show_data = null;
        if (!empty($content['tv_show_id'])) {
            $show_stmt = $conn->prepare("SELECT * FROM tv_shows WHERE id = ?");
            $show_stmt->bind_param("i", $content['tv_show_id']);
            $show_stmt->execute();
            $show_result = $show_stmt->get_result();
            if ($show_result->num_rows > 0) {
                $tv_show_data = $show_result->fetch_assoc();
            }
        }
        
        // Get ads for this TV show (similar to live TV)
        $episode_ads = [];
        $intro_ad = null;
        
        // Check if user has subscription (premium users don't see ads except intro ads)
        $hasSubscription = false;
        if (isLoggedIn()) {
            $user_id = $_SESSION['user_id'] ?? null;
            if ($user_id) {
                $user_stmt = $conn->prepare("SELECT subscription_type, subscription_expires_at FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                if ($user_result->num_rows > 0) {
                    $user_data = $user_result->fetch_assoc();
                    $sub_type = $user_data['subscription_type'] ?? 'free';
                    $sub_expires = $user_data['subscription_expires_at'] ?? null;
                    if ($sub_type !== 'free' && $sub_type !== null) {
                        if ($sub_expires === null || strtotime($sub_expires) > time()) {
                            $hasSubscription = true;
                        }
                    }
                }
            }
        }
        
        // Premium users don't see ads (except intro ads)
        $show_ads = !$hasSubscription;
        
        // Ensure ad columns exist (check first to avoid errors)
        try {
            $columns_to_add = [
                'pre_roll_ad_id' => 'INT NULL',
                'mid_roll_ad_id' => 'INT NULL',
                'end_roll_ad_id' => 'INT NULL',
                'loop_ad_id' => 'INT NULL',
                'loop_interval' => 'INT NULL',
                'banner_ad_id' => 'INT NULL',
                'popup_ad_id' => 'INT NULL',
                'intro_ad_id' => 'INT NULL'
            ];
            
            foreach ($columns_to_add as $column => $definition) {
                $check = $conn->query("SHOW COLUMNS FROM tv_shows LIKE '$column'");
                if ($check->num_rows == 0) {
                    $conn->query("ALTER TABLE tv_shows ADD COLUMN $column $definition");
                }
            }
        } catch (Exception $e) {
            // Columns might already exist or error occurred
            error_log("Error adding ad columns to tv_shows: " . $e->getMessage());
        }
        
        if ($tv_show_data) {
            // Always get intro ads (plays to everyone)
            $show_intro_ad_id = $tv_show_data['intro_ad_id'] ?? null;
            if (!empty($show_intro_ad_id)) {
                $intro_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND type = 'intro-ad' AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                $intro_stmt->bind_param("i", $show_intro_ad_id);
                $intro_stmt->execute();
                $intro_result = $intro_stmt->get_result();
                if ($intro_result->num_rows > 0) {
                    $intro_ad = $intro_result->fetch_assoc();
                }
            } else {
                // Fallback to global intro ad if show doesn't have one
                $intro_stmt = $conn->prepare("SELECT * FROM ads WHERE type = 'intro-ad' AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY created_at DESC LIMIT 1");
                $intro_stmt->execute();
                $intro_result = $intro_stmt->get_result();
                if ($intro_result->num_rows > 0) {
                    $intro_ad = $intro_result->fetch_assoc();
                }
            }
            
            // Always fetch loop ad configuration (for debugging), but only show for free users
            if (!empty($tv_show_data['loop_ad_id'])) {
                $loop_ad_id = intval($tv_show_data['loop_ad_id']);
                $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                $ad_stmt->bind_param("i", $loop_ad_id);
                $ad_stmt->execute();
                $ad_result = $ad_stmt->get_result();
                if ($ad_result->num_rows > 0) {
                    $loop_ad = $ad_result->fetch_assoc();
                    $episode_ads['loop'] = $loop_ad;
                    // Use ad's loop_interval (how often to show), or fallback to duration, or show setting, then 60
                    $episode_ads['loop_interval'] = !empty($loop_ad['loop_interval']) ? intval($loop_ad['loop_interval']) : 
                                                  (!empty($loop_ad['duration']) ? intval($loop_ad['duration']) : 
                                                  (!empty($tv_show_data['loop_interval']) ? intval($tv_show_data['loop_interval']) : 60));
                }
            }
            
            if ($show_ads) {
                // Get show-specific ads (pre-roll, mid-roll, end-roll, banner, popup)
                if (!empty($tv_show_data['pre_roll_ad_id'])) {
                    $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                    $ad_stmt->bind_param("i", $tv_show_data['pre_roll_ad_id']);
                    $ad_stmt->execute();
                    $ad_result = $ad_stmt->get_result();
                    if ($ad_result->num_rows > 0) {
                        $episode_ads['pre_roll'] = $ad_result->fetch_assoc();
                    }
                }
                
                if (!empty($tv_show_data['mid_roll_ad_id'])) {
                    $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                    $ad_stmt->bind_param("i", $tv_show_data['mid_roll_ad_id']);
                    $ad_stmt->execute();
                    $ad_result = $ad_stmt->get_result();
                    if ($ad_result->num_rows > 0) {
                        $episode_ads['mid_roll'] = $ad_result->fetch_assoc();
                    }
                }
                
                if (!empty($tv_show_data['end_roll_ad_id'])) {
                    $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                    $ad_stmt->bind_param("i", $tv_show_data['end_roll_ad_id']);
                    $ad_stmt->execute();
                    $ad_result = $ad_stmt->get_result();
                    if ($ad_result->num_rows > 0) {
                        $episode_ads['end_roll'] = $ad_result->fetch_assoc();
                    }
                }
                
                if (!empty($tv_show_data['banner_ad_id'])) {
                    $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                    $ad_stmt->bind_param("i", $tv_show_data['banner_ad_id']);
                    $ad_stmt->execute();
                    $ad_result = $ad_stmt->get_result();
                    if ($ad_result->num_rows > 0) {
                        $episode_ads['banner'] = $ad_result->fetch_assoc();
                    }
                }
                
                if (!empty($tv_show_data['popup_ad_id'])) {
                    $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
                    $ad_stmt->bind_param("i", $tv_show_data['popup_ad_id']);
                    $ad_stmt->execute();
                    $ad_result = $ad_stmt->get_result();
                    if ($ad_result->num_rows > 0) {
                        $episode_ads['popup'] = $ad_result->fetch_assoc();
                    }
                }
            }
        }
    }
}

if (!$content) {
    header('Location: ' . url());
    exit();
}

$page_title = $title;

if ($type === 'movie' && !empty($content)) {
    applyMovieSeoMeta($conn, $content, 'watch');
    $page_title = $GLOBALS['page_title'] ?? $title;
    require_once __DIR__ . '/includes/content_ads.php';
    ensureContentAdColumns($conn, 'movies');
    $movieAds = loadContentAds($conn, $content);
    $intro_ad = $movieAds['intro_ad'];
    $episode_ads = $movieAds['ads'];
    $show_ads = $movieAds['show_ads'];
    $hasSubscription = $movieAds['has_subscription'];
}

// Get episode info for TV episodes
$episode_id = 0;
$episode_title = '';
$show_title = '';
$episode_thumbnail = '';
$isAndroidTV = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android TV|AFT|BRAVIA|MiTV|SmartTV|GoogleTV|Tizen|Web0S|HbbTV)/i', $_SERVER['HTTP_USER_AGENT']);

if ($type === 'tv_episode' && $content) {
    $episode_id = $content['id'];
    $episode_title = $content['title'] ?? 'Episode';
    $show_title = $content['show_title'] ?? 'TV Show';
    $episode_thumbnail = $content['thumbnail'] ?? '';
    
    // Get TV show info for thumbnail fallback
    if (empty($episode_thumbnail)) {
        $show_info_stmt = $conn->prepare("SELECT poster FROM tv_shows WHERE id = ?");
        $show_info_stmt->bind_param("i", $content['tv_show_id']);
        $show_info_stmt->execute();
        $show_info_result = $show_info_stmt->get_result()->fetch_assoc();
        if ($show_info_result && !empty($show_info_result['poster'])) {
            $episode_thumbnail = $show_info_result['poster'];
        }
    }
    
    $episode_thumbnail = assetUrl($episode_thumbnail);
    
    // Initialize viewer count (will be updated via AJAX)
    $content['current_viewers'] = 0;
}

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

$use_advanced_player = (($type === 'tv_episode' || $type === 'movie') && !empty($sources) && !$showPremiumGate);
$use_standalone_watch_layout = ($type === 'tv_episode' || ($type === 'movie' && $use_advanced_player));
$movie_poster_header = ($type === 'movie' && !empty($content)) ? moviePosterUrl($content) : '';
$movie_header_subtitle = 'Movie';
if ($type === 'movie' && !empty($content)) {
    $subtitle_parts = [];
    if (!empty($content['release_year'])) {
        $subtitle_parts[] = (int) $content['release_year'];
    }
    if (!empty($content['quality_label'])) {
        $subtitle_parts[] = $content['quality_label'];
    }
    if (!empty($content['category_name'])) {
        $subtitle_parts[] = $content['category_name'];
    }
    $movie_header_subtitle = !empty($subtitle_parts) ? implode(' | ', $subtitle_parts) : 'Movie';
}

// Only include site header for non-player layouts
if (!$use_standalone_watch_layout) {
include 'includes/header.php';
} else {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo htmlspecialchars(($page_title ?? 'Watch') . ' - ' . getSetting($conn, 'site_name', 'StreamPanel')); ?></title>
    <?php if (!empty($meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    <?php if (!empty($meta_keywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #000; color: #fff; font-family: system-ui, -apple-system, sans-serif; }
        .netflix-red {
            color: #e50914;
        }
        .bg-netflix-red {
            background-color: #e50914;
        }
    </style>
</head>
<body>
    <?php
}

if ($type === 'tv_episode' && !empty($sources)): ?>
<!-- Streaming libraries: HLS.js and dash.js -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>
<?php elseif ($type === 'movie' && !empty($sources)): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
<script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>
<?php endif; ?>

<style>
/* Watch Page Styles - Matching Live TV Style */
.watch-page {
    min-height: 100vh;
    background: #000;
    color: #fff;
    font-family: system-ui, -apple-system, sans-serif;
}
@media (min-width: 768px) {
    .watch-page {
        padding-bottom: 0; /* Remove on desktop */
    }
}

/* Sticky Header - Same as Live TV */
.sticky-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(4px);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 0.75rem 1rem;
}
@media (min-width: 768px) {
    .sticky-header {
        padding: 1rem 3rem;
    }
}

/* Mobile Header - Two Rows */
.mobile-header-row1 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.mobile-header-row2 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
@media (min-width: 768px) {
    .mobile-header-row1,
    .mobile-header-row2 {
        display: none;
    }
}

/* Desktop Header - Single Row */
.desktop-header {
    display: none;
    align-items: center;
    gap: 1rem;
}
@media (min-width: 768px) {
    .desktop-header {
        display: flex;
    }
}

.header-back-btn {
    padding: 0.5rem;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-back-btn:hover {
    background: rgba(255,255,255,0.1);
}

.episode-thumbnail-header {
    width: 2.5rem;
    height: 2.5rem;
    object-fit: cover;
    flex-shrink: 0;
    border-radius: 0.25rem;
}

.episode-info-header {
    flex: 1;
    min-width: 0;
}
.episode-info-header h1 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (min-width: 768px) {
    .episode-info-header h1 {
        font-size: 1.25rem;
    }
}
.episode-info-header p {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (min-width: 768px) {
    .episode-info-header p {
        font-size: 0.875rem;
    }
}

.viewer-count-header {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    color: #4ade80;
    flex-shrink: 0;
}
.viewer-count-header svg {
    width: 18px;
    height: 18px;
    color: #4ade80;
}
.viewer-count-header span {
    font-weight: 600;
}

.fullscreen-btn-header {
    padding: 0.5rem;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fullscreen-btn-header:hover {
    background: rgba(255,255,255,0.1);
}
.fullscreen-btn-header svg {
    width: 20px;
    height: 20px;
}

.player-error-message {
    font-size: 0.75rem;
    color: #facc15;
    margin-top: 0.5rem;
}

.watch-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0;
}
.video-wrapper {
    background: #000;
    width: 100%;
    margin-bottom: 0;
    position: relative;
}
.player-container {
    width: 100%;
    background: #000;
    position: relative;
}<?php if ($type === 'tv_episode' || $type === 'movie'): ?>
/* Match live TV player size */
.player-container,
.player-container-mobile {
    height: calc(100vh - 80px - 220px);
    min-height: 250px;
    max-height: calc(100vh - 300px);
}
@media (max-width: 480px) {
    .player-container,
    .player-container-mobile {
        height: calc(100vh - 80px - 380px);
        min-height: 180px;
        max-height: calc(100vh - 460px);
    }
}
@media (min-width: 481px) and (max-width: 768px) {
    .player-container,
    .player-container-mobile {
        height: calc(100vh - 80px - 300px);
        min-height: 220px;
        max-height: calc(100vh - 380px);
    }
}
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
    overflow: hidden;
}
<?php endif; ?>
.player-container #player-container,
.player-container.player-container-mobile {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
}
.player-container #player-container > *,
.video-player-wrapper > * {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}
/* Source Selection Buttons */
.try-another-source-section {
    padding: 1rem;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.3);
}
@media (min-width: 768px) {
    .try-another-source-section {
        padding: 1.5rem;
    }
}
.try-another-source-text {
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}
.try-another-source-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
}
.try-source-link {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: rgba(229,9,20,0.8);
    color: #fff;
    text-decoration: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}
.try-source-link:hover {
    background: rgba(229,9,20,1);
    transform: scale(1.05);
    text-decoration: none;
    color: #fff;
}
.try-source-link.active {
    background: rgba(229,9,20,1);
}
/* Ad Overlay Styles */
.ad-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ad-container {
    position: relative;
    width: 100%;
    height: 100%;
    max-width: 1920px;
    max-height: 1080px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.ad-controls {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 1001;
}
.ad-countdown {
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
}
.ad-skip-btn {
    background: rgba(229, 9, 20, 0.9);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.ad-skip-btn:hover {
    background: rgba(229, 9, 20, 1);
}
#ad-content {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
#ad-content iframe,
#ad-content video,
#ad-content img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.watch-info {
    color: #fff;
    padding: 2rem 1.5rem;
}
@media (min-width: 768px) {
    .watch-info {
        padding: 3rem;
    }
}
.watch-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.75rem;
    line-height: 1.2;
}
@media (min-width: 768px) {
    .watch-title {
        font-size: 2rem;
    }
}
.watch-description {
    color: #d1d5db;
    margin-bottom: 1.5rem;
    line-height: 1.6;
    font-size: 0.875rem;
}
@media (min-width: 768px) {
    .watch-description {
        font-size: 1rem;
    }
}
.watch-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}
</style>

<div class="watch-page animate-in fade-in">
    <?php if ($type === 'tv_episode' || ($type === 'movie' && $use_advanced_player)): ?>
    <!-- Header -->
    <?php if ($type === 'tv_episode' && !$isAndroidTV): ?>
    <div class="sticky-header">
        <!-- Mobile: Two rows -->
        <div class="mobile-header-row1">
            <button class="header-back-btn" onclick="handleBack()" aria-label="Back">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5"></path>
                    <path d="m12 19-7-7 7-7"></path>
            </svg>
            </button>
            <?php if (!empty($episode_thumbnail)): ?>
                <img src="<?php echo htmlspecialchars($episode_thumbnail); ?>" alt="<?php echo htmlspecialchars($episode_title); ?>" class="episode-thumbnail-header">
            <?php endif; ?>
            <div class="episode-info-header">
                <h1><?php echo htmlspecialchars($episode_title); ?></h1>
                <p><?php echo htmlspecialchars($show_title); ?> - Season <?php echo $content['season_number']; ?>, Episode <?php echo $content['episode_number']; ?></p>
            </div>
            
            <?php if (isLoggedIn()): ?>
            <button id="favoriteBtnMobile" onclick="toggleFavorite()" 
                    class="header-back-btn" aria-label="Add to Favorites" title="Add to Favorites">
                <i class="fas fa-heart" id="favoriteIconMobile"></i>
            </button>
            <?php endif; ?>
            <button type="button" class="header-back-btn" onclick="openStreamReportModal()" aria-label="Report" title="Report a problem">
                <i class="fas fa-flag"></i>
            </button>
            
            <div class="viewer-count-header" id="viewer-count-mobile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <span id="viewer-count-mobile-text">0</span>
            </div>
            
            <button class="fullscreen-btn-header" id="fullscreen-button-mobile" onclick="toggleFullscreen()" title="Enter Fullscreen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path>
                </svg>
            </button>
        </div>
        
        <!-- Desktop: Single row -->
        <div class="desktop-header">
            <button class="header-back-btn" onclick="handleBack()" aria-label="Back">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5"></path>
                    <path d="m12 19-7-7 7-7"></path>
                </svg>
            </button>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0;">
                <?php if (!empty($episode_thumbnail)): ?>
                    <img src="<?php echo htmlspecialchars($episode_thumbnail); ?>" alt="<?php echo htmlspecialchars($episode_title); ?>" class="episode-thumbnail-header">
                <?php endif; ?>
                <div class="episode-info-header">
                    <h1><?php echo htmlspecialchars($episode_title); ?></h1>
                    <p><?php echo htmlspecialchars($show_title); ?> - Season <?php echo $content['season_number']; ?>, Episode <?php echo $content['episode_number']; ?></p>
                </div>
                
                <?php if (isLoggedIn()): ?>
                <button id="favoriteBtnDesktop" onclick="toggleFavorite()" 
                        class="header-back-btn" aria-label="Add to Favorites" title="Add to Favorites">
                    <i class="fas fa-heart" id="favoriteIconDesktop"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="header-back-btn" onclick="openStreamReportModal()" aria-label="Report" title="Report a problem">
                    <i class="fas fa-flag"></i>
                </button>
                
                <div class="viewer-count-header" id="viewer-count-desktop">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span id="viewer-count-desktop-text">0</span>
                </div>
                
                <button class="fullscreen-btn-header" id="fullscreen-button-desktop" onclick="toggleFullscreen()" title="Enter Fullscreen">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <p id="player-error-message" class="player-error-message" style="display: none;">
            ⚠️ Stream error – please refresh the page
        </p>
    </div>
    <?php elseif ($type === 'movie' && !$isAndroidTV): ?>
    <div class="sticky-header">
        <div class="mobile-header-row1">
            <button class="header-back-btn" onclick="handleBack()" aria-label="Back to movie">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5"></path>
                    <path d="m12 19-7-7 7-7"></path>
                </svg>
            </button>
            <?php if (!empty($movie_poster_header)): ?>
            <img src="<?php echo htmlspecialchars($movie_poster_header); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="episode-thumbnail-header" onerror="this.style.display='none'">
            <?php endif; ?>
            <div class="episode-info-header">
                <h1><?php echo htmlspecialchars($title); ?></h1>
                <p><?php echo htmlspecialchars($movie_header_subtitle); ?></p>
            </div>
            <?php if (isLoggedIn()): ?>
            <button id="favoriteBtnMobile" onclick="toggleFavorite()" class="header-back-btn" aria-label="Add to Favorites" title="Add to Favorites">
                <i class="fas fa-heart" id="favoriteIconMobile"></i>
            </button>
            <?php endif; ?>
            <button type="button" class="header-back-btn" onclick="openStreamReportModal()" aria-label="Report" title="Report a problem">
                <i class="fas fa-flag"></i>
            </button>
            <div class="viewer-count-header" id="viewer-count-mobile">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <span id="viewer-count-mobile-text">0</span>
            </div>
            <button class="fullscreen-btn-header" id="fullscreen-button-mobile" onclick="toggleFullscreen()" title="Enter Fullscreen">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path>
                </svg>
            </button>
        </div>
        <div class="desktop-header">
            <button class="header-back-btn" onclick="handleBack()" aria-label="Back to movie">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5"></path>
                    <path d="m12 19-7-7 7-7"></path>
                </svg>
            </button>
            <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0;">
                <?php if (!empty($movie_poster_header)): ?>
                <img src="<?php echo htmlspecialchars($movie_poster_header); ?>" alt="<?php echo htmlspecialchars($title); ?>" class="episode-thumbnail-header" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="episode-info-header">
                    <h1><?php echo htmlspecialchars($title); ?></h1>
                    <p><?php echo htmlspecialchars($movie_header_subtitle); ?></p>
                </div>
                <?php if (isLoggedIn()): ?>
                <button id="favoriteBtnDesktop" onclick="toggleFavorite()" class="header-back-btn" aria-label="Add to Favorites" title="Add to Favorites">
                    <i class="fas fa-heart" id="favoriteIconDesktop"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="header-back-btn" onclick="openStreamReportModal()" aria-label="Report" title="Report a problem">
                    <i class="fas fa-flag"></i>
                </button>
                <div class="viewer-count-header" id="viewer-count-desktop">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span id="viewer-count-desktop-text">0</span>
                </div>
                <button class="fullscreen-btn-header" id="fullscreen-button-desktop" onclick="toggleFullscreen()" title="Enter Fullscreen">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path>
                    </svg>
                </button>
            </div>
        </div>
        <p id="player-error-message" class="player-error-message" style="display: none;">
            ⚠️ Stream error – please refresh the page
        </p>
    </div>
    <?php endif; ?>
    
    <?php if ($type === 'movie' && $use_advanced_player): ?>
    <div id="player-container" class="player-container player-container-mobile">
        <div class="video-player-wrapper" id="video-wrapper">
            <iframe id="youtubePlayer" style="display: none; border: none;" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
            <div id="htmlEmbedContainer" style="display: none;"></div>
            <video id="videoPlayer" style="display: none;" controls autoplay playsinline class="w-full h-full">Your browser does not support the video tag.</video>
            <div id="playerMessage" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center; display: none; z-index: 5;">
                <div id="playerMessageText"></div>
            </div>
            <div id="ad-overlay" class="ad-overlay" style="display: none;">
                <div class="ad-container">
                    <div id="ad-content"></div>
                    <div id="ad-controls" class="ad-controls">
                        <div id="ad-countdown" class="ad-countdown"></div>
                        <button id="ad-skip-btn" class="ad-skip-btn" style="display: none;" onclick="skipAd()">
                            Skip Ad (<span id="skip-timer">5</span>s)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="watch-container">
        <div class="video-wrapper player-container">
            <?php if ($use_advanced_player): ?>
                <!-- Multi-source Player -->
                <div id="player-container">
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
                           controls autoplay playsinline
                           class="w-full h-full">
                        Your browser does not support the video tag.
                    </video>
                    
                    <!-- Loading/Error Message -->
                    <div id="playerMessage" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-align: center; display: none; z-index: 5;">
                        <div id="playerMessageText"></div>
                    </div>
                    
                    <!-- Ad Overlay (inside player container) -->
                    <div id="ad-overlay" class="ad-overlay" style="display: none;">
                        <div class="ad-container">
                            <div id="ad-content"></div>
                            <div id="ad-controls" class="ad-controls">
                                <div id="ad-countdown" class="ad-countdown"></div>
                                <button id="ad-skip-btn" class="ad-skip-btn" style="display: none;" onclick="skipAd()">
                                    Skip Ad (<span id="skip-timer">5</span>s)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Movie Player (direct video) -->
                <video id="videoPlayer" controls autoplay>
                    <source src="<?php echo htmlspecialchars($content['video_url'] ?? $content['stream_url'] ?? ''); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

        <!-- Source Selection Buttons (if multiple sources) -->
        <?php if ($use_advanced_player && count($sources) > 1): ?>
        <div class="try-another-source-section">
            <p class="try-another-source-text">If stream not playing video, Try Another Source:</p>
            <div class="try-another-source-links">
                            <?php foreach ($sources as $idx => $src): ?>
                    <?php
                    $is_current = ($idx === $current_source_index);
                    $source_num = $idx + 1;
                    
                    if ($type === 'movie') {
                        $source_url = getMovieWatchUrl($content, $idx, $conn);
                    } elseif (!empty($tv_show_slug) && !empty($episode_info)) {
                        $source_url = BASE_URL . '/watch-tv-show/' . htmlspecialchars($tv_show_slug) . '/' . htmlspecialchars($episode_info) . '?source=' . $idx;
                    } else {
                        $source_url = BASE_URL . '/watch.php?type=tv_episode&id=' . $content['id'] . '&source=' . $idx;
                    }
                    ?>
                    <?php if (!$is_current): ?>
                        <a href="<?php echo htmlspecialchars($source_url); ?>" class="try-source-link">
                            <?php echo htmlspecialchars($src['label'] ?? 'Source ' . $source_num); ?>
                        </a>
                    <?php endif; ?>
                            <?php endforeach; ?>
            </div>
            <p class="try-another-source-text" style="margin-top: 0.75rem;">
                <button type="button" onclick="openStreamReportModal()" style="background:none;border:none;color:#e50914;font-weight:700;cursor:pointer;padding:0;">Report a problem</button>
            </p>
                    </div>
                    <?php elseif ($use_advanced_player && ($type === 'tv_episode' || $type === 'movie')): ?>
        <div class="try-another-source-section">
            <button type="button" onclick="openStreamReportModal()" class="try-source-link">Report a problem</button>
        </div>
                    <?php endif; ?>

        <?php
        if ($use_advanced_player && ($type === 'tv_episode' || $type === 'movie') && !empty($content['id'])) {
            $report_content_type = ($type === 'tv_episode') ? 'tv_episode' : 'movie';
            $report_content_id = (int) $content['id'];
            $report_source_index = (int) ($current_source_index ?? 0);
            include __DIR__ . '/includes/stream-report-markup.php';
        }
        ?>

        <?php if ($type === 'movie'): ?>
        <?php $movieDownloadLinks = getActiveDownloadLinks($content); ?>
        <?php if (!empty($movieDownloadLinks) && !empty($movieAccess['allowed'])): ?>
        <div class="try-another-source-section" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <p class="try-another-source-text">Download Links:</p>
            <div class="try-another-source-links">
                <?php foreach ($movieDownloadLinks as $dlink): ?>
                <a href="<?php echo htmlspecialchars($dlink['url']); ?>" class="try-source-link" target="_blank" rel="noopener noreferrer">
                    <i class="fas fa-download mr-1"></i>
                    <?php echo htmlspecialchars($dlink['label'] ?? 'Download'); ?>
                    <?php if (!empty($dlink['quality'])): ?>(<?php echo htmlspecialchars($dlink['quality']); ?>)<?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <!-- Next/Previous Episode Navigation -->
        <?php if ($type === 'tv_episode' && (isset($next_episode) || isset($prev_episode))): ?>
        <div class="episode-navigation-section" style="padding: 1.5rem 1rem; border-top: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.3);">
            <div style="display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center;">
                <?php if (isset($prev_episode)): ?>
                <?php
                $prev_season_padded = str_pad($prev_episode['season_number'], 2, '0', STR_PAD_LEFT);
                $prev_episode_padded = str_pad($prev_episode['episode_number'], 2, '0', STR_PAD_LEFT);
                $prev_episode_slug = 's' . $prev_season_padded . 'e' . $prev_episode_padded;
                $prev_url = !empty($tv_show_slug) ? BASE_URL . '/watch-tv-show/' . htmlspecialchars($tv_show_slug) . '/' . $prev_episode_slug : BASE_URL . '/watch.php?type=tv_episode&id=' . $prev_episode['id'];
                ?>
                <a href="<?php echo $prev_url; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; background: rgba(255,255,255,0.1); border-radius: 0.5rem; text-decoration: none; color: #fff; transition: background 0.2s; flex: 1; min-width: 200px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5"></path>
                        <path d="m12 19-7-7 7-7"></path>
                    </svg>
                    <div style="text-align: left;">
                        <div style="font-size: 0.75rem; color: #9ca3af; margin-bottom: 0.25rem;">Previous Episode</div>
                        <div style="font-size: 0.875rem; font-weight: 600;">S<?php echo $prev_episode['season_number']; ?>E<?php echo $prev_episode['episode_number']; ?>: <?php echo htmlspecialchars($prev_episode['title']); ?></div>
                </div>
                </a>
            <?php else: ?>
                <div style="flex: 1; min-width: 200px;"></div>
                <?php endif; ?>
                
                <?php if (isset($next_episode)): ?>
                <?php
                $next_season_padded = str_pad($next_episode['season_number'], 2, '0', STR_PAD_LEFT);
                $next_episode_padded = str_pad($next_episode['episode_number'], 2, '0', STR_PAD_LEFT);
                $next_episode_slug = 's' . $next_season_padded . 'e' . $next_episode_padded;
                $next_url = !empty($tv_show_slug) ? BASE_URL . '/watch-tv-show/' . htmlspecialchars($tv_show_slug) . '/' . $next_episode_slug : BASE_URL . '/watch.php?type=tv_episode&id=' . $next_episode['id'];
                ?>
                <a href="<?php echo $next_url; ?>" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1.5rem; background: rgba(229,9,20,0.8); border-radius: 0.5rem; text-decoration: none; color: #fff; transition: background 0.2s; flex: 1; min-width: 200px; justify-content: flex-end;">
                    <div style="text-align: right;">
                        <div style="font-size: 0.75rem; color: rgba(255,255,255,0.9); margin-bottom: 0.25rem;">Next Episode</div>
                        <div style="font-size: 0.875rem; font-weight: 600;">S<?php echo $next_episode['season_number']; ?>E<?php echo $next_episode['episode_number']; ?>: <?php echo htmlspecialchars($next_episode['title']); ?></div>
                    </div>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14"></path>
                        <path d="m12 5 7 7-7 7"></path>
                    </svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php elseif ($type === 'movie' && $showPremiumGate): ?>
    <!-- Movies premium gate -->
    <?php
        $premium_gate_back_url = getMovieDetailUrl($content, $conn);
        $premium_gate_back_label = 'Back to Movie';
        $premium_gate_message = 'This movie requires a Premium subscription. Please upgrade to watch.';
        include __DIR__ . '/includes/premium-gate.php';
    ?>
    <?php elseif ($type === 'movie'): ?>
    <!-- Movie fallback when no JSON sources -->
    <div class="watch-container">
        <div class="video-wrapper player-container">
                <video id="videoPlayer" controls autoplay>
                    <source src="<?php echo htmlspecialchars($content['video_url'] ?? $content['stream_url'] ?? ''); ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
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
                <span><i class="fas fa-calendar mr-2"></i><?php echo $content['release_year']; ?></span>
                <?php if (!empty($content['duration']) && $content['duration'] > 0): ?>
                <span><i class="fas fa-clock mr-2"></i><?php echo $content['duration']; ?> min</span>
                <?php endif; ?>
                <span><i class="fas fa-star mr-2"></i><?php echo number_format($content['rating'], 1); ?></span>
                <span><i class="fas fa-eye mr-2"></i><?php echo number_format($content['views']); ?> views</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
if ($use_standalone_watch_layout) {
    $minimal_site_footer = true;
}
include 'includes/footer.php';
?>

<script>
// Check favorite status and update button
<?php if (isLoggedIn()): ?>
const contentType = '<?php echo $type === "tv_episode" ? "tv_show" : ($type === "live_tv" ? "live_tv" : "movie"); ?>';
const contentId = <?php echo $type === "tv_episode" ? ($content['tv_show_id'] ?? $content['id']) : $content['id']; ?>;

async function checkFavorite() {
    try {
        const response = await fetch(`<?php echo apiUrl('api/favorites.php'); ?>?content_type=${contentType}&content_id=${contentId}`);
        const data = await response.json();
        if (data.success && data.is_favorite) {
            // Update mobile icon
            const mobileIcon = document.getElementById('favoriteIconMobile');
            if (mobileIcon) mobileIcon.classList.add('text-red-500');
            // Update desktop icon
            const desktopIcon = document.getElementById('favoriteIconDesktop');
            if (desktopIcon) desktopIcon.classList.add('text-red-500');
            // Update regular icon (for movies)
            const regularIcon = document.getElementById('favoriteIcon');
            if (regularIcon) {
                regularIcon.classList.add('text-red-500');
                const favoriteText = document.getElementById('favoriteText');
                if (favoriteText) favoriteText.textContent = 'Remove from Favorites';
            }
        }
    } catch (error) {
        console.error('Error checking favorite:', error);
    }
}

async function toggleFavorite() {
    const mobileIcon = document.getElementById('favoriteIconMobile');
    const desktopIcon = document.getElementById('favoriteIconDesktop');
    const regularIcon = document.getElementById('favoriteIcon');
    const text = document.getElementById('favoriteText');
    
    // Check if favorite (check any available icon)
    const icon = mobileIcon || desktopIcon || regularIcon;
    const isFavorite = icon && icon.classList.contains('text-red-500');
    
    try {
        const url = `<?php echo apiUrl('api/favorites.php'); ?>`;
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
                // Add favorite to all icons
                if (mobileIcon) mobileIcon.classList.add('text-red-500');
                if (desktopIcon) desktopIcon.classList.add('text-red-500');
                if (regularIcon) regularIcon.classList.add('text-red-500');
                if (text) text.textContent = 'Remove from Favorites';
            } else {
                // Remove favorite from all icons
                if (mobileIcon) mobileIcon.classList.remove('text-red-500');
                if (desktopIcon) desktopIcon.classList.remove('text-red-500');
                if (regularIcon) regularIcon.classList.remove('text-red-500');
                if (text) text.textContent = 'Add to Favorites';
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

// Multi-source Player Logic
<?php if ($use_advanced_player): ?>
const episodeSources = <?php echo json_encode($sources, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
let currentSourceIndex = <?php echo $current_source_index; ?>;

// Ad management data
const adsData = <?php echo json_encode([
    'intro_ad' => $intro_ad ?? null,
    'pre_roll' => $episode_ads['pre_roll'] ?? null,
    'mid_roll' => $episode_ads['mid_roll'] ?? null,
    'end_roll' => $episode_ads['end_roll'] ?? null,
    'loop' => $episode_ads['loop'] ?? null,
    'loop_interval' => $episode_ads['loop_interval'] ?? null,
    'banner' => $episode_ads['banner'] ?? null,
    'popup' => $episode_ads['popup'] ?? null,
    'show_ads' => $show_ads ?? false,
    'is_premium' => $hasSubscription ?? false
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

console.log('[TV Episode] Ads data loaded:', adsData);

// Helper: wrap http URLs behind local HTTPS proxy to avoid mixed-content blocking
function getProxiedStreamUrl(originalUrl) {
    try {
        if (!originalUrl) return originalUrl;
        if (window.location.protocol === 'https:' && /^http:\/\//i.test(originalUrl)) {
            const encoded = btoa(originalUrl);
            return `<?php echo apiUrl('proxy/hls-proxy.php'); ?>?u=${encodeURIComponent(encoded)}`;
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

// Ad management variables
let currentAd = null;
let adTimer = null;
let skipTimer = null;
let adCountdown = null;
let introAdShown = false;

// Show ad function
function showAd(ad, callback) {
    if (!ad) {
        console.log('[TV Episode] No ad to show');
        if (callback) callback();
        return;
    }
    
    console.log('[TV Episode] Showing ad:', ad.name, ad);
    currentAd = ad;
    const adOverlay = document.getElementById('ad-overlay');
    const adContent = document.getElementById('ad-content');
    const adCountdownEl = document.getElementById('ad-countdown');
    const adSkipBtn = document.getElementById('ad-skip-btn');
    const skipTimerEl = document.getElementById('skip-timer');
    
    if (!adOverlay || !adContent) {
        console.error('[TV Episode] Ad overlay elements not found');
        if (callback) callback();
        return;
    }
    
    adContent.innerHTML = '';
    adOverlay.style.display = 'flex';
    
    // Render ad based on content type
    if (ad.content_type === 'image' && ad.logo) {
        const img = document.createElement('img');
        let adPath = ad.logo;
        if (adPath.startsWith('uploads/')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        } else if (!adPath.startsWith('http')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        }
        console.log('[TV Episode] Loading ad image from:', adPath);
        img.src = adPath;
        img.style.width = '100%';
        img.style.height = '100%';
        img.style.objectFit = 'contain';
        img.onerror = function() {
            console.error('[TV Episode] Failed to load ad image:', adPath);
            setTimeout(function() {
                hideAd();
                if (callback) callback();
            }, 2000);
        };
        adContent.appendChild(img);
    } else if (ad.content_type === 'video' && ad.logo) {
        const videoContainer = document.createElement('div');
        videoContainer.style.cssText = 'position: relative; width: 100%; height: 100%; background: #000; display: flex; align-items: center; justify-content: center;';
        
        const video = document.createElement('video');
        let adPath = ad.logo;
        if (adPath.startsWith('uploads/')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        } else if (!adPath.startsWith('http')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        }
        console.log('[TV Episode] Loading ad video from:', adPath);
        video.src = adPath;
        video.style.cssText = 'width: 100%; height: 100%; object-fit: contain; background: #000;';
        video.controls = false;
        video.autoplay = true;
        video.playsInline = true;
        video.muted = false;
        
        videoContainer.appendChild(video);
        adContent.appendChild(videoContainer);
        
        video.addEventListener('ended', function() {
            console.log('[TV Episode] Ad video ended');
            hideAd();
            if (callback) callback();
        });
        
        video.play().catch(function(error) {
            console.log('[TV Episode] Ad video autoplay blocked, trying muted:', error);
            video.muted = true;
            video.play().then(function() {
                // Try to unmute after user interaction
                document.addEventListener('click', function unmuteAd() {
                    video.muted = false;
                    document.removeEventListener('click', unmuteAd);
                }, { once: true });
            }).catch(function(err) {
                console.error('[TV Episode] Failed to play ad video:', err);
                if (ad.duration) {
                    setTimeout(function() {
                        hideAd();
                        if (callback) callback();
                    }, ad.duration * 1000);
                }
            });
        });
    } else if (ad.content_type === 'html' && ad.content) {
        adContent.innerHTML = ad.content;
    }
    
    // Handle skip button
    const canSkip = ad.skipable == 1;
    if (canSkip && adSkipBtn) {
        adSkipBtn.style.display = 'block';
        let skipSeconds = 5;
        if (skipTimerEl) skipTimerEl.textContent = skipSeconds;
        
        skipTimer = setInterval(function() {
            skipSeconds--;
            if (skipTimerEl) skipTimerEl.textContent = skipSeconds;
            if (skipSeconds <= 0) {
                clearInterval(skipTimer);
            }
        }, 1000);
    } else if (adSkipBtn) {
        adSkipBtn.style.display = 'none';
    }
    
    // Countdown timer
    const duration = ad.duration || 10;
    let remaining = duration;
    if (adCountdownEl) adCountdownEl.textContent = 'Ad: ' + remaining + 's';
    
    adCountdown = setInterval(function() {
        remaining--;
        if (adCountdownEl) adCountdownEl.textContent = 'Ad: ' + remaining + 's';
        if (remaining <= 0) {
            clearInterval(adCountdown);
            hideAd();
            if (callback) callback();
        }
    }, 1000);
}

// Hide ad function
function hideAd() {
    const adOverlay = document.getElementById('ad-overlay');
    if (adOverlay) adOverlay.style.display = 'none';
    
    if (adTimer) {
        clearInterval(adTimer);
        adTimer = null;
    }
    if (skipTimer) {
        clearInterval(skipTimer);
        skipTimer = null;
    }
    if (adCountdown) {
        clearInterval(adCountdown);
        adCountdown = null;
    }
    
    currentAd = null;
}

// Skip ad function
function skipAd() {
    if (currentAd && currentAd.skipable == 1) {
        hideAd();
        if (typeof continueAfterAd === 'function') {
            continueAfterAd();
        }
    }
}

// Show banner ad (non-intrusive overlay)
function showBannerAd(ad) {
    if (!ad) return;
    
    const existingBanner = document.getElementById('banner-ad');
    if (existingBanner) existingBanner.remove();
    
    const banner = document.createElement('div');
    banner.id = 'banner-ad';
    banner.style.cssText = 'position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 999; max-width: 90%; max-height: 150px; background: rgba(0,0,0,0.8); padding: 10px; border-radius: 8px; cursor: pointer;';
    
    if (ad.content_type === 'image' && ad.logo) {
        const img = document.createElement('img');
        let adPath = ad.logo;
        if (adPath.startsWith('uploads/')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        } else if (!adPath.startsWith('http')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        }
        img.src = adPath;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '100%';
        img.style.objectFit = 'contain';
        banner.appendChild(img);
    } else if (ad.content_type === 'html' && ad.content) {
        banner.innerHTML = ad.content;
    }
    
    if (ad.url) {
        banner.onclick = function() {
            window.open(ad.url, '_blank');
        };
    }
    
    document.body.appendChild(banner);
    
    // Auto-remove after duration
    if (ad.duration) {
        setTimeout(function() {
            if (banner.parentNode) {
                banner.remove();
            }
        }, ad.duration * 1000);
    }
}

// Show popup ad
function showPopupAd(ad) {
    if (!ad) return;
    
    const existingPopup = document.getElementById('popup-ad');
    if (existingPopup) existingPopup.remove();
    
    const popup = document.createElement('div');
    popup.id = 'popup-ad';
    popup.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; align-items: center; justify-content: center;';
    
    const popupContent = document.createElement('div');
    popupContent.style.cssText = 'position: relative; max-width: 90%; max-height: 90%; background: #000; padding: 20px; border-radius: 8px;';
    
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'position: absolute; top: 10px; right: 10px; background: rgba(255,255,255,0.2); color: #fff; border: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: 20px;';
    closeBtn.onclick = function() {
        popup.remove();
    };
    popupContent.appendChild(closeBtn);
    
    if (ad.content_type === 'image' && ad.logo) {
        const img = document.createElement('img');
        let adPath = ad.logo;
        if (adPath.startsWith('uploads/')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        } else if (!adPath.startsWith('http')) {
            adPath = '<?php echo rtrim(BASE_URL, '/'); ?>/' + adPath;
        }
        img.src = adPath;
        img.style.maxWidth = '100%';
        img.style.maxHeight = '100%';
        img.style.objectFit = 'contain';
        popupContent.appendChild(img);
    } else if (ad.content_type === 'html' && ad.content) {
        popupContent.innerHTML += ad.content;
    }
    
    if (ad.url) {
        popupContent.style.cursor = 'pointer';
        popupContent.onclick = function() {
            window.open(ad.url, '_blank');
        };
    }
    
    popup.appendChild(popupContent);
    document.body.appendChild(popup);
    
    // Auto-remove after duration
    if (ad.duration) {
        setTimeout(function() {
            if (popup.parentNode) {
                popup.remove();
            }
        }, ad.duration * 1000);
    }
}

// Load source function
function loadSourceActual(sourceIndex) {
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
                console.error('[Watch] Invalid YouTube URL:', originalStreamUrl);
            }
        }
        return;
    }
    
    // Check for HTML embed / iframe
    const isHtmlEmbed = streamType === 'embed' || streamType === 'html-embed' || streamType === 'html' || streamType === 'iframe-only';
    if (isHtmlEmbed) {
        console.log('[Watch] Loading embed source');
        if (htmlEmbedContainer) {
            htmlEmbedContainer.style.display = 'block';
            const raw = (originalStreamUrl || '').trim();
            if (raw.startsWith('<')) {
                htmlEmbedContainer.innerHTML = raw;
            } else {
                htmlEmbedContainer.innerHTML = '<iframe src="' + raw.replace(/"/g, '&quot;') + '" allowfullscreen allow="autoplay; encrypted-media; picture-in-picture" style="width:100%;height:100%;border:none;"></iframe>';
            }
            const iframe = htmlEmbedContainer.querySelector('iframe');
            if (iframe) {
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.style.border = 'none';
                if (!iframe.getAttribute('allow')) {
                    iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
                }
            }
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
        
        // Setup mid-roll, loop, and end-roll ads for free users after video starts
        if (adsData.show_ads) {
            // Mid-roll ad (after 30 seconds)
            if (adsData.mid_roll) {
                const midRollHandler = function() {
                    if (video.currentTime >= 30 && !video.midRollAdShown) {
                        video.midRollAdShown = true;
                        let savedCurrentTime = video.currentTime;
                        video.pause();
                        showAd(adsData.mid_roll, function() {
                            video.currentTime = savedCurrentTime;
                            video.play().catch(e => console.error('Error resuming:', e));
                        });
                    }
                };
                video.addEventListener('timeupdate', midRollHandler);
            }
            
            // End-roll ad (when video ends)
            if (adsData.end_roll) {
                video.addEventListener('ended', function() {
                    showAd(adsData.end_roll, function() {});
                });
            }
            
            // Banner ad
            if (adsData.banner) {
                showBannerAd(adsData.banner);
            }
            
            // Popup ad (after 60 seconds)
            if (adsData.popup) {
                let popupShown = false;
                const popupHandler = function() {
                    if (!popupShown && video.currentTime >= 60) {
                        popupShown = true;
                        showPopupAd(adsData.popup);
                    }
                };
                video.addEventListener('timeupdate', popupHandler);
            }
        }
    }
}

// Wrapper function that checks for ads before loading
function loadSource(sourceIndex) {
    // Show intro ad first (plays to everyone)
    if (adsData.intro_ad && !introAdShown) {
        console.log('[TV Episode] Showing intro ad');
        introAdShown = true;
        showAd(adsData.intro_ad, function() {
            console.log('[TV Episode] Intro ad finished');
            // After intro ad, show pre-roll if free user
            if (adsData.show_ads && adsData.pre_roll) {
                console.log('[TV Episode] Showing pre-roll ad');
                showAd(adsData.pre_roll, function() {
                    console.log('[TV Episode] Pre-roll ad finished');
                    loadSourceActual(sourceIndex);
                });
            } else {
                loadSourceActual(sourceIndex);
            }
        });
        return;
    }
    
    // Show pre-roll ad for free users (if no intro ad)
    if (adsData.show_ads && adsData.pre_roll && !introAdShown) {
        console.log('[TV Episode] Showing pre-roll ad (no intro)');
        showAd(adsData.pre_roll, function() {
            loadSourceActual(sourceIndex);
        });
        return;
    }
    
    // No ads, load source directly
    console.log('[TV Episode] No ads, loading source directly');
    loadSourceActual(sourceIndex);
}

// Load initial source (based on URL parameter or default to 0)
loadSource(currentSourceIndex);

<?php else: ?>
// Video player controls (for movies)
const video = document.getElementById('videoPlayer');
if (video) {
    video.addEventListener('play', function() {
        // Track play event if needed
    });
}
<?php endif; ?>

<?php if ($type === 'movie' && $use_advanced_player): ?>
const movieId = <?php echo (int) $content['id']; ?>;
const MOVIE_VIEWER_API = <?php echo json_encode(apiUrl('movies/api/viewer_tracker.php')); ?>;

function getMovieViewerToken(id) {
    const storageKey = 'movie_viewer_token_' + id;
    let token = sessionStorage.getItem(storageKey);
    if (!token) {
        token = 'mv_' + id + '_' + Date.now() + '_' + Math.random().toString(36).slice(2, 12);
        sessionStorage.setItem(storageKey, token);
    }
    return token;
}
const movieViewerToken = getMovieViewerToken(movieId);

function handleBack() {
    window.location.href = <?php echo json_encode(getMovieDetailUrl($content, $conn)); ?>;
}

function toggleFullscreen() {
    if (typeof window.streamToggleFullscreen === 'function') {
        window.streamToggleFullscreen('player-container');
        return;
    }
    const playerContainer = document.getElementById('player-container');
    if (!playerContainer) return;
    const fs = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
    if (!fs) {
        const req = playerContainer.requestFullscreen || playerContainer.webkitRequestFullscreen || playerContainer.mozRequestFullScreen || playerContainer.msRequestFullscreen;
        if (req) req.call(playerContainer);
    } else {
        const exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
        if (exit) exit.call(document);
        if (typeof window.resetPlayerBrightness === 'function') window.resetPlayerBrightness();
    }
}

function updateMovieViewerDisplay(count) {
    const mobileText = document.getElementById('viewer-count-mobile-text');
    const desktopText = document.getElementById('viewer-count-desktop-text');
    const mobileEl = document.getElementById('viewer-count-mobile');
    const desktopEl = document.getElementById('viewer-count-desktop');
    if (mobileText) mobileText.textContent = count;
    if (desktopText) desktopText.textContent = count;
    if (mobileEl) mobileEl.style.display = 'flex';
    if (desktopEl) desktopEl.style.display = 'flex';
}

function updateMovieViewerCount() {
    fetch(MOVIE_VIEWER_API + '?action=get&movie_id=' + movieId)
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.success) {
                updateMovieViewerDisplay(data.viewers || 0);
            }
        })
        .catch(function(err) { console.error('[Movie] viewer count error:', err); });
}

function pingMovieViewer() {
    fetch(MOVIE_VIEWER_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=ping&movie_id=' + encodeURIComponent(movieId) + '&viewer_token=' + encodeURIComponent(movieViewerToken)
    })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data && data.success) {
                updateMovieViewerDisplay(data.viewers || 0);
            }
        })
        .catch(function(err) { console.error('[Movie] viewer ping error:', err); });
}

pingMovieViewer();
setInterval(pingMovieViewer, 15000);
setInterval(updateMovieViewerCount, 5000);

window.addEventListener('beforeunload', function() {
    const formData = new FormData();
    formData.append('action', 'leave');
    formData.append('movie_id', movieId);
    formData.append('viewer_token', movieViewerToken);
    if (navigator.sendBeacon) {
        navigator.sendBeacon(MOVIE_VIEWER_API, formData);
    }
});

<?php if ($isAndroidTV): ?>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Backspace' || e.key === 'Escape' || e.key === 'BrowserBack') {
        e.preventDefault();
        handleBack();
    }
});
<?php endif; ?>
<?php endif; ?>

<?php if ($type === 'tv_episode' && $episode_id > 0): ?>
// Episode Viewer Tracking and Fullscreen
const episodeId = <?php echo $episode_id; ?>;

// Helper function to get API URL
function getApiUrl(endpoint) {
    if (endpoint.includes('viewer_tracker')) {
        return <?php echo json_encode(apiUrl('shows/api/viewer_tracker.php')); ?>;
    }
    return <?php echo json_encode(apiUrl('api/')); ?> + endpoint.replace(/^\//, '');
}

// Handle back navigation
function handleBack() {
    const showSlug = '<?php echo !empty($tv_show_slug) ? htmlspecialchars($tv_show_slug, ENT_QUOTES) : ""; ?>';
    if (showSlug) {
        window.location.href = '<?php echo url('tv-show'); ?>/' + showSlug;
    } else {
        window.history.back();
    }
}

// Fullscreen functionality
function toggleFullscreen() {
    if (typeof window.streamToggleFullscreen === 'function') {
        window.streamToggleFullscreen('player-container');
        return;
    }
    const playerContainer = document.getElementById('player-container');
    if (!playerContainer) return;
    const fs = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
    if (!fs) {
        const req = playerContainer.requestFullscreen || playerContainer.webkitRequestFullscreen || playerContainer.mozRequestFullScreen || playerContainer.msRequestFullscreen;
        if (req) req.call(playerContainer);
    } else {
        const exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
        if (exit) exit.call(document);
        if (typeof window.resetPlayerBrightness === 'function') window.resetPlayerBrightness();
    }
}

// Real-time viewer tracking - Same as TV channels
function updateViewerCount() {
    const apiUrl = getApiUrl('viewer_tracker.php') + '?action=get&episode_id=' + episodeId;
    console.log('[Episode] Fetching viewer count from:', apiUrl);
    fetch(apiUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            console.log('[Episode] Viewer count response:', data);
            if (data.success && data.viewers !== undefined) {
                const count = parseInt(data.viewers) || 0;
                const mobileText = document.getElementById('viewer-count-mobile-text');
                const desktopText = document.getElementById('viewer-count-desktop-text');
                
                // Always show the viewer count element
                const mobileEl = document.getElementById('viewer-count-mobile');
                const desktopEl = document.getElementById('viewer-count-desktop');
                
                if (mobileEl) {
                    mobileEl.style.display = 'flex';
                    if (mobileText) mobileText.textContent = count.toLocaleString();
                }
                if (desktopEl) {
                    desktopEl.style.display = 'flex';
                    if (desktopText) desktopText.textContent = count.toLocaleString();
                }
            } else {
                console.warn('[Episode] Invalid response from viewer tracker:', data);
            }
        })
        .catch(error => {
            console.error('[Episode] Error updating viewer count:', error);
        });
}

// Ping server that user is watching - Same as TV channels (returns viewer count)
function pingViewer() {
    const formData = new FormData();
    formData.append('action', 'ping');
    formData.append('episode_id', episodeId);
    
    console.log('[Episode] Sending ping to viewer tracker for episode:', episodeId);
    
    fetch(getApiUrl('viewer_tracker.php'), {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(response => response.json())
    .then(data => {
        console.log('[Episode] Ping response:', data);
        if (data.success && data.viewers !== undefined) {
            // Update viewer count from ping response (same as TV channels)
            const count = parseInt(data.viewers) || 0;
            const mobileText = document.getElementById('viewer-count-mobile-text');
            const desktopText = document.getElementById('viewer-count-desktop-text');
            const mobileEl = document.getElementById('viewer-count-mobile');
            const desktopEl = document.getElementById('viewer-count-desktop');
            
            if (mobileEl) {
                mobileEl.style.display = 'flex';
                if (mobileText) mobileText.textContent = count.toLocaleString();
            }
            if (desktopEl) {
                desktopEl.style.display = 'flex';
                if (desktopText) desktopText.textContent = count.toLocaleString();
            }
        }
    })
    .catch(error => {
        console.error('[Episode] Error pinging viewer tracker:', error);
    });
}

// Start viewer tracking
if (episodeId > 0) {
    // Initial ping
    pingViewer();
    
    // Update viewer count every 5 seconds
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            pingViewer();
            updateViewerCount();
        }
    }, 5000);
    
    // Initial viewer count
    updateViewerCount();
    
    // Ping when page becomes visible
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            pingViewer();
            updateViewerCount();
        }
    });
    
    // Clean up on page unload
    window.addEventListener('beforeunload', function() {
        // Send leave signal (optional - cleanup happens server-side)
        navigator.sendBeacon(getApiUrl('viewer_tracker.php') + '?action=leave&episode_id=' + episodeId);
    });
}

// Android TV remote "Back" support
<?php if ($isAndroidTV): ?>
document.addEventListener('keydown', function(e) {
    if (e.key === 'Backspace' || e.key === 'Escape' || e.key === 'BrowserBack') {
        e.preventDefault();
        handleBack();
    }
});
<?php endif; ?>
<?php endif; ?>
</script>
<?php include __DIR__ . '/includes/player-fullscreen.js.php'; ?>
<?php include __DIR__ . '/includes/player-touch-gestures.js.php'; ?>
