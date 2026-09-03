<?php
/**
 * Add TV Show - Basic Details Only
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/../includes/movies_schema.php';
require_once __DIR__ . '/../includes/movie_helpers.php';

$page_title = "Add TV Show";
$conn = getDBConnection();
ensureTvShowsSchema($conn);

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $poster = trim(strip_tags((string) ($_POST['poster'] ?? '')));
    $backdrop = trim(strip_tags((string) ($_POST['backdrop'] ?? '')));
    $thumbnail = trim(strip_tags((string) ($_POST['thumbnail'] ?? '')));
    $release_year = intval($_POST['release_year'] ?? date('Y'));
    $rating = floatval($_POST['rating'] ?? 0);
    $tmdb_id = !empty($_POST['tmdb_id']) ? intval($_POST['tmdb_id']) : null;
    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $quality_label = sanitize($_POST['quality_label'] ?? '');
    $tags = encodeMovieTagsInput($_POST['tags'] ?? '');

    $upload_dir = __DIR__ . '/../uploads/tv-show-logos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Handle poster upload
    if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, $allowed_extensions, true)) {
            $file_name = 'tv_show_poster_' . time() . '_' . uniqid() . '.' . $file_extension;
            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $upload_dir . $file_name)) {
                $poster = 'uploads/tv-show-logos/' . $file_name;
            }
        }
    }

    // Handle logo/thumbnail upload
    if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, $allowed_extensions, true)) {
            $file_name = 'tv_show_logo_' . time() . '_' . uniqid() . '.' . $file_extension;
            if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $upload_dir . $file_name)) {
                $thumbnail = 'uploads/tv-show-logos/' . $file_name;
            }
        }
    }

    // Handle banner/backdrop upload
    if (isset($_FILES['backdrop_file']) && $_FILES['backdrop_file']['error'] === UPLOAD_ERR_OK) {
        $file_extension = strtolower(pathinfo($_FILES['backdrop_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, $allowed_extensions, true)) {
            $file_name = 'tv_show_banner_' . time() . '_' . uniqid() . '.' . $file_extension;
            if (move_uploaded_file($_FILES['backdrop_file']['tmp_name'], $upload_dir . $file_name)) {
                $backdrop = 'uploads/tv-show-logos/' . $file_name;
            }
        }
    }

    if ($thumbnail === '' && $poster !== '') {
        $thumbnail = $poster;
    }
    
    // Empty sources - will be added separately
    $sourcesJson = '[]';
    
    // Handle ad selections
    $pre_roll_ad_id = !empty($_POST['pre_roll_ad_id']) ? intval($_POST['pre_roll_ad_id']) : null;
    $mid_roll_ad_id = !empty($_POST['mid_roll_ad_id']) ? intval($_POST['mid_roll_ad_id']) : null;
    $end_roll_ad_id = !empty($_POST['end_roll_ad_id']) ? intval($_POST['end_roll_ad_id']) : null;
    $loop_ad_id = !empty($_POST['loop_ad_id']) ? intval($_POST['loop_ad_id']) : null;
    $loop_interval = null;
    $banner_ad_id = !empty($_POST['banner_ad_id']) ? intval($_POST['banner_ad_id']) : null;
    $popup_ad_id = !empty($_POST['popup_ad_id']) ? intval($_POST['popup_ad_id']) : null;
    $intro_ad_id = !empty($_POST['intro_ad_id']) ? intval($_POST['intro_ad_id']) : null;
    
    // Ensure ad columns exist (check first to avoid errors)
    try {
        $columns_to_add = [
            'pre_roll_ad_id' => 'INT NULL',
            'mid_roll_ad_id' => 'INT NULL',
            'end_roll_ad_id' => 'INT NULL',
            'loop_ad_id' => 'INT NULL',
            'loop_interval' => 'INT NULL',
            'banner_ad_id' => 'INT NULL',
            'popup_ad_id' => 'INT NULL',
            'intro_ad_id' => 'INT NULL'
        ];
        
        foreach ($columns_to_add as $column => $definition) {
            $check = $conn->query("SHOW COLUMNS FROM tv_shows LIKE '$column'");
            if ($check->num_rows == 0) {
                $conn->query("ALTER TABLE tv_shows ADD COLUMN $column $definition");
            }
        }
    } catch (Exception $e) {
        error_log("Error adding ad columns to tv_shows: " . $e->getMessage());
    }
    
    // Generate slug
    $slug = getUniqueSlug($conn, 'tv_shows', $title, null);
    
    $stmt = $conn->prepare("INSERT INTO tv_shows (title, description, thumbnail, poster, backdrop, release_year, rating, category_id, featured, is_active, is_free, is_premium, slug, sources, tmdb_id, tags, quality_label, pre_roll_ad_id, mid_roll_ad_id, end_roll_ad_id, loop_ad_id, loop_interval, banner_ad_id, popup_ad_id, intro_ad_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "sssssidiiiiississiiiiiiii",
        $title,
        $description,
        $thumbnail,
        $poster,
        $backdrop,
        $release_year,
        $rating,
        $category_id,
        $featured,
        $is_active,
        $is_free,
        $is_premium,
        $slug,
        $sourcesJson,
        $tmdb_id,
        $tags,
        $quality_label,
        $pre_roll_ad_id,
        $mid_roll_ad_id,
        $end_roll_ad_id,
        $loop_ad_id,
        $loop_interval,
        $banner_ad_id,
        $popup_ad_id,
        $intro_ad_id
    );
    
    if ($stmt->execute()) {
        $new_id = $conn->insert_id;
        header("Location: edit-tv-show.php?id=" . $new_id);
        exit;
    } else {
        $message = 'Error adding TV show: ' . $stmt->error;
        $message_type = 'error';
    }
}

$categories = getAllCategories($conn);

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="tv-shows.php" class="text-blue-400 hover:text-blue-300 mb-4 inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Back to TV Shows
        </a>
        <h1 class="text-4xl font-bold mb-2">Add New TV Show</h1>
    </div>

    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="bg-gray-900 rounded-lg p-6 max-w-4xl">
            <h2 class="text-2xl font-bold mb-6">TV Show Information</h2>

            <div class="mb-6 p-4 bg-gray-800 rounded-lg border border-gray-700">
                <h3 class="text-lg font-semibold mb-3"><i class="fas fa-tv mr-2"></i>Fetch from TMDB</h3>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" id="tmdb-input" placeholder="TMDB ID or URL (e.g. 1396 or https://www.themoviedb.org/tv/1396-breaking-bad)"
                           class="flex-1 bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white">
                    <button type="button" id="tmdb-fetch-btn" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded font-semibold whitespace-nowrap">
                        <i class="fas fa-download mr-2"></i>Fetch
                    </button>
                </div>
                <p class="text-xs text-gray-400 mt-2">Auto-fills title, description, logo, poster, banner, year &amp; rating. Images are linked directly from TMDB.</p>
                <div id="tmdb-fetch-status" class="text-sm mt-2 hidden"></div>
            </div>
            
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Title *</label>
                        <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                               required
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Category</label>
                        <select name="category_id" id="category_id" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($cat['name'])); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">Description</label>
                    <textarea name="description" id="description" rows="4" 
                              class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <?php
                    $show = [
                        'poster' => $_POST['poster'] ?? '',
                        'backdrop' => $_POST['backdrop'] ?? '',
                        'thumbnail' => $_POST['thumbnail'] ?? '',
                    ];
                    include __DIR__ . '/includes/tv-show-image-fields.php';
                    unset($show);
                    ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Release Year</label>
                        <input type="number" name="release_year" id="release_year" value="<?php echo htmlspecialchars($_POST['release_year'] ?? date('Y')); ?>" 
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Rating</label>
                        <input type="number" step="0.1" min="0" max="10" name="rating" id="rating" value="<?php echo htmlspecialchars($_POST['rating'] ?? '0'); ?>"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">TMDB ID</label>
                        <input type="number" name="tmdb_id" id="tmdb_id" value="<?php echo htmlspecialchars($_POST['tmdb_id'] ?? ''); ?>"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" placeholder="Optional">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Tags (shown on poster)</label>
                        <input type="text" name="tags" id="tags" value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>"
                               placeholder="Hindi Dubbed, Dual Audio"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                        <p class="text-xs text-gray-400 mt-1">Comma-separated tags displayed on the TV show card.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Quality Badge</label>
                        <select name="quality_label" id="quality_label" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                            <option value="">None</option>
                            <?php foreach (['HD', 'FHD', '4K', 'CAM', 'TS', 'Low Quality', 'High Quality'] as $ql): ?>
                            <option value="<?php echo $ql; ?>" <?php echo ($_POST['quality_label'] ?? '') === $ql ? 'selected' : ''; ?>><?php echo $ql; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="p-4 bg-gray-800 rounded-lg">
                    <h3 class="text-lg font-semibold mb-3">Content Access Settings</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_free" value="1" checked 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Free Content</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_premium" value="1" 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Premium Content</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="featured" value="1" 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Featured</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="is_active" value="1" checked 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Active</span>
                        </label>
                    </div>
                </div>
                
                <?php
                $ad_settings_row = [];
                $ad_settings_audience = 'TV show episode watching';
                include __DIR__ . '/includes/ad-settings-fields.php';
                ?>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add TV Show
                </button>
                <a href="tv-shows.php" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                    Cancel
                </a>
            </div>
        </div>
    </form>

    <div class="mt-6 bg-blue-900 bg-opacity-30 border border-blue-700 rounded-lg p-4 max-w-4xl">
        <p class="text-sm text-blue-200">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Note:</strong> After adding the TV show, you'll be redirected to edit it where you can add streaming sources and episodes.
        </p>
    </div>
</div>

<script>
document.getElementById('tmdb-fetch-btn')?.addEventListener('click', async function () {
    const input = document.getElementById('tmdb-input').value.trim();
    const status = document.getElementById('tmdb-fetch-status');
    if (!input) {
        status.textContent = 'Enter a TMDB ID or URL';
        status.className = 'text-sm mt-2 text-red-400';
        status.classList.remove('hidden');
        return;
    }
    status.textContent = 'Fetching from TMDB...';
    status.className = 'text-sm mt-2 text-blue-400';
    status.classList.remove('hidden');
    this.disabled = true;
    try {
        const res = await fetch('<?php echo BASE_URL; ?>/admin/api/tmdb-fetch.php?type=tv&input=' + encodeURIComponent(input));
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Fetch failed');
        const d = json.data;
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
        setVal('tmdb_id', d.tmdb_id);
        setVal('title', d.title);
        setVal('description', d.description);
        setVal('poster', d.poster || d.thumbnail || '');
        setVal('backdrop', d.backdrop || '');
        setVal('thumbnail', d.thumbnail || d.poster || '');
        setVal('release_year', d.release_year || '');
        setVal('rating', d.rating || 0);
        if (typeof updateTvImagePreview === 'function') {
            updateTvImagePreview('thumbnail', d.thumbnail || d.poster || '');
            updateTvImagePreview('poster', d.poster || d.thumbnail || '');
            updateTvImagePreview('backdrop', d.backdrop || '');
        }
        if (Array.isArray(d.genres) && d.genres.length) {
            const cat = document.getElementById('category_id');
            if (cat && !cat.value) {
                const names = d.genres.map(g => String(g).toLowerCase());
                for (const opt of cat.options) {
                    const n = (opt.dataset.name || opt.textContent || '').toLowerCase().trim();
                    if (n && names.includes(n)) {
                        cat.value = opt.value;
                        break;
                    }
                }
            }
        }
        status.textContent = 'TV show data fetched successfully!';
        status.className = 'text-sm mt-2 text-green-400';
    } catch (e) {
        status.textContent = e.message;
        status.className = 'text-sm mt-2 text-red-400';
    }
    this.disabled = false;
});
</script>

<?php include 'includes/footer.php'; ?>
