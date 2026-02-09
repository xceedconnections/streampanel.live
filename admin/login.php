<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Allow admin login even during maintenance
$page_title = "Admin Login";
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (loginAdmin($username, $password)) {
        header('Location: index.php?tab=dashboard');
        exit();
    } else {
        $error = 'Invalid username or password';
    }
}

if (isAdminLoggedIn()) {
    header('Location: index.php?tab=dashboard');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - StreamPanel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #141414; color: #e5e5e5; }
        .netflix-red { color: #e50914; }
        .bg-netflix-red { background-color: #e50914; }
    </style>
</head>
<body class="bg-black text-white">
<div class="min-h-screen flex items-center justify-center bg-gradient-to-b from-black via-gray-900 to-black py-20">
    <div class="bg-gray-900 bg-opacity-90 p-8 rounded-lg w-full max-w-md">
        <h1 class="text-4xl font-bold mb-8 text-center netflix-red">StreamPanel</h1>
        <h2 class="text-2xl font-bold mb-6 text-center">Admin Login</h2>
        
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
            <div class="mb-6">
                <input type="password" name="password" placeholder="Password" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red" required>
            </div>
            <button type="submit" class="w-full bg-netflix-red text-white py-3 rounded font-semibold hover:bg-red-700 transition">
                Sign In
            </button>
        </form>
        
        <div class="mt-4 text-center">
            <a href="../index.php" class="text-gray-400 hover:text-white text-sm">Back to Home</a>
        </div>
    </div>
</div>
</body>
</html>
