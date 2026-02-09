<?php
require_once __DIR__ . '/../../includes/auth.php';
requireAdminLogin();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Admin - StreamFlix</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #141414; color: #e5e5e5; }
        .netflix-red { color: #e50914; }
        .bg-netflix-red { background-color: #e50914; }
    </style>
</head>
<body class="bg-black text-white">
    <nav class="bg-gray-900 border-b border-gray-800">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center space-x-8">
                <a href="dashboard.php" class="text-2xl font-bold netflix-red">ADMIN PANEL</a>
                <div class="flex space-x-6">
                    <a href="dashboard.php" class="hover:text-gray-300 <?php echo $current_page == 'dashboard.php' ? 'font-bold' : ''; ?>">Dashboard</a>
                    <a href="movies.php" class="hover:text-gray-300 <?php echo $current_page == 'movies.php' ? 'font-bold' : ''; ?>">Movies</a>
                    <a href="tv-shows.php" class="hover:text-gray-300 <?php echo $current_page == 'tv-shows.php' ? 'font-bold' : ''; ?>">TV Shows</a>
                    <a href="live-tv.php" class="hover:text-gray-300 <?php echo $current_page == 'live-tv.php' ? 'font-bold' : ''; ?>">Live TV</a>
                    <a href="categories.php" class="hover:text-gray-300 <?php echo $current_page == 'categories.php' ? 'font-bold' : ''; ?>">Categories</a>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-gray-400"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="../index.php" class="text-gray-400 hover:text-white">View Site</a>
                <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="bg-netflix-red px-4 py-2 rounded hover:bg-red-700">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container mx-auto px-4 py-8">
