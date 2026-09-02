<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../admin/includes/functions.php';

// Get site name from settings
$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamFlix');

// Check maintenance mode (allow admin to bypass)
$maintenance_mode = getSetting($conn, 'maintenance_mode', '0');
$is_admin = isset($_SESSION['admin_id']);

// If maintenance mode is enabled and user is not admin, show maintenance page
if ($maintenance_mode == '1' && !$is_admin) {
    // Don't include header/footer, just show maintenance message
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Site Under Maintenance - <?php echo htmlspecialchars($site_name); ?></title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%); }
        </style>
    </head>
    <body class="min-h-screen flex items-center justify-center">
        <div class="text-center px-4">
            <div class="mb-8">
                <i class="fas fa-tools text-6xl text-yellow-500 mb-4"></i>
            </div>
            <h1 class="text-5xl font-bold text-white mb-4">SITE IS UNDER MAINTENANCE</h1>
            <p class="text-xl text-gray-400 mb-8">We're currently performing some updates. Please check back soon.</p>
            <div class="flex justify-center space-x-4">
                <button onclick="location.reload()" class="bg-red-600 hover:bg-red-700 px-6 py-3 rounded font-semibold">
                    <i class="fas fa-sync-alt mr-2"></i>Refresh Page
                </button>
            </div>
        </div>
        <script>
            // Auto-refresh every 30 seconds to check if maintenance is disabled
            setTimeout(function() {
                location.reload();
            }, 30000);
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Check which sections are enabled
$enable_movies = isSectionEnabled($conn, 'movies');
$enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
$enable_live_tv = isSectionEnabled($conn, 'live_tv');

// Validate session on every page load (for logged in users)
if (isset($_SESSION['user_id'])) {
    // Check if user has exceeded device limit (temp session)
    // If so, redirect to device management page (except if already on that page)
    if (isset($_SESSION['temp_session']) && $_SESSION['temp_session'] === true) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($current_page !== 'manage-devices.php') {
            header('Location: ' . BASE_URL . '/manage-devices.php?device_limit=1');
            exit();
        }
    }
    
    // Check if user is banned
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT banned FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_check = $stmt->get_result()->fetch_assoc();
    
    // Check if user is banned (handle MySQL BOOLEAN 0/1 values)
    $banned_value = $user_check['banned'] ?? 0;
    $is_banned = ($banned_value == 1 || $banned_value === '1' || $banned_value === true);
    
    if ($is_banned) {
        // User is banned, delete all sessions and redirect to report page
        removeAllUserSessions($user_id);
        $_SESSION = array();
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time()-3600, '/');
        }
        session_destroy();
        // Start new session for banned user
        session_start();
        $_SESSION['banned_user_id'] = $user_id;
        header('Location: ' . BASE_URL . '/report.php?banned=1');
        exit();
    }
    
    // Only validate session against database if it's not a temp session
    if (!isset($_SESSION['temp_session']) || $_SESSION['temp_session'] !== true) {
        if (!validateUserSession()) {
            // Session was invalidated, redirect to login
            header('Location: ' . BASE_URL . '/login.php?session_expired=1');
            exit();
        }
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo htmlspecialchars($site_name); ?></title>
    <?php if (isset($meta_description) && !empty($meta_description)): ?>
    <meta name="description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    <?php if (isset($meta_keywords) && !empty($meta_keywords)): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($meta_keywords); ?>">
    <?php endif; ?>
    <?php if (!empty($canonical_url)): ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">
    <?php endif; ?>
    <?php if (!empty($og_image)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <?php endif; ?>
    <?php if (!empty($page_title)): ?>
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title . ' - ' . $site_name); ?>">
    <?php endif; ?>
    <?php if (!empty($meta_description)): ?>
    <meta property="og:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    <meta property="og:type" content="<?php echo htmlspecialchars($og_type ?? 'website'); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical_url ?? BASE_URL); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php if (!empty($page_title)): ?>
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title . ' - ' . $site_name); ?>">
    <?php endif; ?>
    <?php if (!empty($meta_description)): ?>
    <meta name="twitter:description" content="<?php echo htmlspecialchars($meta_description); ?>">
    <?php endif; ?>
    <?php if (!empty($og_image)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fadeIn 0.7s ease-out; }
        body {
            background-color: #000;
            color: #fff;
        }
        .netflix-red {
            color: #e50914;
        }
        .bg-netflix-red {
            background-color: #e50914;
        }
        .navbar-blur {
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
        }
        
        /* Mobile Footer Navigation - Android TV/Phone Style */
        .mobile-footer-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(20, 20, 20, 0.98);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0.5rem 0;
            z-index: 1000;
            height: 60px;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
        }
        @media (min-width: 768px) {
            .mobile-footer-nav {
                display: none; /* Hide on desktop */
            }
        }
        .mobile-footer-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
            padding: 0.5rem 0.75rem;
            color: #9ca3af;
            text-decoration: none;
            transition: all 0.2s ease;
            border-radius: 0.5rem;
            min-width: 60px;
            flex: 1;
            max-width: 100px;
        }
        .mobile-footer-nav .nav-item svg {
            width: 24px;
            height: 24px;
            stroke-width: 2;
            transition: all 0.2s ease;
        }
        .mobile-footer-nav .nav-item span {
            font-size: 0.7rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .mobile-footer-nav .nav-item:active {
            transform: scale(0.95);
        }
        .mobile-footer-nav .nav-item.active,
        .mobile-footer-nav .nav-item:hover {
            color: #e50914;
            background: rgba(229, 9, 20, 0.1);
        }
        .mobile-footer-nav .nav-item.active svg,
        .mobile-footer-nav .nav-item:hover svg {
            stroke-width: 2.5;
            transform: scale(1.1);
        }
        .mobile-footer-nav .nav-item.active span,
        .mobile-footer-nav .nav-item:hover span {
            font-weight: 600;
        }
        
        /* Add bottom padding to body for mobile footer */
        body {
            padding-bottom: 60px;
        }
        @media (min-width: 768px) {
            body {
                padding-bottom: 0;
            }
        }
    </style>
</head>
<body class="bg-black text-white">
    <nav class="fixed top-0 left-0 right-0 z-50 navbar-blur">
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center space-x-8">
                <a href="<?php echo BASE_URL; ?>/" class="text-3xl font-bold netflix-red"><?php echo strtoupper(htmlspecialchars($site_name)); ?></a>
                <div class="hidden md:flex space-x-6">
                    <a href="<?php echo BASE_URL; ?>/" class="hover:text-gray-300 <?php echo $current_page == 'index.php' ? 'font-bold' : ''; ?>">Home</a>
                    <?php if ($enable_movies): ?>
                    <a href="<?php echo BASE_URL; ?>/movies" class="hover:text-gray-300 <?php echo $current_page == 'movies.php' ? 'font-bold' : ''; ?>">Movies</a>
                    <?php endif; ?>
                    <?php if ($enable_tv_shows): ?>
                    <a href="<?php echo BASE_URL; ?>/tv-shows" class="hover:text-gray-300 <?php echo $current_page == 'tv-shows.php' ? 'font-bold' : ''; ?>">TV Shows</a>
                    <?php endif; ?>
                    <?php if ($enable_live_tv): ?>
                    <a href="<?php echo BASE_URL; ?>/live-tv" class="hover:text-gray-300 <?php echo $current_page == 'live-tv.php' ? 'font-bold' : ''; ?>">Live TV</a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/streampanel.apk" class="hover:text-gray-300 <?php echo $current_page == 'live-tv.php' ? 'font-bold' : ''; ?>">Download APK</a>

                </div>
            </div>
            <div class="flex items-center space-x-4">
                <!-- Search Icon -->
                <button onclick="toggleSearchModal()" class="hover:text-gray-300 transition-colors" aria-label="Search">
                    <i class="fas fa-search text-xl"></i>
                </button>
                <?php if (isLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/profile" class="hover:text-gray-300">
                        <i class="fas fa-user-circle text-2xl"></i>
                    </a>
                    <a href="<?php echo BASE_URL; ?>/logout" class="bg-netflix-red px-4 py-2 rounded hover:bg-red-700">Logout</a>
                <?php elseif (isAdminLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" class="hover:text-gray-300">Admin Panel</a>
                    <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="bg-netflix-red px-4 py-2 rounded hover:bg-red-700">Logout</a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/login" class="hover:text-gray-300">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Search Modal -->
    <div id="search-modal" class="hidden fixed inset-0 z-50 bg-black bg-opacity-90 flex items-start justify-center pt-20 px-4">
        <div class="w-full max-w-4xl">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-3xl font-bold">Search</h2>
                <button onclick="toggleSearchModal()" class="text-gray-400 hover:text-white text-3xl">&times;</button>
            </div>
            <form method="GET" action="<?php echo BASE_URL; ?>/search" class="mb-6">
                <div class="relative">
                    <input type="text" name="q" id="search-input" placeholder="Search movies, actors, TV shows, channels..." 
                           class="w-full bg-gray-900 border-2 border-gray-700 rounded-lg px-6 py-4 text-white text-lg focus:border-netflix-red focus:outline-none"
                           autocomplete="off">
                    <button type="submit" class="absolute right-2 top-2 bg-netflix-red hover:bg-red-700 px-6 py-2 rounded-lg">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
                <div class="flex flex-wrap gap-3 mt-4">
                    <select name="type" class="bg-gray-900 border border-gray-700 rounded px-4 py-2 text-white">
                        <option value="">All Types</option>
                        <option value="movie">Movies</option>
                        <option value="tv-show">TV Shows</option>
                        <option value="live-tv">Live TV</option>
                    </select>
                    <select name="category" class="bg-gray-900 border border-gray-700 rounded px-4 py-2 text-white">
                        <option value="">All Categories</option>
                        <?php
                        $categories = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
                        foreach ($categories as $cat):
                        ?>
                        <option value="<?php echo htmlspecialchars($cat['slug']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <script>
    function toggleSearchModal() {
        const modal = document.getElementById('search-modal');
        modal.classList.toggle('hidden');
        if (!modal.classList.contains('hidden')) {
            document.getElementById('search-input').focus();
        }
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('search-modal');
            if (!modal.classList.contains('hidden')) {
                modal.classList.add('hidden');
            }
        }
    });
    </script>
    <?php include __DIR__ . '/search-suggest.js.php'; ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('search-input');
        if (searchInput && typeof initSearchSuggest === 'function') {
            initSearchSuggest(searchInput, { scope: 'all' });
        }
    });
    </script>
    
    <div class="pt-16">
