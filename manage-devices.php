<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$page_title = "Manage Devices";
$conn = getDBConnection();

// Define sanitize function if not already defined
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

// Handle device removal - user is already logged in
if (isset($_GET['remove_device'])) {
    $session_record_id = sanitize($_GET['remove_device']);
    
    // User should be logged in (either normally or from device limit scenario)
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        // Delete the session record (this logs out that device)
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("si", $session_record_id, $user_id);
        $stmt->execute();
        
        // Clear device limit flag if set
        unset($_SESSION['device_limit_exceeded']);
        unset($_SESSION['device_limit_max']);
        
        // Check if we're in device limit scenario (user has temp session)
        if (isset($_GET['device_limit']) || isset($_SESSION['device_limit_exceeded']) || isset($_SESSION['temp_session'])) {
            // Check if we're now under the limit
            $stmt = $conn->prepare("SELECT max_devices FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_data = $stmt->get_result()->fetch_assoc();
            $max_devices = $user_data['max_devices'] ?? 2;
            
            $active_count = getUserActiveSessionsCount($user_id);
            
            if ($active_count < $max_devices) {
                // Now under limit, create real session and redirect to home page
                // User was logged in with temp session, now we can create real session
                try {
                    $device_name = $_SESSION['temp_device_name'] ?? '';
                    createUserSession($user_id, session_id(), $device_name);
                    unset($_SESSION['temp_session']);
                    unset($_SESSION['temp_device_name']);
                } catch (Exception $e) {
                    error_log("Error creating session after device removal: " . $e->getMessage());
                }
                
                // Clear device limit flags
                unset($_SESSION['device_limit_exceeded']);
                unset($_SESSION['device_limit_max']);
                
                header('Location: ' . BASE_URL . '/');
                exit();
            } else {
                // Still at limit, refresh page to show updated list
                header('Location: ' . BASE_URL . '/manage-devices?device_limit=1');
                exit();
            }
        } else {
            // Normal device removal - redirect to home page
            header('Location: ' . BASE_URL . '/');
            exit();
        }
    } else {
        // Not logged in, redirect to login
        header('Location: login.php');
        exit();
    }
}

// Handle device limit exceeded scenario - user is already logged in (may have temp session)
if (isset($_GET['device_limit']) || isset($_SESSION['device_limit_exceeded'])) {
    // User is logged in (may have temp session that's not in database yet)
    // Don't use requireLogin() here as it might redirect if session validation fails
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $show_device_limit = true;
        
        // Get user info
        $stmt = $conn->prepare("SELECT max_devices FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        $max_devices = $user_data['max_devices'] ?? 2;
        
        // Get active sessions (from database - temp session won't be here)
        $stmt = $conn->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        error_log("Manage devices - Device limit scenario. User ID: " . $user_id . ", Sessions count: " . count($sessions) . ", Max: " . $max_devices);
    } else {
        // Not logged in, redirect to login
        error_log("Manage devices - No user_id in session, redirecting to login");
        header('Location: login.php');
        exit();
    }
} else {
    requireLogin();
    $user_id = $_SESSION['user_id'];
    $show_device_limit = false;
}

// Get user's max devices limit (if not already set)
if (!isset($max_devices)) {
    $user = $conn->prepare("SELECT max_devices FROM users WHERE id = ?");
    $user->bind_param("i", $user_id);
    $user->execute();
    $user_data = $user->get_result()->fetch_assoc();
    $max_devices = $user_data['max_devices'] ?? 2;
}

// Get all active sessions for this user (if not already set)
if (!isset($sessions)) {
    $stmt = $conn->prepare("SELECT * FROM user_sessions WHERE user_id = ? ORDER BY last_activity DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get current session ID (always get it, user is logged in)
// If user has temp_session flag, they don't have a session in database yet
$current_session_id = isset($_SESSION['temp_session']) ? '' : session_id();

include 'includes/header.php';
?>

<style>
.device-card {
    background: #1f2937;
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
    border: 2px solid #374151;
    transition: all 0.2s;
}
.device-card:hover {
    border-color: #4b5563;
    background: #374151;
}
.device-card.current {
    border-color: #10b981;
    background: #064e3b;
}
.device-info {
    display: flex;
    align-items: center;
    gap: 1rem;
}
.device-icon {
    width: 48px;
    height: 48px;
    background: #374151;
    border-radius: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
</style>

<div class="container mx-auto px-4 py-8 max-w-4xl">
    <h1 class="text-4xl font-bold mb-2">Manage Devices</h1>
    <?php if ($show_device_limit): ?>
    <div class="bg-yellow-900 bg-opacity-50 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <p class="font-semibold">⚠ Device Limit Reached</p>
        <p class="text-sm mt-1">You are logged in on <?php echo count($sessions); ?> device(s), but your maximum is <?php echo $max_devices; ?>. Please remove a device below to continue using this device.</p>
    </div>
    <?php else: ?>
    <p class="text-gray-400 mb-6">You are logged in on <?php echo count($sessions); ?> of <?php echo $max_devices; ?> allowed devices</p>
    <?php endif; ?>
    
    <div class="space-y-4">
        <?php if (empty($sessions)): ?>
        <div class="text-center py-12">
            <i class="fas fa-mobile-alt text-6xl text-gray-700 mb-4"></i>
            <p class="text-gray-400 text-lg">No active devices</p>
        </div>
        <?php else: ?>
        <?php foreach ($sessions as $session): ?>
        <div class="device-card <?php echo $session['session_id'] === $current_session_id ? 'current' : ''; ?>">
            <div class="flex items-center justify-between">
                <div class="device-info flex-1">
                    <div class="device-icon">
                        <i class="fas fa-<?php 
                            $user_agent = $session['user_agent'] ?? '';
                            if (stripos($user_agent, 'mobile') !== false || stripos($user_agent, 'android') !== false || stripos($user_agent, 'iphone') !== false) {
                                echo 'mobile-alt';
                            } elseif (stripos($user_agent, 'tablet') !== false || stripos($user_agent, 'ipad') !== false) {
                                echo 'tablet-alt';
                            } else {
                                echo 'desktop';
                            }
                        ?>"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg">
                            <?php 
                            // Show device name if available, otherwise try to extract from user agent
                            if (!empty($session['device_name'])) {
                                echo htmlspecialchars($session['device_name']);
                            } else {
                                $user_agent = $session['user_agent'] ?? 'Unknown Device';
                                if (preg_match('/(iPhone|iPad|Android|Windows|Mac|Linux)/i', $user_agent, $matches)) {
                                    echo htmlspecialchars($matches[1]);
                                } else {
                                    echo 'Device';
                                }
                            }
                            ?>
                            <?php if ($session['session_id'] === $current_session_id): ?>
                            <span class="ml-2 px-2 py-1 bg-green-900 text-green-200 rounded text-xs">Current Device</span>
                            <?php endif; ?>
                        </h3>
                        <p class="text-sm text-gray-400 mt-1">
                            <i class="fas fa-map-marker-alt mr-1"></i>
                            IP: <?php echo htmlspecialchars($session['ip_address'] ?? 'Unknown'); ?>
                        </p>
                        <p class="text-sm text-gray-400">
                            <i class="fas fa-clock mr-1"></i>
                            Last active: <?php echo date('M d, Y h:i A', strtotime($session['last_activity'])); ?>
                        </p>
                    </div>
                </div>
                <?php if ($session['session_id'] !== $current_session_id): ?>
                <a href="?remove_device=<?php echo urlencode($session['id']); ?><?php echo $show_device_limit ? '&device_limit=1' : ''; ?>" 
                   class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded font-semibold">
                    <i class="fas fa-trash mr-2"></i><?php echo $show_device_limit ? 'Remove Device and Login here' : 'Remove Device'; ?>
                </a>
                <?php else: ?>
                <span class="px-4 py-2 text-gray-500 text-sm">Current Device</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="mt-8 text-center">
        <?php if ($show_device_limit): ?>
        <a href="<?php echo BASE_URL; ?>/" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold inline-block">
            <i class="fas fa-home mr-2"></i>Go to Home
        </a>
        <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Back to Home
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
