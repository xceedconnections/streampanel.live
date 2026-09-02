<?php
/**
 * Shared admin helpers for movie add/edit forms.
 */
require_once __DIR__ . '/../../includes/movie_helpers.php';

function saveMovieFromRequest($conn, ?int $movieId = null): array
{
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $thumbnail = sanitize($_POST['thumbnail'] ?? '');
    $poster = sanitize($_POST['poster'] ?? '');
    $duration = intval($_POST['duration'] ?? 0);
    $release_year = intval($_POST['release_year'] ?? date('Y'));
    $rating = floatval($_POST['rating'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    if ($category_id <= 0) {
        $category_id = 0; // never bind null as int (corrupts later fields on some PHP/mysqli)
    }
    $featured = isset($_POST['featured']) ? (int) ((string) $_POST['featured'] === '1') : 0;
    $is_active = isset($_POST['is_active']) ? (int) ((string) $_POST['is_active'] === '1') : 0;
    $is_free = isset($_POST['is_free']) ? (int) ((string) $_POST['is_free'] === '1') : 0;
    $is_premium = isset($_POST['is_premium']) ? (int) ((string) $_POST['is_premium'] === '1') : 0;
    $show_in_slider = isset($_POST['show_in_slider']) ? (int) ((string) $_POST['show_in_slider'] === '1') : 0;
    $tmdb_id = !empty($_POST['tmdb_id']) ? intval($_POST['tmdb_id']) : 0;
    $backdrop = sanitize($_POST['backdrop'] ?? '');
    $trailer_url = sanitize($_POST['trailer_url'] ?? '');
    $director = sanitize($_POST['director'] ?? '');
    $quality_label = sanitize($_POST['quality_label'] ?? '');
    $tags = encodeMovieTagsInput($_POST['tags'] ?? '');
    $cast_data = $_POST['cast_data'] ?? '[]';
    $genres = $_POST['genres'] ?? '[]';

    if (!is_string($cast_data) || ($cast_data !== '' && json_decode($cast_data) === null && json_last_error() !== JSON_ERROR_NONE)) {
        $cast_data = '[]';
    }
    if (!is_string($genres) || ($genres !== '' && json_decode($genres) === null && json_last_error() !== JSON_ERROR_NONE)) {
        $genres = '[]';
    }

    if (isset($_FILES['thumbnail_file']) && $_FILES['thumbnail_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = dirname(__DIR__, 2) . '/uploads/tv-show-logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_extension = strtolower(pathinfo($_FILES['thumbnail_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $file_name = 'movie_logo_' . time() . '_' . uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['thumbnail_file']['tmp_name'], $file_path)) {
                if ($movieId && !empty($thumbnail) && strpos($thumbnail, 'uploads/tv-show-logos/') !== false) {
                    $old_file_path = str_replace(BASE_URL . '/', dirname(__DIR__, 2) . '/', $thumbnail);
                    if (file_exists($old_file_path)) {
                        @unlink($old_file_path);
                    }
                }
                $thumbnail = BASE_URL . '/uploads/tv-show-logos/' . $file_name;
            }
        }
    }

    $sources = [];
    if (isset($_POST['sources']) && is_array($_POST['sources'])) {
        foreach ($_POST['sources'] as $idx => $source) {
            $sourceType = trim($source['type'] ?? 'embed');
            $rawUrl = trim($source['url'] ?? '');

            if ($rawUrl === '') {
                continue;
            }

            if (in_array($sourceType, ['html-embed', 'html', 'embed', 'iframe-only'], true) && isset($rawUrl[0]) && $rawUrl[0] === '<') {
                $sourceUrl = $rawUrl;
            } else {
                $sourceUrl = sanitize($rawUrl);
            }
            $sources[] = [
                'id' => $source['id'] ?? 'src_' . time() . '_' . uniqid(),
                'label' => sanitize($source['label'] ?? ''),
                'url' => $sourceUrl,
                'type' => preg_replace('/[^a-z0-9_-]/i', '', $sourceType) ?: 'embed',
                'quality' => sanitize($source['quality'] ?? 'Auto'),
                'language' => sanitize($source['language'] ?? 'English'),
                'priority' => intval($source['priority'] ?? 999),
                'isActive' => isset($source['isActive']),
                'isVisible' => isset($source['isVisible']),
            ];
        }
    }
    $sourcesJson = encodeSources($sources);

    $download_links = [];
    if (isset($_POST['download_links']) && is_array($_POST['download_links'])) {
        foreach ($_POST['download_links'] as $link) {
            if (!empty($link['url'])) {
                $download_links[] = [
                    'id' => $link['id'] ?? 'dl_' . time() . '_' . uniqid(),
                    'label' => sanitize($link['label'] ?? 'Download'),
                    'url' => sanitize($link['url'] ?? ''),
                    'quality' => sanitize($link['quality'] ?? ''),
                    'size' => sanitize($link['size'] ?? ''),
                    'isActive' => isset($link['isActive']),
                ];
            }
        }
    }
    $downloadLinksJson = encodeDownloadLinks($download_links);
    $slug = getUniqueSlug($conn, 'movies', $title, $movieId);

    // Keep nullable ints out of the big bind_param (null/0 breaks flags or FK)
    if ($movieId) {
        $stmt = $conn->prepare('UPDATE movies SET title=?, description=?, thumbnail=?, poster=?, backdrop=?, duration=?, release_year=?, rating=?, featured=?, is_active=?, is_free=?, is_premium=?, show_in_slider=?, slug=?, sources=?, download_links=?, director=?, tags=?, quality_label=?, cast_data=?, genres=? WHERE id=?');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Error preparing statement: ' . $conn->error, 'id' => $movieId];
        }
        $stmt->bind_param('sssssiidiiiiissssssssi', $title, $description, $thumbnail, $poster, $backdrop, $duration, $release_year, $rating, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $slug, $sourcesJson, $downloadLinksJson, $director, $tags, $quality_label, $cast_data, $genres, $movieId);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Error updating movie: ' . $stmt->error, 'id' => $movieId];
        }
        $savedId = $movieId;
    } else {
        $stmt = $conn->prepare('INSERT INTO movies (title, description, thumbnail, poster, backdrop, duration, release_year, rating, featured, is_active, is_free, is_premium, show_in_slider, slug, sources, download_links, director, tags, quality_label, cast_data, genres) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) {
            return ['success' => false, 'message' => 'Error preparing statement: ' . $conn->error, 'id' => null];
        }
        $stmt->bind_param('sssssiidiiiiissssssss', $title, $description, $thumbnail, $poster, $backdrop, $duration, $release_year, $rating, $featured, $is_active, $is_free, $is_premium, $show_in_slider, $slug, $sourcesJson, $downloadLinksJson, $director, $tags, $quality_label, $cast_data, $genres);
        if (!$stmt->execute()) {
            return ['success' => false, 'message' => 'Error adding movie: ' . $stmt->error, 'id' => null];
        }
        $savedId = (int) $conn->insert_id;
    }

    if ($savedId <= 0) {
        return ['success' => false, 'message' => 'Save failed: missing movie id', 'id' => null];
    }

    // Force homepage flags with raw SQL, then verify (prepared binds have been unreliable here)
    $okFlags = $conn->query(
        'UPDATE movies SET featured = ' . (int) $featured .
        ', is_active = ' . (int) $is_active .
        ', is_free = ' . (int) $is_free .
        ', is_premium = ' . (int) $is_premium .
        ', show_in_slider = ' . (int) $show_in_slider .
        ' WHERE id = ' . (int) $savedId
    );
    if (!$okFlags) {
        return ['success' => false, 'message' => 'Error saving homepage flags: ' . $conn->error, 'id' => $savedId];
    }
    $verify = $conn->query('SELECT featured, is_active, show_in_slider FROM movies WHERE id = ' . (int) $savedId);
    $verifyRow = $verify ? $verify->fetch_assoc() : null;
    if (!$verifyRow || (int) $verifyRow['show_in_slider'] !== (int) $show_in_slider) {
        return [
            'success' => false,
            'message' => 'Homepage slider flag did not save correctly. Please try again.',
            'id' => $savedId,
        ];
    }

    if ($category_id > 0) {
        $cstmt = $conn->prepare('UPDATE movies SET category_id = ? WHERE id = ?');
        if ($cstmt) {
            $cstmt->bind_param('ii', $category_id, $savedId);
            $cstmt->execute();
        }
    } else {
        $conn->query('UPDATE movies SET category_id = NULL WHERE id = ' . (int) $savedId);
    }

    if ($tmdb_id > 0) {
        $tmdbStmt = $conn->prepare('UPDATE movies SET tmdb_id = ? WHERE id = ?');
        if ($tmdbStmt) {
            $tmdbStmt->bind_param('ii', $tmdb_id, $savedId);
            $tmdbStmt->execute();
        }
    } else {
        $conn->query('UPDATE movies SET tmdb_id = NULL WHERE id = ' . (int) $savedId);
    }

    $tstmt = $conn->prepare('UPDATE movies SET trailer_url = ? WHERE id = ?');
    if ($tstmt) {
        $tstmt->bind_param('si', $trailer_url, $savedId);
        $tstmt->execute();
    }

    return [
        'success' => true,
        'message' => ($movieId ? 'Movie updated successfully' : 'Movie added successfully')
            . ' | Banner slider: ' . ($show_in_slider ? 'ON' : 'OFF')
            . ' | Featured row: ' . ($featured ? 'ON' : 'OFF'),
        'id' => $savedId,
        'show_in_slider' => $show_in_slider,
        'featured' => $featured,
    ];
}

function getMovieConcurrentViewers($conn, int $movieId): int
{
    try {
        $conn->query('DELETE FROM movie_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)');
        $stmt = $conn->prepare('SELECT COUNT(DISTINCT session_id) AS count FROM movie_viewers WHERE movie_id = ?');
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('i', $movieId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return (int) ($row['count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

function prepareMovieForForm($conn, int $movieId): ?array
{
    $movie = getMovieById($conn, $movieId);
    if (!$movie) {
        return null;
    }
    $movie['sources'] = parseSources($movie['sources'] ?? '[]');
    $movie['download_links'] = parseDownloadLinks($movie['download_links'] ?? '[]');
    return $movie;
}
