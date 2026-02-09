<?php
/**
 * Admin Panel - Countdown Management
 */
$page_title = "Manage Countdowns";

$message = '';
$message_type = '';

// Check for success message from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'deleted') {
        $message = 'Countdown deleted successfully';
    } else {
        $message = 'Countdown ' . (isset($_GET['edit']) ? 'updated' : 'added') . ' successfully';
    }
    $message_type = 'success';
}

// Note: Delete and POST actions are now handled in admin/index.php before output starts
// to avoid "headers already sent" errors

$countdowns = $conn->query("SELECT * FROM countdowns ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$edit_countdown = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_countdown = $conn->prepare("SELECT * FROM countdowns WHERE id = ?");
    $edit_countdown->bind_param("i", $edit_id);
    $edit_countdown->execute();
    $edit_countdown = $edit_countdown->get_result()->fetch_assoc();
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Countdowns</h1>
    <p class="text-gray-400">Create and manage countdown timers for events</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4"><?php echo $edit_countdown ? 'Edit' : 'Add'; ?> Countdown</h2>
    <form method="POST" action="">
        <?php if ($edit_countdown): ?>
        <input type="hidden" name="id" value="<?php echo $edit_countdown['id']; ?>">
        <?php endif; ?>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Title *</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($edit_countdown['title'] ?? ''); ?>" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_countdown['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Target Date & Time (Pakistan Standard Time) *</label>
                <input type="datetime-local" name="target_datetime" 
                       value="<?php echo $edit_countdown['target_datetime'] ? date('Y-m-d\TH:i', strtotime($edit_countdown['target_datetime'])) : ''; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
                <p class="text-xs text-gray-400 mt-1">Time will be interpreted as Pakistan Standard Time (PKT - UTC+5)</p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">URL Slug *</label>
                <input type="text" name="slug" value="<?php echo htmlspecialchars($edit_countdown['slug'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required
                       pattern="[a-z0-9-]+" title="Only lowercase letters, numbers, and hyphens allowed">
                <p class="text-xs text-gray-400 mt-1">URL: <?php echo BASE_URL; ?>/countdown/<span id="slug-preview"><?php echo htmlspecialchars($edit_countdown['slug'] ?? 'your-slug'); ?></span></p>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" <?php echo ($edit_countdown['is_active'] ?? 1) ? 'checked' : ''; ?> 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <?php echo $edit_countdown ? 'Update' : 'Add'; ?> Countdown
        </button>
        <?php if ($edit_countdown): ?>
        <a href="?tab=countdown" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All Countdowns</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Title</th>
                    <th class="text-left p-3">Target Date/Time</th>
                    <th class="text-left p-3">Slug</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Link</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($countdowns)): ?>
                <tr>
                    <td colspan="6" class="p-4 text-center text-gray-400">No countdowns found. Create your first countdown above.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($countdowns as $countdown): ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3 font-semibold"><?php echo htmlspecialchars($countdown['title']); ?></td>
                    <td class="p-3">
                        <?php 
                        $target_date = new DateTime($countdown['target_datetime'], new DateTimeZone('Asia/Karachi'));
                        echo $target_date->format('Y-m-d H:i:s') . ' PKT';
                        ?>
                    </td>
                    <td class="p-3 font-mono text-sm"><?php echo htmlspecialchars($countdown['slug']); ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?php echo $countdown['is_active'] ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                            <?php echo $countdown['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <a href="<?php echo BASE_URL; ?>/countdown/<?php echo htmlspecialchars($countdown['slug']); ?>" 
                           target="_blank" 
                           class="text-blue-400 hover:text-blue-300 text-sm">
                            <i class="fas fa-external-link-alt mr-1"></i>View
                        </a>
                    </td>
                    <td class="p-3">
                        <a href="?tab=countdown&edit=<?php echo $countdown['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                        <a href="?tab=countdown&delete=<?php echo $countdown['id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this countdown?')" 
                           class="text-red-400 hover:text-red-300">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Update slug preview as user types
document.querySelector('input[name="slug"]')?.addEventListener('input', function(e) {
    const preview = document.getElementById('slug-preview');
    if (preview) {
        preview.textContent = e.target.value || 'your-slug';
    }
});

// Auto-generate slug from title if slug is empty
document.querySelector('input[name="title"]')?.addEventListener('blur', function(e) {
    const slugInput = document.querySelector('input[name="slug"]');
    if (slugInput && !slugInput.value) {
        const slug = e.target.value.toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slugInput.value = slug;
        const preview = document.getElementById('slug-preview');
        if (preview) {
            preview.textContent = slug || 'your-slug';
        }
    }
});
</script>
