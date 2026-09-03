<?php
/**
 * Edit TV Show - Basic Details Only (No Sources)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Edit TV Show";
$conn = getDBConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';
$message_type = '';

// Get TV show
$show = null;
if ($id) {
    $show = getTVShowById($conn, $id);
}

if (!$show) {
    header("Location: tv-shows.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $poster = sanitize($_POST['poster'] ?? '');
    $thumbnail = sanitize($_POST['thumbnail'] ?? ($show['thumbnail'] ?? ''));
    $release_year = intval($_POST['release_year'] ?? date('Y'));
    $rating = floatval($_POST['rating'] ?? 0);
    $tmdb_id = !empty($_POST['tmdb_id']) ? intval($_POST['tmdb_id']) : null;
    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;

    // Handle poster upload
    if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/tv-show-logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = 'tv_show_banner_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $file_path)) {
                // Delete old poster if it was in our uploads folder
                if (!empty($show['poster']) && strpos($show['poster'], 'uploads/tv-show-logos/') !== false) {
                    $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $show['poster']);
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $poster = 'uploads/tv-show-logos/' . $file_name;
            }
        }
    }

    if ($thumbnail === '' && $poster !== '') {
        $thumbnail = $poster;
    }
    
    // Keep existing sources - don't modify them here
    $sourcesJson = $show['sources'] ?? '[]';
    
    // Handle ad selections
    $pre_roll_ad_id = !empty($_POST['pre_roll_ad_id']) ? intval($_POST['pre_roll_ad_id']) : null;
    $mid_roll_ad_id = !empty($_POST['mid_roll_ad_id']) ? intval($_POST['mid_roll_ad_id']) : null;
    $end_roll_ad_id = !empty($_POST['end_roll_ad_id']) ? intval($_POST['end_roll_ad_id']) : null;
    $loop_ad_id = !empty($_POST['loop_ad_id']) ? intval($_POST['loop_ad_id']) : null;
    $loop_interval = null; // Will be fetched from ad duration
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
        // Columns might already exist or error occurred
        error_log("Error adding ad columns to tv_shows: " . $e->getMessage());
    }
    
    // Generate slug
    $slug = getUniqueSlug($conn, 'tv_shows', $title, $id);
    
    $stmt = $conn->prepare("UPDATE tv_shows SET title=?, description=?, thumbnail=?, poster=?, release_year=?, rating=?, category_id=?, featured=?, is_active=?, is_free=?, is_premium=?, slug=?, tmdb_id=?, pre_roll_ad_id=?, mid_roll_ad_id=?, end_roll_ad_id=?, loop_ad_id=?, loop_interval=?, banner_ad_id=?, popup_ad_id=?, intro_ad_id=? WHERE id=?");
    $stmt->bind_param(
        "ssssidiiiisiiiiiiiiiii",
        $title,
        $description,
        $thumbnail,
        $poster,
        $release_year,
        $rating,
        $category_id,
        $featured,
        $is_active,
        $is_free,
        $is_premium,
        $slug,
        $tmdb_id,
        $pre_roll_ad_id,
        $mid_roll_ad_id,
        $end_roll_ad_id,
        $loop_ad_id,
        $loop_interval,
        $banner_ad_id,
        $popup_ad_id,
        $intro_ad_id,
        $id
    );
    
    if ($stmt->execute()) {
        $message = 'TV Show updated successfully';
        $message_type = 'success';
        // Refresh show data
        $show = getTVShowById($conn, $id);
    } else {
        $message = 'Error updating TV show: ' . $stmt->error;
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
        <h1 class="text-4xl font-bold mb-2">Edit TV Show</h1>
        <p class="text-gray-400">TV Show: <strong><?php echo htmlspecialchars($show['title']); ?></strong></p>
    </div>

    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Edit Form -->
        <div class="lg:col-span-2">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="bg-gray-900 rounded-lg p-6">
                    <h2 class="text-2xl font-bold mb-6">TV Show Information</h2>

                    <div class="mb-6 p-4 bg-gray-800 rounded-lg border border-gray-700">
                        <h3 class="text-lg font-semibold mb-3"><i class="fas fa-tv mr-2"></i>Fetch from TMDB</h3>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" id="tmdb-input" placeholder="TMDB ID or URL (e.g. 1396 or https://www.themoviedb.org/tv/1396-breaking-bad)"
                                   value="<?php echo !empty($show['tmdb_id']) ? (int) $show['tmdb_id'] : ''; ?>"
                                   class="flex-1 bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white">
                            <button type="button" id="tmdb-fetch-btn" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded font-semibold whitespace-nowrap">
                                <i class="fas fa-download mr-2"></i>Fetch
                            </button>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Auto-fills title, description, poster, year &amp; rating. Images are linked directly from TMDB.</p>
                        <div id="tmdb-fetch-status" class="text-sm mt-2 hidden"></div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Title *</label>
                                <input type="text" name="title" id="title" value="<?php echo htmlspecialchars($show['title'] ?? ''); ?>" 
                                       required
                                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Category</label>
                                <select name="category_id" id="category_id" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" data-name="<?php echo htmlspecialchars(strtolower($cat['name'])); ?>" <?php echo ($show['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Description</label>
                            <textarea name="description" id="description" rows="4" 
                                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($show['description'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold mb-2">Poster / Banner</label>
                            <div class="space-y-2">
                                <?php if (!empty($show['poster'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars(assetUrl($show['poster'] ?? '')); ?>" alt="Current Banner"
                                         class="max-w-xs max-h-40 object-cover bg-gray-800 rounded"
                                         onerror="this.style.display='none'">
                                </div>
                                <?php endif; ?>
                                <input type="file" name="poster_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                                <p class="text-xs text-gray-400">Upload new image or paste URL below</p>
                                <input type="text" name="poster" id="poster" value="<?php echo htmlspecialchars($show['poster'] ?? ''); ?>" 
                                       placeholder="Or enter banner/poster URL"
                                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                                <input type="hidden" name="thumbnail" id="thumbnail" value="<?php echo htmlspecialchars($show['thumbnail'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold mb-2">Release Year</label>
                                <input type="number" name="release_year" id="release_year" value="<?php echo $show['release_year'] ?? date('Y'); ?>" 
                                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">Rating</label>
                                <input type="number" step="0.1" min="0" max="10" name="rating" id="rating" value="<?php echo htmlspecialchars($show['rating'] ?? '0'); ?>"
                                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold mb-2">TMDB ID</label>
                                <input type="number" name="tmdb_id" id="tmdb_id" value="<?php echo htmlspecialchars($show['tmdb_id'] ?? ''); ?>"
                                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" placeholder="Optional">
                            </div>
                        </div>

                        <div class="p-4 bg-gray-800 rounded-lg">
                            <h3 class="text-lg font-semibold mb-3">Content Access Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_free" value="1" <?php echo ($show['is_free'] ?? 1) ? 'checked' : ''; ?> 
                                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                                    <span>Free Content</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_premium" value="1" <?php echo ($show['is_premium'] ?? 0) ? 'checked' : ''; ?> 
                                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                                    <span>Premium Content</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="featured" value="1" <?php echo ($show['featured'] ?? 0) ? 'checked' : ''; ?> 
                                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                                    <span>Featured</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" name="is_active" value="1" <?php echo ($show['is_active'] ?? 1) ? 'checked' : ''; ?> 
                                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                                    <span>Active</span>
                                </label>
                            </div>
                        </div>
                        
                        <?php
                        $ad_settings_row = $show ?? [];
                        $ad_settings_audience = 'TV show episode watching';
                        include __DIR__ . '/includes/ad-settings-fields.php';
                        ?>
                    </div>

                    <div class="mt-6 flex gap-4">
                        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                        <a href="tv-shows.php" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                            Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Quick Actions Sidebar -->
        <div class="lg:col-span-1">
            <div class="bg-gray-900 rounded-lg p-6 space-y-4">
                <h3 class="text-xl font-bold mb-4">Quick Actions</h3>
                
                <a href="add-tv-show-sources.php?show_id=<?php echo $id; ?>" class="block w-full bg-green-600 hover:bg-green-700 px-4 py-3 rounded text-center font-semibold">
                    <i class="fas fa-link mr-2"></i>Manage Sources
                </a>
                
                <a href="add-episode.php?show_id=<?php echo $id; ?>" class="block w-full bg-blue-600 hover:bg-blue-700 px-4 py-3 rounded text-center font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add Episode
                </a>
                
                <a href="tv-shows.php?show_episodes=<?php echo $id; ?>" class="block w-full bg-purple-600 hover:bg-purple-700 px-4 py-3 rounded text-center font-semibold">
                    <i class="fas fa-list mr-2"></i>View Episodes
                </a>
                
                <a href="tv-shows.php?delete=<?php echo $id; ?>" 
                   onclick="return confirm('Are you sure you want to delete this TV show? This will also delete all episodes!')"
                   class="block w-full bg-red-600 hover:bg-red-700 px-4 py-3 rounded text-center font-semibold">
                    <i class="fas fa-trash mr-2"></i>Delete TV Show
                </a>
            </div>
        </div>
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
        setVal('poster', d.poster || d.backdrop || '');
        setVal('thumbnail', d.thumbnail || d.poster || '');
        setVal('release_year', d.release_year || '');
        setVal('rating', d.rating || 0);
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
        status.textContent = 'TV show data fetched successfully! Review and click Save Changes.';
        status.className = 'text-sm mt-2 text-green-400';
    } catch (e) {
        status.textContent = e.message;
        status.className = 'text-sm mt-2 text-red-400';
    }
    this.disabled = false;
});
</script>

<?php include 'includes/footer.php'; ?>
