<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';

$page_title = "Movies";
$conn = getDBConnection();
ensureMoviesSchema($conn);

// Check if Movies section is enabled
if (!isSectionEnabled($conn, 'movies')) {
    include 'includes/header.php';
    ?>
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-black via-gray-900 to-black py-20">
        <div class="text-center">
            <h1 class="text-4xl font-bold mb-4 text-red-500">Section is Under Maintenance</h1>
            <p class="text-xl text-gray-400 mb-8">The Movies section is currently unavailable. Please check back later.</p>
            <a href="/" class="bg-netflix-red px-6 py-3 rounded hover:bg-red-700 font-semibold">Go to Home</a>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <?php exit(); }

// Movies listing is public; login is enforced per-movie on the detail/watch pages.
// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT m.*, c.name as category_name FROM movies m LEFT JOIN categories c ON m.category_id = c.id WHERE (m.is_active = 1 OR m.is_active IS NULL)";
$params = [];
$types = '';

if ($category) {
    $query .= " AND c.slug = ?";
    $params[] = $category;
    $types .= 's';
}

if ($search) {
    // Search by title, description, or actor names (in description)
    $query .= " AND (m.title LIKE ? OR m.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$query .= " ORDER BY m.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
    padding: 0 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}
@media (min-width: 640px) {
    .movie-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 0.875rem;
    }
}
@media (min-width: 768px) {
    .movie-grid {
        grid-template-columns: repeat(5, 1fr);
        padding: 0 3rem;
    }
}
@media (min-width: 1024px) {
    .movie-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
@media (min-width: 1280px) {
    .movie-grid {
        grid-template-columns: repeat(7, 1fr);
    }
}
.movie-card-page {
    position: relative;
    aspect-ratio: 2/3;
    border-radius: 0.375rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.3s;
    box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
    background: #1a1a1a;
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
.movie-badge-year {
    position: absolute;
    bottom: 2.5rem;
    right: 0.5rem;
    background: rgba(0,0,0,0.8);
    color: #fff;
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 0.2rem;
    z-index: 10;
}
.movie-card-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 0.5rem;
    background: linear-gradient(to top, rgba(0,0,0,0.95), transparent);
    z-index: 5;
}
.movie-card-info h3 {
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
}
.movie-card-info .meta {
    font-size: 0.65rem;
    color: #9ca3af;
    display: flex;
    gap: 0.5rem;
    margin-top: 0.15rem;
}
.movie-card-page:hover {
    transform: scale(1.04);
    z-index: 20;
}
.movie-card-page img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
.movie-card-page:hover .movie-card-play-overlay {
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
.movie-card-overlay-page {
    display: none;
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
    $page_type = 'movies';
    include 'includes/slider-display.php';
    ?>
    
    <div class="page-header">
        <h1 class="text-4xl font-bold mb-4">Movies</h1>
    </div>
    
    <!-- Search and Filter -->
    <div class="search-filter-section">
        <form method="GET" class="mb-4">
            <input type="text" name="search" placeholder="Search movies..." 
                   value="<?php echo htmlspecialchars($search); ?>"
                   class="search-input">
        </form>
        <div class="filter-buttons">
            <a href="<?php echo BASE_URL; ?>/movies" class="filter-btn <?php echo !$category ? 'active' : ''; ?>">
                All
            </a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo $cat['slug']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-btn <?php echo $category == $cat['slug'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Movies Grid -->
    <?php if (!empty($movies)): ?>
    <div class="movie-grid">
        <?php foreach ($movies as $movie): ?>
        <?php
        $detailUrl = getMovieDetailUrl($movie, $conn);
        $poster = moviePosterUrl($movie);
        ?>
        <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="movie-card-page">
            <?php renderMoviePosterBadges($movie); ?>
            <?php if (!empty($movie['release_year'])): ?>
            <span class="movie-badge-year"><?php echo (int) $movie['release_year']; ?></span>
            <?php endif; ?>
            <img src="<?php echo htmlspecialchars($poster); ?>" 
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
                    <?php if (!empty($movie['rating'])): ?>
                    <span><i class="fas fa-star text-yellow-400"></i> <?php echo number_format((float) $movie['rating'], 1); ?></span>
                    <?php endif; ?>
                    <?php $dur = formatMovieDuration((int) ($movie['duration'] ?? 0)); if ($dur): ?>
                    <span><?php echo $dur; ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center py-20 px-4">
        <p class="text-gray-400 text-xl">No movies found</p>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
