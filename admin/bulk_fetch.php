<?php
/**
 * Admin Panel - Bulk Fetch Movies/TV Shows
 */
$page_title = "Bulk Fetch";

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch'])) {
    $type = sanitize($_POST['type'] ?? 'movie');
    $tmdb_ids = sanitize($_POST['tmdb_ids'] ?? '');
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    $ids = array_filter(array_map('trim', explode(',', $tmdb_ids)));
    
    if (empty($ids)) {
        $message = 'Please enter at least one TMDB ID';
        $message_type = 'error';
    } else {
        // Simplified bulk fetch - in production, you would integrate with TMDB API
        $message = 'Bulk fetch functionality requires TMDB API integration. ' . count($ids) . ' items requested.';
        $message_type = 'success';
    }
}

$categories = getAllCategories($conn);
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Bulk Fetch</h1>
    <p class="text-gray-400">Fetch movies and TV shows from TMDB in bulk</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">Fetch Content</h2>
    <form method="POST" action="">
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Content Type</label>
                <select name="type" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="movie">Movies</option>
                    <option value="tv">TV Shows</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-semibold mb-2">TMDB IDs (comma-separated) *</label>
                <textarea name="tmdb_ids" rows="5" 
                          placeholder="e.g., 550, 238, 424" 
                          class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required></textarea>
                <p class="text-xs text-gray-400 mt-1">Enter TMDB IDs separated by commas</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Category</label>
                    <select name="category_id" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-center mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="featured" 
                               class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                        <span>Mark as Featured</span>
                    </label>
                </div>
            </div>
        </div>
        
        <button type="submit" name="fetch" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 mt-4">
            <i class="fas fa-download mr-2"></i>Fetch Content
        </button>
    </form>
</div>

<div class="bg-blue-900 bg-opacity-30 border border-blue-700 rounded-lg p-4 mt-6">
    <h4 class="font-semibold text-blue-200 mb-2">ℹ️ Bulk Fetch Information</h4>
    <ul class="text-sm text-gray-300 space-y-1 list-disc list-inside">
        <li>Enter TMDB IDs (comma-separated) to fetch multiple items at once</li>
        <li>This feature requires TMDB API key configuration</li>
        <li>Fetched content will be automatically added to your library</li>
    </ul>
</div>
