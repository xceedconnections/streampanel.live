<?php
/**
 * Admin Panel - User Messages (Banned Users Contact)
 */
$page_title = "User Messages";

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'])) {
    $message_id = intval($_POST['message_id']);
    $admin_reply = trim($_POST['admin_reply'] ?? '');
    $status = $_POST['status'] ?? 'replied';
    
    if (!empty($admin_reply)) {
        $stmt = $conn->prepare("UPDATE user_messages SET admin_reply = ?, status = ?, replied_at = NOW() WHERE id = ?");
        $stmt->bind_param("ssi", $admin_reply, $status, $message_id);
        $stmt->execute();
    } else {
        // Just update status
        $stmt = $conn->prepare("UPDATE user_messages SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $message_id);
        $stmt->execute();
    }
    
    // Use JavaScript redirect since headers are already sent
    echo '<script>window.location.href = "?tab=user-messages";</script>';
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $message_id = intval($_POST['message_id']);
    $status = $_POST['status'] ?? 'pending';
    
    $stmt = $conn->prepare("UPDATE user_messages SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $message_id);
    $stmt->execute();
    
    // Use JavaScript redirect since headers are already sent
    echo '<script>window.location.href = "?tab=user-messages";</script>';
    exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$where_clause = '';
if ($filter === 'pending') {
    $where_clause = "WHERE status = 'pending'";
} elseif ($filter === 'replied') {
    $where_clause = "WHERE status = 'replied'";
} elseif ($filter === 'resolved') {
    $where_clause = "WHERE status = 'resolved'";
}

// Get user messages
$query = "SELECT um.*, u.username, u.email, u.full_name, u.banned 
          FROM user_messages um
          JOIN users u ON um.user_id = u.id
          $where_clause
          ORDER BY um.created_at DESC";
$user_messages = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get stats
$total_messages = $conn->query("SELECT COUNT(*) as count FROM user_messages")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM user_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$replied_messages = $conn->query("SELECT COUNT(*) as count FROM user_messages WHERE status = 'replied'")->fetch_assoc()['count'];
$resolved_messages = $conn->query("SELECT COUNT(*) as count FROM user_messages WHERE status = 'resolved'")->fetch_assoc()['count'];
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">User Messages</h1>
    <p class="text-gray-400">Manage messages from banned and regular users</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Total Messages</p>
        <p class="text-3xl font-bold text-white"><?php echo $total_messages; ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Pending</p>
        <p class="text-3xl font-bold text-yellow-400"><?php echo $pending_messages; ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Replied</p>
        <p class="text-3xl font-bold text-green-400"><?php echo $replied_messages; ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Resolved</p>
        <p class="text-3xl font-bold text-blue-400"><?php echo $resolved_messages; ?></p>
    </div>
</div>

<!-- Filters -->
<div class="mb-6 flex space-x-4">
    <a href="?tab=user-messages&filter=all" class="px-4 py-2 rounded <?php echo $filter === 'all' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        All
    </a>
    <a href="?tab=user-messages&filter=pending" class="px-4 py-2 rounded <?php echo $filter === 'pending' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        Pending
    </a>
    <a href="?tab=user-messages&filter=replied" class="px-4 py-2 rounded <?php echo $filter === 'replied' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        Replied
    </a>
    <a href="?tab=user-messages&filter=resolved" class="px-4 py-2 rounded <?php echo $filter === 'resolved' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        Resolved
    </a>
</div>

<!-- Messages List -->
<div class="space-y-6">
    <?php if (empty($user_messages)): ?>
    <div class="bg-gray-900 rounded-lg p-8 text-center">
        <p class="text-gray-400">No messages found.</p>
    </div>
    <?php else: ?>
        <?php foreach ($user_messages as $msg): ?>
        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($msg['subject']); ?></h3>
                    <p class="text-sm text-gray-400 mt-1">
                        From: <span class="text-white"><?php echo htmlspecialchars($msg['username']); ?></span>
                        <?php if ($msg['banned']): ?>
                            <span class="ml-2 px-2 py-1 bg-red-900 text-red-200 rounded text-xs">BANNED</span>
                        <?php endif; ?>
                        <span class="ml-4"><?php echo htmlspecialchars($msg['email']); ?></span>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo date('F j, Y g:i A', strtotime($msg['created_at'])); ?></p>
                </div>
                <span class="px-3 py-1 rounded text-xs font-semibold
                    <?php 
                    if ($msg['status'] === 'replied') echo 'bg-green-900 text-green-200';
                    elseif ($msg['status'] === 'resolved') echo 'bg-blue-900 text-blue-200';
                    else echo 'bg-yellow-900 text-yellow-200';
                    ?>">
                    <?php echo strtoupper($msg['status']); ?>
                </span>
            </div>
            
            <div class="mb-4 bg-gray-800 rounded p-4">
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($msg['message']); ?></p>
            </div>
            
            <?php if (!empty($msg['admin_reply'])): ?>
            <div class="mb-4 bg-green-900 bg-opacity-30 rounded p-4 border border-green-700">
                <p class="text-sm font-semibold text-green-400 mb-2">Admin Reply:</p>
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($msg['admin_reply']); ?></p>
                <p class="text-xs text-gray-500 mt-2">Replied: <?php echo date('F j, Y g:i A', strtotime($msg['replied_at'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Reply Form -->
            <form method="POST" action="" class="mt-4">
                <input type="hidden" name="message_id" value="<?php echo $msg['id']; ?>">
                <div class="mb-3">
                    <label class="block text-sm font-semibold mb-2">Admin Reply</label>
                    <textarea name="admin_reply" rows="4" placeholder="Write your reply here..."
                              class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($msg['admin_reply'] ?? ''); ?></textarea>
                </div>
                <div class="flex items-center space-x-4">
                    <select name="status" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                        <option value="pending" <?php echo $msg['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="replied" <?php echo $msg['status'] === 'replied' ? 'selected' : ''; ?>>Replied</option>
                        <option value="resolved" <?php echo $msg['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                    </select>
                    <button type="submit" name="reply_message" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
                        <?php echo empty($msg['admin_reply']) ? 'Send Reply' : 'Update Reply'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
