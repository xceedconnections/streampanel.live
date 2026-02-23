<?php
/**
 * Add Streaming Sources to Episode
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$page_title = "Add Sources to Episode";
$conn = getDBConnection();

$episode_id = isset($_GET['episode_id']) ? intval($_GET['episode_id']) : 0;
$show_id = isset($_GET['show_id']) ? intval($_GET['show_id']) : 0;
$message = '';
$message_type = '';

// Get episode info
$episode = null;
$show = null;
if ($episode_id) {
    $stmt = $conn->prepare("SELECT * FROM tv_episodes WHERE id = ?");
    $stmt->bind_param("i", $episode_id);
    $stmt->execute();
    $episode = $stmt->get_result()->fetch_assoc();
    
    if ($episode) {
        $show_id = $episode['tv_show_id'];
        $stmt = $conn->prepare("SELECT * FROM tv_shows WHERE id = ?");
        $stmt->bind_param("i", $show_id);
        $stmt->execute();
        $show = $stmt->get_result()->fetch_assoc();
    }
}

if (!$episode || !$show) {
    header("Location: tv-shows.php");
    exit;
}

// Parse existing sources
$episode['sources'] = parseSources($episode['sources'] ?? '[]');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $episode_sources = [];
    if (isset($_POST['sources']) && is_array($_POST['sources'])) {
        foreach ($_POST['sources'] as $source) {
            if (!empty($source['url'])) {
                $raw_type = isset($source['type']) ? $source['type'] : '';
                $source_type = !empty($raw_type) ? trim($raw_type) : 'embed';
                
                if (in_array($source_type, ['embed', 'html-embed', 'iframe-only'], true)) {
                    $raw_url = html_entity_decode(trim($source['url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                } else {
                    $raw_url = sanitize($source['url'] ?? '');
                }
                
                $episode_sources[] = [
                    'id' => $source['id'] ?? 'src_' . time() . '_' . uniqid(),
                    'label' => sanitize($source['label'] ?? ''),
                    'url' => $raw_url,
                    'type' => $source_type,
                    'quality' => sanitize($source['quality'] ?? 'Auto'),
                    'language' => sanitize($source['language'] ?? 'English'),
                    'priority' => intval($source['priority'] ?? 999),
                    'isActive' => isset($source['isActive']) ? true : false,
                    'isVisible' => isset($source['isVisible']) ? true : false
                ];
            }
        }
    }
    
    $sourcesJson = encodeSources($episode_sources);
    $stmt = $conn->prepare("UPDATE tv_episodes SET sources = ? WHERE id = ?");
    $stmt->bind_param("si", $sourcesJson, $episode_id);
    $stmt->execute();
    
    $message = 'Sources updated successfully!';
    $message_type = 'success';
    
    // Refresh episode data
    $stmt = $conn->prepare("SELECT * FROM tv_episodes WHERE id = ?");
    $stmt->bind_param("i", $episode_id);
    $stmt->execute();
    $episode = $stmt->get_result()->fetch_assoc();
    $episode['sources'] = parseSources($episode['sources'] ?? '[]');
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="edit-tv-show.php?id=<?php echo $show_id; ?>" class="text-blue-400 hover:text-blue-300 mb-4 inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Back to TV Show
        </a>
        <h1 class="text-4xl font-bold mb-2">Add Streaming Sources</h1>
        <p class="text-gray-400">
            TV Show: <strong><?php echo htmlspecialchars($show['title']); ?></strong> | 
            Episode: <strong>S<?php echo $episode['season_number']; ?>E<?php echo $episode['episode_number']; ?> - <?php echo htmlspecialchars($episode['title']); ?></strong>
        </p>
    </div>

    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="bg-gray-900 rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-2xl font-bold">Streaming Sources</h2>
                <button type="button" onclick="addSource()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add Source
                </button>
            </div>

            <div id="sources-container" class="space-y-4">
                <?php if (!empty($episode['sources'])): ?>
                    <?php foreach ($episode['sources'] as $idx => $source): ?>
                        <div class="source-item bg-gray-800 rounded-lg p-4 border border-gray-700" data-index="<?php echo $idx; ?>">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold">Source #<?php echo $idx + 1; ?></h4>
                                <button type="button" onclick="removeSource(this)" class="text-red-400 hover:text-red-300">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Source Label *</label>
                                    <input type="text" name="sources[<?php echo $idx; ?>][label]" value="<?php echo htmlspecialchars($source['label'] ?? ''); ?>" 
                                           placeholder="e.g., Server 1, HD Quality" required
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                                    <input type="hidden" name="sources[<?php echo $idx; ?>][id]" value="<?php echo htmlspecialchars($source['id'] ?? 'src_' . time() . '_' . uniqid()); ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Source Type *</label>
                                    <select name="sources[<?php echo $idx; ?>][type]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                                        <optgroup label="Social Media">
                                            <option value="youtube" <?php echo ($source['type'] ?? '') === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                                            <option value="dailymotion" <?php echo ($source['type'] ?? '') === 'dailymotion' ? 'selected' : ''; ?>>Dailymotion</option>
                                            <option value="vimeo" <?php echo ($source['type'] ?? '') === 'vimeo' ? 'selected' : ''; ?>>Vimeo</option>
                                        </optgroup>
                                        <optgroup label="Streaming Protocols">
                                            <option value="m3u8" <?php echo ($source['type'] ?? '') === 'm3u8' ? 'selected' : ''; ?>>M3U8 (HLS)</option>
                                            <option value="hls" <?php echo ($source['type'] ?? '') === 'hls' ? 'selected' : ''; ?>>HLS Stream</option>
                                            <option value="dash" <?php echo ($source['type'] ?? '') === 'dash' ? 'selected' : ''; ?>>MPEG-DASH</option>
                                        </optgroup>
                                        <optgroup label="Direct & Embed">
                                            <option value="direct" <?php echo ($source['type'] ?? '') === 'direct' ? 'selected' : ''; ?>>Direct MP4/Video</option>
                                            <option value="embed" <?php echo ($source['type'] ?? '') === 'embed' ? 'selected' : ''; ?>>Iframe Embed</option>
                                            <option value="html-embed" <?php echo ($source['type'] ?? '') === 'html-embed' ? 'selected' : ''; ?>>HTML Embed Code</option>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold mb-1">Stream URL *</label>
                                    <input type="text" name="sources[<?php echo $idx; ?>][url]" value="<?php echo htmlspecialchars($source['url'] ?? ''); ?>" 
                                           placeholder="https://example.com/video.m3u8" required
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold mb-1">Priority</label>
                                    <input type="number" name="sources[<?php echo $idx; ?>][priority]" value="<?php echo $source['priority'] ?? 999; ?>" min="0"
                                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                                           placeholder="0 = Default">
                                    <p class="text-xs text-gray-400 mt-1"><strong>0</strong> = Default (plays first)</p>
                                </div>
                                <div class="flex items-center gap-4 pt-2">
                                    <label class="flex items-center text-sm cursor-pointer">
                                        <input type="checkbox" name="sources[<?php echo $idx; ?>][isActive]" <?php echo ($source['isActive'] ?? true) ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                                        <span>Active</span>
                                    </label>
                                    <label class="flex items-center text-sm cursor-pointer">
                                        <input type="checkbox" name="sources[<?php echo $idx; ?>][isVisible]" <?php echo ($source['isVisible'] ?? true) ? 'checked' : ''; ?> 
                                               class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                                        <span>Visible</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
                    <i class="fas fa-save mr-2"></i>Save Sources
                </button>
                <a href="edit-tv-show.php?id=<?php echo $show_id; ?>" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>

<script>
let sourceIndex = <?php echo count($episode['sources'] ?? []); ?>;

function addSource() {
    const container = document.getElementById('sources-container');
    const sourceHtml = `
        <div class="source-item bg-gray-800 rounded-lg p-4 border border-gray-700" data-index="${sourceIndex}">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold">Source #${sourceIndex + 1}</h4>
                <button type="button" onclick="removeSource(this)" class="text-red-400 hover:text-red-300">
                    <i class="fas fa-trash"></i> Remove
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm font-semibold mb-1">Source Label *</label>
                    <input type="text" name="sources[${sourceIndex}][label]" placeholder="e.g., Server 1" required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                    <input type="hidden" name="sources[${sourceIndex}][id]" value="src_${Date.now()}_${Math.random().toString(36).substr(2, 9)}">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Source Type *</label>
                    <select name="sources[${sourceIndex}][type]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                        <optgroup label="Social Media">
                            <option value="youtube">YouTube</option>
                            <option value="dailymotion">Dailymotion</option>
                            <option value="vimeo">Vimeo</option>
                        </optgroup>
                        <optgroup label="Streaming Protocols">
                            <option value="m3u8">M3U8 (HLS)</option>
                            <option value="hls">HLS Stream</option>
                            <option value="dash">MPEG-DASH</option>
                        </optgroup>
                        <optgroup label="Direct & Embed">
                            <option value="direct">Direct MP4/Video</option>
                            <option value="embed">Iframe Embed</option>
                            <option value="html-embed">HTML Embed Code</option>
                        </optgroup>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold mb-1">Stream URL *</label>
                    <input type="text" name="sources[${sourceIndex}][url]" placeholder="https://example.com/video.m3u8" required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Priority</label>
                    <input type="number" name="sources[${sourceIndex}][priority]" value="999" min="0"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm"
                           placeholder="0 = Default">
                    <p class="text-xs text-gray-400 mt-1"><strong>0</strong> = Default (plays first)</p>
                </div>
                <div class="flex items-center gap-4 pt-2">
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="checkbox" name="sources[${sourceIndex}][isActive]" checked
                               class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                        <span>Active</span>
                    </label>
                    <label class="flex items-center text-sm cursor-pointer">
                        <input type="checkbox" name="sources[${sourceIndex}][isVisible]" checked
                               class="w-4 h-4 text-netflix-red bg-gray-600 border-gray-500 rounded mr-2">
                        <span>Visible</span>
                    </label>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', sourceHtml);
    sourceIndex++;
}

function removeSource(btn) {
    if (confirm('Are you sure you want to remove this source?')) {
        btn.closest('.source-item').remove();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
