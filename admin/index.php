<?php
/**
 * Admin Panel - Main Dashboard with Tab Navigation
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireAdminLogin();

$conn = getDBConnection();
$tab = $_GET['tab'] ?? 'dashboard';
$validTabs = [
    'dashboard', 'movies', 'tv-shows', 'live-tv', 'edit-channel', 'categories',
    'users', 'reports', 'user-messages', 'coupons', 'ads', 'sliders', 'settings',
    'import', 'iptv', 'links', 'bulk-fetch', 'episodes', 'countdown',
    'tools', 'match-replace', 'import-sql',
    'delete-m3u8-channels', 'delete-no-source-channels', 'delete-dash-channels',
    'remove-bad-m3u8-sources', 'remove-bad-dash-sources', 'remove-http-stream-links',
    'search-check-streams', 'remove-no-logo-channels'
];

if (!in_array($tab, $validTabs)) {
    $tab = 'dashboard';
}

// Handle coupons actions BEFORE any output (to avoid header errors)
if ($tab === 'coupons') {
    // Handle delete action
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $conn->query("DELETE FROM coupons WHERE id = $id");
        header("Location: ?tab=coupons");
        exit;
    }
    
    // Handle POST (add/edit) action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
        $id = $_POST['id'] ?? null;
        $code = strtoupper(sanitize($_POST['code'] ?? ''));
        $description = sanitize($_POST['description'] ?? '');
        $duration_days = intval($_POST['duration_days'] ?? 30);
        $max_uses = intval($_POST['max_uses'] ?? 1);
        if ($max_uses < 0) $max_uses = 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        
        if ($id) {
            $stmt = $conn->prepare("UPDATE coupons SET code=?, description=?, duration_days=?, max_uses=?, is_active=?, expires_at=? WHERE id=?");
            $stmt->bind_param("ssiiisi", $code, $description, $duration_days, $max_uses, $is_active, $expires_at, $id);
            $stmt->execute();
            header("Location: ?tab=coupons&edit=" . $id . "&success=1");
        } else {
            $stmt = $conn->prepare("INSERT INTO coupons (code, description, duration_days, max_uses, is_active, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiiis", $code, $description, $duration_days, $max_uses, $is_active, $expires_at);
            $stmt->execute();
            header("Location: ?tab=coupons&success=1");
        }
        exit;
    }
}

// Handle countdown actions BEFORE any output (to avoid header errors)
if ($tab === 'countdown') {
    // Handle delete action
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        $conn->query("DELETE FROM countdowns WHERE id = $id");
        header("Location: ?tab=countdown&success=deleted");
        exit;
    }
    
    // Handle POST (add/edit) action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title'])) {
        $id = $_POST['id'] ?? null;
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $target_datetime = $_POST['target_datetime'] ?? '';
        $slug = sanitize($_POST['slug'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Generate slug from title if not provided
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        }
        
        // Ensure slug is unique
        if ($id) {
            $check_slug = $conn->prepare("SELECT id FROM countdowns WHERE slug = ? AND id != ?");
            $check_slug->bind_param("si", $slug, $id);
        } else {
            $check_slug = $conn->prepare("SELECT id FROM countdowns WHERE slug = ?");
            $check_slug->bind_param("s", $slug);
        }
        $check_slug->execute();
        if ($check_slug->get_result()->num_rows > 0) {
            $slug = $slug . '-' . time();
        }
        
        if ($id) {
            $stmt = $conn->prepare("UPDATE countdowns SET title=?, description=?, target_datetime=?, slug=?, is_active=? WHERE id=?");
            $stmt->bind_param("ssssii", $title, $description, $target_datetime, $slug, $is_active, $id);
            $stmt->execute();
            header("Location: ?tab=countdown&edit=" . $id . "&success=1");
        } else {
            $stmt = $conn->prepare("INSERT INTO countdowns (title, description, target_datetime, slug, is_active) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $title, $description, $target_datetime, $slug, $is_active);
            $stmt->execute();
            header("Location: ?tab=countdown&success=1");
        }
        exit;
    }
}

// Handle import-sql actions BEFORE any output (to avoid header errors)
// 1) Export current live_tv_channels as SQL
if ($tab === 'import-sql' && isset($_GET['export']) && $_GET['export'] === 'live_tv') {
    // Columns we will export, must match importer allowed_fields
    $columns = [
        'name',
        'description',
        'logo',
        'stream_url',
        'category',
        'country',
        'language',
        'featured',
        'is_active',
        'is_free',
        'is_premium',
        'show_in_slider',
        'slug',
        'sources',
    ];

    // Ensure UTF-8
    $conn->set_charset("utf8mb4");

    $result = $conn->query(
        "SELECT " . implode(',', array_map(function($c) { return '`' . $c . '`'; }, $columns)) .
        " FROM live_tv_channels ORDER BY id ASC"
    );

    if ($result && $result->num_rows > 0) {
        $filename = 'live_tv_channels_export_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo "-- StreamPanel live_tv_channels export\n";
        echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

        // Build INSERT statements in batches
        $batchSize = 200;
        $rows      = [];

        while ($row = $result->fetch_assoc()) {
            $values = [];
            foreach ($columns as $col) {
                $val = $row[$col];
                if ($val === null) {
                    $values[] = 'NULL';
                } elseif (in_array($col, ['featured', 'is_active', 'is_free', 'is_premium', 'show_in_slider'], true)) {
                    // Integer/boolean fields
                    $values[] = (string)(int)$val;
                } else {
                    $escaped = $conn->real_escape_string($val);
                    $values[] = "'" . $escaped . "'";
                }
            }
            $rows[] = '(' . implode(', ', $values) . ')';

            if (count($rows) >= $batchSize) {
                echo "INSERT INTO `live_tv_channels` (`" . implode('`, `', $columns) . "`) VALUES\n";
                echo implode(",\n", $rows) . ";\n\n";
                $rows = [];
            }
        }

        // Flush remaining rows
        if (!empty($rows)) {
            echo "INSERT INTO `live_tv_channels` (`" . implode('`, `', $columns) . "`) VALUES\n";
            echo implode(",\n", $rows) . ";\n\n";
        }

        exit;
    } else {
        // No channels to export; redirect back with an error message in query string
        header("Location: ?tab=import-sql&error=" . urlencode("No channels found in live_tv_channels to export."));
        exit;
    }
}

// 2) Handle SQL import upload
if ($tab === 'import-sql' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Ensure database connection uses UTF-8
        $conn->set_charset("utf8mb4");
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'sql') {
            header("Location: ?tab=import-sql&error=" . urlencode("Invalid file type. Please upload a .sql file."));
            exit;
        }
        
        $stats = [
            'total' => 0,
            'updated' => 0,
            'added' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_messages' => []
        ];
        
        try {
            // Read SQL file
            $sql_content = file_get_contents($file['tmp_name']);
            if ($sql_content === false || trim($sql_content) === '') {
                throw new Exception("Uploaded SQL file appears to be empty or unreadable. Please confirm the file is not empty and try again.");
            }
            // Keep an untouched copy for robust INSERT detection
            $raw_sql_content = $sql_content;
            
            // Normalize line endings and remove comments
            // Only treat lines that START with "--" (optionally after whitespace) as comments,
            // so that URLs containing "--" are not broken.
            $sql_content = preg_replace('/^\s*--.*$/m', '', $sql_content); // Remove SQL comments at line start
            $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content); // Remove block comments
            
            // Find all INSERT statements (handle single and multi-line).
            // Our own exporter generates statements like:
            //   INSERT INTO `live_tv_channels` (`col1`, `col2`, ...) VALUES
            //   (...),
            //   (...);
            // To make this robust, we scan manually for "INSERT INTO `live_tv_channels`"
            // and then extract the column list and VALUES block using string operations,
            // instead of relying on fragile regex.
            $insert_matches = [];
            $marker = 'INSERT INTO `live_tv_channels`';

            // First try on the original (raw) content to avoid any side effects
            $content_for_scan = $raw_sql_content;
            $len = strlen($content_for_scan);
            $offset = 0;
            
            while (($pos = strpos($content_for_scan, $marker, $offset)) !== false) {
                $posOpenCols = strpos($content_for_scan, '(', $pos);
                if ($posOpenCols === false) {
                    break;
                }
                $posCloseCols = strpos($content_for_scan, ')', $posOpenCols);
                if ($posCloseCols === false) {
                    break;
                }
                $columns_str = substr($content_for_scan, $posOpenCols + 1, $posCloseCols - $posOpenCols - 1);
                
                // Find VALUES keyword after column list
                $posValues = stripos($content_for_scan, 'VALUES', $posCloseCols);
                if ($posValues === false) {
                    break;
                }
                $posValues += strlen('VALUES');
                // Skip whitespace/newlines after VALUES
                while ($posValues < $len && ctype_space($content_for_scan[$posValues])) {
                    $posValues++;
                }
                
                // Find terminating semicolon for this INSERT
                $posSemi = strpos($content_for_scan, ';', $posValues);
                if ($posSemi === false) {
                    $posSemi = $len;
                }
                
                $values_str = substr($content_for_scan, $posValues, $posSemi - $posValues);
                $full_snippet = substr($content_for_scan, $pos, $posSemi - $pos + 1);
                
                // Shape this like preg_match_all result: [0]=full, [1]=columns, [2]=values
                $insert_matches[] = [
                    $full_snippet,
                    $columns_str,
                    $values_str
                ];
                
                $offset = $posSemi + 1;
            }
            
            if (empty($insert_matches)) {
                throw new Exception("No INSERT statements found in SQL file. Please ensure the file contains INSERT INTO live_tv_channels statements.");
            }
            
            // Extract column names from first INSERT (they should be the same for all)
            $first_insert = $insert_matches[0];
            $columns_str = trim($first_insert[1]);
            $columns = preg_split('/\s*,\s*/', $columns_str);
            $columns = array_map(function($col) {
                return trim(trim($col), '`\'"');
            }, $columns);
            
            // Function to parse SQL values
            function parseSqlValues($values_str) {
                $values = [];
                $current = '';
                $in_quotes = false;
                $quote_char = '';
                $len = strlen($values_str);
                
                for ($i = 0; $i < $len; $i++) {
                    $char = $values_str[$i];
                    $prev = ($i > 0) ? $values_str[$i - 1] : '';
                    $next = ($i < $len - 1) ? $values_str[$i + 1] : '';
                    
                    if (!$in_quotes && ($char === '"' || $char === "'")) {
                        $in_quotes = true;
                        $quote_char = $char;
                        $current .= $char;
                    } elseif ($in_quotes && $char === $quote_char && $prev !== '\\') {
                        $in_quotes = false;
                        $current .= $char;
                    } elseif (!$in_quotes && $char === ',' && ($i === 0 || $prev !== '\\')) {
                        $values[] = trim($current);
                        $current = '';
                    } else {
                        $current .= $char;
                    }
                }
                if (!empty(trim($current))) {
                    $values[] = trim($current);
                }
                
                // Clean values
                $cleaned = [];
                foreach ($values as $val) {
                    $val = trim($val);
                    if (strtoupper($val) === 'NULL') {
                        $cleaned[] = null;
                    } elseif ((substr($val, 0, 1) === '"' && substr($val, -1) === '"') || 
                              (substr($val, 0, 1) === "'" && substr($val, -1) === "'")) {
                        $val = substr($val, 1, -1);
                        $val = str_replace(['\\"', "\\'", '\\\\', '\\n', '\\r', '\\t'], ['"', "'", '\\', "\n", "\r", "\t"], $val);
                        $cleaned[] = $val;
                    } elseif (is_numeric($val)) {
                        $cleaned[] = $val;
                    } else {
                        $cleaned[] = $val;
                    }
                }
                return $cleaned;
            }
            
            // Process all INSERT statements
            foreach ($insert_matches as $match) {
                $values_str = $match[2];
                
                // Extract individual value sets: (val1, val2, ...), (val3, val4, ...)
                // Handle nested parentheses (e.g., JSON in sources field)
                $value_sets = [];
                $current_set = '';
                $paren_depth = 0;
                $in_quotes = false;
                $quote_char = '';
                
                $len = strlen($values_str);
                for ($i = 0; $i < $len; $i++) {
                    $char = $values_str[$i];
                    $prev = ($i > 0) ? $values_str[$i - 1] : '';
                    
                    if (!$in_quotes && ($char === '"' || $char === "'")) {
                        $in_quotes = true;
                        $quote_char = $char;
                        $current_set .= $char;
                    } elseif ($in_quotes && $char === $quote_char && $prev !== '\\') {
                        $in_quotes = false;
                        $current_set .= $char;
                    } elseif (!$in_quotes && $char === '(') {
                        $paren_depth++;
                        if ($paren_depth > 1) {
                            $current_set .= $char; // Include nested opening paren
                        }
                    } elseif (!$in_quotes && $char === ')') {
                        if ($paren_depth > 1) {
                            $current_set .= $char; // Include nested closing paren
                        } elseif ($paren_depth === 1) {
                            // End of value set
                            $value_sets[] = $current_set;
                            $current_set = '';
                        }
                        $paren_depth--;
                    } elseif ($paren_depth > 0) {
                        $current_set .= $char;
                    }
                }
                
                foreach ($value_sets as $value_set) {
                    $stats['total']++;
                    
                    $cleaned_values = parseSqlValues($value_set);
                    
                    // Create associative array
                    $channel_data = [];
                    foreach ($columns as $idx => $col) {
                        if (isset($cleaned_values[$idx])) {
                            $channel_data[$col] = $cleaned_values[$idx];
                        }
                    }
                    
                    // Skip if no name (required field)
                    if (empty($channel_data['name'])) {
                        $stats['skipped']++;
                        continue;
                    }
                    
                    $channel_name = $channel_data['name'];
                    
                    // Check if channel exists by name (exact match)
                    $check = $conn->prepare("SELECT id FROM live_tv_channels WHERE name = ?");
                    $check->bind_param("s", $channel_name);
                    $check->execute();
                    $result = $check->get_result();
                    
                    // Fields to update/insert (exclude auto-increment and system fields)
                    $allowed_fields = ['name', 'description', 'logo', 'stream_url', 'category', 'country', 
                                      'language', 'featured', 'is_active', 'is_free', 'is_premium', 
                                      'show_in_slider', 'slug', 'sources'];
                    
                    if ($result->num_rows > 0) {
                        // Update existing channel
                        $existing = $result->fetch_assoc();
                        $channel_id = $existing['id'];
                        
                        $update_fields = [];
                        $update_params = [];
                        $update_types = '';
                        
                        foreach ($allowed_fields as $field) {
                            if (isset($channel_data[$field])) {
                                $value = $channel_data[$field];
                                $update_fields[] = "`$field` = ?";
                                $update_params[] = $value;
                                // Determine type
                                if ($value === null) {
                                    $update_types .= 's'; // NULL as string
                                } elseif (in_array($field, ['featured', 'is_active', 'is_free', 'is_premium', 'show_in_slider'])) {
                                    $update_types .= 'i'; // Integer/boolean
                                } else {
                                    $update_types .= 's'; // String
                                }
                            }
                        }
                        
                        if (!empty($update_fields)) {
                            $update_sql = "UPDATE live_tv_channels SET " . implode(", ", $update_fields) . " WHERE id = ?";
                            $update_params[] = $channel_id;
                            $update_types .= 'i';
                            
                            $update_stmt = $conn->prepare($update_sql);
                            if ($update_stmt) {
                                $update_stmt->bind_param($update_types, ...$update_params);
                                if ($update_stmt->execute()) {
                                    $stats['updated']++;
                                } else {
                                    $stats['errors']++;
                                    $stats['error_messages'][] = "Failed to update channel: $channel_name - " . $update_stmt->error;
                                }
                            } else {
                                $stats['errors']++;
                                $stats['error_messages'][] = "Failed to prepare update for: $channel_name - " . $conn->error;
                            }
                        } else {
                            $stats['skipped']++;
                        }
                    } else {
                        // Insert new channel
                        $insert_fields = [];
                        $insert_values = [];
                        $insert_params = [];
                        $insert_types = '';
                        
                        foreach ($allowed_fields as $field) {
                            if (isset($channel_data[$field])) {
                                $value = $channel_data[$field];
                                $insert_fields[] = "`$field`";
                                $insert_values[] = "?";
                                $insert_params[] = $value;
                                // Determine type
                                if ($value === null) {
                                    $insert_types .= 's';
                                } elseif (in_array($field, ['featured', 'is_active', 'is_free', 'is_premium', 'show_in_slider'])) {
                                    $insert_types .= 'i';
                                } else {
                                    $insert_types .= 's';
                                }
                            }
                        }
                        
                        if (!empty($insert_fields)) {
                            $insert_sql = "INSERT INTO live_tv_channels (" . implode(", ", $insert_fields) . ") VALUES (" . implode(", ", $insert_values) . ")";
                            $insert_stmt = $conn->prepare($insert_sql);
                            if ($insert_stmt) {
                                $insert_stmt->bind_param($insert_types, ...$insert_params);
                                if ($insert_stmt->execute()) {
                                    $stats['added']++;
                                } else {
                                    $stats['errors']++;
                                    $stats['error_messages'][] = "Failed to add channel: $channel_name - " . $insert_stmt->error;
                                }
                            } else {
                                $stats['errors']++;
                                $stats['error_messages'][] = "Failed to prepare insert for: $channel_name - " . $conn->error;
                            }
                        } else {
                            $stats['skipped']++;
                        }
                    }
                }
            }
            
            // Redirect with results
            $result_params = http_build_query([
                'tab' => 'import-sql',
                'success' => 1,
                'total' => $stats['total'],
                'updated' => $stats['updated'],
                'added' => $stats['added'],
                'skipped' => $stats['skipped'],
                'errors' => $stats['errors']
            ]);
            header("Location: ?" . $result_params);
            exit;
        } catch (Exception $e) {
            header("Location: ?tab=import-sql&error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: ?tab=import-sql&error=" . urlencode("File upload error: " . $file['error']));
        exit;
    }
}

// Handle match-replace actions BEFORE any output (to avoid header errors)
if ($tab === 'match-replace' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        // Ensure database connection uses UTF-8
        $conn->set_charset("utf8mb4");
        
        // Ensure the country and category columns support UTF-8
        try {
            $conn->query("ALTER TABLE live_tv_channels MODIFY COLUMN country VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->query("ALTER TABLE live_tv_channels MODIFY COLUMN category VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (Exception $e) {
            // Columns might already be UTF-8 or error might occur, continue anyway
        }
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stats = [
            'total' => 0,
            'matched' => 0,
            'updated' => 0,
            'not_found' => 0,
            'errors' => 0,
            'error_messages' => []
        ];
        
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
                    
                    // Convert headers to UTF-8 (with fallback if mbstring not available)
                    $headers = array_map(function($h) {
                        if (function_exists('mb_convert_encoding')) {
                            // Try to detect and convert encoding
                            if (function_exists('mb_detect_encoding')) {
                                $encoding = mb_detect_encoding($h, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
                                if ($encoding && $encoding !== 'UTF-8') {
                                    $h = mb_convert_encoding($h, 'UTF-8', $encoding);
                                }
                            } else {
                                // Try auto-detection
                                $h = mb_convert_encoding($h, 'UTF-8', 'auto');
                            }
                        } else {
                            // Fallback: assume UTF-8 or try iconv if available
                            if (function_exists('iconv')) {
                                $h = @iconv('UTF-8', 'UTF-8//IGNORE', $h);
                            }
                        }
                        return strtolower(trim($h));
                    }, $headers);
                    
                    while (($row = fgetcsv($handle)) !== false) {
                        if (count($row) !== count($headers)) continue;
                        // Convert row data to UTF-8 (with fallback if mbstring not available)
                        $row = array_map(function($cell) {
                            if (function_exists('mb_convert_encoding')) {
                                if (function_exists('mb_detect_encoding')) {
                                    $encoding = mb_detect_encoding($cell, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
                                    if ($encoding && $encoding !== 'UTF-8') {
                                        $cell = mb_convert_encoding($cell, 'UTF-8', $encoding);
                                    }
                                } else {
                                    $cell = mb_convert_encoding($cell, 'UTF-8', 'auto');
                                }
                            } else {
                                // Fallback: try iconv if available
                                if (function_exists('iconv')) {
                                    $cell = @iconv('UTF-8', 'UTF-8//IGNORE', $cell);
                                }
                            }
                            return $cell;
                        }, $row);
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
                        // Ensure header is UTF-8 (with fallback)
                        $h = (string)$h;
                        if (function_exists('mb_convert_encoding')) {
                            $h = mb_convert_encoding($h, 'UTF-8', 'auto');
                        } elseif (function_exists('iconv')) {
                            $h = @iconv('UTF-8', 'UTF-8//IGNORE', $h);
                        }
                        return strtolower(trim($h));
                    }, $rows[0]);
                    
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        while (count($row) < count($headers)) {
                            $row[] = '';
                        }
                        $row = array_slice($row, 0, count($headers));
                        // Ensure all cell values are UTF-8 (with fallback)
                        $row = array_map(function($cell) {
                            $cell = (string)$cell;
                            if (function_exists('mb_convert_encoding')) {
                                $cell = mb_convert_encoding($cell, 'UTF-8', 'auto');
                            } elseif (function_exists('iconv')) {
                                $cell = @iconv('UTF-8', 'UTF-8//IGNORE', $cell);
                            }
                            return $cell;
                        }, $row);
                        $data[] = array_combine($headers, $row);
                    }
                } else {
                    throw new Exception("PhpSpreadsheet library is required for Excel files. Please install it or use CSV format.");
                }
            } else {
                throw new Exception("Unsupported file format. Please use .xlsx, .xls, or .csv");
            }
            
            // Process the data
            $stats['total'] = count($data);
            
            // Find column names (flexible matching)
            $name_col = null;
            $category_col = null;
            $country_col = null;
            
            foreach (['name', 'channel name', 'channel_name', 'channel', 'title'] as $col) {
                if (isset($data[0][$col])) {
                    $name_col = $col;
                    break;
                }
            }
            
            foreach (['category', 'cat', 'categories'] as $col) {
                if (isset($data[0][$col])) {
                    $category_col = $col;
                    break;
                }
            }
            
            foreach (['country', 'countries', 'nation'] as $col) {
                if (isset($data[0][$col])) {
                    $country_col = $col;
                    break;
                }
            }
            
            if (!$name_col) {
                throw new Exception("Could not find channel name column. Expected: name, channel name, channel_name, channel, or title");
            }
            
            if (!$category_col && !$country_col) {
                throw new Exception("Could not find category or country column. At least one is required.");
            }
            
            // Process each row
            foreach ($data as $row) {
                $channel_name = trim($row[$name_col] ?? '');
                if (empty($channel_name)) continue;
                
                $category = $category_col ? trim($row[$category_col] ?? '') : null;
                $country = $country_col ? trim($row[$country_col] ?? '') : null;
                
                // Find matching channel - try exact match first, then case-insensitive, then partial match
                $channel = null;
                
                // First try exact match
                $stmt = $conn->prepare("SELECT id, name, category, country FROM live_tv_channels WHERE name = ? LIMIT 1");
                $stmt->bind_param("s", $channel_name);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    // Try case-insensitive match
                    $stmt = $conn->prepare("SELECT id, name, category, country FROM live_tv_channels WHERE LOWER(name) = LOWER(?) LIMIT 1");
                    $stmt->bind_param("s", $channel_name);
                    $stmt->execute();
                    $result = $stmt->get_result();
                }
                
                if ($result->num_rows === 0) {
                    // Try partial match (contains)
                    $stmt = $conn->prepare("SELECT id, name, category, country FROM live_tv_channels WHERE name LIKE ? OR name LIKE ? LIMIT 1");
                    $search_name1 = $channel_name . '%';
                    $search_name2 = '%' . $channel_name . '%';
                    $stmt->bind_param("ss", $search_name1, $search_name2);
                    $stmt->execute();
                    $result = $stmt->get_result();
                }
                
                if ($result->num_rows > 0) {
                    $channel = $result->fetch_assoc();
                    $stats['matched']++;
                    
                    // Build update query
                    $updates = [];
                    $params = [];
                    $types = '';
                    
                    if ($category_col && !empty($category)) {
                        $updates[] = "category = ?";
                        // Ensure UTF-8 encoding (with fallback)
                        if (function_exists('mb_convert_encoding')) {
                            $category = mb_convert_encoding($category, 'UTF-8', 'auto');
                        } elseif (function_exists('iconv')) {
                            $category = @iconv('UTF-8', 'UTF-8//IGNORE', $category);
                        }
                        $params[] = $category;
                        $types .= 's';
                    }
                    
                    if ($country_col && !empty($country)) {
                        $updates[] = "country = ?";
                        // Ensure UTF-8 encoding (with fallback)
                        if (function_exists('mb_convert_encoding')) {
                            $country = mb_convert_encoding($country, 'UTF-8', 'auto');
                        } elseif (function_exists('iconv')) {
                            $country = @iconv('UTF-8', 'UTF-8//IGNORE', $country);
                        }
                        // Remove any null bytes or control characters that might cause issues
                        $country = preg_replace('/[\x00-\x1F\x7F]/u', '', $country);
                        $params[] = $country;
                        $types .= 's';
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $channel['id'];
                        $types .= 'i';
                        
                        $update_sql = "UPDATE live_tv_channels SET " . implode(", ", $updates) . " WHERE id = ?";
                        $update_stmt = $conn->prepare($update_sql);
                        $update_stmt->bind_param($types, ...$params);
                        
                        if ($update_stmt->execute()) {
                            $stats['updated']++;
                        } else {
                            $stats['errors']++;
                            $stats['error_messages'][] = "Failed to update channel: " . $channel['name'];
                        }
                    }
                } else {
                    $stats['not_found']++;
                }
            }
            
            // Redirect with results
            $result_params = http_build_query([
                'tab' => 'match-replace',
                'success' => 1,
                'total' => $stats['total'],
                'matched' => $stats['matched'],
                'updated' => $stats['updated'],
                'not_found' => $stats['not_found'],
                'errors' => $stats['errors']
            ]);
            header("Location: ?" . $result_params);
            exit;
        } catch (Exception $e) {
            header("Location: ?tab=match-replace&error=" . urlencode($e->getMessage()));
            exit;
        }
    } else {
        header("Location: ?tab=match-replace&error=" . urlencode("File upload error: " . $file['error']));
        exit;
    }
}

// Handle sliders actions BEFORE any output (to avoid header errors)
if ($tab === 'sliders') {
    // Slider actions are handled in sliders.php, but we need to ensure redirects work
    // The sliders.php file handles its own redirects with headers_sent() checks
}

// Handle ads actions BEFORE any output (to avoid header errors)
if ($tab === 'ads') {
    require_once __DIR__ . '/../config/config.php';
    
    // Ensure ads table has logo/image_url and content_type columns
    try {
        $conn->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS logo VARCHAR(500) NULL");
        $conn->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS content_type ENUM('image', 'video', 'html') DEFAULT 'html'");
        $conn->query("ALTER TABLE ads MODIFY COLUMN type ENUM('pre-roll', 'mid-roll', 'post-roll', 'banner', 'popup', 'intro-ad', 'loop') NOT NULL");
        $conn->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS skipable BOOLEAN DEFAULT TRUE");
        $conn->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS loop_interval INT NULL COMMENT 'For loop ads: how often to show (in seconds). Duration is how long ad plays.'");
    } catch (Exception $e) {
        // Columns might already exist
    }
    
    // Ensure uploads/ads directory exists
    $upload_dir = __DIR__ . '/../uploads/ads';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Handle delete action
    if (isset($_GET['delete'])) {
        $id = intval($_GET['delete']);
        
        // Delete associated file
        $ad = $conn->query("SELECT logo FROM ads WHERE id = $id")->fetch_assoc();
        if ($ad && !empty($ad['logo']) && file_exists(__DIR__ . '/../' . $ad['logo'])) {
            unlink(__DIR__ . '/../' . $ad['logo']);
        }
        
        $conn->query("DELETE FROM ads WHERE id = $id");
        header("Location: ?tab=ads");
        exit;
    }
    
    // Handle POST (add/edit) action
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
        $id = $_POST['id'] ?? null;
        $name = sanitize($_POST['name'] ?? '');
        $type = sanitize($_POST['type'] ?? 'pre-roll');
        $content = $_POST['content'] ?? ''; // Don't sanitize HTML content
        $content_type = sanitize($_POST['content_type'] ?? 'html');
        $duration = intval($_POST['duration'] ?? 0);
        $loop_interval = ($type === 'loop' && !empty($_POST['loop_interval'])) ? intval($_POST['loop_interval']) : null;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $skipable = isset($_POST['skipable']) ? 1 : 0;
        
        // Handle dates - if empty, set to NULL (no expiry)
        $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $logo = null;
        
        // If editing, get existing logo first (will be overwritten if new file uploaded)
        if ($id) {
            $existing = $conn->prepare("SELECT logo FROM ads WHERE id = ?");
            $existing->bind_param("i", $id);
            $existing->execute();
            $existing_result = $existing->get_result();
            if ($existing_result->num_rows > 0) {
                $existing_logo = $existing_result->fetch_assoc()['logo'];
                if (!empty($existing_logo)) {
                    $logo = $existing_logo; // Set existing logo as default
                }
            }
        }
        
        // Handle file upload (will overwrite existing logo if new file uploaded)
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo_file'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_error = $file['error'];
            
            // Get file extension
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Allowed extensions
            $allowed_image = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
            $allowed_video = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
            $allowed = array_merge($allowed_image, $allowed_video);
            
            if (in_array($file_ext, $allowed)) {
                // Check file size (max 50MB)
                if ($file_size <= 50 * 1024 * 1024) {
                    // Generate unique filename
                    $new_filename = 'ad_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . '/' . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        $logo = 'uploads/ads/' . $new_filename;
                        
                        // If editing, delete old file
                        if ($id) {
                            $old_ad = $conn->prepare("SELECT logo FROM ads WHERE id = ?");
                            $old_ad->bind_param("i", $id);
                            $old_ad->execute();
                            $old_logo = $old_ad->get_result()->fetch_assoc()['logo'];
                            if ($old_logo && file_exists(__DIR__ . '/../' . $old_logo)) {
                                unlink(__DIR__ . '/../' . $old_logo);
                            }
                        }
                    }
                }
            }
        }
        // Note: If no file uploaded and editing, $logo already contains existing logo from above
        
        // Auto-detect content_type if logo is uploaded
        if ($logo && $content_type === 'html') {
            $logo_ext = strtolower(pathinfo($logo, PATHINFO_EXTENSION));
            if (in_array($logo_ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                $content_type = 'image';
            } elseif (in_array($logo_ext, ['mp4', 'webm', 'ogg', 'mov', 'avi'])) {
                $content_type = 'video';
            }
        }
        
        // Intro ads are never skipable
        if ($type === 'intro-ad') {
            $skipable = 0;
        }
        
        if ($id) {
            // Always include logo in UPDATE (will be existing logo if no new file uploaded, or new logo if uploaded)
            $stmt = $conn->prepare("UPDATE ads SET name=?, type=?, content=?, content_type=?, logo=?, duration=?, loop_interval=?, is_active=?, skipable=?, start_date=?, end_date=? WHERE id=?");
            $stmt->bind_param("sssssiissssi", $name, $type, $content, $content_type, $logo, $duration, $loop_interval, $is_active, $skipable, $start_date, $end_date, $id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO ads (name, type, content, content_type, logo, duration, loop_interval, is_active, skipable, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssiissss", $name, $type, $content, $content_type, $logo, $duration, $loop_interval, $is_active, $skipable, $start_date, $end_date);
            $stmt->execute();
        }
        header("Location: ?tab=ads");
        exit;
    }
}

// Get stats for dashboard
$stats = getAdminStats($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #141414; color: #e5e5e5; }
        .netflix-red { color: #e50914; }
        .bg-netflix-red { background-color: #e50914; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .nav-item.active { border-bottom: 2px solid #e50914; }
        
        /* Dropdown menu styles */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #1a1a1a;
            min-width: 200px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.5);
            z-index: 1000;
            border: 1px solid #333;
            border-radius: 4px;
            top: 100%;
            left: 0;
            margin-top: 2px;
        }
        
        .dropdown:hover .dropdown-content {
            display: block;
        }
        
        .dropdown-item {
            color: #e5e5e5;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: #2a2a2a;
            color: #e50914;
        }
        
        .dropdown-item.active {
            background-color: #e50914;
            color: white;
        }
        
        /* Horizontal scrolling menu */
        .nav-scroll-container {
            position: relative;
            display: flex;
            align-items: center;
            max-width: 100%;
        }
        
        .nav-scroll-wrapper {
            position: relative;
            flex: 1;
            overflow: hidden;
        }
        
        .nav-scroll-menu {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            padding: 0 10px;
        }
        
        .nav-scroll-menu::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }
        
        .nav-scroll-arrow {
            background: rgba(0, 0, 0, 0.7);
            border: none;
            color: white;
            cursor: pointer;
            padding: 8px 12px;
            font-size: 18px;
            z-index: 10;
            transition: all 0.3s;
            flex-shrink: 0;
        }
        
        .nav-scroll-arrow:hover {
            background: rgba(229, 9, 20, 0.8);
        }
        
        .nav-scroll-arrow:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        .nav-scroll-arrow-left {
            border-radius: 4px 0 0 4px;
        }
        
        .nav-scroll-arrow-right {
            border-radius: 0 4px 4px 0;
        }
        
        /* Profile Dropdown */
        #profile-menu {
            animation: fadeIn 0.2s ease-in-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="bg-black text-white">
    <!-- Admin Header -->
    <nav class="bg-gray-900 border-b border-gray-800 sticky top-0 z-50">
        <!-- First Row: Logo and Profile -->
        <div class="container mx-auto px-4 py-3 flex items-center justify-between">
            <a href="?tab=dashboard" class="text-2xl font-bold netflix-red">ADMIN PANEL</a>
            <div class="relative">
                <button onclick="toggleProfileMenu()" class="flex items-center space-x-2 hover:opacity-80">
                    <i class="fas fa-user-circle text-3xl text-gray-400"></i>
                    <span class="hidden md:block"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                    <i class="fas fa-chevron-down text-xs"></i>
                </button>
                <div id="profile-menu" class="hidden absolute right-0 mt-2 w-48 bg-gray-800 rounded-lg shadow-lg border border-gray-700 py-2 z-50">
                    <div class="px-4 py-2 border-b border-gray-700">
                        <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></p>
                        <p class="text-xs text-gray-400">Administrator</p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/admin/logout.php" class="block px-4 py-2 hover:bg-gray-700 text-sm">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Second Row: Navigation Menu -->
        <div class="container mx-auto px-4 pb-3">
            <div class="nav-scroll-container">
                <button class="nav-scroll-arrow nav-scroll-arrow-left" id="nav-scroll-left" onclick="scrollNav('left')" aria-label="Scroll left">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="nav-scroll-wrapper">
                    <div class="nav-scroll-menu" id="admin-nav">
                            <a href="?tab=dashboard" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'dashboard' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                            </a>
                            <a href="?tab=movies" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'movies' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-film mr-2"></i>Movies
                            </a>
                            <a href="?tab=tv-shows" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'tv-shows' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-tv mr-2"></i>TV Shows
                            </a>
                            <a href="?tab=live-tv" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'live-tv' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-broadcast-tower mr-2"></i>Live TV
                            </a>
                            <a href="?tab=users" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'users' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-users mr-2"></i>Users
                            </a>
                            <a href="?tab=coupons" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'coupons' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-ticket-alt mr-2"></i>Coupons
                            </a>
                            <a href="?tab=ads" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'ads' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-ad mr-2"></i>Ads
                            </a>
                            <a href="?tab=sliders" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'sliders' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-images mr-2"></i>Sliders
                            </a>
                            <a href="?tab=reports" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'reports' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-chart-bar mr-2"></i>Reports
                            </a>
                            <a href="?tab=user-messages" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'user-messages' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-envelope mr-2"></i>Msgs
                            </a>
                            <a href="?tab=settings" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'settings' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-cog mr-2"></i>Settings
                            </a>
                            <a href="?tab=import" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'import' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-download mr-2"></i>Import/Export
                            </a>
                            <a href="?tab=iptv" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'iptv' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-satellite-dish mr-2"></i>IPTV
                            </a>
                            <a href="?tab=countdown" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo $tab === 'countdown' ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-clock mr-2"></i>Countdown
                            </a>
                            <a href="?tab=tools" class="nav-item px-3 py-2 hover:text-gray-300 whitespace-nowrap flex-shrink-0 <?php echo in_array($tab, ['tools', 'match-replace', 'import-sql', 'delete-m3u8-channels', 'delete-no-source-channels', 'delete-dash-channels', 'remove-bad-m3u8-sources', 'remove-bad-dash-sources', 'remove-http-stream-links', 'search-check-streams', 'remove-no-logo-channels']) ? 'active font-bold text-netflix-red' : ''; ?>">
                                <i class="fas fa-tools mr-2"></i>Tools
                            </a>
                        </div>
                    </div>
                    <button class="nav-scroll-arrow nav-scroll-arrow-right" id="nav-scroll-right" onclick="scrollNav('right')" aria-label="Scroll right">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Admin Content -->
    <div class="container mx-auto px-4 py-8">
        <?php
        // Load the appropriate tab content
        $tabFile = __DIR__ . '/' . $tab . '.php';
        
        // Handle special cases with different file names
        if ($tab === 'tv-shows') {
            $tabFile = __DIR__ . '/tv-shows.php';
        } elseif ($tab === 'live-tv') {
            $tabFile = __DIR__ . '/live-tv.php';
        } elseif ($tab === 'user-messages') {
            $tabFile = __DIR__ . '/user-messages.php';
        } elseif ($tab === 'bulk-fetch') {
            $tabFile = __DIR__ . '/bulk_fetch.php';
        } elseif ($tab === 'iptv') {
            $tabFile = __DIR__ . '/iptv.php';
        } elseif ($tab === 'match-replace') {
            $tabFile = __DIR__ . '/match-replace.php';
        } elseif ($tab === 'import-sql') {
            $tabFile = __DIR__ . '/import-sql.php';
        } elseif ($tab === 'delete-m3u8-channels') {
            $tabFile = __DIR__ . '/delete-m3u8-channels.php';
        } elseif ($tab === 'delete-no-source-channels') {
            $tabFile = __DIR__ . '/delete-no-source-channels.php';
        } elseif ($tab === 'delete-dash-channels') {
            $tabFile = __DIR__ . '/delete-dash-channels.php';
        } elseif ($tab === 'remove-bad-m3u8-sources') {
            $tabFile = __DIR__ . '/remove-bad-m3u8-sources.php';
        } elseif ($tab === 'remove-bad-dash-sources') {
            $tabFile = __DIR__ . '/remove-bad-dash-sources.php';
        } elseif ($tab === 'remove-http-stream-links') {
            $tabFile = __DIR__ . '/remove-http-stream-links.php';
        } elseif ($tab === 'search-check-streams') {
            $tabFile = __DIR__ . '/search-check-streams.php';
        } elseif ($tab === 'remove-no-logo-channels') {
            $tabFile = __DIR__ . '/remove-no-logo-channels.php';
        }
        
        if (file_exists($tabFile)) {
            // Set flag to indicate this file is being loaded as a tab
            define('ADMIN_TAB_LOAD', true);
            require $tabFile;
        } else {
            echo '<div class="bg-red-900 bg-opacity-50 border border-red-700 text-red-200 px-4 py-3 rounded mb-4">';
            echo 'Tab file not found: ' . htmlspecialchars($tab);
            echo '</div>';
        }
        ?>
    </div>

    <script>
        const navMenu = document.getElementById('admin-nav');
        const scrollLeftBtn = document.getElementById('nav-scroll-left');
        const scrollRightBtn = document.getElementById('nav-scroll-right');
        const scrollAmount = 200; // Pixels to scroll per click
        
        function updateScrollButtons() {
            const isAtStart = navMenu.scrollLeft <= 0;
            const isAtEnd = navMenu.scrollLeft >= navMenu.scrollWidth - navMenu.clientWidth - 1;
            
            scrollLeftBtn.disabled = isAtStart;
            scrollRightBtn.disabled = isAtEnd;
        }
        
        function scrollNav(direction) {
            const currentScroll = navMenu.scrollLeft;
            const scrollTo = direction === 'left' 
                ? currentScroll - scrollAmount 
                : currentScroll + scrollAmount;
            
            navMenu.scrollTo({
                left: scrollTo,
                behavior: 'smooth'
            });
        }
        
        // Update button states on scroll
        navMenu.addEventListener('scroll', updateScrollButtons);
        
        // Update button states on window resize
        window.addEventListener('resize', updateScrollButtons);
        
        // Initial button state check
        updateScrollButtons();
        
        // Check if scrolling is needed after page load
        setTimeout(updateScrollButtons, 100);
        
        // Smooth scroll for navigation items
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                document.querySelectorAll('.nav-item').forEach(nav => {
                    nav.classList.remove('active', 'font-bold', 'text-netflix-red');
                });
                this.classList.add('active', 'font-bold', 'text-netflix-red');
                
                // Scroll active item into view
                setTimeout(() => {
                    this.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }, 100);
            });
        });
        
        // Profile Dropdown Toggle
        function toggleProfileMenu() {
            const menu = document.getElementById('profile-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('profile-menu');
            const button = event.target.closest('[onclick="toggleProfileMenu()"]');
            
            if (menu && !button && !menu.classList.contains('hidden')) {
                menu.classList.add('hidden');
            }
        });
    </script>
</body>
</html>
