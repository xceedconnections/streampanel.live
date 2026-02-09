<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

// SEO meta data for Live TV page
$page_title = "Watch Live TV Online Free - HD Streaming Channels | {$site_name}";
$meta_description = "Watch live TV online free - Stream thousands of HD live TV channels including sports, news, entertainment, movies, and more. Free online live streaming with no registration required.";
$meta_keywords = "live tv, watch live tv, online tv, online live streaming, free live tv, live tv channels, watch tv online, streaming tv, live streaming, hd live tv, online television, live tv free, streaming channels, watch live streaming, online tv channels, live tv online free, free streaming tv, live tv streaming, watch tv live, online live tv";

// Check if Live TV section is enabled
if (!isSectionEnabled($conn, 'live_tv')) {
    include 'includes/header.php';
    ?>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-black via-gray-900 to-black py-20">
        <div class="text-center">
            <h1 class="text-4xl font-bold mb-4 text-red-500">Section is Under Maintenance</h1>
            <p class="text-xl text-gray-400 mb-8">The Live TV section is currently unavailable. Please check back later.</p>
            <a href="/" class="bg-netflix-red px-6 py-3 rounded hover:bg-red-700 font-semibold">Go to Home</a>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php exit(); }

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$country_filter = $_GET['country'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // Channels per page when viewing a specific category

// Channels per row (for category view)
$channels_per_row = 6;

// Build query for live TV channels - only show channels with sources
$query = "SELECT * FROM live_tv_channels WHERE is_active = 1 
          AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
          AND (sources LIKE '%\"url\"%' OR stream_url IS NOT NULL AND stream_url != '')";
$params = [];
$types = '';

if ($search) {
    // Search by channel name, description, category, or country
    $query .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ? OR country LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ssss';
}

if ($category_filter) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
    $types .= 's';
}

if ($country_filter) {
    $query .= " AND country = ?";
    $params[] = $country_filter;
    $types .= 's';
}

$query .= " ORDER BY featured DESC, name ASC";

// Get total count for pagination (before adding LIMIT)
$count_query = str_replace("SELECT *", "SELECT COUNT(*) as total", $query);
$count_params = $params;
$count_types = $types;
if (!empty($count_params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($count_types, ...$count_params);
    $count_stmt->execute();
    $total_channels = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_channels = $conn->query($count_query)->fetch_assoc()['total'];
}

// If viewing a specific category, apply pagination
if ($category_filter && !$search) {
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT $per_page OFFSET $offset";
}

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $channels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $channels = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}

// Get unique categories and countries for filters
$all_categories = $conn->query("SELECT DISTINCT category FROM live_tv_channels WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetch_all(MYSQLI_ASSOC);
$all_countries = $conn->query("SELECT DISTINCT country FROM live_tv_channels WHERE country IS NOT NULL AND country != '' ORDER BY country ASC")->fetch_all(MYSQLI_ASSOC);

// Filter out channels with no valid sources
$channels = array_filter($channels, function($channel) {
    $sources = json_decode($channel['sources'] ?? '[]', true);
    $has_sources = false;
    
    if (is_array($sources) && !empty($sources)) {
        // Check if any source has a URL
        foreach ($sources as $source) {
            if (!empty($source['url'])) {
                $has_sources = true;
                break;
            }
        }
    }
    
    // Fallback to stream_url if no sources
    if (!$has_sources && !empty($channel['stream_url'])) {
        $has_sources = true;
    }
    
    return $has_sources;
});

// Group by category (only if not viewing a specific category)
$channels_by_category = [];
if (!$category_filter || $search) {
    foreach ($channels as $channel) {
        $cat = $channel['category'] ?? 'Other';
        if (!isset($channels_by_category[$cat])) {
            $channels_by_category[$cat] = [];
        }
        $channels_by_category[$cat][] = $channel;
    }
}

// Get total channels per category for "Show More" functionality (only channels with sources)
$category_counts = [];
foreach ($all_categories as $cat) {
    $count_query = "SELECT COUNT(*) as total FROM live_tv_channels 
                    WHERE is_active = 1 
                    AND category = ?
                    AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
                    AND (sources LIKE '%\"url\"%' OR stream_url IS NOT NULL AND stream_url != '')";
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param("s", $cat['category']);
    $count_stmt->execute();
    $category_counts[$cat['category']] = $count_stmt->get_result()->fetch_assoc()['total'];
}

include 'includes/header.php';
?>

<style>
.page-container {
    min-height: 100vh;
    background: #000;
    padding: 2rem 0;
}
.page-header {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .page-header {
        padding: 0 3rem 2rem;
    }
}
.live-tv-section {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .live-tv-section {
        padding: 0 3rem 2rem;
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
        grid-template-columns: repeat(6, 1fr);
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
    aspect-ratio: 16/9;
    background: linear-gradient(to bottom right, rgba(229,9,20,0.2), rgba(37,99,235,0.2));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.live-tv-channel-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 1rem;
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

/* Category Filter Scroll Container */
.category-filter-scroll-container {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 0.5rem 0;
}

.category-filter-scroll-wrapper {
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
    padding: 0.5rem 0;
    min-width: 0; /* Allow flex item to shrink */
}

.category-filter-scroll-wrapper::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

.category-filter-arrow {
    background: rgba(0, 0, 0, 0.8);
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: #fff;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    flex-shrink: 0;
    z-index: 10;
    margin: 0;
    padding: 0;
}

.category-filter-arrow:hover {
    background: rgba(229, 9, 20, 0.9);
    border-color: #e50914;
    transform: scale(1.1);
}

.category-filter-arrow:active {
    transform: scale(0.95);
}

.category-filter-arrow svg {
    width: 20px;
    height: 20px;
}

@media (max-width: 768px) {
    .category-filter-scroll-container {
        gap: 0.5rem;
    }
    .category-filter-arrow {
        width: 36px;
        height: 36px;
    }
    .category-filter-arrow svg {
        width: 18px;
        height: 18px;
    }
}
</style>

<div class="page-container animate-in fade-in">
    <!-- Sliders -->
    <?php
    $page_type = 'live_tv';
    include 'includes/slider-display.php';
    ?>
    
    <div class="page-header">
        <h1 class="text-4xl font-bold mb-4">Live TV</h1>
        
        <!-- Search and Filter -->
        <div class="search-filter-section mb-6">
            <form method="GET" class="mb-4">
                <div class="flex gap-2">
                    <input type="text" name="search" placeholder="Search by channel name, category..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-netflix-red focus:outline-none">
                    <button type="submit" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </form>
            <!-- Category Filter with Scroll Arrows -->
            <div class="category-filter-scroll-container">
                <button class="category-filter-arrow category-filter-arrow-left" onclick="scrollCategoryFilterLeft()" aria-label="Scroll categories left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="category-filter-scroll-wrapper" id="categoryFilterScroll" onmouseenter="startCategoryHoverScroll(this)" onmouseleave="stopCategoryHoverScroll(this)">
                    <div class="flex gap-3" style="width: max-content;">
                        <a href="<?php echo BASE_URL; ?>/live-tv" class="px-4 py-2 rounded-lg whitespace-nowrap <?php echo !$category_filter && !$country_filter ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                            All
                        </a>
                        <?php foreach ($all_categories as $cat): ?>
                        <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($cat['category']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $country_filter ? '&country=' . urlencode($country_filter) : ''; ?>" 
                           class="px-4 py-2 rounded-lg whitespace-nowrap <?php echo $category_filter == $cat['category'] ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button class="category-filter-arrow category-filter-arrow-right" onclick="scrollCategoryFilterRight()" aria-label="Scroll categories right">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    
    <?php if ($category_filter && !$search): ?>
    <!-- Single Category View with Pagination -->
    <div class="live-tv-section">
        <div class="flex items-center justify-between mb-4">
            <h2 class="live-tv-section-title">
                <i class="fas fa-broadcast-tower text-netflix-red"></i>
                <?php echo htmlspecialchars($category_filter); ?> Channels
                <span class="text-gray-400 text-lg">(<?php echo $total_channels; ?> channels)</span>
            </h2>
        </div>
        <?php if (!empty($channels)): ?>
        <div class="live-tv-channels-grid">
            <?php foreach ($channels as $channel): ?>
            <?php
                $channel_url = BASE_URL . (!empty($channel['slug']) ? '/tv/' . htmlspecialchars($channel['slug']) : '/tv/tv-channel.php?id=' . $channel['id']);
            ?>
            <a href="<?php echo htmlspecialchars($channel_url); ?>" 
               class="live-tv-channel-card <?php echo (($channel['is_premium'] ?? 0) == 1) ? 'premium' : 'free'; ?>" 
               onclick="return checkLoginAndPlay(event, '<?php echo htmlspecialchars($channel_url); ?>');">
                <div class="live-tv-channel-logo">
                    <?php if (!empty($channel['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($channel['logo']); ?>" 
                         alt="<?php echo htmlspecialchars($channel['name']); ?>"
                         onerror="this.style.display='none'">
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
                        <?php endif; ?>|
                        <?php if (!empty($channel['country'])): ?>
                        <span><?php echo htmlspecialchars($channel['country']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_channels > $per_page): ?>
        <div class="flex justify-center items-center gap-2 mt-8">
            <?php if ($page > 1): ?>
            <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($category_filter); ?>&page=<?php echo $page - 1; ?>" 
               class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg">
                <i class="fas fa-chevron-left mr-2"></i>Previous
            </a>
            <?php endif; ?>
            
            <div class="flex gap-2">
                <?php
                $total_pages = ceil($total_channels / $per_page);
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($category_filter); ?>&page=1" 
                       class="px-4 py-2 rounded-lg <?php echo $page == 1 ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">1</a>
                    <?php if ($start_page > 2): ?>
                    <span class="px-2 py-2 text-gray-400">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($category_filter); ?>&page=<?php echo $i; ?>" 
                   class="px-4 py-2 rounded-lg <?php echo $page == $i ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <span class="px-2 py-2 text-gray-400">...</span>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($category_filter); ?>&page=<?php echo $total_pages; ?>" 
                       class="px-4 py-2 rounded-lg <?php echo $page == $total_pages ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                        <?php echo $total_pages; ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($page < $total_pages): ?>
            <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($category_filter); ?>&page=<?php echo $page + 1; ?>" 
               class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg">
                Next<i class="fas fa-chevron-right ml-2"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="text-center py-12 text-gray-400">
            <i class="fas fa-tv text-6xl mb-4"></i>
            <p>No channels found in this category.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php elseif (!empty($channels_by_category)): ?>
        <?php foreach ($channels_by_category as $category => $category_channels): ?>
        <div class="live-tv-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="live-tv-section-title">
                    <i class="fas fa-broadcast-tower text-netflix-red"></i>
                    <?php echo htmlspecialchars($category); ?>
                    <span class="text-gray-400 text-lg">(<?php echo $category_counts[$category] ?? count($category_channels); ?> channels)</span>
                </h2>
                <?php if (($category_counts[$category] ?? count($category_channels)) > $channels_per_row): ?>
                <a href="<?php echo BASE_URL; ?>/live-tv?category=<?php echo urlencode($category); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="text-netflix-red hover:text-red-600 font-semibold">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="live-tv-channels-grid">
                <?php 
                // Show only first row (6 channels) per category
                $display_channels = array_slice($category_channels, 0, $channels_per_row);
                foreach ($display_channels as $channel): 
                ?>
                    <?php
                        $channel_url = BASE_URL . (!empty($channel['slug']) ? '/tv/' . htmlspecialchars($channel['slug']) : '/tv/tv-channel.php?id=' . $channel['id']);
                    ?>
                    <a href="<?php echo htmlspecialchars($channel_url); ?>" 
                       class="live-tv-channel-card <?php echo (($channel['is_premium'] ?? 0) == 1) ? 'premium' : 'free'; ?>" 
                       onclick="return checkLoginAndPlay(event, '<?php echo htmlspecialchars($channel_url); ?>');">
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
                                <?php endif; ?>|
                                <?php if (!empty($channel['country'])): ?>
                                <span><?php echo htmlspecialchars($channel['country']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
    <div class="text-center py-20 px-4">
        <p class="text-gray-400 text-xl">No live TV channels available</p>
    </div>
    <?php endif; ?>
</div>

<script>
function checkLoginAndPlay(event, url) {
    <?php if (isLoggedIn()): ?>
    // User is logged in, allow normal link navigation
    return true;
    <?php else: ?>
    // User is not logged in, prevent default and redirect to login
    if (event) {
        event.preventDefault();
    }
    window.location.href = '<?php echo BASE_URL; ?>/login?redirect=' + encodeURIComponent(url);
    return false;
    <?php endif; ?>
}

// Category filter scrolling functions
function scrollCategoryFilterLeft() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    if (scrollWrapper) {
        const scrollAmount = scrollWrapper.clientWidth * 0.6; // Scroll 60% of visible width
        scrollWrapper.scrollBy({
            left: -scrollAmount,
            behavior: 'smooth'
        });
        updateCategoryFilterArrows();
    }
}

function scrollCategoryFilterRight() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    if (scrollWrapper) {
        const scrollAmount = scrollWrapper.clientWidth * 0.6; // Scroll 60% of visible width
        scrollWrapper.scrollBy({
            left: scrollAmount,
            behavior: 'smooth'
        });
        updateCategoryFilterArrows();
    }
}

// Update arrow visibility based on scroll position
function updateCategoryFilterArrows() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    const leftArrow = document.querySelector('.category-filter-arrow-left');
    const rightArrow = document.querySelector('.category-filter-arrow-right');
    
    if (!scrollWrapper || !leftArrow || !rightArrow) return;
    
    const isAtStart = scrollWrapper.scrollLeft <= 10;
    const isAtEnd = scrollWrapper.scrollLeft >= scrollWrapper.scrollWidth - scrollWrapper.clientWidth - 10;
    
    leftArrow.style.opacity = isAtStart ? '0.3' : '1';
    rightArrow.style.opacity = isAtEnd ? '0.3' : '1';
    leftArrow.style.pointerEvents = isAtStart ? 'none' : 'all';
    rightArrow.style.pointerEvents = isAtEnd ? 'none' : 'all';
}

// Hover scroll functionality for category filter
let categoryHoverScrollInterval = null;

function startCategoryHoverScroll(scrollElement) {
    // Stop any existing scroll
    if (categoryHoverScrollInterval) {
        clearInterval(categoryHoverScrollInterval);
    }
    
    let scrollDirection = 0;
    let mouseX = 0;
    
    // Track mouse position
    scrollElement.addEventListener('mousemove', function(e) {
        const rect = scrollElement.getBoundingClientRect();
        const elementWidth = rect.width;
        const mousePosition = e.clientX - rect.left;
        
        // Determine scroll direction based on mouse position
        // Left 20% = scroll left, Right 20% = scroll right, Middle 60% = no scroll
        if (mousePosition < elementWidth * 0.2) {
            scrollDirection = -1; // Scroll left
        } else if (mousePosition > elementWidth * 0.8) {
            scrollDirection = 1; // Scroll right
        } else {
            scrollDirection = 0; // No scroll
        }
    });
    
    // Start auto-scrolling
    categoryHoverScrollInterval = setInterval(function() {
        if (scrollDirection !== 0 && scrollElement) {
            const scrollSpeed = 2; // Pixels per interval
            scrollElement.scrollLeft += scrollDirection * scrollSpeed;
            updateCategoryFilterArrows();
        }
    }, 16); // ~60fps
}

function stopCategoryHoverScroll() {
    if (categoryHoverScrollInterval) {
        clearInterval(categoryHoverScrollInterval);
        categoryHoverScrollInterval = null;
    }
}

// Initialize arrow visibility on page load and scroll
document.addEventListener('DOMContentLoaded', function() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    if (scrollWrapper) {
        // Initial check
        updateCategoryFilterArrows();
        
        // Update on scroll
        scrollWrapper.addEventListener('scroll', function() {
            updateCategoryFilterArrows();
        });
        
        // Update on window resize
        window.addEventListener('resize', function() {
            updateCategoryFilterArrows();
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>