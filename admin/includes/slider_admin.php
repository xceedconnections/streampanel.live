<?php
/**
 * Slider admin schema + request handlers (must run before HTML output).
 */

function ensureSliderAdminSchema(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $conn->query("CREATE TABLE IF NOT EXISTS sliders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            display_on_home TINYINT(1) DEFAULT 0,
            display_on_movies TINYINT(1) DEFAULT 0,
            display_on_tv_shows TINYINT(1) DEFAULT 0,
            display_on_live_tv TINYINT(1) DEFAULT 0,
            auto_rotate TINYINT(1) DEFAULT 1,
            rotate_interval INT DEFAULT 5000,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $columns = [
            'display_on_home' => "ALTER TABLE sliders ADD COLUMN display_on_home TINYINT(1) DEFAULT 0",
            'display_on_movies' => "ALTER TABLE sliders ADD COLUMN display_on_movies TINYINT(1) DEFAULT 0",
            'display_on_tv_shows' => "ALTER TABLE sliders ADD COLUMN display_on_tv_shows TINYINT(1) DEFAULT 0",
            'display_on_live_tv' => "ALTER TABLE sliders ADD COLUMN display_on_live_tv TINYINT(1) DEFAULT 0",
            'auto_rotate' => "ALTER TABLE sliders ADD COLUMN auto_rotate TINYINT(1) DEFAULT 1",
            'rotate_interval' => "ALTER TABLE sliders ADD COLUMN rotate_interval INT DEFAULT 5000",
        ];

        foreach ($columns as $column => $sql) {
            $check = $conn->query("SHOW COLUMNS FROM sliders LIKE '" . $conn->real_escape_string($column) . "'");
            if ($check && $check->num_rows === 0) {
                @$conn->query($sql);
            }
        }

        $conn->query("CREATE TABLE IF NOT EXISTS slider_slides (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slider_id INT NOT NULL,
            title VARCHAR(255) DEFAULT NULL,
            description TEXT,
            image_url VARCHAR(500) NOT NULL DEFAULT '',
            link_type ENUM('movie', 'tv_show', 'live_tv', 'external') DEFAULT 'external',
            link_id INT NULL,
            link_url VARCHAR(500) DEFAULT NULL,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_slider_id (slider_id),
            INDEX idx_display_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {
        error_log('Slider schema update error: ' . $e->getMessage());
    }
}

function sliderAdminFlash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $_SESSION['slider_admin_message'] = $message;
    $_SESSION['slider_admin_message_type'] = $type;
}

function sliderAdminRedirect(string $location): void
{
    if (!headers_sent()) {
        header('Location: ' . $location);
        exit;
    }

    echo '<script>window.location.href = ' . json_encode($location) . ';</script>';
    exit;
}

function processSliderAdminRequests(mysqli $conn): void
{
    ensureSliderAdminSchema($conn);

    if (isset($_GET['delete_slider'])) {
        $id = (int) $_GET['delete_slider'];
        $stmt = $conn->prepare('DELETE FROM sliders WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
        sliderAdminRedirect('?tab=sliders');
    }

    if (isset($_GET['delete_slide'])) {
        $id = (int) $_GET['delete_slide'];
        $sliderId = (int) ($_GET['slider_id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM slider_slides WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
        sliderAdminRedirect('?tab=sliders' . ($sliderId > 0 ? '&slider_id=' . $sliderId : ''));
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['reorder_slides'])) {
        $sliderId = (int) ($_POST['slider_id'] ?? 0);
        $wantsJson = !empty($_POST['ajax'])
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

        $orderIds = [];
        if (!empty($_POST['slide_order']) && is_array($_POST['slide_order'])) {
            $orderIds = array_map('intval', $_POST['slide_order']);
        } elseif (!empty($_POST['slide_priorities']) && is_array($_POST['slide_priorities'])) {
            $priorities = [];
            foreach ($_POST['slide_priorities'] as $sid => $prio) {
                $priorities[(int) $sid] = (int) $prio;
            }
            asort($priorities, SORT_NUMERIC);
            $orderIds = array_keys($priorities);
        }

        if ($sliderId > 0 && !empty($orderIds)) {
            $stmt = $conn->prepare('UPDATE slider_slides SET display_order = ? WHERE id = ? AND slider_id = ?');
            if ($stmt) {
                $pos = 1;
                foreach ($orderIds as $slideId) {
                    if ($slideId <= 0) {
                        continue;
                    }
                    $stmt->bind_param('iii', $pos, $slideId, $sliderId);
                    $stmt->execute();
                    $pos++;
                }
            }
        }

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => count($orderIds)]);
            exit;
        }

        sliderAdminRedirect('?tab=sliders&slider_id=' . $sliderId . '&reordered=1');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_slider'])) {
        $id = !empty($_POST['id']) ? (int) $_POST['id'] : null;
        $title = sanitize($_POST['title'] ?? '');
        $display_order = (int) ($_POST['display_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $display_on_home = isset($_POST['display_on_home']) ? 1 : 0;
        $display_on_movies = isset($_POST['display_on_movies']) ? 1 : 0;
        $display_on_tv_shows = isset($_POST['display_on_tv_shows']) ? 1 : 0;
        $display_on_live_tv = isset($_POST['display_on_live_tv']) ? 1 : 0;
        $auto_rotate = isset($_POST['auto_rotate']) ? 1 : 0;
        $rotate_interval = (int) ($_POST['rotate_interval'] ?? 5000);

        if ($title === '') {
            sliderAdminFlash('error', 'Slider name is required.');
            sliderAdminRedirect('?tab=sliders');
        }

        if ($id) {
            $stmt = $conn->prepare('UPDATE sliders SET title=?, display_order=?, is_active=?, display_on_home=?, display_on_movies=?, display_on_tv_shows=?, display_on_live_tv=?, auto_rotate=?, rotate_interval=? WHERE id=?');
            if (!$stmt) {
                sliderAdminFlash('error', 'Database error: ' . $conn->error);
                sliderAdminRedirect('?tab=sliders&edit_slider=' . $id);
            }
            $stmt->bind_param('siiiiiiiii', $title, $display_order, $is_active, $display_on_home, $display_on_movies, $display_on_tv_shows, $display_on_live_tv, $auto_rotate, $rotate_interval, $id);
            $ok = $stmt->execute();
            $redirectId = $id;
        } else {
            $stmt = $conn->prepare('INSERT INTO sliders (title, display_order, is_active, display_on_home, display_on_movies, display_on_tv_shows, display_on_live_tv, auto_rotate, rotate_interval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                sliderAdminFlash('error', 'Database error: ' . $conn->error);
                sliderAdminRedirect('?tab=sliders');
            }
            $stmt->bind_param('siiiiiiii', $title, $display_order, $is_active, $display_on_home, $display_on_movies, $display_on_tv_shows, $display_on_live_tv, $auto_rotate, $rotate_interval);
            $ok = $stmt->execute();
            $redirectId = (int) $conn->insert_id;
        }

        if (!$ok) {
            sliderAdminFlash('error', 'Failed to save slider: ' . ($stmt->error ?: $conn->error));
            sliderAdminRedirect('?tab=sliders');
        }

        sliderAdminFlash('success', $id ? 'Slider updated successfully' : 'Slider added successfully');
        sliderAdminRedirect('?tab=sliders&slider_id=' . $redirectId);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_slide'])) {
        $id = !empty($_POST['slide_id']) ? (int) $_POST['slide_id'] : null;
        $slider_id = (int) ($_POST['slider_id'] ?? 0);
        $title = sanitize($_POST['slide_title'] ?? '');
        $description = sanitize($_POST['slide_description'] ?? '');
        $image_url = sanitize($_POST['slide_image_url'] ?? '');
        $link_type = sanitize($_POST['slide_link_type'] ?? 'external');
        $link_id = !empty($_POST['slide_link_id']) ? (int) $_POST['slide_link_id'] : 0;
        $link_url = sanitize($_POST['slide_link_url'] ?? '');
        $display_order = (int) ($_POST['slide_display_order'] ?? 0);
        $is_active = isset($_POST['slide_is_active']) ? 1 : 0;
        $message_type = '';

        if ($slider_id <= 0) {
            sliderAdminFlash('error', 'Invalid slider.');
            sliderAdminRedirect('?tab=sliders');
        }

        if (isset($_FILES['slide_image_file']) && $_FILES['slide_image_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . '/uploads/sliders/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = strtolower(pathinfo($_FILES['slide_image_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 5 * 1024 * 1024;

            if ($_FILES['slide_image_file']['size'] > $max_file_size) {
                $message_type = 'error';
                sliderAdminFlash('error', 'File size exceeds 5MB limit');
            } elseif (in_array($file_extension, $allowed_extensions, true)) {
                $file_name = 'slider_' . time() . '_' . uniqid() . '.' . $file_extension;
                $file_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['slide_image_file']['tmp_name'], $file_path) && file_exists($file_path)) {
                    if ($id) {
                        $old_slide = $conn->prepare('SELECT image_url FROM slider_slides WHERE id = ?');
                        if ($old_slide) {
                            $old_slide->bind_param('i', $id);
                            $old_slide->execute();
                            $old_slide_result = $old_slide->get_result()->fetch_assoc();
                            if ($old_slide_result && !empty($old_slide_result['image_url']) && strpos($old_slide_result['image_url'], 'uploads/sliders/') !== false) {
                                $old_url = $old_slide_result['image_url'];
                                $old_file_path = str_replace(BASE_URL . '/', dirname(__DIR__) . '/', $old_url);
                                $old_file_path = str_replace('/admin', '', $old_file_path);
                                if (file_exists($old_file_path)) {
                                    @unlink($old_file_path);
                                }
                            }
                        }
                    }
                    $base_url = str_replace('/admin', '', BASE_URL);
                    $image_url = rtrim($base_url, '/') . '/uploads/sliders/' . $file_name;
                } else {
                    $message_type = 'error';
                    sliderAdminFlash('error', 'Failed to upload image.');
                }
            } else {
                $message_type = 'error';
                sliderAdminFlash('error', 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP');
            }
        }

        if ($message_type !== 'error' && $link_type === 'movie' && $link_id) {
            require_once dirname(__DIR__) . '/../includes/movie_helpers.php';
            $movie = getMovieById($conn, $link_id);
            if ($movie) {
                if ($title === '') {
                    $title = $movie['title'] ?? '';
                }
                if ($description === '') {
                    $description = $movie['description'] ?? '';
                }
                if ($image_url === '') {
                    $image_url = movieBackdropUrl($movie);
                    if ($image_url === '') {
                        $image_url = moviePosterUrl($movie);
                    }
                }
            }
        } elseif ($message_type !== 'error' && $link_type === 'tv_show' && $link_id) {
            $show = getTVShowById($conn, $link_id);
            if ($show) {
                if ($title === '') {
                    $title = $show['title'] ?? '';
                }
                if ($description === '' && !empty($show['description'])) {
                    $description = $show['description'];
                }
                if ($image_url === '') {
                    $image_url = $show['poster'] ?? ($show['thumbnail'] ?? '');
                }
            }
        } elseif ($message_type !== 'error' && $link_type === 'live_tv' && $link_id) {
            $channel = getChannelById($conn, $link_id);
            if ($channel) {
                if ($title === '') {
                    $title = $channel['name'] ?? '';
                }
                if ($description === '' && !empty($channel['description'])) {
                    $description = $channel['description'];
                }
                if ($image_url === '') {
                    $image_url = $channel['logo'] ?? '';
                }
            }
        }

        if ($message_type !== 'error' && $image_url === '') {
            if ($id) {
                $existing_slide = $conn->prepare('SELECT image_url FROM slider_slides WHERE id = ?');
                if ($existing_slide) {
                    $existing_slide->bind_param('i', $id);
                    $existing_slide->execute();
                    $existing_result = $existing_slide->get_result()->fetch_assoc();
                    if ($existing_result && !empty($existing_result['image_url'])) {
                        $image_url = $existing_result['image_url'];
                    }
                }
            }
            if ($image_url === '') {
                sliderAdminFlash('error', 'Please provide an image, or link a Movie/TV/Live item that has a poster/banner');
                sliderAdminRedirect('?tab=sliders&slider_id=' . $slider_id);
            }
        }

        if ($message_type !== 'error') {
            if ($id) {
                $stmt = $conn->prepare('UPDATE slider_slides SET title=?, description=?, image_url=?, link_type=?, link_id=?, link_url=?, display_order=?, is_active=? WHERE id=?');
                if (!$stmt) {
                    sliderAdminFlash('error', 'Database error: ' . $conn->error);
                    sliderAdminRedirect('?tab=sliders&slider_id=' . $slider_id);
                }
                $stmt->bind_param('ssssisiii', $title, $description, $image_url, $link_type, $link_id, $link_url, $display_order, $is_active, $id);
                $ok = $stmt->execute();
                $flash = $ok ? 'Slide updated successfully' : ('Failed to update slide: ' . $stmt->error);
            } else {
                if ($display_order <= 0) {
                    $maxRes = $conn->prepare('SELECT COALESCE(MAX(display_order), 0) AS m FROM slider_slides WHERE slider_id = ?');
                    if ($maxRes) {
                        $maxRes->bind_param('i', $slider_id);
                        $maxRes->execute();
                        $maxRow = $maxRes->get_result()->fetch_assoc();
                        $display_order = ((int) ($maxRow['m'] ?? 0)) + 1;
                    }
                }
                $stmt = $conn->prepare('INSERT INTO slider_slides (slider_id, title, description, image_url, link_type, link_id, link_url, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                if (!$stmt) {
                    sliderAdminFlash('error', 'Database error: ' . $conn->error);
                    sliderAdminRedirect('?tab=sliders&slider_id=' . $slider_id);
                }
                $stmt->bind_param('issssisii', $slider_id, $title, $description, $image_url, $link_type, $link_id, $link_url, $display_order, $is_active);
                $ok = $stmt->execute();
                $flash = $ok ? 'Slide added successfully' : ('Failed to add slide: ' . $stmt->error);
            }

            sliderAdminFlash($ok ? 'success' : 'error', $flash);
        }

        sliderAdminRedirect('?tab=sliders&slider_id=' . $slider_id);
    }
}

function sliderAdminTakeFlash(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $message = $_SESSION['slider_admin_message'] ?? '';
    $message_type = $_SESSION['slider_admin_message_type'] ?? '';
    unset($_SESSION['slider_admin_message'], $_SESSION['slider_admin_message_type']);
    return [$message, $message_type];
}
