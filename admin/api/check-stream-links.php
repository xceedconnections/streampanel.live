<?php
/**
 * API Endpoint for Checking Stream Links with Progress Tracking
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAdminLogin();
header('Content-Type: application/json');

$conn = getDBConnection();

// Check if stream URL is accessible
function checkStreamUrl($url, $timeout = 10) {
    if (empty($url)) {
        return ['status' => 'error', 'message' => 'Empty URL'];
    }
    
    // Determine if this is a streaming URL (M3U8, MPD, etc.)
    $is_streaming_url = false;
    $url_lower = strtolower($url);
    if (strpos($url_lower, '.m3u8') !== false || 
        strpos($url_lower, '.mpd') !== false || 
        strpos($url_lower, 'm3u8') !== false ||
        strpos($url_lower, 'dash') !== false ||
        strpos($url_lower, 'hls') !== false ||
        strpos($url_lower, 'master.m3u8') !== false) {
        $is_streaming_url = true;
    }
    
    try {
        // For streaming URLs, always use GET request (many servers don't support HEAD)
        // For regular URLs, try HEAD first for efficiency
        $use_get = $is_streaming_url;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        
        if ($use_get) {
            // For streaming URLs, fetch first 2KB to verify it's accessible
            curl_setopt($ch, CURLOPT_RANGE, '0-2047');
            curl_setopt($ch, CURLOPT_NOBODY, false);
        } else {
            // For regular URLs, use HEAD request
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Handle connection errors
        if (!empty($curl_error)) {
            // Critical errors that definitely mean the link is dead
            if (strpos($curl_error, 'timeout') !== false || 
                strpos($curl_error, 'Connection refused') !== false ||
                strpos($curl_error, 'Could not resolve') !== false ||
                strpos($curl_error, 'SSL') !== false) {
                return ['status' => 'error', 'message' => $curl_error];
            }
            // For other errors, if we used HEAD, retry with GET
            if (!$use_get) {
                // Retry with GET request
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
                curl_setopt($ch, CURLOPT_RANGE, '0-2047');
                curl_setopt($ch, CURLOPT_NOBODY, false);
                
                $response = curl_exec($ch);
                $http_code_retry = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error_retry = curl_error($ch);
                curl_close($ch);
                
                if (empty($curl_error_retry) && ($http_code_retry >= 200 && $http_code_retry < 500)) {
                    return ['status' => 'ok', 'http_code' => $http_code_retry];
                }
            }
            return ['status' => 'error', 'message' => $curl_error];
        }
        
        // For streaming URLs, be conservative: if we reached here without a low-level
        // curl error, treat the link as working regardless of HTTP status code.
        // Many streaming servers respond with unusual codes for partial / probe requests.
        if ($is_streaming_url) {
            return [
                'status' => 'ok',
                'http_code' => $http_code ?: 'unknown',
                'note' => 'Streaming URL treated as alive (HTTP check not decisive)'
            ];
        }

        // For non-streaming URLs, keep stricter HTTP code handling.
        // Accept these HTTP codes as "working"
        // 200, 206 (partial content), 301, 302, 303, 307, 308 (redirects)
        if ($http_code >= 200 && $http_code < 400) {
            return ['status' => 'ok', 'http_code' => $http_code];
        }

        return ['status' => 'error', 'message' => "HTTP $http_code"];
    } catch (Exception $e) {
        // For streaming URLs, be more lenient - don't delete on exceptions
        if ($is_streaming_url) {
            return ['status' => 'ok', 'http_code' => 'unknown', 'note' => 'Exception occurred, assumed working'];
        }
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Check CORS (simplified - just check if we can make a request)
function checkCORS($url) {
    // For m3u8 and streaming URLs, CORS is usually not an issue if the server allows it
    // This is a simplified check
    return true; // Assume CORS is fine if URL is accessible
}

$rawInput = file_get_contents('php://input');
$inputData = json_decode($rawInput, true);
if (!is_array($inputData)) {
    $inputData = [];
}

// Action can come from query, form-encoded POST, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? ($inputData['action'] ?? '');

// Determine which source types should be checked (default: HLS/M3U8 and DASH)
$rawTypes = $_GET['types'] ?? $_POST['types'] ?? ($inputData['types'] ?? '');
$allowed_types = [];
if (!empty($rawTypes)) {
    $type_parts = explode(',', $rawTypes);
    foreach ($type_parts as $t) {
        $t = strtolower(trim($t));
        if ($t === 'm3u8') {
            $t = 'hls'; // normalize
        }
        if (in_array($t, ['hls', 'dash'], true)) {
            $allowed_types[$t] = true;
        }
    }
}
// If nothing specified, default to both
if (empty($allowed_types)) {
    $allowed_types = ['hls' => true, 'dash' => true];
}

// Optional category filter (used by Search & Check Streams tool)
$filterCategory = $_GET['category'] ?? $_POST['category'] ?? ($inputData['category'] ?? '');
$filterCategory = trim((string)$filterCategory);

// Optional specific channel IDs filter (used by Search & Check Streams tool)
$rawChannelIds = $inputData['channel_ids'] ?? ($_POST['channel_ids'] ?? []);
$channelIds = [];
if (is_array($rawChannelIds)) {
    foreach ($rawChannelIds as $cid) {
        $cid = (int)$cid;
        if ($cid > 0) {
            $channelIds[] = $cid;
        }
    }
}

if ($action === 'start') {
    // Get all channels with sources (optionally filtered by category and/or specific channel IDs)
    $conditions = ["sources IS NOT NULL AND sources != ''"];
    $params = [];
    $types  = '';

    if ($filterCategory !== '') {
        $conditions[] = "category = ?";
        $params[] = $filterCategory;
        $types  .= 's';
    }

    if (!empty($channelIds)) {
        $placeholders = implode(',', array_fill(0, count($channelIds), '?'));
        $conditions[] = "id IN ($placeholders)";
        $types .= str_repeat('i', count($channelIds));
        $params = array_merge($params, $channelIds);
    }

    $sql = "SELECT id, name, sources FROM live_tv_channels";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY id ASC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to prepare channel query'
            ]);
            exit;
        }
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $channels = $stmt->get_result();
    } else {
        $channels = $conn->query($sql);
    }

    $all_channels = [];
    
    while ($channel = $channels->fetch_assoc()) {
        $sources = json_decode($channel['sources'] ?? '[]', true);
        if (is_array($sources)) {
            foreach ($sources as $source) {
                $url = $source['url'] ?? '';
                $type = strtolower($source['type'] ?? 'embed');

                if (empty($url)) {
                    continue;
                }

                // Only include sources whose explicit type is allowed (HLS/M3U8 or DASH)
                if (!isset($allowed_types[$type])) {
                    continue;
                }

                // Additional safety: ensure URL matches expected streaming pattern for its type
                $url_lower = strtolower($url);
                if ($type === 'hls') {
                    if (strpos($url_lower, '.m3u8') === false &&
                        strpos($url_lower, 'm3u8') === false &&
                        strpos($url_lower, 'hls') === false) {
                        continue;
                    }
                } elseif ($type === 'dash') {
                    if (strpos($url_lower, '.mpd') === false &&
                        strpos($url_lower, 'dash') === false) {
                        continue;
                    }
                }

                $all_channels[] = [
                    'channel_id' => $channel['id'],
                    'channel_name' => $channel['name'],
                    'source_id' => $source['id'] ?? '',
                    'source_url' => $url,
                    'source_type' => $source['type'] ?? 'embed'
                ];
            }
        }
    }
    
    // Save to session or file for resuming
    $_SESSION['stream_check_data'] = [
        'channels' => $all_channels,
        'current_index' => 0,
        'total' => count($all_channels),
        'checked' => 0,
        'dead' => 0,
        'alive' => 0,
        'paused' => false
    ];
    
    echo json_encode([
        'success' => true,
        'total' => count($all_channels),
        'message' => 'Scan started'
    ]);
    
} elseif ($action === 'check') {
    // Check next batch of links
    $batch_size = intval($_GET['batch'] ?? 20); // Check 20 channels at a time
    $session_data = $_SESSION['stream_check_data'] ?? null;
    
    if (!$session_data || $session_data['paused']) {
        echo json_encode([
            'success' => false,
            'paused' => true,
            'message' => 'Scan is paused'
        ]);
        exit;
    }
    
    $channels = $session_data['channels'];
    $current_index = $session_data['current_index'];
    $total = $session_data['total'];
    $checked = $session_data['checked'];
    $dead = $session_data['dead'];
    $alive = $session_data['alive'];
    
    $results = [];
    $dead_links = [];
    
    // Check batch
    $end_index = min($current_index + $batch_size, $total);
    for ($i = $current_index; $i < $end_index; $i++) {
        if (!isset($channels[$i])) break;
        
        $channel_data = $channels[$i];
        $url = $channel_data['source_url'];
        
        $check_result = checkStreamUrl($url, 8);
        $checked++;
        
        if ($check_result['status'] === 'ok') {
            $alive++;
            $results[] = [
                'channel' => $channel_data['channel_name'],
                'url' => $url,
                'status' => 'alive'
            ];
        } else {
            $dead++;
            $dead_links[] = [
                'channel_id' => $channel_data['channel_id'],
                'source_id' => $channel_data['source_id'],
                'channel_name' => $channel_data['channel_name'],
                'url' => $url,
                'error' => $check_result['message'] ?? 'Unknown error'
            ];
            
            // Remove dead link from channel
            $channel = $conn->prepare("SELECT sources FROM live_tv_channels WHERE id = ?");
            $channel->bind_param("i", $channel_data['channel_id']);
            $channel->execute();
            $result = $channel->get_result();
            $channel_row = $result->fetch_assoc();
            
            $sources = json_decode($channel_row['sources'] ?? '[]', true);
            if (is_array($sources)) {
                $sources = array_filter($sources, function($source) use ($channel_data) {
                    return ($source['id'] ?? '') !== $channel_data['source_id'];
                });
                $sources = array_values($sources); // Re-index
                
                $sources_json = json_encode($sources);
                $update = $conn->prepare("UPDATE live_tv_channels SET sources = ? WHERE id = ?");
                $update->bind_param("si", $sources_json, $channel_data['channel_id']);
                $update->execute();
            }
        }
    }
    
    // Update session
    $_SESSION['stream_check_data'] = [
        'channels' => $channels,
        'current_index' => $end_index,
        'total' => $total,
        'checked' => $checked,
        'dead' => $dead,
        'alive' => $alive,
        'paused' => false
    ];
    
    $progress = ($checked / $total) * 100;
    $remaining = $total - $checked;
    
    echo json_encode([
        'success' => true,
        'checked' => $checked,
        'total' => $total,
        'remaining' => $remaining,
        'dead' => $dead,
        'alive' => $alive,
        'progress' => round($progress, 2),
        'dead_links' => $dead_links,
        'completed' => $checked >= $total
    ]);
    
} elseif ($action === 'pause') {
    if (isset($_SESSION['stream_check_data'])) {
        $_SESSION['stream_check_data']['paused'] = true;
    }
    echo json_encode(['success' => true, 'message' => 'Scan paused']);
    
} elseif ($action === 'resume') {
    if (isset($_SESSION['stream_check_data'])) {
        $_SESSION['stream_check_data']['paused'] = false;
    }
    echo json_encode(['success' => true, 'message' => 'Scan resumed']);
    
} elseif ($action === 'stop' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    unset($_SESSION['stream_check_data']);
    echo json_encode([
        'success' => true,
        'message' => 'Scan stopped and cleared'
    ]);
    
} elseif ($action === 'status') {
    $session_data = $_SESSION['stream_check_data'] ?? null;
    if ($session_data) {
        $progress = ($session_data['checked'] / $session_data['total']) * 100;
        echo json_encode([
            'success' => true,
            'checked' => $session_data['checked'],
            'total' => $session_data['total'],
            'remaining' => $session_data['total'] - $session_data['checked'],
            'dead' => $session_data['dead'],
            'alive' => $session_data['alive'],
            'progress' => round($progress, 2),
            'paused' => $session_data['paused'] ?? false,
            'completed' => $session_data['checked'] >= $session_data['total']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No scan in progress'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}
