<?php
/**
 * Admin Panel - Remove HTTP-only M3U8/HLS/DASH Stream Links
 * Finds all HLS/DASH sources that use http:// (not https://) and removes those sources.
 * Channels are preserved; only the matching sources are removed.
 */

$page_title = "Remove HTTP Stream Links";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();
$message = '';
$message_type = '';

$stats = [
    'total_channels'         => 0,
    'channels_with_http'     => 0,
    'sources_checked'        => 0,
    'http_sources_found'     => 0,
    'http_sources_removed'   => 0, // actual removed when process=run
    'http_sources_to_remove' => 0, // preview count
    'channels_updated'       => 0,
];

$has_run    = false;
$is_preview = false;
$is_process = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['preview']) && $_POST['preview'] === 'yes') {
        $is_preview = true;
    } elseif (isset($_POST['process']) && $_POST['process'] === 'yes') {
        $is_process = true;
    }
}

if ($is_preview || $is_process) {
    $has_run = true;
    set_time_limit(1800); // 30 minutes for scan

    // Get channels that have any sources defined
    $channels = $conn->query("SELECT id, name, sources FROM live_tv_channels WHERE sources IS NOT NULL AND sources != '' AND sources != '[]' ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    $stats['total_channels'] = count($channels);

    foreach ($channels as $channel) {
        $channel_id   = $channel['id'];
        $channel_name = $channel['name'];
        $sources_json = $channel['sources'];

        $sources = parseSources($sources_json);
        if (empty($sources) || !is_array($sources)) {
            continue;
        }

        $updated_sources         = [];
        $channel_had_http        = false;
        $removed_for_this_channel = 0;

        foreach ($sources as $source) {
            $url  = $source['url'] ?? '';
            $type = strtolower($source['type'] ?? '');

            // Only consider HLS/M3U8/DASH sources
            $is_stream_type = in_array($type, ['hls', 'm3u8', 'dash'], true);
            $is_http        = is_string($url) && stripos($url, 'http://') === 0;

            if ($is_stream_type) {
                $stats['sources_checked']++;
            }

            if ($is_stream_type && $is_http) {
                // Count this source as HTTP-only stream
                $stats['http_sources_found']++;
                $stats['http_sources_to_remove']++;
                $removed_for_this_channel++;
                $channel_had_http = true;
                continue;
            }

            // Keep all other sources
            $updated_sources[] = $source;
        }

        if ($channel_had_http) {
            $stats['channels_with_http']++;
        }

        // If we are in "process" mode, actually update the database
        if ($is_process && $removed_for_this_channel > 0) {
            $updated_sources_json = encodeSources($updated_sources);
            $stmt = $conn->prepare("UPDATE live_tv_channels SET sources = ? WHERE id = ?");
            $stmt->bind_param("si", $updated_sources_json, $channel_id);
            if ($stmt->execute()) {
                $stats['channels_updated']++;
                $stats['http_sources_removed'] += $removed_for_this_channel;
            }
        }
    }

    if ($is_process) {
        $message = "Cleanup complete! Checked {$stats['total_channels']} channels. "
                 . "{$stats['sources_checked']} HLS/DASH sources inspected. "
                 . "{$stats['http_sources_removed']} HTTP-only stream link(s) removed from {$stats['channels_updated']} channel(s).";
        $message_type = 'success';
    } else {
        $message = "Preview only: Found {$stats['http_sources_to_remove']} HTTP-only HLS/DASH stream link(s) "
                 . "across {$stats['channels_with_http']} channel(s). No changes have been made yet.";
        $message_type = 'warning';
    }
}

?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-unlock-alt mr-2 text-netflix-red"></i>Remove HTTP-only HLS/DASH Stream Links
    </h1>

    <p class="text-gray-400 mb-6">
        This tool finds all streaming sources where the <strong>source type</strong> is
        <code>hls</code>, <code>m3u8</code>, or <code>dash</code> and the URL starts with
        <code>http://</code> (not <code>https://</code>). Only those matching stream links are removed.
        <strong>Channels are never deleted.</strong>
    </p>

    <?php if ($message): ?>
        <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($has_run && !empty($stats) && $stats['total_channels'] > 0): ?>
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h3 class="text-lg font-bold mb-3"><?php echo $is_process ? 'Cleanup Summary' : 'Preview Summary'; ?></h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-400"><?php echo $stats['total_channels']; ?></div>
                    <div class="text-sm text-gray-400">Total Channels</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-400"><?php echo $stats['channels_with_http']; ?></div>
                    <div class="text-sm text-gray-400">Channels with HTTP Links</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-400"><?php echo $stats['sources_checked']; ?></div>
                    <div class="text-sm text-gray-400">HLS/DASH Sources Checked</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-400"><?php echo $stats['http_sources_found']; ?></div>
                    <div class="text-sm text-gray-400">HTTP Links Found</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-400">
                        <?php echo $is_process ? $stats['channels_updated'] : $stats['channels_with_http']; ?>
                    </div>
                    <div class="text-sm text-gray-400">
                        <?php echo $is_process ? 'Channels Updated' : 'Channels with HTTP Links'; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Note:</strong> This process may take several minutes depending on the number of channels.
        It will <strong>only remove HTTP (<code>http://</code>) HLS/DASH stream links</strong> and will
        leave all HTTPS links and non-HLS/DASH sources (iframe, YouTube, etc.) untouched.
    </div>

    <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-bold mb-4">What This Tool Does</h2>
        <ul class="list-disc list-inside text-gray-300 space-y-2 mb-4">
            <li>Scans all TV channels with any sources configured.</li>
            <li>Identifies sources where <code>type</code> is <code>hls</code>, <code>m3u8</code>, or <code>dash</code>.</li>
            <li>Among those, finds URLs that start with <code>http://</code> instead of <code>https://</code>.</li>
            <li><strong>Removes only those HTTP HLS/DASH links</strong> from the channel’s sources.</li>
            <li>Keeps all HTTPS links and all non-HLS/DASH sources intact.</li>
            <li><strong>Does not delete any channels</strong>; it only cleans up problematic links.</li>
        </ul>

        <!-- Step 1: Preview -->
        <form method="POST" class="mb-4" data-tool-progress="Scanning HTTP stream links...">
            <input type="hidden" name="preview" value="yes">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors">
                <i class="fas fa-search mr-2"></i>Preview HTTP-only Stream Links
            </button>
        </form>

        <!-- Step 2: Run cleanup (shown after preview) -->
        <?php if ($has_run && !$is_process && $stats['http_sources_to_remove'] > 0): ?>
        <form method="POST" data-tool-progress="Removing HTTP stream links..." onsubmit="return confirm('This will remove all detected HTTP-only HLS/DASH links (type hls/m3u8/dash) from TV channels. Channels will be preserved. Continue?');">
            <input type="hidden" name="process" value="yes">
            <button type="submit" class="bg-netflix-red hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors">
                <i class="fas fa-trash-alt mr-2"></i>Confirm & Remove HTTP-only Stream Links
            </button>
            <a href="?tab=tools" class="text-gray-400 hover:text-white ml-4">
                <i class="fas fa-arrow-left mr-2"></i>Back to Tools
            </a>
        </form>
        <?php else: ?>
        <a href="?tab=tools" class="text-gray-400 hover:text-white">
            <i class="fas fa-arrow-left mr-2"></i>Back to Tools
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/tool-progress-ui.php'; ?>

