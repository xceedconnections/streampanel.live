<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$page_title = "Profile";
$conn = getDBConnection();
$user_id = $_SESSION['user_id'];

$message = '';
$message_type = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All password fields are required';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New password and confirm password do not match';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'New password must be at least 6 characters long';
        $message_type = 'error';
    } else {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        
        if (!$user_data || !password_verify($current_password, $user_data['password'])) {
            $message = 'Current password is incorrect';
            $message_type = 'error';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $message = 'Password changed successfully';
                $message_type = 'success';
            } else {
                $message = 'Error changing password';
                $message_type = 'error';
            }
        }
    }
}

// Handle coupon redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem_coupon'])) {
    $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    
    if (empty($coupon_code)) {
        $message = 'Please enter a coupon code';
        $message_type = 'error';
    } else {
        // Check if coupon exists and is valid
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
        $stmt->bind_param("s", $coupon_code);
        $stmt->execute();
        $coupon = $stmt->get_result()->fetch_assoc();
        
        if (!$coupon) {
            $message = 'Invalid or inactive coupon code';
            $message_type = 'error';
        } else {
            // Check if coupon has expired
            if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < time()) {
                $message = 'This coupon has expired';
                $message_type = 'error';
            } else {
                // Check if user has already used this coupon
                $stmt = $conn->prepare("SELECT id FROM coupon_redemptions WHERE coupon_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $coupon['id'], $user_id);
                $stmt->execute();
                $already_used = $stmt->get_result()->num_rows > 0;
                
                if ($already_used) {
                    $message = 'You have already used this coupon';
                    $message_type = 'error';
                } else {
                    // Check if coupon has reached max uses (0 means unlimited)
                    if ($coupon['max_uses'] > 0) {
                        $stmt = $conn->prepare("SELECT COUNT(*) as used_count FROM coupon_redemptions WHERE coupon_id = ?");
                        $stmt->bind_param("i", $coupon['id']);
                        $stmt->execute();
                        $used_count = $stmt->get_result()->fetch_assoc()['used_count'];
                        
                        if ($used_count >= $coupon['max_uses']) {
                            $message = 'This coupon has reached its maximum uses';
                            $message_type = 'error';
                        } else {
                            // Proceed with redemption
                            $proceed_redemption = true;
                        }
                    } else {
                        // max_uses is 0, meaning unlimited - proceed with redemption
                        $proceed_redemption = true;
                    }
                    
                    if (isset($proceed_redemption) && $proceed_redemption) {
                        // Calculate expiry date
                        $expires_at = date('Y-m-d H:i:s', strtotime('+' . $coupon['duration_days'] . ' days'));
                        
                        // Update user subscription
                        $stmt = $conn->prepare("UPDATE users SET subscription_type = 'premium', subscription_expires_at = ?, subscription_started_at = NOW() WHERE id = ?");
                        $stmt->bind_param("si", $expires_at, $user_id);
                        $stmt->execute();
                        
                        // Record redemption
                        $stmt = $conn->prepare("INSERT INTO coupon_redemptions (coupon_id, user_id) VALUES (?, ?)");
                        $stmt->bind_param("ii", $coupon['id'], $user_id);
                        $stmt->execute();
                        
                        // Update session
                        $_SESSION['subscription_type'] = 'premium';
                        
                        $message = 'Coupon redeemed successfully! Your premium subscription expires on ' . date('F d, Y', strtotime($expires_at));
                        $message_type = 'success';
                    }
                }
            }
        }
    }
}

// Get user info
$user = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user->bind_param("i", $user_id);
$user->execute();
$user = $user->get_result()->fetch_assoc();

// Get active tab
$active_tab = $_GET['tab'] ?? 'history';

// Ensure watch_history table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS watch_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content_type ENUM('movie', 'tv_episode', 'tv_show', 'live_tv') NOT NULL,
        content_id INT NOT NULL,
        watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        progress INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_content (user_id, content_type, content_id),
        INDEX idx_watched_at (watched_at)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Ensure favorites table exists
try {
    $conn->query("CREATE TABLE IF NOT EXISTS favorites (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content_type ENUM('movie', 'tv_show', 'live_tv') NOT NULL,
        content_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_favorite (user_id, content_type, content_id),
        INDEX idx_user_id (user_id)
    )");
} catch (Exception $e) {
    // Table might already exist
}

// Get watch history with content details
$history_query = "SELECT wh.*, 
    m.title as movie_title, m.thumbnail as movie_thumbnail, m.poster as movie_poster,
    t.title as tv_show_title, t.thumbnail as tv_show_thumbnail, t.id as tv_show_id,
    e.title as episode_title, e.thumbnail as episode_thumbnail, e.tv_show_id as episode_tv_show_id,
    ltv.name as channel_name, ltv.logo as channel_logo
    FROM watch_history wh
    LEFT JOIN movies m ON wh.content_type = 'movie' AND wh.content_id = m.id
    LEFT JOIN tv_episodes e ON wh.content_type = 'tv_episode' AND wh.content_id = e.id
    LEFT JOIN tv_shows t ON e.tv_show_id = t.id
    LEFT JOIN live_tv_channels ltv ON wh.content_type = 'live_tv' AND wh.content_id = ltv.id
    WHERE wh.user_id = ?";
    
// Filter by content type if specified
$content_filter = $_GET['content_type'] ?? '';
if ($content_filter && in_array($content_filter, ['movie', 'tv_show', 'live_tv'])) {
    // Map tv_show filter to tv_episode for watch_history
    $filter_type = ($content_filter === 'tv_show') ? 'tv_episode' : $content_filter;
    $history_query .= " AND wh.content_type = ?";
    $stmt = $conn->prepare($history_query . " ORDER BY wh.watched_at DESC LIMIT 100");
    $stmt->bind_param("is", $user_id, $filter_type);
} else {
    $stmt = $conn->prepare($history_query . " ORDER BY wh.watched_at DESC LIMIT 100");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$watch_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get favorites with content details
$favorites_query = "SELECT f.*,
    m.title as movie_title, m.thumbnail as movie_thumbnail, m.poster as movie_poster,
    t.title as tv_show_title, t.thumbnail as tv_show_thumbnail, t.poster as tv_show_poster,
    ltv.name as channel_name, ltv.logo as channel_logo
    FROM favorites f
    LEFT JOIN movies m ON f.content_type = 'movie' AND f.content_id = m.id
    LEFT JOIN tv_shows t ON f.content_type = 'tv_show' AND f.content_id = t.id
    LEFT JOIN live_tv_channels ltv ON f.content_type = 'live_tv' AND f.content_id = ltv.id
    WHERE f.user_id = ?";
    
// Filter favorites by content type if specified
if ($active_tab === 'favorites' && $content_filter && in_array($content_filter, ['movie', 'tv_show', 'live_tv'])) {
    $favorites_query .= " AND f.content_type = ?";
    $stmt = $conn->prepare($favorites_query . " ORDER BY f.created_at DESC");
    $stmt->bind_param("is", $user_id, $content_filter);
} else {
    $stmt = $conn->prepare($favorites_query . " ORDER BY f.created_at DESC");
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$user_favorites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get last 5 redeemed coupons
$stmt = $conn->prepare("SELECT cr.*, c.code, c.description, c.duration_days 
    FROM coupon_redemptions cr 
    JOIN coupons c ON cr.coupon_id = c.id 
    WHERE cr.user_id = ? 
    ORDER BY cr.redeemed_at DESC 
    LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$redeemed_coupons = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include 'includes/header.php';
?>

<style>
.tab-button {
    padding: 0.75rem 1.5rem;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: #9ca3af;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}
.tab-button:hover {
    color: #fff;
}
.tab-button.active {
    color: #e50914;
    border-bottom-color: #e50914;
}
.content-type-filter {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 1rem;
    flex-wrap: wrap;
}
.filter-btn {
    padding: 0.5rem 1rem;
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 0.5rem;
    color: #9ca3af;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 0.875rem;
}
.filter-btn:hover {
    background: #374151;
    color: #fff;
}
.filter-btn.active {
    background: #e50914;
    border-color: #e50914;
    color: #fff;
}
.history-item, .favorite-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #141414;
    border-radius: 0.5rem;
    transition: all 0.2s;
}
.history-item:hover, .favorite-item:hover {
    background: #1f2937;
    transform: translateX(4px);
}
.content-thumbnail {
    width: 120px;
    height: 68px;
    object-fit: cover;
    border-radius: 0.25rem;
    flex-shrink: 0;
}
@media (min-width: 640px) {
    .content-thumbnail {
        width: 160px;
        height: 90px;
    }
}
@media (min-width: 768px) {
    .content-thumbnail {
        width: 200px;
        height: 112px;
    }
}
.history-item, .favorite-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #141414;
    border-radius: 0.5rem;
    transition: all 0.2s;
    margin-bottom: 0.75rem;
}
.history-item:hover, .favorite-item:hover {
    background: #1f2937;
    transform: translateX(4px);
}
.content-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (min-width: 640px) {
    .content-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 768px) {
    .content-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (min-width: 1024px) {
    .content-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
@media (min-width: 1280px) {
    .content-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
.content-card {
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
    transition: all 0.3s;
    cursor: pointer;
}
.content-card:hover {
    transform: scale(1.05);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
}
.content-card-image {
    width: 100%;
    aspect-ratio: 16/9;
    object-fit: cover;
    background: #1f2937;
}
.content-card-body {
    padding: 1rem;
}
.content-card-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.content-card-meta {
    font-size: 0.875rem;
    color: #9ca3af;
}

/* Live TV Channel Card Styling (same as live-tv.php) */
.live-tv-channel-card {
    position: relative;
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}
.live-tv-channel-card:hover {
    transform: scale(1.05);
}
.live-tv-channel-logo {
    height: 110px;
    background: linear-gradient(to bottom right, rgba(229,9,20,0.2), rgba(37,99,235,0.2));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.live-tv-channel-logo img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    padding: 0.5rem;
}
.live-tv-channel-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.live-tv-channel-card:hover .live-tv-channel-overlay {
    opacity: 1;
}
.live-tv-channel-play-icon {
    background: #e50914;
    border-radius: 50%;
    padding: 0.75rem;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.live-tv-channel-info {
    padding: 0.75rem;
}
.live-tv-channel-info h3 {
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
}
.live-tv-channel-info p {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
}
.coupon-history-item {
    padding: 0.75rem;
    background: #1f2937;
    border-radius: 0.5rem;
    margin-bottom: 0.5rem;
    border-left: 3px solid #e50914;
}
</style>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-4xl font-bold mb-8">My Profile</h1>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Profile Info & Coupon Section -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Profile Info -->
            <div class="bg-gray-900 rounded-lg p-6">
                <div class="text-center mb-6">
                    <div class="w-32 h-32 mx-auto bg-gray-800 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-user text-5xl text-gray-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></h2>
                    <p class="text-gray-400">@<?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                <div class="space-y-3">
                    <div>
                        <p class="text-gray-400 text-sm">Email</p>
                        <p class="text-white"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Subscription</p>
                        <p class="text-white capitalize">
                            <?php 
                            $subscription_type = $user['subscription_type'] ?? 'free';
                            if ($user['subscription_expires_at']) {
                                $expires = new DateTime($user['subscription_expires_at']);
                                $now = new DateTime();
                                if ($expires > $now) {
                                    echo '<span class="text-yellow-400 font-semibold">Premium</span>';
                                    echo '<br><span class="text-xs text-gray-400">Expires: ' . date('M d, Y', strtotime($user['subscription_expires_at'])) . '</span>';
                                } else {
                                    echo 'Free (Expired)';
                                }
                            } else {
                                echo ucfirst($subscription_type);
                            }
                            ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-gray-400 text-sm">Member Since</p>
                        <p class="text-white"><?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-800 space-y-2">
                        <a href="manage-devices.php" class="block w-full bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-center transition">
                            <i class="fas fa-mobile-alt mr-2"></i>Manage Devices
                        </a>
                        <button onclick="document.getElementById('change-password-form').classList.toggle('hidden')" 
                                class="block w-full bg-gray-800 hover:bg-gray-700 px-4 py-2 rounded text-center transition">
                            <i class="fas fa-key mr-2"></i>Change Password
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Form -->
            <div id="change-password-form" class="bg-gray-900 rounded-lg p-6 hidden">
                <h3 class="text-xl font-bold mb-4">Change Password</h3>
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Current Password</label>
                        <input type="password" name="current_password" required
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">New Password</label>
                        <input type="password" name="new_password" required minlength="6"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                        <p class="text-xs text-gray-400 mt-1">Must be at least 6 characters long</p>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-semibold mb-2">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" name="change_password" 
                                class="flex-1 bg-netflix-red px-4 py-2 rounded hover:bg-red-700 font-semibold">
                            Change Password
                        </button>
                        <button type="button" onclick="document.getElementById('change-password-form').classList.add('hidden')" 
                                class="bg-gray-700 px-4 py-2 rounded hover:bg-gray-600">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Coupon Redemption -->
            <div class="bg-gray-900 rounded-lg p-6">
                <h3 class="text-xl font-bold mb-4">Redeem Coupon</h3>
                <form method="POST" action="">
                    <div class="mb-4">
                        <input type="text" name="coupon_code" placeholder="Enter coupon code" 
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white uppercase"
                               style="text-transform: uppercase;" required>
                    </div>
                    <button type="submit" name="redeem_coupon" 
                            class="w-full bg-netflix-red px-4 py-2 rounded hover:bg-red-700 font-semibold">
                        Redeem Coupon
                    </button>
                </form>
                
                <?php if (!empty($redeemed_coupons)): ?>
                <div class="mt-6">
                    <h4 class="text-lg font-semibold mb-3">Recent Coupons</h4>
                    <div class="space-y-2">
                        <?php foreach ($redeemed_coupons as $redemption): ?>
                        <div class="coupon-history-item">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-semibold text-yellow-400"><?php echo htmlspecialchars($redemption['code']); ?></span>
                                <span class="text-xs text-gray-400"><?php echo date('M d, Y', strtotime($redemption['redeemed_at'])); ?></span>
                            </div>
                            <?php if (!empty($redemption['description'])): ?>
                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($redemption['description']); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo $redemption['duration_days']; ?> days premium</p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tabs Content -->
        <div class="lg:col-span-2">
            <!-- Tabs Navigation -->
            <div class="flex border-b border-gray-800 mb-6">
                <button class="tab-button <?php echo $active_tab === 'favorites' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?tab=favorites'">
                    <i class="fas fa-heart mr-2"></i>Favorites
                </button>
                <button class="tab-button <?php echo $active_tab === 'history' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?tab=history'">
                    <i class="fas fa-history mr-2"></i>History
                </button>
            </div>
            
            <!-- Content Type Filters -->
            <div class="content-type-filter">
                <button class="filter-btn <?php echo empty($content_filter) ? 'active' : ''; ?>" 
                        onclick="window.location.href='?tab=<?php echo $active_tab; ?>'">
                    All
                </button>
                <button class="filter-btn <?php echo $content_filter === 'movie' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?tab=<?php echo $active_tab; ?>&content_type=movie'">
                    Movies
                </button>
                <button class="filter-btn <?php echo $content_filter === 'tv_show' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?tab=<?php echo $active_tab; ?>&content_type=tv_show'">
                    TV Shows
                </button>
                <button class="filter-btn <?php echo $content_filter === 'live_tv' ? 'active' : ''; ?>" 
                        onclick="window.location.href='?tab=<?php echo $active_tab; ?>&content_type=live_tv'">
                    Live TV
                </button>
            </div>
            
            <!-- Favorites Tab -->
            <?php if ($active_tab === 'favorites'): ?>
            <div>
                <?php if (!empty($user_favorites)): ?>
                <div class="content-grid">
                    <?php foreach ($user_favorites as $fav): ?>
                    <?php
                    $is_live_tv = ($fav['content_type'] === 'live_tv');
                    $card_class = $is_live_tv ? 'live-tv-channel-card' : 'content-card';
                    ?>
                    <a href="<?php 
                        if ($fav['content_type'] === 'tv_show') {
                            $tv_show_stmt = $conn->prepare("SELECT slug FROM tv_shows WHERE id = ?");
                            $tv_show_stmt->bind_param("i", $fav['content_id']);
                            $tv_show_stmt->execute();
                            $tv_show_result = $tv_show_stmt->get_result()->fetch_assoc();
                            if (!empty($tv_show_result['slug'])) {
                                echo 'tv-show-detail.php?slug=' . htmlspecialchars($tv_show_result['slug']);
                            } else {
                                echo 'tv-show-detail.php?id=' . $fav['content_id'];
                            }
                        } elseif ($fav['content_type'] === 'live_tv') {
                            $channel_stmt = $conn->prepare("SELECT slug FROM live_tv_channels WHERE id = ?");
                            $channel_stmt->bind_param("i", $fav['content_id']);
                            $channel_stmt->execute();
                            $channel_result = $channel_stmt->get_result()->fetch_assoc();
                            if (!empty($channel_result['slug'])) {
                                echo 'tv/' . htmlspecialchars($channel_result['slug']);
                            } else {
                                echo 'tv/tv-channel.php?id=' . $fav['content_id'];
                            }
                        } else {
                            echo 'watch.php?type=' . $fav['content_type'] . '&id=' . $fav['content_id'];
                        }
                    ?>" class="<?php echo $card_class; ?>">
                        <?php
                        $thumbnail = '';
                        $title = '';
                        if ($fav['content_type'] === 'movie') {
                            $thumbnail = $fav['movie_thumbnail'] ?? $fav['movie_poster'] ?? '';
                            $title = $fav['movie_title'] ?? 'Movie #' . $fav['content_id'];
                        } elseif ($fav['content_type'] === 'tv_show') {
                            $thumbnail = $fav['tv_show_thumbnail'] ?? $fav['tv_show_poster'] ?? '';
                            $title = $fav['tv_show_title'] ?? 'TV Show #' . $fav['content_id'];
                        } elseif ($fav['content_type'] === 'live_tv') {
                            $thumbnail = $fav['channel_logo'] ?? '';
                            $title = $fav['channel_name'] ?? 'Channel #' . $fav['content_id'];
                        }
                        ?>
                        
                        <?php if ($is_live_tv): ?>
                        <!-- Live TV Channel Styling -->
                        <div class="live-tv-channel-logo">
                            <?php if ($thumbnail): ?>
                            <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($title); ?>" 
                                 onerror="this.style.display='none'">
                            <?php else: ?>
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                                <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                                <polyline points="17 2 12 7 7 2"></polyline>
                            </svg>
                            <?php endif; ?>
                            <div class="live-tv-channel-overlay">
                                <div class="live-tv-channel-play-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21"></polygon>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="live-tv-channel-info">
                            <h3><?php echo htmlspecialchars($title); ?></h3>
                            <p><?php echo date('M d, Y', strtotime($fav['created_at'])); ?></p>
                        </div>
                        <?php else: ?>
                        <!-- Movie/TV Show Styling -->
                        <?php if ($thumbnail): ?>
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($title); ?>" 
                             class="content-card-image" 
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/placeholder.jpg'; this.onerror=null;">
                        <?php else: ?>
                        <div class="content-card-image bg-gray-800 flex items-center justify-center">
                            <i class="fas fa-<?php echo $fav['content_type'] === 'movie' ? 'film' : 'tv'; ?> text-4xl text-gray-600"></i>
                        </div>
                        <?php endif; ?>
                        <div class="content-card-body">
                            <h3 class="content-card-title"><?php echo htmlspecialchars($title); ?></h3>
                            <p class="content-card-meta">
                                <?php echo ucfirst(str_replace('_', ' ', $fav['content_type'])); ?>
                                <span class="mx-2">•</span>
                                <?php echo date('M d, Y', strtotime($fav['created_at'])); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-heart text-6xl text-gray-700 mb-4"></i>
                    <p class="text-gray-400 text-lg">No favorites yet</p>
                    <p class="text-gray-500 text-sm mt-2">Start adding content to your favorites!</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- History Tab -->
            <?php if ($active_tab === 'history'): ?>
            <div>
                <?php if (!empty($watch_history)): ?>
                <div class="content-grid">
                    <?php foreach ($watch_history as $item): ?>
                    <?php
                    $is_live_tv = ($item['content_type'] === 'live_tv');
                    $card_class = $is_live_tv ? 'live-tv-channel-card' : 'content-card';
                    ?>
                    <a href="<?php 
                        if ($item['content_type'] === 'tv_episode') {
                            $tv_show_id = $item['episode_tv_show_id'] ?? $item['tv_show_id'] ?? null;
                            if ($tv_show_id) {
                                $tv_show_stmt = $conn->prepare("SELECT slug FROM tv_shows WHERE id = ?");
                                $tv_show_stmt->bind_param("i", $tv_show_id);
                                $tv_show_stmt->execute();
                                $tv_show_result = $tv_show_stmt->get_result()->fetch_assoc();
                                if (!empty($tv_show_result['slug'])) {
                                    echo BASE_URL . '/tv-show/' . htmlspecialchars($tv_show_result['slug']);
                                } else {
                                    echo BASE_URL . '/tv-show-detail?id=' . $tv_show_id;
                                }
                            } else {
                                echo '#';
                            }
                        } elseif ($item['content_type'] === 'live_tv') {
                            $channel_stmt = $conn->prepare("SELECT slug FROM live_tv_channels WHERE id = ?");
                            $channel_stmt->bind_param("i", $item['content_id']);
                            $channel_stmt->execute();
                            $channel_result = $channel_stmt->get_result()->fetch_assoc();
                            if (!empty($channel_result['slug'])) {
                                echo 'tv/' . htmlspecialchars($channel_result['slug']);
                            } else {
                                echo 'tv/tv-channel.php?id=' . $item['content_id'];
                            }
                        } else {
                            echo 'watch.php?type=' . $item['content_type'] . '&id=' . $item['content_id'];
                        }
                    ?>" class="<?php echo $card_class; ?>">
                        <?php
                        $thumbnail = '';
                        $title = '';
                        if ($item['content_type'] === 'movie') {
                            $movie_stmt = $conn->prepare("SELECT thumbnail, poster FROM movies WHERE id = ?");
                            $movie_stmt->bind_param("i", $item['content_id']);
                            $movie_stmt->execute();
                            $movie_result = $movie_stmt->get_result()->fetch_assoc();
                            $thumbnail = $item['movie_thumbnail'] ?? $movie_result['thumbnail'] ?? $movie_result['poster'] ?? '';
                            $title = $item['movie_title'] ?? 'Movie #' . $item['content_id'];
                        } elseif ($item['content_type'] === 'tv_episode') {
                            $thumbnail = $item['episode_thumbnail'] ?? $item['tv_show_thumbnail'] ?? '';
                            $show_title = $item['tv_show_title'] ?? 'TV Show';
                            $episode_title = $item['episode_title'] ?? 'Episode';
                            $title = $show_title . ' - ' . $episode_title;
                        } elseif ($item['content_type'] === 'live_tv') {
                            $thumbnail = $item['channel_logo'] ?? '';
                            $title = $item['channel_name'] ?? 'Channel #' . $item['content_id'];
                        }
                        ?>
                        
                        <?php if ($is_live_tv): ?>
                        <!-- Live TV Channel Styling -->
                        <div class="live-tv-channel-logo">
                            <?php if ($thumbnail): ?>
                            <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($title); ?>" 
                                 onerror="this.style.display='none'">
                            <?php else: ?>
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                                <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                                <polyline points="17 2 12 7 7 2"></polyline>
                            </svg>
                            <?php endif; ?>
                            <div class="live-tv-channel-overlay">
                                <div class="live-tv-channel-play-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                        <polygon points="5 3 19 12 5 21"></polygon>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="live-tv-channel-info">
                            <h3><?php echo htmlspecialchars($title); ?></h3>
                            <p>
                                <?php 
                                $watched_time = strtotime($item['watched_at']);
                                $now = time();
                                $diff = $now - $watched_time;
                                if ($diff < 3600) {
                                    echo 'Just now';
                                } elseif ($diff < 86400) {
                                    echo date('g:i A', $watched_time);
                                } elseif ($diff < 604800) {
                                    echo date('D, M d', $watched_time);
                                } else {
                                    echo date('M d, Y', $watched_time);
                                }
                                ?>
                            </p>
                        </div>
                        <?php else: ?>
                        <!-- Movie/TV Show Styling -->
                        <?php if ($thumbnail): ?>
                        <img src="<?php echo htmlspecialchars($thumbnail); ?>" alt="<?php echo htmlspecialchars($title); ?>" 
                             class="content-card-image" 
                             onerror="this.src='<?php echo BASE_URL; ?>/assets/placeholder.jpg'; this.onerror=null;">
                        <?php else: ?>
                        <div class="content-card-image bg-gray-800 flex items-center justify-center">
                            <i class="fas fa-<?php echo $item['content_type'] === 'movie' ? 'film' : 'tv'; ?> text-4xl text-gray-600"></i>
                        </div>
                        <?php endif; ?>
                        <div class="content-card-body">
                            <h3 class="content-card-title"><?php echo htmlspecialchars($title); ?></h3>
                            <p class="content-card-meta">
                                <?php 
                                $type_label = $item['content_type'] === 'tv_episode' ? 'TV Show' : ucfirst(str_replace('_', ' ', $item['content_type']));
                                echo $type_label;
                                ?>
                                <span class="mx-2">•</span>
                                <?php 
                                $watched_time = strtotime($item['watched_at']);
                                $now = time();
                                $diff = $now - $watched_time;
                                if ($diff < 3600) {
                                    echo 'Just now';
                                } elseif ($diff < 86400) {
                                    echo date('g:i A', $watched_time);
                                } elseif ($diff < 604800) {
                                    echo date('D, M d', $watched_time);
                                } else {
                                    echo date('M d, Y', $watched_time);
                                }
                                ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <i class="fas fa-history text-6xl text-gray-700 mb-4"></i>
                    <p class="text-gray-400 text-lg">No watch history yet</p>
                    <p class="text-gray-500 text-sm mt-2">Start watching to see your history here!</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
