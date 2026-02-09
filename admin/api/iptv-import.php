<?php
/**
 * API Endpoint for IPTV Excel Import with Progress Tracking and Batch Processing
 */
// Prevent any output before JSON and suppress errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Set error handler to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
        exit;
    }
});

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    // Set longer session timeout for large imports
    ini_set('session.gc_maxlifetime', 7200); // 2 hours
    ini_set('session.cookie_lifetime', 7200); // 2 hours
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Check admin login - if not logged in, return JSON error instead of redirecting
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Please log in as admin.'
    ]);
    exit;
}

// Set unlimited execution time and increase memory limit for large imports
set_time_limit(0); // Unlimited execution time
ini_set('max_execution_time', 0); // Also set via ini_set
ini_set('memory_limit', '512M'); // Increase memory limit

// Get database connection
try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection returned null');
    }
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Function to download image from URL
// $channel_name is used to generate a friendly file name for the logo
function downloadImageFromUrl($url, $upload_dir, $channel_name = '') {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    
    try {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Reduced timeout for faster processing
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code !== 200 || empty($image_data) || !empty($curl_error)) {
            return null;
        }
        
        $valid_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array(strtolower($content_type), $valid_types)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_buffer($finfo, $image_data);
            finfo_close($finfo);
            if (!in_array(strtolower($detected_type), $valid_types)) {
                return null;
            }
        }
        
        $extension = 'jpg';
        if (strpos($content_type, 'png') !== false) $extension = 'png';
        elseif (strpos($content_type, 'gif') !== false) $extension = 'gif';
        elseif (strpos($content_type, 'webp') !== false) $extension = 'webp';
        elseif (strpos($content_type, 'svg') !== false) $extension = 'svg';
        
        // Generate a clean filename based on channel name (no spaces, safe chars)
        if (!function_exists('generateChannelLogoBaseName')) {
            // Fallback in case helper is not loaded for some reason
            $baseName = strtolower(trim($channel_name));
            $baseName = preg_replace('/[^a-z0-9]/', '', $baseName);
            if ($baseName === '' || $baseName === null) {
                $baseName = 'channel_logo_' . time();
            }
        } else {
            $baseName = generateChannelLogoBaseName($channel_name);
        }

        $filename = $baseName . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (file_put_contents($filepath, $image_data)) {
            return 'uploads/tv-logos/' . $filename;
        }
    } catch (Exception $e) {
        error_log("Error downloading image: " . $e->getMessage());
    }
    
    return null;
}

function detectStreamType($url) {
    if (empty($url)) return 'embed';
    $url_lower = strtolower($url);
    
    if (strpos($url_lower, '.m3u8') !== false || strpos($url_lower, 'm3u8') !== false) {
        return 'hls';
    } elseif (strpos($url_lower, '.mpd') !== false || strpos($url_lower, 'dash') !== false) {
        return 'dash';
    } elseif (strpos($url_lower, 'youtube.com') !== false || strpos($url_lower, 'youtu.be') !== false) {
        return 'youtube';
    } elseif (strpos($url_lower, '.mp4') !== false || strpos($url_lower, '.m3u') !== false) {
        return 'embed';
    } else {
        return 'embed';
    }
}

function sourceExists($sources_json, $new_url) {
    if (empty($sources_json)) return false;
    $sources = json_decode($sources_json, true);
    if (!is_array($sources)) return false;
    foreach ($sources as $source) {
        if (isset($source['url']) && $source['url'] === $new_url) {
            return true;
        }
    }
    return false;
}

function addSourceToChannel($conn, $channel_id, $new_url, $stream_type) {
    $channel_data = $conn->prepare("SELECT sources FROM live_tv_channels WHERE id = ?");
    $channel_data->bind_param("i", $channel_id);
    $channel_data->execute();
    $result = $channel_data->get_result();
    $channel = $result->fetch_assoc();
    
    $sources = json_decode($channel['sources'] ?? '[]', true);
    if (!is_array($sources)) {
        $sources = [];
    }
    
    $sources[] = [
        'id' => 'src_' . time() . '_' . uniqid(),
        'label' => 'Source ' . (count($sources) + 1),
        'url' => $new_url,
        'type' => $stream_type,
        'quality' => 'Auto',
        'language' => 'English',
        'priority' => count($sources),
        'isActive' => true,
        'isVisible' => true
    ];
    
    $sources_json = json_encode($sources, JSON_UNESCAPED_SLASHES);
    
    $update = $conn->prepare("UPDATE live_tv_channels SET sources = ? WHERE id = ?");
    $update->bind_param("si", $sources_json, $channel_id);
    return $update->execute();
}

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? 'upload';

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../../uploads/tv-logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        try {
            $data = [];
            
            // Handle CSV files
            if ($file_ext === 'csv') {
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle !== false) {
                    $headers = fgetcsv($handle);
                    if ($headers === false) {
                        throw new Exception("Could not read CSV headers");
                    }
                    
                    $headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $headers);
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) !== count($headers)) continue;
                        $data[] = array_combine($headers, $row);
                    }
                    fclose($handle);
                }
            }
            // Handle Excel files
            elseif (in_array($file_ext, ['xlsx', 'xls'])) {
                if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    if (count($rows) < 2) {
                        throw new Exception("Excel file must have at least a header row and one data row");
                    }
                    
                    $headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $rows[0]);
                    
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        while (count($row) < count($headers)) {
                            $row[] = '';
                        }
                        $row = array_slice($row, 0, count($headers));
                        $data[] = array_combine($headers, $row);
                    }
                } elseif ($file_ext === 'xlsx' && class_exists('ZipArchive')) {
                    // Simple XLSX parser using ZipArchive
                    $zip = new ZipArchive();
                    if ($zip->open($file['tmp_name']) === TRUE) {
                        $sharedStrings = [];
                        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
                        if ($sharedStringsXml) {
                            preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $sharedStringsXml, $matches);
                            $sharedStrings = $matches[1];
                        }
                        
                        $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                        if (!$worksheetXml) {
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $filename = $zip->getNameIndex($i);
                                if (preg_match('/xl\/worksheets\/sheet(\d+)\.xml$/', $filename)) {
                                    $worksheetXml = $zip->getFromName($filename);
                                    break;
                                }
                            }
                        }
                        
                        if ($worksheetXml) {
                            preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $worksheetXml, $rowMatches);
                            $rows = [];
                            
                            foreach ($rowMatches[1] as $rowXml) {
                                preg_match_all('/<c[^>]*r="([^"]*)"[^>]*t="([^"]*)"[^>]*>.*?<v>(.*?)<\/v>/s', $rowXml, $cellMatches, PREG_SET_ORDER);
                                
                                $row = [];
                                foreach ($cellMatches as $cell) {
                                    $cellRef = $cell[1];
                                    $cellType = $cell[2];
                                    $cellValue = $cell[3];
                                    
                                    preg_match('/^([A-Z]+)/', $cellRef, $colMatch);
                                    $colIndex = 0;
                                    if (isset($colMatch[1])) {
                                        $colLetters = $colMatch[1];
                                        for ($i = 0; $i < strlen($colLetters); $i++) {
                                            $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - ord('A') + 1);
                                        }
                                        $colIndex--;
                                    }
                                    
                                    if ($cellType === 's' && isset($sharedStrings[intval($cellValue)])) {
                                        $row[$colIndex] = $sharedStrings[intval($cellValue)];
                                    } else {
                                        $row[$colIndex] = $cellValue;
                                    }
                                }
                                
                                if (!empty($row)) {
                                    $maxCol = max(array_keys($row));
                                    for ($i = 0; $i <= $maxCol; $i++) {
                                        if (!isset($row[$i])) {
                                            $row[$i] = '';
                                        }
                                    }
                                    ksort($row);
                                    $rows[] = $row;
                                }
                            }
                            
                            if (count($rows) < 2) {
                                throw new Exception("Excel file must have at least a header row and one data row");
                            }
                            
                            $headers = array_map(function($h) {
                                return strtolower(trim($h));
                            }, $rows[0]);
                            
                            for ($i = 1; $i < count($rows); $i++) {
                                $row = $rows[$i];
                                while (count($row) < count($headers)) {
                                    $row[] = '';
                                }
                                $row = array_slice($row, 0, count($headers));
                                $data[] = array_combine($headers, $row);
                            }
                        } else {
                            throw new Exception("Could not read worksheet from Excel file.");
                        }
                        
                        $zip->close();
                    } else {
                        throw new Exception("Could not open Excel file.");
                    }
                } else {
                    throw new Exception("Excel file support requires PhpSpreadsheet or ZipArchive extension.");
                }
            } else {
                throw new Exception("Unsupported file format.");
            }
            
            if (empty($data)) {
                throw new Exception("No data found in file.");
            }
            
            $total_rows = count($data);
            
            // Store data in session for batch processing
            $_SESSION['iptv_import_data'] = $data;
            $_SESSION['iptv_import_progress'] = [
                'total' => $total_rows,
                'processed' => 0,
                'added' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'current_channel' => '',
                'status' => 'ready', // ready, processing, paused, completed, error
                'paused_at' => null
            ];
            
            // IMPORTANT: Clear any existing stop flag when starting new import
            unset($_SESSION['iptv_import_stopped']);
            error_log("IPTV Import: Starting new import, cleared any existing stop flag");
            
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully. Ready to process.',
                'total' => $total_rows
            ]);
            exit;
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'File upload error: ' . $file['error']
        ]);
        exit;
    }
}

// Handle batch processing
elseif ($action === 'process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Ensure session is active
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verify session is still valid
        if (!isset($_SESSION['admin_id'])) {
            throw new Exception('Session expired. Please refresh and try again.');
        }
        
        // Clear any output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        if (!isset($_SESSION['iptv_import_data']) || empty($_SESSION['iptv_import_data'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'No import data found. Please upload a file first.'
            ]);
            exit;
        }
        
        $data = $_SESSION['iptv_import_data'];
        $progress = $_SESSION['iptv_import_progress'] ?? [
            'total' => count($data),
            'processed' => 0,
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'current_channel' => '',
            'status' => 'processing'
        ];
        
        // Check if paused
        if ($progress['status'] === 'paused') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'paused' => true,
                'progress' => $progress
            ]);
            exit;
        }
        
        $upload_dir = __DIR__ . '/../../uploads/tv-logos/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception("Failed to create upload directory: " . $upload_dir);
            }
        }
        
        $batch_size = 50; // Process 50 channels per batch (increased for better performance)
        $start_index = $progress['processed'];
        $end_index = min($start_index + $batch_size, count($data));
        
        $progress['status'] = 'processing';
        
        // IMPORTANT: Clear any existing stop flag at the start of each batch
        // This ensures the flag is only set when user explicitly clicks stop
        // and doesn't persist from previous requests
        if (isset($_SESSION['iptv_import_stopped'])) {
            // Only clear if it's not explicitly true (to allow user-initiated stops)
            if ($_SESSION['iptv_import_stopped'] !== true) {
                error_log("IPTV Import: Clearing invalid stop flag: " . var_export($_SESSION['iptv_import_stopped'], true));
                unset($_SESSION['iptv_import_stopped']);
            } else {
                // It's true, so user wants to stop - but we'll check this below
                error_log("IPTV Import: Stop flag is TRUE at start of batch - user requested stop");
            }
        }
        
        // Check if stopped before processing batch
        // Only check if explicitly set to true (not if it doesn't exist or is false)
        if (isset($_SESSION['iptv_import_stopped']) && $_SESSION['iptv_import_stopped'] === true) {
            error_log("IPTV Import: Stop flag detected. Value: " . var_export($_SESSION['iptv_import_stopped'], true));
            $progress['status'] = 'stopped';
            $progress['current_channel'] = 'Import stopped by user';
            $_SESSION['iptv_import_progress'] = $progress;
            unset($_SESSION['iptv_import_stopped']); // Clear stop flag
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'progress' => $progress,
                'stopped' => true
            ]);
            exit;
        }
        
        // Process batch
        for ($i = $start_index; $i < $end_index; $i++) {
            if (!isset($data[$i])) {
                continue;
            }
            $row = $data[$i];
            
            try {
            $channel_name = trim($row['channel name'] ?? $row['channel_name'] ?? $row['name'] ?? '');
            $country = trim($row['country'] ?? 'US');
            $category = trim($row['category'] ?? '');
            $description = trim($row['description'] ?? '');
            $logo_url = trim($row['logo'] ?? '');
            $stream_url = trim($row['url'] ?? $row['stream url'] ?? $row['stream_url'] ?? '');
            $status = strtolower(trim($row['status'] ?? 'active'));
            
            // Skip row if channel name or URL is missing
            if (empty($channel_name) || empty($stream_url)) {
                $progress['skipped']++;
                $progress['current_channel'] = 'Skipped row ' . ($i + 1) . ' (missing name or URL)';
                $progress['processed']++;
                $_SESSION['iptv_import_progress'] = $progress;
                continue;
            }
            
            $progress['current_channel'] = $channel_name;
            $is_active = ($status === 'active' || $status === '1' || $status === 'yes' || $status === 'true');
            
            // Check if channel exists FIRST before downloading logo
            $check = $conn->prepare("SELECT id, sources, logo FROM live_tv_channels WHERE name = ?");
            $check->bind_param("s", $channel_name);
            $check->execute();
            $result = $check->get_result();
            
            $stream_type = detectStreamType($stream_url);
            $logo_path = null;
            
            if ($result->num_rows > 0) {
                // Channel exists - use existing logo, don't download again
                $existing = $result->fetch_assoc();
                $logo_path = $existing['logo'] ?? null;
                $existing_sources = $existing['sources'] ?? '[]';
                
                if (sourceExists($existing_sources, $stream_url)) {
                    $progress['skipped']++;
                    $progress['current_channel'] = $channel_name . ' (skipped - source exists)';
                    $progress['processed']++;
                    $_SESSION['iptv_import_progress'] = $progress;
                    continue;
                } else {
                    // Check if stopped before updating
                    if (isset($_SESSION['iptv_import_stopped']) && $_SESSION['iptv_import_stopped'] === true) {
                        $progress['status'] = 'stopped';
                        $progress['current_channel'] = 'Import stopped by user';
                        break;
                    }
                    
                    // Add new source to existing channel
                    if (addSourceToChannel($conn, $existing['id'], $stream_url, $stream_type)) {
                        $progress['updated']++;
                        $progress['current_channel'] = $channel_name . ' (updated)';
                    } else {
                        $progress['errors']++;
                        $progress['current_channel'] = $channel_name . ' (error adding source)';
                    }
                    $progress['processed']++;
                    
                    // Update session after each channel for real-time progress
                    $_SESSION['iptv_import_progress'] = $progress;
                    continue;
                }
            } else {
                // Channel doesn't exist - FIRST check for duplicates BEFORE downloading logo
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $channel_name)));
                $slug = preg_replace('/-+/', '-', $slug);
                $slug = trim($slug, '-');
                
                // Check if channel name already exists (to avoid duplicates) - DO THIS FIRST
                $name_check = $conn->prepare("SELECT id FROM live_tv_channels WHERE name = ?");
                $name_check->bind_param("s", $channel_name);
                $name_check->execute();
                if ($name_check->get_result()->num_rows > 0) {
                    // Channel name already exists - skip to avoid duplicate (don't download logo)
                    $progress['skipped']++;
                    $progress['current_channel'] = $channel_name . ' (skipped - duplicate channel name)';
                    $progress['processed']++;
                    $_SESSION['iptv_import_progress'] = $progress;
                    continue;
                }
                
                // Check if slug already exists (to avoid duplicates)
                $slug_check = $conn->prepare("SELECT id FROM live_tv_channels WHERE slug = ?");
                $slug_check->bind_param("s", $slug);
                $slug_check->execute();
                if ($slug_check->get_result()->num_rows > 0) {
                    $slug = $slug . '-' . time() . '-' . uniqid();
                }
                
                // NOW download logo if provided (only after confirming no duplicate)
                if (!empty($logo_url)) {
                    // Check if stopped before logo download
                    if (isset($_SESSION['iptv_import_stopped']) && $_SESSION['iptv_import_stopped'] === true) {
                        $progress['status'] = 'stopped';
                        $progress['current_channel'] = 'Import stopped by user';
                        break;
                    }
                    
                    // Use channel name to generate a friendly logo filename
                    $logo_path = downloadImageFromUrl($logo_url, $upload_dir, $channel_name);
                    
                    // Check if stopped after logo download
                    if (isset($_SESSION['iptv_import_stopped']) && $_SESSION['iptv_import_stopped'] === true) {
                        $progress['status'] = 'stopped';
                        $progress['current_channel'] = 'Import stopped by user';
                        break;
                    }
                    
                    if (!$logo_path) {
                        // Logo download failed - skip this channel (don't import)
                        $progress['skipped']++;
                        $progress['current_channel'] = $channel_name . ' (skipped - logo download failed)';
                        $progress['processed']++;
                        $_SESSION['iptv_import_progress'] = $progress;
                        continue;
                    }
                    // Logo downloaded successfully - convert to full URL
                    if (defined('BASE_URL') && !empty(BASE_URL)) {
                        $logo_path = rtrim(BASE_URL, '/') . '/' . $logo_path;
                    } else {
                        // Fallback: try to get from database settings
                        try {
                            $settings_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'site_url' LIMIT 1");
                            if ($settings_query && $settings_row = $settings_query->fetch_assoc()) {
                                $base_url = rtrim($settings_row['setting_value'], '/');
                                $logo_path = $base_url . '/' . $logo_path;
                            } else {
                                $logo_path = '/' . $logo_path;
                            }
                        } catch (Exception $e) {
                            $logo_path = '/' . $logo_path;
                        }
                    }
                } else {
                    // No logo URL provided - allow import without logo
                    $logo_path = null;
                }
                
                $sources = [[
                    'id' => 'src_' . time() . '_' . uniqid(),
                    'label' => 'Source 1',
                    'url' => $stream_url,
                    'type' => $stream_type,
                    'quality' => 'Auto',
                    'language' => 'English',
                    'priority' => 0,
                    'isActive' => true,
                    'isVisible' => true
                ]];
                $sources_json = json_encode($sources, JSON_UNESCAPED_SLASHES);
                
                // Insert new channel with stream_url (required field)
                // Parameters: name, description, logo, stream_url, category, country, language, is_active, is_free, is_premium, slug, sources
                // Types: s, s, s, s, s, s, s (hardcoded 'en'), i, i (hardcoded 1), i (hardcoded 0), s, s
                $insert = $conn->prepare("INSERT INTO live_tv_channels (name, description, logo, stream_url, category, country, language, is_active, is_free, is_premium, slug, sources) VALUES (?, ?, ?, ?, ?, ?, 'en', ?, 1, 0, ?, ?)");
                
                if (!$insert) {
                    $progress['errors']++;
                    $progress['current_channel'] = $channel_name . ' (error preparing statement: ' . $conn->error . ')';
                    error_log("IPTV Import: Failed to prepare INSERT for '{$channel_name}': " . $conn->error);
                    $progress['processed']++;
                    continue;
                }
                
                // bind_param: s=string, i=integer
                // Parameters: name(s), description(s), logo(s), stream_url(s), category(s), country(s), is_active(i), slug(s), sources(s)
                // Total: 9 parameters (language, is_free, is_premium are hardcoded)
                $insert->bind_param("ssssssiss", $channel_name, $description, $logo_path, $stream_url, $category, $country, $is_active, $slug, $sources_json);
                
                // Check if stopped before insert
                if (isset($_SESSION['iptv_import_stopped']) && $_SESSION['iptv_import_stopped'] === true) {
                    $progress['status'] = 'stopped';
                    $progress['current_channel'] = 'Import stopped by user';
                    break;
                }
                
                if ($insert->execute()) {
                    $inserted_id = $conn->insert_id;
                    $progress['added']++;
                    $progress['current_channel'] = $channel_name . ' (added - ID: ' . $inserted_id . ')';
                    error_log("IPTV Import: Successfully added channel '{$channel_name}' (ID: {$inserted_id})");
                } else {
                    $error_msg = $insert->error ?: $conn->error;
                    $progress['errors']++;
                    $progress['current_channel'] = $channel_name . ' (error: ' . $error_msg . ')';
                    error_log("IPTV Import: Failed to insert channel '{$channel_name}': {$error_msg}");
                    error_log("IPTV Import: Data - name: '{$channel_name}', stream_url: '{$stream_url}', logo: '{$logo_path}'");
                }
                $progress['processed']++;
                
                // Update session after each channel for real-time progress
                $_SESSION['iptv_import_progress'] = $progress;
                
                // Update session after each channel for real-time progress
                $_SESSION['iptv_import_progress'] = $progress;
            }
        } catch (Exception $e) {
            // Skip this channel and continue
            $progress['errors']++;
            $progress['current_channel'] = 'Error on row ' . ($i + 1) . ': ' . $e->getMessage();
            $progress['processed']++;
            error_log("IPTV Import Error Row " . ($i + 1) . ": " . $e->getMessage());
            
            // Update session after error
            $_SESSION['iptv_import_progress'] = $progress;
        }
    }
    
        // Check if stopped after batch (ONLY if explicitly set to true)
        // Add explicit check to prevent false positives
        $explicitly_stopped = false;
        if (isset($_SESSION['iptv_import_stopped'])) {
            if ($_SESSION['iptv_import_stopped'] === true) {
                $explicitly_stopped = true;
                error_log("IPTV Import: Explicit stop detected after batch. Processed: " . $progress['processed'] . " / " . count($data));
            } else {
                // Flag exists but is not true - clear it (shouldn't happen)
                error_log("IPTV Import: Warning - Stop flag exists but is not true: " . var_export($_SESSION['iptv_import_stopped'], true));
                unset($_SESSION['iptv_import_stopped']);
            }
        }
        
        if ($explicitly_stopped) {
            $progress['status'] = 'stopped';
            $progress['current_channel'] = 'Import stopped by user';
            unset($_SESSION['iptv_import_stopped']); // Clear stop flag
        }
        // Check if completed
        elseif ($progress['processed'] >= count($data)) {
            $progress['status'] = 'completed';
            $progress['current_channel'] = 'Import completed!';
            unset($_SESSION['iptv_import_data']); // Clear data after completion
            unset($_SESSION['iptv_import_stopped']); // Clear stop flag
        } else {
            // Ensure status is still processing if not stopped and not completed
            if ($progress['status'] !== 'completed' && $progress['status'] !== 'stopped') {
                $progress['status'] = 'processing';
            }
            // No sleep needed - process continuously for better performance
        }
        
        // Update session
        $_SESSION['iptv_import_progress'] = $progress;
        
        // Ensure no output before JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'progress' => $progress,
            'completed' => $progress['status'] === 'completed',
            'stopped' => $progress['status'] === 'stopped'
        ]);
        exit;
        
    } catch (Exception $e) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        error_log("IPTV Import Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        echo json_encode([
            'success' => false,
            'error' => 'Processing error: ' . $e->getMessage()
        ]);
        exit;
    } catch (Error $e) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        http_response_code(500);
        error_log("IPTV Import Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle pause
elseif ($action === 'pause' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['iptv_import_progress'])) {
        $_SESSION['iptv_import_progress']['status'] = 'paused';
        $_SESSION['iptv_import_progress']['paused_at'] = time();
        echo json_encode([
            'success' => true,
            'message' => 'Import paused'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No import in progress'
        ]);
    }
    exit;
}

// Handle resume
elseif ($action === 'resume' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SESSION['iptv_import_progress']) && $_SESSION['iptv_import_progress']['status'] === 'paused') {
        $_SESSION['iptv_import_progress']['status'] = 'processing';
        $_SESSION['iptv_import_progress']['paused_at'] = null;
        echo json_encode([
            'success' => true,
            'message' => 'Import resumed',
            'progress' => $_SESSION['iptv_import_progress']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No paused import found'
        ]);
    }
    exit;
}

// Handle stop
elseif ($action === 'stop' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set stop flag immediately - this will be checked in the processing loop
    $_SESSION['iptv_import_stopped'] = true;
    // Update progress status immediately if it exists
    if (isset($_SESSION['iptv_import_progress'])) {
        $_SESSION['iptv_import_progress']['status'] = 'stopped';
        $_SESSION['iptv_import_progress']['current_channel'] = 'Import stopped by user';
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Stop signal sent. Processing will stop after current batch.',
        'stopped' => true
    ]);
    exit;
}

// Handle progress check (without processing)
elseif ($action === 'progress' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $progress = $_SESSION['iptv_import_progress'] ?? [
        'total' => 0,
        'processed' => 0,
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'current_channel' => '',
        'status' => 'idle'
    ];
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'progress' => $progress,
        'completed' => $progress['status'] === 'completed',
        'stopped' => $progress['status'] === 'stopped'
    ]);
    exit;
}

else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action or no file uploaded'
    ]);
    exit;
}
