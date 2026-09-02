<?php
/**
 * Admin Panel - Enhanced Sliders Management
 * Supports multiple slides per slider, display page selection, and auto-rotate
 */
$page_title = "Manage Sliders";

require_once __DIR__ . '/includes/slider_admin.php';

$message = '';
$message_type = '';
$sliders = [];
$current_slider = null;
$current_slides = [];
$slider_id = isset($_GET['slider_id']) ? intval($_GET['slider_id']) : null;
$edit_slider_id = isset($_GET['edit_slider']) ? intval($_GET['edit_slider']) : null;
$edit_slide = null;
$movies = [];
$tv_shows = [];
$live_tv_channels = [];
$slider_form_action = 'index.php?tab=sliders';

try {
    if (!ensureSliderAdminSchema($conn)) {
        throw new RuntimeException('Could not initialize slider database tables.');
    }

    list($message, $message_type) = sliderAdminTakeFlash();

    if (sliderAdminTableExists($conn, 'slider_slides')) {
        $slidersResult = $conn->query("SELECT s.*, 
            (SELECT COUNT(*) FROM slider_slides WHERE slider_id = s.id) as slide_count
            FROM sliders s 
            ORDER BY s.display_order ASC, s.id DESC");
    } else {
        $slidersResult = $conn->query("SELECT s.*, 0 as slide_count
            FROM sliders s 
            ORDER BY s.display_order ASC, s.id DESC");
    }

    if ($slidersResult) {
        $sliders = $slidersResult->fetch_all(MYSQLI_ASSOC);
    } else {
        throw new RuntimeException('Failed to load sliders: ' . $conn->error);
    }

    if ($edit_slider_id && !$slider_id) {
        $slider_id = $edit_slider_id;
    }

    if ($slider_id) {
        $stmt = $conn->prepare("SELECT * FROM sliders WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $slider_id);
            $stmt->execute();
            $current_slider = $stmt->get_result()->fetch_assoc();
        }

        if ($current_slider && sliderAdminTableExists($conn, 'slider_slides')) {
            $stmt = $conn->prepare("SELECT * FROM slider_slides WHERE slider_id = ? ORDER BY display_order ASC, id ASC");
            if ($stmt) {
                $stmt->bind_param("i", $slider_id);
                $stmt->execute();
                $slideResult = $stmt->get_result();
                $current_slides = $slideResult ? $slideResult->fetch_all(MYSQLI_ASSOC) : [];
            }
        }
    }

    if (isset($_GET['edit_slide']) && sliderAdminTableExists($conn, 'slider_slides')) {
        $slide_id = intval($_GET['edit_slide']);
        $stmt = $conn->prepare("SELECT * FROM slider_slides WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $slide_id);
            $stmt->execute();
            $edit_slide = $stmt->get_result()->fetch_assoc();
            if ($edit_slide) {
                $slider_id = $edit_slide['slider_id'];
            }
        }
    }

    require_once __DIR__ . '/../includes/movies_schema.php';
    ensureMoviesSchema($conn);
    require_once __DIR__ . '/../includes/movie_helpers.php';

    $moviesQuery = $conn->query("SELECT id, title, description, poster, thumbnail, backdrop FROM movies WHERE COALESCE(is_active, 1) = 1 ORDER BY title ASC");
    $movies_raw = $moviesQuery ? $moviesQuery->fetch_all(MYSQLI_ASSOC) : [];
    $tvShowsQuery = $conn->query("SELECT id, title, description, poster, thumbnail FROM tv_shows WHERE COALESCE(is_active, 1) = 1 ORDER BY title ASC");
    $tv_shows_raw = $tvShowsQuery ? $tvShowsQuery->fetch_all(MYSQLI_ASSOC) : [];
    $liveTvQuery = $conn->query("SELECT id, name, description, logo FROM live_tv_channels WHERE COALESCE(is_active, 1) = 1 ORDER BY name ASC");
    $live_tv_raw = $liveTvQuery ? $liveTvQuery->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) {
    error_log('Admin sliders page error: ' . $e->getMessage());
    if ($message === '') {
        $message = $e->getMessage();
        $message_type = 'error';
    }
    $movies_raw = [];
    $tv_shows_raw = [];
    $live_tv_raw = [];
}

$movies = [];
if (!empty($movies_raw) && function_exists('movieBackdropUrl')) {
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
            <form method="POST" action="<?php echo htmlspecialchars($slider_form_action); ?>">
                <input type="hidden" name="tab" value="sliders">
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
    <form method="POST" action="<?php echo htmlspecialchars($slider_form_action . '&slider_id=' . (int) $edit_slider_data['id'] . '&edit_slider=' . (int) $edit_slider_data['id']); ?>">
        <input type="hidden" name="tab" value="sliders">
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

<!-- Slides List -->
<div class="bg-gray-900 rounded-lg p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
        <div>
            <h3 class="text-xl font-bold">Slides (<?php echo count($current_slides); ?>)</h3>
            <p class="text-sm text-gray-400 mt-1">Drag rows to reorder, or set Priority (1 = first on homepage). Lower number shows first.</p>
        </div>
        <?php if (!empty($current_slides)): ?>
        <button type="button" id="saveSlideOrderBtn" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm font-semibold">
            <i class="fas fa-save mr-1"></i> Save Order
        </button>
        <?php endif; ?>
    </div>

    <?php if (isset($_GET['reordered'])): ?>
    <div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-4 text-sm">
        Slide order saved.
    </div>
    <?php endif; ?>

    <?php if (empty($current_slides)): ?>
    <p class="text-gray-400">No slides added yet. Add your first slide above.</p>
    <?php else: ?>
    <form method="POST" action="index.php?tab=sliders&amp;slider_id=<?php echo (int) $current_slider['id']; ?>" id="slideOrderForm">
        <input type="hidden" name="tab" value="sliders">
        <input type="hidden" name="reorder_slides" value="1">
        <input type="hidden" name="slider_id" value="<?php echo (int) $current_slider['id']; ?>">
        <div id="slidesSortable" class="space-y-3">
            <?php foreach ($current_slides as $index => $slide): ?>
            <?php $prio = (int) ($slide['display_order'] ?? ($index + 1)); if ($prio <= 0) $prio = $index + 1; ?>
            <div class="slide-sort-item bg-gray-800 rounded-lg p-3 border border-gray-700 flex flex-col md:flex-row gap-3 md:items-center"
                 draggable="true"
                 data-slide-id="<?php echo (int) $slide['id']; ?>">
                <div class="flex items-center gap-3 flex-shrink-0">
                    <button type="button" class="drag-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-white px-2" title="Drag to reorder" aria-label="Drag">
                        <i class="fas fa-grip-vertical text-lg"></i>
                    </button>
                    <span class="slide-pos-badge bg-netflix-red text-white text-xs font-bold rounded px-2 py-1 min-w-[2rem] text-center">#<?php echo $index + 1; ?></span>
                    <label class="text-xs text-gray-400 flex items-center gap-1">
                        Priority
                        <input type="number" min="1" step="1"
                               name="slide_priorities[<?php echo (int) $slide['id']; ?>]"
                               value="<?php echo $prio; ?>"
                               class="slide-priority-input w-16 bg-gray-900 border border-gray-600 rounded px-2 py-1 text-white text-sm">
                    </label>
                </div>
                <div class="flex items-center gap-3 flex-1 min-w-0">
                    <?php if (!empty($slide['image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($slide['image_url']); ?>" alt=""
                         class="w-20 h-12 object-cover rounded flex-shrink-0 bg-black">
                    <?php else: ?>
                    <div class="w-20 h-12 rounded bg-gray-700 flex-shrink-0"></div>
                    <?php endif; ?>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold truncate"><?php echo htmlspecialchars($slide['title'] ?? 'Untitled Slide'); ?></div>
                        <div class="text-xs text-gray-400">
                            <?php
                            if ($slide['link_type'] === 'movie') echo 'Movie';
                            elseif ($slide['link_type'] === 'tv_show') echo 'TV Show';
                            elseif ($slide['link_type'] === 'live_tv') echo 'Live TV';
                            else echo 'Custom URL';
                            echo $slide['is_active'] ? ' · Active' : ' · Inactive';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3 flex-shrink-0">
                    <a href="?tab=sliders&slider_id=<?php echo (int) $current_slider['id']; ?>&edit_slide=<?php echo (int) $slide['id']; ?>"
                       class="text-blue-400 hover:text-blue-300 text-sm">Edit</a>
                    <a href="?tab=sliders&slider_id=<?php echo (int) $current_slider['id']; ?>&delete_slide=<?php echo (int) $slide['id']; ?>"
                       onclick="return confirm('Delete this slide?')"
                       class="text-red-400 hover:text-red-300 text-sm">Delete</a>
                </div>
                <input type="hidden" class="slide-order-input" name="slide_order[]" value="<?php echo (int) $slide['id']; ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </form>

    <style>
    .slide-sort-item.dragging { opacity: 0.5; border-color: #e50914; }
    .slide-sort-item.drag-over { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.35); }
    </style>
    <script>
    (function () {
        const list = document.getElementById('slidesSortable');
        const form = document.getElementById('slideOrderForm');
        const saveBtn = document.getElementById('saveSlideOrderBtn');
        if (!list || !form) return;

        let dragEl = null;

        function refreshPositions() {
            const items = Array.from(list.querySelectorAll('.slide-sort-item'));
            items.forEach(function (item, idx) {
                const badge = item.querySelector('.slide-pos-badge');
                const prio = item.querySelector('.slide-priority-input');
                const orderInput = item.querySelector('.slide-order-input');
                if (badge) badge.textContent = '#' + (idx + 1);
                if (prio) prio.value = String(idx + 1);
                if (orderInput) orderInput.value = item.getAttribute('data-slide-id');
            });
        }

        function syncOrderFromPriorities() {
            const items = Array.from(list.querySelectorAll('.slide-sort-item'));
            items.sort(function (a, b) {
                const pa = parseInt(a.querySelector('.slide-priority-input').value, 10) || 9999;
                const pb = parseInt(b.querySelector('.slide-priority-input').value, 10) || 9999;
                if (pa !== pb) return pa - pb;
                return 0;
            });
            items.forEach(function (item) { list.appendChild(item); });
            refreshPositions();
        }

        list.querySelectorAll('.slide-sort-item').forEach(function (item) {
            item.addEventListener('dragstart', function (e) {
                dragEl = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                try { e.dataTransfer.setData('text/plain', item.getAttribute('data-slide-id')); } catch (err) {}
            });
            item.addEventListener('dragend', function () {
                item.classList.remove('dragging');
                list.querySelectorAll('.slide-sort-item').forEach(function (el) { el.classList.remove('drag-over'); });
                dragEl = null;
                refreshPositions();
            });
            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                if (!dragEl || dragEl === item) return;
                item.classList.add('drag-over');
                const rect = item.getBoundingClientRect();
                const before = (e.clientY - rect.top) < rect.height / 2;
                if (before) {
                    list.insertBefore(dragEl, item);
                } else {
                    list.insertBefore(dragEl, item.nextSibling);
                }
            });
            item.addEventListener('dragleave', function () {
                item.classList.remove('drag-over');
            });
            item.addEventListener('drop', function (e) {
                e.preventDefault();
                item.classList.remove('drag-over');
                refreshPositions();
            });

            const handle = item.querySelector('.drag-handle');
            if (handle) {
                handle.addEventListener('mousedown', function () { item.setAttribute('draggable', 'true'); });
            }

            const prioInput = item.querySelector('.slide-priority-input');
            if (prioInput) {
                prioInput.addEventListener('change', function () {
                    syncOrderFromPriorities();
                });
                // Don't start drag when editing priority
                prioInput.addEventListener('mousedown', function (e) { e.stopPropagation(); });
                prioInput.addEventListener('focus', function () { item.setAttribute('draggable', 'false'); });
                prioInput.addEventListener('blur', function () { item.setAttribute('draggable', 'true'); });
            }
        });

        function saveOrder(viaAjax) {
            refreshPositions();
            if (!viaAjax) {
                form.submit();
                return;
            }
            const fd = new FormData(form);
            fd.set('ajax', '1');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
            fetch(form.getAttribute('action') || window.location.href, {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  saveBtn.disabled = false;
                  saveBtn.innerHTML = '<i class="fas fa-save mr-1"></i> Save Order';
                  if (data && data.success) {
                      const note = document.createElement('div');
                      note.className = 'bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-4 text-sm';
                      note.textContent = 'Slide order saved.';
                      form.parentNode.insertBefore(note, form);
                      setTimeout(function () { note.remove(); }, 2500);
                  } else {
                      alert('Could not save order');
                  }
              }).catch(function () {
                  // Fallback to normal form submit
                  form.submit();
              });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function () { saveOrder(true); });
        }
    })();
    </script>
    <?php endif; ?>
</div>

<?php endif; ?>
