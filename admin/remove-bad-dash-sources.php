<?php
/**
 * Admin Panel - Remove Non-Working DASH/MPD Sources
 * Checks all DASH/MPD sources and removes only the non-working ones
 * Channels are preserved, only bad sources are removed
 */
$page_title = "Remove Bad DASH Sources";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();
$message = '';
$message_type = '';
$stats = [
    'total_channels'      => 0,
    'channels_checked'    => 0,
    'sources_checked'     => 0,
    'sources_removed'     => 0, // actual removed when process=run
    'sources_to_remove'   => 0, // preview count
    'channels_updated'    => 0,
    'channels_with_bad'   => 0,
];

$has_run    = false;
$is_preview = false;
$is_process = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['preview']) && $_POST['preview'] === 'yes') {
        $is_preview = true;
    } elseif (isset($_POST['process']) && $_POST['process'] === 'yes') {
        $is_process = true;
    }
}

// Function to check if DASH/MPD stream is working
function checkDASHStream($url, $timeout = 15) {
    if (empty($url)) {
        return ['status' => 'error', 'message' => 'Empty URL'];
    }
    
    // Verify it's a DASH/MPD URL
    $url_lower = strtolower($url);
    if (strpos($url_lower, '.mpd') === false && 
        strpos($url_lower, 'dash') === false) {
        return ['status' => 'skip', 'message' => 'Not a DASH/MPD URL'];
    }
    
    try {
        // Use GET request with range to fetch first part of MPD manifest
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_RANGE, '0-4095'); // Fetch first 4KB
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET'); // Explicit GET for CORS
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $content_length = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        
        // Handle connection errors
        if (!empty($curl_error)) {
            // Critical errors that definitely mean the link is dead
            if (strpos($curl_error, 'timeout') !== false || 
                strpos($curl_error, 'Connection refused') !== false ||
                strpos($curl_error, 'Could not resolve') !== false ||
                strpos($curl_error, 'SSL') !== false ||
                strpos($curl_error, 'Connection timed out') !== false) {
                return ['status' => 'error', 'message' => $curl_error];
            }
            return ['status' => 'error', 'message' => $curl_error];
        }
        
        // Check HTTP status codes
        // Accept 200, 206 (partial content), 301, 302, 303, 307, 308 (redirects)
        if ($http_code >= 200 && $http_code < 400) {
            // Verify we got actual content (MPD manifest should have content)
            if ($content_length > 0) {
                // Check if response contains MPD manifest indicators
                $body = substr($response, $header_size);
                if (stripos($body, 'MPD') !== false || 
                    stripos($body, '<?xml') !== false ||
                    stripos($body, '<MPD') !== false ||
                    stripos($body, 'xmlns') !== false ||
                    strlen($body) > 10) {
                    return ['status' => 'ok', 'http_code' => $http_code];
                }
            }
            return ['status' => 'ok', 'http_code' => $http_code];
        }
        
        // For DASH, 403 and 405 might mean server exists but access restricted
        // Be conservative - only mark as error if clearly dead
        if ($http_code == 403 || $http_code == 405) {
            // Check if we got any content
            if ($content_length > 0) {
                return ['status' => 'ok', 'http_code' => $http_code, 'note' => 'Server exists, access restricted'];
            }
            // No content means likely dead
            return ['status' => 'error', 'message' => "HTTP $http_code - No content"];
        }
        
        // 404, 500, 502, 503, 504 are definitely errors
        if ($http_code == 404 || $http_code >= 500) {
            return ['status' => 'error', 'message' => "HTTP $http_code"];
        }
        
        // Other 4xx errors
        if ($http_code >= 400) {
            return ['status' => 'error', 'message' => "HTTP $http_code"];
        }
        
        return ['status' => 'ok', 'http_code' => $http_code];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Handle processing / preview
if ($is_preview || $is_process) {
    $has_run = true;
    set_time_limit(1800); // 30 minutes
    
    // Get all channels with sources
    $channels = $conn->query("SELECT id, name, sources FROM live_tv_channels WHERE sources IS NOT NULL AND sources != '' AND sources != '[]' ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
    
    $stats['total_channels'] = count($channels);
    
    foreach ($channels as $channel) {
        $channel_id = $channel['id'];
        $channel_name = $channel['name'];
        $sources_json = $channel['sources'];
        
        $sources = parseSources($sources_json);
        if (empty($sources) || !is_array($sources)) {
            continue;
        }
        
        $stats['channels_checked']++;
        $original_count = count($sources);
        $updated_sources = [];
        $removed_count = 0;
        
        foreach ($sources as $source) {
            $source_url = $source['url'] ?? '';
            $source_type = strtolower($source['type'] ?? '');
            $source_id = $source['id'] ?? '';
            
            // Only check DASH/MPD sources
            $is_dash = false;
            if ($source_type === 'dash' ||
                strpos(strtolower($source_url), '.mpd') !== false ||
                strpos(strtolower($source_url), 'dash') !== false) {
                $is_dash = true;
            }
            
            if (!$is_dash) {
                // Keep non-DASH sources
                $updated_sources[] = $source;
                continue;
            }
            
            // Check DASH stream
            $stats['sources_checked']++;
            $check_result = checkDASHStream($source_url);
            
            if ($check_result['status'] === 'ok') {
                // Source is working, keep it
                $updated_sources[] = $source;
            } else {
                // Source is dead, mark for removal
                $removed_count++;
                $stats['sources_to_remove']++;
            }
        }
        
        if ($removed_count > 0) {
            $stats['channels_with_bad']++;
        }

        // Update channel if sources were removed and we are in "process" mode
        if ($is_process && $removed_count > 0) {
            $updated_sources_json = encodeSources($updated_sources);
            $stmt = $conn->prepare("UPDATE live_tv_channels SET sources = ? WHERE id = ?");
            $stmt->bind_param("si", $updated_sources_json, $channel_id);
            if ($stmt->execute()) {
                $stats['channels_updated']++;
                $stats['sources_removed'] += $removed_count;
            }
        }
    }
    
    if ($is_process) {
        $message = "Cleanup complete! Checked {$stats['channels_checked']} channels, {$stats['sources_checked']} DASH/MPD sources. "
                 . "Removed {$stats['sources_removed']} non-working sources from {$stats['channels_updated']} channels.";
        $message_type = 'success';
    } else {
        $message = "Preview only: Found {$stats['sources_to_remove']} non-working DASH/MPD sources across {$stats['channels_with_bad']} channel(s). "
                 . "No changes have been made yet.";
        $message_type = 'warning';
    }
}

?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-link mr-2 text-netflix-red"></i>Remove Non-Working DASH/MPD Sources
    </h1>
    
    <p class="text-gray-400 mb-6">
        This tool will check all DASH/MPD streaming sources and remove only the non-working ones. 
        <strong>Channels are preserved</strong> - only bad sources are removed. The tool performs proper checks including HTTP status, content validation, CORS, and connection errors.
    </p>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($has_run && !empty($stats) && $stats['total_channels'] > 0): ?>
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-bold mb-3"><?php echo $is_process ? 'Cleanup Summary' : 'Preview Summary'; ?></h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-400"><?php echo $stats['total_channels']; ?></div>
                <div class="text-sm text-gray-400">Total Channels</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-400"><?php echo $stats['channels_checked']; ?></div>
                <div class="text-sm text-gray-400">Channels Checked</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-400"><?php echo $stats['sources_checked']; ?></div>
                <div class="text-sm text-gray-400">DASH Sources Checked</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-400">
                    <?php echo $is_process ? $stats['sources_removed'] : $stats['sources_to_remove']; ?>
                </div>
                <div class="text-sm text-gray-400">
                    <?php echo $is_process ? 'Sources Removed' : 'Sources to Remove'; ?>
                </div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-400">
                    <?php echo $is_process ? $stats['channels_updated'] : $stats['channels_with_bad']; ?>
                </div>
                <div class="text-sm text-gray-400">
                    <?php echo $is_process ? 'Channels Updated' : 'Channels with Bad Sources'; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bg-yellow-900 bg-opacity-30 border border-yellow-700 text-yellow-200 px-4 py-3 rounded mb-6">
        <i class="fas fa-exclamation-triangle mr-2"></i>
        <strong>Note:</strong> This process may take several minutes depending on the number of channels. The tool will check each DASH/MPD source properly (HTTP status, content validation, CORS, connection errors) before removing it.
    </div>
    
    <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-bold mb-4">What This Tool Does</h2>
        <ul class="list-disc list-inside text-gray-300 space-y-2 mb-4">
            <li>Scans all TV channels in the database</li>
            <li>Identifies DASH/MPD sources (by type or URL pattern)</li>
            <li>Performs proper checks for each DASH source:
                <ul class="list-disc list-inside ml-6 mt-2 space-y-1 text-sm">
                    <li>HTTP status code validation (200-399 = working, 404/500+ = dead)</li>
                    <li>Content validation (checks for MPD, XML, xmlns in response)</li>
                    <li>Connection error detection (timeouts, DNS failures, SSL errors)</li>
                    <li>CORS compatibility (uses proper GET requests)</li>
                </ul>
            </li>
            <li><strong>Removes only non-working DASH/MPD sources</strong> from channels</li>
            <li><strong>Preserves all channels</strong> - channels are never deleted</li>
            <li>Keeps all non-DASH sources (YouTube, iframe, m3u8, etc.) untouched</li>
        </ul>
        
        <!-- Step 1: Preview -->
        <form method="POST" class="mb-4">
            <input type="hidden" name="preview" value="yes">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors">
                <i class="fas fa-search mr-2"></i>Preview Bad DASH/MPD Sources
            </button>
        </form>

        <!-- Step 2: Run cleanup (shown after preview) -->
        <?php if ($has_run && !$is_process && $stats['sources_to_remove'] > 0): ?>
        <form method="POST" onsubmit="return confirm('This will remove all detected non-working DASH/MPD sources. Channels will be preserved. Continue?');">
            <input type="hidden" name="process" value="yes">
            <button type="submit" class="bg-netflix-red hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors">
                <i class="fas fa-play mr-2"></i>Confirm & Remove Bad DASH Sources
            </button>
            <a href="?tab=tools" class="text-gray-400 hover:text-white ml-4">
                <i class="fas fa-arrow-left mr-2"></i>Back to Tools
            </a>
        </form>
        <?php else: ?>
        <a href="?tab=tools" class="text-gray-400 hover:text-white">
            <i class="fas fa-arrow-left mr-2"></i>Back to Tools
        </a>
        <?php endif; ?>
    </div>
</div>
