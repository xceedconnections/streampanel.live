<?php
/**
 * Admin Panel - Remove Live TV channels that have no logo (empty DB field or missing file on disk)
 */
$page_title = 'Remove Channels Without Logo';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();
$message = '';
$message_type = '';

function findChannelsWithoutLogo(mysqli $conn): array
{
    $channels = [];
    $result = $conn->query('SELECT id, name, category, logo FROM live_tv_channels ORDER BY name ASC');
    if (!$result) {
        return $channels;
    }

    while ($row = $result->fetch_assoc()) {
        if (!channelHasNoLogo($row['logo'] ?? '')) {
            continue;
        }
        $row['missing_reason'] = channelMissingLogoReason($row['logo'] ?? '');
        if ($row['missing_reason'] === '') {
            $row['missing_reason'] = 'file_missing_on_disk';
        }
        $channels[] = $row;
    }

    return $channels;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
    $to_delete = findChannelsWithoutLogo($conn);
    if (empty($to_delete)) {
        $message = 'No channels without a logo to remove.';
        $message_type = 'error';
    } else {
        $ids = array_map(static fn($ch) => (int) $ch['id'], $to_delete);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $stmt = $conn->prepare("DELETE FROM live_tv_channels WHERE id IN ($placeholders)");
        if ($stmt) {
            $stmt->bind_param($types, ...$ids);
            if ($stmt->execute()) {
                $deleted = $stmt->affected_rows;
                $message = "Removed {$deleted} channel(s) without a logo.";
                $message_type = 'success';
            } else {
                $message = 'Error removing channels: ' . ($stmt->error ?: 'unknown');
                $message_type = 'error';
            }
            $stmt->close();
        } else {
            $message = 'Error preparing delete: ' . ($conn->error ?: 'unknown');
            $message_type = 'error';
        }
    }
}

$channels = findChannelsWithoutLogo($conn);
$no_logo_count = count($channels);
$empty_db_count = 0;
$missing_file_count = 0;
foreach ($channels as $ch) {
    if (($ch['missing_reason'] ?? '') === 'empty_in_database') {
        $empty_db_count++;
    } else {
        $missing_file_count++;
    }
}

?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-image mr-2 text-netflix-red"></i>Remove Live TV Channels Without Logo
    </h1>

    <p class="text-gray-400 mb-6">
        This tool finds <strong>live TV channels</strong> with no usable logo: the database field is empty,
        or the logo points to <code class="bg-gray-800 px-1 rounded">uploads/tv-logos/</code> but the file is missing on disk.
        It shows how many will be affected, then lets you delete them in one step.
    </p>

    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="bg-gray-800 rounded-lg p-6 mb-6 border border-gray-700">
        <h2 class="text-xl font-bold mb-2">Channels with no logo</h2>
        <p class="text-4xl font-extrabold text-netflix-red mb-2"><?php echo number_format($no_logo_count); ?></p>
        <p class="text-sm text-gray-400 mb-3">
            Checked against the database <code class="bg-gray-900 px-1 rounded">logo</code> field and files under
            <code class="bg-gray-900 px-1 rounded">uploads/tv-logos/</code>.
        </p>
        <ul class="text-sm text-gray-300 space-y-1">
            <li><strong><?php echo number_format($empty_db_count); ?></strong> empty or blank in database</li>
            <li><strong><?php echo number_format($missing_file_count); ?></strong> logo path set but file missing on disk</li>
        </ul>
    </div>

    <?php if ($no_logo_count > 0): ?>
    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Warning:</strong> Deletion cannot be undone. These channels will be removed entirely.
    </div>

    <div class="mb-6 max-h-96 overflow-y-auto border border-gray-700 rounded">
        <table class="w-full text-sm">
            <thead class="bg-gray-700 sticky top-0">
                <tr>
                    <th class="p-2 text-left">ID</th>
                    <th class="p-2 text-left">Name</th>
                    <th class="p-2 text-left">Category</th>
                    <th class="p-2 text-left">Reason</th>
                    <th class="p-2 text-left">Logo value</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($channels as $ch): ?>
                <tr class="border-b border-gray-700">
                    <td class="p-2"><?php echo (int) $ch['id']; ?></td>
                    <td class="p-2 font-semibold"><?php echo htmlspecialchars($ch['name'] ?? ''); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($ch['category'] ?? ''); ?></td>
                    <td class="p-2">
                        <?php if (($ch['missing_reason'] ?? '') === 'empty_in_database'): ?>
                            <span class="text-orange-300">Empty in DB</span>
                        <?php else: ?>
                            <span class="text-red-300">File missing</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-2 text-gray-400 truncate max-w-xs" title="<?php echo htmlspecialchars($ch['logo'] ?? ''); ?>">
                        <?php echo htmlspecialchars($ch['logo'] ?? ''); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <form method="POST" data-tool-progress="Removing channels without logo..." onsubmit="return confirm('Delete <?php echo (int) $no_logo_count; ?> channel(s) with no logo? This cannot be undone.');">
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
        No channels without a logo. Every channel has a database value and a matching file in <code class="bg-gray-900 px-1 rounded">uploads/tv-logos/</code> (or an external logo URL).
    </div>
    <a href="?tab=tools" class="text-gray-400 hover:text-white">
        <i class="fas fa-arrow-left mr-2"></i>Back to Tools
    </a>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/tool-progress-ui.php'; ?>
