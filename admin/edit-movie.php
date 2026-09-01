<?php
/**
 * Admin Panel - Edit Movie (separate page, like edit-channel).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/movie_admin.php';

$page_title = 'Edit Movie';
$conn = getDBConnection();
ensureMoviesSchema($conn);

$message = '';
$message_type = '';

$movie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($movie_id <= 0) {
    if (headers_sent()) {
        echo '<script>window.location.href="?tab=movies";</script>';
    } else {
        header('Location: ?tab=movies');
    }
    exit;
}

$edit_movie = prepareMovieForForm($conn, $movie_id);
if (!$edit_movie) {
    if (headers_sent()) {
        echo '<script>window.location.href="?tab=movies";</script>';
    } else {
        header('Location: ?tab=movies');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = saveMovieFromRequest($conn, $movie_id);
    if ($result['success']) {
        $message = 'Movie updated successfully';
        $message_type = 'success';
    } else {
        $message = $result['message'];
        $message_type = 'error';
    }
    $edit_movie = prepareMovieForForm($conn, $movie_id);
}

if (isset($_GET['success'])) {
    $message = 'Movie updated successfully';
    $message_type = 'success';
}

$categories = getAllCategories($conn);
$movie_form_action = '?tab=edit-movie&id=' . $movie_id;
$movie_form_mode = 'edit';
?>

<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Edit Movie</h1>
    <p class="text-gray-400">Update movie details, sources, and download links</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($edit_movie['title']); ?></h2>
        <a href="?tab=movies" class="text-gray-400 hover:text-white"><i class="fas fa-arrow-left mr-1"></i> Back to Movies</a>
    </div>
    <?php include __DIR__ . '/includes/movie-form-fields.php'; ?>
</div>

<?php include __DIR__ . '/includes/movie-form-scripts.php'; ?>
