<?php
/**
 * Admin Panel - Edit Live TV Channel (Separate Page)
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = "Edit Live TV Channel";
$conn = getDBConnection();

$message = '';
$message_type = '';

// Get channel ID
$channel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$channel_id) {
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=live-tv";</script>';
        exit;
    } else {
        header("Location: ?tab=live-tv");
        exit;
    }
}

// Get channel data
$edit_channel = getChannelById($conn, $channel_id);
if (!$edit_channel) {
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=live-tv";</script>';
        exit;
    } else {
        header("Location: ?tab=live-tv");
        exit;
    }
}

$edit_channel['sources'] = parseSources($edit_channel['sources'] ?? '[]');

// Fix logo path if it contains /api (auto-fix on load)
if (!empty($edit_channel['logo']) && strpos($edit_channel['logo'], '/api/') !== false) {
    $fixed_logo = str_replace('/api/', '/', $edit_channel['logo']);
    $fixed_logo = str_replace('/api', '', $fixed_logo);
    // Update in database
    $fix_logo = $conn->prepare("UPDATE live_tv_channels SET logo = ? WHERE id = ?");
    $fix_logo->bind_param("si", $fixed_logo, $channel_id);
    $fix_logo->execute();
    $edit_channel['logo'] = $fixed_logo;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $logo = sanitize($_POST['logo'] ?? '');
    $category = sanitize($_POST['category'] ?? '');
    $country = sanitize($_POST['country'] ?? 'US');
    $language = sanitize($_POST['language'] ?? 'en');
    $featured = isset($_POST['featured']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $show_in_slider = isset($_POST['show_in_slider']) ? 1 : 0;
    
    // Handle free/premium - mutually exclusive (radio button)
    $content_type = $_POST['content_type'] ?? 'free';
    $is_free = ($content_type === 'free') ? 1 : 0;
    $is_premium = ($content_type === 'premium') ? 1 : 0;
    
    // Handle logo upload
    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/tv-logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = 'tv_logo_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $file_path)) {
                // Delete old logo if exists
                if (!empty($edit_channel['logo'])) {
                    $old_rel = normalizeUploadPath($edit_channel['logo']);
                    if ($old_rel !== '') {
                        $old_file_path = dirname(__DIR__) . '/' . $old_rel;
                        if (file_exists($old_file_path)) {
                            @unlink($old_file_path);
                        }
                    }
                }
                $logo = 'uploads/tv-logos/' . $file_name;
            }
        }
    }
    
    $logo = normalizeUploadPath($logo);
    
    // Handle sources - IMPORTANT: Make sure type is properly saved
    // For embed-style types we must NOT strip HTML tags, so full embed code is stored
    $sources = [];
    if (isset($_POST['sources']) && is_array($_POST['sources'])) {
        foreach ($_POST['sources'] as $source) {
            if (!empty($source['url'])) {
                // Ensure type is properly captured - default to 'html-embed' for backward compatibility
                $source_type = !empty($source['type']) ? sanitize($source['type']) : 'html-embed';
                
                // For html-embed type, keep full HTML (no strip_tags/htmlspecialchars)
                // For iframe type, treat as URL (will be loaded directly in iframe)
                if (in_array($source_type, ['html-embed'], true)) {
                    // Also decode any HTML entities that might have been pasted or stored previously
                    $raw_url = html_entity_decode(trim($source['url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                } else {
                    $raw_url = sanitize($source['url'] ?? '');
                }

                $sources[] = [
                    'id' => $source['id'] ?? 'src_' . time() . '_' . uniqid(),
                    'label' => sanitize($source['label'] ?? ''),
                    'url' => $raw_url,
                    'type' => $source_type, // Use the properly captured type
                    'quality' => sanitize($source['quality'] ?? 'Auto'),
                    'language' => sanitize($source['language'] ?? 'English'),
                    'priority' => intval($source['priority'] ?? 0),
                    'isActive' => isset($source['isActive']) ? true : false,
                    'isVisible' => isset($source['isVisible']) ? true : false
                ];
            }
        }
    }
    $sourcesJson = encodeSources($sources);
    
    // Generate slug (use provided slug or auto-generate)
    $slug = !empty($_POST['slug']) ? sanitize($_POST['slug']) : getUniqueSlug($conn, 'live_tv_channels', $name, $channel_id);
    // Ensure slug is valid
    $slug = generateSlug($slug);
    // Make sure it's unique
    $slug = getUniqueSlug($conn, 'live_tv_channels', $slug, $channel_id);
    
    // Handle ad selections
    // Intro ad is controlled globally from ads page, not per-channel
    $pre_roll_ad_id = !empty($_POST['pre_roll_ad_id']) ? intval($_POST['pre_roll_ad_id']) : null;
    $mid_roll_ad_id = !empty($_POST['mid_roll_ad_id']) ? intval($_POST['mid_roll_ad_id']) : null;
    $end_roll_ad_id = !empty($_POST['end_roll_ad_id']) ? intval($_POST['end_roll_ad_id']) : null;
    $loop_ad_id = !empty($_POST['loop_ad_id']) ? intval($_POST['loop_ad_id']) : null;
    // Loop interval is now taken from the ad's duration, so we don't need a separate field
    $loop_interval = null; // Will be fetched from ad duration
    $banner_ad_id = !empty($_POST['banner_ad_id']) ? intval($_POST['banner_ad_id']) : null;
    $popup_ad_id = !empty($_POST['popup_ad_id']) ? intval($_POST['popup_ad_id']) : null;
    
    // Ensure ad columns exist
    try {
        // Intro ad is controlled globally from ads page, not per-channel
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS pre_roll_ad_id INT NULL");
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS mid_roll_ad_id INT NULL");
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS end_roll_ad_id INT NULL");
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS loop_ad_id INT NULL");
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS loop_interval INT NULL");
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS banner_ad_id INT NULL");
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS popup_ad_id INT NULL");
    } catch (Exception $e) {
        // Columns might already exist
    }
    
    // Update channel
    // Note: loop_interval is now taken from the ad's duration, so we set it to NULL
    // Intro ad is controlled globally from ads page, not per-channel - removed intro_ad_id
    $stmt = $conn->prepare("UPDATE live_tv_channels SET name=?, description=?, logo=?, category=?, country=?, language=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, slug=?, sources=?, pre_roll_ad_id=?, mid_roll_ad_id=?, end_roll_ad_id=?, loop_ad_id=?, loop_interval=?, banner_ad_id=?, popup_ad_id=? WHERE id=?");
    if ($stmt) {
        // Type string: 6 strings (name, description, logo, category, country, language) + 5 ints (featured, is_active, is_free, is_premium, show_in_slider) + 2 strings (slug, sources) + 8 ints (pre_roll_ad_id, mid_roll_ad_id, end_roll_ad_id, loop_ad_id, loop_interval, banner_ad_id, popup_ad_id, channel_id) = 21 total
        $stmt->bind_param("ssssssiiiiissiiiiiiii", $name, $description, $logo, $category, $country, $language, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $slug, $sourcesJson, $pre_roll_ad_id, $mid_roll_ad_id, $end_roll_ad_id, $loop_ad_id, $loop_interval, $banner_ad_id, $popup_ad_id, $channel_id);
        if ($stmt->execute()) {
            $message = 'Channel updated successfully';
            $message_type = 'success';
            
            // Refresh channel data
            $edit_channel = getChannelById($conn, $channel_id);
            $edit_channel['sources'] = parseSources($edit_channel['sources'] ?? '[]');
        } else {
            $message = 'Error updating channel: ' . $stmt->error;
            $message_type = 'error';
        }
    } else {
        $message = 'Error preparing statement: ' . $conn->error;
        $message_type = 'error';
    }
}
?>

<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-4xl font-bold mb-2">Edit Live TV Channel</h1>
            <p class="text-gray-400">Edit channel: <?php echo htmlspecialchars($edit_channel['name']); ?></p>
        </div>
        <a href="?tab=live-tv" class="bg-gray-700 hover:bg-gray-600 px-6 py-2 rounded">
            <i class="fas fa-arrow-left mr-2"></i>Back to Channels
        </a>
    </div>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Edit Form -->
<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4">Channel Details</h2>
    <form method="POST" action="" id="channel-form" enctype="multipart/form-data" onsubmit="return validateChannelForm()">
        <input type="hidden" name="id" value="<?php echo $edit_channel['id']; ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Channel Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($edit_channel['name'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required
                       onchange="updateSlugPreview(this.value)">
                <p class="text-xs text-gray-400 mt-1">Slug will be auto-generated: <span id="slug-preview" class="text-green-400"><?php echo htmlspecialchars($edit_channel['slug'] ?? generateSlug($edit_channel['name'] ?? '')); ?></span></p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Slug (URL-friendly)</label>
                <input type="text" name="slug" value="<?php echo htmlspecialchars($edit_channel['slug'] ?? ''); ?>" 
                       placeholder="Auto-generated from name"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Leave empty to auto-generate. Example: "ARY News" &#8594; "ary-news"</p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Category</label>
                <input type="text" name="category" value="<?php echo htmlspecialchars($edit_channel['category'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" 
                       placeholder="e.g., Movies, News, Sports, Entertainment">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_channel['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Logo</label>
                <div class="space-y-2">
                    <input type="file" name="logo_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                    <p class="text-xs text-gray-400">Upload logo (JPG, PNG, GIF, WEBP)</p>
                    <?php if (!empty($edit_channel['logo'])): 
                        // Fix logo path if it contains /api
                        $logo_display = $edit_channel['logo'];
                        if (strpos($logo_display, '/api/') !== false) {
                            $logo_display = str_replace('/api/', '/', $logo_display);
                            $logo_display = str_replace('/api', '', $logo_display);
                            // Update in database if incorrect
                            $fix_logo = $conn->prepare("UPDATE live_tv_channels SET logo = ? WHERE id = ?");
                            $fix_logo->bind_param("si", $logo_display, $edit_channel['id']);
                            $fix_logo->execute();
                            $edit_channel['logo'] = $logo_display; // Update for form field too
                        }
                    ?>
                    <div class="mt-2">
                        <img src="<?php echo htmlspecialchars($logo_display); ?>" alt="Current Logo" 
                             class="max-w-24 max-h-24 object-contain bg-gray-800 rounded p-2" 
                             onerror="this.style.display='none'">
                    </div>
                    <?php endif; ?>
                    <input type="text" name="logo" value="<?php echo htmlspecialchars($edit_channel['logo'] ?? ''); ?>" 
                           placeholder="Or enter logo URL" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm mt-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Country</label>
                <input type="text" name="country" value="<?php echo htmlspecialchars($edit_channel['country'] ?? 'US'); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" placeholder="US">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Language</label>
                <input type="text" name="language" value="<?php echo htmlspecialchars($edit_channel['language'] ?? 'en'); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" placeholder="en">
            </div>
        </div>
        
        <!-- Content Access Settings -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Content Access Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Content Type *</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="radio" name="content_type" value="free" <?php echo (($edit_channel['is_premium'] ?? 0) == 0) ? 'checked' : ''; ?> 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 mr-2" required>
                            <span>Free Content (Available to all logged-in users)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="content_type" value="premium" <?php echo (($edit_channel['is_premium'] ?? 0) == 1) ? 'checked' : ''; ?> 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 mr-2" required>
                            <span>Premium Content (Requires subscription)</span>
                        </label>
                    </div>
                </div>
                <label class="flex items-center">
                    <input type="checkbox" name="featured" value="1" <?php echo ($edit_channel['featured'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Featured</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="show_in_slider" value="1" <?php echo ($edit_channel['show_in_slider'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Show in Homepage Slider</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($edit_channel['is_active'] ?? 1) ? 'checked' : ''; ?> 
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
                <?php if (!empty($edit_channel['sources'])): ?>
                    <?php foreach ($edit_channel['sources'] as $index => $source): ?>
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
                                            <option value="html-embed" <?php echo ($source['type'] ?? '') === 'html-embed' ? 'selected' : ''; ?>>HTML Embed Code</option>
                                            <option value="iframe" <?php echo ($source['type'] ?? '') === 'iframe' || ($source['type'] ?? '') === 'iframe-only' || (($source['type'] ?? '') === 'embed' && !empty($source['type'] ?? '')) ? 'selected' : ''; ?>>Iframe Legacy</option>
                                            <option value="open-window" <?php echo ($source['type'] ?? '') === 'open-window' ? 'selected' : ''; ?>>Open in New Window</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold mb-1">Stream URL / Embed Code *</label>
                                    <textarea name="sources[<?php echo $index; ?>][url]" rows="4"
                                              class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                                              placeholder="https://... or full HTML embed code" required><?php echo htmlspecialchars($source['url'] ?? '', ENT_NOQUOTES, 'UTF-8'); ?></textarea>
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
                                    <input type="number" name="sources[<?php echo $index; ?>][priority]" value="<?php echo $source['priority'] ?? 0; ?>" min="0" 
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
        
        <!-- Ad Settings -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Advertisement Settings</h3>
            <p class="text-sm text-gray-400 mb-4">Select ads to play during live TV. Premium users will not see ads. Intro ads are controlled globally from the Ads page.</p>
            
            <?php
            // Get all active ads (intro ads are controlled globally from ads page, not per-channel)
            $all_ads = $conn->query("SELECT id, name, type, content_type FROM ads WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
            ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Pre-roll Ad (before channel plays)</label>
                    <select name="pre_roll_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="">-- No Ad --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <?php if (in_array($ad['type'], ['pre-roll', 'intro-ad'])): ?>
                            <option value="<?php echo $ad['id']; ?>" <?php echo ($edit_channel['pre_roll_ad_id'] ?? null) == $ad['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ad['name']); ?> (<?php echo htmlspecialchars($ad['type']); ?>)
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Mid-roll Ad</label>
                    <select name="mid_roll_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="">-- No Ad --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <?php if ($ad['type'] === 'mid-roll'): ?>
                            <option value="<?php echo $ad['id']; ?>" <?php echo ($edit_channel['mid_roll_ad_id'] ?? null) == $ad['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ad['name']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">End-roll Ad (after channel stops)</label>
                    <select name="end_roll_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="">-- No Ad --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <?php if ($ad['type'] === 'post-roll'): ?>
                            <option value="<?php echo $ad['id']; ?>" <?php echo ($edit_channel['end_roll_ad_id'] ?? null) == $ad['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ad['name']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Loop Ad (runs every N seconds based on ad duration)</label>
                    <select name="loop_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="">-- No Ad --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <?php if (in_array($ad['type'], ['loop', 'mid-roll', 'banner'])): ?>
                            <option value="<?php echo $ad['id']; ?>" <?php echo ($edit_channel['loop_ad_id'] ?? null) == $ad['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ad['name']); ?> (<?php echo htmlspecialchars($ad['type']); ?>) - Duration: <?php echo $ad['duration'] ?? 30; ?>s
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">The loop ad will play every N seconds based on the ad's duration setting (configured in Ads page)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Banner Ad (displays as overlay during playback)</label>
                    <select name="banner_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="">-- No Ad --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <?php if ($ad['type'] === 'banner'): ?>
                            <option value="<?php echo $ad['id']; ?>" <?php echo ($edit_channel['banner_ad_id'] ?? null) == $ad['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ad['name']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Banner ads display as overlay during playback (non-intrusive)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-semibold mb-2">Popup Ad (displays as popup during playback)</label>
                    <select name="popup_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="">-- No Ad --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <?php if ($ad['type'] === 'popup'): ?>
                            <option value="<?php echo $ad['id']; ?>" <?php echo ($edit_channel['popup_ad_id'] ?? null) == $ad['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ad['name']); ?>
                            </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">Popup ads display as modal overlay during playback</p>
                </div>
            </div>
        </div>
        
        <div class="flex gap-4">
            <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
                <i class="fas fa-save mr-2"></i>Save Changes
            </button>
            <a href="?tab=live-tv" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                <i class="fas fa-times mr-2"></i>Cancel
            </a>
        </div>
    </form>
</div>

<script>
function updateSlugPreview(name) {
    if (!name) return;
    // Simple slug generation (matches PHP generateSlug function)
    let slug = name.toLowerCase().trim();
    slug = slug.replace(/[^a-z0-9-]/g, '-');
    slug = slug.replace(/-+/g, '-');
    slug = slug.replace(/^-|-$/g, '');
    document.getElementById('slug-preview').textContent = slug;
}

let sourceCount = <?php echo !empty($edit_channel['sources']) ? count($edit_channel['sources']) : 0; ?>;

// Calculate maximum priority from existing sources
function getMaxPriority() {
    let maxPriority = -1;
    const priorityInputs = document.querySelectorAll('input[name*="[priority]"]');
    priorityInputs.forEach(input => {
        const priority = parseInt(input.value) || 0;
        if (priority > maxPriority) {
            maxPriority = priority;
        }
    });
    return maxPriority;
}

// Get next available priority (max + 1, or 0 if no sources exist)
function getNextPriority() {
    const maxPriority = getMaxPriority();
    // If no sources exist or max is -1, return 0 for first source
    // Otherwise, return max + 1
    return maxPriority < 0 ? 0 : maxPriority + 1;
}

function addSource() {
    sourceCount++;
    const nextPriority = getNextPriority();
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
                            <option value="html-embed">HTML Embed Code</option>
                            <option value="iframe">Iframe Legacy</option>
                            <option value="open-window">Open in New Window</option>
                        </optgroup>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold mb-1">Stream URL / Embed Code *</label>
                    <textarea name="sources[${sourceCount}][url]" rows="4"
                              class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                              placeholder="https://... or full HTML embed code" required></textarea>
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
                    <input type="number" name="sources[${sourceCount}][priority]" value="${nextPriority}" min="0" 
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
    // Remove the source item immediately without using confirm dialogs,
    // because some browsers can block them and make the delete icon appear broken.
    const item = btn.closest('.source-item');
    if (item) {
        item.remove();
    }
}

function validateChannelForm() {
    const sources = document.querySelectorAll('.source-item');
    // Allow saving a channel even if it has no streaming sources.
    // Previously this showed a confirm dialog which, if blocked by the browser,
    // could prevent the form from submitting and made it seem like streams
    // could not be deleted. Now we simply allow submit and (optionally) rely
    // on server&#8209;side/UX messaging if needed.
    return true;
}
</script>
