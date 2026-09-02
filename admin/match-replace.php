<?php
/**
 * Admin Panel - Match and Replace Tool
 * Matches TV channels by name from Excel/CSV and updates category and country
 */
$page_title = "Match & Replace";
?>

<?php
$message = '';
$message_type = '';
$stats = [];

// Check for success message from redirect
if (isset($_GET['success'])) {
    $message_type = 'success';
    $stats = [
        'total' => intval($_GET['total'] ?? 0),
        'matched' => intval($_GET['matched'] ?? 0),
        'updated' => intval($_GET['updated'] ?? 0),
        'not_found' => intval($_GET['not_found'] ?? 0),
        'errors' => intval($_GET['errors'] ?? 0)
    ];
    
    $message = "Processing complete! ";
    $message .= "Total rows: {$stats['total']}, ";
    $message .= "Matched: {$stats['matched']}, ";
    $message .= "Updated: {$stats['updated']}, ";
    $message .= "Not found: {$stats['not_found']}";
    if ($stats['errors'] > 0) {
        $message .= ", Errors: {$stats['errors']}";
    }
}

// Check for error message
if (isset($_GET['error'])) {
    $message = urldecode($_GET['error']);
    $message_type = 'error';
}
?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-exchange-alt mr-2 text-netflix-red"></i>Match & Replace
    </h1>
    
    <p class="text-gray-400 mb-6">
        Upload an Excel or CSV file containing channel names, categories, and countries. 
        The system will match channels by name and update their category and country information.
    </p>
    
    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($stats) && $stats['total'] > 0): ?>
    <div class="bg-gray-800 rounded-lg p-4 mb-6">
        <h3 class="text-lg font-bold mb-3">Processing Results</h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-400"><?php echo $stats['total']; ?></div>
                <div class="text-sm text-gray-400">Total Rows</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-400"><?php echo $stats['matched']; ?></div>
                <div class="text-sm text-gray-400">Matched</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-yellow-400"><?php echo $stats['updated']; ?></div>
                <div class="text-sm text-gray-400">Updated</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-400"><?php echo $stats['not_found']; ?></div>
                <div class="text-sm text-gray-400">Not Found</div>
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
        <h2 class="text-xl font-bold mb-4">File Format Requirements</h2>
        <div class="space-y-3 text-sm text-gray-300">
            <div class="flex items-start">
                <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                <div>
                    <strong>Required Column:</strong> Channel Name (can be named: "name", "channel name", "channel_name", "channel", or "title")
                </div>
            </div>
            <div class="flex items-start">
                <i class="fas fa-check-circle text-green-400 mr-2 mt-1"></i>
                <div>
                    <strong>Optional Columns:</strong> Category (can be named: "category", "cat", or "categories") and Country (can be named: "country", "countries", or "nation")
                </div>
            </div>
            <div class="flex items-start">
                <i class="fas fa-info-circle text-blue-400 mr-2 mt-1"></i>
                <div>
                    <strong>Matching:</strong> Channels are matched by name (case-insensitive, partial match). At least one of category or country must be provided.
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-gray-800 rounded-lg p-6">
        <h2 class="text-xl font-bold mb-4">Upload File</h2>
        <form method="POST" enctype="multipart/form-data" id="uploadForm" data-tool-progress="Uploading and processing file...">
            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Excel/CSV File *</label>
                <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" 
                       class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:border-netflix-red focus:outline-none" required>
                <p class="text-xs text-gray-400 mt-1">
                    Supported formats: .xlsx, .xls, .csv
                    <?php if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')): ?>
                    <span class="text-yellow-400">(CSV is recommended - Excel requires PhpSpreadsheet library)</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="flex items-center justify-between">
                <button type="submit" class="bg-netflix-red hover:bg-red-700 text-white font-bold py-2 px-6 rounded transition-colors">
                    <i class="fas fa-upload mr-2"></i>Upload & Process
                </button>
                <a href="?tab=tools" class="text-gray-400 hover:text-white">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tools
                </a>
            </div>
        </form>
        
        <!-- Progress indicator -->
        <div id="uploadProgress" class="hidden mt-4">
            <div class="bg-gray-700 rounded-full h-2.5">
                <div class="bg-netflix-red h-2.5 rounded-full animate-pulse" style="width: 100%"></div>
            </div>
            <p class="text-sm text-gray-400 mt-2 text-center">Processing file, please wait...</p>
        </div>
    </div>
    
    <!-- Example CSV format -->
    <div class="bg-gray-800 rounded-lg p-6 mt-6">
        <h2 class="text-xl font-bold mb-4">Example CSV Format</h2>
        <div class="bg-gray-900 rounded p-4 overflow-x-auto">
            <pre class="text-sm text-gray-300 font-mono">name,category,country
CNN News,News,US
BBC One,Entertainment,UK
ESPN,Sports,US
Discovery Channel,Documentary,US</pre>
        </div>
        <p class="text-xs text-gray-400 mt-2">
            <i class="fas fa-info-circle mr-1"></i>
            The first row should contain column headers. Column names are case-insensitive and flexible.
        </p>
    </div>
</div>

<script>
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('excel_file');
    if (fileInput.files.length === 0) {
        e.preventDefault();
        alert('Please select a file to upload');
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

<?php require __DIR__ . '/includes/tool-progress-ui.php'; ?>
