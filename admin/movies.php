<?php
/**
 * Admin Panel - Movies Management with Multiple Sources
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = "Manage Movies";
$conn = getDBConnection();

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM movies WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=movies";</script>';
        exit;
    } else {
        header("Location: ?tab=movies");
        exit;
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $thumbnail = sanitize($_POST['thumbnail'] ?? '');
    $poster = sanitize($_POST['poster'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $release_year = intval($_POST['release_year'] ?? date('Y'));
    $rating = floatval($_POST['rating'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $show_in_slider = isset($_POST['show_in_slider']) ? 1 : 0;
    $tmdb_id = !empty($_POST['tmdb_id']) ? intval($_POST['tmdb_id']) : null;

    // Handle movie logo/thumbnail upload (re-use uploads/tv-show-logos)
    if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/tv-show-logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = 'movie_logo_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;

            if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $file_path)) {
                // Delete old thumbnail if it was in our uploads folder
                if (!empty($id) && !empty($thumbnail) && strpos($thumbnail, 'uploads/tv-show-logos/') !== false) {
                    $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $thumbnail);
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $thumbnail = BASE_URL . '/uploads/tv-show-logos/' . $file_name;
            }
        }
    }
    
    // Handle sources
    $sources = [];
    if (isset($_POST['sources']) && is_array($_POST['sources'])) {
        foreach ($_POST['sources'] as $source) {
            if (!empty($source['url'])) {
                $sources[] = [
                    'id' => $source['id'] ?? 'src_' . time() . '_' . uniqid(),
                    'label' => sanitize($source['label'] ?? ''),
                    'url' => sanitize($source['url'] ?? ''),
                    'type' => sanitize($source['type'] ?? 'embed'),
                    'quality' => sanitize($source['quality'] ?? 'Auto'),
                    'language' => sanitize($source['language'] ?? 'English'),
                    'priority' => intval($source['priority'] ?? 999),
                    'isActive' => isset($source['isActive']) ? true : false,
                    'isVisible' => isset($source['isVisible']) ? true : false
                ];
            }
        }
    }
    $sourcesJson = encodeSources($sources);
    
    // Generate slug
    $slug = getUniqueSlug($conn, 'movies', $title, $id);
    
    if ($id) {
        $stmt = $conn->prepare("UPDATE movies SET title=?, description=?, thumbnail=?, poster=?, duration=?, release_year=?, rating=?, category_id=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, tmdb_id=?, slug=?, sources=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("sssssiidiiiiisssi", $title, $description, $thumbnail, $poster, $duration, $release_year, $rating, $category_id, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $tmdb_id, $slug, $sourcesJson, $id);
            if ($stmt->execute()) {
                $message = 'Movie updated successfully';
                $message_type = 'success';
            } else {
                $message = 'Error updating movie: ' . $stmt->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Error preparing statement: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO movies (title, description, thumbnail, poster, duration, release_year, rating, category_id, featured, is_active, is_free, is_premium, show_in_slider, tmdb_id, slug, sources) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssiidiiiiisss", $title, $description, $thumbnail, $poster, $duration, $release_year, $rating, $category_id, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $tmdb_id, $slug, $sourcesJson);
            if ($stmt->execute()) {
                $message = 'Movie added successfully';
                $message_type = 'success';
                $new_id = $conn->insert_id;
            } else {
                $message = 'Error adding movie: ' . $stmt->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Error preparing statement: ' . $conn->error;
            $message_type = 'error';
        }
    }
    if ($message_type === 'success') {
        // Use JavaScript redirect if headers already sent (when included)
        if (headers_sent()) {
            echo '<script>window.location.href = "?tab=movies&edit=' . ($id ?: $new_id ?? '') . '";</script>';
            exit;
        } else {
            header("Location: ?tab=movies&edit=" . ($id ?: $new_id ?? ''));
            exit;
        }
    }
}

// Get movies
$movies = $conn->query("SELECT m.*, c.name as category_name FROM movies m LEFT JOIN categories c ON m.category_id = c.id ORDER BY m.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get categories
$categories = getAllCategories($conn);

// Get movie for edit
$edit_movie = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_movie = getMovieById($conn, $edit_id);
    if ($edit_movie) {
        $edit_movie['sources'] = parseSources($edit_movie['sources'] ?? '[]');
    }
}
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

<!-- Add/Edit Form -->
<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4"><?php echo $edit_movie ? 'Edit' : 'Add'; ?> Movie</h2>
    <form method="POST" action="" id="movie-form" enctype="multipart/form-data" onsubmit="return validateMovieForm()">
        <?php if ($edit_movie): ?>
        <input type="hidden" name="id" value="<?php echo $edit_movie['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Title *</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($edit_movie['title'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Category</label>
                <select name="category_id" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_movie['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_movie['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Movie Logo / Thumbnail</label>
                <div class="space-y-2">
                    <input type="file" name="thumbnail_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                    <p class="text-xs text-gray-400">Upload movie logo image (JPG, PNG, GIF, WEBP). Stored in uploads/tv-show-logos.</p>
                    <?php if (!empty($edit_movie['thumbnail'])): ?>
                    <div class="mt-2">
                        <img src="<?php echo htmlspecialchars($edit_movie['thumbnail']); ?>" alt="Current Movie Logo"
                             class="max-w-24 max-h-24 object-contain bg-gray-800 rounded p-2"
                             onerror="this.style.display='none'">
                    </div>
                    <?php endif; ?>
                    <input type="text" name="thumbnail" value="<?php echo htmlspecialchars($edit_movie['thumbnail'] ?? ''); ?>" 
                           placeholder="Or enter logo/thumbnail URL"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Poster URL (Main Banner)</label>
                <input type="text" name="poster" value="<?php echo htmlspecialchars($edit_movie['poster'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Main movie banner image (separate from logo image).</p>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Duration (minutes)</label>
                <input type="number" name="duration" value="<?php echo $edit_movie['duration'] ?? ''; ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Release Year</label>
                <input type="number" name="release_year" value="<?php echo $edit_movie['release_year'] ?? date('Y'); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Rating</label>
                <input type="number" step="0.1" name="rating" value="<?php echo $edit_movie['rating'] ?? '0'; ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">TMDB ID (Optional)</label>
            <input type="number" name="tmdb_id" value="<?php echo $edit_movie['tmdb_id'] ?? ''; ?>" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
        </div>
        
        <!-- Content Access Settings -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Content Access Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_free" value="1" <?php echo ($edit_movie['is_free'] ?? 1) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Free Content (Available to all logged-in users)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_premium" value="1" <?php echo ($edit_movie['is_premium'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Premium Content (Requires subscription)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="featured" value="1" <?php echo ($edit_movie['featured'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Featured</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="show_in_slider" value="1" <?php echo ($edit_movie['show_in_slider'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Show in Homepage Slider</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($edit_movie['is_active'] ?? 1) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Active</span>
                </label>
            </div>
        </div>
        
        <!-- Multiple Sources Management -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Streaming Sources</h3>
                <button type="button" onclick="addSource()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add Source
                </button>
            </div>
            <div id="sources-container" class="space-y-3">
                <?php if ($edit_movie && !empty($edit_movie['sources'])): ?>
                    <?php foreach ($edit_movie['sources'] as $index => $source): ?>
                        <div class="source-item bg-gray-800 rounded-lg p-4 border border-gray-700" data-index="<?php echo $index; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold">Source #<?php echo $index + 1; ?></h4>
                                <button type="button" onclick="removeSource(this)" class="text-red-400 hover:text-red-300">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Source Label *</label>
                                    <input type="text" name="sources[<?php echo $index; ?>][label]" value="<?php echo htmlspecialchars($source['label'] ?? ''); ?>" placeholder="e.g., Server 1, HD Quality" 
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                                    <input type="hidden" name="sources[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($source['id'] ?? 'src_' . time() . '_' . uniqid()); ?>">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Source Type *</label>
                                    <select name="sources[<?php echo $index; ?>][type]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                                        <optgroup label="Social Media">
                                            <option value="youtube" <?php echo ($source['type'] ?? '') === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                                            <option value="dailymotion" <?php echo ($source['type'] ?? '') === 'dailymotion' ? 'selected' : ''; ?>>Dailymotion</option>
                                            <option value="vimeo" <?php echo ($source['type'] ?? '') === 'vimeo' ? 'selected' : ''; ?>>Vimeo</option>
                                            <option value="facebook" <?php echo ($source['type'] ?? '') === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                                            <option value="instagram" <?php echo ($source['type'] ?? '') === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                                            <option value="tiktok" <?php echo ($source['type'] ?? '') === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                                            <option value="twitter" <?php echo ($source['type'] ?? '') === 'twitter' ? 'selected' : ''; ?>>Twitter/X</option>
                                        </optgroup>
                                        <optgroup label="Streaming Protocols (Shaka Player)">
                                            <option value="m3u8" <?php echo ($source['type'] ?? '') === 'm3u8' ? 'selected' : ''; ?>>M3U8 (HLS)</option>
                                            <option value="hls" <?php echo ($source['type'] ?? '') === 'hls' ? 'selected' : ''; ?>>HLS Stream</option>
                                            <option value="dash" <?php echo ($source['type'] ?? '') === 'dash' ? 'selected' : ''; ?>>MPEG-DASH</option>
                                            <option value="m3u" <?php echo ($source['type'] ?? '') === 'm3u' ? 'selected' : ''; ?>>M3U Playlist</option>
                                            <option value="rtmp" <?php echo ($source['type'] ?? '') === 'rtmp' ? 'selected' : ''; ?>>RTMP Stream</option>
                                            <option value="rtsp" <?php echo ($source['type'] ?? '') === 'rtsp' ? 'selected' : ''; ?>>RTSP Stream</option>
                                        </optgroup>
                                        <optgroup label="Direct & Embed">
                                            <option value="direct" <?php echo ($source['type'] ?? '') === 'direct' ? 'selected' : ''; ?>>Direct MP4/Video</option>
                                            <option value="embed" <?php echo ($source['type'] ?? 'embed' || empty($source['type'])) ? 'selected' : ''; ?>>Iframe Embed</option>
                                            <option value="html-embed" <?php echo ($source['type'] ?? '') === 'html-embed' ? 'selected' : ''; ?>>HTML Embed Code</option>
                                            <option value="iframe-only" <?php echo ($source['type'] ?? '') === 'iframe-only' ? 'selected' : ''; ?>>Iframe Only</option>
                                            <option value="open-window" <?php echo ($source['type'] ?? '') === 'open-window' ? 'selected' : ''; ?>>Open in New Window</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold mb-1">Stream URL *</label>
                                    <input type="text" name="sources[<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($source['url'] ?? ''); ?>" placeholder="https://..." 
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Quality</label>
                                    <select name="sources[<?php echo $index; ?>][quality]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                                        <option value="Auto" <?php echo ($source['quality'] ?? 'Auto') === 'Auto' ? 'selected' : ''; ?>>Auto</option>
                                        <option value="SD" <?php echo ($source['quality'] ?? '') === 'SD' ? 'selected' : ''; ?>>SD</option>
                                        <option value="HD" <?php echo ($source['quality'] ?? '') === 'HD' ? 'selected' : ''; ?>>HD</option>
                                        <option value="FHD" <?php echo ($source['quality'] ?? '') === 'FHD' ? 'selected' : ''; ?>>FHD</option>
                                        <option value="UHD" <?php echo ($source['quality'] ?? '') === 'UHD' ? 'selected' : ''; ?>>UHD</option>
                                        <option value="4K" <?php echo ($source['quality'] ?? '') === '4K' ? 'selected' : ''; ?>>4K</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Language</label>
                                    <input type="text" name="sources[<?php echo $index; ?>][language]" value="<?php echo htmlspecialchars($source['language'] ?? 'English'); ?>" 
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1">Priority (0 = Default)</label>
                                    <input type="number" name="sources[<?php echo $index; ?>][priority]" value="<?php echo $source['priority'] ?? 999; ?>" min="0" 
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                                </div>
                                <div class="flex items-center gap-4">
                                    <label class="flex items-center text-xs">
                                        <input type="checkbox" name="sources[<?php echo $index; ?>][isActive]" <?php echo ($source['isActive'] ?? true) ? 'checked' : ''; ?> 
                                               class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                                        <span>Active</span>
                                    </label>
                                    <label class="flex items-center text-xs">
                                        <input type="checkbox" name="sources[<?php echo $index; ?>][isVisible]" <?php echo ($source['isVisible'] ?? true) ? 'checked' : ''; ?> 
                                               class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                                        <span>Visible</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">Add multiple sources. Set priority to 0 for default source (plays first).</p>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <?php echo $edit_movie ? 'Update' : 'Add'; ?> Movie
        </button>
        <?php if ($edit_movie): ?>
        <a href="?tab=movies" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<!-- Movies List -->
<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All Movies (<?php echo count($movies); ?>)</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Title</th>
                    <th class="text-left p-3">Category</th>
                    <th class="text-left p-3">Year</th>
                    <th class="text-left p-3">Sources</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Views</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($movies as $movie): ?>
                <?php $movie_sources = parseSources($movie['sources'] ?? '[]'); ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3">
                        <div class="font-semibold"><?php echo htmlspecialchars($movie['title']); ?></div>
                        <div class="text-xs text-gray-400 mt-1">
                            <?php if ($movie['featured']): ?><span class="text-yellow-400 mr-2">⭐ Featured</span><?php endif; ?>
                            <?php if ($movie['show_in_slider']): ?><span class="text-blue-400">📺 Slider</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="p-3"><?php echo htmlspecialchars($movie['category_name'] ?? 'N/A'); ?></td>
                    <td class="p-3"><?php echo $movie['release_year']; ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-gray-700 rounded text-xs"><?php echo count($movie_sources); ?> source(s)</span>
                    </td>
                    <td class="p-3">
                        <div class="flex flex-col gap-1">
                            <span class="px-2 py-1 rounded text-xs <?php echo ($movie['is_active'] ?? 1) ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                                <?php echo ($movie['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span class="px-2 py-1 rounded text-xs <?php echo ($movie['is_free'] ?? 1) ? 'bg-blue-900 text-blue-200' : 'bg-purple-900 text-purple-200'; ?>">
                                <?php echo ($movie['is_free'] ?? 1) ? 'Free' : 'Premium'; ?>
                            </span>
                        </div>
                    </td>
                    <td class="p-3"><?php echo number_format($movie['views'] ?? 0); ?></td>
                    <td class="p-3">
                        <a href="?tab=movies&edit=<?php echo $movie['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                        <a href="?tab=movies&delete=<?php echo $movie['id']; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="text-red-400 hover:text-red-300">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
let sourceCount = <?php echo ($edit_movie && !empty($edit_movie['sources'])) ? count($edit_movie['sources']) : 0; ?>;

function addSource() {
    sourceCount++;
    const container = document.getElementById('sources-container');
    const sourceHtml = `
        <div class="source-item bg-gray-800 rounded-lg p-4 border border-gray-700" data-index="${sourceCount}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold">Source #${sourceCount}</h4>
                <button type="button" onclick="removeSource(this)" class="text-red-400 hover:text-red-300">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">Source Label *</label>
                    <input type="text" name="sources[${sourceCount}][label]" placeholder="e.g., Server 1, HD Quality" 
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                    <input type="hidden" name="sources[${sourceCount}][id]" value="src_${Date.now()}_${Math.random().toString(36).substr(2, 9)}">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Source Type *</label>
                    <select name="sources[${sourceCount}][type]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                        <optgroup label="Social Media">
                            <option value="youtube">YouTube</option>
                            <option value="dailymotion">Dailymotion</option>
                            <option value="vimeo">Vimeo</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="twitter">Twitter/X</option>
                        </optgroup>
                        <optgroup label="Streaming Protocols (Shaka Player)">
                            <option value="m3u8">M3U8 (HLS)</option>
                            <option value="hls">HLS Stream</option>
                            <option value="dash">MPEG-DASH</option>
                            <option value="m3u">M3U Playlist</option>
                            <option value="rtmp">RTMP Stream</option>
                            <option value="rtsp">RTSP Stream</option>
                        </optgroup>
                        <optgroup label="Direct & Embed">
                            <option value="direct">Direct MP4/Video</option>
                            <option value="embed" selected>Iframe Embed</option>
                            <option value="html-embed">HTML Embed Code</option>
                            <option value="iframe-only">Iframe Only</option>
                            <option value="open-window">Open in New Window</option>
                        </optgroup>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold mb-1">Stream URL *</label>
                    <input type="text" name="sources[${sourceCount}][url]" placeholder="https://..." 
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Quality</label>
                    <select name="sources[${sourceCount}][quality]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="Auto" selected>Auto</option>
                        <option value="SD">SD</option>
                        <option value="HD">HD</option>
                        <option value="FHD">FHD</option>
                        <option value="UHD">UHD</option>
                        <option value="4K">4K</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Language</label>
                    <input type="text" name="sources[${sourceCount}][language]" value="English" 
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Priority (0 = Default)</label>
                    <input type="number" name="sources[${sourceCount}][priority]" value="999" min="0" 
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center text-xs">
                        <input type="checkbox" name="sources[${sourceCount}][isActive]" checked 
                               class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                        <span>Active</span>
                    </label>
                    <label class="flex items-center text-xs">
                        <input type="checkbox" name="sources[${sourceCount}][isVisible]" checked 
                               class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                        <span>Visible</span>
                    </label>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', sourceHtml);
}

function removeSource(btn) {
    if (confirm('Remove this source?')) {
        btn.closest('.source-item').remove();
    }
}

function validateMovieForm() {
    const sources = document.querySelectorAll('.source-item');
    if (sources.length === 0) {
        if (!confirm('No sources added. Continue without sources?')) {
            return false;
        }
    }
    return true;
}
</script>
