<?php
/**
 * Admin Panel - Reports & Analytics
 */
$page_title = "Reports & Analytics";

// Get top movies by views
$top_movies = $conn->query("SELECT * FROM movies ORDER BY views DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Get top TV shows by views
$top_tv_shows = $conn->query("SELECT * FROM tv_shows ORDER BY views DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Get top channels by views
$top_channels = $conn->query("SELECT * FROM live_tv_channels ORDER BY views DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// Get total views
$total_movie_views = $conn->query("SELECT SUM(views) as total FROM movies")->fetch_assoc()['total'] ?? 0;
$total_tv_views = $conn->query("SELECT SUM(views) as total FROM tv_shows")->fetch_assoc()['total'] ?? 0;
$total_channel_views = $conn->query("SELECT SUM(views) as total FROM live_tv_channels")->fetch_assoc()['total'] ?? 0;
$total_views = $total_movie_views + $total_tv_views + $total_channel_views;

// Get concurrent live viewers
$total_concurrent_viewers = getTotalConcurrentViewers($conn);
$concurrent_viewers_by_channel = getConcurrentViewersByChannel($conn);
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Reports & Analytics</h1>
    <p class="text-gray-400">View analytics and performance metrics</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Total Views</p>
        <p class="text-3xl font-bold text-white"><?php echo number_format($total_views); ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Movie Views</p>
        <p class="text-3xl font-bold text-blue-400"><?php echo number_format($total_movie_views); ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">TV Show Views</p>
        <p class="text-3xl font-bold text-green-400"><?php echo number_format($total_tv_views); ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Channel Views</p>
        <p class="text-3xl font-bold text-yellow-400"><?php echo number_format($total_channel_views); ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 border-2 border-green-500">
        <p class="text-gray-400 text-sm mb-1">Live Viewers Now</p>
        <p class="text-3xl font-bold text-green-400"><?php echo number_format($total_concurrent_viewers); ?></p>
        <p class="text-xs text-gray-500 mt-1">Real-time concurrent</p>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <div class="bg-gray-900 rounded-lg p-6">
        <h3 class="text-xl font-bold mb-4">Top Movies</h3>
        <div class="space-y-2">
            <?php foreach ($top_movies as $index => $movie): ?>
            <div class="flex justify-between items-center p-2 hover:bg-gray-800 rounded">
                <div>
                    <span class="text-gray-400">#<?php echo $index + 1; ?></span>
                    <span class="ml-2"><?php echo htmlspecialchars($movie['title']); ?></span>
                </div>
                <span class="text-netflix-red font-bold"><?php echo number_format($movie['views']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6">
        <h3 class="text-xl font-bold mb-4">Top TV Shows</h3>
        <div class="space-y-2">
            <?php foreach ($top_tv_shows as $index => $show): ?>
            <div class="flex justify-between items-center p-2 hover:bg-gray-800 rounded">
                <div>
                    <span class="text-gray-400">#<?php echo $index + 1; ?></span>
                    <span class="ml-2"><?php echo htmlspecialchars($show['title']); ?></span>
                </div>
                <span class="text-netflix-red font-bold"><?php echo number_format($show['views']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6">
        <h3 class="text-xl font-bold mb-4">Top Channels</h3>
        <div class="space-y-2">
            <?php foreach ($top_channels as $index => $channel): ?>
            <div class="flex justify-between items-center p-2 hover:bg-gray-800 rounded">
                <div>
                    <span class="text-gray-400">#<?php echo $index + 1; ?></span>
                    <span class="ml-2"><?php echo htmlspecialchars($channel['name']); ?></span>
                </div>
                <span class="text-netflix-red font-bold"><?php echo number_format($channel['views']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Concurrent Live Viewers Section -->
<div class="bg-gray-900 rounded-lg p-6 mt-8">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-xl font-bold">Live Viewers (Real-time)</h3>
        <button onclick="refreshLiveViewers()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm">
            <i class="fas fa-sync-alt mr-2"></i>Refresh
        </button>
    </div>
    <div id="live-viewers-container">
        <?php if (empty($concurrent_viewers_by_channel)): ?>
        <p class="text-gray-400 text-center py-8">No active viewers at the moment</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="text-left p-3">Channel</th>
                        <th class="text-left p-3">Concurrent Viewers</th>
                        <th class="text-left p-3">Total Views</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($concurrent_viewers_by_channel as $viewer_data): ?>
                    <?php 
                    $channel_info = $conn->query("SELECT name, views FROM live_tv_channels WHERE id = " . intval($viewer_data['channel_id']))->fetch_assoc();
                    ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800">
                        <td class="p-3">
                            <div class="font-semibold"><?php echo htmlspecialchars($viewer_data['channel_name'] ?? 'Unknown Channel'); ?></div>
                        </td>
                        <td class="p-3">
                            <span class="px-3 py-1 bg-green-900 text-green-200 rounded-full font-bold">
                                <i class="fas fa-eye mr-1"></i><?php echo number_format($viewer_data['concurrent_viewers']); ?>
                            </span>
                        </td>
                        <td class="p-3 text-gray-400">
                            <?php echo number_format($channel_info['views'] ?? 0); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function refreshLiveViewers() {
    const container = document.getElementById('live-viewers-container');
    container.innerHTML = '<p class="text-gray-400 text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Refreshing...</p>';
    
    // Reload the page to refresh data
    setTimeout(() => {
        window.location.reload();
    }, 500);
}

// Auto-refresh every 10 seconds
setInterval(function() {
    const refreshBtn = document.querySelector('[onclick="refreshLiveViewers()"]');
    if (refreshBtn && document.visibilityState === 'visible') {
        refreshLiveViewers();
    }
}, 10000);
</script>
