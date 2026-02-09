<?php
/**
 * Admin Panel - Import/Export SQL Tool
 * - Import: TV channels from SQL file, updates existing and adds new without deleting
 * - Export: TV channels from database into a re-importable SQL file
 */
$page_title = "Import SQL";

$message = '';
$message_type = '';
$stats = [];

// Check for success message from redirect
if (isset($_GET['success'])) {
    $message_type = 'success';
    $stats = [
        'total' => intval($_GET['total'] ?? 0),
        'updated' => intval($_GET['updated'] ?? 0),
        'added' => intval($_GET['added'] ?? 0),
        'skipped' => intval($_GET['skipped'] ?? 0),
        'errors' => intval($_GET['errors'] ?? 0)
    ];
    
    $message = "Import complete! ";
    $message .= "Total channels in SQL: {$stats['total']}, ";
    $message .= "Updated: {$stats['updated']}, ";
    $message .= "Added: {$stats['added']}, ";
    $message .= "Skipped: {$stats['skipped']}";
    if ($stats['errors'] > 0) {
        $message .= ", Errors: {$stats['errors']}";
    }
    $message .= ". Note: Existing channels not in the SQL file were kept unchanged.";
}

// Check for error message
if (isset($_GET['error'])) {
    $message = urldecode($_GET['error']);
    $message_type = 'error';
}
?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-database mr-2 text-netflix-red"></i>Import SQL
    </h1>
    
    <p class="text-gray-400 mb-6">
        Upload a SQL file containing TV channels data. The system will:
    </p>
    
    <ul class="list-disc list-inside text-gray-300 mb-6 space-y-2">
        <li><strong>Update</strong> existing channels that match by name</li>
        <li><strong>Add</strong> new channels from the SQL file</li>
        <li><strong>Keep</strong> all existing channels that are not in the SQL file (nothing is deleted)</li>
    </ul>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($stats) && $stats['total'] > 0): ?>
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-bold mb-3">Import Results</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-400"><?php echo $stats['total']; ?></div>
                <div class="text-sm text-gray-400">Total in SQL</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-400"><?php echo $stats['updated']; ?></div>
                <div class="text-sm text-gray-400">Updated</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-400"><?php echo $stats['added']; ?></div>
                <div class="text-sm text-gray-400">Added</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-400"><?php echo $stats['skipped']; ?></div>
                <div class="text-sm text-gray-400">Skipped</div>
            </div>
            <?php if ($stats['errors'] > 0): ?>
            <div class="text-center">
                <div class="text-2xl font-bold text-red-400"><?php echo $stats['errors']; ?></div>
                <div class="text-sm text-gray-400">Errors</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">SQL File Format Requirements</h2>
        <div class="space-y-3 text-sm text-gray-300">
            <div class="flex items-start">
                <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                <div>
                    <strong>File Format:</strong> Must be a .sql file containing INSERT INTO live_tv_channels statements
                </div>
            </div>
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-400 mr-2 mt-1"></i>
                <div>
                    <strong>Matching:</strong> Channels are matched by name (case-sensitive). If a channel with the same name exists, it will be updated. Otherwise, a new channel will be added.
                </div>
            </div>
            <div class="flex items-start">
                <i class="fas fa-shield-alt text-yellow-400 mr-2 mt-1"></i>
                <div>
                    <strong>Safety:</strong> No channels will be deleted. All existing channels not in the SQL file will remain unchanged.
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-bold mb-4">Upload SQL File</h2>
        <form method="POST" enctype="multipart/form-data" id="uploadForm">
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">SQL File *</label>
                <input type="file" name="sql_file" id="sql_file" accept=".sql" 
                       class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:border-netflix-red focus:outline-none" required>
                <p class="text-xs text-gray-400 mt-1">
                    Supported format: .sql file with INSERT INTO live_tv_channels statements
                </p>
            </div>
            
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <button type="submit" class="bg-netflix-red hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors">
                        <i class="fas fa-upload mr-2"></i>Upload & Import
                    </button>
                    <a href="?tab=import-sql&export=live_tv" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors">
                        <i class="fas fa-download mr-2"></i>Export Current Channels as SQL
                    </a>
                </div>
                <a href="?tab=tools" class="text-gray-400 hover:text-white inline-flex items-center mt-2 md:mt-0">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tools
                </a>
            </div>
        </form>
        
        <!-- Progress indicator -->
        <div id="uploadProgress" class="hidden mt-4">
            <div class="bg-gray-700 rounded-full h-2.5">
                <div class="bg-netflix-red h-2.5 rounded-full animate-pulse" style="width: 100%"></div>
            </div>
            <p class="text-sm text-gray-400 mt-2 text-center">Processing SQL file, please wait...</p>
        </div>
    </div>
    
    <!-- Example SQL format -->
    <div class="bg-gray-800 rounded-lg p-6 mt-6">
        <h2 class="text-xl font-bold mb-4">Example SQL Format</h2>
        <div class="bg-gray-900 rounded p-4 overflow-x-auto">
            <pre class="text-sm text-gray-300 font-mono">INSERT INTO `live_tv_channels` (`name`, `description`, `logo`, `stream_url`, `category`, `country`, `language`, `is_active`) VALUES
('CNN News', 'CNN News Channel', 'https://example.com/logo.png', 'https://stream.com/cnn.m3u8', 'News', 'US', 'en', 1),
('BBC One', 'BBC One Channel', 'https://example.com/bbc.png', 'https://stream.com/bbc.m3u8', 'Entertainment', 'UK', 'en', 1);</pre>
        </div>
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            The SQL file should contain INSERT statements for the live_tv_channels table. Column names should match the database schema.
        </p>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('sql_file');
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please select a SQL file to upload');
        return false;
    }
    
    // Show progress indicator
    document.getElementById('uploadProgress').classList.remove('hidden');
    
    // Disable submit button
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
});
</script>
