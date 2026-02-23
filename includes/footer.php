    </div>
    <?php
    // Ensure BASE_URL is defined
    if (!defined('BASE_URL')) {
        try {
            require_once __DIR__ . '/../config/config.php';
        } catch (Exception $e) {
            define('BASE_URL', 'https://streampanel.live');
        }
    }
    
    // Get site name with error handling
    $site_name = 'StreamPanel'; // Default fallback
    try {
        if (!isset($conn)) {
            require_once __DIR__ . '/../config/database.php';
            require_once __DIR__ . '/../admin/includes/functions.php';
            $conn = getDBConnection();
        }
        if (isset($conn) && function_exists('getSetting')) {
            $site_name = getSetting($conn, 'site_name', 'StreamPanel');
        }
    } catch (Exception $e) {
        // Use default if there's any error
        $site_name = 'StreamPanel';
    }
    ?>
    <footer class="bg-black border-t border-gray-800 mt-20 py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4 netflix-red"><?php echo strtoupper(htmlspecialchars($site_name)); ?></h3>
                    <p class="text-gray-400">Your ultimate streaming destination</p>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Company</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="<?php echo BASE_URL; ?>/about-us" class="hover:text-white">About Us</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/contact" class="hover:text-white">Contact</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/careers" class="hover:text-white">Careers</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="<?php echo BASE_URL; ?>/terms-of-use" class="hover:text-white">Terms of Use</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/privacy-policy" class="hover:text-white">Privacy Policy</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/cookie-policy" class="hover:text-white">Cookie Policy</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-semibold mb-4">Connect</h4>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-2xl"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-2xl"></i></a>
                    </div>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-800 text-center text-gray-400">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_name); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Mobile Footer Navigation - Android TV/Phone Style -->
    <?php
    // Get enabled sections for navigation with error handling
    try {
        if (!isset($conn)) {
            require_once __DIR__ . '/../config/database.php';
            require_once __DIR__ . '/../admin/includes/functions.php';
            $conn = getDBConnection();
        }
        
        if (isset($conn) && function_exists('isSectionEnabled')) {
            if (!isset($enable_movies)) {
                $enable_movies = isSectionEnabled($conn, 'movies');
            }
            if (!isset($enable_tv_shows)) {
                $enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
            }
            if (!isset($enable_live_tv)) {
                $enable_live_tv = isSectionEnabled($conn, 'live_tv');
            }
        } else {
            // Default to enabled if functions not available
            if (!isset($enable_movies)) $enable_movies = true;
            if (!isset($enable_tv_shows)) $enable_tv_shows = true;
            if (!isset($enable_live_tv)) $enable_live_tv = true;
        }
    } catch (Exception $e) {
        // Default to enabled if there's an error
        if (!isset($enable_movies)) $enable_movies = true;
        if (!isset($enable_tv_shows)) $enable_tv_shows = true;
        if (!isset($enable_live_tv)) $enable_live_tv = true;
    }
    
    // Check if Android TV (hide footer on Android TV)
    // Use existing $isAndroidTV if set, otherwise check user agent
    if (!isset($isAndroidTV)) {
        $isAndroidTV = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android TV|AFT|BRAVIA|MiTV|SmartTV|GoogleTV|Tizen|Web0S|HbbTV)/i', $_SERVER['HTTP_USER_AGENT']);
    }
    
    // Get current page for active state
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_path = $_SERVER['REQUEST_URI'] ?? '';
    ?>
    <?php 
    // Only show mobile footer nav on mobile devices (not desktop)
    // Check if it's a mobile device (not desktop browser)
    $isMobile = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Mobile|Android|iPhone|iPad|iPod|BlackBerry|Windows Phone)/i', $_SERVER['HTTP_USER_AGENT']);
    ?>
    <?php if (!$isAndroidTV && $isMobile): ?>
    <nav class="mobile-footer-nav">
        <a href="<?php echo BASE_URL; ?>/" class="nav-item <?php echo ($current_page == 'index.php' || $current_path == '/' || $current_path == BASE_URL . '/') ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            <span>Home</span>
        </a>
        
        <?php if ($enable_movies): ?>
        <a href="<?php echo BASE_URL; ?>/movies" class="nav-item <?php echo ($current_page == 'movies.php' || strpos($current_path, '/movies') !== false) ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            <span>Movies</span>
        </a>
        <?php endif; ?>
        
        <?php if ($enable_tv_shows): ?>
        <a href="<?php echo BASE_URL; ?>/tv-shows" class="nav-item <?php echo ($current_page == 'tv-shows.php' || strpos($current_path, '/tv-shows') !== false || strpos($current_path, '/tv-show/') !== false || $current_page == 'tv-show-detail.php') ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span>TV Shows</span>
        </a>
        <?php endif; ?>
        
        <?php if ($enable_live_tv): ?>
        <a href="<?php echo BASE_URL; ?>/live-tv" class="nav-item <?php echo ($current_page == 'live-tv.php' || strpos($current_path, '/live-tv') !== false || strpos($current_path, '/tv/') !== false) ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                <circle cx="12" cy="12" r="2"></circle>
                <path d="M6 12h.01M18 12h.01"></path>
            </svg>
            <span>Live TV</span>
        </a>
        <?php endif; ?>
        
        <a href="<?php echo BASE_URL; ?>/search" class="nav-item <?php echo ($current_page == 'search.php' || strpos($current_path, '/search') !== false) ? 'active' : ''; ?>">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <span>Search</span>
        </a>
    </nav>
    <?php endif; ?>
</body>
</html>
