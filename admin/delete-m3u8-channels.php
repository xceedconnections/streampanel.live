<?php
/**
 * Admin Panel - Delete All M3U8 Linked Channels
 * Deletes TV channels that have only m3u8/hls sources
 * Channels with YouTube, iframe, or other non-m3u8 sources are preserved
 */
$page_title = "Delete M3U8 Channels";

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
            $message = "Successfully deleted {$deleted_count} channel(s) with m3u8 links";
            $message_type = 'success';
        } else {
            $message = 'Error deleting channels: ' . $stmt->error;
            $message_type = 'error';
        }
    }
}

// Get all channels and check their sources
$all_channels = $conn->query("SELECT id, name, description, category, sources FROM live_tv_channels ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// Function to check if a source is m3u8/hls
function isM3U8Source($source) {
    if (empty($source) || !is_array($source)) {
        return false;
    }
    
    $type = strtolower($source['type'] ?? '');
    $url = strtolower($source['url'] ?? '');
    
    // Check if type is m3u8 or hls
    if ($type === 'm3u8' || $type === 'hls') {
        return true;
    }
    
    // Check if URL contains m3u8
    if (strpos($url, '.m3u8') !== false || strpos($url, 'm3u8') !== false) {
        return true;
    }
    
    return false;
}

// Function to check if a source is a non-m3u8 source (youtube, iframe, etc.)
function isNonM3U8Source($source) {
    if (empty($source) || !is_array($source)) {
        return false;
    }
    
    $type = strtolower($source['type'] ?? '');
    $url = strtolower($source['url'] ?? '');
    
    // Non-m3u8 source types
    $non_m3u8_types = [
        'youtube',
        'iframe',
        'iframe-only',
        'html-embed',
        'embed',
        'direct',
        'dash',
        'm3u',
        'rtmp',
        'rtsp',
        'open-window'
    ];
    
    if (in_array($type, $non_m3u8_types)) {
        return true;
    }
    
    // Check if URL is YouTube
    if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        return true;
    }
    
    return false;
}

// Analyze channels
foreach ($all_channels as $channel) {
    $sources = parseSources($channel['sources'] ?? '[]');
    
    // Skip channels with no sources
    if (empty($sources)) {
        continue;
    }
    
    $has_m3u8 = false;
    $has_non_m3u8 = false;
    
    // Check each source (check all sources, not just active ones)
    foreach ($sources as $source) {
        if (isM3U8Source($source)) {
            $has_m3u8 = true;
        }
        
        if (isNonM3U8Source($source)) {
            $has_non_m3u8 = true;
        }
    }
    
    // If channel has m3u8 sources but NO non-m3u8 sources, mark for deletion
    if ($has_m3u8 && !$has_non_m3u8) {
        $channels_to_delete[] = $channel;
    }
}

if ($is_preview || $is_process) {
    $has_run = true;
}

?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-trash-alt mr-2 text-netflix-red"></i>Delete All M3U8 Linked Channels
    </h1>
    
    <p class="text-gray-400 mb-6">
        This tool will delete TV channels that have <strong>only m3u8/hls sources</strong>. 
        Channels with YouTube, iframe, HTML embed, or other non-m3u8 sources will be preserved.
    </p>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Warning:</strong> First preview, then confirm deletion. This action cannot be undone once executed.
    </div>

    <!-- Step 1: Preview button -->
    <form method="POST" data-tool-progress="Scanning M3U8 channels..." class="mb-6">
        <input type="hidden" name="preview" value="yes">
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors">
            <i class="fas fa-search mr-2"></i>Preview M3U8-only Channels
        </button>
    </form>
    
    <?php if ($has_run && empty($channels_to_delete)): ?>
    <div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-4">
        <i class="fas fa-check-circle mr-2"></i>
        No channels found with only m3u8 sources. All channels either have no sources or have non-m3u8 sources (YouTube, iframe, etc.) and will be preserved.
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
        
        <form method="POST" data-tool-progress="Deleting M3U8 channels..." id="deleteForm" onsubmit="return confirm('Are you absolutely sure you want to delete <?php echo count($channels_to_delete); ?> M3U8-only channel(s)? This action cannot be undone!');">
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
                            <th class="p-3 text-left">Sources</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($channels_to_delete as $channel): ?>
                        <?php 
                        $sources = parseSources($channel['sources'] ?? '[]');
                        $m3u8_sources = [];
                        foreach ($sources as $source) {
                            if (isM3U8Source($source)) {
                                $m3u8_sources[] = $source;
                            }
                        }
                        ?>
                        <tr class="border-b border-gray-700 hover:bg-gray-750">
                            <td class="p-3">
                                <input type="checkbox" name="channel_ids[]" value="<?php echo $channel['id']; ?>" checked class="channel-checkbox">
                            </td>
                            <td class="p-3"><?php echo htmlspecialchars($channel['id']); ?></td>
                            <td class="p-3 font-semibold"><?php echo htmlspecialchars($channel['name']); ?></td>
                            <td class="p-3"><?php echo htmlspecialchars($channel['category'] ?? 'N/A'); ?></td>
                            <td class="p-3">
                                <div class="text-xs text-gray-400">
                                    <?php echo count($m3u8_sources); ?> m3u8 source(s)
                                    <?php if (count($m3u8_sources) > 0): ?>
                                    <div class="mt-1">
                                        <?php 
                                        $first_source = $m3u8_sources[0];
                                        $url_preview = substr($first_source['url'] ?? '', 0, 60);
                                        echo htmlspecialchars($url_preview) . (strlen($first_source['url'] ?? '') > 60 ? '...' : '');
                                        ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
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
            <li><strong>Deletes</strong> channels that have <strong>only m3u8/hls sources</strong></li>
            <li><strong>Preserves</strong> channels that have YouTube, iframe, HTML embed, direct video, or other non-m3u8 sources</li>
            <li>Channels with no sources are preserved</li>
        </ul>
        
        <h3 class="text-lg font-bold mt-4 mb-2">Source Types That Will Preserve a Channel:</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm text-gray-300">
            <div><i class="fas fa-check text-green-400 mr-1"></i>YouTube</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>Iframe</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>HTML Embed</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>Direct Video</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>DASH</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>RTMP</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>RTSP</div>
            <div><i class="fas fa-check text-green-400 mr-1"></i>M3U</div>
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
