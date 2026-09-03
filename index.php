<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';
require_once __DIR__ . '/includes/movies_schema.php';
require_once __DIR__ . '/includes/seo.php';

$conn = getDBConnection();
ensureMoviesSchema($conn);
seoApplyMeta(buildHomeSeoMeta($conn));

// Avoid CDN/browser serving a stale homepage banner
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

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

// Featured = poster row. Homepage big banner comes from Admin > Sliders.
$featured_movies = [];
$all_movies = [];
$popular_movies = [];
$hero_slides = [];

if ($enable_movies) {
    $featured_movies = $conn->query(
        "SELECT * FROM movies
         WHERE COALESCE(featured, 0) = 1
           AND COALESCE(is_active, 1) = 1
         ORDER BY rating DESC, updated_at DESC, id DESC
         LIMIT 20"
    )->fetch_all(MYSQLI_ASSOC);

    $all_movies = $conn->query(
        "SELECT * FROM movies
         WHERE COALESCE(is_active, 1) = 1
         ORDER BY views DESC, created_at DESC
         LIMIT 30"
    )->fetch_all(MYSQLI_ASSOC);
    $popular_movies = array_filter($all_movies, function ($m) {
        return empty($m['featured']);
    });
    $popular_movies = array_slice($popular_movies, 0, 10);
}

// Homepage trending banner = Admin > Sliders with "Display on Home"
$hero_slides = getHomeHeroSlides($conn);

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
    /* clip avoids creating a containing block that breaks position:fixed footers */
    overflow-x: clip;
}

/* Hero Section - full-bleed cinematic banner */
.home-page .hero-carousel {
    position: relative;
    width: 100vw;
    max-width: 100vw;
    left: 50%;
    right: 50%;
    margin-left: -50vw;
    margin-right: -50vw;
    overflow: hidden;
    background: #000;
    min-height: 60vh;
}
@media (min-width: 768px) {
    .home-page .hero-carousel {
        min-height: 70vh;
    }
}
.hero-carousel-track {
    display: flex;
    height: 100%;
    min-height: inherit;
    transition: transform 0.65s cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
}
.home-page .hero-section {
    position: relative;
    flex: 0 0 100%;
    width: 100%;
    min-height: 60vh;
    display: flex;
    align-items: flex-end;
    padding: 0 1.25rem 3rem;
    overflow: hidden;
    margin-bottom: 0;
    background: #000;
}
@media (min-width: 768px) {
    .home-page .hero-section {
        min-height: 70vh;
        padding: 0 3rem 4rem;
    }
}
.home-page .hero-bg-image {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center 15%;
    z-index: 0;
}
.home-page .hero-section::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to right, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.35) 45%, rgba(0,0,0,0.2) 100%);
    z-index: 1;
}
.home-page .hero-section::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,1) 0%, rgba(0,0,0,0.65) 35%, transparent 70%);
    z-index: 2;
}
.home-page .hero-content {
    position: relative;
    z-index: 10;
    max-width: 42rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
@media (min-width: 640px) {
    .home-page .hero-content {
        gap: 1.25rem;
    }
}
.hero-carousel-nav {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 20;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 2px solid rgba(255,255,255,0.35);
    background: rgba(0,0,0,0.55);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s, transform 0.2s;
}
.hero-carousel-nav:hover {
    background: rgba(229,9,20,0.9);
    border-color: #e50914;
}
.hero-carousel-nav.prev {
    left: 1rem;
}
.hero-carousel-nav.next {
    right: 1rem;
}
@media (max-width: 640px) {
    .hero-carousel-nav {
        width: 36px;
        height: 36px;
    }
    .hero-carousel-nav.prev {
        left: 0.5rem;
    }
    .hero-carousel-nav.next {
        right: 0.5rem;
    }
}
.hero-carousel-dots {
    position: absolute;
    bottom: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 0.5rem;
    z-index: 15;
}
.hero-carousel-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255,255,255,0.45);
    border: none;
    cursor: pointer;
    padding: 0;
    transition: all 0.3s ease;
}
.hero-carousel-dot.active {
    background: #e50914;
    width: 28px;
    border-radius: 5px;
}
.hero-carousel-dot:hover {
    background: rgba(255,255,255,0.8);
}
.section-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0 1.5rem;
}
@media (min-width: 768px) {
    .section-header-row {
        padding: 0 3rem;
    }
}
.section-header-row .movie-row-title,
.section-header-row .live-tv-section-title,
.section-header-row .tv-shows-section-title {
    padding: 0;
    margin: 0;
}
.section-view-all {
    color: #e50914;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    white-space: nowrap;
    flex-shrink: 0;
    transition: color 0.2s;
}
@media (min-width: 768px) {
    .section-view-all {
        font-size: 1rem;
    }
}
.section-view-all:hover {
    color: #f40612;
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
    gap: 0.75rem;
    padding-top: 0.75rem;
    margin-top: 0.25rem;
}
@media (min-width: 640px) {
    .hero-actions {
        flex-direction: row;
        align-items: center;
        gap: 1rem;
        padding-top: 1rem;
        margin-top: 0.5rem;
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
    margin-top: -4rem;
    position: relative;
    z-index: 20;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding-bottom: 2.5rem;
}
.content-rows.has-hero-banner {
    margin-top: -3rem;
    padding-top: 1.5rem;
}
@media (min-width: 768px) {
    .content-rows {
        margin-top: -6rem;
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
    background: #1a1a1a;
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
.movie-card-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    right: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    z-index: 10;
    pointer-events: none;
}
.movie-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.4rem;
    border-radius: 0.2rem;
    text-transform: uppercase;
    line-height: 1.2;
}
.movie-badge-quality {
    background: #e50914;
    color: #fff;
}
.movie-badge-tag {
    background: rgba(0,0,0,0.75);
    color: #fbbf24;
    border: 1px solid rgba(251,191,36,0.4);
}
.movie-card-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0.5rem;
    background: linear-gradient(to top, rgba(0,0,0,0.95) 70%, transparent);
    z-index: 5;
    pointer-events: none;
}
.movie-card-info h3 {
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
    margin: 0;
}
.movie-card-info .meta {
    font-size: 0.65rem;
    color: #9ca3af;
    margin-top: 0.15rem;
}
.movie-card-play-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.55);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 8;
    pointer-events: none;
}
.movie-card:hover .movie-card-play-overlay {
    opacity: 1;
}
.movie-card-play-icon {
    background: #e50914;
    border-radius: 50%;
    padding: 0.65rem;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
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
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.live-tv-section .section-header-row {
    margin-bottom: 1rem;
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
    <!-- Hero Section — from Admin > Sliders (Display on Home) -->
    <?php if (!empty($hero_slides)): ?>
        <div class="hero-carousel" id="hero-carousel" data-slide-count="<?php echo count($hero_slides); ?>">
            <div class="hero-carousel-track" id="hero-carousel-track">
            <?php foreach ($hero_slides as $slideIndex => $heroSlide): ?>
                <?php
                $heroImage = htmlspecialchars(assetUrl($heroSlide['image'] ?? ''));
                $heroPlayUrl = $heroSlide['play_url'] ?? '#';
                $heroInfoUrl = $heroSlide['info_url'] ?? $heroPlayUrl;
                $heroPlayLabel = $heroSlide['play_label'] ?? 'Play';
                ?>
                <div class="hero-section hero-slide" data-hero-index="<?php echo $slideIndex; ?>">
                    <?php if ($heroImage !== ''): ?>
                    <img src="<?php echo $heroImage; ?>" alt="<?php echo htmlspecialchars($heroSlide['title'] ?? ''); ?>" class="hero-bg-image" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="hero-content">
                        <div class="hero-badge">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                            </svg>
                            <span>#<?php echo $slideIndex + 1; ?> Trending Today</span>
                        </div>
                        <h1 class="hero-title"><?php echo htmlspecialchars($heroSlide['title'] ?? ''); ?></h1>
                        <?php if (!empty($heroSlide['description'])): ?>
                        <p class="hero-description"><?php echo htmlspecialchars($heroSlide['description']); ?></p>
                        <?php endif; ?>
                        <div class="hero-actions">
                            <a href="<?php echo htmlspecialchars($heroPlayUrl); ?>" class="btn-play-hero">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="black" style="display: inline-block;">
                                    <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                </svg>
                                <?php echo htmlspecialchars($heroPlayLabel); ?>
                            </a>
                            <a href="<?php echo htmlspecialchars($heroInfoUrl); ?>" class="btn-info-hero">
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
            <?php endforeach; ?>
            </div>
            <?php if (count($hero_slides) > 1): ?>
                <button type="button" class="hero-carousel-nav prev" id="hero-carousel-prev" aria-label="Previous slide">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>
                <button type="button" class="hero-carousel-nav next" id="hero-carousel-next" aria-label="Next slide">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
                <div class="hero-carousel-dots" aria-label="Homepage slides">
                    <?php foreach ($hero_slides as $dotIndex => $_): ?>
                        <button type="button"
                                class="hero-carousel-dot<?php echo $dotIndex === 0 ? ' active' : ''; ?>"
                                data-hero-dot="<?php echo $dotIndex; ?>"
                                aria-label="Go to slide <?php echo $dotIndex + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Content Rows -->
    <div class="content-rows <?php if (empty($hero_slides)): ?>no-hero<?php else: ?>has-hero-banner<?php endif; ?>">
        <!-- Featured Movies (small posters) — from Featured checkbox -->
        <?php if (!empty($featured_movies)): ?>
            <div class="movie-row group/row">
                <div class="section-header-row">
                    <h2 class="movie-row-title">✨ Featured Movies</h2>
                    <a href="<?php echo BASE_URL; ?>/movies" class="section-view-all">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="movie-row-container">
                    <button class="movie-row-btn movie-row-btn-left" onclick="slideRow(this, 'left')">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <div class="movie-row-scroll" data-row="featured">
                        <?php foreach ($featured_movies as $movie): ?>
                            <?php
                            $movieAccess = getMovieAccess($conn, $movie);
                            $moviePlayUrl = resolveMovieWatchHref($movie, $movieAccess, 0, $conn);
                            ?>
                            <div class="movie-card" onclick="window.location.href='<?php echo htmlspecialchars($moviePlayUrl, ENT_QUOTES); ?>'">
                                <?php renderMoviePosterBadges($movie); ?>
                                <img src="<?php echo htmlspecialchars(moviePosterUrl($movie)); ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                                     loading="lazy"
                                     onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                                <div class="movie-card-play-overlay">
                                    <div class="movie-card-play-icon">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
                                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                        </svg>
                                    </div>
                                </div>
                                <div class="movie-card-info">
                                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                    <div class="meta">
                                        <?php if (!empty($movie['release_year'])): ?>
                                        <span><?php echo (int) $movie['release_year']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!$movieAccess['allowed'] && $movieAccess['reason'] === 'login'): ?>
                                        <span><i class="fas fa-lock"></i> Login</span>
                                        <?php elseif (!$movieAccess['allowed'] && $movieAccess['reason'] === 'premium'): ?>
                                        <span><i class="fas fa-crown"></i> Premium</span>
                                        <?php endif; ?>
                                    </div>
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
                            <?php
                            $movieAccess = getMovieAccess($conn, $movie);
                            $moviePlayUrl = resolveMovieWatchHref($movie, $movieAccess, 0, $conn);
                            ?>
                            <div class="movie-card" onclick="window.location.href='<?php echo htmlspecialchars($moviePlayUrl, ENT_QUOTES); ?>'">
                                <?php renderMoviePosterBadges($movie); ?>
                                <img src="<?php echo htmlspecialchars(moviePosterUrl($movie)); ?>" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>" 
                                     loading="lazy"
                                     onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                                <div class="movie-card-play-overlay">
                                    <div class="movie-card-play-icon">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
                                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                        </svg>
                                    </div>
                                </div>
                                <div class="movie-card-info">
                                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                                    <div class="meta">
                                        <?php if (!empty($movie['release_year'])): ?>
                                        <span><?php echo (int) $movie['release_year']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!$movieAccess['allowed'] && $movieAccess['reason'] === 'login'): ?>
                                        <span><i class="fas fa-lock"></i> Login</span>
                                        <?php elseif (!$movieAccess['allowed'] && $movieAccess['reason'] === 'premium'): ?>
                                        <span><i class="fas fa-crown"></i> Premium</span>
                                        <?php endif; ?>
                                    </div>
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
                            <img src="<?php echo htmlspecialchars(assetUrl($show['poster'] ?? '') ?: FALLBACK_POSTER); ?>" 
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
                <div class="section-header-row">
                    <h2 class="live-tv-section-title">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #e50914;">
                            <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                            <polyline points="17 2 12 7 7 2"></polyline>
                        </svg>
                        Live TV Channels
                    </h2>
                    <a href="<?php echo BASE_URL; ?>/live-tv" class="section-view-all">
                        View All Live TV Channels <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                <div class="live-tv-channels-grid">
                    <?php foreach (array_slice($live_tv_channels, 0, 12) as $channel): ?>
                        <?php
                            $channel_url = BASE_URL . (!empty($channel['slug']) ? '/tv/' . htmlspecialchars($channel['slug']) : '/tv/tv-channel.php?id=' . $channel['id']);
                        ?>
                        <a href="<?php echo htmlspecialchars($channel_url); ?>"
                           class="live-tv-channel-card <?php echo (($channel['is_premium'] ?? 0) == 1) ? 'premium' : 'free'; ?>">
                            <div class="live-tv-channel-logo">
                                <?php if (!empty($channel['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars(assetUrl($channel['logo'])); ?>" alt="<?php echo htmlspecialchars($channel['name']); ?>" onerror="this.style.display='none'">
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
function slideRow(btn, direction) {
    const container = btn.closest('.movie-row-container');
    const scroll = container.querySelector('.movie-row-scroll');
    const scrollAmount = scroll.clientWidth;
    const scrollTo = direction === 'left' ? scroll.scrollLeft - scrollAmount : scroll.scrollLeft + scrollAmount;
    scroll.scrollTo({ left: scrollTo, behavior: 'smooth' });
}

(function initHeroCarousel() {
    const carousel = document.getElementById('hero-carousel');
    const track = document.getElementById('hero-carousel-track');
    if (!carousel || !track) return;

    const slides = carousel.querySelectorAll('.hero-slide');
    const dots = carousel.querySelectorAll('.hero-carousel-dot');
    const prevBtn = document.getElementById('hero-carousel-prev');
    const nextBtn = document.getElementById('hero-carousel-next');
    if (slides.length <= 1) return;

    let currentIndex = 0;
    let timer = null;
    const intervalMs = 7000;

    function showSlide(index) {
        currentIndex = (index + slides.length) % slides.length;
        track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';
        dots.forEach((dot, i) => dot.classList.toggle('active', i === currentIndex));
    }

    function nextSlide() {
        showSlide(currentIndex + 1);
    }

    function prevSlide() {
        showSlide(currentIndex - 1);
    }

    function startAutoRotate() {
        clearInterval(timer);
        timer = setInterval(nextSlide, intervalMs);
    }

    dots.forEach((dot) => {
        dot.addEventListener('click', () => {
            showSlide(parseInt(dot.getAttribute('data-hero-dot'), 10));
            startAutoRotate();
        });
    });

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            prevSlide();
            startAutoRotate();
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            nextSlide();
            startAutoRotate();
        });
    }

    let touchStartX = 0;
    carousel.addEventListener('touchstart', (e) => {
        touchStartX = e.changedTouches[0].screenX;
    }, { passive: true });
    carousel.addEventListener('touchend', (e) => {
        const touchEndX = e.changedTouches[0].screenX;
        const diff = touchEndX - touchStartX;
        if (Math.abs(diff) > 50) {
            if (diff < 0) nextSlide();
            else prevSlide();
            startAutoRotate();
        }
    }, { passive: true });

    carousel.addEventListener('mouseenter', () => clearInterval(timer));
    carousel.addEventListener('mouseleave', startAutoRotate);

    showSlide(0);
    startAutoRotate();
})();
</script>

<?php include 'includes/footer.php'; ?>
