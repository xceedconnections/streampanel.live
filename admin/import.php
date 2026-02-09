<?php
/**
 * Admin Panel - Import/Export SQL
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Import/Export";

$message = '';
$message_type = '';
$import_stats = [];

$conn = getDBConnection();

// Handle Export
if (isset($_GET['export']) && $_GET['export'] === 'sql') {
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="streaming_portal_backup_' . date('Y-m-d_His') . '.sql"');
    
    // Get database name
    $db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
    
    echo "-- Streaming Portal Database Backup\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database: {$db_name}\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n";
    echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
    
    // Get all tables
    $tables = $conn->query("SHOW TABLES");
    $table_list = [];
    
    while ($row = $tables->fetch_row()) {
        $table_name = $row[0];
        $table_list[] = $table_name;
        
        // Get table structure
        $create_table = $conn->query("SHOW CREATE TABLE `{$table_name}`");
        $create_row = $create_table->fetch_row();
        
        echo "-- Table structure for `{$table_name}`\n";
        echo "DROP TABLE IF EXISTS `{$table_name}`;\n";
        echo $create_row[1] . ";\n\n";
        
        // Get table data
        $data = $conn->query("SELECT * FROM `{$table_name}`");
        if ($data->num_rows > 0) {
            echo "-- Data for table `{$table_name}`\n";
            
            // Get column names
            $columns = [];
            $fields = $data->fetch_fields();
            foreach ($fields as $field) {
                $columns[] = "`{$field->name}`";
            }
            
            // Generate INSERT statements in batches
            $data->data_seek(0);
            $batch_size = 100;
            $batch = [];
            $count = 0;
            
            while ($row = $data->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $batch[] = '(' . implode(',', $values) . ')';
                $count++;
                
                if (count($batch) >= $batch_size) {
                    echo "INSERT INTO `{$table_name}` (" . implode(',', $columns) . ") VALUES\n";
                    echo implode(",\n", $batch) . ";\n\n";
                    $batch = [];
                }
            }
            
            // Insert remaining rows
            if (!empty($batch)) {
                echo "INSERT INTO `{$table_name}` (" . implode(',', $columns) . ") VALUES\n";
                echo implode(",\n", $batch) . ";\n\n";
            }
            
            echo "-- Total rows: {$count}\n\n";
        }
    }
    
    echo "SET FOREIGN_KEY_CHECKS=1;\n";
    exit;
}

// Handle Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['sql_file'])) {
    $file = $_FILES['sql_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $content = file_get_contents($file['tmp_name']);
        
        // Remove comments
        $content = preg_replace('/--.*$/m', '', $content);
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);
        
        // Split into statements (handling strings properly)
        $statements = [];
        $current = '';
        $in_string = false;
        $string_char = '';
        $escaped = false;
        
        for ($i = 0; $i < strlen($content); $i++) {
            $char = $content[$i];
            
            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }
            
            if ($char === '\\') {
                $escaped = true;
                $current .= $char;
                continue;
            }
            
            if (!$in_string && ($char === '"' || $char === "'" || $char === '`')) {
                $in_string = true;
                $string_char = $char;
                $current .= $char;
            } elseif ($in_string && $char === $string_char) {
                $in_string = false;
                $current .= $char;
            } elseif (!$in_string && $char === ';') {
                $stmt = trim($current);
                if (!empty($stmt) && !preg_match('/^(SET|DROP)/i', $stmt)) {
                    $statements[] = $stmt;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        // Add last statement if exists
        $stmt = trim($current);
        if (!empty($stmt) && !preg_match('/^(SET|DROP)/i', $stmt)) {
            $statements[] = $stmt;
        }
        
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $error_messages = [];
        
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
        
        foreach ($statements as $stmt) {
            if (empty(trim($stmt))) continue;
            
            // Check if it's an INSERT statement
            if (preg_match('/^INSERT\s+(IGNORE\s+)?INTO/i', $stmt)) {
                // Count rows in the INSERT statement
                preg_match_all('/\([^)]+\)/', $stmt, $matches);
                $row_count = count($matches[0]);
                
                // Execute with INSERT IGNORE to skip duplicates
                $stmt_ignore = preg_replace('/INSERT\s+(IGNORE\s+)?INTO/i', 'INSERT IGNORE INTO', $stmt);
                
                if ($conn->query($stmt_ignore)) {
                    $affected = $conn->affected_rows;
                    $imported += $affected;
                    $skipped += ($row_count - $affected);
                } else {
                    $error = $conn->error;
                    $errors++;
                    if (count($error_messages) < 10) {
                        $error_messages[] = substr($error, 0, 200);
                    }
                }
            } else {
                // Execute other statements (CREATE TABLE, etc.)
                if ($conn->query($stmt)) {
                    // Success
                } else {
                    $error = $conn->error;
                    // Ignore "table already exists" errors
                    if (strpos($error, 'already exists') === false) {
                        $errors++;
                        if (count($error_messages) < 10) {
                            $error_messages[] = substr($error, 0, 200);
                        }
                    }
                }
            }
        }
        
        $conn->query("SET FOREIGN_KEY_CHECKS=1");
        
        $import_stats = [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'error_messages' => $error_messages
        ];
        
        if ($errors === 0) {
            $message = "Import completed! Imported: {$imported} rows, Skipped (duplicates): {$skipped} rows";
            $message_type = 'success';
        } elseif ($imported > 0) {
            $message = "Import completed with some errors. Imported: {$imported} rows, Skipped: {$skipped} rows, Errors: {$errors}";
            $message_type = 'warning';
        } else {
            $message = "Import failed. Errors: {$errors}";
            $message_type = 'error';
        }
    } else {
        $message = 'Error uploading file';
        $message_type = 'error';
    }
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Import/Export</h1>
    <p class="text-gray-400">Backup and restore your database with SQL files</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>-700 text-<?php echo $message_type === 'success' ? 'green' : ($message_type === 'warning' ? 'yellow' : 'red'); ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
    <?php if (!empty($import_stats) && !empty($import_stats['error_messages'])): ?>
    <div class="mt-2 text-sm">
        <strong>Error Details:</strong>
        <ul class="list-disc list-inside mt-1">
            <?php foreach ($import_stats['error_messages'] as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-gray-900 rounded-lg p-6">
        <h3 class="text-xl font-bold mb-4">Export Database</h3>
        <p class="text-gray-400 mb-4">Download your entire database as an SQL backup file.</p>
        <a href="?tab=import&export=sql" class="block bg-netflix-red px-6 py-2 rounded hover:bg-red-700 w-full text-center">
            <i class="fas fa-download mr-2"></i>Export & Download SQL
        </a>
        <p class="text-xs text-gray-500 mt-2">Includes all tables with data</p>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6">
        <h3 class="text-xl font-bold mb-4">Import Database</h3>
        <p class="text-gray-400 mb-4">Upload an SQL backup file to restore your database.</p>
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="mb-4">
                <input type="file" name="sql_file" accept=".sql" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            </div>
            <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 w-full">
                <i class="fas fa-upload mr-2"></i>Import SQL File
            </button>
        </form>
        <p class="text-xs text-gray-500 mt-2">Duplicates will be automatically skipped</p>
    </div>
</div>

<div class="bg-blue-900 bg-opacity-30 border border-blue-700 rounded-lg p-4 mt-6">
    <h4 class="font-semibold text-blue-200 mb-2">ℹ️ Import/Export Information</h4>
    <ul class="text-sm text-gray-300 space-y-1 list-disc list-inside">
        <li><strong>Export:</strong> Creates a complete SQL backup with all tables and data</li>
        <li><strong>Import:</strong> Imports SQL file and automatically skips duplicate entries</li>
        <li><strong>Duplicate Detection:</strong> Based on primary keys or unique constraints</li>
        <li><strong>Safety:</strong> Always backup before importing to avoid data loss</li>
        <li><strong>Tables Included:</strong> All tables in the database (movies, tv_shows, live_tv_channels, users, etc.)</li>
    </ul>
</div>
