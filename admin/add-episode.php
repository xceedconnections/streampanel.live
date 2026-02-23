<?php
/**
 * Add Episode - Simplified Form
 * Only requires: Season Number, Episode Number, Episode Title
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();

$show_id = isset($_GET['show_id']) ? intval($_GET['show_id']) : 0;
$episode_id = isset($_GET['episode_id']) ? intval($_GET['episode_id']) : 0;

$page_title = $episode_id ? "Edit Episode" : "Add Episode";
$message = '';
$message_type = '';
$edit_episode = null;

// Get TV show info
$show = null;
if ($show_id) {
    $stmt = $conn->prepare("SELECT * FROM tv_shows WHERE id = ?");
    $stmt->bind_param("i", $show_id);
    $stmt->execute();
    $show = $stmt->get_result()->fetch_assoc();
}

if (!$show) {
    header("Location: tv-shows.php");
    exit;
}

// Get episode for editing if episode_id is provided
if ($episode_id) {
    $stmt = $conn->prepare("SELECT * FROM tv_episodes WHERE id = ? AND tv_show_id = ?");
    $stmt->bind_param("ii", $episode_id, $show_id);
    $stmt->execute();
    $edit_episode = $stmt->get_result()->fetch_assoc();
    
    if (!$edit_episode) {
        header("Location: tv-shows.php?show_episodes=" . $show_id);
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['episode_id']) ? intval($_POST['episode_id']) : null;
    $season = intval($_POST['season_number'] ?? 1);
    $episode_num = intval($_POST['episode_number'] ?? 1);
    $episode_title = sanitize($_POST['episode_title'] ?? '');
    $thumbnail = sanitize($_POST['thumbnail'] ?? '');
    
    if (empty($episode_title)) {
        $message = 'Episode title is required';
        $message_type = 'error';
    } else {
        // Handle thumbnail upload
        if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/tv-episode-thumbnails/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed_extensions)) {
                $file_name = 'episode_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $file_path)) {
                    // Delete old thumbnail if it was in our uploads folder
                    if ($id) {
                        $existing_stmt = $conn->prepare("SELECT thumbnail FROM tv_episodes WHERE id = ?");
                        $existing_stmt->bind_param("i", $id);
                        $existing_stmt->execute();
                        $existing_ep = $existing_stmt->get_result()->fetch_assoc();
                        if (!empty($existing_ep['thumbnail']) && strpos($existing_ep['thumbnail'], 'uploads/tv-episode-thumbnails/') !== false) {
                            $old_file_path = str_replace(BASE_URL . '/', __DIR__ . '/../', $existing_ep['thumbnail']);
                            if (file_exists($old_file_path)) {
                                @unlink($old_file_path);
                            }
                        }
                    }
                    $thumbnail = BASE_URL . '/uploads/tv-episode-thumbnails/' . $file_name;
                }
            }
        }
        
        if ($id) {
            // Update existing episode
            // Keep existing sources and thumbnail if not updated
            $existing_stmt = $conn->prepare("SELECT sources, thumbnail FROM tv_episodes WHERE id = ?");
            $existing_stmt->bind_param("i", $id);
            $existing_stmt->execute();
            $existing = $existing_stmt->get_result()->fetch_assoc();
            $sourcesJson = $existing['sources'] ?? '[]';
            // Only update thumbnail if a new one was uploaded
            if (empty($thumbnail)) {
                $thumbnail = $existing['thumbnail'] ?? '';
            }
            
            $stmt = $conn->prepare("UPDATE tv_episodes SET season_number=?, episode_number=?, title=?, thumbnail=?, sources=? WHERE id=? AND tv_show_id=?");
            $stmt->bind_param("iisssii", $season, $episode_num, $episode_title, $thumbnail, $sourcesJson, $id, $show_id);
            $stmt->execute();
            
            $message = 'Episode updated successfully';
            $message_type = 'success';
            
            // Redirect back to episode list
            header("Location: tv-shows.php?show_episodes=" . $show_id);
            exit;
        } else {
            // Check for duplicate season/episode
            $check_stmt = $conn->prepare("SELECT id FROM tv_episodes WHERE tv_show_id = ? AND season_number = ? AND episode_number = ?");
            $check_stmt->bind_param("iii", $show_id, $season, $episode_num);
            $check_stmt->execute();
            $existing = $check_stmt->get_result()->fetch_assoc();
            
            if ($existing) {
                $message = 'Episode already exists for this season/episode number';
                $message_type = 'error';
            } else {
                // Insert episode with empty sources (sources will be added separately)
                $sourcesJson = '[]';
                $stmt = $conn->prepare("INSERT INTO tv_episodes (tv_show_id, season_number, episode_number, title, thumbnail, sources) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisss", $show_id, $season, $episode_num, $episode_title, $thumbnail, $sourcesJson);
                $stmt->execute();
                
                $message = 'Episode added successfully! You can now add streaming sources.';
                $message_type = 'success';
                
                // Redirect to add sources page
                header("Location: add-sources.php?episode_id=" . $conn->insert_id . "&show_id=" . $show_id);
                exit;
            }
        }
    }
}

include 'includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="edit-tv-show.php?id=<?php echo $show_id; ?>" class="text-blue-400 hover:text-blue-300 mb-4 inline-block">
            <i class="fas fa-arrow-left mr-2"></i>Back to TV Show
        </a>
        <h1 class="text-4xl font-bold mb-2"><?php echo $edit_episode ? 'Edit Episode' : 'Add New Episode'; ?></h1>
        <p class="text-gray-400">TV Show: <strong><?php echo htmlspecialchars($show['title']); ?></strong></p>
    </div>

    <?php if ($message): ?>
    <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div class="bg-gray-900 rounded-lg p-6 max-w-2xl">
        <h2 class="text-2xl font-bold mb-6">Episode Information</h2>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <?php if ($edit_episode): ?>
            <input type="hidden" name="episode_id" value="<?php echo $edit_episode['id']; ?>">
            <?php endif; ?>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold mb-2">Season Number *</label>
                    <input type="number" name="season_number" value="<?php echo htmlspecialchars($_POST['season_number'] ?? ($edit_episode['season_number'] ?? '1')); ?>" 
                           min="1" required
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <p class="text-xs text-gray-400 mt-1">e.g., 1, 2, 3...</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">Episode Number *</label>
                    <input type="number" name="episode_number" value="<?php echo htmlspecialchars($_POST['episode_number'] ?? ($edit_episode['episode_number'] ?? '1')); ?>" 
                           min="1" required
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <p class="text-xs text-gray-400 mt-1">e.g., 1, 2, 3...</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">Episode Title *</label>
                    <input type="text" name="episode_title" value="<?php echo htmlspecialchars($_POST['episode_title'] ?? ($edit_episode['title'] ?? '')); ?>" 
                           required
                           placeholder="e.g., The Pilot Episode"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                </div>

                <div>
                    <label class="block text-sm font-semibold mb-2">Episode Thumbnail</label>
                    <div class="space-y-3">
                        <?php if ($edit_episode && !empty($edit_episode['thumbnail'])): ?>
                        <div class="mb-2">
                            <p class="text-xs text-gray-400 mb-2">Current Thumbnail:</p>
                            <img src="<?php echo htmlspecialchars($edit_episode['thumbnail']); ?>" 
                                 alt="Current thumbnail" 
                                 class="max-w-xs h-auto rounded border border-gray-600">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="thumbnail_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-netflix-red file:text-white hover:file:bg-red-700">
                        <p class="text-xs text-gray-400 mt-1">
                            Upload an image for this episode. If not provided, the TV show's poster will be used as fallback.
                            <br>Accepted formats: JPG, PNG, GIF, WEBP
                        </p>
                        <?php if ($edit_episode && !empty($edit_episode['thumbnail'])): ?>
                        <div class="mt-2">
                            <label class="flex items-center text-sm">
                                <input type="text" name="thumbnail" value="<?php echo htmlspecialchars($edit_episode['thumbnail']); ?>" 
                                       placeholder="Or enter image URL"
                                       class="flex-1 bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                            </label>
                            <p class="text-xs text-gray-400 mt-1">Or enter a direct image URL instead of uploading</p>
                        </div>
                        <?php else: ?>
                        <input type="text" name="thumbnail" value="<?php echo htmlspecialchars($_POST['thumbnail'] ?? ''); ?>" 
                               placeholder="Or enter image URL"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm mt-2">
                        <p class="text-xs text-gray-400 mt-1">Or enter a direct image URL instead of uploading</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex gap-4">
                <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
                    <i class="fas fa-<?php echo $edit_episode ? 'save' : 'plus'; ?> mr-2"></i><?php echo $edit_episode ? 'Update Episode' : 'Add Episode'; ?>
                </button>
                <a href="tv-shows.php?show_episodes=<?php echo $show_id; ?>" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <?php if (!$edit_episode): ?>
    <div class="mt-6 bg-blue-900 bg-opacity-30 border border-blue-700 rounded-lg p-4 max-w-2xl">
        <p class="text-sm text-blue-200">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Note:</strong> After adding the episode, you'll be redirected to add streaming sources for it.
        </p>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
