<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';
require_once __DIR__ . '/includes/cast_helpers.php';

$page_title = "Search Results";
$conn = getDBConnection();

// Check if login is required for TV channels
$login_required_tv_channels = '0'; // Default to '0' (login NOT required)
try {
    $setting_result = getSetting($conn, 'login_required_tv_channels', '0');
    if ($setting_result !== false && $setting_result !== null) {
        $login_required_tv_channels = $setting_result;
    } else {
        $direct_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_tv_channels' LIMIT 1");
        if ($direct_query && $direct_query->num_rows > 0) {
            $row = $direct_query->fetch_assoc();
            $login_required_tv_channels = $row['setting_value'] ?? '0';
        }
    }
} catch (Exception $e) {
    $login_required_tv_channels = '0';
}

// Check if login is actually required
$tv_login_required = false;
if (is_string($login_required_tv_channels)) {
    $login_required_tv_channels = trim($login_required_tv_channels);
    $tv_login_required = ($login_required_tv_channels === '1' || $login_required_tv_channels === 'true' || $login_required_tv_channels === 'yes');
} else {
    $tv_login_required = ($login_required_tv_channels === 1 || $login_required_tv_channels === true);
}

if (empty($login_required_tv_channels) || $login_required_tv_channels === '0' || $login_required_tv_channels === 0 || $login_required_tv_channels === false || $login_required_tv_channels === null) {
    $tv_login_required = false;
}

// Check if login is required for TV Shows
$login_required_tv_shows = '0'; // Default to '0' (login NOT required)
try {
    $setting_result = getSetting($conn, 'login_required_tv_shows', '0');
    if ($setting_result !== false && $setting_result !== null) {
        $login_required_tv_shows = $setting_result;
    } else {
        $direct_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_tv_shows' LIMIT 1");
        if ($direct_query && $direct_query->num_rows > 0) {
            $row = $direct_query->fetch_assoc();
            $login_required_tv_shows = $row['setting_value'] ?? '0';
        }
    }
} catch (Exception $e) {
    $login_required_tv_shows = '0';
}

// Check if login is actually required for TV Shows
$tv_shows_login_required = false;
if (is_string($login_required_tv_shows)) {
    $login_required_tv_shows = trim($login_required_tv_shows);
    $tv_shows_login_required = ($login_required_tv_shows === '1' || $login_required_tv_shows === 'true' || $login_required_tv_shows === 'yes');
} else {
    $tv_shows_login_required = ($login_required_tv_shows === 1 || $login_required_tv_shows === true);
}

if (empty($login_required_tv_shows) || $login_required_tv_shows === '0' || $login_required_tv_shows === 0 || $login_required_tv_shows === false || $login_required_tv_shows === null) {
    $tv_shows_login_required = false;
}

// Check if login is required for Movies
$login_required_movies = '0'; // Default to '0' (login NOT required)
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

// Check if login is actually required for Movies
$movies_login_required = false;
if (is_string($login_required_movies)) {
    $login_required_movies = trim($login_required_movies);
    $movies_login_required = ($login_required_movies === '1' || $login_required_movies === 'true' || $login_required_movies === 'yes');
} else {
    $movies_login_required = ($login_required_movies === 1 || $login_required_movies === true);
}

if (empty($login_required_movies) || $login_required_movies === '0' || $login_required_movies === 0 || $login_required_movies === false || $login_required_movies === null) {
    $movies_login_required = false;
}

$search_query = $_GET['q'] ?? '';
$type_filter = $_GET['type'] ?? '';
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';

$results = [
    'movies' => [],
    'actors' => [],
    'tv_shows' => [],
    'live_tv' => []
];

if (!empty($search_query)) {
    // Normalize search to lowercase so matching is case-insensitive regardless of column collation.
    // Use strtolower here because mbstring may not be enabled on all servers.
    $normalized_search = strtolower($search_query);
    $search_param = "%$normalized_search%";
    
    // Search Movies (title, description, cast)
    if (empty($type_filter) || $type_filter === 'movie') {
        $results['movies'] = searchMoviesWithCast($conn, $search_query, 50, $category_filter ?: null);
        $results['actors'] = searchActors($conn, $search_query, 30);
    }
    
    // Search TV Shows
    if (empty($type_filter) || $type_filter === 'tv-show') {
        $tv_query = "SELECT t.*, c.name as category_name, c.slug as category_slug 
                     FROM tv_shows t 
                     LEFT JOIN categories c ON t.category_id = c.id 
                     WHERE t.is_active = 1 AND (
                         LOWER(t.title) LIKE ? OR 
                         LOWER(t.description) LIKE ?
                     )";
        $tv_params = [$search_param, $search_param];
        $tv_types = 'ss';
        
        if ($category_filter) {
            $tv_query .= " AND c.slug = ?";
            $tv_params[] = $category_filter;
            $tv_types .= 's';
        }
        
        $tv_query .= " ORDER BY t.featured DESC, t.views DESC LIMIT 50";
        
        $stmt = $conn->prepare($tv_query);
        if (!empty($tv_params)) {
            $stmt->bind_param($tv_types, ...$tv_params);
        }
        $stmt->execute();
        $results['tv_shows'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Search Live TV Channels - only show channels with sources
    if (empty($type_filter) || $type_filter === 'live-tv') {
        $tv_channel_query = "SELECT * FROM live_tv_channels 
                             WHERE is_active = 1 
                             AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
                             AND (sources LIKE '%\"url\"%' OR stream_url IS NOT NULL AND stream_url != '')
                             AND (
                                 LOWER(name) LIKE ? OR 
                                 LOWER(description) LIKE ? OR
                                 LOWER(category) LIKE ?
                             )";
        $tv_channel_params = [$search_param, $search_param, $search_param];
        $tv_channel_types = 'sss';
        
        if ($category_filter) {
            $tv_channel_query .= " AND category = ?";
            $tv_channel_params[] = $category_filter;
            $tv_channel_types .= 's';
        }
        
        if ($country_filter) {
            $tv_channel_query .= " AND country = ?";
            $tv_channel_params[] = $country_filter;
            $tv_channel_types .= 's';
        }
        
        $tv_channel_query .= " ORDER BY featured DESC, views DESC LIMIT 50";
        
        $stmt = $conn->prepare($tv_channel_query);
        if (!empty($tv_channel_params)) {
            $stmt->bind_param($tv_channel_types, ...$tv_channel_params);
        }
        $stmt->execute();
        $tv_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Filter out channels with no valid sources
        $results['live_tv'] = array_filter($tv_results, function($channel) {
            return countActiveSources($channel) > 0;
        });
    }
}

// Filter results based on login requirements
// If login is required and user is not logged in, remove those results
if (!isLoggedIn()) {
    if ($movies_login_required) {
        $results['movies'] = [];
    }
    if ($tv_shows_login_required) {
        $results['tv_shows'] = [];
    }
    if ($tv_login_required) {
        $results['live_tv'] = [];
    }
}

$total_results = count($results['movies']) + count($results['actors']) + count($results['tv_shows']) + count($results['live_tv']);

include 'includes/header.php';
?>

<style>
.search-page {
    min-height: 100vh;
    background: #000;
    padding: 2rem 0;
}
.search-header {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .search-header {
        padding: 0 3rem 2rem;
    }
}
.search-results-section {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .search-results-section {
        padding: 0 3rem 2rem;
    }
}
.search-results-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (min-width: 640px) {
    .search-results-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 768px) {
    .search-results-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (min-width: 1024px) {
    .search-results-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
@media (min-width: 1280px) {
    .search-results-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
.search-result-card {
    position: relative;
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}
.search-result-card:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    z-index: 10;
}
.search-result-poster {
    width: 100%;
    padding-top: 150%;
    background: #1a1a1a;
    position: relative;
    overflow: hidden;
}
.search-result-poster .movie-card-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    right: 0.5rem;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
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
.movie-badge-quality { background: #e50914; color: #fff; }
.movie-badge-tag { background: rgba(0,0,0,0.75); color: #fbbf24; border: 1px solid rgba(251,191,36,0.4); }
.search-result-poster img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* TV show results: use 16:9 and contain so banner-style thumbnails are not cropped */
.search-result-poster-tv {
    padding-top: 56.25%;
}
.search-result-poster-tv img {
    object-fit: contain;
    background: #000;
}

/* TV show grid: make cards a bit wider for better readability */
.search-results-grid-tv {
    grid-template-columns: repeat(2, 1fr);
}
@media (min-width: 640px) {
    .search-results-grid-tv {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 1024px) {
    .search-results-grid-tv {
        grid-template-columns: repeat(4, 1fr);
    }
}
.search-result-info {
    padding: 0.75rem;
    background: linear-gradient(to top, rgba(0,0,0,0.9), transparent);
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
}
.search-result-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    margin-bottom: 0.25rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.search-result-meta {
    font-size: 0.75rem;
    color: #9ca3af;
}
.search-result-poster.actor {
    padding-top: 100%;
}
.search-result-poster.actor img {
    object-fit: cover;
    border-radius: 50%;
    width: 70%;
    height: 70%;
    top: 15%;
    left: 15%;
}
.search-section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Live TV channel cards (match live-tv.php theme + size) */
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

<div class="search-page animate-in fade-in">
    <div class="search-header">
        <h1 class="text-4xl font-bold mb-4">Search Results</h1>
        <?php if (!empty($search_query)): ?>
        <p class="text-gray-400">Found <?php echo $total_results; ?> result(s) for "<strong><?php echo htmlspecialchars($search_query); ?></strong>"</p>
        <?php else: ?>
        <p class="text-gray-400">Enter a search term to find movies, actors, TV shows, and live TV channels</p>
        <?php endif; ?>
    </div>
    
    <?php if (empty($search_query)): ?>
    <div class="search-results-section text-center py-20">
        <i class="fas fa-search text-6xl text-gray-600 mb-4"></i>
        <p class="text-xl text-gray-400">Start typing to search...</p>
    </div>
    <?php else: ?>
    
    <!-- Movies Results -->
    <?php if (!empty($results['movies'])): ?>
    <div class="search-results-section">
        <h2 class="search-section-title">
            <i class="fas fa-film text-netflix-red"></i>
            Movies (<?php echo count($results['movies']); ?>)
        </h2>
        <div class="search-results-grid">
            <?php foreach ($results['movies'] as $movie): ?>
            <?php $moviePlayUrl = resolveMovieWatchHref($movie, getMovieAccess($conn, $movie), 0, $conn); ?>
            <div class="search-result-card" onclick="window.location.href='<?php echo htmlspecialchars($moviePlayUrl, ENT_QUOTES); ?>'">
                <div class="search-result-poster">
                    <?php renderMoviePosterBadges($movie); ?>
                    <img src="<?php echo htmlspecialchars(moviePosterUrl($movie)); ?>" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>"
                         onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                    <div class="search-result-info">
                        <div class="search-result-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                        <div class="search-result-meta">
                            <?php if ($movie['release_year']): ?><?php echo $movie['release_year']; ?> � <?php endif; ?>
                            <?php if ($movie['category_name']): ?><?php echo htmlspecialchars($movie['category_name']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Actors Results -->
    <?php if (!empty($results['actors'])): ?>
    <div class="search-results-section">
        <h2 class="search-section-title">
            <i class="fas fa-user text-netflix-red"></i>
            Actors & Actresses (<?php echo count($results['actors']); ?>)
        </h2>
        <div class="search-results-grid">
            <?php foreach ($results['actors'] as $actor): ?>
            <a href="<?php echo htmlspecialchars($actor['url']); ?>" class="search-result-card">
                <div class="search-result-poster actor">
                    <?php if (!empty($actor['profile_url'])): ?>
                    <img src="<?php echo htmlspecialchars($actor['profile_url']); ?>"
                         alt="<?php echo htmlspecialchars($actor['name']); ?>"
                         onerror="this.style.display='none'">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-gray-500 absolute inset-0">
                        <i class="fas fa-user text-4xl"></i>
                    </div>
                    <?php endif; ?>
                    <div class="search-result-info">
                        <div class="search-result-title"><?php echo htmlspecialchars($actor['name']); ?></div>
                        <div class="search-result-meta">Actor / Actress</div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- TV Shows Results -->
    <?php if (!empty($results['tv_shows'])): ?>
    <div class="search-results-section">
        <h2 class="search-section-title">
            <i class="fas fa-tv text-netflix-red"></i>
            TV Shows (<?php echo count($results['tv_shows']); ?>)
        </h2>
        <div class="search-results-grid">
            <?php foreach ($results['tv_shows'] as $show): ?>
            <div class="search-result-card search-result-card-tv" onclick="checkLoginAndPlay('<?php echo BASE_URL; ?><?php echo !empty($show['slug']) ? '/tv-show/' . htmlspecialchars($show['slug']) : '/tv-show-detail?id=' . $show['id']; ?>')">
                <div class="search-result-poster search-result-poster-tv">
                    <img src="<?php echo htmlspecialchars(assetUrl($show['poster'] ?? $show['thumbnail'] ?? '') ?: FALLBACK_POSTER); ?>" 
                         alt="<?php echo htmlspecialchars($show['title']); ?>"
                         onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                    <div class="search-result-info">
                        <div class="search-result-title"><?php echo htmlspecialchars($show['title']); ?></div>
                        <div class="search-result-meta">
                            <?php if ($show['release_year']): ?><?php echo $show['release_year']; ?> � <?php endif; ?>
                            <?php if ($show['category_name']): ?><?php echo htmlspecialchars($show['category_name']); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Live TV Results -->
    <?php if (!empty($results['live_tv'])): ?>
    <div class="search-results-section">
        <h2 class="search-section-title">
            <i class="fas fa-broadcast-tower text-netflix-red"></i>
            Live TV Channels (<?php echo count($results['live_tv']); ?>)
        </h2>
        <div class="live-tv-channels-grid">
            <?php foreach ($results['live_tv'] as $channel): ?>
            <?php
                $channel_url = BASE_URL . (!empty($channel['slug'])
                    ? '/tv/' . htmlspecialchars($channel['slug'])
                    : '/tv/tv-channel.php?id=' . $channel['id']
                );
            ?>
            <a href="<?php echo $channel_url; ?>"
               class="live-tv-channel-card <?php echo (($channel['is_premium'] ?? 0) == 1) ? 'premium' : 'free'; ?>"
               onclick="checkLoginAndPlay(event, this.href)">
                <div class="live-tv-channel-logo">
                    <?php if (!empty($channel['logo'])): ?>
                        <img src="<?php echo htmlspecialchars(assetUrl($channel['logo'])); ?>"
                             alt="<?php echo htmlspecialchars($channel['name']); ?>"
                             onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-gray-500">
                            <i class="fas fa-tv text-4xl"></i>
                        </div>
                    <?php endif; ?>
                    <div class="live-tv-channel-overlay">
                        <div class="live-tv-channel-play-icon">
                            <i class="fas fa-play"></i>
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
    </div>
    <?php endif; ?>
    
    <?php if ($total_results === 0): ?>
    <div class="search-results-section text-center py-20">
        <i class="fas fa-search text-6xl text-gray-600 mb-4"></i>
        <p class="text-xl text-gray-400 mb-2">No results found</p>
        <p class="text-gray-500">Try different keywords or filters</p>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<script>
function checkLoginAndPlay(event, url) {
    if (event && event.preventDefault) event.preventDefault();
    <?php if (!isLoggedIn() && $tv_login_required): ?>
    window.location.href = '<?php echo BASE_URL; ?>/login?redirect=' + encodeURIComponent(url);
    <?php else: ?>
    window.location.href = url;
    <?php endif; ?>
}
</script>

<?php include 'includes/footer.php'; ?>