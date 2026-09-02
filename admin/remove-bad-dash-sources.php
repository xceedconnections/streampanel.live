<?php
/**
 * Admin Panel - Remove Non-Working DASH/MPD Sources (live progress)
 */
$page_title = "Remove Bad DASH Sources";

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();
$count_row = $conn->query("SELECT COUNT(*) AS c FROM live_tv_channels WHERE sources IS NOT NULL AND sources != '' AND sources != '[]'")->fetch_assoc();
$channel_count = (int) ($count_row['c'] ?? 0);
?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-link mr-2 text-netflix-red"></i>Remove Non-Working DASH/MPD Sources
    </h1>

    <p class="text-gray-400 mb-6">
        Checks DASH/MPD sources in batches and shows live progress. Channels are preserved — only dead sources are removed.
    </p>

    <div class="bg-gray-800 rounded-lg p-4 mb-6 grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="text-center">
            <div class="text-2xl font-bold text-blue-400"><?php echo number_format($channel_count); ?></div>
            <div class="text-sm text-gray-400">Channels with sources</div>
        </div>
        <div class="text-center md:col-span-2 text-left md:text-center text-sm text-gray-400 flex items-center justify-center">
            Progress updates live. You can pause/stop anytime.
        </div>
    </div>

    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6 text-sm">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        Large libraries can take a while. Keep this tab open while scanning.
    </div>

    <div class="flex flex-wrap gap-3 mb-4">
        <button type="button" id="dashPreviewBtn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
            <i class="fas fa-search mr-2"></i>Preview Bad DASH Sources
        </button>
        <button type="button" id="dashRemoveBtn" class="bg-netflix-red hover:bg-red-700 text-white font-bold py-2 px-6 rounded">
            <i class="fas fa-play mr-2"></i>Scan &amp; Remove Bad DASH Sources
        </button>
        <a href="?tab=tools" class="text-gray-400 hover:text-white py-2">
            <i class="fas fa-arrow-left mr-2"></i>Back to Tools
        </a>
    </div>
</div>

<?php require __DIR__ . '/includes/tool-progress-ui.php'; ?>
<script>
(function () {
    const api = <?php echo json_encode(apiUrl('admin/api/check-stream-links.php')); ?>;
    function start(mode) {
        if (mode === 'remove' && !confirm('This will remove non-working DASH/MPD sources. Channels stay. Continue?')) {
            return;
        }
        ToolProgress.startBatchScan({
            apiUrl: api,
            title: mode === 'preview' ? 'Previewing DASH sources…' : 'Removing bad DASH sources…',
            startPayload: { action: 'start', types: 'dash', mode: mode }
        });
    }
    document.getElementById('dashPreviewBtn').addEventListener('click', function () { start('preview'); });
    document.getElementById('dashRemoveBtn').addEventListener('click', function () { start('remove'); });
})();
</script>
