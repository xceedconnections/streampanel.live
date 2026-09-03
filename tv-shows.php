<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';
require_once __DIR__ . '/includes/seo.php';

$conn = getDBConnection();
ensureTvShowsSchema($conn);

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
    <?php exit();
}

$category = $_GET['category'] ?? '';
$search = trim($_GET['search'] ?? '');
$year = isset($_GET['year']) ? (int) $_GET['year'] : 0;
$letter = isset($_GET['letter']) ? strtoupper(substr(trim($_GET['letter']), 0, 1)) : '';
if ($letter !== '' && $letter !== '#' && !preg_match('/^[A-Z]$/', $letter)) {
    $letter = '';
}
$page = max(1, isset($_GET['page']) ? (int) $_GET['page'] : 1);
$per_page = 12;

function tvShowsFilterUrl(array $overrides = []): string
{
    global $category, $search, $year, $letter, $page;

    $params = [];
    if ($category !== '') {
        $params['category'] = $category;
    }
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($year > 0) {
        $params['year'] = $year;
    }
    if ($letter !== '') {
        $params['letter'] = $letter;
    }
    if ($page > 1) {
        $params['page'] = $page;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '' || $value === 0) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    if (isset($params['page']) && (int) $params['page'] <= 1) {
        unset($params['page']);
    }

    $qs = http_build_query($params);
    return BASE_URL . '/tv-shows' . ($qs ? '?' . $qs : '');
}

$query = "SELECT t.*, c.name AS category_name, c.slug AS category_slug
          FROM tv_shows t
          LEFT JOIN categories c ON t.category_id = c.id
          WHERE (t.is_active = 1 OR t.is_active IS NULL)";
$params = [];
$types = '';

if ($category) {
    $query .= " AND c.slug = ?";
    $params[] = $category;
    $types .= 's';
}

if ($year > 0) {
    $query .= " AND t.release_year = ?";
    $params[] = $year;
    $types .= 'i';
}

if ($letter !== '') {
    if ($letter === '#') {
        $query .= " AND t.title REGEXP '^[^A-Za-z]'";
    } else {
        $query .= " AND t.title LIKE ?";
        $params[] = $letter . '%';
        $types .= 's';
    }
}

if ($search !== '') {
    $query .= " AND (t.title LIKE ? OR t.description LIKE ? OR c.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY t.featured DESC, t.created_at DESC";

$count_query = str_replace(
    "SELECT t.*, c.name AS category_name, c.slug AS category_slug",
    "SELECT COUNT(*) AS total",
    $query
);
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_shows = (int) $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_shows = (int) $conn->query($count_query)->fetch_assoc()['total'];
}

$use_pagination = $search === '';
if ($use_pagination) {
    $offset = ($page - 1) * $per_page;
    $query .= " LIMIT $per_page OFFSET $offset";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$tv_shows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = $conn->query("SELECT c.*, (
    SELECT COUNT(*) FROM tv_shows t
    WHERE t.category_id = c.id AND (t.is_active = 1 OR t.is_active IS NULL)
) AS show_count
FROM categories c
HAVING show_count > 0
ORDER BY c.name ASC")->fetch_all(MYSQLI_ASSOC);

$years = $conn->query(
    "SELECT t.release_year, COUNT(*) AS show_count
     FROM tv_shows t
     WHERE (t.is_active = 1 OR t.is_active IS NULL)
       AND t.release_year IS NOT NULL AND t.release_year > 0
     GROUP BY t.release_year
     ORDER BY t.release_year DESC"
)->fetch_all(MYSQLI_ASSOC);

$alphabet = array_merge(['#'], range('A', 'Z'));

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

seoApplyMeta(buildTvShowsListingSeoMeta($conn, [
    'category' => $active_category_name !== '' ? $active_category_name : $category,
    'search' => $search,
    'year' => $year,
]));

include 'includes/header.php';
?>

<style>
.page-container {
    background: #000;
    padding: 2rem 0;
}
.page-header {
    padding: 0 0 2rem;
}
.movies-page-inner {
    max-width: 1600px;
    margin: 0 auto;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
}
@media (min-width: 768px) {
    .movies-page-inner {
        padding-left: 3rem;
        padding-right: 3rem;
    }
}
.movies-section {
    padding: 0 0 2rem;
}
.movies-archive-layout {
    display: flex;
    gap: 1.5rem;
    align-items: flex-start;
    padding-bottom: 2rem;
}
.movies-main {
    flex: 1;
    min-width: 0;
}
.movies-sidebar-right {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex-shrink: 0;
    width: 34px;
    padding: 0.5rem 0.15rem;
    background: rgba(0, 0, 0, 0.88);
    border: 1px solid #2a2a2a;
    z-index: 40;
    scrollbar-width: thin;
    scrollbar-color: #333 transparent;
}
.movies-sidebar-right::-webkit-scrollbar {
    width: 4px;
}
.movies-sidebar-right::-webkit-scrollbar-thumb {
    background: #333;
    border-radius: 4px;
}
@media (max-width: 1023px) {
    .movies-sidebar-right {
        position: fixed;
        right: 0;
        top: 4.5rem;
        bottom: 4.5rem;
        overflow-y: auto;
        border-radius: 0.35rem 0 0 0.35rem;
        border-right: none;
    }
    .movies-main {
        padding-right: 2.25rem;
    }
}
@media (min-width: 1024px) {
    .movies-sidebar-right {
        position: sticky;
        top: 5.5rem;
        max-height: calc(100vh - 7rem);
        overflow-y: auto;
        border-radius: 0.35rem;
        width: 38px;
    }
}
.movies-sidebar-title {
    display: none;
}
.movies-alpha-list {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
}
.movies-alpha-list li {
    width: 100%;
    margin: 0;
}
.movies-alpha-link {
    display: block;
    width: 100%;
    text-align: center;
    padding: 0.12rem 0;
    font-size: 0.6875rem;
    font-weight: 500;
    color: #8b8b8b;
    text-decoration: none;
    line-height: 1.35;
    transition: color 0.15s;
}
.movies-alpha-link:hover {
    color: #fff;
    text-decoration: none;
}
.movies-alpha-link.active {
    color: #e50914;
    font-weight: 700;
}
.movies-alpha-clear {
    margin-top: 0.35rem;
    padding-top: 0.35rem;
    border-top: 1px solid #2a2a2a;
    font-size: 0.625rem !important;
}
.movies-filters-mobile {
    display: block;
    margin-bottom: 1rem;
}
@media (min-width: 1024px) {
    .movies-filters-mobile {
        display: none;
    }
}
.movies-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1rem;
}
.movies-year-filter {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.movies-year-filter label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
    white-space: nowrap;
}
.movies-years-select {
    width: 100%;
    min-width: 160px;
    background: #111;
    border: 1px solid #333;
    color: #fff;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
}
.movies-filters-mobile .movies-sidebar-title {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
    margin-bottom: 0.5rem;
}
.movies-active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1rem;
}
.movies-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #1f2937;
    border: 1px solid #374151;
    color: #e5e7eb;
    font-size: 0.8125rem;
    padding: 0.35rem 0.65rem;
    border-radius: 9999px;
    text-decoration: none;
}
.movies-filter-chip:hover {
    border-color: #e50914;
    color: #fff;
    text-decoration: none;
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
.movie-badge-featured {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: #e50914;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.4rem;
    border-radius: 0.2rem;
    text-transform: uppercase;
    z-index: 10;
}
.movie-card-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    right: 2.5rem;
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
    padding: 0.55rem;
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
</style>

<?php
function renderTvShowGridCard(array $show): void
{
    $show_url = !empty($show['slug'])
        ? BASE_URL . '/tv-show/' . rawurlencode($show['slug'])
        : BASE_URL . '/tv-show-detail?id=' . (int) $show['id'];
    $poster = moviePosterUrl($show);
    ?>
    <a href="<?php echo htmlspecialchars($show_url); ?>" class="movie-card-page">
        <?php renderMoviePosterBadges($show); ?>
        <?php if (!empty($show['featured'])): ?>
        <span class="movie-badge-featured">Featured</span>
        <?php endif; ?>
        <?php if (!empty($show['release_year'])): ?>
        <span class="movie-badge-year"><?php echo (int) $show['release_year']; ?></span>
        <?php endif; ?>
        <img src="<?php echo htmlspecialchars($poster); ?>"
             alt="<?php echo htmlspecialchars($show['title']); ?>"
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
            <h3><?php echo htmlspecialchars($show['title']); ?></h3>
            <div class="meta">
                <?php if (!empty($show['rating'])): ?>
                <span><i class="fas fa-star text-yellow-400"></i> <?php echo number_format((float) $show['rating'], 1); ?></span>
                <?php endif; ?>
                <?php if (!empty($show['category_name'])): ?>
                <span><?php echo htmlspecialchars($show['category_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php
}

function renderTvShowsPagination(int $totalShows, int $perPage, int $currentPage): void
{
    if ($totalShows <= $perPage) {
        return;
    }

    $totalPages = (int) ceil($totalShows / $perPage);
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    ?>
    <div class="flex justify-center items-center gap-2 mt-8 flex-wrap">
        <?php if ($currentPage > 1): ?>
        <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['page' => $currentPage - 1])); ?>"
           class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg">
            <i class="fas fa-chevron-left mr-2"></i>Previous
        </a>
        <?php endif; ?>

        <div class="flex gap-2 flex-wrap justify-center">
            <?php if ($startPage > 1): ?>
                <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['page' => 1])); ?>"
                   class="px-4 py-2 rounded-lg <?php echo $currentPage === 1 ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">1</a>
                <?php if ($startPage > 2): ?><span class="px-2 py-2 text-gray-400">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['page' => $i])); ?>"
               class="px-4 py-2 rounded-lg <?php echo $currentPage === $i ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($endPage < $totalPages): ?>
                <?php if ($endPage < $totalPages - 1): ?><span class="px-2 py-2 text-gray-400">...</span><?php endif; ?>
                <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['page' => $totalPages])); ?>"
                   class="px-4 py-2 rounded-lg <?php echo $currentPage === $totalPages ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                    <?php echo $totalPages; ?>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($currentPage < $totalPages): ?>
        <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['page' => $currentPage + 1])); ?>"
           class="bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded-lg">
            Next<i class="fas fa-chevron-right ml-2"></i>
        </a>
        <?php endif; ?>
    </div>
    <?php
}
?>

<div class="page-container animate-in fade-in">
    <?php
    $page_type = 'tv_shows';
    include 'includes/slider-display.php';
    ?>

    <div class="movies-page-inner">
    <div class="page-header">
        <h1 class="text-4xl font-bold mb-4">TV Shows</h1>

        <div class="search-filter-section mb-6">
            <form method="GET" class="mb-4" id="tv-shows-search-form">
                <div class="flex gap-2 relative" id="tv-shows-search-wrap">
                    <input type="text" name="search" id="tv-shows-search-input" placeholder="Search TV shows..."
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="flex-1 bg-gray-900 border border-gray-700 rounded-lg px-4 py-2 text-white focus:border-netflix-red focus:outline-none"
                           autocomplete="off">
                    <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <?php endif; ?>
                    <?php if ($year > 0): ?>
                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                    <?php endif; ?>
                    <?php if ($letter !== ''): ?>
                    <input type="hidden" name="letter" value="<?php echo htmlspecialchars($letter); ?>">
                    <?php endif; ?>
                    <button type="submit" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </form>

            <div class="category-filter-scroll-container">
                <button type="button" class="category-filter-arrow category-filter-arrow-left" onclick="scrollCategoryFilterLeft()" aria-label="Scroll categories left">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 18l-6-6 6-6"></path>
                    </svg>
                </button>
                <div class="category-filter-scroll-wrapper" id="categoryFilterScroll">
                    <div class="flex gap-3" style="width: max-content;">
                        <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['category' => null, 'page' => null])); ?>"
                           class="px-4 py-2 rounded-lg whitespace-nowrap <?php echo !$category ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                            All
                        </a>
                        <?php foreach ($categories as $cat): ?>
                        <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['category' => $cat['slug'], 'page' => null])); ?>"
                           class="px-4 py-2 rounded-lg whitespace-nowrap <?php echo $category === $cat['slug'] ? 'bg-netflix-red' : 'bg-gray-800 hover:bg-gray-700'; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button type="button" class="category-filter-arrow category-filter-arrow-right" onclick="scrollCategoryFilterRight()" aria-label="Scroll categories right">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div class="movies-archive-layout">
        <div class="movies-main">
            <div class="movies-filters-mobile">
                <label for="tv-year-select-mobile" class="movies-sidebar-title">Year</label>
                <select id="tv-year-select-mobile" class="movies-years-select" onchange="if(this.value) window.location.href=this.value;">
                    <option value="<?php echo htmlspecialchars(tvShowsFilterUrl(['year' => null, 'page' => null])); ?>" <?php echo $year <= 0 ? 'selected' : ''; ?>>All Years</option>
                    <?php foreach ($years as $yearRow): ?>
                    <option value="<?php echo htmlspecialchars(tvShowsFilterUrl(['year' => (int) $yearRow['release_year'], 'page' => null])); ?>"
                            <?php echo $year === (int) $yearRow['release_year'] ? 'selected' : ''; ?>>
                        <?php echo (int) $yearRow['release_year']; ?> (<?php echo (int) $yearRow['show_count']; ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="movies-toolbar">
                <h2 class="movies-section-title mb-0">
                    <i class="fas fa-tv text-netflix-red"></i>
                    <?php if ($search !== ''): ?>
                        Search Results
                    <?php elseif ($category !== ''): ?>
                        <?php echo htmlspecialchars($active_category_name); ?> TV Shows
                    <?php elseif ($year > 0): ?>
                        TV Shows from <?php echo $year; ?>
                    <?php elseif ($letter !== ''): ?>
                        TV Shows — <?php echo $letter === '#' ? '#' : $letter; ?>
                    <?php else: ?>
                        All TV Shows
                    <?php endif; ?>
                    <span class="text-gray-400 text-lg">(<?php echo $total_shows; ?>)</span>
                </h2>
                <div class="movies-year-filter hidden lg:flex">
                    <label for="tv-year-select">Year</label>
                    <select id="tv-year-select" class="movies-years-select" onchange="if(this.value) window.location.href=this.value;">
                        <option value="<?php echo htmlspecialchars(tvShowsFilterUrl(['year' => null, 'page' => null])); ?>" <?php echo $year <= 0 ? 'selected' : ''; ?>>All Years</option>
                        <?php foreach ($years as $yearRow): ?>
                        <option value="<?php echo htmlspecialchars(tvShowsFilterUrl(['year' => (int) $yearRow['release_year'], 'page' => null])); ?>"
                                <?php echo $year === (int) $yearRow['release_year'] ? 'selected' : ''; ?>>
                            <?php echo (int) $yearRow['release_year']; ?> (<?php echo (int) $yearRow['show_count']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($year > 0 || $letter !== '' || $category !== ''): ?>
            <div class="movies-active-filters">
                <?php if ($category !== ''): ?>
                <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['category' => null, 'page' => null])); ?>" class="movies-filter-chip">
                    <?php echo htmlspecialchars($active_category_name); ?> <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
                <?php if ($year > 0): ?>
                <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['year' => null, 'page' => null])); ?>" class="movies-filter-chip">
                    Year: <?php echo $year; ?> <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
                <?php if ($letter !== ''): ?>
                <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['letter' => null, 'page' => null])); ?>" class="movies-filter-chip">
                    Letter: <?php echo htmlspecialchars($letter); ?> <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($tv_shows)): ?>
            <div class="movie-grid">
                <?php foreach ($tv_shows as $show): ?>
                    <?php renderTvShowGridCard($show); ?>
                <?php endforeach; ?>
            </div>

            <?php if ($use_pagination): ?>
                <?php renderTvShowsPagination($total_shows, $per_page, $page); ?>
            <?php endif; ?>
            <?php else: ?>
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-tv text-6xl mb-4"></i>
                <p>No TV shows found<?php echo $search !== '' ? ' for your search.' : '.'; ?></p>
            </div>
            <?php endif; ?>
        </div>

        <aside class="movies-sidebar-right" aria-label="Browse TV shows A to Z">
            <ul class="movies-alpha-list">
                <?php foreach ($alphabet as $alpha): ?>
                <li>
                    <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['letter' => $alpha, 'page' => null])); ?>"
                       class="movies-alpha-link <?php echo $letter === $alpha ? 'active' : ''; ?>"
                       title="TV shows starting with <?php echo htmlspecialchars($alpha); ?>">
                        <?php echo htmlspecialchars($alpha); ?>
                    </a>
                </li>
                <?php endforeach; ?>
                <?php if ($letter !== ''): ?>
                <li>
                    <a href="<?php echo htmlspecialchars(tvShowsFilterUrl(['letter' => null, 'page' => null])); ?>"
                       class="movies-alpha-link movies-alpha-clear" title="Clear letter filter">
                        <i class="fas fa-times"></i>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </aside>
    </div>
    </div>
</div>

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
});
</script>

<?php include 'includes/footer.php'; ?>
