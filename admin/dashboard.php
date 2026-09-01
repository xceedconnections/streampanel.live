<?php
/**
 * Admin Dashboard (included from index.php?tab=dashboard)
 */
if (!isset($stats)) {
    require_once __DIR__ . '/../config/config.php';
    header('Location: ' . url('admin/index.php?tab=dashboard'));
    exit;
}

$page_title = "Dashboard";
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Dashboard</h1>
    <p class="text-gray-400">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>!</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 hover:border-netflix-red transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm mb-1">Total Movies</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_movies']); ?></p>
            </div>
            <i class="fas fa-film text-4xl text-netflix-red"></i>
        </div>
        <a href="?tab=movies" class="text-sm text-gray-400 hover:text-netflix-red mt-2 inline-block">View All →</a>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 hover:border-netflix-red transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm mb-1">Total TV Shows</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_tv_shows']); ?></p>
            </div>
            <i class="fas fa-tv text-4xl text-netflix-red"></i>
        </div>
        <a href="?tab=tv-shows" class="text-sm text-gray-400 hover:text-netflix-red mt-2 inline-block">View All →</a>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 hover:border-netflix-red transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm mb-1">Live TV Channels</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_channels']); ?></p>
            </div>
            <i class="fas fa-broadcast-tower text-4xl text-netflix-red"></i>
        </div>
        <a href="?tab=live-tv" class="text-sm text-gray-400 hover:text-netflix-red mt-2 inline-block">View All →</a>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 hover:border-netflix-red transition-colors">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm mb-1">Total Users</p>
                <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_users']); ?></p>
            </div>
            <i class="fas fa-users text-4xl text-netflix-red"></i>
        </div>
        <a href="?tab=users" class="text-sm text-gray-400 hover:text-netflix-red mt-2 inline-block">View All →</a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <h3 class="text-xl font-bold mb-4">Featured Content</h3>
        <div class="space-y-3">
            <div class="flex justify-between">
                <span class="text-gray-400">Featured Movies</span>
                <span class="font-bold text-yellow-400"><?php echo $stats['featured_movies']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Featured TV Shows</span>
                <span class="font-bold text-yellow-400"><?php echo $stats['featured_tv_shows']; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Featured Channels</span>
                <span class="font-bold text-yellow-400"><?php echo $stats['featured_channels']; ?></span>
            </div>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <h3 class="text-xl font-bold mb-4">Quick Actions</h3>
        <div class="space-y-2">
            <a href="?tab=movies" class="block bg-netflix-red hover:bg-red-700 px-4 py-2 rounded text-center transition-colors">
                <i class="fas fa-plus mr-2"></i>Add Movie
            </a>
            <a href="?tab=tv-shows" class="block bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-center transition-colors">
                <i class="fas fa-plus mr-2"></i>Add TV Show
            </a>
            <a href="?tab=live-tv" class="block bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-center transition-colors">
                <i class="fas fa-plus mr-2"></i>Add Channel
            </a>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <h3 class="text-xl font-bold mb-4">System Info</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-400">PHP Version</span>
                <span class="text-white"><?php echo PHP_VERSION; ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Server Time</span>
                <span class="text-white"><?php echo date('Y-m-d H:i:s'); ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Categories</span>
                <span class="text-white"><?php echo $stats['total_categories']; ?></span>
            </div>
        </div>
    </div>
</div>
