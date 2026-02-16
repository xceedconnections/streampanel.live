<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

// Initialize database structure to ensure all required tables and columns exist
initializeDatabase();

$conn = getDBConnection();
$site_name = getSetting($conn, 'site_name', 'StreamPanel');

$page_title = "Sign In";
$error = '';

// Generate captcha for signup form if not set
if (!isset($_SESSION['captcha_answer'])) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
}

// Handle redirect parameter
if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $device_name = trim($_POST['device_name'] ?? '');
    
    if (empty($username) || empty($password)) {
        $error = 'Username/email and password are required';
    } else {
    $login_result = loginUser($username, $password, $device_name);
    
    if ($login_result === true) {
        // Redirect to the page they were trying to access, or home
            $redirect = $_GET['redirect'] ?? $_SESSION['redirect_after_login'] ?? BASE_URL . '/';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit();
        } elseif ($login_result === 'device_limit') {
            // User is logged in but device limit exceeded - redirect to device management
            // Session variables are already set in loginUser function
            // Verify session is active
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Debug: Log session state
            error_log("Login device_limit - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . ", Session status: " . session_status());
            
            // Double-check that user_id is set (should be set by loginUser)
            if (!isset($_SESSION['user_id'])) {
                // If somehow not set, try to get it from database and set it
                $conn = getDBConnection();
                $stmt = $conn->prepare("SELECT id, username, full_name, subscription_type FROM users WHERE username = ? OR email = ?");
                $stmt->bind_param("ss", $username, $username);
                $stmt->execute();
                $user_data = $stmt->get_result()->fetch_assoc();
                
                if ($user_data) {
                    $_SESSION['user_id'] = $user_data['id'];
                    $_SESSION['username'] = $user_data['username'];
                    $_SESSION['full_name'] = $user_data['full_name'] ?? '';
                    $_SESSION['subscription_type'] = $user_data['subscription_type'] ?? 'free';
                    $_SESSION['device_limit_exceeded'] = true;
                    $_SESSION['temp_session'] = true;
                } else {
                    $error = 'Login failed. Please try again.';
                    error_log("Device limit: Could not find user in database");
                }
            }
            
            // Redirect to device management if user_id is set
            if (isset($_SESSION['user_id'])) {
                error_log("Redirecting to manage-devices.php?device_limit=1");
                header('Location: manage-devices.php?device_limit=1');
                exit();
            }
    } elseif ($login_result === 'banned') {
        // Get user ID for banned user (sessions already deleted in loginUser function)
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND (banned = 1 OR banned = '1')");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $banned_user = $stmt->get_result()->fetch_assoc();
        
        if ($banned_user) {
            // Start a new session for the banned user to access report page
            session_start();
            $_SESSION['banned_user_id'] = $banned_user['id'];
            header('Location: report.php?banned=1');
            exit();
        } else {
            $error = 'Your account has been banned. Please contact support.';
        }
        } else {
            // More detailed error - check what actually failed
            $conn = getDBConnection();
            $check_stmt = $conn->prepare("SELECT id, username, email, banned FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 0) {
                $error = 'User not found. Please check your username/email.';
            } else {
                $check_user = $check_result->fetch_assoc();
                $banned_value = $check_user['banned'] ?? 0;
                if ($banned_value == 1 || $banned_value === '1') {
                    $error = 'Your account has been banned. Please contact support.';
    } else {
                    $error = 'Invalid password. Please try again or reset your password.';
                }
            }
        }
    }
}

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . '/');
    exit();
}

include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-black via-gray-900 to-black py-20">
    <div class="bg-gray-900 bg-opacity-90 p-8 rounded-lg w-full max-w-md">
        <h1 class="text-4xl font-bold mb-8 text-center netflix-red"><?php echo strtoupper(htmlspecialchars($site_name)); ?></h1>
        
        <!-- Login/Signup Tabs -->
        <div class="flex mb-6 border-b border-gray-700">
            <button onclick="showLogin()" id="login-tab" class="flex-1 py-3 text-center font-semibold border-b-2 border-netflix-red text-white">
                Sign In
            </button>
            <button onclick="showSignup()" id="signup-tab" class="flex-1 py-3 text-center font-semibold text-gray-400 hover:text-white">
                Sign Up
            </button>
        </div>
        
        <!-- Login Form -->
        <div id="login-form">
            <h2 class="text-2xl font-bold mb-6 text-center">Sign In to Continue</h2>
            
            <?php if (isset($_GET['session_expired'])): ?>
            <div class="bg-yellow-900 bg-opacity-50 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-4">
                <p class="font-semibold">Session Expired</p>
                <p class="text-sm mt-1">Your session was terminated because you logged in on another device. Please sign in again.</p>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="bg-red-900 bg-opacity-50 border border-red-700 text-red-200 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <input type="text" name="username" placeholder="Username or Email" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
                </div>
                <div class="mb-4">
                    <input type="password" name="password" placeholder="Password" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
                </div>
                <div class="mb-6">
                    <input type="text" name="device_name" placeholder="Device Name (Optional, e.g., My Phone, Work Laptop)" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red">
                    <p class="text-xs text-gray-400 mt-1">Give this device a name to easily identify it later</p>
                </div>
                <button type="submit" class="w-full bg-netflix-red text-white py-3 rounded font-semibold hover:bg-red-700 transition">
                    Sign In
                </button>
            </form>
        </div>
        
        <!-- Signup Form (Hidden by default) -->
        <div id="signup-form" class="hidden">
            <h2 class="text-2xl font-bold mb-6 text-center">Create Your Account</h2>
            <p class="text-gray-400 text-sm mb-6 text-center">Join <?php echo htmlspecialchars($site_name); ?> to access unlimited movies, TV shows, and live TV</p>
            
            <form method="POST" action="register.php">
                <div class="mb-4">
                    <input type="text" name="username" placeholder="Username" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
                </div>
                <div class="mb-4">
                    <input type="email" name="email" placeholder="Email" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
                </div>
                <div class="mb-4">
                    <input type="text" name="full_name" placeholder="Full Name (Optional)" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red">
                </div>
                <div class="mb-6">
                    <input type="password" name="password" placeholder="Password" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
                </div>
                <div class="mb-6">
                    <div class="flex items-center gap-3">
                        <label class="text-white font-semibold whitespace-nowrap">
                            <?php echo $_SESSION['captcha_num1'] ?? 0; ?> + <?php echo $_SESSION['captcha_num2'] ?? 0; ?> = ?
                        </label>
                        <input type="number" name="captcha_answer" placeholder="Answer" 
                               class="flex-1 bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" 
                               required min="0" max="20">
                    </div>
                </div>
                <button type="submit" class="w-full bg-netflix-red text-white py-3 rounded font-semibold hover:bg-red-700 transition">
                    Sign Up
                </button>
            </form>
        </div>
        
        <div class="mt-6 text-center">
            <p class="text-gray-400 text-sm">By continuing, you agree to <?php echo htmlspecialchars($site_name); ?>'s Terms of Service and Privacy Policy</p>
        </div>
    </div>
</div>

<script>
function showLogin() {
    document.getElementById('login-form').classList.remove('hidden');
    document.getElementById('signup-form').classList.add('hidden');
    document.getElementById('login-tab').classList.add('border-b-2', 'border-netflix-red', 'text-white');
    document.getElementById('login-tab').classList.remove('text-gray-400');
    document.getElementById('signup-tab').classList.remove('border-b-2', 'border-netflix-red', 'text-white');
    document.getElementById('signup-tab').classList.add('text-gray-400');
}

function showSignup() {
    document.getElementById('signup-form').classList.remove('hidden');
    document.getElementById('login-form').classList.add('hidden');
    document.getElementById('signup-tab').classList.add('border-b-2', 'border-netflix-red', 'text-white');
    document.getElementById('signup-tab').classList.remove('text-gray-400');
    document.getElementById('login-tab').classList.remove('border-b-2', 'border-netflix-red', 'text-white');
    document.getElementById('login-tab').classList.add('text-gray-400');
}

// Show signup form if coming from register link
<?php if (isset($_GET['signup']) || isset($_GET['register'])): ?>
showSignup();
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
