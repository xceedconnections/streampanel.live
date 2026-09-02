<?php
/**
 * Admin Panel - Enhanced Sliders Management
 * Supports multiple slides per slider, display page selection, and auto-rotate
 */
$page_title = "Manage Sliders";

$message = '';
$message_type = '';

// Initialize database schema
try {
    // Check and add new columns to sliders table
    $check_columns = $conn->query("SHOW COLUMNS FROM sliders LIKE 'display_on_home'");
    if ($check_columns->num_rows == 0) {
        @$conn->query("ALTER TABLE sliders ADD COLUMN display_on_home BOOLEAN DEFAULT FALSE");
        @$conn->query("ALTER TABLE sliders ADD COLUMN display_on_movies BOOLEAN DEFAULT FALSE");
        @$conn->query("ALTER TABLE sliders ADD COLUMN display_on_tv_shows BOOLEAN DEFAULT FALSE");
        @$conn->query("ALTER TABLE sliders ADD COLUMN display_on_live_tv BOOLEAN DEFAULT FALSE");
        @$conn->query("ALTER TABLE sliders ADD COLUMN auto_rotate BOOLEAN DEFAULT TRUE");
        @$conn->query("ALTER TABLE sliders ADD COLUMN rotate_interval INT DEFAULT 5000");
    }
    
    // Create slider_slides table if it doesn't exist
    @$conn->query("CREATE TABLE IF NOT EXISTS slider_slides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slider_id INT NOT NULL,
        title VARCHAR(255),
        description TEXT,
        image_url VARCHAR(500) NOT NULL,
        link_type ENUM('movie', 'tv_show', 'live_tv', 'external') DEFAULT 'external',
        link_id INT NULL,
        link_url VARCHAR(500),
        display_order INT DEFAULT 0,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (slider_id) REFERENCES sliders(id) ON DELETE CASCADE,
        INDEX idx_slider_id (slider_id),
        INDEX idx_display_order (display_order)
    )");
} catch (Exception $e) {
    error_log("Slider schema update error: " . $e->getMessage());
}

// Handle slider deletion
if (isset($_GET['delete_slider'])) {
    $id = intval($_GET['delete_slider']);
    $stmt = $conn->prepare("DELETE FROM sliders WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'Slider deleted successfully';
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=sliders";</script>';
        exit;
    } else {
    header("Location: ?tab=sliders");
    exit;
    }
}

// Handle slide deletion
if (isset($_GET['delete_slide'])) {
    $id = intval($_GET['delete_slide']);
    $stmt = $conn->prepare("DELETE FROM slider_slides WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'Slide deleted successfully';
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=sliders' . (isset($_GET['slider_id']) ? '&slider_id=' . intval($_GET['slider_id']) : '') . '";</script>';
        exit;
    } else {
        header("Location: ?tab=sliders" . (isset($_GET['slider_id']) ? '&slider_id=' . intval($_GET['slider_id']) : ''));
        exit;
    }
}

// Handle slider save/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slider'])) {
    $id = $_POST['id'] ?? null;
    $title = sanitize($_POST['title'] ?? '');
    $display_order = intval($_POST['display_order'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $display_on_home = isset($_POST['display_on_home']) ? 1 : 0;
    $display_on_movies = isset($_POST['display_on_movies']) ? 1 : 0;
    $display_on_tv_shows = isset($_POST['display_on_tv_shows']) ? 1 : 0;
    $display_on_live_tv = isset($_POST['display_on_live_tv']) ? 1 : 0;
    $auto_rotate = isset($_POST['auto_rotate']) ? 1 : 0;
    $rotate_interval = intval($_POST['rotate_interval'] ?? 5000);
    
    if ($id) {
        $stmt = $conn->prepare("UPDATE sliders SET title=?, display_order=?, is_active=?, display_on_home=?, display_on_movies=?, display_on_tv_shows=?, display_on_live_tv=?, auto_rotate=?, rotate_interval=? WHERE id=?");
        $stmt->bind_param("siiiiiiiii", $title, $display_order, $is_active, $display_on_home, $display_on_movies, $display_on_tv_shows, $display_on_live_tv, $auto_rotate, $rotate_interval, $id);
        $stmt->execute();
        $message = 'Slider updated successfully';
    } else {
        $stmt = $conn->prepare("INSERT INTO sliders (title, display_order, is_active, display_on_home, display_on_movies, display_on_tv_shows, display_on_live_tv, auto_rotate, rotate_interval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("siiiiiiii", $title, $display_order, $is_active, $display_on_home, $display_on_movies, $display_on_tv_shows, $display_on_live_tv, $auto_rotate, $rotate_interval);
        $stmt->execute();
        $id = $conn->insert_id;
        $message = 'Slider added successfully';
    }
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=sliders&slider_id=' . $id . '";</script>';
        exit;
    } else {
        header("Location: ?tab=sliders&slider_id=" . $id);
        exit;
    }
}

// Handle slide save/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_slide'])) {
    $id = $_POST['slide_id'] ?? null;
    $slider_id = intval($_POST['slider_id']);
    $title = sanitize($_POST['slide_title'] ?? '');
    $description = sanitize($_POST['slide_description'] ?? '');
    $image_url = sanitize($_POST['slide_image_url'] ?? '');
    $link_type = sanitize($_POST['slide_link_type'] ?? 'external');
    $link_id = !empty($_POST['slide_link_id']) ? intval($_POST['slide_link_id']) : null;
    $link_url = sanitize($_POST['slide_link_url'] ?? '');
    $display_order = intval($_POST['slide_display_order'] ?? 0);
    $is_active = isset($_POST['slide_is_active']) ? 1 : 0;
    
    // Handle image upload
    if (isset($_FILES['slide_image_file']) && $_FILES['slide_image_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/sliders/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['slide_image_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_file_size = 5 * 1024 * 1024; // 5MB
        
        if ($_FILES['slide_image_file']['size'] > $max_file_size) {
            $message = 'File size exceeds 5MB limit';
            $message_type = 'error';
        } elseif (in_array($file_extension, $allowed_extensions)) {
            $file_name = 'slider_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['slide_image_file']['tmp_name'], $file_path)) {
                // Verify file was actually saved
                if (file_exists($file_path)) {
                    // Delete old image if exists (for updates)
                    if ($id) {
                        $old_slide = $conn->prepare("SELECT image_url FROM slider_slides WHERE id = ?");
                        $old_slide->bind_param("i", $id);
                        $old_slide->execute();
                        $old_slide_result = $old_slide->get_result()->fetch_assoc();
                        if ($old_slide_result && !empty($old_slide_result['image_url']) && strpos($old_slide_result['image_url'], 'uploads/sliders/') !== false) {
                            // Clean up old file path - handle both BASE_URL formats
                            $old_url = $old_slide_result['image_url'];
                            $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $old_url);
                            $old_file_path = str_replace('/admin', '', $old_file_path);
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                    }
                    // Ensure BASE_URL doesn't include /admin
                    $base_url = str_replace('/admin', '', BASE_URL);
                    $image_url = rtrim($base_url, '/') . '/uploads/sliders/' . $file_name;
                } else {
                    $message = 'File upload failed - file not found after upload';
                    $message_type = 'error';
                }
            } else {
                $message = 'Failed to upload image. Error: ' . ($_FILES['slide_image_file']['error'] ?? 'Unknown');
                $message_type = 'error';
            }
        } else {
            $message = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP';
            $message_type = 'error';
        }
    }

    // Auto-fill from linked content when fields are empty
    if ($message_type !== 'error' && $link_type === 'movie' && $link_id) {
        require_once __DIR__ . '/../includes/movie_helpers.php';
        $movie = getMovieById($conn, $link_id);
        if ($movie) {
            if ($title === '') {
                $title = $movie['title'] ?? '';
            }
            if ($description === '') {
                $description = $movie['description'] ?? '';
            }
            if ($image_url === '') {
                $image_url = movieBackdropUrl($movie);
                if ($image_url === '') {
                    $image_url = moviePosterUrl($movie);
                }
            }
        }
    } elseif ($message_type !== 'error' && $link_type === 'tv_show' && $link_id) {
        $show = getTVShowById($conn, $link_id);
        if ($show) {
            if ($title === '') {
                $title = $show['title'] ?? '';
            }
            if ($description === '' && !empty($show['description'])) {
                $description = $show['description'];
            }
            if ($image_url === '') {
                $image_url = $show['poster'] ?? ($show['thumbnail'] ?? '');
            }
        }
    } elseif ($message_type !== 'error' && $link_type === 'live_tv' && $link_id) {
        $channel = getChannelById($conn, $link_id);
        if ($channel) {
            if ($title === '') {
                $title = $channel['name'] ?? '';
            }
            if ($description === '' && !empty($channel['description'])) {
                $description = $channel['description'];
            }
            if ($image_url === '') {
                $image_url = $channel['logo'] ?? '';
            }
        }
    }

    // Image required only when we still have nothing (movies can use TMDB backdrop)
    if ($message_type !== 'error' && $image_url === '') {
        if ($id) {
            $existing_slide = $conn->prepare("SELECT image_url FROM slider_slides WHERE id = ?");
            $existing_slide->bind_param("i", $id);
            $existing_slide->execute();
            $existing_result = $existing_slide->get_result()->fetch_assoc();
            if ($existing_result && !empty($existing_result['image_url'])) {
                $image_url = $existing_result['image_url'];
            }
        }
        if ($image_url === '') {
            $message = 'Please provide an image, or link a Movie/TV/Live item that has a poster/banner';
            $message_type = 'error';
        }
    }
    
    // Only proceed if no error occurred
    if ($message_type !== 'error') {
        if ($id) {
            $stmt = $conn->prepare("UPDATE slider_slides SET title=?, description=?, image_url=?, link_type=?, link_id=?, link_url=?, display_order=?, is_active=? WHERE id=?");
            $stmt->bind_param("sssssiiii", $title, $description, $image_url, $link_type, $link_id, $link_url, $display_order, $is_active, $id);
            $stmt->execute();
            $message = 'Slide updated successfully';
        } else {
            $stmt = $conn->prepare("INSERT INTO slider_slides (slider_id, title, description, image_url, link_type, link_id, link_url, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssii", $slider_id, $title, $description, $image_url, $link_type, $link_id, $link_url, $display_order, $is_active);
            $stmt->execute();
            $message = 'Slide added successfully';
        }
        $message_type = 'success';
    }
    
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=sliders&slider_id=' . $slider_id . '";</script>';
        exit;
    } else {
        header("Location: ?tab=sliders&slider_id=" . $slider_id);
    exit;
    }
}

// Get all sliders
$sliders = $conn->query("SELECT s.*, 
    (SELECT COUNT(*) FROM slider_slides WHERE slider_id = s.id) as slide_count
    FROM sliders s 
    ORDER BY s.display_order ASC, s.created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get current slider and its slides
$current_slider = null;
$current_slides = [];
$slider_id = isset($_GET['slider_id']) ? intval($_GET['slider_id']) : null;

// If edit_slider is set but slider_id is not, use edit_slider as slider_id
$edit_slider_id = isset($_GET['edit_slider']) ? intval($_GET['edit_slider']) : null;
if ($edit_slider_id && !$slider_id) {
    $slider_id = $edit_slider_id;
}

if ($slider_id) {
    $stmt = $conn->prepare("SELECT * FROM sliders WHERE id = ?");
    $stmt->bind_param("i", $slider_id);
    $stmt->execute();
    $current_slider = $stmt->get_result()->fetch_assoc();
    
    if ($current_slider) {
        $stmt = $conn->prepare("SELECT * FROM slider_slides WHERE slider_id = ? ORDER BY display_order ASC, created_at ASC");
        $stmt->bind_param("i", $slider_id);
        $stmt->execute();
        $current_slides = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get edit slide
$edit_slide = null;
if (isset($_GET['edit_slide'])) {
    $slide_id = intval($_GET['edit_slide']);
    $stmt = $conn->prepare("SELECT * FROM slider_slides WHERE id = ?");
    $stmt->bind_param("i", $slide_id);
    $stmt->execute();
    $edit_slide = $stmt->get_result()->fetch_assoc();
    if ($edit_slide) {
        $slider_id = $edit_slide['slider_id'];
    }
}

// Get movies, TV shows, and Live TV channels for dropdowns + autofill
require_once __DIR__ . '/../includes/movie_helpers.php';
$movies_raw = $conn->query("SELECT id, title, description, poster, thumbnail, backdrop FROM movies WHERE COALESCE(is_active, 1) = 1 ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC) ?: [];
$tv_shows_raw = $conn->query("SELECT id, title, description, poster, thumbnail FROM tv_shows WHERE COALESCE(is_active, 1) = 1 ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC) ?: [];
$live_tv_raw = $conn->query("SELECT id, name, description, logo FROM live_tv_channels WHERE COALESCE(is_active, 1) = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC) ?: [];

$movies = [];
foreach ($movies_raw as $m) {
    $img = movieBackdropUrl($m);
    if ($img === '') {
        $img = moviePosterUrl($m);
    }
    $movies[] = [
        'id' => (int) $m['id'],
        'title' => (string) ($m['title'] ?? ''),
        'description' => (string) ($m['description'] ?? ''),
        'image' => $img,
    ];
}
$tv_shows = [];
foreach ($tv_shows_raw as $s) {
    $img = trim((string) ($s['poster'] ?? ''));
    if ($img === '') {
        $img = trim((string) ($s['thumbnail'] ?? ''));
    }
    $tv_shows[] = [
        'id' => (int) $s['id'],
        'title' => (string) ($s['title'] ?? ''),
        'description' => (string) ($s['description'] ?? ''),
        'image' => $img,
    ];
}
$live_tv_channels = [];
foreach ($live_tv_raw as $c) {
    $live_tv_channels[] = [
        'id' => (int) $c['id'],
        'title' => (string) ($c['name'] ?? ''),
        'description' => (string) ($c['description'] ?? ''),
        'image' => (string) ($c['logo'] ?? ''),
    ];
}
$slide_catalog_json = json_encode([
    'movie' => $movies,
    'tv_show' => $tv_shows,
    'live_tv' => $live_tv_channels,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Sliders</h1>
    <p class="text-gray-400">Create sliders with multiple slides. For the homepage trending banner: create a slider, enable <strong>Display on Home</strong>, then add slides (Movie / TV Show / Live TV / Custom URL).</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if (!$current_slider): ?>
<!-- Slider List / Create New Slider -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Create New Slider Form -->
    <div class="lg:col-span-1">
        <div class="bg-gray-900 rounded-lg p-6 mb-6">
            <h2 class="text-2xl font-bold mb-4">Create New Slider</h2>
            <form method="POST" action="">
                <input type="hidden" name="save_slider" value="1">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Slider Name *</label>
                    <input type="text" name="title" required
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                           placeholder="e.g., Featured Movies Slider">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Display Order</label>
                    <input type="number" name="display_order" value="0"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Display On Pages</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="display_on_home" value="1" class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Home</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="display_on_movies" value="1" class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Movies</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="display_on_tv_shows" value="1" class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>TV Shows</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="display_on_live_tv" value="1" class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Live TV</span>
                        </label>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="auto_rotate" value="1" checked class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                        <span>Auto Rotate</span>
                    </label>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Rotate Interval (ms)</label>
                    <input type="number" name="rotate_interval" value="5000" min="1000" step="500"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <p class="text-xs text-gray-400 mt-1">Time between slide changes (default: 5000ms = 5 seconds)</p>
                </div>
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="is_active" value="1" checked class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                        <span>Active</span>
                    </label>
                </div>
                <button type="submit" class="w-full bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
                    Create Slider
                </button>
            </form>
        </div>
    </div>
    
    <!-- Sliders List -->
    <div class="lg:col-span-2">
        <div class="bg-gray-900 rounded-lg p-6">
            <h2 class="text-2xl font-bold mb-4">All Sliders</h2>
            <?php if (empty($sliders)): ?>
            <p class="text-gray-400">No sliders created yet. Create your first slider above.</p>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($sliders as $slider): ?>
                <div class="bg-gray-800 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex-1">
                            <h3 class="font-semibold text-lg mb-2"><?php echo htmlspecialchars($slider['title']); ?></h3>
                            <div class="flex flex-wrap gap-2 mb-2">
                                <?php if ($slider['display_on_home'] ?? false): ?>
                                <span class="px-2 py-1 rounded text-xs bg-blue-900 text-blue-200">Home</span>
                                <?php endif; ?>
                                <?php if ($slider['display_on_movies'] ?? false): ?>
                                <span class="px-2 py-1 rounded text-xs bg-green-900 text-green-200">Movies</span>
                                <?php endif; ?>
                                <?php if ($slider['display_on_tv_shows'] ?? false): ?>
                                <span class="px-2 py-1 rounded text-xs bg-purple-900 text-purple-200">TV Shows</span>
                                <?php endif; ?>
                                <?php if ($slider['display_on_live_tv'] ?? false): ?>
                                <span class="px-2 py-1 rounded text-xs bg-yellow-900 text-yellow-200">Live TV</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-4 text-sm text-gray-400">
                                <span>Slides: <?php echo $slider['slide_count'] ?? 0; ?></span>
                                <span>Order: <?php echo $slider['display_order']; ?></span>
                                <?php if ($slider['auto_rotate'] ?? false): ?>
                                <span>Auto: <?php echo ($slider['rotate_interval'] ?? 5000) / 1000; ?>s</span>
                                <?php endif; ?>
                                <span class="px-2 py-1 rounded text-xs <?php echo $slider['is_active'] ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                                    <?php echo $slider['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <a href="?tab=sliders&slider_id=<?php echo $slider['id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm font-semibold text-center">
                                Manage Slides
                            </a>
                            <a href="?tab=sliders&edit_slider=<?php echo $slider['id']; ?>" 
                               class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded text-sm font-semibold text-center">
                                Edit
                            </a>
                            <a href="?tab=sliders&delete_slider=<?php echo $slider['id']; ?>" 
                               onclick="return confirm('Delete this slider and all its slides?')"
                               class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm font-semibold text-center">
                                Delete
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Manage Slides for Selected Slider -->
<?php
// Get edit_slider_id from URL (already processed above)
$edit_slider_data = null;
if ($edit_slider_id) {
    // Use the slider data we already fetched
    $edit_slider_data = $current_slider;
} else {
    $edit_slider_data = $current_slider;
}
?>

<div class="mb-6">
    <a href="?tab=sliders" class="text-blue-400 hover:text-blue-300 mb-4 inline-block">
        <i class="fas fa-arrow-left mr-2"></i>Back to Sliders
    </a>
    <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($current_slider['title']); ?> - Manage Slides</h2>
</div>

<!-- Edit Slider Settings -->
<?php if ($edit_slider_id): ?>
<div class="bg-gray-900 rounded-lg p-6 mb-6">
    <h3 class="text-xl font-bold mb-4">Edit Slider Settings</h3>
    <form method="POST" action="">
        <input type="hidden" name="save_slider" value="1">
        <input type="hidden" name="id" value="<?php echo $edit_slider_data['id']; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Slider Name *</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($edit_slider_data['title']); ?>" required
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Display Order</label>
                <input type="number" name="display_order" value="<?php echo $edit_slider_data['display_order']; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Display On Pages</label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                <label class="flex items-center">
                    <input type="checkbox" name="display_on_home" value="1" <?php echo ($edit_slider_data['display_on_home'] ?? false) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Home</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="display_on_movies" value="1" <?php echo ($edit_slider_data['display_on_movies'] ?? false) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Movies</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="display_on_tv_shows" value="1" <?php echo ($edit_slider_data['display_on_tv_shows'] ?? false) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>TV Shows</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="display_on_live_tv" value="1" <?php echo ($edit_slider_data['display_on_live_tv'] ?? false) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Live TV</span>
                </label>
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="flex items-center mb-2">
                    <input type="checkbox" name="auto_rotate" value="1" <?php echo ($edit_slider_data['auto_rotate'] ?? true) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Auto Rotate</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Rotate Interval (ms)</label>
                <input type="number" name="rotate_interval" value="<?php echo $edit_slider_data['rotate_interval'] ?? 5000; ?>" min="1000" step="500"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" value="1" <?php echo ($edit_slider_data['is_active'] ?? true) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
        </div>
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
            Update Slider
        </button>
        <a href="?tab=sliders&slider_id=<?php echo $current_slider['id']; ?>" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
    </form>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/slider-slide-form.php'; ?>

<?php endif; ?>
