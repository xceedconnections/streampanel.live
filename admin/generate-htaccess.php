<?php
/**
 * Generate .htaccess file based on BASE_URL from database settings
 * This ensures the .htaccess file always matches the site_url setting
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminLogin();

$conn = getDBConnection();
$message = '';
$error = '';
$preview = '';
$htaccess_path = __DIR__ . '/../.htaccess';

// Get BASE_URL from database
$base_url = BASE_URL;

// Extract path from BASE_URL
$parsed_url = parse_url($base_url);
$base_path = isset($parsed_url['path']) ? rtrim($parsed_url['path'], '/') : '';
if (empty($base_path) || $base_path === '/') {
    $base_path = '';
    $rewrite_base = '/';
} else {
    $rewrite_base = $base_path . '/';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['generate']) || isset($_POST['action']))) {
    $action = $_POST['action'] ?? 'preview';
    
    // Generate .htaccess content
    $htaccess_content = generateHtaccessContent($rewrite_base, $base_path);
    
    if ($action === 'write') {
        // Write to file
        if (is_writable($htaccess_path) || (file_exists($htaccess_path) && is_writable(dirname($htaccess_path)))) {
            if (file_put_contents($htaccess_path, $htaccess_content)) {
                $message = '✅ .htaccess file has been successfully generated and written!';
            } else {
                $error = '❌ Failed to write .htaccess file. Please check file permissions.';
            }
        } else {
            $error = '❌ .htaccess file is not writable. Please check file permissions (chmod 644 or 666).';
        }
    } else {
        // Preview mode
        $preview = $htaccess_content;
    }
}

// Generate preview on page load if no action
if (empty($preview) && empty($message) && empty($error)) {
    $preview = generateHtaccessContent($rewrite_base, $base_path);
}

/**
 * Generate .htaccess content based on base path
 */
function generateHtaccessContent($rewrite_base, $base_path) {
    // Escape base_path for use in regex patterns
    $base_path_escaped = preg_quote($base_path, '#');
    
    $content = "RewriteEngine On\n";
    $content .= "RewriteBase {$rewrite_base}\n";
    $content .= "\n";
    
    // Handle API endpoints
    if (!empty($base_path)) {
        $content .= "# IMPORTANT: Handle API endpoints FIRST - rewrite requests without .php back to .php\n";
        $content .= "# This must be before the redirect rule to prevent .php from being stripped\n";
        $content .= "RewriteCond %{REQUEST_URI} ^{$base_path_escaped}/tv/api/viewer_tracker(\\?.*)?\$ [NC]\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $content .= "RewriteRule ^{$base_path_escaped}/tv/api/viewer_tracker\$ tv/api/viewer_tracker.php [L,QSA]\n";
        $content .= "\n";
        
        $content .= "# Allow API endpoints with .php extension to work directly\n";
        $content .= "RewriteCond %{REQUEST_URI} ^{$base_path_escaped}/tv/api/.*\\.php [NC]\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} -f\n";
        $content .= "RewriteRule ^ - [L]\n";
        $content .= "\n";
        
        $content .= "# Allow diagnostic/utility scripts with .php extension to work directly\n";
        $content .= "RewriteCond %{REQUEST_URI} ^{$base_path_escaped}/(fix_user_login_check|_db_check|reset_admin_password)\\.php [NC]\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} -f\n";
        $content .= "RewriteRule ^ - [L]\n";
        $content .= "\n";
        
        $content .= "# Redirect old .php URLs to clean URLs (301 permanent redirect)\n";
        $content .= "# But exclude API endpoints, admin panel, and diagnostic scripts - check THE_REQUEST to see original URL\n";
        $content .= "RewriteCond %{THE_REQUEST} \\s{$base_path_escaped}/([^?\\s]*?)\\.php[\\s?] [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s{$base_path_escaped}/tv/api/viewer_tracker [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s{$base_path_escaped}/admin/ [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s{$base_path_escaped}/api/ [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s{$base_path_escaped}/fix_user_login_check\\.php [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s{$base_path_escaped}/_db_check\\.php [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s{$base_path_escaped}/reset_admin_password\\.php [NC]\n";
        $content .= "RewriteRule ^ %1? [R=301,L]\n";
        $content .= "\n";
        
        $content .= "# Handle root URL (index.php)\n";
        $content .= "RewriteRule ^\$ index.php [L]\n";
        $content .= "\n";
        
        $content .= "# Redirect /tv/{slug} to tv-channel.php with slug parameter (before other rules)\n";
        $content .= "RewriteRule ^tv/([a-z0-9-]+)/?\$ tv/tv-channel.php?slug=\$1 [L,QSA]\n";
        $content .= "\n";
        
        $content .= "# Handle clean URLs without .php extension\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $content .= "RewriteRule ^(live-tv|movies|tv-shows|profile|login|register|watch|manage-devices|tv-show-detail|report|logout|search)\$ \$1.php [L,QSA]\n";
    } else {
        // Root installation (no base path)
        $content .= "# IMPORTANT: Handle API endpoints FIRST - rewrite requests without .php back to .php\n";
        $content .= "# This must be before the redirect rule to prevent .php from being stripped\n";
        $content .= "RewriteCond %{REQUEST_URI} ^/tv/api/viewer_tracker(\\?.*)?\$ [NC]\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $content .= "RewriteRule ^tv/api/viewer_tracker\$ tv/api/viewer_tracker.php [L,QSA]\n";
        $content .= "\n";
        
        $content .= "# Allow API endpoints with .php extension to work directly\n";
        $content .= "RewriteCond %{REQUEST_URI} ^/tv/api/.*\\.php [NC]\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} -f\n";
        $content .= "RewriteRule ^ - [L]\n";
        $content .= "\n";
        
        $content .= "# Allow diagnostic/utility scripts with .php extension to work directly\n";
        $content .= "RewriteCond %{REQUEST_URI} ^/(fix_user_login_check|_db_check|reset_admin_password)\\.php [NC]\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} -f\n";
        $content .= "RewriteRule ^ - [L]\n";
        $content .= "\n";
        
        $content .= "# Redirect old .php URLs to clean URLs (301 permanent redirect)\n";
        $content .= "# But exclude API endpoints, admin panel, and diagnostic scripts - check THE_REQUEST to see original URL\n";
        $content .= "RewriteCond %{THE_REQUEST} \\s/([^?\\s]*?)\\.php[\\s?] [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s/tv/api/viewer_tracker [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s/admin/ [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s/api/ [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s/fix_user_login_check\\.php [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s/_db_check\\.php [NC]\n";
        $content .= "RewriteCond %{THE_REQUEST} !\\s/reset_admin_password\\.php [NC]\n";
        $content .= "RewriteRule ^ %1? [R=301,L]\n";
        $content .= "\n";
        
        $content .= "# Handle root URL (index.php)\n";
        $content .= "RewriteRule ^\$ index.php [L]\n";
        $content .= "\n";
        
        $content .= "# Redirect /tv/{slug} to tv-channel.php with slug parameter (before other rules)\n";
        $content .= "RewriteRule ^tv/([a-z0-9-]+)/?\$ tv/tv-channel.php?slug=\$1 [L,QSA]\n";
        $content .= "\n";
        
        $content .= "# Handle clean URLs without .php extension\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $content .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $content .= "RewriteRule ^(live-tv|movies|tv-shows|profile|login|register|watch|manage-devices|tv-show-detail|report|logout|search)\$ \$1.php [L,QSA]\n";
    }
    
    $content .= "\n";
    $content .= "# Prevent directory listing\n";
    $content .= "Options -Indexes\n";
    $content .= "\n";
    $content .= "# Protect sensitive files\n";
    $content .= "<FilesMatch \"\\.(sql|md|log)\$\">\n";
    $content .= "    Order allow,deny\n";
    $content .= "    Deny from all\n";
    $content .= "</FilesMatch>\n";
    
    return $content;
}

$page_title = "Generate .htaccess";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #141414; color: #e5e5e5; }
        .netflix-red { color: #e50914; }
        .bg-netflix-red { background-color: #e50914; }
        pre { background: #1a1a1a; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; }
    </style>
</head>
<body class="bg-black text-white">
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold mb-2">Generate .htaccess File</h1>
            <p class="text-gray-400">This tool generates the .htaccess file based on your current BASE_URL setting from the database.</p>
        </div>

        <?php if ($message): ?>
            <div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-900 bg-opacity-50 border border-red-700 text-red-200 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bg-gray-900 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Current Configuration</h2>
            <div class="space-y-2">
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 w-32">BASE_URL:</span>
                    <code class="bg-gray-800 px-3 py-1 rounded"><?php echo htmlspecialchars($base_url); ?></code>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 w-32">Base Path:</span>
                    <code class="bg-gray-800 px-3 py-1 rounded"><?php echo htmlspecialchars($base_path ?: '/ (root)'); ?></code>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 w-32">RewriteBase:</span>
                    <code class="bg-gray-800 px-3 py-1 rounded"><?php echo htmlspecialchars($rewrite_base); ?></code>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 w-32">File Path:</span>
                    <code class="bg-gray-800 px-3 py-1 rounded"><?php echo htmlspecialchars($htaccess_path); ?></code>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-gray-400 w-32">Writable:</span>
                    <span class="<?php echo (is_writable($htaccess_path) || (file_exists($htaccess_path) && is_writable(dirname($htaccess_path)))) ? 'text-green-400' : 'text-red-400'; ?>">
                        <?php echo (is_writable($htaccess_path) || (file_exists($htaccess_path) && is_writable(dirname($htaccess_path)))) ? 'Yes' : 'No'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Generated .htaccess Content</h2>
            <pre class="text-sm text-gray-300"><code><?php echo htmlspecialchars($preview); ?></code></pre>
        </div>

        <form method="POST" class="space-y-4" id="htaccess-form">
            <div class="flex gap-4">
                <button type="submit" name="action" value="preview" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded">
                    <i class="fas fa-eye mr-2"></i>Preview Only
                </button>
                <button type="submit" name="action" value="write" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded">
                    <i class="fas fa-save mr-2"></i>Generate & Write to File
                </button>
                <input type="hidden" name="generate" value="1">
            </div>
        </form>

        <div class="mt-6 bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded">
            <p class="font-semibold mb-2">⚠️ Important Notes:</p>
            <ul class="list-disc list-inside space-y-1 text-sm">
                <li>Make sure your BASE_URL in Settings → Site URL is correct before generating.</li>
                <li>The .htaccess file will be overwritten. Make a backup if needed.</li>
                <li>If the file is not writable, you may need to set permissions: <code class="bg-gray-800 px-2 py-1 rounded">chmod 644 .htaccess</code></li>
                <li>After generating, test your site to ensure all URLs work correctly.</li>
                <li>If you change your BASE_URL in settings, regenerate this file.</li>
            </ul>
        </div>

        <div class="mt-6">
            <a href="index.php?tab=settings" class="text-blue-400 hover:text-blue-300">
                <i class="fas fa-arrow-left mr-2"></i>Back to Settings
            </a>
        </div>
    </div>

</body>
</html>
