<?php
/**
 * API Endpoint: Get users who redeemed a coupon
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();

header('Content-Type: application/json');

if (!isset($_GET['coupon_id'])) {
    echo json_encode(['error' => 'Coupon ID is required']);
    exit;
}

$coupon_id = intval($_GET['coupon_id']);
$conn = getDBConnection();

// Get users who redeemed the coupon
$stmt = $conn->prepare("SELECT cr.*, u.username, u.email, u.full_name, u.subscription_expires_at 
    FROM coupon_redemptions cr 
    JOIN users u ON cr.user_id = u.id 
    WHERE cr.coupon_id = ? 
    ORDER BY cr.redeemed_at DESC");
$stmt->bind_param("i", $coupon_id);
$stmt->execute();
$coupon_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get coupon details
$stmt = $conn->prepare("SELECT * FROM coupons WHERE id = ?");
$stmt->bind_param("i", $coupon_id);
$stmt->execute();
$viewed_coupon = $stmt->get_result()->fetch_assoc();

echo json_encode([
    'coupon' => $viewed_coupon,
    'users' => $coupon_users
]);
exit;
