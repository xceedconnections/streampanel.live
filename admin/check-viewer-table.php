<?php
/**
 * Viewer Table Checker and Fixer
 * Admin tool to check and fix channel_viewers table structure
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Require admin login
requireAdminLogin();

$conn = getDBConnection();
$messages = [];
$errors = [];
$table_exists = false;
$table_structure = [];
$current_viewers = [];
$viewer_counts = [];

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'channel_viewers'");
$table_exists = $result && $result->num_rows > 0;

if ($table_exists) {
    // Get table structure
    $columns = $conn->query("SHOW COLUMNS FROM channel_viewers");
    while ($col = $columns->fetch_assoc()) {
        $table_structure[] = $col;
    }
    
    // Get current viewers
    $viewers_result = $conn->query("SELECT * FROM channel_viewers ORDER BY 
        COALESCE(last_ping, last_seen, created_at) DESC LIMIT 50");
    if ($viewers_result) {
        $current_viewers = $viewers_result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get viewer counts by channel
    $counts_result = $conn->query("
        SELECT 
            cv.channel_id,
            ltc.name as channel_name,
            COUNT(DISTINCT cv.session_id) as concurrent_viewers,
            MAX(COALESCE(cv.last_ping, cv.last_seen)) as last_activity
        FROM channel_viewers cv
        LEFT JOIN live_tv_channels ltc ON cv.channel_id = ltc.id
        GROUP BY cv.channel_id, ltc.name
        ORDER BY concurrent_viewers DESC
    ");
    if ($counts_result) {
        $viewer_counts = $counts_result->fetch_all(MYSQLI_ASSOC);
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_table') {
        try {
            $conn->query("CREATE TABLE IF NOT EXISTS channel_viewers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                channel_id INT NOT NULL,
                user_id INT NULL,
                session_id VARCHAR(255) NOT NULL,
                last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_viewer (channel_id, session_id),
                INDEX idx_channel (channel_id),
                INDEX idx_user (user_id),
                INDEX idx_last_ping (last_ping)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $messages[] = "Table created successfully!";
            header("Location: ?success=1");
            exit;
        } catch (Exception $e) {
            $errors[] = "Error creating table: " . $e->getMessage();
        }
    }
    
    if ($action === 'fix_table') {
        try {
            // Add last_ping if missing
            $result = $conn->query("SHOW COLUMNS FROM channel_viewers LIKE 'last_ping'");
            if ($result && $result->num_rows == 0) {
                $conn->query("ALTER TABLE channel_viewers ADD COLUMN last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                $messages[] = "Added last_ping column";
                
                // Copy data from last_seen if it exists
                $result2 = $conn->query("SHOW COLUMNS FROM channel_viewers LIKE 'last_seen'");
                if ($result2 && $result2->num_rows > 0) {
                    $conn->query("UPDATE channel_viewers SET last_ping = last_seen WHERE last_ping IS NULL AND last_seen IS NOT NULL");
                    $messages[] = "Copied data from last_seen to last_ping";
                }
            }
            
            // Add session_id if missing
            $result = $conn->query("SHOW COLUMNS FROM channel_viewers LIKE 'session_id'");
            if ($result && $result->num_rows == 0) {
                $conn->query("ALTER TABLE channel_viewers ADD COLUMN session_id VARCHAR(255) NOT NULL DEFAULT '' AFTER user_id");
                $conn->query("UPDATE channel_viewers SET session_id = CONCAT('session_', COALESCE(user_id, 0), '_', id, '_', UNIX_TIMESTAMP()) WHERE session_id = ''");
                $messages[] = "Added session_id column and populated existing records";
            }
            
            // Make user_id nullable
            $conn->query("ALTER TABLE channel_viewers MODIFY COLUMN user_id INT NULL");
            $messages[] = "Made user_id nullable";
            
            // Fix unique key
            try {
                $conn->query("ALTER TABLE channel_viewers DROP INDEX unique_viewer");
            } catch (Exception $e) {
                // Index might not exist, that's okay
            }
            $conn->query("ALTER TABLE channel_viewers ADD UNIQUE KEY unique_viewer (channel_id, session_id)");
            $messages[] = "Fixed unique constraint";
            
            header("Location: ?success=1");
            exit;
        } catch (Exception $e) {
            $errors[] = "Error fixing table: " . $e->getMessage();
        }
    }
    
    if ($action === 'cleanup') {
        try {
            $deleted = 0;
            // Try with last_ping first
            try {
                $result = $conn->query("DELETE FROM channel_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
                $deleted = $conn->affected_rows;
            } catch (Exception $e) {
                // Try with last_seen
                $result = $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
                $deleted = $conn->affected_rows;
            }
            $messages[] = "Cleaned up $deleted old viewer records";
            header("Location: ?success=1");
            exit;
        } catch (Exception $e) {
            $errors[] = "Error cleaning up: " . $e->getMessage();
        }
    }
}

$page_title = "Viewer Table Checker";
include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Viewer Table Checker & Fixer</h1>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-900 border border-green-700 text-green-200 px-4 py-3 rounded mb-4">
        Operation completed successfully!
    </div>
    <?php endif; ?>
    
    <?php foreach ($messages as $msg): ?>
    <div class="bg-blue-900 border border-blue-700 text-blue-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endforeach; ?>
    
    <?php foreach ($errors as $error): ?>
    <div class="bg-red-900 border border-red-700 text-red-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endforeach; ?>
    
    <!-- Table Status -->
    <div class="bg-gray-900 rounded-lg p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4">Table Status</h2>
        <?php if ($table_exists): ?>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-green-400 text-xl">✓</span>
            <span class="text-lg">Table 'channel_viewers' exists</span>
        </div>
        <?php else: ?>
        <div class="flex items-center gap-2 mb-4">
            <span class="text-red-400 text-xl">✗</span>
            <span class="text-lg">Table 'channel_viewers' does NOT exist</span>
        </div>
        <form method="POST" class="mt-4">
            <input type="hidden" name="action" value="create_table">
            <button type="submit" class="bg-netflix-red hover:bg-red-700 px-6 py-2 rounded">
                Create Table
            </button>
        </form>
        <?php endif; ?>
    </div>
    
    <?php if ($table_exists): ?>
    <!-- Table Structure -->
    <div class="bg-gray-900 rounded-lg p-6 mb-6">
        <h2 class="text-2xl font-bold mb-4">Table Structure</h2>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left p-3">Field</th>
                        <th class="text-left p-3">Type</th>
                        <th class="text-left p-3">Null</th>
                        <th class="text-left p-3">Key</th>
                        <th class="text-left p-3">Default</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($table_structure as $col): ?>
                    <tr class="border-b border-gray-800">
                        <td class="p-3"><?php echo htmlspecialchars($col['Field']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($col['Type']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($col['Null']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($col['Key']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($col['Default'] ?? 'NULL'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        $has_last_ping = false;
        $has_session_id = false;
        $user_id_nullable = false;
        foreach ($table_structure as $col) {
            if ($col['Field'] === 'last_ping') $has_last_ping = true;
            if ($col['Field'] === 'session_id') $has_session_id = true;
            if ($col['Field'] === 'user_id' && $col['Null'] === 'YES') $user_id_nullable = true;
        }
        
        $needs_fix = !$has_last_ping || !$has_session_id || !$user_id_nullable;
        ?>
        
        <?php if ($needs_fix): ?>
        <div class="mt-4 p-4 bg-yellow-900 border border-yellow-700 rounded">
            <p class="text-yellow-200 mb-2">⚠️ Table structure needs fixing:</p>
            <ul class="list-disc list-inside text-yellow-200 space-y-1">
                <?php if (!$has_last_ping): ?><li>Missing 'last_ping' column</li><?php endif; ?>
                <?php if (!$has_session_id): ?><li>Missing 'session_id' column</li><?php endif; ?>
                <?php if (!$user_id_nullable): ?><li>'user_id' should be nullable</li><?php endif; ?>
            </ul>
            <form method="POST" class="mt-4">
                <input type="hidden" name="action" value="fix_table">
                <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 px-6 py-2 rounded">
                    Fix Table Structure
                </button>
            </form>
        </div>
        <?php else: ?>
        <div class="mt-4 p-4 bg-green-900 border border-green-700 rounded">
            <p class="text-green-200">✓ Table structure is correct!</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Current Viewers -->
    <div class="bg-gray-900 rounded-lg p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Current Viewers (Last 50)</h2>
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="cleanup">
                <button type="submit" class="bg-orange-600 hover:bg-orange-700 px-4 py-2 rounded text-sm">
                    Cleanup Old Viewers
                </button>
            </form>
        </div>
        <?php if (empty($current_viewers)): ?>
        <p class="text-gray-400">No viewers currently tracked</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left p-3">ID</th>
                        <th class="text-left p-3">Channel ID</th>
                        <th class="text-left p-3">User ID</th>
                        <th class="text-left p-3">Session ID</th>
                        <th class="text-left p-3">Last Ping</th>
                        <th class="text-left p-3">Last Seen</th>
                        <th class="text-left p-3">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($current_viewers as $viewer): ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800">
                        <td class="p-3"><?php echo htmlspecialchars($viewer['id']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($viewer['channel_id']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($viewer['user_id'] ?? 'NULL'); ?></td>
                        <td class="p-3 text-xs"><?php echo htmlspecialchars(substr($viewer['session_id'] ?? '', 0, 40)); ?>...</td>
                        <td class="p-3"><?php echo htmlspecialchars($viewer['last_ping'] ?? 'N/A'); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($viewer['last_seen'] ?? 'N/A'); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($viewer['created_at'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Viewer Counts by Channel -->
    <div class="bg-gray-900 rounded-lg p-6">
        <h2 class="text-2xl font-bold mb-4">Viewer Counts by Channel</h2>
        <?php if (empty($viewer_counts)): ?>
        <p class="text-gray-400">No active viewers</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b border-gray-700">
                        <th class="text-left p-3">Channel ID</th>
                        <th class="text-left p-3">Channel Name</th>
                        <th class="text-left p-3">Concurrent Viewers</th>
                        <th class="text-left p-3">Last Activity</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($viewer_counts as $count): ?>
                    <tr class="border-b border-gray-800 hover:bg-gray-800">
                        <td class="p-3"><?php echo htmlspecialchars($count['channel_id']); ?></td>
                        <td class="p-3"><?php echo htmlspecialchars($count['channel_name'] ?? 'Unknown'); ?></td>
                        <td class="p-3">
                            <span class="px-3 py-1 bg-green-900 text-green-200 rounded-full font-bold">
                                <?php echo number_format($count['concurrent_viewers']); ?>
                            </span>
                        </td>
                        <td class="p-3 text-gray-400"><?php echo htmlspecialchars($count['last_activity'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
