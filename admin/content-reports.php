<?php
/**
 * Admin Panel - Content Reports (Broken Links, etc.)
 */
$page_title = "Content Reports";

// Handle reply submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_report'])) {
    $report_id = intval($_POST['report_id']);
    $admin_reply = trim($_POST['admin_reply'] ?? '');
    $status = $_POST['status'] ?? 'resolved';
    
    if (!empty($admin_reply)) {
        $stmt = $conn->prepare("UPDATE reports SET admin_reply = ?, status = ?, resolved_at = NOW(), reply_read = 0 WHERE id = ?");
        $stmt->bind_param("ssi", $admin_reply, $status, $report_id);
        $stmt->execute();
    } else {
        // Just update status
        $stmt = $conn->prepare("UPDATE reports SET status = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $report_id);
        $stmt->execute();
    }
    
    header('Location: ?tab=content-reports');
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = intval($_POST['report_id']);
    $status = $_POST['status'] ?? 'pending';
    
    $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $report_id);
    $stmt->execute();
    
    header('Location: ?tab=content-reports');
    exit();
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$where_clause = '';
if ($filter === 'pending') {
    $where_clause = "WHERE r.status = 'pending'";
} elseif ($filter === 'resolved') {
    $where_clause = "WHERE r.status = 'resolved'";
} elseif ($filter === 'dismissed') {
    $where_clause = "WHERE r.status = 'dismissed'";
}

// Get content reports
$query = "SELECT r.*, 
    u.username, u.email,
    CASE 
        WHEN r.content_type = 'movie' THEN m.title
        WHEN r.content_type = 'tv_show' THEN t.title
        WHEN r.content_type = 'tv_episode' THEN CONCAT(t2.title, ' - S', e.season_number, 'E', e.episode_number)
        WHEN r.content_type = 'live_tv' THEN l.name
    END as content_title
    FROM reports r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN movies m ON r.content_type = 'movie' AND r.content_id = m.id
    LEFT JOIN tv_shows t ON r.content_type = 'tv_show' AND r.content_id = t.id
    LEFT JOIN tv_episodes e ON r.content_type = 'tv_episode' AND r.content_id = e.id
    LEFT JOIN tv_shows t2 ON e.tv_show_id = t2.id
    LEFT JOIN live_tv_channels l ON r.content_type = 'live_tv' AND r.content_id = l.id
    $where_clause
    ORDER BY r.created_at DESC";
$content_reports = $conn->query($query)->fetch_all(MYSQLI_ASSOC);

// Get stats
$total_reports = $conn->query("SELECT COUNT(*) as count FROM reports")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];
$resolved_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'resolved'")->fetch_assoc()['count'];
$dismissed_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'dismissed'")->fetch_assoc()['count'];
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Content Reports</h1>
    <p class="text-gray-400">Manage reports for broken links and content issues</p>
</div>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Total Reports</p>
        <p class="text-3xl font-bold text-white"><?php echo $total_reports; ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Pending</p>
        <p class="text-3xl font-bold text-yellow-400"><?php echo $pending_reports; ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Resolved</p>
        <p class="text-3xl font-bold text-green-400"><?php echo $resolved_reports; ?></p>
    </div>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Dismissed</p>
        <p class="text-3xl font-bold text-gray-400"><?php echo $dismissed_reports; ?></p>
    </div>
</div>

<!-- Filters -->
<div class="mb-6 flex space-x-4">
    <a href="?tab=content-reports&filter=all" class="px-4 py-2 rounded <?php echo $filter === 'all' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        All
    </a>
    <a href="?tab=content-reports&filter=pending" class="px-4 py-2 rounded <?php echo $filter === 'pending' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        Pending
    </a>
    <a href="?tab=content-reports&filter=resolved" class="px-4 py-2 rounded <?php echo $filter === 'resolved' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        Resolved
    </a>
    <a href="?tab=content-reports&filter=dismissed" class="px-4 py-2 rounded <?php echo $filter === 'dismissed' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">
        Dismissed
    </a>
</div>

<!-- Reports List -->
<div class="space-y-6">
    <?php if (empty($content_reports)): ?>
    <div class="bg-gray-900 rounded-lg p-8 text-center">
        <p class="text-gray-400">No reports found.</p>
    </div>
    <?php else: ?>
        <?php foreach ($content_reports as $report): ?>
        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-xl font-bold"><?php echo htmlspecialchars($report['content_title'] ?? 'Unknown Content'); ?></h3>
                    <p class="text-sm text-gray-400 mt-1">
                        Type: <span class="text-white"><?php echo ucfirst(str_replace('_', ' ', $report['content_type'])); ?></span>
                        | Issue: <span class="text-white"><?php echo ucfirst(str_replace('_', ' ', $report['issue_type'])); ?></span>
                        <?php if ($report['username']): ?>
                            | Reported by: <span class="text-white"><?php echo htmlspecialchars($report['username']); ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-1"><?php echo date('F j, Y g:i A', strtotime($report['created_at'])); ?></p>
                </div>
                <span class="px-3 py-1 rounded text-xs font-semibold
                    <?php 
                    if ($report['status'] === 'resolved') echo 'bg-green-900 text-green-200';
                    elseif ($report['status'] === 'dismissed') echo 'bg-gray-700 text-gray-300';
                    else echo 'bg-yellow-900 text-yellow-200';
                    ?>">
                    <?php echo strtoupper($report['status']); ?>
                </span>
            </div>
            
            <div class="mb-4 bg-gray-800 rounded p-4">
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($report['description']); ?></p>
                <?php if (!empty($report['source_id'])): ?>
                <p class="text-sm text-gray-400 mt-2">Source ID: <?php echo htmlspecialchars($report['source_id']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($report['admin_reply'])): ?>
            <div class="mb-4 bg-green-900 bg-opacity-30 rounded p-4 border border-green-700">
                <p class="text-sm font-semibold text-green-400 mb-2">Admin Reply:</p>
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($report['admin_reply']); ?></p>
                <p class="text-xs text-gray-500 mt-2">Resolved: <?php echo date('F j, Y g:i A', strtotime($report['resolved_at'])); ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Reply Form -->
            <form method="POST" action="" class="mt-4">
                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                <div class="mb-3">
                    <label class="block text-sm font-semibold mb-2">Admin Reply</label>
                    <textarea name="admin_reply" rows="4" placeholder="Write your reply here..."
                              class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($report['admin_reply'] ?? ''); ?></textarea>
                </div>
                <div class="flex items-center space-x-4">
                    <select name="status" class="bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                        <option value="pending" <?php echo $report['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="resolved" <?php echo $report['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="dismissed" <?php echo $report['status'] === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                    </select>
                    <button type="submit" name="reply_report" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
                        <?php echo empty($report['admin_reply']) ? 'Send Reply' : 'Update Reply'; ?>
                    </button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
