<?php
/**
 * Admin Panel - TV Shows Listing Page
 * All management functions are now in separate pages:
 * - add-tv-show.php (Add new TV show)
 * - edit-tv-show.php (Edit TV show details)
 * - add-tv-show-sources.php (Manage TV show sources)
 * - add-episode.php (Add episodes - simplified)
 * - add-sources.php (Add sources to episodes)
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Manage TV Shows";
$conn = getDBConnection();

$message = '';
$message_type = '';

// Handle episode delete
if (isset($_GET['delete_episode'])) {
    $episode_id = intval($_GET['delete_episode']);
    $show_id = isset($_GET['show_id']) ? intval($_GET['show_id']) : 0;
    
    $stmt = $conn->prepare("DELETE FROM tv_episodes WHERE id = ?");
    $stmt->bind_param("i", $episode_id);
    $stmt->execute();
    
    $message = 'Episode deleted successfully';
    $message_type = 'success';
    
    if ($show_id) {
        header("Location: tv-shows.php?show_episodes=" . $show_id);
        exit;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    
    // Also delete all episodes for this TV show
    $conn->query("DELETE FROM tv_episodes WHERE tv_show_id = $id");
    
    $stmt = $conn->prepare("DELETE FROM tv_shows WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    
    $message = 'TV Show and all episodes deleted successfully';
    $message_type = 'success';
}

// Check if we're viewing episodes for a specific show
$show_episodes_id = isset($_GET['show_episodes']) ? intval($_GET['show_episodes']) : 0;
$show_episodes = null;
$episodes = [];

if ($show_episodes_id) {
    $stmt = $conn->prepare("SELECT * FROM tv_shows WHERE id = ?");
    $stmt->bind_param("i", $show_episodes_id);
    $stmt->execute();
    $show_episodes = $stmt->get_result()->fetch_assoc();
    
    if ($show_episodes) {
        $episodes_query = $conn->prepare("SELECT * FROM tv_episodes WHERE tv_show_id = ? ORDER BY season_number, episode_number");
        $episodes_query->bind_param("i", $show_episodes_id);
        $episodes_query->execute();
        $episodes = $episodes_query->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

// Get all TV shows with episode counts
$tv_shows = $conn->query("
    SELECT t.*, 
           c.name as category_name,
           (SELECT COUNT(*) FROM tv_episodes WHERE tv_show_id = t.id) as episode_count
    FROM tv_shows t 
    LEFT JOIN categories c ON t.category_id = c.id 
    ORDER BY t.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Count sources from all episodes for each show
foreach ($tv_shows as &$show) {
    $episodes_query = $conn->prepare("SELECT sources FROM tv_episodes WHERE tv_show_id = ?");
    $episodes_query->bind_param("i", $show['id']);
    $episodes_query->execute();
    $episodes_result = $episodes_query->get_result();
    
    $total_sources = 0;
    while ($episode = $episodes_result->fetch_assoc()) {
        $ep_sources = parseSources($episode['sources'] ?? '[]');
        $total_sources += count($ep_sources);
    }
    $show['episodes_with_sources'] = $total_sources;
    $episodes_query->close();
}

// Only include header if not loaded as a tab from index.php
if (!defined('ADMIN_TAB_LOAD')) {
    include 'includes/header.php';
}
?>

<?php if ($show_episodes_id && $show_episodes): ?>
<!-- Episode Listing View -->
<h1 class="text-4xl font-bold mb-2">Manage Episodes</h1>
<p class="text-gray-400 mb-8">TV Show: <strong><?php echo htmlspecialchars($show_episodes['title']); ?></strong></p>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="mb-6 flex gap-4">
    <a href="add-episode.php?show_id=<?php echo $show_episodes_id; ?>" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold inline-block">
        <i class="fas fa-plus mr-2"></i>Add New Episode
    </a>
    <a href="edit-tv-show.php?id=<?php echo $show_episodes_id; ?>" class="bg-blue-600 px-6 py-2 rounded hover:bg-blue-700 font-semibold inline-block">
        <i class="fas fa-edit mr-2"></i>Edit TV Show
    </a>
    <a href="tv-shows.php" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 font-semibold inline-block">
        <i class="fas fa-arrow-left mr-2"></i>Back to TV Shows
    </a>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All Episodes</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Season</th>
                    <th class="text-left p-3">Episode</th>
                    <th class="text-left p-3">Title</th>
                    <th class="text-left p-3">Sources</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($episodes)): ?>
                <tr>
                    <td colspan="5" class="p-8 text-center text-gray-400">
                        <i class="fas fa-tv text-4xl mb-3 block"></i>
                        <p>No episodes added yet. <a href="add-episode.php?show_id=<?php echo $show_episodes_id; ?>" class="text-netflix-red hover:underline">Add your first episode</a></p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($episodes as $episode): ?>
                <?php 
                $ep_sources = parseSources($episode['sources'] ?? '[]');
                $source_count = count($ep_sources);
                ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3">
                        <span class="px-3 py-1 bg-blue-600 rounded font-semibold">S<?php echo $episode['season_number']; ?></span>
                    </td>
                    <td class="p-3">
                        <span class="px-3 py-1 bg-green-600 rounded font-semibold">E<?php echo $episode['episode_number']; ?></span>
                    </td>
                    <td class="p-3 font-medium"><?php echo htmlspecialchars($episode['title']); ?></td>
                    <td class="p-3">
                        <?php if ($source_count > 0): ?>
                        <span class="px-3 py-1 bg-gray-700 rounded text-sm font-semibold">
                            <i class="fas fa-link mr-1"></i><?php echo $source_count; ?> source<?php echo $source_count > 1 ? 's' : ''; ?>
                        </span>
                        <?php else: ?>
                        <span class="px-3 py-1 bg-red-900 rounded text-sm text-red-200">
                            <i class="fas fa-exclamation-triangle mr-1"></i>No sources
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <a href="add-episode.php?show_id=<?php echo $show_episodes_id; ?>&episode_id=<?php echo $episode['id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                            <a href="add-sources.php?episode_id=<?php echo $episode['id']; ?>&show_id=<?php echo $show_episodes_id; ?>" 
                               class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-link mr-1"></i>Sources
                            </a>
                            <a href="tv-shows.php?delete_episode=<?php echo $episode['id']; ?>&show_id=<?php echo $show_episodes_id; ?>" 
                               onclick="return confirm('Are you sure you want to delete this episode?')" 
                               class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- TV Shows Listing View -->
<h1 class="text-4xl font-bold mb-8">Manage TV Shows</h1>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <a href="add-tv-show.php" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold inline-block">
        <i class="fas fa-plus mr-2"></i>Add New TV Show
    </a>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All TV Shows</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Title</th>
                    <th class="text-left p-3">Category</th>
                    <th class="text-left p-3">Year</th>
                    <th class="text-left p-3">Episodes</th>
                    <th class="text-left p-3">Sources</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tv_shows)): ?>
                <tr>
                    <td colspan="7" class="p-8 text-center text-gray-400">
                        <i class="fas fa-tv text-4xl mb-3 block"></i>
                        <p>No TV shows found. <a href="add-tv-show.php" class="text-netflix-red hover:underline">Add your first TV show</a></p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($tv_shows as $show): ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3 font-medium"><?php echo htmlspecialchars($show['title']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($show['category_name'] ?? 'N/A'); ?></td>
                    <td class="p-3"><?php echo $show['release_year']; ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-blue-600 rounded text-sm">
                            <?php echo $show['episode_count']; ?> episode<?php echo $show['episode_count'] != 1 ? 's' : ''; ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <span class="px-2 py-1 bg-gray-700 rounded text-sm">
                            <?php echo $show['episodes_with_sources']; ?> source<?php echo $show['episodes_with_sources'] != 1 ? 's' : ''; ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <?php if ($show['is_active']): ?>
                        <span class="px-2 py-1 bg-green-600 rounded text-xs">Active</span>
                        <?php else: ?>
                        <span class="px-2 py-1 bg-gray-600 rounded text-xs">Inactive</span>
                        <?php endif; ?>
                        <?php if ($show['is_premium']): ?>
                        <span class="px-2 py-1 bg-yellow-600 rounded text-xs ml-1">Premium</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3">
                        <div class="flex items-center gap-2">
                            <a href="edit-tv-show.php?id=<?php echo $show['id']; ?>" 
                               class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-edit mr-1"></i>Edit
                            </a>
                            <a href="add-tv-show-sources.php?show_id=<?php echo $show['id']; ?>" 
                               class="bg-green-600 hover:bg-green-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-link mr-1"></i>Sources
                            </a>
                            <a href="add-episode.php?show_id=<?php echo $show['id']; ?>" 
                               class="bg-purple-600 hover:bg-purple-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-plus mr-1"></i>Episode
                            </a>
                            <a href="tv-shows.php?show_episodes=<?php echo $show['id']; ?>" 
                               class="bg-indigo-600 hover:bg-indigo-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-list mr-1"></i>Episodes
                            </a>
                            <a href="tv-shows.php?delete=<?php echo $show['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this TV show? This will also delete all episodes!')"
                               class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm font-semibold">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php 
// Only include footer if not loaded as a tab from index.php
if (!defined('ADMIN_TAB_LOAD')) {
    include 'includes/footer.php';
}
?>
