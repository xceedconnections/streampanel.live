<?php
/**
 * Admin Password Reset Script
 * Run this once to reset admin password, then delete this file for security
 */

require_once __DIR__ . '/config/database.php';

$new_password = 'admin123'; // Change this to your desired password
$admin_username = 'admin'; // Admin username to reset

$conn = getDBConnection();

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update admin password
$stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $hashed_password, $admin_username);

if ($stmt->execute()) {
    echo "<h2 style='color: green;'>✓ Admin password reset successfully!</h2>";
    echo "<p><strong>Username:</strong> " . htmlspecialchars($admin_username) . "</p>";
    echo "<p><strong>New Password:</strong> " . htmlspecialchars($new_password) . "</p>";
    echo "<p style='color: red;'><strong>⚠ IMPORTANT:</strong> Please delete this file (reset_admin_password.php) after use for security!</p>";
    echo "<p><a href='admin/login.php'>Go to Admin Login</a></p>";
} else {
    echo "<h2 style='color: red;'>✗ Error resetting password: " . $stmt->error . "</h2>";
}

$stmt->close();
$conn->close();
?>
