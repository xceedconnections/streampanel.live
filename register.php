<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Initialize database structure to ensure all required tables and columns exist
initializeDatabase();

$page_title = "Sign Up";
$error = '';
$success = '';

// Generate captcha if not set or if form was submitted (regenerate on error)
if (!isset($_SESSION['captcha_answer']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha_answer']))) {
    $num1 = rand(1, 10);
    $num2 = rand(1, 10);
    $_SESSION['captcha_num1'] = $num1;
    $_SESSION['captcha_num2'] = $num2;
    $_SESSION['captcha_answer'] = $num1 + $num2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $captcha_answer = isset($_POST['captcha_answer']) ? intval($_POST['captcha_answer']) : 0;
    $stored_answer = $_SESSION['captcha_answer'] ?? 0;
    
    // Validate captcha
    if ($captcha_answer !== $stored_answer) {
        $error = 'Incorrect captcha answer. Please try again.';
        // Regenerate captcha on error
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $_SESSION['captcha_num1'] = $num1;
        $_SESSION['captcha_num2'] = $num2;
        $_SESSION['captcha_answer'] = $num1 + $num2;
    } elseif (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        if (registerUser($username, $email, $password, $full_name)) {
            // Clear captcha after successful registration
            unset($_SESSION['captcha_answer']);
            unset($_SESSION['captcha_num1']);
            unset($_SESSION['captcha_num2']);
            
            // Auto-login after registration
            if (loginUser($username, $password, '')) {
               $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . '/';
                unset($_SESSION['redirect_after_login']);
                header('Location: ' . $redirect);
                exit();
            } else {
                $success = 'Registration successful! Redirecting to login...';
                header('Location: login.php?registered=1');
                exit();
            }
        } else {
            // Get more specific error message
            $conn = getDBConnection();
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
            $error = 'Username or email already exists';
            } else {
                $error = 'Registration failed. Please try again or contact support.';
                error_log("Registration failed for: $username / $email - Database error: " . $conn->error);
            }
        }
    }
}

if (isLoggedIn()) {
    header('Location: /');
    exit();
}

include 'includes/header.php';
?>

<div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-black via-gray-900 to-black py-20">
    <div class="bg-gray-900 bg-opacity-90 p-8 rounded-lg w-full max-w-md">
        <h1 class="text-4xl font-bold mb-8 text-center netflix-red">Streampanel</h1>
        <h2 class="text-2xl font-bold mb-6 text-center">Sign Up</h2>
        
        <?php if ($error): ?>
        <div class="bg-red-900 bg-opacity-50 border border-red-700 text-red-200 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-4">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-4">
                <input type="text" name="full_name" placeholder="Full Name" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red">
            </div>
            <div class="mb-4">
                <input type="text" name="username" placeholder="Username" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
            </div>
            <div class="mb-4">
                <input type="email" name="email" placeholder="Email" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
            </div>
            <div class="mb-6">
                <input type="password" name="password" placeholder="Password (min 6 characters)" 
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
        
        <div class="mt-6 text-center">
            <p class="text-gray-400">Already have an account? <a href="login.php" class="text-white hover:underline">Sign in</a></p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
