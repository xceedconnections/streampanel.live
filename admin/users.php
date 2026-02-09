<?php
/**
 * Admin Panel - Users Management
 */
$page_title = "Manage Users";

$message = '';
$message_type = '';

// Check if banned column exists, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'banned'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN banned BOOLEAN DEFAULT FALSE");
}

// Check if max_devices column exists, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM users LIKE 'max_devices'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN max_devices INT DEFAULT 2");
}

// Create user_sessions table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_id (session_id)
)");

// Handle add new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = sanitize($_POST['full_name'] ?? '');
    $max_devices = intval($_POST['max_devices'] ?? 2);
    $subscription_type = sanitize($_POST['subscription_type'] ?? 'free');
    
    if (empty($username) || empty($email) || empty($password)) {
        $message = 'Username, email, and password are required';
        $message_type = 'error';
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $message = 'Username or email already exists';
            $message_type = 'error';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, max_devices, subscription_type) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssis", $username, $email, $hashed_password, $full_name, $max_devices, $subscription_type);
            
            if ($stmt->execute()) {
                $message = 'User added successfully';
                $message_type = 'success';
            } else {
                $message = 'Error adding user: ' . $stmt->error;
                $message_type = 'error';
            }
        }
    }
    
    if ($message_type === 'success') {
        if (headers_sent()) {
            echo '<script>window.location.href = "?tab=users";</script>';
            exit;
        } else {
            header("Location: ?tab=users");
            exit;
        }
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    $selected_ids = array_map('intval', $_POST['selected_users']);
    $action = $_POST['bulk_action'];
    $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
    
    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' user(s) deleted successfully';
        $message_type = 'success';
    } elseif ($action === 'ban') {
        $stmt = $conn->prepare("UPDATE users SET banned = 1 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' user(s) banned successfully';
        $message_type = 'success';
    } elseif ($action === 'unban') {
        $stmt = $conn->prepare("UPDATE users SET banned = 0 WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' user(s) unbanned successfully';
        $message_type = 'success';
    } elseif ($action === 'remove_subscription') {
        $stmt = $conn->prepare("UPDATE users SET subscription_type = 'free', subscription_expires_at = NULL, subscription_started_at = NULL WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selected_ids)), ...$selected_ids);
        $stmt->execute();
        $message = count($selected_ids) . ' user subscription(s) removed successfully';
        $message_type = 'success';
    }
}

// Handle max devices update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_max_devices'])) {
    $user_id = intval($_POST['user_id']);
    $max_devices = intval($_POST['max_devices']);
    $stmt = $conn->prepare("UPDATE users SET max_devices = ? WHERE id = ?");
    $stmt->bind_param("ii", $max_devices, $user_id);
    $stmt->execute();
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=users";</script>';
        exit;
    } else {
        header("Location: ?tab=users");
        exit;
    }
}

// Handle single user actions
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'User deleted successfully';
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=users";</script>';
        exit;
    } else {
        header("Location: ?tab=users");
        exit;
    }
}

if (isset($_GET['ban'])) {
    $id = intval($_GET['ban']);
    $stmt = $conn->prepare("UPDATE users SET banned = 1 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'User banned successfully';
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=users";</script>';
        exit;
    } else {
        header("Location: ?tab=users");
        exit;
    }
}

if (isset($_GET['unban'])) {
    $id = intval($_GET['unban']);
    $stmt = $conn->prepare("UPDATE users SET banned = 0 WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'User unbanned successfully';
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=users";</script>';
        exit;
    } else {
        header("Location: ?tab=users");
        exit;
    }
}

if (isset($_GET['remove_subscription'])) {
    $id = intval($_GET['remove_subscription']);
    $stmt = $conn->prepare("UPDATE users SET subscription_type = 'free', subscription_expires_at = NULL, subscription_started_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = 'User subscription removed successfully';
    $message_type = 'success';
    if (headers_sent()) {
        echo '<script>window.location.href = "?tab=users";</script>';
        exit;
    } else {
        header("Location: ?tab=users");
        exit;
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_user_password'])) {
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = 'Password fields are required';
        $message_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = 'Passwords do not match';
        $message_type = 'error';
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long';
        $message_type = 'error';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $message = 'User password changed successfully';
            $message_type = 'success';
        } else {
            $message = 'Error changing password';
            $message_type = 'error';
        }
    }
    
    if ($message_type === 'success') {
        if (headers_sent()) {
            echo '<script>window.location.href = "?tab=users";</script>';
            exit;
        } else {
            header("Location: ?tab=users");
            exit;
        }
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query with search
$query = "SELECT u.*, 
    (SELECT c.code FROM coupon_redemptions cr 
     JOIN coupons c ON cr.coupon_id = c.id 
     WHERE cr.user_id = u.id 
     ORDER BY cr.redeemed_at DESC LIMIT 1) as last_coupon_code,
    (SELECT cr.redeemed_at FROM coupon_redemptions cr 
     WHERE cr.user_id = u.id 
     ORDER BY cr.redeemed_at DESC LIMIT 1) as last_coupon_date
    FROM users u WHERE 1=1";
$params = [];
$types = '';

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.full_name LIKE ?";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
    
    // Also search in coupon codes
    $query .= " OR EXISTS (SELECT 1 FROM coupon_redemptions cr 
                JOIN coupons c ON cr.coupon_id = c.id 
                WHERE cr.user_id = u.id AND c.code LIKE ?)";
    $params[] = $search_param;
    $types .= 's';
    $query .= ")";
}

$query .= " ORDER BY u.created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $users = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
}
?>
<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-4xl font-bold mb-2">Manage Users</h1>
        <p class="text-gray-400">View and manage user accounts</p>
    </div>
    <button onclick="document.getElementById('add-user-form').classList.toggle('hidden')" 
            class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
        <i class="fas fa-plus mr-2"></i>Add New User
    </button>
</div>

<!-- Add User Form -->
<div id="add-user-form" class="bg-gray-900 rounded-lg p-6 mb-6 hidden">
    <h2 class="text-2xl font-bold mb-4">Add New User</h2>
    <form method="POST" action="">
        <input type="hidden" name="add_user" value="1">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Username *</label>
                <input type="text" name="username" required
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Email *</label>
                <input type="email" name="email" required
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Password *</label>
                <input type="password" name="password" required
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Full Name</label>
                <input type="text" name="full_name"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Max Devices</label>
                <input type="number" name="max_devices" value="2" min="1" max="10"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Maximum number of devices user can login simultaneously</p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Subscription Type</label>
                <select name="subscription_type" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="free">Free</option>
                    <option value="premium">Premium</option>
                </select>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
                Add User
            </button>
            <button type="button" onclick="document.getElementById('add-user-form').classList.add('hidden')" 
                    class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                Cancel
            </button>
        </div>
    </form>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Search and Filter -->
<div class="bg-gray-900 rounded-lg p-6 mb-6">
    <form method="GET" action="" class="flex flex-col md:flex-row gap-2">
        <input type="hidden" name="tab" value="users">
        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
               placeholder="Search by username, email, name, or coupon code..." 
               class="flex-1 bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
            <i class="fas fa-search mr-2"></i>Search
        </button>
        <?php if (!empty($search)): ?>
        <a href="?tab=users" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 flex items-center justify-center">
            <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Bulk Actions -->
<div class="bg-gray-900 rounded-lg p-6 mb-6">
    <form method="POST" action="" id="bulk-action-form" onsubmit="return confirmBulkAction()">
        <div class="flex flex-col md:flex-row gap-2 items-start md:items-center mb-4">
            <select name="bulk_action" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
                <option value="">Bulk Actions</option>
                <option value="ban">Ban Users</option>
                <option value="unban">Unban Users</option>
                <option value="remove_subscription">Remove Subscription</option>
                <option value="delete">Delete Users</option>
            </select>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded font-semibold">
                Apply
            </button>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-800">
                        <th class="text-left p-3 w-12">
                            <input type="checkbox" id="select-all-users" onchange="toggleAllUsers(this)">
                        </th>
                        <th class="text-left p-3">ID</th>
                        <th class="text-left p-3">Username</th>
                        <th class="text-left p-3">Email</th>
                        <th class="text-left p-3">Full Name</th>
                        <th class="text-left p-3">Subscription</th>
                        <th class="text-left p-3">Coupon</th>
                        <th class="text-left p-3">Max Devices</th>
                        <th class="text-left p-3">Status</th>
                        <th class="text-left p-3">Joined</th>
                        <th class="text-left p-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php 
                    $is_premium = ($user['subscription_type'] ?? 'free') === 'premium';
                    $has_active_subscription = false;
                    if ($user['subscription_expires_at']) {
                        $expires = new DateTime($user['subscription_expires_at']);
                        $now = new DateTime();
                        $has_active_subscription = $expires > $now;
                    }
                    ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800">
                        <td class="p-3">
                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" 
                                   class="user-checkbox w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded">
                        </td>
                        <td class="p-3"><?php echo $user['id']; ?></td>
                        <td class="p-3">
                            <div class="font-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                        </td>
                        <td class="p-3"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                <span class="px-2 py-1 rounded text-xs <?php echo $is_premium && $has_active_subscription ? 'bg-yellow-900 text-yellow-200' : 'bg-gray-700 text-gray-300'; ?>">
                                    <?php echo ucfirst($user['subscription_type'] ?? 'free'); ?>
                                </span>
                                <?php if ($user['subscription_expires_at']): ?>
                                <span class="text-xs text-gray-400">
                                    <?php 
                                    $expires = new DateTime($user['subscription_expires_at']);
                                    $now = new DateTime();
                                    if ($expires > $now) {
                                        $diff = $now->diff($expires);
                                        echo $diff->days . ' days left';
                                    } else {
                                        echo 'Expired';
                                    }
                                    ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="p-3">
                            <?php if (!empty($user['last_coupon_code'])): ?>
                            <div class="flex flex-col gap-1">
                                <span class="px-2 py-1 rounded text-xs bg-green-900 text-green-200 font-mono">
                                    <?php echo htmlspecialchars($user['last_coupon_code']); ?>
                                </span>
                                <?php if ($user['last_coupon_date']): ?>
                                <span class="text-xs text-gray-400">
                                    <?php echo date('M d, Y', strtotime($user['last_coupon_date'])); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <span class="text-gray-500 text-sm">None</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3">
                            <form method="POST" class="inline" action="?tab=users">
                                <input type="hidden" name="update_max_devices" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="number" name="max_devices" value="<?php echo $user['max_devices'] ?? 2; ?>" 
                                       min="1" max="10" 
                                       onchange="this.form.submit()"
                                       class="w-20 bg-gray-800 border border-gray-700 rounded px-2 py-1 text-white text-sm">
                            </form>
                        </td>
                        <td class="p-3">
                            <?php if ($user['banned'] ?? false): ?>
                            <span class="px-2 py-1 rounded text-xs bg-red-900 text-red-200">Banned</span>
                            <?php else: ?>
                            <span class="px-2 py-1 rounded text-xs bg-green-900 text-green-200">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                        <td class="p-3">
                            <div class="flex flex-col gap-1">
                                <button onclick="showChangePasswordModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')" 
                                        class="text-blue-400 hover:text-blue-300 text-sm text-left">Change Password</button>
                                <?php if ($user['banned'] ?? false): ?>
                                <a href="?tab=users&unban=<?php echo $user['id']; ?>" 
                                   onclick="return confirm('Unban this user?')" 
                                   class="text-green-400 hover:text-green-300 text-sm">Unban</a>
                                <?php else: ?>
                                <a href="?tab=users&ban=<?php echo $user['id']; ?>" 
                                   onclick="return confirm('Ban this user?')" 
                                   class="text-orange-400 hover:text-orange-300 text-sm">Ban</a>
                                <?php endif; ?>
                                <?php if ($is_premium && $has_active_subscription): ?>
                                <a href="?tab=users&remove_subscription=<?php echo $user['id']; ?>" 
                                   onclick="return confirm('Remove subscription from this user?')" 
                                   class="text-yellow-400 hover:text-yellow-300 text-sm">Remove Subscription</a>
                                <?php endif; ?>
                                <a href="?tab=users&delete=<?php echo $user['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to delete this user?')" 
                                   class="text-red-400 hover:text-red-300 text-sm">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </form>
</div>

<script>
function toggleAllUsers(checkbox) {
    const checkboxes = document.querySelectorAll('.user-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function confirmBulkAction() {
    const selected = document.querySelectorAll('.user-checkbox:checked');
    const action = document.querySelector('[name="bulk_action"]').value;
    
    if (selected.length === 0) {
        alert('Please select at least one user');
        return false;
    }
    
    if (!action) {
        alert('Please select a bulk action');
        return false;
    }
    
    const actionText = {
        'delete': 'delete',
        'ban': 'ban',
        'unban': 'unban',
        'remove_subscription': 'remove subscription from'
    }[action] || action;
    
    return confirm(`Are you sure you want to ${actionText} ${selected.length} user(s)?`);
}

function showChangePasswordModal(userId, username) {
    document.getElementById('change-password-user-id').value = userId;
    document.getElementById('change-password-username').textContent = username;
    document.getElementById('change-password-modal').classList.remove('hidden');
}

function closeChangePasswordModal() {
    document.getElementById('change-password-modal').classList.add('hidden');
    document.getElementById('change-password-form').reset();
}
</script>

<!-- Change Password Modal -->
<div id="change-password-modal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
    <div class="bg-gray-900 rounded-lg p-6 max-w-md w-full mx-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Change Password</h2>
            <button onclick="closeChangePasswordModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <p class="text-gray-400 mb-4">Change password for user: <span id="change-password-username" class="font-semibold text-white"></span></p>
        <form method="POST" action="" id="change-password-form">
            <input type="hidden" name="change_user_password" value="1">
            <input type="hidden" name="user_id" id="change-password-user-id">
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
                <button type="submit" class="flex-1 bg-netflix-red px-4 py-2 rounded hover:bg-red-700 font-semibold">
                    Change Password
                </button>
                <button type="button" onclick="closeChangePasswordModal()" 
                        class="bg-gray-700 px-4 py-2 rounded hover:bg-gray-600">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
