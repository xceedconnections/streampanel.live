<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$page_title = "Home";
$conn = getDBConnection();

// Check which sections are enabled
$enable_movies = isSectionEnabled($conn, 'movies');
$enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
$enable_live_tv = isSectionEnabled($conn, 'live_tv');

// Count enabled sections
$enabled_sections_count = 0;
if ($enable_movies) $enabled_sections_count++;
if ($enable_tv_shows) $enabled_sections_count++;
if ($enable_live_tv) $enabled_sections_count++;

// If only one section is enabled, redirect to that section's page
if ($enabled_sections_count === 1) {
    if ($enable_movies) {
        header('Location: ' . BASE_URL . '/movies');
        exit();
    } elseif ($enable_tv_shows) {
        header('Location: ' . BASE_URL . '/tv-shows');
        exit();
    } elseif ($enable_live_tv) {
        header('Location: ' . BASE_URL . '/live-tv');
        exit();
    }
}

// Check if login is required for Movies
$login_required_movies = '0';
$movies_login_required = false;
if ($enable_movies) {
    try {
        $setting_result = getSetting($conn, 'login_required_movies', '0');
        if ($setting_result !== false && $setting_result !== null) {
            $login_required_movies = $setting_result;
        } else {
            $direct_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_movies' LIMIT 1");
            if ($direct_query && $direct_query->num_rows > 0) {
                $row = $direct_query->fetch_assoc();
                $login_required_movies = $row['setting_value'] ?? '0';
            }
        }
    } catch (Exception $e) {
        $login_required_movies = '0';
    }
    
    if (is_string($login_required_movies)) {
        $login_required_movies = trim($login_required_movies);
        $movies_login_required = ($login_required_movies === '1' || $login_required_movies === 'true' || $login_required_movies === 'yes');
    } else {
        $movies_login_required = ($login_required_movies === 1 || $login_required_movies === true);
    }
    
    if (empty($login_required_movies) || $login_required_movies === '0' || $login_required_movies === 0 || $login_required_movies === false || $login_required_movies === null) {
        $movies_login_required = false;
    }
}

// Get featured movies (only if enabled and login not required OR user is logged in)
$featured_movies = [];
$all_movies = [];
$popular_movies = [];
$heroMovie = null;
$heroImage = '';

if ($enable_movies && (!$movies_login_required || isLoggedIn())) {
    $featured_movies = $conn->query("SELECT * FROM movies WHERE featured = 1 ORDER BY rating DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
    
    // Get all movies for popular section
    $all_movies = $conn->query("SELECT * FROM movies ORDER BY views DESC, created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);
    $popular_movies = array_filter($all_movies, function($m) {
        return !($m['featured'] ?? false);
    });
    $popular_movies = array_slice($popular_movies, 0, 10);
    
    // Hero: only show when a movie is marked as featured (no fallback to popular)
    $heroMovie = !empty($featured_movies) ? $featured_movies[0] : null;
    
    // Get hero image URL
    if ($heroMovie) {
        $heroImage = htmlspecialchars($heroMovie['poster'] ?? $heroMovie['thumbnail'] ?? '');
    }
}

// Get TV shows for index - featured first, max 6 (2 rows × 3), not in slider
$tv_shows_for_index = [];
if ($enable_tv_shows) {
    $tv_shows_for_index = $conn->query("SELECT * FROM tv_shows WHERE is_active = 1 ORDER BY featured DESC, created_at DESC LIMIT 6")->fetch_all(MYSQLI_ASSOC);
}

// Get live TV channels (only if enabled) - filter out channels without active sources
$live_tv_channels = [];
if ($enable_live_tv) {
    // First, get channels that have sources configured
    $all_channels = $conn->query("SELECT * FROM live_tv_channels 
                                   WHERE is_active = 1 
                                   AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
                                   AND sources LIKE '%\"url\"%'
                                   ORDER BY featured DESC, views DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
    
    // Filter channels to only include those with at least one active, visible source with valid URL
    foreach ($all_channels as $channel) {
        $active_source_count = countActiveSources($channel);
        if ($active_source_count > 0) {
            $live_tv_channels[] = $channel;
            // Limit to 12 channels for homepage
            if (count($live_tv_channels) >= 12) {
                break;
            }
        }
    }
}

include 'includes/header.php';
?>

<style>
/* Home Page Styles - Matching Netflix Theme */
.home-page {
    min-height: 100vh;
    background: #000;
    color: #fff;
}

/* Hero Section - Netflix Style */
.hero-section {
    position: relative;
    height: 70vh;
    width: 100%;
    display: flex;
    align-items: flex-end;
    padding: 0 1rem 4rem;
    overflow: hidden;
    margin-bottom: 2rem;
}
@media (min-width: 640px) {
    .hero-section {
        height: 85vh;
        padding: 0 3rem 5rem;
    }
}
@media (min-width: 768px) {
    .hero-section {
        padding: 0 3rem 8rem;
    }
}
.hero-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url('<?php echo $heroImage; ?>');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
}
.hero-section::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,1), rgba(0,0,0,0.4), transparent);
}
.hero-content {
    position: relative;
    z-index: 10;
    max-width: 42rem;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .hero-content {
        gap: 1rem;
    }
}
@media (min-width: 768px) {
    .hero-content {
        gap: 1rem;
    }
}
.hero-badge {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #e50914;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-size: 0.625rem;
}
@media (min-width: 640px) {
    .hero-badge {
        font-size: 0.75rem;
    }
}
.hero-badge svg {
    width: 12px;
    height: 12px;
    fill: currentColor;
}
@media (min-width: 640px) {
    .hero-badge svg {
        width: 14px;
        height: 14px;
    }
}
.hero-title {
    font-size: 1.5rem;
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1.1;
    color: #fff;
}
@media (min-width: 640px) {
    .hero-title {
        font-size: 2.25rem;
    }
}
@media (min-width: 768px) {
    .hero-title {
        font-size: 4.5rem;
    }
}
.hero-description {
    font-size: 0.75rem;
    color: #d1d5db;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
@media (min-width: 640px) {
    .hero-description {
        font-size: 0.875rem;
        -webkit-line-clamp: 3;
    }
}
@media (min-width: 768px) {
    .hero-description {
        font-size: 1.125rem;
    }
}
.hero-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding-top: 0.5rem;
}
@media (min-width: 640px) {
    .hero-actions {
        flex-direction: row;
        align-items: center;
        gap: 0.75rem;
        padding-top: 1rem;
    }
}
@media (min-width: 768px) {
    .hero-actions {
        padding-top: 1rem;
    }
}
.btn-play-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: #fff;
    color: #000;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.2s;
    font-size: 0.875rem;
}
@media (min-width: 640px) {
    .btn-play-hero {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
}
@media (min-width: 768px) {
    .btn-play-hero {
        padding: 0.75rem 2rem;
    }
}
.btn-play-hero:hover {
    background: rgba(255,255,255,0.9);
}
.btn-info-hero {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    background: rgba(107,114,128,0.4);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.2s;
    backdrop-filter: blur(12px);
    font-size: 0.875rem;
}
@media (min-width: 640px) {
    .btn-info-hero {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
}
@media (min-width: 768px) {
    .btn-info-hero {
        padding: 0.75rem 2rem;
    }
}
.btn-info-hero:hover {
    background: rgba(107,114,128,0.6);
}

/* Content Rows */
.content-rows {
    margin-top: -8rem;
    position: relative;
    z-index: 20;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding-bottom: 2.5rem;
}
@media (min-width: 768px) {
    .content-rows {
        margin-top: -8rem;
    }
}
/* When a slider is present above, avoid overlapping it */
.content-rows.has-slider {
    margin-top: 0;
}
/* When no hero section: add top padding so content is not hidden behind fixed navbar */
.content-rows.no-hero {
    margin-top: 0;
    padding-top: 6rem; /* Clear fixed header so first row (e.g. Live TV) is not overlayed */
}
@media (min-width: 768px) {
    .content-rows.no-hero {
        padding-top: 7rem;
    }
}

/* Movie Row - Netflix Style */
.movie-row {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 1rem 0;
}
.movie-row-title {
    padding: 0 1.5rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: #e5e7eb;
    transition: color 0.2s;
}
@media (min-width: 768px) {
    .movie-row-title {
        padding: 0 3rem;
        font-size: 1.25rem;
    }
}
.movie-row-title:hover {
    color: #fff;
}
.movie-row-container {
    position: relative;
}
.movie-row-scroll {
    display: flex;
    overflow-x: auto;
    gap: 0.75rem;
    padding: 0 1.5rem;
    scroll-behavior: smooth;
    -ms-overflow-style: none;
    scrollbar-width: none;
}
@media (min-width: 768px) {
    .movie-row-scroll {
        padding: 0 3rem;
    }
}
.movie-row-scroll::-webkit-scrollbar {
    display: none;
}
.movie-row-btn {
    position: absolute;
    top: 0;
    bottom: 0;
    z-index: 40;
    width: 3rem;
    background: rgba(0,0,0,0.4);
    opacity: 0;
    transition: opacity 0.2s, background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    cursor: pointer;
    color: #fff;
}
.movie-row-container:hover .movie-row-btn {
    opacity: 1;
}
.movie-row-btn:hover {
    background: rgba(0,0,0,0.6);
}
.movie-row-btn-left {
    left: 0;
}
.movie-row-btn-right {
    right: 0;
}
.movie-card {
    flex: none;
    width: 150px;
    aspect-ratio: 2/3;
    position: relative;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
    z-index: 10;
}
@media (min-width: 768px) {
    .movie-card {
        width: 210px;
    }
}
.movie-card:hover {
    transform: scale(1.05);
    z-index: 20;
}
.movie-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.movie-card-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent, transparent);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: flex-end;
    padding: 0.75rem;
}
.movie-card:hover .movie-card-overlay {
    opacity: 1;
}
.movie-card-overlay p {
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    color: #fff;
}

/* TV Show cards on home (use 16:9 and contain so YouTube-style banners are not cropped) */
.movie-row-tv .movie-card {
    aspect-ratio: 16/9;
    width: 190px;
}
@media (min-width: 768px) {
    .movie-row-tv .movie-card {
        width: 260px;
    }
}
.movie-row-tv .movie-card img {
    object-fit: contain;
    background: #000;
}
.movie-row-tv .tv-show-card-title {
    margin-top: 0.35rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: #e5e7eb;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* TV Shows Section on index - grid 3 per row, 2 rows (6 total), not slider */
.tv-shows-section {
    padding: 0 1.5rem;
}
@media (min-width: 768px) {
    .tv-shows-section {
        padding: 0 3rem;
    }
}
.tv-shows-section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.tv-shows-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
.tv-shows-grid .tv-show-card-wrap {
    position: relative;
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
    display: block;
    text-decoration: none;
    color: inherit;
}
.tv-shows-grid .tv-show-card-wrap:hover {
    text-decoration: none;
    color: inherit;
}
.tv-shows-grid .tv-show-poster {
    aspect-ratio: 16/9;
    width: 100%;
    object-fit: contain;
    background: #000;
    display: block;
}
.tv-shows-grid .tv-show-card-title {
    padding: 0.75rem;
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
}

/* Live TV Section */
.live-tv-section {
    padding: 0 1.5rem;
}
@media (min-width: 768px) {
    .live-tv-section {
        padding: 0 3rem;
    }
}
.live-tv-section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.live-tv-channels-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (min-width: 640px) {
    .live-tv-channels-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 768px) {
    .live-tv-channels-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (min-width: 1024px) {
    .live-tv-channels-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
@media (min-width: 1280px) {
    .live-tv-channels-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
.live-tv-channel-card {
    position: relative;
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
    display: block;
    text-decoration: none;
    color: inherit;
}
.live-tv-channel-card:hover {
    text-decoration: none;
    color: inherit;
}
.live-tv-channel-card:hover {
    transform: scale(1.05);
}
.live-tv-channel-logo {
    height: 110px;
    background: linear-gradient(to bottom right, rgba(229,9,20,0.2), rgba(37,99,235,0.2));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.live-tv-channel-logo img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    padding: 0.5rem;
}
.live-tv-channel-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.live-tv-channel-card:hover .live-tv-channel-overlay {
    opacity: 1;
}
.live-tv-channel-play-icon {
    background: #e50914;
    border-radius: 50%;
    padding: 0.75rem;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.live-tv-channel-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: #e50914;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    z-index: 10;
}
.live-tv-channel-card.premium {
    border: 2px solid rgba(251, 191, 36, 0.6);
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}
.live-tv-channel-card.premium:hover {
    border-color: rgba(251, 191, 36, 0.9);
    box-shadow: 0 6px 16px rgba(251, 191, 36, 0.4);
}
.live-tv-channel-card.free {
    border: 2px solid rgba(16, 185, 129, 0.4);
}
.live-tv-channel-card.free:hover {
    border-color: rgba(16, 185, 129, 0.6);
}
.live-tv-channel-info {
    padding: 0.75rem;
}
.live-tv-channel-info h3 {
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
}
.live-tv-channel-info p {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.live-tv-channel-meta {
    font-size: 0.7rem;
    color: #fff;
    margin-top: 0.25rem;
    opacity: 0.8;
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.live-tv-channel-meta span {
    white-space: nowrap;
}
.live-tv-channel-source-count {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.7rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}
.live-tv-channel-source-count svg {
    width: 14px;
    height: 14px;
    fill: currentColor;
}
</style>

<div class="home-page animate-in fade-in" style="animation: fadeIn 0.7s ease-out;">
    <!-- Hero Section -->
    <?php if ($heroMovie): ?>
        <div class="hero-section">
            <div class="hero-content">
                <div class="hero-badge">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                    </svg>
                    <span>#1 Trending Today</span>
                </div>
                <h1 class="hero-title"><?php echo htmlspecialchars($heroMovie['title']); ?></h1>
                <p class="hero-description"><?php echo htmlspecialchars($heroMovie['description'] ?? ''); ?></p>
                <div class="hero-actions">
                    <a href="<?php echo isLoggedIn() ? 'watch.php?type=movie&id=' . $heroMovie['id'] : 'login.php'; ?>" class="btn-play-hero">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="black" style="display: inline-block;">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                        <?php echo isLoggedIn() ? 'Play' : 'Sign In to Play'; ?>
                    </a>
                    <a href="/movies" class="btn-info-hero">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block;">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        More Info
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Sliders -->
    <?php
    $page_type = 'home';
    include 'includes/slider-display.php';
    ?>
    
    <!-- Content Rows (no-hero = no big hero banner, so add top padding so content is not hidden under fixed menu) -->
    <div class="content-rows <?php if (!$heroMovie): ?>no-hero<?php endif; ?> <?php if (!empty($GLOBALS['page_has_sliders'])): ?>has-slider<?php endif; ?>">
        <!-- Featured Movies -->
        <?php if (!empty($featured_movies)): ?>
            <div class="movie-row group/row">
                <h2 class="movie-row-title">✨ Featured Movies</h2>
                <div class="movie-row-container">
                    <button class="movie-row-btn movie-row-btn-left" onclick="slideRow(this, 'left')">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <div class="movie-row-scroll" data-row="featured">
                        <?php foreach ($featured_movies as $movie): ?>
                            <div class="movie-card" onclick="checkLoginAndPlay('watch.php?type=movie&id=<?php echo $movie['id']; ?>')">
                                <img src="<?php echo htmlspecialchars($movie['thumbnail'] ?? $movie['poster'] ?? FALLBACK_POSTER); ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                                     loading="lazy"
                                     onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                                <div class="movie-card-overlay">
                                    <p><?php echo htmlspecialchars($movie['title']); ?></p>
                                    <?php if (!isLoggedIn()): ?>
                                    <div class="mt-2 text-xs text-yellow-300">
                                        <i class="fas fa-lock mr-1"></i>Login to watch
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="movie-row-btn movie-row-btn-right" onclick="slideRow(this, 'right')">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Popular Movies -->
        <?php if (!empty($popular_movies)): ?>
            <div class="movie-row group/row">
                <h2 class="movie-row-title">🎬 Popular Movies</h2>
                <div class="movie-row-container">
                    <button class="movie-row-btn movie-row-btn-left" onclick="slideRow(this, 'left')">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <div class="movie-row-scroll" data-row="popular">
                        <?php foreach ($popular_movies as $movie): ?>
                            <div class="movie-card" onclick="checkLoginAndPlay('watch.php?type=movie&id=<?php echo $movie['id']; ?>')">
                                <img src="<?php echo htmlspecialchars($movie['thumbnail'] ?? $movie['poster'] ?? FALLBACK_POSTER); ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                                     loading="lazy"
                                     onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                                <div class="movie-card-overlay">
                                    <p><?php echo htmlspecialchars($movie['title']); ?></p>
                                    <?php if (!isLoggedIn()): ?>
                                    <div class="mt-2 text-xs text-yellow-300">
                                        <i class="fas fa-lock mr-1"></i>Login to watch
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button class="movie-row-btn movie-row-btn-right" onclick="slideRow(this, 'right')">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- TV Shows - grid 3 per row, 2 rows (6 max), not slider; featured first -->
        <?php if (!empty($tv_shows_for_index)): ?>
            <div class="tv-shows-section">
                <h2 class="tv-shows-section-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #e50914;">
                        <rect width="20" height="15" x="2" y="4" rx="2" ry="2"></rect>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                    </svg>
                    TV Shows
                </h2>
                <div class="tv-shows-grid">
                    <?php foreach ($tv_shows_for_index as $show): ?>
                        <?php
                        $show_url = !empty($show['slug']) ? BASE_URL . '/tv-show/' . htmlspecialchars($show['slug']) : BASE_URL . '/tv-show-detail?id=' . $show['id'];
                        ?>
                        <a href="<?php echo $show_url; ?>" class="tv-show-card-wrap">
                            <img src="<?php echo htmlspecialchars($show['poster'] ?? FALLBACK_POSTER); ?>" 
                                 alt="<?php echo htmlspecialchars($show['title']); ?>" 
                                 class="tv-show-poster"
                                 loading="lazy"
                                 onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                            <div class="tv-show-card-title">
                                <?php echo htmlspecialchars($show['title']); ?>
                                <?php if (!empty($show['featured'])): ?>
                                <span class="text-yellow-400 text-xs ml-1">⭐</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="<?php echo BASE_URL; ?>/tv-shows" style="color: #e50914; text-decoration: none; font-weight: 600;">
                        View More TV Shows →
                    </a>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Live TV Section -->
        <?php if (!empty($live_tv_channels)): ?>
            <div class="live-tv-section">
                <h2 class="live-tv-section-title">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #e50914;">
                        <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                        <polyline points="17 2 12 7 7 2"></polyline>
                    </svg>
                    Live TV Channels
                </h2>
                <div class="live-tv-channels-grid">
                    <?php foreach (array_slice($live_tv_channels, 0, 12) as $channel): ?>
                        <?php
                            $channel_url = BASE_URL . (!empty($channel['slug']) ? '/tv/' . htmlspecialchars($channel['slug']) : '/tv/tv-channel.php?id=' . $channel['id']);
                        ?>
                        <a href="<?php echo htmlspecialchars($channel_url); ?>"
                           class="live-tv-channel-card <?php echo (($channel['is_premium'] ?? 0) == 1) ? 'premium' : 'free'; ?>">
                            <div class="live-tv-channel-logo">
                                <?php if (!empty($channel['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars($channel['logo']); ?>" alt="<?php echo htmlspecialchars($channel['name']); ?>" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                                        <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                                        <polyline points="17 2 12 7 7 2"></polyline>
                                    </svg>
                                <?php endif; ?>
                                <div class="live-tv-channel-overlay">
                                    <div class="live-tv-channel-play-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                        </svg>
                                    </div>
                                </div>
                                <div class="live-tv-channel-badge">LIVE</div>
                            </div>
                            <div class="live-tv-channel-info">
                                <h3><?php echo htmlspecialchars($channel['name']); ?></h3>
                                <?php if (!empty($channel['description'])): ?>
                                    <p><?php echo htmlspecialchars($channel['description']); ?></p>
                                <?php endif; ?>
                                <div class="live-tv-channel-meta">
                                    <?php if (!empty($channel['category'])): ?>
                                    <span><?php echo htmlspecialchars($channel['category']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($channel['category']) && !empty($channel['country'])): ?>|<?php endif; ?>
                                    <?php if (!empty($channel['country'])): ?>
                                    <span><?php echo htmlspecialchars($channel['country']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php
                                $source_count = countActiveSources($channel);
                                if ($source_count > 0):
                                ?>
                                <div class="live-tv-channel-source-count">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                                        <polyline points="17 2 12 7 7 2"></polyline>
                                    </svg>
                                    <span><?php echo $source_count; ?> source<?php echo $source_count > 1 ? 's' : ''; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="<?php echo BASE_URL; ?>/live-tv" style="color: #e50914; text-decoration: none; font-weight: 600;">
                        View More TV Channels →
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function checkLoginAndPlay(url) {
    <?php if (isLoggedIn()): ?>
    window.location.href = url;
    <?php else: ?>
    window.location.href = 'login.php?redirect=' + encodeURIComponent(url);
    <?php endif; ?>
}

function slideRow(btn, direction) {
    const container = btn.closest('.movie-row-container');
    const scroll = container.querySelector('.movie-row-scroll');
    const scrollAmount = scroll.clientWidth;
    const scrollTo = direction === 'left' ? scroll.scrollLeft - scrollAmount : scroll.scrollLeft + scrollAmount;
    scroll.scrollTo({ left: scrollTo, behavior: 'smooth' });
}
</script>

<?php include 'includes/footer.php'; ?>
