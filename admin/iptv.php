<?php
/**
 * Admin Panel - IPTV Channels Management
 * Supports Excel (.xlsx, .xls) and CSV file import
 */
require_once __DIR__ . '/../config/config.php';
$page_title = "IPTV Channels";

$message = '';
$message_type = '';

// Function to download image from URL
function downloadImageFromUrl($url, $upload_dir) {
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    
    try {
        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
        
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($http_code !== 200 || empty($image_data)) {
            return null;
        }
        
        // Validate it's an image
        $valid_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array(strtolower($content_type), $valid_types)) {
            // Try to detect from content
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_buffer($finfo, $image_data);
            finfo_close($finfo);
            if (!in_array(strtolower($detected_type), $valid_types)) {
                return null;
            }
        }
        
        // Determine file extension
        $extension = 'jpg';
        if (strpos($content_type, 'png') !== false) $extension = 'png';
        elseif (strpos($content_type, 'gif') !== false) $extension = 'gif';
        elseif (strpos($content_type, 'webp') !== false) $extension = 'webp';
        elseif (strpos($content_type, 'svg') !== false) $extension = 'svg';
        
        // Generate unique filename
        $filename = 'tv_logo_' . time() . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        // Save file
        if (file_put_contents($filepath, $image_data)) {
            return 'uploads/tv-logos/' . $filename;
        }
    } catch (Exception $e) {
        error_log("Error downloading image from $url: " . $e->getMessage());
    }
    
    return null;
}

// Function to detect stream protocol type
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

// Function to check if source URL already exists in channel sources
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

// Function to add source to existing channel
function addSourceToChannel($conn, $channel_id, $new_url, $new_type) {
    $channel = $conn->prepare("SELECT sources FROM live_tv_channels WHERE id = ?");
    $channel->bind_param("i", $channel_id);
    $channel->execute();
    $result = $channel->get_result();
    $channel_data = $result->fetch_assoc();
    
    $sources = json_decode($channel_data['sources'] ?? '[]', true);
    if (!is_array($sources)) {
        $sources = [];
    }
    
    // Create new source
    $new_source = [
        'id' => 'src_' . time() . '_' . uniqid(),
        'label' => 'Source ' . (count($sources) + 1),
        'url' => $new_url,
        'type' => $new_type,
        'quality' => 'Auto',
        'language' => 'English',
        'priority' => count($sources),
        'isActive' => true,
        'isVisible' => true
    ];
    
    $sources[] = $new_source;
    $sources_json = json_encode($sources);
    
    $update = $conn->prepare("UPDATE live_tv_channels SET sources = ? WHERE id = ?");
    $update->bind_param("si", $sources_json, $channel_id);
    return $update->execute();
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/tv-logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stats = [
            'total' => 0,
            'added' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_messages' => []
        ];
        
        try {
            $data = [];
            
            // Handle CSV files
            if ($file_ext === 'csv') {
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle !== false) {
                    // Read header row
                    $headers = fgetcsv($handle);
                    if ($headers === false) {
                        throw new Exception("Could not read CSV headers");
                    }
                    
                    // Normalize headers (lowercase, trim)
                    $headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $headers);
                    
                    // Read data rows
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) !== count($headers)) continue;
                        $data[] = array_combine($headers, $row);
                    }
                    fclose($handle);
                }
            }
            // Handle Excel files
            elseif (in_array($file_ext, ['xlsx', 'xls'])) {
                // Try PhpSpreadsheet first if available
                if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file['tmp_name']);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();
                    
                    if (count($rows) < 2) {
                        throw new Exception("Excel file must have at least a header row and one data row");
                    }
                    
                    // First row is headers
                    $headers = array_map(function($h) {
                        return strtolower(trim($h));
                    }, $rows[0]);
                    
                    // Rest are data rows
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        if (count($row) !== count($headers)) continue;
                        $data[] = array_combine($headers, $row);
                    }
                } elseif ($file_ext === 'xlsx' && class_exists('ZipArchive')) {
                    // Simple XLSX parser using ZipArchive (for .xlsx files only)
                    $zip = new ZipArchive();
                    if ($zip->open($file['tmp_name']) === TRUE) {
                        // Read shared strings
                        $sharedStrings = [];
                        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
                        if ($sharedStringsXml) {
                            preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $sharedStringsXml, $matches);
                            $sharedStrings = $matches[1];
                        }
                        
                        // Read worksheet
                        $worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
                        if (!$worksheetXml) {
                            // Try to find first sheet
                            $sheetFiles = [];
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $filename = $zip->getNameIndex($i);
                                if (preg_match('/xl\/worksheets\/sheet(\d+)\.xml$/', $filename)) {
                                    $worksheetXml = $zip->getFromName($filename);
                                    break;
                                }
                            }
                        }
                        
                        if ($worksheetXml) {
                            // Parse rows
                            preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $worksheetXml, $rowMatches);
                            $rows = [];
                            
                            foreach ($rowMatches[1] as $rowXml) {
                                preg_match_all('/<c[^>]*r="([^"]*)"[^>]*t="([^"]*)"[^>]*>.*?<v>(.*?)<\/v>/s', $rowXml, $cellMatches, PREG_SET_ORDER);
                                
                                $row = [];
                                foreach ($cellMatches as $cell) {
                                    $cellRef = $cell[1]; // e.g., A1, B1
                                    $cellType = $cell[2]; // s = shared string, n = number, etc.
                                    $cellValue = $cell[3];
                                    
                                    // Extract column letter (A, B, C, etc.)
                                    preg_match('/^([A-Z]+)/', $cellRef, $colMatch);
                                    $colIndex = 0;
                                    if (isset($colMatch[1])) {
                                        $colLetters = $colMatch[1];
                                        for ($i = 0; $i < strlen($colLetters); $i++) {
                                            $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - ord('A') + 1);
                                        }
                                        $colIndex--; // Make 0-based
                                    }
                                    
                                    // Get actual value
                                    if ($cellType === 's' && isset($sharedStrings[intval($cellValue)])) {
                                        $row[$colIndex] = $sharedStrings[intval($cellValue)];
                                    } else {
                                        $row[$colIndex] = $cellValue;
                                    }
                                }
                                
                                if (!empty($row)) {
                                    // Fill gaps in row array
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
                            
                            // First row is headers
                            $headers = array_map(function($h) {
                                return strtolower(trim($h));
                            }, $rows[0]);
                            
                            // Rest are data rows
                            for ($i = 1; $i < count($rows); $i++) {
                                $row = $rows[$i];
                                // Pad row to match header count
                                while (count($row) < count($headers)) {
                                    $row[] = '';
                                }
                                $row = array_slice($row, 0, count($headers));
                                $data[] = array_combine($headers, $row);
                            }
                        } else {
                            throw new Exception("Could not read worksheet from Excel file. Please try saving as CSV instead.");
                        }
                        
                        $zip->close();
                    } else {
                        throw new Exception("Could not open Excel file. Please try saving as CSV instead.");
                    }
                } elseif ($file_ext === 'xls') {
                    // Old Excel format (.xls) requires PhpSpreadsheet or conversion
                    throw new Exception("Old Excel format (.xls) is not supported without PhpSpreadsheet library. Please either: 1) Install PhpSpreadsheet via Composer: 'composer require phpoffice/phpspreadsheet', 2) Convert your file to .xlsx format, or 3) Save as CSV (recommended - works without any dependencies).");
                } else {
                    throw new Exception("Excel file support requires either PhpSpreadsheet library or PHP ZipArchive extension (for .xlsx files). Please install one of these, or save your file as CSV and upload it. CSV files work without any additional requirements.");
                }
            } else {
                throw new Exception("Unsupported file format. Please upload CSV, XLSX, or XLS files.");
            }
            
            if (empty($data)) {
                throw new Exception("No data found in file. Please check the file format.");
            }
            
            // Process each row
            foreach ($data as $row) {
                $stats['total']++;
                
                try {
                    // Extract data (case-insensitive column matching)
                    $channel_name = normalizeDisplayText(trim($row['channel name'] ?? $row['channel_name'] ?? $row['name'] ?? ''));
                    $country = trim($row['country'] ?? 'US');
                    $category = trim($row['category'] ?? '');
                    $description = trim($row['description'] ?? '');
                    $logo_url = trim($row['logo'] ?? '');
                    $stream_url = trim($row['url'] ?? $row['stream url'] ?? $row['stream_url'] ?? '');
                    $status = strtolower(trim($row['status'] ?? 'active'));
                    
                    // Validate required fields
                    if (empty($channel_name) || empty($stream_url)) {
                        $stats['skipped']++;
                        $stats['error_messages'][] = "Row {$stats['total']}: Missing channel name or stream URL";
                        continue;
                    }
                    
                    $is_active = ($status === 'active' || $status === '1' || $status === 'yes' || $status === 'true');
                    
                    // Download logo
                    $logo_path = null;
                    if (!empty($logo_url)) {
                        $logo_path = downloadImageFromUrl($logo_url, $upload_dir);
                        if ($logo_path) {
                            $logo_path = normalizeUploadPath($logo_path);
                        }
                    }
                    
                    // Detect stream type
                    $stream_type = detectStreamType($stream_url);
                    
                    // Check if channel exists
                    $check = $conn->prepare("SELECT id, sources FROM live_tv_channels WHERE name = ?");
                    $check->bind_param("s", $channel_name);
                    $check->execute();
                    $result = $check->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Channel exists - check if source URL is different
                        $existing = $result->fetch_assoc();
                        $existing_sources = $existing['sources'] ?? '[]';
                        
                        if (sourceExists($existing_sources, $stream_url)) {
                            // Same URL exists - skip
                            $stats['skipped']++;
                            continue;
                        } else {
                            // Different URL or protocol - add as new source
                            if (addSourceToChannel($conn, $existing['id'], $stream_url, $stream_type)) {
                                $stats['updated']++;
                            } else {
                                $stats['errors']++;
                                $stats['error_messages'][] = "Row {$stats['total']}: Failed to add source to existing channel: $channel_name";
                            }
                            continue;
                        }
                    } else {
                        // New channel - create it
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
                        $sources_json = json_encode($sources);
                        
                        // Generate slug
                        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $channel_name)));
                        $slug = preg_replace('/-+/', '-', $slug);
                        $slug = trim($slug, '-');
                        
                        // Check if slug exists
                        $slug_check = $conn->prepare("SELECT id FROM live_tv_channels WHERE slug = ?");
                        $slug_check->bind_param("s", $slug);
                        $slug_check->execute();
                        if ($slug_check->get_result()->num_rows > 0) {
                            $slug = $slug . '-' . time();
                        }
                        
                        $insert = $conn->prepare("INSERT INTO live_tv_channels (name, description, logo, category, country, language, is_active, is_free, is_premium, slug, sources) VALUES (?, ?, ?, ?, ?, 'en', ?, 1, 0, ?, ?)");
                        $insert->bind_param("sssssiss", $channel_name, $description, $logo_path, $category, $country, $is_active, $slug, $sources_json);
                        
                        if ($insert->execute()) {
                            $stats['added']++;
                        } else {
                            $stats['errors']++;
                            $stats['error_messages'][] = "Row {$stats['total']}: Failed to add channel: $channel_name - " . $insert->error;
                        }
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    $stats['error_messages'][] = "Row {$stats['total']}: " . $e->getMessage();
                }
            }
            
            // Build success message
            $message = "Import completed! ";
            $message .= "Total: {$stats['total']}, ";
            $message .= "Added: {$stats['added']}, ";
            $message .= "Updated: {$stats['updated']}, ";
            $message .= "Skipped: {$stats['skipped']}";
            if ($stats['errors'] > 0) {
                $message .= ", Errors: {$stats['errors']}";
            }
            
            if (!empty($stats['error_messages']) && count($stats['error_messages']) <= 10) {
                $message .= "<br><br>Errors:<br>" . implode("<br>", array_slice($stats['error_messages'], 0, 10));
            } elseif (!empty($stats['error_messages'])) {
                $message .= "<br><br>First 10 Errors:<br>" . implode("<br>", array_slice($stats['error_messages'], 0, 10));
            }
            
            $message_type = ($stats['errors'] > 0) ? 'warning' : 'success';
            
        } catch (Exception $e) {
            $message = "Error processing file: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "File upload error: " . $file['error'];
        $message_type = 'error';
    }
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">IPTV Channels Import</h1>
    <p class="text-gray-400">Import IPTV channels from Excel (.xlsx, .xls) or CSV files</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>-700 text-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>-200 px-4 py-3 rounded mb-4">
    <?php echo $message; ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4">Import Excel/CSV File</h2>
    <form id="excelUploadForm" method="POST" enctype="multipart/form-data">
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Excel/CSV File *</label>
            <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            <p class="text-xs text-gray-400 mt-1">
                Supported formats: .xlsx, .xls, .csv 
                <span class="text-green-400">(CSV is recommended - works without any dependencies)</span>
            </p>
        </div>
        
        <!-- Progress Bar -->
        <div id="uploadProgress" class="hidden mb-4">
            <div class="flex justify-between items-center mb-2">
                <span class="text-sm text-gray-300">Processing channels...</span>
                <span id="uploadProgressText" class="text-sm text-gray-400">0%</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2.5">
                <div id="uploadProgressBar" class="bg-netflix-red h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <div id="uploadStats" class="mt-2 text-sm text-gray-400">
                <span id="uploadAdded">Added: 0</span> | 
                <span id="uploadUpdated">Updated: 0</span> | 
                <span id="uploadSkipped">Skipped: 0</span> | 
                <span id="uploadRemaining">Remaining: 0</span>
                <span id="uploadErrors" class="text-red-400 ml-2">Errors: 0</span>
            </div>
            <div id="uploadStatus" class="mt-2 text-sm text-gray-300 font-semibold"></div>
            <div class="mt-3 flex gap-2">
                <button id="pauseImportBtn" class="hidden bg-yellow-600 hover:bg-yellow-700 px-4 py-2 rounded text-sm">
                    <i class="fas fa-pause mr-2"></i>Pause
                </button>
                <button id="resumeImportBtn" class="hidden bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm">
                    <i class="fas fa-play mr-2"></i>Resume
                </button>
                <button id="stopImportBtn" class="hidden bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">
                    <i class="fas fa-stop mr-2"></i>Stop (Force)
                </button>
            </div>
        </div>
        
        <button type="submit" id="uploadBtn" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <i class="fas fa-upload mr-2"></i>Upload & Import Channels
        </button>
    </form>
</div>

<!-- Stream Link Checker -->
<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4">Check & Remove Dead Stream Links</h2>
    <p class="text-gray-400 mb-4">Scan all live TV channels and remove dead/inaccessible stream links. Channels will be kept, only dead links will be removed.</p>

    <!-- Source type filters -->
    <div class="mb-4 flex flex-wrap items-center gap-4">
        <div>
            <span class="block text-sm font-semibold mb-1 text-gray-300">Source types to scan:</span>
            <label class="inline-flex items-center mr-4 text-sm text-gray-300">
                <input type="checkbox" id="checkTypeHls" class="mr-2" checked>
                <span>M3U8 / HLS</span>
            </label>
            <label class="inline-flex items-center text-sm text-gray-300">
                <input type="checkbox" id="checkTypeDash" class="mr-2" checked>
                <span>DASH (.mpd)</span>
            </label>
        </div>
    </div>
    
    <div class="mb-4">
        <button id="startCheckBtn" class="bg-blue-600 px-6 py-2 rounded hover:bg-blue-700 mr-2">
            <i class="fas fa-play mr-2"></i>Start Scan
        </button>
        <button id="pauseCheckBtn" class="bg-yellow-600 px-6 py-2 rounded hover:bg-yellow-700 mr-2 hidden">
            <i class="fas fa-pause mr-2"></i>Pause
        </button>
        <button id="resumeCheckBtn" class="bg-green-600 px-6 py-2 rounded hover:bg-green-700 mr-2 hidden">
            <i class="fas fa-play mr-2"></i>Resume
        </button>
        <button id="stopCheckBtn" class="bg-red-600 px-6 py-2 rounded hover:bg-red-700 hidden">
            <i class="fas fa-stop mr-2"></i>Stop (Force)
        </button>
    </div>
    
    <!-- Progress Bar -->
    <div id="checkProgress" class="hidden mb-4">
        <div class="flex justify-between items-center mb-2">
            <span class="text-sm text-gray-300">Scanning stream links...</span>
            <span id="checkProgressText" class="text-sm text-gray-400">0%</span>
        </div>
        <div class="w-full bg-gray-700 rounded-full h-2.5">
            <div id="checkProgressBar" class="bg-blue-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <div id="checkStats" class="mt-2 text-sm text-gray-400">
            <span id="checkChecked">Checked: 0</span> | 
            <span id="checkTotal">Total: 0</span> | 
            <span id="checkRemaining">Remaining: 0</span> | 
            <span id="checkAlive" class="text-green-400">Alive: 0</span> | 
            <span id="checkDead" class="text-red-400">Dead: 0</span>
        </div>
        <div id="checkStatus" class="mt-2 text-sm text-gray-300"></div>
    </div>
    
    <!-- Results -->
    <div id="checkResults" class="hidden mt-4">
        <h3 class="text-lg font-semibold mb-2">Recent Dead Links Removed:</h3>
        <div id="deadLinksList" class="bg-gray-800 rounded p-4 max-h-64 overflow-y-auto">
            <!-- Dead links will be listed here -->
        </div>
    </div>
</div>

<div class="bg-blue-900 bg-opacity-30 border border-blue-700 rounded-lg p-4 mb-8">
    <h4 class="font-semibold text-blue-200 mb-2">ℹ️ Excel/CSV File Format</h4>
    <p class="text-sm text-gray-300 mb-3">Your file must have the following columns (column names are case-insensitive):</p>
    <div class="bg-gray-800 rounded p-3 mb-3">
        <table class="text-sm text-gray-300 w-full">
            <thead>
                <tr class="border-b border-gray-700">
                    <th class="text-left py-2 px-3">Column Name</th>
                    <th class="text-left py-2 px-3">Required</th>
                    <th class="text-left py-2 px-3">Description</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-gray-700">
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">Channel Name</code> or <code class="bg-gray-900 px-2 py-1 rounded">Name</code></td>
                    <td class="py-2 px-3"><span class="text-red-400">Yes</span></td>
                    <td class="py-2 px-3">Channel name</td>
                </tr>
                <tr class="border-b border-gray-700">
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">URL</code> or <code class="bg-gray-900 px-2 py-1 rounded">Stream URL</code></td>
                    <td class="py-2 px-3"><span class="text-red-400">Yes</span></td>
                    <td class="py-2 px-3">Stream URL (m3u8, mp4, etc.)</td>
                </tr>
                <tr class="border-b border-gray-700">
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">Logo</code></td>
                    <td class="py-2 px-3"><span class="text-red-400">Yes (new channels)</span></td>
                    <td class="py-2 px-3">Logo URL — required for <strong>new</strong> channels; must download successfully or the row is skipped. Existing channels with a logo only get new stream URLs merged when different.</td>
                </tr>
                <tr class="border-b border-gray-700">
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">Country</code></td>
                    <td class="py-2 px-3"><span class="text-green-400">No</span></td>
                    <td class="py-2 px-3">Country code (default: US)</td>
                </tr>
                <tr class="border-b border-gray-700">
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">Category</code></td>
                    <td class="py-2 px-3"><span class="text-green-400">No</span></td>
                    <td class="py-2 px-3">Channel category</td>
                </tr>
                <tr class="border-b border-gray-700">
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">Description</code></td>
                    <td class="py-2 px-3"><span class="text-green-400">No</span></td>
                    <td class="py-2 px-3">Channel description</td>
                </tr>
                <tr>
                    <td class="py-2 px-3"><code class="bg-gray-900 px-2 py-1 rounded">Status</code></td>
                    <td class="py-2 px-3"><span class="text-green-400">No</span></td>
                    <td class="py-2 px-3">Active/Inactive (default: Active)</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-green-900 bg-opacity-30 border border-green-700 rounded-lg p-4">
    <h4 class="font-semibold text-green-200 mb-2">✓ Import Features</h4>
    <ul class="text-sm text-gray-300 space-y-1 list-disc list-inside">
        <li>New channels: <strong>Logo</strong> column required; logo must download successfully or the channel is not created</li>
        <li>Existing channel that already has a logo: merges new stream URL only if it differs from current sources</li>
        <li>Existing channel with no logo: optional logo URL in file will be downloaded and saved; then new stream URL is merged if different</li>
        <li>Automatically downloads logos from URLs and saves them locally</li>
        <li>Detects stream protocol type (HLS, DASH, YouTube, etc.)</li>
        <li>If stream URL is same as an existing source, skips the row</li>
        <li>Supports multiple sources per channel</li>
        <li>Generates unique slugs automatically</li>
    </ul>
</div>

<script>
// Excel Upload with Batch Processing and Pause/Resume
let processingInterval = null;
let isProcessing = false;

document.getElementById('excelUploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData();
    const fileInput = document.getElementById('excel_file');
    const uploadBtn = document.getElementById('uploadBtn');
    const progressDiv = document.getElementById('uploadProgress');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const uploadAdded = document.getElementById('uploadAdded');
    const uploadUpdated = document.getElementById('uploadUpdated');
    const uploadSkipped = document.getElementById('uploadSkipped');
    const uploadRemaining = document.getElementById('uploadRemaining');
    const uploadErrors = document.getElementById('uploadErrors');
    const uploadStatus = document.getElementById('uploadStatus');
    const pauseBtn = document.getElementById('pauseImportBtn');
    const resumeBtn = document.getElementById('resumeImportBtn');
    const stopBtn = document.getElementById('stopImportBtn');
    
    if (!fileInput.files[0]) {
        alert('Please select a file');
        return;
    }
    
    formData.append('excel_file', fileInput.files[0]);
    formData.append('action', 'upload');
    
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Uploading...';
    progressDiv.classList.remove('hidden');
    uploadStatus.textContent = 'Uploading file...';
    
    // Upload file first
    fetch('<?php echo apiUrl('admin/api/iptv-import.php'); ?>?action=upload', {
        method: 'POST',
        body: formData
    })
    .then(async response => {
        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('JSON parse error. Response:', text.substring(0, 500));
            throw new Error('Invalid JSON response: ' + err.message);
        }
        
        if (!data || !data.success) {
            throw new Error(data.error || 'Upload failed');
        }
        
        // File uploaded, start processing
        uploadBtn.innerHTML = '<i class="fas fa-cog fa-spin mr-2"></i>Processing...';
        pauseBtn.classList.remove('hidden');
        stopBtn.classList.remove('hidden');
        isProcessing = true;
        
        // Start batch processing
        startBatchProcessing();
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error uploading file: ' + error.message + '\n\nCheck browser console (F12) for details.');
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload & Import Channels';
        progressDiv.classList.add('hidden');
    });
});

function startBatchProcessing() {
    if (processingInterval) {
        clearInterval(processingInterval);
    }
    
    processingInterval = setInterval(() => {
        if (!isProcessing) return;
        
        fetch('<?php echo apiUrl('admin/api/iptv-import.php'); ?>?action=process', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(async response => {
            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (err) {
                console.error('JSON parse error:', text.substring(0, 500));
                return;
            }
            
            if (data.success && data.progress) {
                updateProgress(data.progress);
                
                if (data.completed || data.stopped) {
                    // Import completed or stopped
                    if (processingInterval) {
                        clearInterval(processingInterval);
                        processingInterval = null;
                    }
                    isProcessing = false;
                    
                    const uploadBtn = document.getElementById('uploadBtn');
                    const pauseBtn = document.getElementById('pauseImportBtn');
                    const resumeBtn = document.getElementById('resumeImportBtn');
                    const stopBtn = document.getElementById('stopImportBtn');
                    const uploadStatus = document.getElementById('uploadStatus');
                    
                    pauseBtn.classList.add('hidden');
                    resumeBtn.classList.add('hidden');
                    stopBtn.classList.add('hidden');
                    
                    if (data.stopped) {
                        uploadStatus.textContent = 'Import stopped by user';
                        uploadStatus.className = 'mt-2 text-sm text-yellow-400 font-semibold';
                    } else {
                        uploadStatus.textContent = 'Import completed!';
                        uploadStatus.className = 'mt-2 text-sm text-green-400 font-semibold';
                        
                        setTimeout(() => {
                            alert('Import completed!\n\n' +
                                  'Total: ' + (data.progress.total || 0) + '\n' +
                                  'Added: ' + (data.progress.added || 0) + '\n' +
                                  'Updated: ' + (data.progress.updated || 0) + '\n' +
                                  'Skipped: ' + (data.progress.skipped || 0) + '\n' +
                                  ((data.progress.errors || 0) > 0 ? 'Errors: ' + data.progress.errors : ''));
                            location.reload();
                        }, 500);
                    }
                } else if (data.paused) {
                    // Import paused
                    isProcessing = false;
                    const pauseBtn = document.getElementById('pauseImportBtn');
                    const resumeBtn = document.getElementById('resumeImportBtn');
                    pauseBtn.classList.add('hidden');
                    resumeBtn.classList.remove('hidden');
                }
            }
        })
        .catch(error => {
            console.error('Processing error:', error);
        });
    }, 2000); // Check progress every 2 seconds (batches process 5 channels with 5-second gap)
}

function updateProgress(prog) {
    const progressBar = document.getElementById('uploadProgressBar');
    const progressText = document.getElementById('uploadProgressText');
    const uploadAdded = document.getElementById('uploadAdded');
    const uploadUpdated = document.getElementById('uploadUpdated');
    const uploadSkipped = document.getElementById('uploadSkipped');
    const uploadRemaining = document.getElementById('uploadRemaining');
    const uploadErrors = document.getElementById('uploadErrors');
    const uploadStatus = document.getElementById('uploadStatus');
    
    const percentage = prog.total > 0 ? Math.round((prog.processed / prog.total) * 100) : 0;
    
    progressBar.style.width = percentage + '%';
    progressText.textContent = percentage + '%';
    
    uploadAdded.textContent = 'Added: ' + (prog.added || 0);
    uploadUpdated.textContent = 'Updated: ' + (prog.updated || 0);
    uploadSkipped.textContent = 'Skipped: ' + (prog.skipped || 0);
    uploadRemaining.textContent = 'Remaining: ' + Math.max(0, (prog.total || 0) - (prog.processed || 0));
    uploadErrors.textContent = 'Errors: ' + (prog.errors || 0);
    
    if (prog.current_channel) {
        uploadStatus.textContent = 'Processing: ' + prog.current_channel;
    }
}

// Pause button
document.getElementById('pauseImportBtn').addEventListener('click', function() {
    fetch('<?php echo apiUrl('admin/api/iptv-import.php'); ?>?action=pause', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            isProcessing = false;
            this.classList.add('hidden');
            document.getElementById('resumeImportBtn').classList.remove('hidden');
        }
    });
});

// Resume button
document.getElementById('resumeImportBtn').addEventListener('click', function() {
    fetch('<?php echo apiUrl('admin/api/iptv-import.php'); ?>?action=resume', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            isProcessing = true;
            this.classList.add('hidden');
            document.getElementById('pauseImportBtn').classList.remove('hidden');
            startBatchProcessing();
        }
    });
});

// Stop button
document.getElementById('stopImportBtn').addEventListener('click', function() {
    if (confirm('Are you sure you want to stop the import? Processing will stop after the current batch.')) {
        // Immediately stop processing on frontend
        if (processingInterval) {
            clearInterval(processingInterval);
            processingInterval = null;
        }
        isProcessing = false;
        
        const uploadBtn = document.getElementById('uploadBtn');
        const progressDiv = document.getElementById('uploadProgress');
        const pauseBtn = document.getElementById('pauseImportBtn');
        const resumeBtn = document.getElementById('resumeImportBtn');
        const stopBtn = document.getElementById('stopImportBtn');
        const uploadStatus = document.getElementById('uploadStatus');
        
        uploadStatus.textContent = 'Stopping import...';
        uploadStatus.className = 'mt-2 text-sm text-yellow-400 font-semibold';
        
        // Send stop signal to backend
        fetch('<?php echo apiUrl('admin/api/iptv-import.php'); ?>?action=stop', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="fas fa-upload mr-2"></i>Upload & Import Channels';
                pauseBtn.classList.add('hidden');
                resumeBtn.classList.add('hidden');
                stopBtn.classList.add('hidden');
                uploadStatus.textContent = 'Import stopped by user';
            }
        })
        .catch(error => {
            console.error('Error stopping import:', error);
            uploadStatus.textContent = 'Import stopped (may take a moment to fully stop)';
        });
    }
});

// Stream Link Checker
let checkInterval = null;
let isChecking = false;

document.getElementById('startCheckBtn').addEventListener('click', function() {
    startStreamCheck();
});

document.getElementById('pauseCheckBtn').addEventListener('click', function() {
    pauseStreamCheck();
});

document.getElementById('resumeCheckBtn').addEventListener('click', function() {
    resumeStreamCheck();
});

document.getElementById('stopCheckBtn').addEventListener('click', function() {
    stopStreamCheck();
});

function startStreamCheck() {
    const types = [];
    const hlsCheckbox = document.getElementById('checkTypeHls');
    const dashCheckbox = document.getElementById('checkTypeDash');

    if (hlsCheckbox && hlsCheckbox.checked) types.push('hls');
    if (dashCheckbox && dashCheckbox.checked) types.push('dash');

    if (types.length === 0) {
        alert('Please select at least one source type to scan (M3U8/HLS or DASH).');
        return;
    }

    const params = new URLSearchParams();
    params.set('action', 'start');
    params.set('types', types.join(','));

    fetch('<?php echo apiUrl('admin/api/check-stream-links.php'); ?>?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('startCheckBtn').classList.add('hidden');
                document.getElementById('pauseCheckBtn').classList.remove('hidden');
                document.getElementById('stopCheckBtn').classList.remove('hidden');
                document.getElementById('checkProgress').classList.remove('hidden');
                document.getElementById('checkResults').classList.remove('hidden');
                
                isChecking = true;
                checkStreamLinks();
            } else {
                alert('Error: ' + (data.message || 'Failed to start scan'));
            }
        });
}

function checkStreamLinks() {
    if (!isChecking) return;
    
    // Check 20 stream links at a time when removing dead streams
    fetch('<?php echo apiUrl('admin/api/check-stream-links.php'); ?>?action=check&batch=20')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCheckProgress(data);
                
                if (data.completed) {
                    // Scan completed
                    isChecking = false;
                    document.getElementById('pauseCheckBtn').classList.add('hidden');
                    document.getElementById('resumeCheckBtn').classList.add('hidden');
                    document.getElementById('stopCheckBtn').classList.add('hidden');
                    document.getElementById('startCheckBtn').classList.remove('hidden');
                    document.getElementById('checkStatus').textContent = 'Scan completed!';
                } else {
                    // Continue checking
                    setTimeout(() => checkStreamLinks(), 500);
                }
            } else if (data.paused) {
                isChecking = false;
                document.getElementById('checkStatus').textContent = 'Scan paused';
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
                isChecking = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            isChecking = false;
        });
}

function updateCheckProgress(data) {
    const progressBar = document.getElementById('checkProgressBar');
    const progressText = document.getElementById('checkProgressText');
    const checkChecked = document.getElementById('checkChecked');
    const checkTotal = document.getElementById('checkTotal');
    const checkRemaining = document.getElementById('checkRemaining');
    const checkAlive = document.getElementById('checkAlive');
    const checkDead = document.getElementById('checkDead');
    const checkStatus = document.getElementById('checkStatus');
    const deadLinksList = document.getElementById('deadLinksList');
    
    progressBar.style.width = data.progress + '%';
    progressText.textContent = data.progress + '%';
    checkChecked.textContent = 'Checked: ' + data.checked;
    checkTotal.textContent = 'Total: ' + data.total;
    checkRemaining.textContent = 'Remaining: ' + data.remaining;
    checkAlive.textContent = 'Alive: ' + data.alive;
    checkDead.textContent = 'Dead: ' + data.dead;
    checkStatus.textContent = 'Checking... ' + data.remaining + ' links remaining';
    
    // Add dead links to list
    if (data.dead_links && data.dead_links.length > 0) {
        data.dead_links.forEach(link => {
            const linkDiv = document.createElement('div');
            linkDiv.className = 'text-sm text-red-400 mb-1';
            linkDiv.innerHTML = '<i class="fas fa-times-circle mr-2"></i>' + 
                               link.channel_name + ': ' + 
                               '<span class="text-gray-400">' + link.url.substring(0, 60) + '...</span> ' +
                               '<span class="text-red-300">(' + link.error + ')</span>';
            deadLinksList.insertBefore(linkDiv, deadLinksList.firstChild);
        });
    }
}

function pauseStreamCheck() {
    fetch('<?php echo apiUrl('admin/api/check-stream-links.php'); ?>?action=pause')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isChecking = false;
                document.getElementById('pauseCheckBtn').classList.add('hidden');
                document.getElementById('resumeCheckBtn').classList.remove('hidden');
            }
        });
}

function resumeStreamCheck() {
    fetch('<?php echo apiUrl('admin/api/check-stream-links.php'); ?>?action=resume')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isChecking = true;
                document.getElementById('pauseCheckBtn').classList.remove('hidden');
                document.getElementById('resumeCheckBtn').classList.add('hidden');
                checkStreamLinks();
            }
        });
}

// Check if there's an ongoing import or scan on page load
window.addEventListener('load', function() {
    // Check for ongoing IPTV import
    fetch('<?php echo apiUrl('admin/api/iptv-import.php'); ?>?action=progress')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.progress && data.progress.status === 'processing') {
                // Import is in progress, restore UI
                const progressDiv = document.getElementById('uploadProgress');
                const pauseBtn = document.getElementById('pauseImportBtn');
                const stopBtn = document.getElementById('stopImportBtn');
                const uploadBtn = document.getElementById('uploadBtn');
                
                progressDiv.classList.remove('hidden');
                pauseBtn.classList.remove('hidden');
                stopBtn.classList.remove('hidden');
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-cog fa-spin mr-2"></i>Processing...';
                
                isProcessing = true;
                updateProgress(data.progress);
                startBatchProcessing();
            }
        })
        .catch(error => console.error('Error checking import status:', error));
    
    // Check for ongoing stream link scan
    fetch('<?php echo apiUrl('admin/api/check-stream-links.php'); ?>?action=status')
        .then(response => response.json())
        .then(data => {
            if (data.success && !data.completed) {
                document.getElementById('checkProgress').classList.remove('hidden');
                document.getElementById('checkResults').classList.remove('hidden');
                updateCheckProgress(data);
                
                if (data.paused) {
                    document.getElementById('resumeCheckBtn').classList.remove('hidden');
                } else {
                    document.getElementById('pauseCheckBtn').classList.remove('hidden');
                    document.getElementById('stopCheckBtn').classList.remove('hidden');
                    isChecking = true;
                    checkStreamLinks();
                }
            }
        });
});

function stopStreamCheck() {
    if (!confirm('Are you sure you want to stop the scan? This will immediately stop and clear the current scan progress.')) {
        return;
    }

    isChecking = false;

    fetch('<?php echo apiUrl('admin/api/check-stream-links.php'); ?>?action=stop', {
        method: 'POST'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('pauseCheckBtn').classList.add('hidden');
                document.getElementById('resumeCheckBtn').classList.add('hidden');
                document.getElementById('stopCheckBtn').classList.add('hidden');
                document.getElementById('startCheckBtn').classList.remove('hidden');
                document.getElementById('checkStatus').textContent = 'Scan stopped by user';
            } else {
                alert('Error: ' + (data.message || 'Failed to stop scan'));
            }
        })
        .catch(error => {
            console.error('Error stopping scan:', error);
        });
}
</script>
