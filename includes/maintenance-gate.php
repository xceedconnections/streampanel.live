<?php
/**
 * Site-wide maintenance gate.
 * Blocks all public pages/APIs when maintenance_mode is on.
 * Admin area (/admin/*) and logged-in admins can still access.
 */
if (defined('MAINTENANCE_GATE_DONE')) {
    return;
}
define('MAINTENANCE_GATE_DONE', true);

if (php_sapi_name() === 'cli') {
    return;
}

if (!function_exists('getAppBasePath')) {
    return;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? $requestPath : '/';
$basePath = getAppBasePath();
if ($basePath !== '' && strpos($requestPath, $basePath) === 0) {
    $requestPath = substr($requestPath, strlen($basePath)) ?: '/';
}
if ($requestPath === '' || $requestPath[0] !== '/') {
    $requestPath = '/' . ltrim($requestPath, '/');
}

// Always allow admin panel (including login) during maintenance
if (preg_match('#^/admin(/|$)#i', $requestPath)) {
    return;
}

try {
    require_once __DIR__ . '/../config/database.php';
    $conn = getDBConnection();
    $mode = '0';
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $mode = (string) ($row['setting_value'] ?? '0');
        }
    }
} catch (Throwable $e) {
    return;
}

$enabled = ($mode === '1' || $mode === 'true' || $mode === 'yes');
if (!$enabled) {
    return;
}

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Logged-in admins can browse the public site while maintenance is on
if (!empty($_SESSION['admin_id'])) {
    return;
}

$siteName = 'StreamFlix';
try {
    $nameStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_name' LIMIT 1");
    if ($nameStmt) {
        $nameStmt->execute();
        $nameRow = $nameStmt->get_result()->fetch_assoc();
        if (!empty($nameRow['setting_value'])) {
            $siteName = $nameRow['setting_value'];
        }
    }
} catch (Throwable $e) {
    // keep default
}

http_response_code(503);
header('Retry-After: 3600');
header('Content-Type: text/html; charset=UTF-8');
$safeName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Site Under Maintenance - <?php echo $safeName; ?></title>
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
        <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">Site Under Maintenance</h1>
        <p class="text-xl text-gray-400 mb-8">We're currently performing updates. Please check back soon.</p>
        <button type="button" onclick="location.reload()" class="bg-red-600 hover:bg-red-700 px-6 py-3 rounded font-semibold text-white">
            <i class="fas fa-sync-alt mr-2"></i>Refresh Page
        </button>
    </div>
    <script>
        setTimeout(function () { location.reload(); }, 30000);
    </script>
</body>
</html>
<?php
exit;
