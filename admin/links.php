<?php
/**
 * Admin Panel - Manage Stream Links
 */
$page_title = "Manage Links";

$content_type = $_GET['type'] ?? 'movie';
$content_id = intval($_GET['id'] ?? 0);

if ($content_type === 'movie' && $content_id) {
    $content = getMovieById($conn, $content_id);
    $content_title = $content['title'] ?? 'Unknown';
} elseif ($content_type === 'tv_show' && $content_id) {
    $content = getTVShowById($conn, $content_id);
    $content_title = $content['title'] ?? 'Unknown';
} else {
    $content = null;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $content) {
    $sources = parseSources($content['sources'] ?? '[]');
    
    if (isset($_POST['add_source'])) {
        $new_source = [
            'id' => 'src_' . time() . '_' . uniqid(),
            'label' => sanitize($_POST['source_label'] ?? ''),
            'url' => sanitize($_POST['source_url'] ?? ''),
            'type' => sanitize($_POST['source_type'] ?? 'embed'),
            'quality' => sanitize($_POST['source_quality'] ?? 'Auto'),
            'language' => sanitize($_POST['source_language'] ?? 'English'),
            'priority' => intval($_POST['source_priority'] ?? 0),
            'isActive' => isset($_POST['source_is_active']),
            'isVisible' => isset($_POST['source_is_visible'])
        ];
        $sources[] = $new_source;
        
        $sourcesJson = encodeSources($sources);
        $table = $content_type === 'movie' ? 'movies' : 'tv_shows';
        $stmt = $conn->prepare("UPDATE $table SET sources=? WHERE id=?");
        $stmt->bind_param("si", $sourcesJson, $content_id);
        $stmt->execute();
        $message = 'Source added successfully';
        $message_type = 'success';
    }
    
    if (isset($_POST['delete_source'])) {
        $source_id = $_POST['source_id'] ?? '';
        $sources = array_filter($sources, function($s) use ($source_id) {
            return ($s['id'] ?? '') !== $source_id;
        });
        
        $sourcesJson = encodeSources(array_values($sources));
        $table = $content_type === 'movie' ? 'movies' : 'tv_shows';
        $stmt = $conn->prepare("UPDATE $table SET sources=? WHERE id=?");
        $stmt->bind_param("si", $sourcesJson, $content_id);
        $stmt->execute();
        $message = 'Source deleted successfully';
        $message_type = 'success';
    }
}

if ($content) {
    $sources = parseSources($content['sources'] ?? '[]');
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Stream Links</h1>
    <p class="text-gray-400">Add and manage streaming sources for content</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if (!$content): ?>
<div class="bg-gray-900 rounded-lg p-6">
    <p class="text-gray-400">Please select a movie or TV show to manage links.</p>
    <div class="mt-4">
        <a href="?tab=movies" class="text-netflix-red hover:underline">Browse Movies</a> |
        <a href="?tab=tv-shows" class="text-netflix-red hover:underline">Browse TV Shows</a>
    </div>
</div>
<?php else: ?>
<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4">Manage Sources: <?php echo htmlspecialchars($content_title); ?></h2>
    
    <h3 class="text-xl font-semibold mb-3">Add New Source</h3>
    <form method="POST" action="">
        <input type="hidden" name="add_source" value="1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Source Label *</label>
                <input type="text" name="source_label" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Source Type</label>
                <select name="source_type" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="embed">Embed</option>
                    <option value="m3u8">M3U8/HLS</option>
                    <option value="youtube">YouTube</option>
                    <option value="direct">Direct</option>
                </select>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Stream URL *</label>
            <input type="text" name="source_url" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Quality</label>
                <select name="source_quality" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="Auto">Auto</option>
                    <option value="SD">SD</option>
                    <option value="HD">HD</option>
                    <option value="FHD">FHD</option>
                    <option value="4K">4K</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Language</label>
                <input type="text" name="source_language" value="English" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Priority (0 = default)</label>
                <input type="number" name="source_priority" value="0" min="0" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        <div class="mb-4 flex gap-4">
            <label class="flex items-center">
                <input type="checkbox" name="source_is_active" checked 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
            <label class="flex items-center">
                <input type="checkbox" name="source_is_visible" checked 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Visible</span>
            </label>
        </div>
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            Add Source
        </button>
    </form>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h3 class="text-xl font-semibold mb-4">Existing Sources (<?php echo count($sources); ?>)</h3>
    <?php if (empty($sources)): ?>
    <p class="text-gray-400">No sources added yet.</p>
    <?php else: ?>
    <div class="space-y-3">
        <?php foreach ($sources as $source): ?>
        <div class="bg-gray-800 rounded-lg p-4 flex items-center justify-between">
            <div>
                <h4 class="font-semibold"><?php echo htmlspecialchars($source['label'] ?? 'Source'); ?></h4>
                <p class="text-sm text-gray-400"><?php echo htmlspecialchars($source['url'] ?? ''); ?></p>
                <div class="flex gap-2 mt-2">
                    <span class="px-2 py-1 bg-gray-700 rounded text-xs"><?php echo htmlspecialchars($source['type'] ?? 'embed'); ?></span>
                    <span class="px-2 py-1 bg-gray-700 rounded text-xs"><?php echo htmlspecialchars($source['quality'] ?? 'Auto'); ?></span>
                    <?php if (($source['priority'] ?? 0) === 0): ?>
                    <span class="px-2 py-1 bg-yellow-900 rounded text-xs">Default</span>
                    <?php endif; ?>
                </div>
            </div>
            <form method="POST" action="" class="inline">
                <input type="hidden" name="delete_source" value="1">
                <input type="hidden" name="source_id" value="<?php echo htmlspecialchars($source['id'] ?? ''); ?>">
                <button type="submit" onclick="return confirm('Delete this source?')" 
                        class="text-red-400 hover:text-red-300">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
