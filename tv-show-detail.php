<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$page_title = "TV Show Details";
$conn = getDBConnection();

// Check if TV Shows section is enabled
if (!isSectionEnabled($conn, 'tv_shows')) {
    include 'includes/header.php';
    ?>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-black via-gray-900 to-black py-20">
        <div class="text-center">
            <h1 class="text-4xl font-bold mb-4 text-red-500">Section is Under Maintenance</h1>
            <p class="text-xl text-gray-400 mb-8">The TV Shows section is currently unavailable. Please check back later.</p>
            <a href="/" class="bg-netflix-red px-6 py-3 rounded hover:bg-red-700 font-semibold">Go to Home</a>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php exit(); }

// TV Show detail page is always accessible without login (for SEO purposes)
// Login requirement only applies to the watch page (episodes)

// Get show ID from GET parameter - check both 'id' and 'slug' for flexibility
$show_id = 0;
$show_slug = '';

// Check GET parameters - handle both direct access and clean URL rewrites
if (isset($_GET['id'])) {
    $raw_id = $_GET['id'];
    if (!empty($raw_id)) {
        $show_id = intval($raw_id);
    }
} elseif (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $show_slug = trim($_GET['slug']);
}

if ($show_id <= 0 && empty($show_slug)) {
    header('Location: ' . BASE_URL . '/tv-shows');
    exit();
}

// Get TV show - check if it exists (match the same query logic as tv-shows.php listing page)
// Note: tv-shows.php doesn't filter by is_active, so we won't either to match behavior
$show = null;

if ($show_id > 0) {
    // Query by ID
    $show_stmt = $conn->prepare("SELECT t.*, c.name as category_name FROM tv_shows t LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
    if (!$show_stmt) {
        // Database error - redirect to listing page
        header('Location: ' . BASE_URL . '/tv-shows');
        exit();
    }
    
    $show_stmt->bind_param("i", $show_id);
    if (!$show_stmt->execute()) {
        // Query execution error - redirect to listing page
        header('Location: ' . BASE_URL . '/tv-shows');
        exit();
    }
    
    $show = $show_stmt->get_result()->fetch_assoc();
    $show_stmt->close();
    
    // Redirect to clean URL if show has a slug and was accessed via ID
    if ($show && !empty($show['slug']) && isset($_GET['id'])) {
        header('Location: ' . BASE_URL . '/tv-show/' . htmlspecialchars($show['slug']));
        exit();
    }
} elseif (!empty($show_slug)) {
    // Query by slug
    $show_stmt = $conn->prepare("SELECT t.*, c.name as category_name FROM tv_shows t LEFT JOIN categories c ON t.category_id = c.id WHERE t.slug = ?");
    if (!$show_stmt) {
        header('Location: ' . BASE_URL . '/tv-shows');
        exit();
    }
    
    $show_stmt->bind_param("s", $show_slug);
    if (!$show_stmt->execute()) {
        header('Location: ' . BASE_URL . '/tv-shows');
        exit();
    }
    
    $show = $show_stmt->get_result()->fetch_assoc();
    $show_stmt->close();
    
    // Update show_id for episode query
    if ($show) {
        $show_id = $show['id'];
    }
}

if (!$show) {
    // TV show not found - redirect to listing page
    header('Location: ' . BASE_URL . '/tv-shows');
    exit();
}

// Check if TV show is active (optional - you can remove this if you want to show inactive shows)
// Uncomment the following lines if you want to hide inactive shows:
/*
if (isset($show['is_active']) && $show['is_active'] == 0) {
    header('Location: tv-shows.php');
    exit();
}
*/

// Get episodes grouped by season
$episodes_query = $conn->prepare("SELECT * FROM tv_episodes WHERE tv_show_id = ? ORDER BY season_number, episode_number");
$episodes_query->bind_param("i", $show_id);
$episodes_query->execute();
$all_episodes = $episodes_query->get_result()->fetch_all(MYSQLI_ASSOC);

// Group episodes by season
$seasons = [];
foreach ($all_episodes as $episode) {
    $seasons[$episode['season_number']][] = $episode;
}

$page_title = $show['title'];

// Get enabled sections for navigation (needed for footer)
$enable_movies = isSectionEnabled($conn, 'movies');
$enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
$enable_live_tv = isSectionEnabled($conn, 'live_tv');

include 'includes/header.php';
?>

<style>
.tv-show-hero {
    background: linear-gradient(135deg, #1a1a1a 0%, #000 100%);
    padding: 3rem 0;
}
.tv-show-poster-container {
    max-width: 1280px;
    margin: 0 auto;
    padding: 0 1rem;
}
.tv-show-poster {
    width: 100%;
    aspect-ratio: 16/9;
    object-fit: contain;
    background: #000;
    border-radius: 0.5rem;
    box-shadow: 0 20px 60px rgba(0,0,0,0.8);
}
/* Hide any broken images */
img[src=""], img:not([src]), img[src*="undefined"], img[src*="null"] {
    display: none !important;
}
/* Hide broken image icons */
img::before {
    content: none !important;
}
.tv-show-info {
    padding: 2rem 0;
}
.episode-card {
    background: rgba(30, 30, 30, 0.8);
    border: 1px solid rgba(255,255,255,0.1);
    transition: all 0.3s ease;
}
.episode-card:hover {
    background: rgba(40, 40, 40, 0.9);
    border-color: #e50914;
    transform: translateX(5px);
}
.episode-thumbnail {
    aspect-ratio: 16/9;
    object-fit: contain;
    background: #000;
}
</style>

<div class="bg-black">
    <!-- Hero Section with Poster/Banner Only (No Thumbnail) -->
    <div class="tv-show-hero">
        <div class="tv-show-poster-container">
            <?php if (!empty($show['poster'])): ?>
            <img src="<?php echo htmlspecialchars(assetUrl($show['poster'] ?? '')); ?>" 
                 alt="<?php echo htmlspecialchars($show['title']); ?>"
                 class="tv-show-poster"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div style="display: none; aspect-ratio: 16/9; background: linear-gradient(135deg, #1a1a1a 0%, #000 100%); border-radius: 0.5rem; align-items: center; justify-content: center;">
                <i class="fas fa-image text-6xl text-gray-700"></i>
            </div>
            <?php else: ?>
            <div style="aspect-ratio: 16/9; background: linear-gradient(135deg, #1a1a1a 0%, #000 100%); border-radius: 0.5rem; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-image text-6xl text-gray-700"></i>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TV Show Info -->
    <div class="container mx-auto px-4 tv-show-info">
        <div class="max-w-6xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-6 mb-8">
                <div class="flex-1">
                    <h1 class="text-4xl md:text-5xl font-bold mb-4"><?php echo htmlspecialchars($show['title']); ?></h1>
                    <p class="text-lg text-gray-300 mb-6 max-w-3xl leading-relaxed"><?php echo htmlspecialchars($show['description'] ?? 'No description available.'); ?></p>
                    <div class="flex flex-wrap items-center gap-4 text-gray-300">
                        <?php if ($show['release_year']): ?>
                        <span class="text-lg">
                            <i class="fas fa-calendar mr-2"></i><?php echo $show['release_year']; ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($show['category_name']): ?>
                        <span class="text-lg">
                            <i class="fas fa-tag mr-2"></i><?php echo htmlspecialchars($show['category_name']); ?>
                        </span>
                        <?php endif; ?>
                        <span class="text-lg">
                            <i class="fas fa-tv mr-2"></i><?php echo count($seasons); ?> Season<?php echo count($seasons) > 1 ? 's' : ''; ?>
                        </span>
                        <?php 
                        $total_episodes = count($all_episodes);
                        if ($total_episodes > 0): 
                        ?>
                        <span class="text-lg">
                            <i class="fas fa-play-circle mr-2"></i><?php echo $total_episodes; ?> Episode<?php echo $total_episodes > 1 ? 's' : ''; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (isLoggedIn()): ?>
                <button id="favoriteBtn" onclick="toggleFavorite()" 
                        class="bg-gray-800 hover:bg-gray-700 px-6 py-3 rounded-lg flex items-center gap-2 transition font-semibold">
                    <i class="fas fa-heart" id="favoriteIcon"></i>
                    <span id="favoriteText">Add to Favorites</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Episodes by Season -->
    <div class="container mx-auto px-4 pb-12">
        <div class="max-w-6xl mx-auto">
            <?php if (empty($seasons)): ?>
            <div class="text-center py-20">
                <i class="fas fa-tv text-6xl text-gray-700 mb-4"></i>
                <h2 class="text-3xl font-bold mb-4 text-gray-300">No Episodes Available</h2>
                <p class="text-gray-500 text-lg">Episodes for this TV show will be added soon.</p>
            </div>
            <?php else: ?>
            <?php foreach ($seasons as $season_num => $episodes): ?>
            <div class="mb-12">
                <div class="flex items-center gap-4 mb-6">
                    <h2 class="text-3xl font-bold">Season <?php echo $season_num; ?></h2>
                    <span class="text-gray-500 text-lg"><?php echo count($episodes); ?> Episode<?php echo count($episodes) > 1 ? 's' : ''; ?></span>
                </div>
                <div class="space-y-3">
                    <?php foreach ($episodes as $episode): ?>
                    <?php 
                    $ep_sources = [];
                    if (!empty($episode['sources'])) {
                        $ep_sources = json_decode($episode['sources'], true) ?? [];
                    }
                    $has_sources = !empty($ep_sources) && count($ep_sources) > 0;
                    ?>
                    <?php
                    // Generate clean URL for episode
                    $episode_url = '#';
                    if ($has_sources && !empty($show['slug'])) {
                        // Format: s{season}e{episode} (e.g., s01e01, s2e5)
                        $season_padded = str_pad($episode['season_number'], 2, '0', STR_PAD_LEFT);
                        $episode_padded = str_pad($episode['episode_number'], 2, '0', STR_PAD_LEFT);
                        $episode_slug = 's' . $season_padded . 'e' . $episode_padded;
                        $episode_url = BASE_URL . '/watch-tv-show/' . htmlspecialchars($show['slug']) . '/' . $episode_slug;
                    } elseif ($has_sources) {
                        // Fallback to ID-based URL if slug is not available
                        $episode_url = BASE_URL . '/watch.php?type=tv_episode&id=' . $episode['id'];
                    }
                    ?>
                    <a href="<?php echo $episode_url; ?>" 
                       class="block episode-card rounded-lg overflow-hidden group <?php echo !$has_sources ? 'opacity-60 cursor-not-allowed' : ''; ?>"
                       <?php if (!$has_sources): ?>onclick="event.preventDefault(); alert('This episode has no streaming sources available.');"<?php endif; ?>>
                        <div class="flex flex-col md:flex-row">
                            <div class="relative w-full md:w-80 flex-shrink-0 bg-black">
                                <img src="<?php echo htmlspecialchars(assetUrl($episode['thumbnail'] ?? $show['poster'] ?? '') ?: 'https://via.placeholder.com/1280x720'); ?>" 
                                     alt="<?php echo htmlspecialchars($episode['title']); ?>" 
                                     class="episode-thumbnail w-full">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition flex items-center justify-center">
                                    <?php if ($has_sources): ?>
                                    <i class="fas fa-play text-4xl text-white opacity-0 group-hover:opacity-100 transition"></i>
                                    <?php else: ?>
                                    <i class="fas fa-exclamation-triangle text-2xl text-yellow-400 opacity-0 group-hover:opacity-100 transition"></i>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$has_sources): ?>
                                <div class="absolute top-2 right-2 bg-red-600 text-white text-xs px-2 py-1 rounded">
                                    No Sources
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1 p-6">
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <h3 class="text-xl md:text-2xl font-semibold mb-2">
                                            Episode <?php echo $episode['episode_number']; ?>: <?php echo htmlspecialchars($episode['title']); ?>
                                        </h3>
                                        <?php if (!empty($episode['description'])): ?>
                                        <p class="text-gray-400 mb-3 leading-relaxed"><?php echo htmlspecialchars($episode['description']); ?></p>
                                        <?php endif; ?>
                                        <div class="flex flex-wrap items-center gap-4 text-sm text-gray-500">
                                            <?php if ($episode['duration']): ?>
                                            <span><i class="fas fa-clock mr-1"></i><?php echo $episode['duration']; ?> min</span>
                                            <?php endif; ?>
                                            <?php if ($has_sources): ?>
                                            <span class="text-green-400">
                                                <i class="fas fa-check-circle mr-1"></i><?php echo count($ep_sources); ?> Source<?php echo count($ep_sources) > 1 ? 's' : ''; ?> Available
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Hide any broken or empty images (especially thumbnails)
document.addEventListener('DOMContentLoaded', function() {
    // Hide images with empty, undefined, or null src
    const allImages = document.querySelectorAll('img');
    allImages.forEach(function(img) {
        const src = img.getAttribute('src') || '';
        if (!src || src === '' || src.includes('undefined') || src.includes('null') || src.trim() === '') {
            img.style.display = 'none';
        }
        // Hide broken images
        img.onerror = function() {
            this.style.display = 'none';
        };
    });
    
    // Remove any hidden img tags that might be causing issues
    const hiddenImages = document.querySelectorAll('img[style*="display: none"], img[style*="display:none"]');
    hiddenImages.forEach(function(img) {
        if (!img.classList.contains('tv-show-poster') && !img.classList.contains('episode-thumbnail')) {
            img.remove();
        }
    });
});
</script>

<?php if (isLoggedIn()): ?>
<script>
const contentType = 'tv_show';
const contentId = <?php echo $show['id']; ?>;

async function checkFavorite() {
    try {
        const response = await fetch(`<?php echo apiUrl('api/favorites.php'); ?>?content_type=${contentType}&content_id=${contentId}`);
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
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>