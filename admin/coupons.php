<?php
/**
 * Admin Panel - Coupons Management
 */
$page_title = "Manage Coupons";

$message = '';
$message_type = '';

// Check for success message from redirect
if (isset($_GET['success'])) {
    $message = 'Coupon ' . ($_GET['edit'] ? 'updated' : 'added') . ' successfully';
    $message_type = 'success';
}

// Note: Delete and POST actions are now handled in admin/index.php before output starts
// to avoid "headers already sent" errors

$coupons = $conn->query("SELECT c.*, COUNT(cr.id) as used_count FROM coupons c LEFT JOIN coupon_redemptions cr ON c.id = cr.coupon_id GROUP BY c.id ORDER BY c.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$edit_coupon = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_coupon = $conn->prepare("SELECT * FROM coupons WHERE id = ?");
    $edit_coupon->bind_param("i", $edit_id);
    $edit_coupon->execute();
    $edit_coupon = $edit_coupon->get_result()->fetch_assoc();
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Coupons</h1>
    <p class="text-gray-400">Create and manage subscription coupon codes</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4"><?php echo $edit_coupon ? 'Edit' : 'Add'; ?> Coupon</h2>
    <form method="POST" action="">
        <?php if ($edit_coupon): ?>
        <input type="hidden" name="id" value="<?php echo $edit_coupon['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Coupon Code *</label>
                <input type="text" name="code" value="<?php echo htmlspecialchars($edit_coupon['code'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required
                       style="text-transform: uppercase;">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Duration (Days)</label>
                <select name="duration_days" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="30" <?php echo ($edit_coupon['duration_days'] ?? 30) == 30 ? 'selected' : ''; ?>>30 days</option>
                    <option value="90" <?php echo ($edit_coupon['duration_days'] ?? 30) == 90 ? 'selected' : ''; ?>>90 days</option>
                    <option value="180" <?php echo ($edit_coupon['duration_days'] ?? 30) == 180 ? 'selected' : ''; ?>>180 days</option>
                    <option value="365" <?php echo ($edit_coupon['duration_days'] ?? 30) == 365 ? 'selected' : ''; ?>>365 days</option>
                </select>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Max Uses</label>
                <input type="number" name="max_uses" value="<?php echo $edit_coupon['max_uses'] ?? 1; ?>" min="0"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Enter 0 for unlimited uses (each email can only use once)</p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Expires At (Optional)</label>
                <input type="datetime-local" name="expires_at" 
                       value="<?php echo $edit_coupon['expires_at'] ? date('Y-m-d\TH:i', strtotime($edit_coupon['expires_at'])) : ''; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_coupon['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" <?php echo ($edit_coupon['is_active'] ?? 1) ? 'checked' : ''; ?> 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <?php echo $edit_coupon ? 'Update' : 'Add'; ?> Coupon
        </button>
        <?php if ($edit_coupon): ?>
        <a href="?tab=coupons" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All Coupons</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Code</th>
                    <th class="text-left p-3">Duration</th>
                    <th class="text-left p-3">Used</th>
                    <th class="text-left p-3">Max Uses</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Expires</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($coupons as $coupon): ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3 font-mono font-bold"><?php echo htmlspecialchars($coupon['code']); ?></td>
                    <td class="p-3"><?php echo $coupon['duration_days']; ?> days</td>
                    <td class="p-3"><?php echo $coupon['used_count']; ?></td>
                    <td class="p-3"><?php echo $coupon['max_uses'] == 0 ? '<span class="text-green-400 font-semibold">Unlimited</span>' : $coupon['max_uses']; ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?php echo $coupon['is_active'] ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                            <?php echo $coupon['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="p-3"><?php echo $coupon['expires_at'] ? date('Y-m-d', strtotime($coupon['expires_at'])) : 'Never'; ?></td>
                    <td class="p-3">
                        <?php if ($coupon['used_count'] > 0): ?>
                        <a href="?tab=coupons&view_users=<?php echo $coupon['id']; ?>" 
                           class="text-green-400 hover:text-green-300 mr-3" 
                           onclick="event.preventDefault(); showUsersModal(<?php echo $coupon['id']; ?>, '<?php echo htmlspecialchars($coupon['code'], ENT_QUOTES); ?>');">
                            <i class="fas fa-users mr-1"></i>View Users (<?php echo $coupon['used_count']; ?>)
                        </a>
                        <?php endif; ?>
                        <a href="?tab=coupons&edit=<?php echo $coupon['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                        <a href="?tab=coupons&delete=<?php echo $coupon['id']; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="text-red-400 hover:text-red-300">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Users Modal -->
<div id="users-modal" class="fixed inset-0 bg-black bg-opacity-75 z-50 hidden flex items-center justify-center p-4">
    <div class="bg-gray-900 rounded-lg max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-6 border-b border-gray-800 flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-bold">Users Who Redeemed</h3>
                <p class="text-gray-400 mt-1" id="modal-coupon-code"></p>
            </div>
            <button onclick="closeUsersModal()" class="text-gray-400 hover:text-white text-2xl">&times;</button>
        </div>
        <div class="flex-1 overflow-y-auto p-6">
            <div id="users-modal-content">
                <p class="text-gray-400 text-center">Loading...</p>
            </div>
        </div>
    </div>
</div>

<script>
function showUsersModal(couponId, couponCode) {
    const modal = document.getElementById('users-modal');
    const modalContent = document.getElementById('users-modal-content');
    const modalCouponCode = document.getElementById('modal-coupon-code');
    
    modalCouponCode.textContent = 'Coupon: ' + couponCode;
    modal.classList.remove('hidden');
    modalContent.innerHTML = '<div class="text-center py-8"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-netflix-red"></div><p class="text-gray-400 mt-4">Loading users...</p></div>';
    
    // Fetch users via AJAX
    fetch('<?php echo apiUrl('admin/api/get_coupon_users.php'); ?>?coupon_id=' + couponId)
        .then(response => response.json())
        .then(data => {
            if (data.users && data.users.length > 0) {
                let html = '<div class="overflow-x-auto"><table class="w-full"><thead><tr class="border-b border-gray-800"><th class="text-left p-3">User</th><th class="text-left p-3">Email</th><th class="text-left p-3">Redeemed At</th><th class="text-left p-3">Subscription Expires</th><th class="text-left p-3">Status</th></tr></thead><tbody>';
                
                data.users.forEach(user => {
                    const redeemedDate = new Date(user.redeemed_at);
                    const redeemedDateStr = redeemedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const redeemedTimeStr = redeemedDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                    
                    let expiresHtml = '<span class="text-gray-500">N/A</span>';
                    let statusHtml = '<span class="px-2 py-1 rounded text-xs bg-gray-700 text-gray-300">Free</span>';
                    
                    if (user.subscription_expires_at) {
                        const expiresDate = new Date(user.subscription_expires_at);
                        const now = new Date();
                        const isExpired = expiresDate < now;
                        const expiresDateStr = expiresDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        
                        const daysRemaining = Math.ceil((expiresDate - now) / (1000 * 60 * 60 * 24));
                        
                        expiresHtml = `<span class="${isExpired ? 'text-red-400' : 'text-green-400'}">${expiresDateStr}</span>`;
                        if (!isExpired) {
                            expiresHtml += `<div class="text-xs text-gray-400">${daysRemaining} days remaining</div>`;
                        } else {
                            expiresHtml += `<div class="text-xs text-red-400">Expired</div>`;
                        }
                        
                        statusHtml = `<span class="px-2 py-1 rounded text-xs ${isExpired ? 'bg-red-900 text-red-200' : 'bg-green-900 text-green-200'}">${isExpired ? 'Expired' : 'Active'}</span>`;
                    }
                    
                    const fullName = user.full_name || user.username;
                    html += `<tr class="border-b border-gray-800 hover:bg-gray-800">
                        <td class="p-3">
                            <div class="font-semibold">${escapeHtml(fullName)}</div>
                            <div class="text-xs text-gray-400">@${escapeHtml(user.username)}</div>
                        </td>
                        <td class="p-3">${escapeHtml(user.email)}</td>
                        <td class="p-3">
                            ${redeemedDateStr}
                            <div class="text-xs text-gray-400">${redeemedTimeStr}</div>
                        </td>
                        <td class="p-3">${expiresHtml}</td>
                        <td class="p-3">${statusHtml}</td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
                html += `<div class="mt-4 text-sm text-gray-400">Total: ${data.users.length} user(s) redeemed this coupon</div>`;
                modalContent.innerHTML = html;
            } else {
                modalContent.innerHTML = '<div class="text-center py-12"><i class="fas fa-users text-6xl text-gray-700 mb-4"></i><p class="text-gray-400 text-lg">No users have redeemed this coupon yet</p></div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            modalContent.innerHTML = '<div class="text-center py-12"><p class="text-red-400">Error loading users. Please try again.</p></div>';
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closeUsersModal() {
    document.getElementById('users-modal').classList.add('hidden');
}

// Close modal on outside click
document.getElementById('users-modal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeUsersModal();
    }
});

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUsersModal();
    }
});
</script>
