<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';
require_once __DIR__ . '/includes/cast_helpers.php';

$page_title = "Movies";
$conn = getDBConnection();
ensureMoviesSchema($conn);

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
    <?php exit();
}

$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 12;
$movies_per_row = 7;

$query = "SELECT m.*, c.name AS category_name, c.slug AS category_slug
          FROM movies m
          LEFT JOIN categories c ON m.category_id = c.id
          WHERE (m.is_active = 1 OR m.is_active IS NULL)";
$params = [];
$types = '';

if ($category) {
    $query .= " AND c.slug = ?";
    $params[] = $category;
    $types .= 's';
}

if ($search) {
    $query .= " AND (m.title LIKE ? OR m.description LIKE ? OR c.name LIKE ? OR LOWER(COALESCE(m.cast_data, '')) LIKE ?)";
    $search_param = "%$search%";
    $search_like = '%' . strtolower($search) . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_like;
    $types .= 'ssss';
}

$query .= " ORDER BY m.featured DESC, m.created_at DESC";

$count_query = str_replace("SELECT m.*, c.name AS category_name, c.slug AS category_slug", "SELECT COUNT(*) AS total", $query);
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_movies = (int) $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_movies = (int) $conn->query($count_query)->fetch_assoc()['total'];
}

if ($category && !$search) {
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT $per_page OFFSET $offset";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$search_actors = [];
if ($search) {
    $search_actors = searchActors($conn, $search, 24);
}

$categories = $conn->query("SELECT c.*, (
    SELECT COUNT(*) FROM movies m
    WHERE m.category_id = c.id AND (m.is_active = 1 OR m.is_active IS NULL)
) AS movie_count
FROM categories c
HAVING movie_count > 0
ORDER BY c.name ASC")->fetch_all(MYSQLI_ASSOC);

$category_counts = [];
foreach ($categories as $cat) {
    $category_counts[$cat['slug']] = (int) $cat['movie_count'];
}

$movies_by_category = [];
if (!$category || $search) {
    foreach ($movies as $movie) {
        $catName = $movie['category_name'] ?? 'Other';
        $catSlug = $movie['category_slug'] ?? 'other';
        if (!isset($movies_by_category[$catName])) {
            $movies_by_category[$catName] = [
                'slug' => $catSlug,
                'movies' => [],
            ];
        }
        $movies_by_category[$catName]['movies'][] = $movie;
    }
}

$active_category_name = '';
if ($category) {
    foreach ($categories as $cat) {
        if ($cat['slug'] === $category) {
            $active_category_name = $cat['name'];
            break;
        }
    }
    if ($active_category_name === '') {
        $active_category_name = ucfirst(str_replace('-', ' ', $category));
    }
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
.movies-section {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .movies-section {
        padding: 0 3rem 2rem;
    }
}
.movies-section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.movie-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
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
    border-radius: 0.5rem;
    overflow: hidden;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 8px 20px rgba(0,0,0,0.45);
    background: #141414;
    border: 2px solid transparent;
    display: block;
    text-decoration: none;
    color: inherit;
}
.movie-card-page:hover {
    transform: scale(1.04);
    z-index: 20;
    text-decoration: none;
    color: inherit;
    border-color: rgba(229, 9, 20, 0.35);
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
.movie-card-page img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.movie-card-play-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
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
    padding: 0.75rem;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
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
    scrollbar-width: none;
    -ms-overflow-style: none;
    padding: 0.5rem 0;
    min-width: 0;
}
.category-filter-scroll-wrapper::-webkit-scrollbar {
    display: none;
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
.category-filter-arrow svg {
    width: 20px;
    height: 20px;
}
.movies-actors-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .movies-actors-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (min-width: 768px) {
    .movies-actors-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
.actor-result-card {
    display: block;
    text-align: center;
    text-decoration: none;
    color: #fff;
    background: #141414;
    border-radius: 0.5rem;
    padding: 1rem 0.75rem;
    border: 2px solid transparent;
    transition: transform 0.2s, border-color 0.2s;
}
.actor-result-card:hover {
    transform: scale(1.03);
    border-color: rgba(229, 9, 20, 0.35);
    color: #fff;
    text-decoration: none;
}
.actor-result-photo {
    width: 88px;
    height: 88px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 0.75rem;
    background: #222;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    font-size: 2rem;
}
.actor-result-photo img {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}
.actor-result-name {
    font-size: 0.875rem;
    font-weight: 600;
}
.actor-result-label {
    font-size: 0.7rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}
</style>

<?php
function renderMovieGridCard(array $movie, mysqli $conn): void
{
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
                <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
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
    <?php
}
?>

<div class="page-container animate-in fade-in">
    <?php
    $page_type = 'movies';
    include 'includes/slider-display.php';
    ?>

    <div class="page-header">
        <h1 class="text-4xl font-bold mb-4">Movies</h1>

        <div class="search-filter-section mb-6">
            <form method="GET" class="mb-4" id="movies-search-form">
                <div class="flex gap-2 relative" id="movies-search-wrap">
                    <input type="text" name="search" id="movies-search-input" placeholder="Search movies, actors, actresses..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-netflix-red focus:outline-none"
                           autocomplete="off">
                    <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <?php endif; ?>
                    <button type="submit" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </form>

            <div class="category-filter-scroll-container">
                <button class="category-filter-arrow category-filter-arrow-left" onclick="scrollCategoryFilterLeft()" aria-label="Scroll categories left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="category-filter-scroll-wrapper" id="categoryFilterScroll">
                    <div class="flex gap-3" style="width: max-content;">
                        <a href="<?php echo BASE_URL; ?>/movies<?php echo $search ? '?search=' . urlencode($search) : ''; ?>"
                           class="px-4 py-2 rounded-lg whitespace-nowrap <?php echo !$category ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                            All
                        </a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($cat['slug']); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                           class="px-4 py-2 rounded-lg whitespace-nowrap <?php echo $category === $cat['slug'] ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
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

    <?php if ($category && !$search): ?>
    <div class="movies-section">
        <div class="flex items-center justify-between mb-4">
            <h2 class="movies-section-title">
                <i class="fas fa-film text-netflix-red"></i>
                <?php echo htmlspecialchars($active_category_name); ?> Movies
                <span class="text-gray-400 text-lg">(<?php echo $total_movies; ?> movies)</span>
            </h2>
        </div>
        <?php if (!empty($movies)): ?>
        <div class="movie-grid">
            <?php foreach ($movies as $movie): ?>
                <?php renderMovieGridCard($movie, $conn); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($total_movies > $per_page): ?>
        <div class="flex justify-center items-center gap-2 mt-8">
            <?php if ($page > 1): ?>
            <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($category); ?>&page=<?php echo $page - 1; ?>"
               class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg">
                <i class="fas fa-chevron-left mr-2"></i>Previous
            </a>
            <?php endif; ?>

            <div class="flex gap-2">
                <?php
                $total_pages = (int) ceil($total_movies / $per_page);
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                if ($start_page > 1): ?>
                    <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($category); ?>&page=1"
                       class="px-4 py-2 rounded-lg <?php echo $page === 1 ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">1</a>
                    <?php if ($start_page > 2): ?><span class="px-2 py-2 text-gray-400">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($category); ?>&page=<?php echo $i; ?>"
                   class="px-4 py-2 rounded-lg <?php echo $page === $i ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="px-2 py-2 text-gray-400">...</span><?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($category); ?>&page=<?php echo $total_pages; ?>"
                       class="px-4 py-2 rounded-lg <?php echo $page === $total_pages ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                        <?php echo $total_pages; ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($page < $total_pages): ?>
            <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($category); ?>&page=<?php echo $page + 1; ?>"
               class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg">
                Next<i class="fas fa-chevron-right ml-2"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="text-center py-12 text-gray-400">
            <i class="fas fa-film text-6xl mb-4"></i>
            <p>No movies found in this category.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($search && (!empty($movies) || !empty($search_actors))): ?>
    <div class="movies-section">
        <?php if (!empty($search_actors)): ?>
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="movies-section-title">
                    <i class="fas fa-user text-netflix-red"></i>
                    Actors & Actresses
                    <span class="text-gray-400 text-lg">(<?php echo count($search_actors); ?>)</span>
                </h2>
            </div>
            <div class="movies-actors-grid">
                <?php foreach ($search_actors as $actor): ?>
                <a href="<?php echo htmlspecialchars($actor['url']); ?>" class="actor-result-card">
                    <div class="actor-result-photo">
                        <?php if (!empty($actor['profile_url'])): ?>
                        <img src="<?php echo htmlspecialchars($actor['profile_url']); ?>"
                             alt="<?php echo htmlspecialchars($actor['name']); ?>"
                             onerror="this.parentElement.innerHTML='<i class=\'fas fa-user\'></i>'">
                        <?php else: ?>
                        <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="actor-result-name"><?php echo htmlspecialchars($actor['name']); ?></div>
                    <div class="actor-result-label">Actor / Actress</div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($movies)): ?>
        <div class="flex items-center justify-between mb-4">
            <h2 class="movies-section-title">
                <i class="fas fa-search text-netflix-red"></i>
                Movies
                <span class="text-gray-400 text-lg">(<?php echo count($movies); ?>)</span>
            </h2>
        </div>
        <div class="movie-grid">
            <?php foreach ($movies as $movie): ?>
                <?php renderMovieGridCard($movie, $conn); ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif (!empty($movies_by_category)): ?>
        <?php foreach ($movies_by_category as $categoryName => $categoryData): ?>
        <?php
        $categorySlug = $categoryData['slug'];
        $categoryMovies = $categoryData['movies'];
        $categoryTotal = $category_counts[$categorySlug] ?? count($categoryMovies);
        ?>
        <div class="movies-section">
            <div class="flex items-center justify-between mb-4">
                <h2 class="movies-section-title">
                    <i class="fas fa-film text-netflix-red"></i>
                    <?php echo htmlspecialchars($categoryName); ?>
                    <span class="text-gray-400 text-lg">(<?php echo $categoryTotal; ?> movies)</span>
                </h2>
                <?php if ($categoryTotal > $movies_per_row): ?>
                <a href="<?php echo BASE_URL; ?>/movies?category=<?php echo urlencode($categorySlug); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                   class="text-netflix-red hover:text-red-600 font-semibold">
                    View All <i class="fas fa-arrow-right ml-1"></i>
                </a>
                <?php endif; ?>
            </div>
            <div class="movie-grid">
                <?php foreach (array_slice($categoryMovies, 0, $movies_per_row) as $movie): ?>
                    <?php renderMovieGridCard($movie, $conn); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    <?php else: ?>
    <div class="text-center py-20 px-4">
        <i class="fas fa-film text-6xl mb-4 text-gray-600"></i>
        <p class="text-gray-400 text-xl">No movies found</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/search-suggest.js.php'; ?>

<script>
function scrollCategoryFilterLeft() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    if (scrollWrapper) {
        scrollWrapper.scrollBy({ left: -scrollWrapper.clientWidth * 0.6, behavior: 'smooth' });
        updateCategoryFilterArrows();
    }
}

function scrollCategoryFilterRight() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    if (scrollWrapper) {
        scrollWrapper.scrollBy({ left: scrollWrapper.clientWidth * 0.6, behavior: 'smooth' });
        updateCategoryFilterArrows();
    }
}

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

document.addEventListener('DOMContentLoaded', function() {
    const scrollWrapper = document.getElementById('categoryFilterScroll');
    if (scrollWrapper) {
        updateCategoryFilterArrows();
        scrollWrapper.addEventListener('scroll', updateCategoryFilterArrows);
        window.addEventListener('resize', updateCategoryFilterArrows);
    }

    const moviesSearchInput = document.getElementById('movies-search-input');
    if (moviesSearchInput && typeof initSearchSuggest === 'function') {
        initSearchSuggest(moviesSearchInput, {
            scope: 'movies',
            form: document.getElementById('movies-search-form'),
            wrapper: document.getElementById('movies-search-wrap')
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
