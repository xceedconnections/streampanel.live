<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';
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
        
        /* Horizontal scrolling menu */
        .nav-scroll-container {
            position: relative;
            display: flex;
            align-items: center;
            max-width: 100%;
        }
        
        .nav-scroll-wrapper {
            position: relative;
            flex: 1;
            overflow: hidden;
        }
        
        .nav-scroll-menu {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            padding: 0 10px;
        }
        
        .nav-scroll-menu::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        .nav-scroll-arrow {
            background: rgba(0, 0, 0, 0.7);
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 18px;
            z-index: 10;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        .nav-scroll-arrow:hover {
            background: rgba(229, 9, 20, 0.8);
        }
        
        .nav-scroll-arrow:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .nav-scroll-arrow-left {
            border-radius: 4px 0 0 4px;
        }
        
        .nav-scroll-arrow-right {
            border-radius: 0 4px 4px 0;
        }
        
        /* Profile Dropdown */
        #profile-menu {
            animation: fadeIn 0.2s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-black text-white">
    <!-- Admin Header -->
    <nav class="bg-gray-900 border-b border-gray-800 sticky top-0 z-50">
        <!-- First Row: Logo and Profile -->
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <a href="index.php?tab=dashboard" class="text-2xl font-bold netflix-red">ADMIN PANEL</a>
            <div class="relative">
                <button onclick="toggleProfileMenu()" class="flex items-center space-x-2 hover:opacity-80">
                    <i class="fas fa-user-circle text-3xl text-gray-400"></i>
                    <span class="hidden md:block"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div id="profile-menu" class="hidden absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-2 z-50">
                    <div class="px-4 py-2 border-b border-gray-700">
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></p>
                        <p class="text-xs text-gray-400">Administrator</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/index.php" class="block px-4 py-2 hover:bg-gray-700 text-sm">
                        <i class="fas fa-home mr-2"></i>View Site
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="block px-4 py-2 hover:bg-gray-700 text-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Second Row: Navigation Menu -->
        <div class="container mx-auto px-4 pb-3">
            <div class="nav-scroll-container">
                <button class="nav-scroll-arrow nav-scroll-arrow-left" id="nav-scroll-left" onclick="scrollNav('left')" aria-label="Scroll left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="nav-scroll-wrapper">
                    <div class="nav-scroll-menu" id="admin-nav">
                        <a href="index.php?tab=dashboard" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo (strpos($current_page, 'dashboard') !== false || strpos($current_page, 'index') !== false) ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                        </a>
                        <a href="index.php?tab=movies" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'movie') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-film mr-2"></i>Movies
                        </a>
                        <a href="tv-shows.php" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo (strpos($current_page, 'tv-show') !== false || strpos($current_page, 'episode') !== false || strpos($current_page, 'add-sources') !== false) ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-tv mr-2"></i>TV Shows
                        </a>
                        <a href="index.php?tab=live-tv" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'live-tv') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-broadcast-tower mr-2"></i>Live TV
                        </a>
                        <a href="index.php?tab=users" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'user') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-users mr-2"></i>Users
                        </a>
                        <a href="index.php?tab=coupons" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'coupon') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-ticket-alt mr-2"></i>Coupons
                        </a>
                        <a href="index.php?tab=ads" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'ad') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-ad mr-2"></i>Ads
                        </a>
                        <a href="index.php?tab=sliders" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'slider') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-images mr-2"></i>Sliders
                        </a>
                        <a href="index.php?tab=reports" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'report') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-chart-bar mr-2"></i>Reports
                        </a>
                        <a href="index.php?tab=user-messages" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'message') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-envelope mr-2"></i>Msgs
                        </a>
                        <a href="index.php?tab=settings" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'setting') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-cog mr-2"></i>Settings
                        </a>
                        <a href="index.php?tab=import" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'import') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-download mr-2"></i>Import/Export
                        </a>
                        <a href="index.php?tab=iptv" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'iptv') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-satellite-dish mr-2"></i>IPTV
                        </a>
                        <a href="index.php?tab=countdown" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'countdown') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-clock mr-2"></i>Countdown
                        </a>
                        <a href="index.php?tab=tools" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo strpos($current_page, 'tool') !== false ? 'active font-bold text-netflix-red' : ''; ?>">
                            <i class="fas fa-tools mr-2"></i>Tools
                        </a>
                    </div>
                </div>
                <button class="nav-scroll-arrow nav-scroll-arrow-right" id="nav-scroll-right" onclick="scrollNav('right')" aria-label="Scroll right">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </nav>

    <!-- Admin Content -->
    <div class="container mx-auto px-4 py-8">
