<?php
/**
 * Admin Panel - Live TV Channels Management with Multiple Sources
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
$page_title = "Manage Live TV";
$conn = getDBConnection();

$message = '';
$message_type = '';

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM live_tv_channels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=live-tv";</script>';
        exit;
    } else {
        header("Location: ?tab=live-tv");
        exit;
    }
}

// Handle bulk actions
if (isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    
    // Check if "select all in database" was used
    $select_all_db = isset($_POST['select_all_database']) && $_POST['select_all_database'] === '1';
    
    if ($select_all_db) {
        // Get all channel IDs from database (ignoring filters)
        $all_channels_result = $conn->query("SELECT id FROM live_tv_channels");
        $selected_ids = [];
        while ($row = $all_channels_result->fetch_assoc()) {
            $selected_ids[] = intval($row['id']);
        }
    } elseif (isset($_POST['selected_channels']) && is_array($_POST['selected_channels'])) {
    $selected_ids = array_map('intval', $_POST['selected_channels']);
    } else {
        $selected_ids = [];
    }
    
    if (empty($selected_ids)) {
        $message = 'Please select at least one channel';
        $message_type = 'error';
    } else {
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM live_tv_channels WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' channel(s) deleted successfully';
        $message_type = 'success';
    } elseif ($action === 'activate') {
        $stmt = $conn->prepare("UPDATE live_tv_channels SET is_active = 1 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' channel(s) activated successfully';
        $message_type = 'success';
    } elseif ($action === 'deactivate') {
        $stmt = $conn->prepare("UPDATE live_tv_channels SET is_active = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' channel(s) deactivated successfully';
        $message_type = 'success';
    } elseif ($action === 'set_free') {
        $stmt = $conn->prepare("UPDATE live_tv_channels SET is_free = 1, is_premium = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' channel(s) set as free successfully';
        $message_type = 'success';
    } elseif ($action === 'set_premium') {
        $stmt = $conn->prepare("UPDATE live_tv_channels SET is_free = 0, is_premium = 1 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' channel(s) set as premium successfully';
        $message_type = 'success';
    }
    
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=live-tv";</script>';
        exit;
    } else {
        header("Location: ?tab=live-tv");
        exit;
        }
    }
}

// Handle add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['bulk_action'])) {
    $id = $_POST['id'] ?? null;
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
    $content_type = $_POST['content_type'] ?? 'free'; // 'free' or 'premium'
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
            // Use channel name to build a friendly logo filename (no spaces, safe chars)
            $base_name = function_exists('generateChannelLogoBaseName')
                ? generateChannelLogoBaseName($name)
                : preg_replace('/[^a-z0-9]/', '', strtolower(trim($name)));
            if ($base_name === '' || $base_name === null) {
                $base_name = 'channel_logo_' . time();
            }

            $file_name = $base_name . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $file_path)) {
                // Delete old logo if exists (for updates)
                if ($id) {
                    $old_channel = getChannelById($conn, $id);
                    if ($old_channel && !empty($old_channel['logo']) && strpos($old_channel['logo'], 'uploads/tv-logos/') !== false) {
                        $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $old_channel['logo']);
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
                // Ensure type is properly captured - don't default to 'embed' if type is provided
                $source_type = !empty($source['type']) ? sanitize($source['type']) : 'embed';
                
                // For embed / html-embed / iframe-only types, keep full HTML (no strip_tags/htmlspecialchars)
                if (in_array($source_type, ['embed', 'html-embed', 'iframe-only'], true)) {
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
    
    // Extract first source URL for stream_url field (optional - can be NULL if no sources)
    // If no sources provided, set to NULL (database column should allow NULL)
    $stream_url = null;
    if (!empty($sources) && is_array($sources)) {
        $first_source = reset($sources);
        if (isset($first_source['url']) && !empty($first_source['url'])) {
            $stream_url = $first_source['url'];
        }
    }
    
    // Convert NULL to empty string for bind_param (MySQLi doesn't handle NULL well with 's' type)
    // If database allows NULL, we can use empty string as placeholder
    $stream_url_for_db = ($stream_url === null) ? '' : $stream_url;
    
    // Generate slug (use provided slug or auto-generate)
    $slug = !empty($_POST['slug']) ? sanitize($_POST['slug']) : getUniqueSlug($conn, 'live_tv_channels', $name, $id);
    // Ensure slug is valid
    $slug = generateSlug($slug);
    // Make sure it's unique
    $slug = getUniqueSlug($conn, 'live_tv_channels', $slug, $id);
    
    if ($id) {
        // Update
        $stmt = $conn->prepare("UPDATE live_tv_channels SET name=?, description=?, logo=?, stream_url=?, category=?, country=?, language=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, slug=?, sources=? WHERE id=?");
        if ($stmt) {
            $stmt->bind_param("sssssssiiisssi", $name, $description, $logo, $stream_url_for_db, $category, $country, $language, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $slug, $sourcesJson, $id);
            if ($stmt->execute()) {
                $message = 'Channel updated successfully';
            } else {
                $message = 'Error updating channel: ' . $stmt->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Error preparing statement: ' . $conn->error;
            $message_type = 'error';
        }
    } else {
        // Insert - stream_url can be empty if no sources provided
        $stmt = $conn->prepare("INSERT INTO live_tv_channels (name, description, logo, stream_url, category, country, language, featured, is_active, is_free, is_premium, show_in_slider, slug, sources) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssssiiissss", $name, $description, $logo, $stream_url_for_db, $category, $country, $language, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $slug, $sourcesJson);
            if ($stmt->execute()) {
                $message = 'Channel added successfully';
            } else {
                $message = 'Error adding channel: ' . $stmt->error;
                $message_type = 'error';
            }
        } else {
            $message = 'Error preparing statement: ' . $conn->error;
            $message_type = 'error';
        }
    }
    if ($message_type !== 'error') {
        $message_type = 'success';
    }
    if ($message_type === 'success') {
        // Use JavaScript redirect if headers already sent (when included)
        if (headers_sent()) {
            echo '<script>window.location.href = "?tab=live-tv";</script>';
            exit;
        } else {
            header("Location: ?tab=live-tv");
            exit;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';

// Pagination parameters
$per_page = 50; // Show 50 channels per page
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Build WHERE clause for filters
$where_clause = "WHERE 1=1";
$filter_params = [];
$filter_types = '';

if (!empty($search)) {
    $where_clause .= " AND (name LIKE ? OR description LIKE ?)";
    $search_param = '%' . $search . '%';
    $filter_params[] = $search_param;
    $filter_params[] = $search_param;
    $filter_types .= 'ss';
}

if (!empty($category_filter)) {
    $where_clause .= " AND category = ?";
    $filter_params[] = $category_filter;
    $filter_types .= 's';
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM live_tv_channels " . $where_clause;
if (!empty($filter_params)) {
    $count_stmt = $conn->prepare($count_query);
    $count_stmt->bind_param($filter_types, ...$filter_params);
    $count_stmt->execute();
    $total_count = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_count = $conn->query($count_query)->fetch_assoc()['total'];
}

$total_pages = ceil($total_count / $per_page);

// Build query with filters and pagination
$query = "SELECT * FROM live_tv_channels " . $where_clause . " ORDER BY featured DESC, name ASC LIMIT ? OFFSET ?";
$params = [];
$types = '';

// Add filter parameters
if (!empty($search)) {
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($category_filter)) {
    $params[] = $category_filter;
    $types .= 's';
}

// Add pagination parameters
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $channels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique categories from existing channels
$categories_result = $conn->query("SELECT DISTINCT category FROM live_tv_channels WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
$available_categories = [];
while ($row = $categories_result->fetch_assoc()) {
    if (!empty($row['category'])) {
        $available_categories[] = $row['category'];
    }
}

// If edit parameter is set, redirect to separate edit page
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=edit-channel&id=' . $edit_id . '";</script>';
        exit;
    } else {
        header("Location: ?tab=edit-channel&id=" . $edit_id);
        exit;
    }
}

$edit_channel = null;
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Live TV Channels</h1>
    <p class="text-gray-400">Add, edit, and manage live TV channels with multiple streaming sources</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Add Channel Button -->
<div class="mb-6">
    <button type="button" onclick="toggleAddForm()" id="add-channel-btn" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded font-semibold">
        <i class="fas fa-plus mr-2"></i>Add TV Channel
    </button>
</div>

<!-- Add Form (Hidden by default) -->
<div class="bg-gray-900 rounded-lg p-6 mb-8" id="add-channel-form" style="display: none;">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold">Add Channel</h2>
        <button type="button" onclick="toggleAddForm()" class="text-gray-400 hover:text-white">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    <form method="POST" action="" id="channel-form" enctype="multipart/form-data" onsubmit="return validateChannelForm()">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Channel Name *</label>
                <input type="text" name="name" value="" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required
                       onchange="updateSlugPreview(this.value)">
                <p class="text-xs text-gray-400 mt-1">Slug will be auto-generated: <span id="slug-preview" class="text-green-400"></span></p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Slug (URL-friendly)</label>
                <input type="text" name="slug" value="" 
                       placeholder="Auto-generated from name"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Leave empty to auto-generate. Example: "ARY News" → "ary-news"</p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Category</label>
                <input type="text" name="category" value="" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" 
                       placeholder="e.g., Movies, News, Sports, Entertainment">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"></textarea>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Logo</label>
                <div class="space-y-2">
                    <input type="file" name="logo_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                    <p class="text-xs text-gray-400">Upload logo (JPG, PNG, GIF, WEBP)</p>
                    <input type="text" name="logo" value="" 
                           placeholder="Or enter logo URL" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm mt-2">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Country</label>
                <input type="text" name="country" value="US" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" placeholder="US">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Language</label>
                <input type="text" name="language" value="en" 
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
                            <input type="radio" name="content_type" value="free" checked 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 mr-2" required>
                            <span>Free Content (Available to all logged-in users)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="content_type" value="premium" 
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 mr-2" required>
                            <span>Premium Content (Requires subscription)</span>
                        </label>
                    </div>
                </div>
                <label class="flex items-center">
                    <input type="checkbox" name="featured" value="1" 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Featured</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="show_in_slider" value="1" 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Show in Homepage Slider</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" checked 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Active</span>
                </label>
            </div>
        </div>
        
        <!-- Multiple Sources Management -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Streaming Sources <span class="text-sm text-gray-400 font-normal">(Optional)</span></h3>
                <button type="button" onclick="addSource()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add Source
                </button>
            </div>
            <div id="sources-container" class="space-y-3">
            </div>
            <p class="text-xs text-gray-400 mt-2">Sources are optional. You can add streaming sources now or add them later. Set priority to 0 for default source (plays first).</p>
        </div>
        
        <div class="flex gap-4">
            <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
                <i class="fas fa-save mr-2"></i>Add Channel
            </button>
            <button type="button" onclick="toggleAddForm()" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                <i class="fas fa-times mr-2"></i>Cancel
            </button>
        </div>
    </form>
</div>

<script>
function toggleAddForm() {
    const form = document.getElementById('add-channel-form');
    const btn = document.getElementById('add-channel-btn');
    
    if (form && btn) {
        if (form.style.display === 'none' || !form.style.display) {
            form.style.display = 'block';
            btn.style.display = 'none';
            // Scroll to form
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            form.style.display = 'none';
            btn.style.display = 'inline-block';
            // Reset form
            const channelForm = document.getElementById('channel-form');
            if (channelForm) {
                channelForm.reset();
            }
            // Clear sources
            const sourcesContainer = document.getElementById('sources-container');
            if (sourcesContainer) {
                sourcesContainer.innerHTML = '';
            }
            sourceCount = 0;
        }
    }
}
</script>

<!-- Channels List -->
<div class="bg-gray-900 rounded-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-4 gap-4">
        <h2 class="text-2xl font-bold">All Channels (<?php echo number_format($total_count); ?>)</h2>
        
        <!-- Search and Filter -->
        <form method="GET" action="" class="flex flex-col md:flex-row gap-2 flex-1 md:max-w-md">
            <input type="hidden" name="tab" value="live-tv">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search channels..." 
                   class="flex-1 bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            <select name="category" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <option value="">All Categories</option>
                <?php foreach ($available_categories as $cat): ?>
                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="bg-netflix-red px-4 py-2 rounded hover:bg-red-700">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if (!empty($search) || !empty($category_filter)): ?>
            <a href="?tab=live-tv" class="bg-gray-700 px-4 py-2 rounded hover:bg-gray-600">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Bulk Actions -->
    <form method="POST" action="" id="bulk-action-form" onsubmit="return confirmBulkAction()" class="mb-4">
        <div class="flex flex-col md:flex-row gap-2 items-start md:items-center mb-3">
            <select name="bulk_action" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
                <option value="">Bulk Actions</option>
                <option value="activate">Activate</option>
                <option value="deactivate">Deactivate</option>
                <option value="set_free">Set as Free</option>
                <option value="set_premium">Set as Premium</option>
                <option value="delete">Delete</option>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">
                Apply
            </button>
        </div>
        
        <!-- Select All in Database Option -->
        <div class="mb-3 p-3 bg-yellow-900 bg-opacity-30 border border-yellow-700 rounded">
            <label class="flex items-center cursor-pointer">
                <input type="checkbox" id="select-all-database" 
                       class="w-4 h-4 text-yellow-500 bg-gray-800 border-gray-700 rounded mr-2"
                       onchange="handleSelectAllDatabase(this)">
                <span class="text-yellow-200 font-semibold">
                    Select ALL channels in database (<?php echo number_format($total_count); ?> total) - This will select all channels regardless of current page or filters
                </span>
            </label>
            <p class="text-xs text-yellow-300 mt-1 ml-6">
                ⚠️ Warning: When enabled, bulk actions will affect ALL channels in the database, not just the ones visible on this page.
            </p>
        </div>
        
        <input type="hidden" name="select_all_database" id="select-all-database-hidden" value="0">
        
        <div class="overflow-x-auto mt-4">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3 w-12">
                        <input type="checkbox" id="select-all-channels" onchange="toggleAllChannels(this)">
                    </th>
                    <th class="text-left p-3">Logo</th>
                    <th class="text-left p-3">Name</th>
                    <th class="text-left p-3">Category</th>
                    <th class="text-left p-3">Sources</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Views</th>
                    <th class="text-left p-3">Live Viewers</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $channel): ?>
                <?php $channel_sources = parseSources($channel['sources'] ?? '[]'); ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3">
                        <input type="checkbox" name="selected_channels[]" value="<?php echo $channel['id']; ?>" 
                               class="channel-checkbox w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded">
                    </td>
                    <td class="p-3">
                        <?php if (!empty($channel['logo'])): ?>
                        <img src="<?php echo htmlspecialchars(assetUrl($channel['logo'])); ?>" alt="<?php echo htmlspecialchars($channel['name']); ?>" 
                             class="w-12 h-12 object-contain bg-gray-800 rounded p-1" 
                             onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2248%22%3E%3Crect fill=%22%23333%22 width=%2248%22 height=%2248%22/%3E%3Ctext fill=%22%23999%22 x=%2212%22 y=%2230%22 font-size=%2214%22%3ETV%3C/text%3E%3C/svg%3E'">
                        <?php else: ?>
                        <div class="w-12 h-12 bg-gray-800 rounded flex items-center justify-center text-gray-500 text-xs">TV</div>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <div class="font-semibold"><?php echo htmlspecialchars($channel['name']); ?></div>
                        <div class="text-xs text-gray-400 mt-1">
                            <?php if ($channel['featured']): ?><span class="text-yellow-400 mr-2">⭐ Featured</span><?php endif; ?>
                            <?php if ($channel['show_in_slider']): ?><span class="text-blue-400">📺 Slider</span><?php endif; ?>
                        </div>
                    </td>
                    <td class="p-3"><?php echo htmlspecialchars($channel['category'] ?? 'N/A'); ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-gray-700 rounded text-xs"><?php echo count($channel_sources); ?> source(s)</span>
                    </td>
                    <td class="p-3">
                        <div class="flex flex-col gap-1">
                            <span class="px-2 py-1 rounded text-xs <?php echo ($channel['is_active'] ?? 1) ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                                <?php echo ($channel['is_active'] ?? 1) ? 'Active' : 'Inactive'; ?>
                            </span>
                            <span class="px-2 py-1 rounded text-xs <?php echo ($channel['is_free'] ?? 1) ? 'bg-blue-900 text-blue-200' : 'bg-purple-900 text-purple-200'; ?>">
                                <?php echo ($channel['is_free'] ?? 1) ? 'Free' : 'Premium'; ?>
                            </span>
                        </div>
                    </td>
                    <td class="p-3">
                        <span class="text-gray-300"><?php echo number_format($channel['views'] ?? 0); ?></span>
                        <span class="text-xs text-gray-500 ml-1">(total)</span>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-green-900 text-green-200 rounded text-sm font-semibold" id="live-viewers-<?php echo $channel['id']; ?>">
                            <i class="fas fa-eye mr-1"></i>
                            <span class="viewer-count"><?php echo getConcurrentViewers($conn, $channel['id']); ?></span>
                        </span>
                    </td>
                    <td class="p-3">
                        <a href="?tab=live-tv&edit=<?php echo $channel['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                        <a href="?tab=live-tv&delete=<?php echo $channel['id']; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="text-red-400 hover:text-red-300">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="mt-6 flex items-center justify-between">
            <div class="text-sm text-gray-400">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_count); ?> of <?php echo number_format($total_count); ?> channels
            </div>
            <div class="flex items-center gap-2">
                <?php
                // Build query string for pagination links
                $query_params = [];
                if (!empty($search)) $query_params['search'] = $search;
                if (!empty($category_filter)) $query_params['category'] = $category_filter;
                $query_params['tab'] = 'live-tv';
                $query_string = http_build_query($query_params);
                ?>
                
                <!-- Previous Button -->
                <?php if ($current_page > 1): ?>
                <a href="?<?php echo $query_string; ?>&page=<?php echo $current_page - 1; ?>" 
                   class="px-4 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white">
                    <i class="fas fa-chevron-left mr-1"></i>Previous
                </a>
                <?php else: ?>
                <span class="px-4 py-2 bg-gray-800 border border-gray-700 rounded text-gray-500 cursor-not-allowed">
                    <i class="fas fa-chevron-left mr-1"></i>Previous
                </span>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <div class="flex items-center gap-1">
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Show first page if not in range
                    if ($start_page > 1): ?>
                        <a href="?<?php echo $query_string; ?>&page=1" 
                           class="px-3 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white">
                            1
                        </a>
                        <?php if ($start_page > 2): ?>
                        <span class="px-2 text-gray-500">...</span>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $current_page): ?>
                        <span class="px-3 py-2 bg-netflix-red border border-red-700 rounded text-white font-semibold">
                            <?php echo $i; ?>
                        </span>
                        <?php else: ?>
                        <a href="?<?php echo $query_string; ?>&page=<?php echo $i; ?>" 
                           class="px-3 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white">
                            <?php echo $i; ?>
                        </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <!-- Show last page if not in range -->
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                        <span class="px-2 text-gray-500">...</span>
                        <?php endif; ?>
                        <a href="?<?php echo $query_string; ?>&page=<?php echo $total_pages; ?>" 
                           class="px-3 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white">
                            <?php echo $total_pages; ?>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Next Button -->
                <?php if ($current_page < $total_pages): ?>
                <a href="?<?php echo $query_string; ?>&page=<?php echo $current_page + 1; ?>" 
                   class="px-4 py-2 bg-gray-800 border border-gray-700 rounded hover:bg-gray-700 text-white">
                    Next<i class="fas fa-chevron-right ml-1"></i>
                </a>
                <?php else: ?>
                <span class="px-4 py-2 bg-gray-800 border border-gray-700 rounded text-gray-500 cursor-not-allowed">
                    Next<i class="fas fa-chevron-right ml-1"></i>
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </form>
    </div>
</div>

<script>
function updateSlugPreview(name) {
    if (!name) return;
    // Simple slug generation (matches PHP generateSlug function)
    let slug = name.toLowerCase().trim();
    slug = slug.replace(/[^a-z0-9-]/g, '-');
    slug = slug.replace(/-+/g, '-');
    slug = slug.replace(/^-|-$/g, '');
    const previewEl = document.getElementById('slug-preview');
    if (previewEl) {
        previewEl.textContent = slug;
    }
}

// Auto-refresh live viewer counts every 5 seconds
setInterval(function() {
    if (document.visibilityState === 'visible') {
        const channelIds = [];
        document.querySelectorAll('[id^="live-viewers-"]').forEach(function(el) {
            const channelId = el.id.replace('live-viewers-', '');
            channelIds.push(channelId);
        });
        
        if (channelIds.length > 0) {
            // Fetch viewer counts via AJAX
            fetch('<?php echo apiUrl('admin/api/get_live_viewers.php'); ?>?channels=' + channelIds.join(','))
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Object.keys(data.viewers).forEach(function(channelId) {
                            const countEl = document.querySelector('#live-viewers-' + channelId + ' .viewer-count');
                            if (countEl) {
                                countEl.textContent = data.viewers[channelId];
                            }
                        });
                    }
                })
                .catch(error => console.error('Error fetching live viewers:', error));
        }
    }
}, 5000);

let sourceCount = 0;

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
                    <input type="number" name="sources[${sourceCount}][priority]" value="0" min="0" 
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

function validateChannelForm() {
    // Sources are optional - channels can be added without sources and sources can be added later
    // No validation needed for sources
    return true;
}

function toggleAllChannels(checkbox) {
    const selectAllDb = document.getElementById('select-all-database');
    if (selectAllDb && selectAllDb.checked) {
        // If "select all database" is checked, uncheck it when toggling page checkboxes
        selectAllDb.checked = false;
        document.getElementById('select-all-database-hidden').value = '0';
    }
    const checkboxes = document.querySelectorAll('.channel-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function handleSelectAllDatabase(checkbox) {
    const hiddenInput = document.getElementById('select-all-database-hidden');
    if (checkbox.checked) {
        hiddenInput.value = '1';
        // Uncheck all page checkboxes
        const checkboxes = document.querySelectorAll('.channel-checkbox');
        checkboxes.forEach(cb => cb.checked = false);
        // Uncheck "select all" checkbox
        const selectAllCheckbox = document.getElementById('select-all-channels');
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = false;
        }
    } else {
        hiddenInput.value = '0';
    }
}

function confirmBulkAction() {
    const selectAllDb = document.getElementById('select-all-database');
    const selectAllDbChecked = selectAllDb && selectAllDb.checked;
    const selected = document.querySelectorAll('.channel-checkbox:checked');
    const action = document.querySelector('[name="bulk_action"]').value;
    
    if (!selectAllDbChecked && selected.length === 0) {
        alert('Please select at least one channel or enable "Select ALL channels in database"');
        return false;
    }
    
    if (!action) {
        alert('Please select a bulk action');
        return false;
    }
    
    const actionText = {
        'delete': 'delete',
        'activate': 'activate',
        'deactivate': 'deactivate',
        'set_free': 'set as free',
        'set_premium': 'set as premium'
    }[action] || action;
    
    let confirmMessage = '';
    if (selectAllDbChecked) {
        const totalCount = <?php echo $total_count; ?>;
        confirmMessage = `⚠️ WARNING: You have selected "Select ALL channels in database".\n\nAre you sure you want to ${actionText} ALL ${totalCount.toLocaleString()} channel(s) in the database?\n\nThis action cannot be undone!`;
    } else {
        confirmMessage = `Are you sure you want to ${actionText} ${selected.length} channel(s)?`;
    }
    
    return confirm(confirmMessage);
}
</script>
