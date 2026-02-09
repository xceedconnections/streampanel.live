<?php
/**
 * DASH Stream Link Checker - Cron Job
 * Checks DASH (.mpd) streaming links and removes dead sources
 * Checks ALL channels in one run (designed for 2 runs per day)
 * 
 * Usage: php cron/check-dash-streams.php
 * Cron: Run 2 times per day using PHP interpreter
 */

// Set execution time limit (increased for checking all channels)
set_time_limit(1800); // 30 minutes max (for checking all channels)
ini_set('max_execution_time', 1800);

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Log file
$log_file = __DIR__ . '/../logs/dash-check.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function writeLog($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_message, FILE_APPEND);
    echo $log_message;
}

// Check if stream URL is accessible (specifically for DASH .mpd)
function checkDASHStream($url, $timeout = 15) {
    if (empty($url)) {
        return ['status' => 'error', 'message' => 'Empty URL'];
    }
    
    // Verify it's a DASH URL (.mpd)
    $url_lower = strtolower($url);
    if (strpos($url_lower, '.mpd') === false && 
        strpos($url_lower, 'mpd') === false &&
        strpos($url_lower, 'dash') === false) {
        return ['status' => 'skip', 'message' => 'Not a DASH URL'];
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
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $content_length = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
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
                $body = substr($response, strpos($response, "\r\n\r\n") + 4);
                if (stripos($body, '<MPD') !== false ||
                    stripos($body, '</MPD>') !== false ||
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
        if ($http_code >= 400 && $http_code < 500) {
            return ['status' => 'error', 'message' => "HTTP $http_code"];
        }
        
        return ['status' => 'error', 'message' => "HTTP $http_code"];
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}

// Helper: determine if a source should be treated as a DASH stream based on its type.
// We ONLY want to auto-remove links whose explicit source type is set to "dash".
// If a source is iframe/embed/other but its URL happens to contain ".mpd", it will be skipped.
function isDASHSourceType($source) {
    $source_type = strtolower($source['type'] ?? '');
    return $source_type === 'dash';
}

// Main execution
writeLog("=== DASH Stream Check Started ===");

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        writeLog("ERROR: Database connection failed");
        exit(1);
    }
    
    // Get ALL channels with DASH sources (check everything in one run)
    $query = "SELECT id, name, sources FROM live_tv_channels 
              WHERE is_active = 1
              AND sources IS NOT NULL 
              AND sources != '' 
              AND sources != '[]' 
              AND sources != 'null'
              AND (sources LIKE '%\"type\":\"dash\"%' OR sources LIKE '%\"type\":\"DASH\"%')
              ORDER BY id ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        writeLog("ERROR: Failed to query channels: " . $conn->error);
        exit(1);
    }
    
    $channels = $result->fetch_all(MYSQLI_ASSOC);
    $total_checked = 0;
    $total_dead_removed = 0;
    $total_channels_updated = 0;
    
    if (empty($channels)) {
        writeLog("No channels with DASH sources found.");
        exit(0);
    }
    
    $total_channels = count($channels);
    writeLog("Found $total_channels channels with DASH sources. Starting check...");
    
    foreach ($channels as $channel) {
        $channel_id = $channel['id'];
        $channel_name = $channel['name'];
        $sources_json = $channel['sources'];
        
        writeLog("Checking channel: $channel_name (ID: $channel_id)");
        
        $sources = json_decode($sources_json, true);
        if (!is_array($sources) || empty($sources)) {
            writeLog("  - No sources found, skipping");
            continue;
        }
        
        $original_count = count($sources);
        $dead_sources = [];
        $updated_sources = [];
        
        foreach ($sources as $source) {
            $source_url = $source['url'] ?? '';
            $source_id = $source['id'] ?? '';

            // Only check sources whose explicit type is set to "dash".
            // If source type is iframe/other, it will be kept as-is even if URL contains ".mpd".
            $is_dash = isDASHSourceType($source);

            if (!$is_dash) {
                // Keep non-DASH sources
                $updated_sources[] = $source;
                continue;
            }
            
            // Check DASH stream
            writeLog("  - Checking DASH source: " . substr($source_url, 0, 80) . "...");
            $check_result = checkDASHStream($source_url);
            
            if ($check_result['status'] === 'ok') {
                // Source is working, keep it
                $updated_sources[] = $source;
                writeLog("    &#10003; Working (HTTP " . ($check_result['http_code'] ?? 'OK') . ")");
            } else {
                // Source is dead, mark for removal
                $dead_sources[] = [
                    'id' => $source_id,
                    'url' => substr($source_url, 0, 80),
                    'reason' => $check_result['message'] ?? 'Unknown error'
                ];
                writeLog("    &#10007; Dead: " . ($check_result['message'] ?? 'Unknown error'));
                $total_dead_removed++;
            }
            
            // Small delay to avoid overwhelming servers
            usleep(200000); // 0.2 seconds
        }
        
        // Update channel if sources were removed
        if (count($dead_sources) > 0) {
            $new_sources_json = json_encode($updated_sources, JSON_UNESCAPED_SLASHES);
            
            $update_stmt = $conn->prepare("UPDATE live_tv_channels SET sources = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_sources_json, $channel_id);
            
            if ($update_stmt->execute()) {
                $total_channels_updated++;
                writeLog("  - Updated: Removed " . count($dead_sources) . " dead DASH source(s)");
                
                // If no sources left, log warning but don't delete channel
                if (empty($updated_sources)) {
                    writeLog("  - WARNING: Channel has no sources left, but channel not deleted");
                }
            } else {
                writeLog("  - ERROR: Failed to update channel: " . $conn->error);
            }
            $update_stmt->close();
        } else {
            writeLog("  - All sources working");
        }
        
        $total_checked++;
        
        // Show progress every 10 channels
        if ($total_checked % 10 == 0) {
            writeLog("Progress: $total_checked/$total_channels channels checked...");
        }
    }
    
    writeLog("=== Summary ===");
    writeLog("Total channels found: $total_channels");
    writeLog("Channels checked: $total_checked");
    writeLog("Dead DASH sources removed: $total_dead_removed");
    writeLog("Channels updated: $total_channels_updated");
    writeLog("=== DASH Stream Check Completed ===");
    
    $conn->close();
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

exit(0);

