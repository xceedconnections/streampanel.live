<?php
/**
 * Admin Panel - Content Reports (Broken Links, etc.)
 */
$page_title = "Content Reports";

$conn->query("CREATE TABLE IF NOT EXISTS reports (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) DEFAULT NULL,
    content_type ENUM('movie','tv_show','tv_episode','live_tv') NOT NULL,
    content_id INT(11) NOT NULL,
    source_id VARCHAR(100) DEFAULT NULL,
    issue_type ENUM('broken_link','wrong_content','quality_issue','copyright','other') DEFAULT 'broken_link',
    description TEXT DEFAULT NULL,
    status ENUM('pending','resolved','dismissed') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    admin_reply TEXT DEFAULT NULL,
    reply_read TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
@$conn->query("ALTER TABLE reports MODIFY issue_type ENUM('broken_link','wrong_content','quality_issue','copyright','other') DEFAULT 'broken_link'");

if (!function_exists('parseSources')) {
    require_once __DIR__ . '/includes/functions.php';
}

function adminRemoveReportedSource(mysqli $conn, array $report): array
{
    $type = $report['content_type'] ?? '';
    $contentId = (int) ($report['content_id'] ?? 0);
    $sourceIndex = (int) ($report['source_id'] ?? -1);
    if ($contentId <= 0 || $sourceIndex < 0) {
        return ['ok' => false, 'message' => 'Missing content or source index'];
    }

    if ($type === 'live_tv') {
        $stmt = $conn->prepare('SELECT id, sources FROM live_tv_channels WHERE id = ? LIMIT 1');
        $table = 'live_tv_channels';
    } elseif ($type === 'movie') {
        $stmt = $conn->prepare('SELECT id, sources FROM movies WHERE id = ? LIMIT 1');
        $table = 'movies';
    } elseif ($type === 'tv_episode') {
        $stmt = $conn->prepare('SELECT id, sources FROM tv_episodes WHERE id = ? LIMIT 1');
        $table = 'tv_episodes';
    } else {
        return ['ok' => false, 'message' => 'Unsupported content type for source removal'];
    }

    $stmt->bind_param('i', $contentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return ['ok' => false, 'message' => 'Content not found'];
    }

    $all = parseSources($row['sources'] ?? '[]');
    $active = array_values(array_filter($all, function ($s) {
        return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true);
    }));
    if (!isset($active[$sourceIndex])) {
        return ['ok' => false, 'message' => 'Reported source index no longer exists'];
    }
    $target = $active[$sourceIndex];
    $targetUrl = $target['url'] ?? '';
    $targetType = $target['type'] ?? '';

    $removed = false;
    foreach ($all as $i => $s) {
        if (($s['url'] ?? '') === $targetUrl && ($s['type'] ?? '') === $targetType) {
            array_splice($all, $i, 1);
            $removed = true;
            break;
        }
    }
    if (!$removed) {
        return ['ok' => false, 'message' => 'Could not match source in database'];
    }

    $json = encodeSources($all);
    $upd = $conn->prepare("UPDATE {$table} SET sources = ? WHERE id = ?");
    $upd->bind_param('si', $json, $contentId);
    if (!$upd->execute()) {
        return ['ok' => false, 'message' => 'Failed to save sources'];
    }
    return ['ok' => true, 'message' => 'Source removed'];
}

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
        $stmt = $conn->prepare("UPDATE reports SET status = ?, resolved_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $report_id);
        $stmt->execute();
    }
    
    echo '<script>window.location.href = "?tab=content-reports";</script>';
    exit();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = intval($_POST['report_id']);
    $status = $_POST['status'] ?? 'pending';
    
    $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $report_id);
    $stmt->execute();
    
    echo '<script>window.location.href = "?tab=content-reports";</script>';
    exit();
}

// Remove reported stream source
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_source'])) {
    $report_id = intval($_POST['report_id']);
    $stmt = $conn->prepare('SELECT * FROM reports WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    $result = $report ? adminRemoveReportedSource($conn, $report) : ['ok' => false, 'message' => 'Report not found'];
    if (!empty($result['ok'])) {
        $reply = 'Source removed by admin.';
        $status = 'resolved';
        $upd = $conn->prepare("UPDATE reports SET admin_reply = ?, status = ?, resolved_at = NOW(), reply_read = 0 WHERE id = ?");
        $upd->bind_param('ssi', $reply, $status, $report_id);
        $upd->execute();
    }
    echo '<script>alert(' . json_encode($result['message'] ?? 'Done') . '); window.location.href = "?tab=content-reports";</script>';
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

$total_reports = $conn->query("SELECT COUNT(*) as count FROM reports")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];
$resolved_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'resolved'")->fetch_assoc()['count'];
$dismissed_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'dismissed'")->fetch_assoc()['count'];

$issue_labels = [
    'broken_link' => 'Link not working',
    'copyright' => 'Copyright',
    'wrong_content' => 'Wrong content',
    'quality_issue' => 'Quality issue',
    'other' => 'Other',
];
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Content Reports</h1>
    <p class="text-gray-400">Manage reports for broken links, copyright, and content issues</p>
</div>

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

<div class="mb-6 flex space-x-4">
    <a href="?tab=content-reports&filter=all" class="px-4 py-2 rounded <?php echo $filter === 'all' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">All</a>
    <a href="?tab=content-reports&filter=pending" class="px-4 py-2 rounded <?php echo $filter === 'pending' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">Pending</a>
    <a href="?tab=content-reports&filter=resolved" class="px-4 py-2 rounded <?php echo $filter === 'resolved' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">Resolved</a>
    <a href="?tab=content-reports&filter=dismissed" class="px-4 py-2 rounded <?php echo $filter === 'dismissed' ? 'bg-netflix-red' : 'bg-gray-800'; ?>">Dismissed</a>
</div>

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
                        | Issue: <span class="text-white"><?php echo htmlspecialchars($issue_labels[$report['issue_type']] ?? ucfirst(str_replace('_', ' ', $report['issue_type']))); ?></span>
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
                <?php if ($report['source_id'] !== null && $report['source_id'] !== ''): ?>
                <p class="text-sm text-gray-400 mt-2">Source index: <?php echo htmlspecialchars($report['source_id']); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($report['admin_reply'])): ?>
            <div class="mb-4 bg-green-900 bg-opacity-30 rounded p-4 border border-green-700">
                <p class="text-sm font-semibold text-green-400 mb-2">Admin Reply:</p>
                <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($report['admin_reply']); ?></p>
                <p class="text-xs text-gray-500 mt-2">Resolved: <?php echo date('F j, Y g:i A', strtotime($report['resolved_at'])); ?></p>
            </div>
            <?php endif; ?>

            <?php if (in_array($report['content_type'], ['live_tv', 'movie', 'tv_episode'], true) && $report['status'] === 'pending'): ?>
            <form method="POST" action="?tab=content-reports" class="mb-4" onsubmit="return confirm('Remove this stream source from the content?');">
                <input type="hidden" name="report_id" value="<?php echo (int) $report['id']; ?>">
                <button type="submit" name="remove_source" value="1" class="bg-red-800 hover:bg-red-700 px-4 py-2 rounded text-sm font-semibold">
                    Remove reported source
                </button>
            </form>
            <?php endif; ?>
            
            <form method="POST" action="?tab=content-reports" class="mt-4">
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
