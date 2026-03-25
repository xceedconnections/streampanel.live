<?php
/**
 * Admin Panel - Remove Live TV channels that have no logo
 */
$page_title = 'Remove Channels Without Logo';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();
$message = '';
$message_type = '';

$sql_where = "(logo IS NULL OR TRIM(IFNULL(logo, '')) = '')";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    $pre = $conn->query("SELECT COUNT(*) AS c FROM live_tv_channels WHERE $sql_where");
    $to_delete = $pre ? (int) $pre->fetch_assoc()['c'] : 0;
    if ($to_delete === 0) {
        $message = 'No channels without a logo to remove.';
        $message_type = 'error';
    } else {
        $stmt = $conn->prepare("DELETE FROM live_tv_channels WHERE $sql_where");
        if ($stmt && $stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $message = "Removed {$deleted} channel(s) that had no logo.";
            $message_type = 'success';
        } else {
            $message = 'Error removing channels: ' . ($conn->error ?: 'unknown');
            $message_type = 'error';
        }
    }
}

$count_result = $conn->query("SELECT COUNT(*) AS c FROM live_tv_channels WHERE $sql_where");
$no_logo_count = $count_result ? (int) $count_result->fetch_assoc()['c'] : 0;

$channels = [];
if ($no_logo_count > 0) {
    $list = $conn->query("SELECT id, name, category FROM live_tv_channels WHERE $sql_where ORDER BY name ASC");
    if ($list) {
        $channels = $list->fetch_all(MYSQLI_ASSOC);
    }
}

?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-image mr-2 text-netflix-red"></i>Remove Live TV Channels Without Logo
    </h1>

    <p class="text-gray-400 mb-6">
        This tool finds <strong>live TV channels</strong> where the <code class="bg-gray-800 px-1 rounded">logo</code> field is empty or missing,
        shows how many will be affected, then lets you delete them in one step.
    </p>

    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
        <h2 class="text-xl font-bold mb-2">Channels with no logo</h2>
        <p class="text-4xl font-extrabold text-netflix-red mb-2"><?php echo number_format($no_logo_count); ?></p>
        <p class="text-sm text-gray-400">Live TV rows where logo is null or blank (whitespace only counts as empty).</p>
    </div>

    <?php if ($no_logo_count > 0): ?>
    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Warning:</strong> Deletion cannot be undone. These channels will be removed entirely.
    </div>

    <div class="mb-6 max-h-64 overflow-y-auto border border-gray-700 rounded">
        <table class="w-full text-sm">
            <thead class="bg-gray-700 sticky top-0">
                <tr>
                    <th class="p-2 text-left">ID</th>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Category</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $ch): ?>
                <tr class="border-b border-gray-700">
                    <td class="p-2"><?php echo (int) $ch['id']; ?></td>
                    <td class="p-2 font-semibold"><?php echo htmlspecialchars($ch['name'] ?? ''); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($ch['category'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="POST" onsubmit="return confirm('Delete <?php echo (int) $no_logo_count; ?> channel(s) with no logo? This cannot be undone.');">
        <input type="hidden" name="confirm_delete" value="yes">
        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors">
            <i class="fas fa-trash-alt mr-2"></i>Remove all channels with no logo
        </button>
        <a href="?tab=tools" class="ml-4 text-gray-400 hover:text-white">
            <i class="fas fa-arrow-left mr-2"></i>Back to Tools
        </a>
    </form>
    <?php else: ?>
    <div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-check-circle mr-2"></i>
        No channels without a logo. Nothing to remove.
    </div>
    <a href="?tab=tools" class="text-gray-400 hover:text-white">
        <i class="fas fa-arrow-left mr-2"></i>Back to Tools
    </a>
    <?php endif; ?>
</div>
