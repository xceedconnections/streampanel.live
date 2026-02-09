<?php
/**
 * Admin Panel - Ads Management
 */
require_once __DIR__ . '/../config/config.php';
$page_title = "Manage Ads";

$message = '';
$message_type = '';

// Ensure ads table has logo/image_url and content_type columns
try {
    $conn->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS logo VARCHAR(500) NULL");
    $conn->query("ALTER TABLE ads ADD COLUMN IF NOT EXISTS content_type ENUM('image', 'video', 'html') DEFAULT 'html'");
} catch (Exception $e) {
    // Columns might already exist
}

// Ensure uploads/ads directory exists
$upload_dir = __DIR__ . '/../uploads/ads';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Display success message from URL parameter
if (isset($_GET['success'])) {
    $message = 'Ad saved successfully';
    $message_type = 'success';
}

// Get regular ads (excluding intro-ads)
$ads = $conn->query("SELECT * FROM ads WHERE type != 'intro-ad' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

// Get intro ad (only one should exist)
$intro_ad = $conn->query("SELECT * FROM ads WHERE type = 'intro-ad' ORDER BY created_at DESC LIMIT 1")->fetch_assoc();

$edit_ad = null;
$is_intro_ad = false;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $edit_ad = $conn->prepare("SELECT * FROM ads WHERE id = ?");
    $edit_ad->bind_param("i", $edit_id);
    $edit_ad->execute();
    $edit_ad = $edit_ad->get_result()->fetch_assoc();
    if ($edit_ad && $edit_ad['type'] === 'intro-ad') {
        $is_intro_ad = true;
    }
}

// Check if editing intro ad
if (isset($_GET['edit_intro'])) {
    $is_intro_ad = true;
    if ($intro_ad) {
        $edit_ad = $intro_ad;
    }
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Manage Ads</h1>
    <p class="text-gray-400">Create and manage advertisements</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<!-- Intro Ad Section -->
<div class="bg-gray-900 rounded-lg p-6 mb-8 border-2 border-yellow-600">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-2xl font-bold mb-2">Intro Ad Settings</h2>
            <p class="text-gray-400 text-sm">This ad plays to everyone (free and premium users) before the stream starts. Cannot be skipped.</p>
        </div>
        <?php if ($intro_ad): ?>
        <span class="px-3 py-1 rounded text-sm bg-green-900 text-green-200">Active</span>
        <?php else: ?>
        <span class="px-3 py-1 rounded text-sm bg-gray-700 text-gray-300">Not Set</span>
        <?php endif; ?>
    </div>
    
    <?php if ($intro_ad && !$is_intro_ad): ?>
    <div class="bg-gray-800 rounded p-4 mb-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="font-semibold"><?php echo htmlspecialchars($intro_ad['name']); ?></p>
                <p class="text-sm text-gray-400">Duration: <?php echo $intro_ad['duration']; ?>s</p>
            </div>
            <a href="?tab=ads&edit_intro=1" class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded text-sm">Edit Intro Ad</a>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($is_intro_ad || !$intro_ad): ?>
    <form method="POST" action="" enctype="multipart/form-data" id="introAdForm">
        <input type="hidden" name="type" value="intro-ad">
        <?php if ($edit_ad && $is_intro_ad): ?>
        <input type="hidden" name="id" value="<?php echo $edit_ad['id']; ?>">
        <?php endif; ?>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Intro Ad Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_ad['name'] ?? 'Intro Ad'); ?>" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Content Type *</label>
            <select name="content_type" id="intro_content_type" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required onchange="toggleContentFields('intro')">
                <option value="image" <?php echo ($edit_ad['content_type'] ?? 'html') === 'image' ? 'selected' : ''; ?>>Image</option>
                <option value="video" <?php echo ($edit_ad['content_type'] ?? 'html') === 'video' ? 'selected' : ''; ?>>Video</option>
                <option value="html" <?php echo ($edit_ad['content_type'] ?? 'html') === 'html' ? 'selected' : ''; ?>>Embedded HTML</option>
            </select>
        </div>
        
        <div class="mb-4" id="intro_logo_upload_section">
            <label class="block text-sm font-semibold mb-2">Upload Logo/Image/Video <?php echo ($edit_ad && $is_intro_ad) ? '(Optional - leave empty to keep current file)' : '*'; ?></label>
            <input type="file" name="logo_file" id="intro_logo_file" accept="image/*,video/*" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   onchange="previewFile('intro')" <?php echo ($edit_ad && $is_intro_ad) ? '' : 'required'; ?>>
            <p class="text-xs text-gray-400 mt-1">Supported: JPG, PNG, GIF, WEBP, SVG, MP4, WebM, OGG, MOV, AVI (Max 50MB)<?php echo ($edit_ad && $is_intro_ad) ? ' - Leave empty to keep existing file' : ''; ?></p>
            
            <?php if ($edit_ad && !empty($edit_ad['logo'])): ?>
            <div class="mt-4" id="intro_current_preview">
                <p class="text-sm text-gray-400 mb-2">Current:</p>
                <?php if (in_array(strtolower(pathinfo($edit_ad['logo'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])): ?>
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($edit_ad['logo']); ?>" alt="Current logo" 
                     class="max-w-xs max-h-48 object-contain bg-gray-800 rounded p-2">
                <?php else: ?>
                <video src="<?php echo BASE_URL . '/' . htmlspecialchars($edit_ad['logo']); ?>" 
                       class="max-w-xs max-h-48 bg-gray-800 rounded p-2" controls></video>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 hidden" id="intro_new_preview">
                <p class="text-sm text-gray-400 mb-2">Preview:</p>
                <div id="intro_preview_container"></div>
            </div>
        </div>
        
        <div class="mb-4" id="intro_html_content_section" style="display: none;">
            <label class="block text-sm font-semibold mb-2">Embedded HTML Code *</label>
            <textarea name="content" id="intro_html_content" rows="5" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                      placeholder="Paste your HTML embed code here (e.g., iframe, script tags, etc.)"><?php echo htmlspecialchars($edit_ad['content'] ?? ''); ?></textarea>
            <p class="text-xs text-gray-400 mt-1">Paste HTML embed code for ads (iframe, script tags, etc.)</p>
        </div>
        
        <div class="mb-4" id="intro_content_url_section">
            <label class="block text-sm font-semibold mb-2">Content URL (Optional)</label>
            <input type="url" name="content" id="intro_content_url" value="<?php echo htmlspecialchars($edit_ad['content'] ?? ''); ?>" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="URL for image/video (if not uploading file)">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Duration (seconds)</label>
                <input type="number" name="duration" value="<?php echo $edit_ad['duration'] ?? 0; ?>" min="0"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Start Date (Optional - leave empty for no expiry)</label>
                <input type="datetime-local" name="start_date" 
                       value="<?php echo $edit_ad['start_date'] ? date('Y-m-d\TH:i', strtotime($edit_ad['start_date'])) : ''; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">End Date (Optional - leave empty for no expiry)</label>
                <input type="datetime-local" name="end_date" 
                       value="<?php echo $edit_ad['end_date'] ? date('Y-m-d\TH:i', strtotime($edit_ad['end_date'])) : ''; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" <?php echo ($edit_ad['is_active'] ?? 1) ? 'checked' : ''; ?> 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
        </div>
        
        <input type="hidden" name="skipable" value="0">
        
        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 px-6 py-2 rounded">
            <?php echo ($edit_ad && $is_intro_ad) ? 'Update' : 'Set'; ?> Intro Ad
        </button>
        <?php if ($is_intro_ad): ?>
        <a href="?tab=ads" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<!-- Regular Ads Section -->
<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h2 class="text-2xl font-bold mb-4"><?php echo ($edit_ad && !$is_intro_ad) ? 'Edit' : 'Add'; ?> Ad</h2>
    <form method="POST" action="" enctype="multipart/form-data" id="adForm">
        <?php if ($edit_ad && !$is_intro_ad): ?>
        <input type="hidden" name="id" value="<?php echo $edit_ad['id']; ?>">
        <?php endif; ?>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Ad Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($edit_ad['name'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Ad Type *</label>
                <select name="type" id="ad_type_select" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required onchange="toggleSkipableOption()">
                    <option value="pre-roll" <?php echo ($edit_ad['type'] ?? 'pre-roll') === 'pre-roll' ? 'selected' : ''; ?>>Pre-roll</option>
                    <option value="mid-roll" <?php echo ($edit_ad['type'] ?? '') === 'mid-roll' ? 'selected' : ''; ?>>Mid-roll</option>
                    <option value="post-roll" <?php echo ($edit_ad['type'] ?? '') === 'post-roll' ? 'selected' : ''; ?>>Post-roll</option>
                    <option value="loop" <?php echo ($edit_ad['type'] ?? '') === 'loop' ? 'selected' : ''; ?>>Loop (plays repeatedly every N seconds during playback)</option>
                    <option value="banner" <?php echo ($edit_ad['type'] ?? '') === 'banner' ? 'selected' : ''; ?>>Banner</option>
                    <option value="popup" <?php echo ($edit_ad['type'] ?? '') === 'popup' ? 'selected' : ''; ?>>Popup</option>
                </select>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Content Type *</label>
            <select name="content_type" id="content_type" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required onchange="toggleContentFields('regular')">
                <option value="image" <?php echo ($edit_ad['content_type'] ?? 'html') === 'image' ? 'selected' : ''; ?>>Image</option>
                <option value="video" <?php echo ($edit_ad['content_type'] ?? 'html') === 'video' ? 'selected' : ''; ?>>Video</option>
                <option value="html" <?php echo ($edit_ad['content_type'] ?? 'html') === 'html' ? 'selected' : ''; ?>>Embedded HTML</option>
            </select>
        </div>
        
        <div class="mb-4" id="logo_upload_section">
            <label class="block text-sm font-semibold mb-2">Upload Logo/Image/Video <?php echo ($edit_ad && !$is_intro_ad) ? '(Optional - leave empty to keep current file)' : '*'; ?></label>
            <input type="file" name="logo_file" id="logo_file" accept="image/*,video/*" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   onchange="previewFile('regular')" <?php echo ($edit_ad && !$is_intro_ad) ? '' : 'required'; ?>>
            <p class="text-xs text-gray-400 mt-1">Supported: JPG, PNG, GIF, WEBP, SVG, MP4, WebM, OGG, MOV, AVI (Max 50MB)<?php echo ($edit_ad && !$is_intro_ad) ? ' - Leave empty to keep existing file' : ''; ?></p>
            
            <?php if ($edit_ad && !empty($edit_ad['logo'])): ?>
            <div class="mt-4" id="current_preview">
                <p class="text-sm text-gray-400 mb-2">Current:</p>
                <?php if (in_array(strtolower(pathinfo($edit_ad['logo'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])): ?>
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($edit_ad['logo']); ?>" alt="Current logo" 
                     class="max-w-xs max-h-48 object-contain bg-gray-800 rounded p-2">
                <?php else: ?>
                <video src="<?php echo BASE_URL . '/' . htmlspecialchars($edit_ad['logo']); ?>" 
                       class="max-w-xs max-h-48 bg-gray-800 rounded p-2" controls></video>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="mt-4 hidden" id="new_preview">
                <p class="text-sm text-gray-400 mb-2">Preview:</p>
                <div id="preview_container"></div>
            </div>
        </div>
        
        <div class="mb-4" id="html_content_section" style="display: none;">
            <label class="block text-sm font-semibold mb-2">Embedded HTML Code *</label>
            <textarea name="content" id="html_content" rows="5" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                      placeholder="Paste your HTML embed code here (e.g., iframe, script tags, etc.)"><?php echo htmlspecialchars($edit_ad['content'] ?? ''); ?></textarea>
            <p class="text-xs text-gray-400 mt-1">Paste HTML embed code for ads (iframe, script tags, etc.)</p>
        </div>
        
        <div class="mb-4" id="content_url_section">
            <label class="block text-sm font-semibold mb-2">Content URL (Optional)</label>
            <input type="url" name="content" id="content_url" value="<?php echo htmlspecialchars($edit_ad['content'] ?? ''); ?>" 
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="URL for image/video (if not uploading file)">
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Duration (seconds) - How long the ad plays</label>
                <input type="number" name="duration" value="<?php echo $edit_ad['duration'] ?? 0; ?>" min="0"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Start Date (Optional - leave empty for no expiry)</label>
                <input type="datetime-local" name="start_date" 
                       value="<?php echo $edit_ad['start_date'] ? date('Y-m-d\TH:i', strtotime($edit_ad['start_date'])) : ''; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">End Date (Optional - leave empty for no expiry)</label>
                <input type="datetime-local" name="end_date" 
                       value="<?php echo $edit_ad['end_date'] ? date('Y-m-d\TH:i', strtotime($edit_ad['end_date'])) : ''; ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        
        <!-- Show loop interval field dynamically when type is loop -->
        <div id="loop_interval_field" style="display: none;" class="mb-4">
            <label class="block text-sm font-semibold mb-2">Loop Interval (seconds) - How often to show this ad</label>
            <input type="number" name="loop_interval" value="<?php echo $edit_ad['loop_interval'] ?? 60; ?>" min="5"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="e.g., 60 for every 1 minute">
            <p class="text-xs text-gray-400 mt-1">How often the ad appears during playback (e.g., 60 = every 1 minute). Duration above is how long the ad plays.</p>
        </div>
        
        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" <?php echo ($edit_ad['is_active'] ?? 1) ? 'checked' : ''; ?> 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
        </div>
        
        <div class="mb-4" id="skipable_section">
            <label class="flex items-center">
                <input type="checkbox" name="skipable" id="skipable_checkbox" <?php echo ($edit_ad['skipable'] ?? 1) ? 'checked' : ''; ?> 
                       class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Allow Skip (Users can skip this ad)</span>
            </label>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <?php echo ($edit_ad && !$is_intro_ad) ? 'Update' : 'Add'; ?> Ad
        </button>
        <?php if ($edit_ad && !$is_intro_ad): ?>
        <a href="?tab=ads" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">All Ads (Regular Ads Only)</h2>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800">
                    <th class="text-left p-3">Logo</th>
                    <th class="text-left p-3">Name</th>
                    <th class="text-left p-3">Type</th>
                    <th class="text-left p-3">Content Type</th>
                    <th class="text-left p-3">Duration</th>
                    <th class="text-left p-3">Status</th>
                    <th class="text-left p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ads as $ad): ?>
                <tr class="border-b border-gray-800 hover:bg-gray-800">
                    <td class="p-3">
                        <?php if (!empty($ad['logo'])): ?>
                            <?php if (in_array(strtolower(pathinfo($ad['logo'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($ad['logo']); ?>" alt="Logo" 
                                 class="w-16 h-16 object-contain bg-gray-800 rounded">
                            <?php else: ?>
                            <video src="<?php echo BASE_URL . '/' . htmlspecialchars($ad['logo']); ?>" 
                                   class="w-16 h-16 object-contain bg-gray-800 rounded" muted></video>
                            <?php endif; ?>
                        <?php else: ?>
                        <span class="text-gray-500">No logo</span>
                        <?php endif; ?>
                    </td>
                    <td class="p-3"><?php echo htmlspecialchars($ad['name']); ?></td>
                    <td class="p-3"><?php echo htmlspecialchars($ad['type']); ?></td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs bg-blue-900 text-blue-200">
                            <?php echo htmlspecialchars($ad['content_type'] ?? 'html'); ?>
                        </span>
                    </td>
                    <td class="p-3"><?php echo $ad['duration']; ?>s</td>
                    <td class="p-3">
                        <span class="px-2 py-1 rounded text-xs <?php echo $ad['is_active'] ? 'bg-green-900 text-green-200' : 'bg-gray-700 text-gray-300'; ?>">
                            <?php echo $ad['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td class="p-3">
                        <a href="?tab=ads&edit=<?php echo $ad['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">Edit</a>
                        <a href="?tab=ads&delete=<?php echo $ad['id']; ?>" 
                           onclick="return confirm('Are you sure?')" 
                           class="text-red-400 hover:text-red-300">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleContentFields(type) {
    type = type || 'regular';
    const prefix = type === 'intro' ? 'intro_' : '';
    const contentTypeEl = document.getElementById(prefix + 'content_type');
    if (!contentTypeEl) return;
    
    const contentType = contentTypeEl.value;
    const logoSection = document.getElementById(prefix + 'logo_upload_section');
    const htmlSection = document.getElementById(prefix + 'html_content_section');
    const urlSection = document.getElementById(prefix + 'content_url_section');
    const logoFile = document.getElementById(prefix + 'logo_file');
    const htmlContent = document.getElementById(prefix + 'html_content');
    const contentUrl = document.getElementById(prefix + 'content_url');
    
    // Check if editing and has existing logo - don't require file upload
    const isEditing = <?php echo ($edit_ad && !empty($edit_ad['logo'])) ? 'true' : 'false'; ?>;
    const isIntroEditing = <?php echo ($edit_ad && $is_intro_ad && !empty($edit_ad['logo'])) ? 'true' : 'false'; ?>;
    const hasExistingLogo = (type === 'intro' && isIntroEditing) || (type !== 'intro' && isEditing);
    
    if (contentType === 'html') {
        if (logoSection) logoSection.style.display = 'none';
        if (htmlSection) htmlSection.style.display = 'block';
        if (urlSection) urlSection.style.display = 'none';
        if (logoFile) logoFile.removeAttribute('required');
        if (htmlContent) htmlContent.setAttribute('required', 'required');
        if (contentUrl) contentUrl.removeAttribute('required');
    } else {
        if (logoSection) logoSection.style.display = 'block';
        if (htmlSection) htmlSection.style.display = 'none';
        if (urlSection) urlSection.style.display = 'block';
        
        // Only require file if not editing or if editing but no existing logo
        if (logoFile) {
            if (!hasExistingLogo) {
                logoFile.setAttribute('required', 'required');
            } else {
                logoFile.removeAttribute('required');
            }
        }
        if (htmlContent) htmlContent.removeAttribute('required');
        if (contentUrl) contentUrl.removeAttribute('required');
    }
}

function previewFile() {
    const fileInput = document.getElementById('logo_file');
    const previewContainer = document.getElementById('preview_container');
    const newPreview = document.getElementById('new_preview');
    const currentPreview = document.getElementById('current_preview');
    
    if (fileInput.files && fileInput.files[0]) {
        const file = fileInput.files[0];
        const fileType = file.type;
        
        // Hide current preview if editing
        if (currentPreview) {
            currentPreview.style.display = 'none';
        }
        
        // Show new preview
        newPreview.classList.remove('hidden');
        previewContainer.innerHTML = '';
        
        if (fileType.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'max-w-xs max-h-48 object-contain bg-gray-800 rounded p-2';
                img.alt = 'Preview';
                previewContainer.appendChild(img);
            };
            reader.readAsDataURL(file);
        } else if (fileType.startsWith('video/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const video = document.createElement('video');
                video.src = e.target.result;
                video.className = 'max-w-xs max-h-48 bg-gray-800 rounded p-2';
                video.controls = true;
                video.muted = true;
                previewContainer.appendChild(video);
            };
            reader.readAsDataURL(file);
        }
    }
}

function toggleSkipableOption() {
    // Skipable option is always available for regular ads
    const skipableSection = document.getElementById('skipable_section');
    const skipableCheckbox = document.getElementById('skipable_checkbox');
    const loopIntervalField = document.getElementById('loop_interval_field');
    const typeSelect = document.getElementById('ad_type_select');
    
    if (!skipableSection || !skipableCheckbox) return;
    
    skipableCheckbox.disabled = false;
    skipableSection.style.opacity = '1';
    
    // Show/hide loop interval field when type is 'loop'
    if (loopIntervalField && typeSelect) {
        if (typeSelect.value === 'loop') {
            loopIntervalField.style.display = 'block';
        } else {
            loopIntervalField.style.display = 'none';
        }
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize both forms
    const introContentType = document.getElementById('intro_content_type');
    const regularContentType = document.getElementById('content_type');
    
    if (introContentType) {
        toggleContentFields('intro');
    }
    if (regularContentType) {
        toggleContentFields('regular');
    }
    toggleSkipableOption();
});
</script>
