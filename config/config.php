<?php
// Auto-detect base URL
function getBaseUrl() {
    // CLI / cron-safe: if no HTTP context, return a sensible default
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        // Adjust this to your primary site URL if needed
        return 'https://streampanel.live';
    }

    $serverPort = isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 80;
    $httpsOn = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $protocol = ($httpsOn || $serverPort === 443) ? "https://" : "http://";

    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = dirname($script);
    
    // Remove /admin if it's in the path (when accessed from admin panel)
    $path = str_replace('/admin', '', $path);
    
    // Remove /api if it's in the path (when accessed from API directory)
    $path = str_replace('/api', '', $path);
    
    // Remove /tv if it's in the path (when accessed from TV directory)
    $path = str_replace('/tv', '', $path);
    
    // Remove /stream if it's in the path (for subdirectory installations)
    // This handles both /stream/ and /stream cases
    $path = preg_replace('#^/(/|$)#', '/', $path);
    
    $basePath = rtrim($path, '/');
    
    // If basePath is empty or just '/', return root URL
    if (empty($basePath) || $basePath === '/') {
        return $protocol . $host;
    }
    
    return $protocol . $host . $basePath;
}

// Get BASE_URL from database settings (site_url) - this is the primary source
// If not found in database, fall back to auto-detection
$BASE_URL = null;

try {
    require_once __DIR__ . '/database.php';
    $conn = getDBConnection();
    
    // Get from database settings - this is the PRIMARY source
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_url'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc() && !empty($row['setting_value'])) {
            $base_url_setting = trim($row['setting_value']);
            $base_url_setting = rtrim($base_url_setting, '/');
            if (!empty($base_url_setting)) {
                $BASE_URL = $base_url_setting;
            }
        }
    }
} catch (Exception $e) {
    // Settings table might not exist yet, will use auto-detected URL below
}

// If database setting not found or empty, use auto-detection as fallback
if (empty($BASE_URL)) {
    $BASE_URL = getBaseUrl();
}

// Site configuration
define('SITE_NAME', 'StreamFlix'); // Will be overridden by settings
define('SITE_URL', $BASE_URL);
define('BASE_URL', $BASE_URL);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('IMAGE_BASE_URL', 'https://image.tmdb.org/t/p');
define('FALLBACK_POSTER', 'https://via.placeholder.com/500x750?text=No+Image');

// Timezone
date_default_timezone_set('UTC');
?>
