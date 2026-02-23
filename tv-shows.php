<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$page_title = "TV Shows";
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

// TV Shows listing page is always accessible without login (for SEO purposes)
// Login requirement only applies to the watch page (episodes)

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT t.*, c.name as category_name FROM tv_shows t LEFT JOIN categories c ON t.category_id = c.id WHERE 1=1";
$params = [];
$types = '';

if ($category) {
    $query .= " AND c.slug = ?";
    $params[] = $category;
    $types .= 's';
}

if ($search) {
    // Search by title, description, or actor names (in description)
    $query .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tv_shows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories")->fetch_all(MYSQLI_ASSOC);

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
.search-filter-section {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .search-filter-section {
        padding: 0 3rem 2rem;
    }
}
.movie-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    padding: 0 1.5rem;
}
@media (min-width: 640px) {
    .movie-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 768px) {
    .movie-grid {
        grid-template-columns: repeat(3, 1fr);
        padding: 0 3rem;
    }
}
.movie-card-page {
    position: relative;
    aspect-ratio: 16/9;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
    background: #000;
}
.movie-card-page:hover {
    transform: scale(1.05);
    z-index: 20;
}
.movie-card-page img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}
.movie-card-overlay-page {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8), transparent, transparent);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: flex-end;
    padding: 0.75rem;
}
.movie-card-page:hover .movie-card-overlay-page {
    opacity: 1;
}
.movie-card-overlay-page p {
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    width: 100%;
    color: #fff;
}

.movie-card-title-page {
    margin-top: 0.35rem;
    font-size: 0.85rem;
    font-weight: 500;
    color: #e5e7eb;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.search-input {
    width: 100%;
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 0.25rem;
    padding: 0.75rem 1rem;
    color: #fff;
    font-size: 1rem;
}
.search-input:focus {
    outline: none;
    border-color: #e50914;
    background: rgba(255,255,255,0.15);
}
.filter-buttons {
    display: flex;
    gap: 0.5rem;
    overflow-x: auto;
    padding: 0.5rem 0;
}
.filter-btn {
    padding: 0.5rem 1rem;
    border-radius: 0.25rem;
    background: rgba(255,255,255,0.1);
    color: #fff;
    text-decoration: none;
    white-space: nowrap;
    transition: background 0.2s;
}
.filter-btn:hover,
.filter-btn.active {
    background: #e50914;
}
</style>

<div class="page-container animate-in fade-in">
    <!-- Sliders -->
    <?php
    $page_type = 'tv_shows';
    include 'includes/slider-display.php';
    ?>
    
    <div class="page-header">
        <h1 class="text-4xl font-bold mb-4">TV Shows</h1>
    </div>
    
    <!-- Search and Filter -->
    <div class="search-filter-section">
        <form method="GET" class="mb-4">
            <input type="text" name="search" placeholder="Search TV shows..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="search-input">
        </form>
        <div class="filter-buttons">
            <a href="<?php echo BASE_URL; ?>/tv-shows" class="filter-btn <?php echo !$category ? 'active' : ''; ?>">
                All
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?php echo BASE_URL; ?>/tv-shows?category=<?php echo $cat['slug']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-btn <?php echo $category == $cat['slug'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- TV Shows Grid -->
    <?php if (!empty($tv_shows)): ?>
    <div class="movie-grid">
        <?php foreach ($tv_shows as $show): ?>
        <?php
        // Generate clean URL using slug if available, fallback to ID
        $show_url = '';
        if (!empty($show['slug'])) {
            $show_url = BASE_URL . '/tv-show/' . htmlspecialchars($show['slug']);
        } else {
            $show_url = BASE_URL . '/tv-show-detail?id=' . $show['id'];
        }
        // No login redirect needed - listing page is always accessible
        ?>
        <a href="<?php echo $show_url; ?>" class="movie-card-page">
            <img src="<?php echo htmlspecialchars($show['poster'] ?? FALLBACK_POSTER); ?>" 
                 alt="<?php echo htmlspecialchars($show['title']); ?>" 
                 loading="lazy"
                 onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
            <div class="movie-card-overlay-page">
                <p><?php echo htmlspecialchars($show['title']); ?></p>
            </div>
        </a>
        <div class="movie-card-title-page">
            <?php echo htmlspecialchars($show['title']); ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-20 px-4">
        <p class="text-gray-400 text-xl">No TV shows found</p>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
