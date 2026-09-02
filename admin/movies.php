<?php
/**
 * Admin Panel - Movies listing (same layout as live-tv tab).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/movie_admin.php';
require_once __DIR__ . '/../includes/movie_helpers.php';

$page_title = 'Manage Movies';
$conn = getDBConnection();
ensureMoviesSchema($conn);

$message = '';
$message_type = '';

// Redirect helpers for actions that may run after layout output started
$adminRedirect = static function (string $url): void {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    exit;
};

// Actions are primarily handled early in admin/index.php; keep JS fallback here
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare('DELETE FROM movies WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $adminRedirect('?tab=movies');
}

if (isset($_GET['slider_on'])) {
    $id = (int) $_GET['slider_on'];
    $conn->query('UPDATE movies SET show_in_slider = 1, is_active = 1 WHERE id = ' . $id);
    $adminRedirect('?tab=movies&banner=1');
}
if (isset($_GET['slider_off'])) {
    $id = (int) $_GET['slider_off'];
    $conn->query('UPDATE movies SET show_in_slider = 0 WHERE id = ' . $id);
    $adminRedirect('?tab=movies&banner=1');
}
if (isset($_GET['slider_clear_all'])) {
    $conn->query('UPDATE movies SET show_in_slider = 0');
    $adminRedirect('?tab=movies&banner=1');
}

if (isset($_GET['edit'])) {
    $adminRedirect('?tab=edit-movie&id=' . (int) $_GET['edit']);
}

if (isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $select_all_db = isset($_POST['select_all_database']) && $_POST['select_all_database'] === '1';

    if ($select_all_db) {
        $selected_ids = array_map('intval', array_column($conn->query('SELECT id FROM movies')->fetch_all(MYSQLI_ASSOC), 'id'));
    } elseif (!empty($_POST['selected_movies']) && is_array($_POST['selected_movies'])) {
        $selected_ids = array_map('intval', $_POST['selected_movies']);
    } else {
        $selected_ids = [];
    }

    if (empty($selected_ids)) {
        $message = 'Please select at least one movie';
        $message_type = 'error';
    } else {
        $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
        if ($action === 'delete') {
            $stmt = $conn->prepare("DELETE FROM movies WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt->execute();
            $message = count($selected_ids) . ' movie(s) deleted successfully';
        } elseif ($action === 'activate') {
            $stmt = $conn->prepare("UPDATE movies SET is_active = 1 WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt->execute();
            $message = count($selected_ids) . ' movie(s) activated successfully';
        } elseif ($action === 'deactivate') {
            $stmt = $conn->prepare("UPDATE movies SET is_active = 0 WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt->execute();
            $message = count($selected_ids) . ' movie(s) deactivated successfully';
        } elseif ($action === 'set_free') {
            $stmt = $conn->prepare("UPDATE movies SET is_free = 1, is_premium = 0 WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt->execute();
            $message = count($selected_ids) . ' movie(s) set as free successfully';
        } elseif ($action === 'set_premium') {
            $stmt = $conn->prepare("UPDATE movies SET is_free = 0, is_premium = 1 WHERE id IN ($placeholders)");
            $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
            $stmt->execute();
            $message = count($selected_ids) . ' movie(s) set as premium successfully';
        } else {
            $message = 'Invalid bulk action';
            $message_type = 'error';
        }
        if ($message_type !== 'error') {
            $message_type = 'success';
            header('Location: ?tab=movies');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_action'])) {
    $result = saveMovieFromRequest($conn, null);
    if ($result['success']) {
        header('Location: ?tab=edit-movie&id=' . (int) $result['id']);
        exit;
    }
    $message = $result['message'];
    $message_type = 'error';
}

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($current_page - 1) * $per_page;

$where_clause = 'WHERE 1=1';
$filter_params = [];
$filter_types = '';

if ($search !== '') {
    $where_clause .= ' AND (m.title LIKE ? OR m.description LIKE ?)';
    $search_param = '%' . $search . '%';
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
    $filter_types .= 'ss';
}
if ($category_filter > 0) {
    $where_clause .= ' AND m.category_id = ?';
    $filter_params[] = $category_filter;
    $filter_types .= 'i';
}

$count_query = "SELECT COUNT(*) as total FROM movies m $where_clause";
if (!empty($filter_params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($filter_types, ...$filter_params);
    $count_stmt->execute();
    $total_count = (int) $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_count = (int) $conn->query($count_query)->fetch_assoc()['total'];
}
$total_pages = max(1, (int) ceil($total_count / $per_page));

$query = "SELECT m.*, c.name as category_name FROM movies m LEFT JOIN categories c ON m.category_id = c.id $where_clause ORDER BY m.featured DESC, m.title ASC LIMIT ? OFFSET ?";
$params = $filter_params;
$types = $filter_types;
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$categories = getAllCategories($conn);
$edit_movie = null;
$movie_form_action = '?tab=movies';
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Movies</h1>
    <p class="text-gray-400">Add, edit, and manage movies with multiple streaming sources</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php
$banner_movies = $conn->query(
    "SELECT id, title, show_in_slider, featured, is_active
     FROM movies
     ORDER BY show_in_slider DESC, featured DESC, title ASC
     LIMIT 50"
)->fetch_all(MYSQLI_ASSOC);
$slider_now = array_values(array_filter($banner_movies, function ($m) {
    return (int) ($m['show_in_slider'] ?? 0) === 1 && (int) ($m['is_active'] ?? 0) === 1;
}));
?>
<div class="bg-gray-900 rounded-lg p-6 mb-8 border border-blue-800">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <div>
            <h2 class="text-2xl font-bold"><i class="fas fa-tv mr-2 text-blue-400"></i>Homepage Banner (Slider)</h2>
            <p class="text-gray-400 text-sm mt-1">These are the movies currently set for the big trending banner. This reads live from the database.</p>
        </div>
        <a href="?tab=movies&slider_clear_all=1" class="bg-red-700 hover:bg-red-600 px-4 py-2 rounded text-sm"
           onclick="return confirm('Remove ALL movies from the homepage banner?');">
            Clear all from banner
        </a>
    </div>

    <?php if (empty($slider_now)): ?>
        <div class="bg-yellow-900 bg-opacity-40 border border-yellow-700 text-yellow-100 px-4 py-3 rounded mb-4">
            No movies are in the homepage banner right now. Use <strong>Add to banner</strong> below, or edit a movie and check “Show in Homepage Slider”.
        </div>
    <?php else: ?>
        <ul class="space-y-2 mb-4">
            <?php foreach ($slider_now as $m): ?>
            <li class="flex items-center justify-between bg-gray-800 rounded px-4 py-3">
                <span class="font-semibold"><?php echo htmlspecialchars($m['title']); ?> <span class="text-xs text-gray-400">#<?php echo (int) $m['id']; ?></span></span>
                <a href="?tab=movies&slider_off=<?php echo (int) $m['id']; ?>" class="text-red-300 hover:text-red-200 text-sm">Remove from banner</a>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-700 text-left text-gray-400">
                    <th class="p-2">Movie</th>
                    <th class="p-2">Banner</th>
                    <th class="p-2">Featured</th>
                    <th class="p-2">Active</th>
                    <th class="p-2">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banner_movies as $m): ?>
                <tr class="border-b border-gray-800">
                    <td class="p-2"><?php echo htmlspecialchars($m['title']); ?></td>
                    <td class="p-2"><?php echo (int) $m['show_in_slider'] === 1 ? '<span class="text-blue-400">ON</span>' : '<span class="text-gray-500">off</span>'; ?></td>
                    <td class="p-2"><?php echo (int) $m['featured'] === 1 ? '<span class="text-yellow-400">ON</span>' : '<span class="text-gray-500">off</span>'; ?></td>
                    <td class="p-2"><?php echo (int) $m['is_active'] === 1 ? '<span class="text-green-400">yes</span>' : '<span class="text-red-400">no</span>'; ?></td>
                    <td class="p-2">
                        <?php if ((int) $m['show_in_slider'] === 1): ?>
                            <a class="text-red-300 hover:underline" href="?tab=movies&slider_off=<?php echo (int) $m['id']; ?>">Remove</a>
                        <?php else: ?>
                            <a class="text-blue-300 hover:underline" href="?tab=movies&slider_on=<?php echo (int) $m['id']; ?>">Add to banner</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="mb-6">
    <button type="button" onclick="toggleAddMovieForm()" id="add-movie-btn" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded font-semibold">
        <i class="fas fa-plus mr-2"></i>Add a Movie
    </button>
</div>

<div class="bg-gray-900 rounded-lg p-6 mb-8" id="add-movie-form" style="display: none;">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold">Add Movie</h2>
        <button type="button" onclick="toggleAddMovieForm()" class="text-gray-400 hover:text-white">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    <?php include __DIR__ . '/includes/movie-form-fields.php'; ?>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-4">
        <h2 class="text-2xl font-bold">All Movies (<?php echo number_format($total_count); ?>)</h2>
        <form method="GET" action="" class="flex flex-col md:flex-row gap-2 flex-1 md:max-w-md">
            <input type="hidden" name="tab" value="movies">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search movies..." class="flex-1 bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            <select name="category" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo $category_filter === (int) $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-netflix-red px-4 py-2 rounded hover:bg-red-700"><i class="fas fa-search mr-2"></i>Search</button>
            <?php if ($search !== '' || $category_filter > 0): ?>
            <a href="?tab=movies" class="bg-gray-700 px-4 py-2 rounded hover:bg-gray-600"><i class="fas fa-times"></i></a>
            <?php endif; ?>
        </form>
    </div>

    <form method="POST" action="" id="bulk-action-form" onsubmit="return confirmMovieBulkAction()">
        <div class="flex flex-col md:flex-row gap-2 items-start md:items-center mb-3">
            <select name="bulk_action" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
                <option value="">Bulk Actions</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="set_free">Set as Free</option>
                <option value="set_premium">Set as Premium</option>
                <option value="delete">Delete</option>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">Apply</button>
        </div>

        <div class="mb-3 p-3 bg-yellow-900 bg-opacity-30 border border-yellow-700 rounded">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" id="select-all-database" class="w-4 h-4 text-yellow-500 bg-gray-800 border-gray-700 rounded mr-2" onchange="handleSelectAllMoviesDatabase(this)">
                <span class="text-yellow-200 font-semibold">Select ALL movies in database (<?php echo number_format($total_count); ?> total)</span>
            </label>
        </div>
        <input type="hidden" name="select_all_database" id="select-all-database-hidden" value="0">

        <div class="overflow-x-auto mt-4">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="text-left p-3 w-12"><input type="checkbox" id="select-all-movies" onchange="toggleAllMovies(this)"></th>
                        <th class="text-left p-3">Poster</th>
                        <th class="text-left p-3">Title</th>
                        <th class="text-left p-3">Category</th>
                        <th class="text-left p-3">Year</th>
                        <th class="text-left p-3">Sources</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-left p-3">Views</th>
                        <th class="text-left p-3">Live Viewers</th>
                        <th class="text-left p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movies as $movie): ?>
                    <?php $movie_sources = parseSources($movie['sources'] ?? '[]'); ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800">
                        <td class="p-3"><input type="checkbox" name="selected_movies[]" value="<?php echo (int) $movie['id']; ?>" class="movie-checkbox w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded"></td>
                        <td class="p-3">
                            <?php $poster = moviePosterUrl($movie); ?>
                            <img src="<?php echo htmlspecialchars($poster); ?>" alt="" class="w-10 h-14 object-cover bg-gray-800 rounded" onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                        </td>
                        <td class="p-3">
                            <div class="font-semibold"><?php echo htmlspecialchars($movie['title']); ?></div>
                            <div class="text-xs text-gray-400 mt-1">
                                <?php if ($movie['featured']): ?><span class="text-yellow-400 mr-2">⭐ Featured</span><?php endif; ?>
                                <?php if ($movie['show_in_slider']): ?><span class="text-blue-400">📺 Slider</span><?php endif; ?>
                                <?php if (!empty($movie['quality_label'])): ?><span class="text-red-300 ml-1"><?php echo htmlspecialchars($movie['quality_label']); ?></span><?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3"><?php echo htmlspecialchars($movie['category_name'] ?? 'N/A'); ?></td>
                        <td class="p-3"><?php echo (int) ($movie['release_year'] ?? 0); ?></td>
                        <td class="p-3"><span class="px-2 py-1 bg-gray-700 rounded text-xs"><?php echo count($movie_sources); ?> source(s)</span></td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                <span class="px-2 py-1 rounded text-xs <?php echo ($movie['is_active'] ?? 1) ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>"><?php echo ($movie['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?></span>
                                <span class="px-2 py-1 rounded text-xs <?php echo ($movie['is_free'] ?? 1) ? 'bg-blue-900 text-blue-200' : 'bg-purple-900 text-purple-200'; ?>"><?php echo ($movie['is_free'] ?? 1) ? 'Free' : 'Premium'; ?></span>
                            </div>
                        </td>
                        <td class="p-3"><span class="text-gray-300"><?php echo number_format($movie['views'] ?? 0); ?></span></td>
                        <td class="p-3">
                            <span class="px-2 py-1 bg-green-900 text-green-200 rounded text-sm font-semibold" id="live-viewers-<?php echo (int) $movie['id']; ?>">
                                <i class="fas fa-eye mr-1"></i><span class="viewer-count"><?php echo getMovieConcurrentViewers($conn, (int) $movie['id']); ?></span>
                            </span>
                        </td>
                        <td class="p-3">
                            <a href="?tab=edit-movie&id=<?php echo (int) $movie['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                            <a href="?tab=movies&delete=<?php echo (int) $movie['id']; ?>" onclick="return confirm('Are you sure?')" class="text-red-400 hover:text-red-300">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-400">Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_count); ?> of <?php echo number_format($total_count); ?> movies</div>
            <div class="flex items-center gap-2">
                <?php
                $query_params = ['tab' => 'movies'];
                if ($search !== '') $query_params['search'] = $search;
                if ($category_filter > 0) $query_params['category'] = $category_filter;
                $query_string = http_build_query($query_params);
                ?>
                <?php if ($current_page > 1): ?>
                <a href="?<?php echo $query_string; ?>&page=<?php echo $current_page - 1; ?>" class="px-4 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white"><i class="fas fa-chevron-left mr-1"></i>Previous</a>
                <?php endif; ?>
                <span class="px-3 py-2 bg-netflix-red border border-red-700 rounded text-white font-semibold"><?php echo $current_page; ?></span>
                <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo $query_string; ?>&page=<?php echo $current_page + 1; ?>" class="px-4 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white">Next<i class="fas fa-chevron-right ml-1"></i></a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </form>
</div>

<script>
function toggleAddMovieForm() {
    const form = document.getElementById('add-movie-form');
    const btn = document.getElementById('add-movie-btn');
    if (!form || !btn) return;
    const show = form.style.display === 'none' || !form.style.display;
    form.style.display = show ? 'block' : 'none';
    btn.style.display = show ? 'none' : 'inline-block';
    if (show) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    else {
        const movieForm = document.getElementById('movie-form');
        if (movieForm) movieForm.reset();
        const sources = document.getElementById('sources-container');
        if (sources) sources.innerHTML = '';
        const downloads = document.getElementById('download-links-container');
        if (downloads) downloads.innerHTML = '';
        if (typeof sourceCount !== 'undefined') sourceCount = 0;
        if (typeof downloadCount !== 'undefined') downloadCount = 0;
    }
}
function toggleAllMovies(checkbox) {
    document.getElementById('select-all-database-hidden').value = '0';
    document.querySelectorAll('.movie-checkbox').forEach(cb => { cb.checked = checkbox.checked; });
}
function handleSelectAllMoviesDatabase(checkbox) {
    document.getElementById('select-all-database-hidden').value = checkbox.checked ? '1' : '0';
    if (checkbox.checked) {
        document.querySelectorAll('.movie-checkbox').forEach(cb => { cb.checked = false; });
        const all = document.getElementById('select-all-movies');
        if (all) all.checked = false;
    }
}
function confirmMovieBulkAction() {
    const allDb = document.getElementById('select-all-database')?.checked;
    const selected = document.querySelectorAll('.movie-checkbox:checked');
    const action = document.querySelector('[name="bulk_action"]')?.value;
    if (!allDb && selected.length === 0) { alert('Please select at least one movie'); return false; }
    if (!action) { alert('Please select a bulk action'); return false; }
    if (action === 'delete') return confirm(allDb ? 'Delete ALL movies in the database?' : 'Delete selected movies?');
    return true;
}
</script>
<?php include __DIR__ . '/includes/movie-form-scripts.php'; ?>
