<?php
/**
 * Admin Panel - Delete All TV Channels with No Source
 * Deletes TV channels that have no sources configured
 */
$page_title = "Delete Channels with No Source";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();
$message = '';
$message_type = '';
$channels_to_delete = [];
$deleted_count = 0;

$has_run    = false;
$is_preview = false;
$is_process = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['preview']) && $_POST['preview'] === 'yes') {
        $is_preview = true;   // just scan & show
    } elseif (isset($_POST['process']) && $_POST['process'] === 'yes') {
        $is_process = true;   // delete selected channels
    }
}

// Handle deletion after preview
if ($is_process) {
    $channel_ids = isset($_POST['channel_ids']) ? $_POST['channel_ids'] : [];
    
    if (empty($channel_ids)) {
        $message = 'No channels selected for deletion';
        $message_type = 'error';
    } else {
        // Convert to integers for safety
        $channel_ids = array_map('intval', $channel_ids);
        $placeholders = implode(',', array_fill(0, count($channel_ids), '?'));
        
        // Delete channels
        $stmt = $conn->prepare("DELETE FROM live_tv_channels WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($channel_ids)), ...$channel_ids);
        
        if ($stmt->execute()) {
            $deleted_count = count($channel_ids);
            $message = "Successfully deleted {$deleted_count} channel(s) with no sources";
            $message_type = 'success';
        } else {
            $message = 'Error deleting channels: ' . $stmt->error;
            $message_type = 'error';
        }
    }
}

// Get all channels and check their sources
$all_channels = $conn->query("SELECT id, name, description, category, sources, stream_url FROM live_tv_channels ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Analyze channels
foreach ($all_channels as $channel) {
    // Get sources - handle NULL, empty string, or JSON
    $sources_json = $channel['sources'];
    
    // Check if sources is NULL, empty, or empty JSON array
    $sources_is_empty = false;
    if ($sources_json === null || $sources_json === '') {
        $sources_is_empty = true;
        $sources = [];
    } else {
        $sources_json_trimmed = trim($sources_json);
        // Check if it's an empty JSON array
        if ($sources_json_trimmed === '[]' || $sources_json_trimmed === 'null') {
            $sources_is_empty = true;
            $sources = [];
        } else {
            // Parse sources
            $sources = parseSources($sources_json_trimmed);
            // Check if parsed result is empty
            if (empty($sources) || !is_array($sources) || count($sources) === 0) {
                $sources_is_empty = true;
            }
        }
    }
    
    // If sources is not empty, check for valid sources
    if (!$sources_is_empty && !empty($sources) && is_array($sources)) {
        $valid_sources = [];
        foreach ($sources as $source) {
            if (is_array($source)) {
                $url = trim($source['url'] ?? '');
                $type = trim($source['type'] ?? '');
                // Consider a source valid if it has either a URL or a type
                if (!empty($url) || !empty($type)) {
                    $valid_sources[] = $source;
                }
            }
        }
        // If no valid sources found, treat as empty
        if (count($valid_sources) === 0) {
            $sources_is_empty = true;
        }
    }
    
    // Check stream_url (legacy field)
    $stream_url = trim($channel['stream_url'] ?? '');
    $has_stream_url = !empty($stream_url);
    
    // Channel has no sources if sources is empty AND no stream_url
    if ($sources_is_empty && !$has_stream_url) {
        $channels_to_delete[] = $channel;
    }
}

// Mark that we've "run" the analysis if this is a POST (preview or process)
if ($is_preview || $is_process) {
    $has_run = true;
}

?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-trash-alt mr-2 text-netflix-red"></i>Delete Channels with No Source
    </h1>
    
    <p class="text-gray-400 mb-6">
        This tool will delete TV channels that have <strong>no sources configured</strong>. 
        Channels with any valid sources (m3u8, YouTube, iframe, direct video, etc.) will be preserved.
    </p>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Warning:</strong> This process scans channels for missing sources. Use the preview first, then confirm deletion.
    </div>

    <!-- Step 1: Preview button -->
    <form method="POST" data-tool-progress="Scanning channels..." class="mb-6">
        <input type="hidden" name="preview" value="yes">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors">
            <i class="fas fa-search mr-2"></i>Preview Channels with No Source
        </button>
    </form>
    
    <?php if ($has_run && empty($channels_to_delete)): ?>
    <div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-4">
        <i class="fas fa-check-circle mr-2"></i>
        No channels found without sources. All channels have at least one source configured.
    </div>
    
    <!-- Debug Section -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Debug Information</h2>
        <p class="text-gray-400 text-sm mb-4">Showing first 10 channels and their source detection:</p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-700">
                    <tr>
                        <th class="p-2 text-left">ID</th>
                        <th class="p-2 text-left">Name</th>
                        <th class="p-2 text-left">Sources JSON</th>
                        <th class="p-2 text-left">Stream URL</th>
                        <th class="p-2 text-left">Parsed Count</th>
                        <th class="p-2 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $debug_count = 0;
                    foreach ($all_channels as $channel): 
                        if ($debug_count >= 10) break;
                        $debug_count++;
                        $sources_json = $channel['sources'];
                        $sources = parseSources($sources_json ?? '[]');
                        $stream_url = trim($channel['stream_url'] ?? '');
                        $sources_preview = strlen($sources_json ?? '') > 50 ? substr($sources_json ?? '', 0, 50) . '...' : ($sources_json ?? 'NULL');
                    ?>
                    <tr class="border-b border-gray-700">
                        <td class="p-2"><?php echo htmlspecialchars($channel['id']); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars($channel['name']); ?></td>
                        <td class="p-2 text-xs font-mono"><?php echo htmlspecialchars($sources_preview); ?></td>
                        <td class="p-2 text-xs"><?php echo htmlspecialchars(strlen($stream_url) > 30 ? substr($stream_url, 0, 30) . '...' : ($stream_url ?: 'empty')); ?></td>
                        <td class="p-2"><?php echo count($sources); ?></td>
                        <td class="p-2">
                            <?php if (count($sources) === 0 && empty($stream_url)): ?>
                                <span class="text-red-400">Should Delete</span>
                            <?php else: ?>
                                <span class="text-green-400">Has Sources</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php elseif ($has_run): ?>
    
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-bold">
                Channels to be Deleted: <span class="text-netflix-red"><?php echo count($channels_to_delete); ?></span>
            </h2>
            <div class="text-sm text-gray-400">
                Total channels in database: <?php echo count($all_channels); ?>
            </div>
        </div>
        
        <form method="POST" data-tool-progress="Deleting channels..." id="deleteForm" onsubmit="return confirm('Are you absolutely sure you want to delete <?php echo count($channels_to_delete); ?> channel(s) with no sources? This action cannot be undone!');">
            <input type="hidden" name="process" value="yes">
            
            <div class="mb-4 max-h-96 overflow-y-auto border border-gray-700 rounded">
                <table class="w-full text-sm">
                    <thead class="bg-gray-700 sticky top-0">
                        <tr>
                            <th class="p-3 text-left">
                                <input type="checkbox" id="selectAll" checked onchange="toggleAll(this)">
                            </th>
                            <th class="p-3 text-left">ID</th>
                            <th class="p-3 text-left">Channel Name</th>
                            <th class="p-3 text-left">Category</th>
                            <th class="p-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($channels_to_delete as $channel): ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-750">
                            <td class="p-3">
                                <input type="checkbox" name="channel_ids[]" value="<?php echo $channel['id']; ?>" checked class="channel-checkbox">
                            </td>
                            <td class="p-3"><?php echo htmlspecialchars($channel['id']); ?></td>
                            <td class="p-3 font-semibold"><?php echo htmlspecialchars($channel['name']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($channel['category'] ?? 'N/A'); ?></td>
                            <td class="p-3">
                                <span class="text-red-400 text-xs">
                                    <i class="fas fa-exclamation-circle mr-1"></i>No sources
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="flex items-center justify-between mt-6">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors">
                    <i class="fas fa-trash-alt mr-2"></i>Delete Selected Channels
                </button>
                <a href="?tab=tools" class="text-gray-400 hover:text-white">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tools
                </a>
            </div>
        </form>
    </div>
    
    <?php endif; ?>
    
    <div class="bg-gray-800 rounded-lg p-6 mt-6">
        <h2 class="text-xl font-bold mb-4">How This Tool Works</h2>
        <ul class="list-disc list-inside text-gray-300 space-y-2">
            <li>Scans all TV channels in the database</li>
            <li>Checks each channel's sources (from the JSON sources field)</li>
            <li>Also checks the legacy stream_url field</li>
            <li><strong>Deletes</strong> channels that have <strong>no valid sources</strong> configured</li>
            <li><strong>Preserves</strong> channels that have any sources (m3u8, YouTube, iframe, direct video, etc.)</li>
        </ul>
        
        <h3 class="text-lg font-bold mt-4 mb-2">What Counts as a Valid Source:</h3>
        <div class="text-sm text-gray-300">
            <p class="mb-2">A channel is considered to have sources if it has:</p>
            <ul class="list-disc list-inside ml-4 space-y-1">
                <li>At least one source in the sources JSON array with a valid URL or type</li>
                <li>OR a valid stream_url in the legacy stream_url field</li>
            </ul>
        </div>
    </div>
</div>

<script>
function toggleAll(checkbox) {
    const checkboxes = document.querySelectorAll('.channel-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

// Update select all checkbox when individual checkboxes change
document.querySelectorAll('.channel-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const allChecked = Array.from(document.querySelectorAll('.channel-checkbox')).every(cb => cb.checked);
        document.getElementById('selectAll').checked = allChecked;
    });
});
</script>

<?php require __DIR__ . '/includes/tool-progress-ui.php'; ?>
