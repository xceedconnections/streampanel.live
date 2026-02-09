<?php
/**
 * Admin Panel - Shaka Player Configuration
 */
$page_title = "Shaka Player Config";

$message = '';
$message_type = '';

// Handle logo upload
$logo_path = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['player_logo']) && $_FILES['player_logo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/../uploads/shaka-player/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['player_logo'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $max_file_size = 5 * 1024 * 1024; // 5MB
    
    if ($file['size'] > $max_file_size) {
        $message = 'Logo file size exceeds 5MB limit';
        $message_type = 'error';
    } elseif (in_array($file_ext, $allowed_extensions)) {
        $file_name = 'shaka_logo_' . time() . '_' . uniqid() . '.' . $file_ext;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Delete old logo if exists
            $old_logo = $conn->query("SELECT setting_value FROM settings WHERE setting_key='shaka_player_logo'")->fetch_assoc()['setting_value'] ?? '';
            if ($old_logo && file_exists(__DIR__ . '/../' . $old_logo)) {
                @unlink(__DIR__ . '/../' . $old_logo);
            }
            
            $logo_path = 'uploads/shaka-player/' . $file_name;
            
            // Save logo path to settings
            $check_logo = $conn->prepare("SELECT id FROM settings WHERE setting_key='shaka_player_logo'");
            $check_logo->execute();
            $logo_exists = $check_logo->get_result()->fetch_assoc();
            
            if ($logo_exists) {
                $stmt_logo = $conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key='shaka_player_logo'");
                $stmt_logo->bind_param("s", $logo_path);
                $stmt_logo->execute();
            } else {
                $stmt_logo = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('shaka_player_logo', ?)");
                $stmt_logo->bind_param("s", $logo_path);
                $stmt_logo->execute();
            }
            
            $message = 'Logo uploaded successfully';
            $message_type = 'success';
        } else {
            $message = 'Failed to upload logo';
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, SVG';
        $message_type = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config = json_encode([
        'streaming' => [
            'retryParameters' => [
                'timeout' => intval($_POST['timeout'] ?? 30000),
                'maxAttempts' => intval($_POST['max_attempts'] ?? 10),
                'baseDelay' => intval($_POST['base_delay'] ?? 1000),
                'maxDelay' => intval($_POST['max_delay'] ?? 5000),
                'backoffFactor' => floatval($_POST['backoff_factor'] ?? 2),
                'fuzzFactor' => floatval($_POST['fuzz_factor'] ?? 0.5)
            ],
            'bufferingGoal' => intval($_POST['buffering_goal'] ?? 15),
            'rebufferingGoal' => intval($_POST['rebuffering_goal'] ?? 3),
            'bufferBehind' => intval($_POST['buffer_behind'] ?? 30),
            'lowLatencyMode' => isset($_POST['low_latency_mode']) ? true : false
        ],
        'abr' => [
            'enabled' => isset($_POST['abr_enabled']),
            'useNetworkInformation' => isset($_POST['use_network_info']),
            'restrictions' => [
                'minWidth' => intval($_POST['min_width'] ?? 0),
                'maxWidth' => intval($_POST['max_width'] ?? 0),
                'minHeight' => intval($_POST['min_height'] ?? 0),
                'maxHeight' => intval($_POST['max_height'] ?? 0)
            ]
        ]
    ], JSON_PRETTY_PRINT);
    
    // Check if setting exists, if not insert it
    $check = $conn->prepare("SELECT id FROM settings WHERE setting_key='shaka_player_config'");
    $check->execute();
    $exists = $check->get_result()->fetch_assoc();
    
    if ($exists) {
        $stmt = $conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key='shaka_player_config'");
        $stmt->bind_param("s", $config);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('shaka_player_config', ?)");
        $stmt->bind_param("s", $config);
        $stmt->execute();
    }
    $message = 'Shaka Player configuration updated successfully';
    $message_type = 'success';
}

$configJson = $conn->query("SELECT setting_value FROM settings WHERE setting_key='shaka_player_config'")->fetch_assoc()['setting_value'] ?? '{}';
$config = json_decode($configJson, true) ?? [];
$streaming = $config['streaming'] ?? [];
$abr = $config['abr'] ?? [];

// Get current logo path
$current_logo = $conn->query("SELECT setting_value FROM settings WHERE setting_key='shaka_player_logo'")->fetch_assoc()['setting_value'] ?? '';
if (empty($logo_path) && !empty($current_logo)) {
    $logo_path = $current_logo;
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Shaka Player Configuration</h1>
    <p class="text-gray-400">Configure Shaka Player streaming settings</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6 mb-6">
    <h2 class="text-2xl font-bold mb-4">Player Logo</h2>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Upload Player Logo</label>
            <input type="file" name="player_logo" accept="image/*" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            <p class="text-xs text-gray-400 mt-1">Logo will be displayed on the left side of the player. Max size: 5MB. Allowed: JPG, PNG, GIF, WEBP, SVG</p>
        </div>
        <?php if (!empty($logo_path)): ?>
        <div class="mb-4">
            <p class="text-sm text-gray-400 mb-2">Current Logo:</p>
            <img src="<?php echo BASE_URL . '/' . $logo_path; ?>" alt="Player Logo" class="max-w-xs max-h-32 object-contain bg-gray-800 p-2 rounded">
        </div>
        <?php endif; ?>
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            Upload Logo
        </button>
    </form>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">Streaming Configuration</h2>
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="space-y-4">
            <h3 class="text-xl font-semibold mb-3">Retry Parameters</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Timeout (ms)</label>
                    <input type="number" name="timeout" value="<?php echo $streaming['retryParameters']['timeout'] ?? 30000; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Max Attempts</label>
                    <input type="number" name="max_attempts" value="<?php echo $streaming['retryParameters']['maxAttempts'] ?? 3; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Base Delay (ms)</label>
                    <input type="number" name="base_delay" value="<?php echo $streaming['retryParameters']['baseDelay'] ?? 1000; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Max Delay (ms)</label>
                    <input type="number" name="max_delay" value="<?php echo $streaming['retryParameters']['maxDelay'] ?? 5000; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
            </div>
            
            <h3 class="text-xl font-semibold mb-3 mt-6">Buffering</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Buffering Goal (seconds)</label>
                    <input type="number" name="buffering_goal" value="<?php echo $streaming['bufferingGoal'] ?? 15; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <p class="text-xs text-gray-400 mt-1">Target buffer ahead of playhead</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Rebuffering Goal (seconds)</label>
                    <input type="number" name="rebuffering_goal" value="<?php echo $streaming['rebufferingGoal'] ?? 3; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <p class="text-xs text-gray-400 mt-1">Target buffer when recovering from underflow</p>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Buffer Behind (seconds)</label>
                    <input type="number" name="buffer_behind" value="<?php echo $streaming['bufferBehind'] ?? 30; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <p class="text-xs text-gray-400 mt-1">Maximum buffer behind playhead</p>
                </div>
                <div>
                    <label class="flex items-center mt-6">
                        <input type="checkbox" name="low_latency_mode" <?php echo ($streaming['lowLatencyMode'] ?? false) ? 'checked' : ''; ?> 
                               class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                        <span>Low Latency Mode</span>
                    </label>
                    <p class="text-xs text-gray-400 mt-1">Enable for reduced latency (may affect stability)</p>
                </div>
            </div>
            
            <h3 class="text-xl font-semibold mb-3 mt-6">Adaptive Bitrate (ABR)</h3>
            <div class="mb-4 space-y-2">
                <label class="flex items-center">
                    <input type="checkbox" name="abr_enabled" <?php echo ($abr['enabled'] ?? true) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Enable ABR</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="use_network_info" <?php echo ($abr['useNetworkInformation'] ?? true) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Use Network Information API</span>
                </label>
                <p class="text-xs text-gray-400">Use browser network information for better ABR decisions</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Min Width</label>
                    <input type="number" name="min_width" value="<?php echo $abr['restrictions']['minWidth'] ?? 0; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Max Width</label>
                    <input type="number" name="max_width" value="<?php echo $abr['restrictions']['maxWidth'] ?? 0; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Min Height</label>
                    <input type="number" name="min_height" value="<?php echo $abr['restrictions']['minHeight'] ?? 0; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-2">Max Height</label>
                    <input type="number" name="max_height" value="<?php echo $abr['restrictions']['maxHeight'] ?? 0; ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>
            </div>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 mt-6">
            Save Configuration
        </button>
    </form>
</div>
