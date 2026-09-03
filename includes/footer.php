    <?php if (!empty($site_content_wrapper_open)): ?>
    </div>
    <?php endif; ?>
    <?php
    // Ensure BASE_URL is defined
    if (!defined('BASE_URL')) {
        try {
            require_once __DIR__ . '/../config/config.php';
        } catch (Exception $e) {
            define('BASE_URL', getBaseUrl());
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
        $site_name = 'StreamPanel';
    }
    ?>
    <?php if (empty($minimal_site_footer)): ?>
    <footer class="site-footer-desktop bg-black border-t border-gray-800 mt-20 py-12">
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
    <?php endif; ?>

    <?php
    if (empty($mobile_nav_path)) {
        $mobile_nav_path = $_SERVER['REQUEST_URI'] ?? '';
    }
    include __DIR__ . '/mobile-nav.php';
    ?>
</body>
</html>
