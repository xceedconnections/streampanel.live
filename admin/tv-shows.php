<?php
/**
 * Admin Panel - TV Shows Management with Multiple Sources
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = "Manage TV Shows";
$conn = getDBConnection();

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM tv_shows WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if (headers_sent()) {
        echo '<script>window.location.href = "' . BASE_URL . '/admin/?tab=tv-shows";</script>';
        exit;
    } else {
        header("Location: " . BASE_URL . "/admin/?tab=tv-shows");
        exit;
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['episode_action'])) {
    $id = $_POST['id'] ?? null;
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $thumbnail = ''; // Thumbnail removed - only using poster/banner now
    $poster = sanitize($_POST['poster'] ?? '');
    $release_year = intval($_POST['release_year'] ?? date('Y'));
    $rating = floatval($_POST['rating'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $is_free = isset($_POST['is_free']) ? 1 : 0;
    $is_premium = isset($_POST['is_premium']) ? 1 : 0;
    $show_in_slider = isset($_POST['show_in_slider']) ? 1 : 0;
    $tmdb_id = !empty($_POST['tmdb_id']) ? intval($_POST['tmdb_id']) : null;

    // Thumbnail field removed - only using poster/banner now

    // Handle TV show main banner/poster upload (stored in uploads/tv-show-logos)
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
                if (!empty($id) && !empty($poster) && strpos($poster, 'uploads/tv-show-logos/') !== false) {
                    $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $poster);
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $poster = BASE_URL . '/uploads/tv-show-logos/' . $file_name;
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
    $slug = getUniqueSlug($conn, 'tv_shows', $title, $id);
    
    if ($id) {
        $stmt = $conn->prepare("UPDATE tv_shows SET title=?, description=?, thumbnail=?, poster=?, release_year=?, rating=?, category_id=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, tmdb_id=?, slug=?, sources=? WHERE id=?");
        if ($stmt) {
            // Bind all values as strings to keep the type-definition count simple and correct
            // (MySQL will safely cast numeric strings for INT/FLOAT columns)
            $stmt->bind_param("ssssssssssssssss", $title, $description, $thumbnail, $poster, $release_year, $rating, $category_id, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $tmdb_id, $slug, $sourcesJson, $id);
            if ($stmt->execute()) {
                $message = 'TV Show updated successfully';
                $message_type = 'success';
            } else {
                $message = 'Error updating TV show: ' . $stmt->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Error preparing statement: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO tv_shows (title, description, thumbnail, poster, release_year, rating, category_id, featured, is_active, is_free, is_premium, show_in_slider, tmdb_id, slug, sources) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            // 15 values => 15 "s" in the type string
            $stmt->bind_param("sssssssssssssss", $title, $description, $thumbnail, $poster, $release_year, $rating, $category_id, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $tmdb_id, $slug, $sourcesJson);
            if ($stmt->execute()) {
                $message = 'TV Show added successfully';
                $message_type = 'success';
                $new_id = $conn->insert_id;
            } else {
                $message = 'Error adding TV show: ' . $stmt->error;
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
            echo '<script>window.location.href = "' . BASE_URL . '/admin/?tab=tv-shows&edit=' . ($id ?: $new_id ?? '') . '";</script>';
            exit;
        } else {
            header("Location: " . BASE_URL . "/admin/?tab=tv-shows&edit=" . ($id ?: $new_id ?? ''));
            exit;
        }
    }
}

$tv_shows = $conn->query("SELECT t.*, c.name as category_name FROM tv_shows t LEFT JOIN categories c ON t.category_id = c.id ORDER BY t.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$categories = getAllCategories($conn);

$edit_show = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_show = getTVShowById($conn, $edit_id);
    if ($edit_show) {
        $edit_show['sources'] = parseSources($edit_show['sources'] ?? '[]');
    }
}

?>

<h1 class="text-4xl font-bold mb-8">Manage TV Shows</h1>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4"><?php echo $edit_show ? 'Edit' : 'Add'; ?> TV Show</h2>
    <form method="POST" action="" enctype="multipart/form-data">
        <?php if ($edit_show): ?>
        <input type="hidden" name="id" value="<?php echo $edit_show['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Title</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($edit_show['title'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Category</label>
                <select name="category_id" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_show['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_show['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Poster / Main Banner</label>
                <div class="space-y-2">
                    <input type="file" name="poster_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                    <p class="text-xs text-gray-400">Upload main banner image (stored in uploads/tv-show-logos).</p>
                    <?php if (!empty($edit_show['poster'])): ?>
                    <div class="mt-2">
                        <img src="<?php echo htmlspecialchars($edit_show['poster']); ?>" alt="Current Banner"
                             class="max-w-xs max-h-40 object-cover bg-gray-800 rounded"
                             onerror="this.style.display='none'">
                    </div>
                    <?php endif; ?>
                    <input type="text" name="poster" value="<?php echo htmlspecialchars($edit_show['poster'] ?? ''); ?>" 
                           placeholder="Or enter banner/poster URL"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                </div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Release Year</label>
                <input type="number" name="release_year" value="<?php echo $edit_show['release_year'] ?? date('Y'); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Rating</label>
                <input type="number" step="0.1" name="rating" value="<?php echo $edit_show['rating'] ?? '0'; ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">TMDB ID (Optional)</label>
            <input type="number" name="tmdb_id" value="<?php echo $edit_show['tmdb_id'] ?? ''; ?>" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
        </div>
        
        <!-- Content Access Settings -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Content Access Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_free" value="1" <?php echo ($edit_show['is_free'] ?? 1) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Free Content (Available to all logged-in users)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_premium" value="1" <?php echo ($edit_show['is_premium'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Premium Content (Requires subscription)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="featured" value="1" <?php echo ($edit_show['featured'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Featured</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="show_in_slider" value="1" <?php echo ($edit_show['show_in_slider'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Show in Homepage Slider</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($edit_show['is_active'] ?? 1) ? 'checked' : ''; ?> 
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
                <?php 
                if ($edit_show) {
                    $edit_show['sources'] = parseSources($edit_show['sources'] ?? '[]');
                    if (!empty($edit_show['sources'])) {
                        foreach ($edit_show['sources'] as $index => $source): ?>
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
                        <?php endforeach;
                    }
                }
                ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">Add multiple sources. Set priority to 0 for default source (plays first).</p>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <?php echo $edit_show ? 'Update' : 'Add'; ?> TV Show
        </button>
        <?php if ($edit_show): ?>
        <a href="?tab=tv-shows" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<script>
let sourceCount = <?php echo ($edit_show && !empty($edit_show['sources'])) ? count($edit_show['sources']) : 0; ?>;

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
</script>

<?php if ($edit_show): ?>
<!-- Episodes Management Section -->
<div class="bg-gray-900 rounded-lg p-6 mb-8 border-2 border-blue-600">
    <div class="mb-6">
        <h2 class="text-3xl font-bold mb-2 flex items-center">
            <i class="fas fa-tv mr-3 text-blue-400"></i>
            Episodes for: <?php echo htmlspecialchars($edit_show['title']); ?>
        </h2>
        <p class="text-gray-400 text-sm">Manage episodes for this TV show. Each episode can have multiple streaming sources.</p>
    </div>
    
    <!-- Quick Guide -->
    <div class="bg-blue-900 bg-opacity-30 border border-blue-700 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-semibold mb-2 flex items-center">
            <i class="fas fa-info-circle mr-2 text-blue-400"></i>
            How to Add Episodes:
        </h3>
        <ol class="list-decimal list-inside space-y-1 text-sm text-gray-300">
            <li>Fill in the episode details below (Season, Episode Number, Title, etc.)</li>
            <li>Click <strong>"Add Source"</strong> to add streaming links for this episode</li>
            <li>Each source needs: Label (e.g., "Server 1"), Type (YouTube/HLS/Embed), and Stream URL</li>
            <li>Set Priority to <strong>0</strong> for the default source (plays first)</li>
            <li>Click <strong>"Add Episode"</strong> to save</li>
        </ol>
        <p class="text-xs text-gray-400 mt-3">
            <i class="fas fa-lightbulb mr-1"></i>
            <strong>Tip:</strong> Episode sources are separate from TV show sources. Each episode can have different streaming links.
        </p>
    </div>
    
    <div id="episodes-section">
        <?php
        // Get episodes for this TV show
        $episodes = [];
        if ($edit_show) {
            $episodes_query = $conn->prepare("SELECT * FROM tv_episodes WHERE tv_show_id = ? ORDER BY season_number ASC, episode_number ASC");
            $episodes_query->bind_param("i", $edit_show['id']);
            $episodes_query->execute();
            $episodes = $episodes_query->get_result()->fetch_all(MYSQLI_ASSOC);
        }
        
        // Handle episode add/edit/delete
        $episode_message = '';
        $episode_message_type = '';
        
        if (isset($_POST['episode_action'])) {
            $action = $_POST['episode_action'];
            $episode_id = $_POST['episode_id'] ?? null;
            $season = intval($_POST['season_number'] ?? 1);
            $episode_num = intval($_POST['episode_number'] ?? 1);
            $episode_title = sanitize($_POST['episode_title'] ?? '');
            $episode_desc = sanitize($_POST['episode_description'] ?? '');
            $episode_thumb = sanitize($_POST['episode_thumbnail'] ?? '');
            $episode_duration = intval($_POST['episode_duration'] ?? 0);

            // Handle per-episode image upload (separate from main TV show banner)
            if (isset($_FILES['episode_thumbnail_file']) && $_FILES['episode_thumbnail_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/tv-show-logos/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = strtolower(pathinfo($_FILES['episode_thumbnail_file']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (in_array($file_extension, $allowed_extensions)) {
                    $file_name = 'tv_episode_img_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;

                    if (move_uploaded_file($_FILES['episode_thumbnail_file']['tmp_name'], $file_path)) {
                        // Delete old episode image if it was in our uploads folder
                        if (!empty($edit_episode['thumbnail']) && strpos($edit_episode['thumbnail'], 'uploads/tv-show-logos/') !== false) {
                            $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $edit_episode['thumbnail']);
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                        $episode_thumb = BASE_URL . '/uploads/tv-show-logos/' . $file_name;
                    }
                }
            }
            
            // Handle sources for episode - IMPORTANT: Make sure type is properly saved
            // For embed-style types we must NOT strip HTML tags, so full embed code is stored
            $episode_sources = [];
            if (isset($_POST['episode_sources']) && is_array($_POST['episode_sources'])) {
                foreach ($_POST['episode_sources'] as $source) {
                    if (!empty($source['url'])) {
                        // Ensure type is properly captured - get it directly from POST
                        // Don't use sanitize() on type as it might strip valid values
                        // IMPORTANT: Get raw type value without any processing
                        $raw_type = isset($source['type']) ? $source['type'] : '';
                        $source_type = !empty($raw_type) ? trim($raw_type) : 'embed';
                        
                        // Log for debugging (remove after testing)
                        if (empty($raw_type) || $source_type === 'embed') {
                            error_log("Episode Source Type Issue - Raw: '" . $raw_type . "', Final: '" . $source_type . "', Full POST: " . json_encode($source));
                        }
                        
                        // For embed / html-embed / iframe-only types, keep full HTML (no strip_tags/htmlspecialchars)
                        if (in_array($source_type, ['embed', 'html-embed', 'iframe-only'], true)) {
                            // Also decode any HTML entities that might have been pasted or stored previously
                            $raw_url = html_entity_decode(trim($source['url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        } else {
                            $raw_url = sanitize($source['url'] ?? '');
                        }
                        
                        $episode_sources[] = [
                            'id' => $source['id'] ?? 'src_' . time() . '_' . uniqid(),
                            'label' => sanitize($source['label'] ?? ''),
                            'url' => $raw_url,
                            'type' => $source_type, // Use the properly captured type (youtube, m3u8, dash, etc.)
                            'quality' => sanitize($source['quality'] ?? 'Auto'),
                            'language' => sanitize($source['language'] ?? 'English'),
                            'priority' => intval($source['priority'] ?? 999),
                            'isActive' => isset($source['isActive']) ? true : false,
                            'isVisible' => isset($source['isVisible']) ? true : false
                        ];
                    }
                }
            }
            $episode_sourcesJson = encodeSources($episode_sources);
            
            if ($action === 'add' || $action === 'edit') {
                if ($episode_id && $action === 'edit') {
                    $stmt = $conn->prepare("UPDATE tv_episodes SET season_number=?, episode_number=?, title=?, description=?, thumbnail=?, duration=?, sources=? WHERE id=?");
                    $stmt->bind_param("iisssisi", $season, $episode_num, $episode_title, $episode_desc, $episode_thumb, $episode_duration, $episode_sourcesJson, $episode_id);
                    $stmt->execute();
                    $episode_message = 'Episode updated successfully';
                } else {
                    // Check if an episode with the same tv_show_id, season_number, and episode_number already exists
                    $check_stmt = $conn->prepare("SELECT id FROM tv_episodes WHERE tv_show_id = ? AND season_number = ? AND episode_number = ?");
                    $check_stmt->bind_param("iii", $edit_show['id'], $season, $episode_num);
                    $check_stmt->execute();
                    $existing_episode = $check_stmt->get_result()->fetch_assoc();
                    $check_stmt->close();
                    
                    if ($existing_episode) {
                        // Episode already exists, update it instead
                        $existing_id = $existing_episode['id'];
                        $stmt = $conn->prepare("UPDATE tv_episodes SET title=?, description=?, thumbnail=?, duration=?, sources=? WHERE id=?");
                        $stmt->bind_param("sssisi", $episode_title, $episode_desc, $episode_thumb, $episode_duration, $episode_sourcesJson, $existing_id);
                        $stmt->execute();
                        $episode_message = 'Episode updated successfully (duplicate season/episode detected)';
                    } else {
                        // New episode, insert it
                        $stmt = $conn->prepare("INSERT INTO tv_episodes (tv_show_id, season_number, episode_number, title, description, thumbnail, duration, sources) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("iiisssis", $edit_show['id'], $season, $episode_num, $episode_title, $episode_desc, $episode_thumb, $episode_duration, $episode_sourcesJson);
                        $stmt->execute();
                        $episode_message = 'Episode added successfully';
                    }
                }
                $episode_message_type = 'success';
                $redirect_url = "?tab=tv-shows&edit=" . $edit_show['id'] . "&show_episodes=1";
                if (headers_sent()) {
                    echo '<script>window.location.href = "' . $redirect_url . '";</script>';
                    exit;
                } else {
                    header("Location: " . $redirect_url);
                    exit;
                }
            } elseif ($action === 'delete' && $episode_id) {
                $stmt = $conn->prepare("DELETE FROM tv_episodes WHERE id = ?");
                $stmt->bind_param("i", $episode_id);
                $stmt->execute();
                $episode_message = 'Episode deleted successfully';
                $episode_message_type = 'success';
                $redirect_url = "?tab=tv-shows&edit=" . $edit_show['id'] . "&show_episodes=1";
                if (headers_sent()) {
                    echo '<script>window.location.href = "' . $redirect_url . '";</script>';
                    exit;
                } else {
                    header("Location: " . $redirect_url);
                    exit;
                }
            }
        }
        
        // Get episode for edit
        $edit_episode = null;
        if (isset($_GET['edit_episode'])) {
            $edit_ep_id = intval($_GET['edit_episode']);
            $edit_ep_stmt = $conn->prepare("SELECT * FROM tv_episodes WHERE id = ?");
            $edit_ep_stmt->bind_param("i", $edit_ep_id);
            $edit_ep_stmt->execute();
            $edit_episode = $edit_ep_stmt->get_result()->fetch_assoc();
            if ($edit_episode) {
                $edit_episode['sources'] = parseSources($edit_episode['sources'] ?? '[]');
            }
        }
        ?>
        
        <?php if ($episode_message): ?>
        <div class="bg-<?php echo $episode_message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $episode_message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $episode_message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($episode_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Add/Edit Episode Form -->
        <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-700">
                <h3 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-<?php echo $edit_episode ? 'edit' : 'plus-circle'; ?> mr-2 text-green-400"></i>
                    <?php echo $edit_episode ? 'Edit' : 'Add New'; ?> Episode
                </h3>
                <?php if ($edit_episode): ?>
                <span class="px-3 py-1 bg-blue-600 rounded text-sm">Editing: S<?php echo $edit_episode['season_number']; ?>E<?php echo $edit_episode['episode_number']; ?></span>
                <?php endif; ?>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="episode_action" value="<?php echo $edit_episode ? 'edit' : 'add'; ?>">
                <?php if ($edit_episode): ?>
                <input type="hidden" name="episode_id" value="<?php echo $edit_episode['id']; ?>">
                <?php endif; ?>
                
                <!-- Step 1: Basic Episode Info -->
                <div class="mb-6">
                    <h4 class="text-lg font-semibold mb-3 flex items-center">
                        <span class="bg-blue-600 rounded-full w-6 h-6 flex items-center justify-center text-sm mr-2">1</span>
                        Basic Episode Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-semibold mb-2">Season Number *</label>
                            <input type="number" name="season_number" value="<?php echo $edit_episode['season_number'] ?? '1'; ?>" min="1"
                                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" required>
                            <p class="text-xs text-gray-400 mt-1">e.g., 1, 2, 3...</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Episode Number *</label>
                            <input type="number" name="episode_number" value="<?php echo $edit_episode['episode_number'] ?? '1'; ?>" min="1"
                                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" required>
                            <p class="text-xs text-gray-400 mt-1">e.g., 1, 2, 3...</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-2">Duration (minutes)</label>
                            <input type="number" name="episode_duration" value="<?php echo $edit_episode['duration'] ?? ''; ?>"
                                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white"
                                   placeholder="e.g., 45">
                            <p class="text-xs text-gray-400 mt-1">Optional</p>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Episode Title *</label>
                        <input type="text" name="episode_title" value="<?php echo htmlspecialchars($edit_episode['title'] ?? ''); ?>"
                               class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white" required
                               placeholder="e.g., The Pilot Episode">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Description</label>
                        <textarea name="episode_description" rows="2"
                                  class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white"
                                  placeholder="Episode description (optional)"><?php echo htmlspecialchars($edit_episode['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Episode Thumbnail Image</label>
                        <div class="space-y-2">
                            <input type="file" name="episode_thumbnail_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                            <p class="text-xs text-gray-400">Upload image or paste URL below</p>
                            <?php if (!empty($edit_episode['thumbnail'])): ?>
                            <div class="mt-2">
                                <img src="<?php echo htmlspecialchars($edit_episode['thumbnail']); ?>" alt="Episode Image"
                                     class="max-w-32 max-h-32 object-contain bg-gray-800 rounded p-2 border border-gray-600"
                                     onerror="this.style.display='none'">
                            </div>
                            <?php endif; ?>
                            <input type="text" name="episode_thumbnail" value="<?php echo htmlspecialchars($edit_episode['thumbnail'] ?? ''); ?>"
                                   placeholder="https://example.com/episode-image.jpg"
                                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Episode Sources -->
                <div class="mb-6 border-t border-gray-700 pt-6">
                    <div class="flex items-center justify-between mb-4">
                        <h4 class="text-lg font-semibold flex items-center">
                            <span class="bg-green-600 rounded-full w-6 h-6 flex items-center justify-center text-sm mr-2">2</span>
                            Episode Streaming Sources
                        </h4>
                        <button type="button" onclick="addEpisodeSource()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm font-semibold">
                            <i class="fas fa-plus mr-1"></i>Add Source
                        </button>
                    </div>
                    
                    <div class="bg-yellow-900 bg-opacity-20 border border-yellow-700 rounded-lg p-3 mb-4">
                        <p class="text-sm text-yellow-200">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Important:</strong> Each episode needs at least one source. You can add multiple sources (backup servers, different qualities, etc.). 
                            Set Priority to <strong>0</strong> for the default source that plays first.
                        </p>
                    </div>
                    
                    <div id="episode-sources-container" class="space-y-4">
                        <?php if ($edit_episode && !empty($edit_episode['sources'])): ?>
                            <?php foreach ($edit_episode['sources'] as $ep_idx => $ep_source): ?>
                                <div class="episode-source-item bg-gray-700 rounded-lg p-4 border-2 border-gray-600 hover:border-green-500 transition-colors" data-index="<?php echo $ep_idx; ?>">
                                    <div class="flex items-center justify-between mb-3">
                                        <h5 class="font-semibold text-base flex items-center">
                                            <i class="fas fa-link mr-2 text-green-400"></i>
                                            Source #<?php echo $ep_idx + 1; ?>
                                            <?php if (($ep_source['priority'] ?? 999) == 0): ?>
                                            <span class="ml-2 px-2 py-1 bg-green-600 rounded text-xs">DEFAULT</span>
                                            <?php endif; ?>
                                        </h5>
                                        <button type="button" onclick="removeEpisodeSource(this)" class="text-red-400 hover:text-red-300 text-sm px-2 py-1 hover:bg-red-900 rounded">
                                            <i class="fas fa-trash mr-1"></i>Remove
                                        </button>
                                    </div>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">Source Label *</label>
                                            <input type="text" name="episode_sources[<?php echo $ep_idx; ?>][label]" value="<?php echo htmlspecialchars($ep_source['label'] ?? ''); ?>" 
                                                   placeholder="e.g., Server 1, HD Quality"
                                                   class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" required>
                                            <input type="hidden" name="episode_sources[<?php echo $ep_idx; ?>][id]" value="<?php echo htmlspecialchars($ep_source['id'] ?? 'src_' . time() . '_' . uniqid()); ?>">
                                            <p class="text-xs text-gray-400 mt-1">Name shown to users</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">Source Type *</label>
                                            <select name="episode_sources[<?php echo $ep_idx; ?>][type]" id="episode_source_type_<?php echo $ep_idx; ?>" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" required>
                                                <optgroup label="Social Media">
                                                    <option value="youtube" <?php echo (isset($ep_source['type']) && $ep_source['type'] === 'youtube') ? 'selected' : ''; ?>>YouTube</option>
                                                    <option value="dailymotion" <?php echo ($ep_source['type'] ?? '') === 'dailymotion' ? 'selected' : ''; ?>>Dailymotion</option>
                                                    <option value="vimeo" <?php echo ($ep_source['type'] ?? '') === 'vimeo' ? 'selected' : ''; ?>>Vimeo</option>
                                                    <option value="facebook" <?php echo ($ep_source['type'] ?? '') === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                                                    <option value="instagram" <?php echo ($ep_source['type'] ?? '') === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                                                    <option value="tiktok" <?php echo ($ep_source['type'] ?? '') === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                                                </optgroup>
                                                <optgroup label="Streaming Protocols">
                                                    <option value="m3u8" <?php echo ($ep_source['type'] ?? '') === 'm3u8' ? 'selected' : ''; ?>>M3U8 (HLS)</option>
                                                    <option value="hls" <?php echo ($ep_source['type'] ?? '') === 'hls' ? 'selected' : ''; ?>>HLS Stream</option>
                                                    <option value="dash" <?php echo ($ep_source['type'] ?? '') === 'dash' ? 'selected' : ''; ?>>MPEG-DASH</option>
                                                    <option value="m3u" <?php echo ($ep_source['type'] ?? '') === 'm3u' ? 'selected' : ''; ?>>M3U Playlist</option>
                                                </optgroup>
                                                <optgroup label="Direct & Embed">
                                                    <option value="direct" <?php echo (isset($ep_source['type']) && $ep_source['type'] === 'direct') ? 'selected' : ''; ?>>Direct MP4/Video</option>
                                                    <option value="embed" <?php echo (isset($ep_source['type']) && $ep_source['type'] === 'embed') ? 'selected' : ''; ?>>Iframe Embed</option>
                                                    <option value="html-embed" <?php echo (isset($ep_source['type']) && $ep_source['type'] === 'html-embed') ? 'selected' : ''; ?>>HTML Embed Code</option>
                                                    <option value="open-window" <?php echo (isset($ep_source['type']) && $ep_source['type'] === 'open-window') ? 'selected' : ''; ?>>Open in New Window</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-semibold mb-1">Stream URL *</label>
                                            <input type="text" name="episode_sources[<?php echo $ep_idx; ?>][url]" value="<?php echo htmlspecialchars($ep_source['url'] ?? ''); ?>" 
                                                   placeholder="https://example.com/video.m3u8 or embed URL"
                                                   class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" required>
                                            <p class="text-xs text-gray-400 mt-1">Full URL to the video stream or embed code</p>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">Quality</label>
                                            <select name="episode_sources[<?php echo $ep_idx; ?>][quality]" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm">
                                                <option value="Auto" <?php echo ($ep_source['quality'] ?? 'Auto') === 'Auto' ? 'selected' : ''; ?>>Auto</option>
                                                <option value="SD" <?php echo ($ep_source['quality'] ?? '') === 'SD' ? 'selected' : ''; ?>>SD (480p)</option>
                                                <option value="HD" <?php echo ($ep_source['quality'] ?? '') === 'HD' ? 'selected' : ''; ?>>HD (720p)</option>
                                                <option value="FHD" <?php echo ($ep_source['quality'] ?? '') === 'FHD' ? 'selected' : ''; ?>>FHD (1080p)</option>
                                                <option value="4K" <?php echo ($ep_source['quality'] ?? '') === '4K' ? 'selected' : ''; ?>>4K (2160p)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold mb-1">Priority</label>
                                            <input type="number" name="episode_sources[<?php echo $ep_idx; ?>][priority]" value="<?php echo $ep_source['priority'] ?? 999; ?>" min="0" 
                                                   class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm"
                                                   placeholder="0 = Default">
                                            <p class="text-xs text-gray-400 mt-1"><strong>0</strong> = Default (plays first), Higher = Backup</p>
                                        </div>
                                        <div class="flex items-center gap-4 pt-2">
                                            <label class="flex items-center text-sm cursor-pointer">
                                                <input type="checkbox" name="episode_sources[<?php echo $ep_idx; ?>][isActive]" <?php echo ($ep_source['isActive'] ?? true) ? 'checked' : ''; ?> 
                                                       class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                                                <span>Active</span>
                                            </label>
                                            <label class="flex items-center text-sm cursor-pointer">
                                                <input type="checkbox" name="episode_sources[<?php echo $ep_idx; ?>][isVisible]" <?php echo ($ep_source['isVisible'] ?? true) ? 'checked' : ''; ?> 
                                                       class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                                                <span>Visible to Users</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($edit_episode['sources']) || count($edit_episode['sources']) == 0): ?>
                    <div class="bg-red-900 bg-opacity-30 border border-red-700 rounded-lg p-4 text-center">
                        <p class="text-red-200 text-sm">
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <strong>No sources added yet!</strong> Click "Add Source" above to add at least one streaming source for this episode.
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Submit Buttons -->
                <div class="flex items-center gap-3 pt-4 border-t border-gray-700">
                    <button type="submit" class="bg-netflix-red px-6 py-3 rounded hover:bg-red-700 text-base font-semibold flex items-center">
                        <i class="fas fa-<?php echo $edit_episode ? 'save' : 'plus-circle'; ?> mr-2"></i>
                        <?php echo $edit_episode ? 'Update Episode' : 'Add Episode'; ?>
                    </button>
                    <?php if ($edit_episode): ?>
                    <a href="?tab=tv-shows&edit=<?php echo $edit_show['id']; ?>&show_episodes=1" class="bg-gray-600 px-6 py-3 rounded hover:bg-gray-500 text-base font-semibold">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Episodes List -->
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-2xl font-bold flex items-center">
                    <i class="fas fa-list mr-2 text-blue-400"></i>
                    All Episodes (<?php echo count($episodes); ?>)
                </h3>
                <?php if (!empty($episodes)): ?>
                <span class="text-sm text-gray-400">Click "Edit" to modify an episode</span>
                <?php endif; ?>
            </div>
            <?php if (empty($episodes)): ?>
            <div class="text-center py-8">
                <i class="fas fa-tv text-4xl text-gray-600 mb-3"></i>
                <p class="text-gray-400 text-lg mb-2">No episodes added yet</p>
                <p class="text-gray-500 text-sm">Use the form above to add your first episode</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-700">
                            <th class="text-left p-3 font-semibold">Season</th>
                            <th class="text-left p-3 font-semibold">Episode</th>
                            <th class="text-left p-3 font-semibold">Title</th>
                            <th class="text-left p-3 font-semibold">Sources</th>
                            <th class="text-left p-3 font-semibold">Duration</th>
                            <th class="text-left p-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($episodes as $ep): ?>
                        <?php $ep_sources = parseSources($ep['sources'] ?? '[]'); ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-700 transition-colors">
                            <td class="p-3">
                                <span class="px-3 py-1 bg-blue-600 rounded font-semibold">S<?php echo $ep['season_number']; ?></span>
                            </td>
                            <td class="p-3">
                                <span class="px-3 py-1 bg-green-600 rounded font-semibold">E<?php echo $ep['episode_number']; ?></span>
                            </td>
                            <td class="p-3 font-medium"><?php echo htmlspecialchars($ep['title']); ?></td>
                            <td class="p-3">
                                <?php if (count($ep_sources) > 0): ?>
                                <span class="px-3 py-1 bg-gray-700 rounded text-sm font-semibold">
                                    <i class="fas fa-link mr-1"></i><?php echo count($ep_sources); ?> source<?php echo count($ep_sources) > 1 ? 's' : ''; ?>
                                </span>
                                <?php else: ?>
                                <span class="px-3 py-1 bg-red-900 rounded text-sm text-red-200">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>No sources
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3"><?php echo $ep['duration'] ? $ep['duration'] . ' min' : '-'; ?></td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <a href="?tab=tv-shows&edit=<?php echo $edit_show['id']; ?>&show_episodes=1&edit_episode=<?php echo $ep['id']; ?>" 
                                       class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm font-semibold">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <form method="POST" action="" class="inline" onsubmit="return confirm('Are you sure you want to delete this episode? This cannot be undone!')">
                                        <input type="hidden" name="episode_action" value="delete">
                                        <input type="hidden" name="episode_id" value="<?php echo $ep['id']; ?>">
                                        <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm font-semibold">
                                            <i class="fas fa-trash mr-1"></i>Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Scroll to episodes section when editing
function scrollToEpisodes() {
    const section = document.getElementById('episodes-section');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// Auto-scroll to episodes section if editing an episode
<?php if (isset($_GET['edit_episode'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(scrollToEpisodes, 100);
});
<?php endif; ?>

let episodeSourceCount = <?php echo $edit_episode && !empty($edit_episode['sources']) ? count($edit_episode['sources']) : 0; ?>;

function addEpisodeSource() {
    episodeSourceCount++;
    const container = document.getElementById('episode-sources-container');
    const sourceHtml = `
        <div class="episode-source-item bg-gray-700 rounded-lg p-4 border-2 border-gray-600 hover:border-green-500 transition-colors" data-index="${episodeSourceCount}">
            <div class="flex items-center justify-between mb-3">
                <h5 class="font-semibold text-base flex items-center">
                    <i class="fas fa-link mr-2 text-green-400"></i>
                    Source #${episodeSourceCount}
                </h5>
                <button type="button" onclick="removeEpisodeSource(this)" class="text-red-400 hover:text-red-300 text-sm px-2 py-1 hover:bg-red-900 rounded">
                    <i class="fas fa-trash mr-1"></i>Remove
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1">Source Label *</label>
                    <input type="text" name="episode_sources[${episodeSourceCount}][label]" placeholder="e.g., Server 1, HD Quality" 
                           class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" required>
                    <input type="hidden" name="episode_sources[${episodeSourceCount}][id]" value="src_${Date.now()}_${Math.random().toString(36).substr(2, 9)}">
                    <p class="text-xs text-gray-400 mt-1">Name shown to users</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Source Type *</label>
                    <select name="episode_sources[${episodeSourceCount}][type]" id="episode_source_type_${episodeSourceCount}" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" required>
                        <optgroup label="Social Media">
                            <option value="youtube" selected>YouTube</option>
                            <option value="dailymotion">Dailymotion</option>
                            <option value="vimeo">Vimeo</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                        </optgroup>
                        <optgroup label="Streaming Protocols">
                            <option value="m3u8">M3U8 (HLS)</option>
                            <option value="hls">HLS Stream</option>
                            <option value="dash">MPEG-DASH</option>
                            <option value="m3u">M3U Playlist</option>
                        </optgroup>
                        <optgroup label="Direct & Embed">
                            <option value="direct">Direct MP4/Video</option>
                            <option value="embed">Iframe Embed</option>
                            <option value="html-embed">HTML Embed Code</option>
                            <option value="open-window">Open in New Window</option>
                        </optgroup>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-1">Stream URL *</label>
                    <input type="text" name="episode_sources[${episodeSourceCount}][url]" placeholder="https://example.com/video.m3u8 or embed URL" 
                           class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm" required>
                    <p class="text-xs text-gray-400 mt-1">Full URL to the video stream or embed code</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Quality</label>
                    <select name="episode_sources[${episodeSourceCount}][quality]" class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm">
                        <option value="Auto" selected>Auto</option>
                        <option value="SD">SD (480p)</option>
                        <option value="HD">HD (720p)</option>
                        <option value="FHD">FHD (1080p)</option>
                        <option value="4K">4K (2160p)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Priority</label>
                    <input type="number" name="episode_sources[${episodeSourceCount}][priority]" value="999" min="0" 
                           placeholder="0 = Default"
                           class="w-full bg-gray-600 border border-gray-500 rounded px-3 py-2 text-white text-sm">
                    <p class="text-xs text-gray-400 mt-1"><strong>0</strong> = Default (plays first), Higher = Backup</p>
                </div>
                <div class="flex items-center gap-4 pt-2">
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="checkbox" name="episode_sources[${episodeSourceCount}][isActive]" checked 
                               class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                        <span>Active</span>
                    </label>
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="checkbox" name="episode_sources[${episodeSourceCount}][isVisible]" checked 
                               class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                        <span>Visible to Users</span>
                    </label>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', sourceHtml);
}

function removeEpisodeSource(btn) {
    if (confirm('Remove this source?')) {
        btn.closest('.episode-source-item').remove();
    }
}
</script>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All TV Shows</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Title</th>
                    <th class="text-left p-3">Category</th>
                    <th class="text-left p-3">Year</th>
                    <th class="text-left p-3">Rating</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tv_shows as $show): ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3"><?php echo htmlspecialchars($show['title']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($show['category_name'] ?? 'N/A'); ?></td>
                    <td class="p-3"><?php echo $show['release_year']; ?></td>
                    <td class="p-3"><?php echo number_format($show['rating'], 1); ?></td>
                    <td class="p-3">
                        <a href="?tab=tv-shows&edit=<?php echo $show['id']; ?>&show_episodes=1" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                        <a href="?tab=tv-shows&delete=<?php echo $show['id']; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="text-red-400 hover:text-red-300">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
