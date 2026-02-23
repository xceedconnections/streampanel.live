<?php
/**
 * Admin Helper Functions
 */

require_once __DIR__ . '/../../config/database.php';

// Sanitize input (only if not already defined)
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

// Get all categories
function getAllCategories($conn) {
    $result = $conn->query("SELECT * FROM categories ORDER BY display_order ASC, name ASC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get category by ID
function getCategoryById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get movie by ID
function getMovieById($conn, $id) {
    $stmt = $conn->prepare("SELECT m.*, c.name as category_name FROM movies m LEFT JOIN categories c ON m.category_id = c.id WHERE m.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get TV show by ID
function getTVShowById($conn, $id) {
    $stmt = $conn->prepare("SELECT t.*, c.name as category_name FROM tv_shows t LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get live TV channel by ID
function getChannelById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM live_tv_channels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Parse JSON sources
function parseSources($sourcesJson) {
    if (empty($sourcesJson)) return [];
    $sources = json_decode($sourcesJson, true);
    return is_array($sources) ? $sources : [];
}

// Encode sources to JSON
function encodeSources($sources) {
    return json_encode($sources, JSON_UNESCAPED_SLASHES);
}

// Count active sources for a channel
function countActiveSources($channel) {
    $sources = parseSources($channel['sources'] ?? '[]');
    $count = 0;
    
    if (is_array($sources) && !empty($sources)) {
        foreach ($sources as $source) {
            // Skip if source is not an array
            if (!is_array($source)) {
                continue;
            }
            
            // Check if source is active - default to true if not set, but explicitly check for false values
            $isActive = true;
            if (isset($source['isActive'])) {
                $isActiveValue = $source['isActive'];
                // Handle boolean false, string "false", 0, "0", etc.
                if ($isActiveValue === false || $isActiveValue === 0 || $isActiveValue === '0' || 
                    (is_string($isActiveValue) && strtolower($isActiveValue) === 'false')) {
                    $isActive = false;
                } else {
                    $isActive = (bool)$isActiveValue;
                }
            }
            
            // Check if source is visible - default to true if not set, but explicitly check for false values
            $isVisible = true;
            if (isset($source['isVisible'])) {
                $isVisibleValue = $source['isVisible'];
                // Handle boolean false, string "false", 0, "0", etc.
                if ($isVisibleValue === false || $isVisibleValue === 0 || $isVisibleValue === '0' || 
                    (is_string($isVisibleValue) && strtolower($isVisibleValue) === 'false')) {
                    $isVisible = false;
                } else {
                    $isVisible = (bool)$isVisibleValue;
                }
            }
            
            // Check if source has a valid URL
            $url = trim($source['url'] ?? '');
            
            // Only count if source is active, visible, and has a valid URL
            if ($isActive && $isVisible && !empty($url) && strlen($url) > 3) {
                $count++;
            }
        }
    }
    
    // Do NOT fallback to stream_url - channels must have valid JSON sources
    // This ensures we only show channels with properly configured sources
    
    return $count;
}

// Get sliders for a specific page
function getSlidersForPage($conn, $page) {
    $page_field = '';
    switch ($page) {
        case 'home':
            $page_field = 'display_on_home';
            break;
        case 'movies':
            $page_field = 'display_on_movies';
            break;
        case 'tv_shows':
            $page_field = 'display_on_tv_shows';
            break;
        case 'live_tv':
            $page_field = 'display_on_live_tv';
            break;
        default:
            return [];
    }
    
    if (empty($page_field)) return [];
    
    $query = "SELECT s.* FROM sliders s WHERE s.is_active = 1 AND s.$page_field = 1 ORDER BY s.display_order ASC, s.created_at ASC";
    $sliders = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
    
    // Get slides for each slider
    foreach ($sliders as &$slider) {
        $stmt = $conn->prepare("SELECT * FROM slider_slides WHERE slider_id = ? AND is_active = 1 ORDER BY display_order ASC, created_at ASC");
        $stmt->bind_param("i", $slider['id']);
        $stmt->execute();
        $slider['slides'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    return $sliders;
}

// Generate link URL for a slide
function getSlideLink($slide, $conn) {
    if ($slide['link_type'] === 'external') {
        return $slide['link_url'] ?? '#';
    }
    
    if (empty($slide['link_id'])) {
        return '#';
    }
    
    switch ($slide['link_type']) {
        case 'movie':
            return BASE_URL . '/watch.php?type=movie&id=' . $slide['link_id'];
        case 'tv_show':
            $tv_show = getTVShowById($conn, $slide['link_id']);
            if ($tv_show && !empty($tv_show['slug'])) {
                return BASE_URL . '/tv-show/' . $tv_show['slug'];
            }
            return BASE_URL . '/tv-shows';
        case 'live_tv':
            $channel = getChannelById($conn, $slide['link_id']);
            if ($channel && !empty($channel['slug'])) {
                return BASE_URL . '/tv/' . $channel['slug'];
            }
            return BASE_URL . '/live-tv';
        default:
            return '#';
    }
}

// Generate slug from title
function generateSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// Generate a filesystem-safe base filename for a channel logo from channel name
// Example: "ARY News HD" -> "arynewshd"
function generateChannelLogoBaseName($channelName) {
    // Lowercase and trim
    $base = strtolower(trim($channelName));
    // Remove anything that's not a letter or number
    $base = preg_replace('/[^a-z0-9]/', '', $base);
    
    // Fallback if name becomes empty
    if ($base === '' || $base === null) {
        $base = 'channel_logo_' . time();
    }
    
    return $base;
}

// Check if slug exists
function slugExists($conn, $table, $slug, $excludeId = null) {
    $query = "SELECT id FROM $table WHERE slug = ?";
    $params = [$slug];
    $types = "s";
    
    if ($excludeId) {
        $query .= " AND id != ?";
        $params[] = $excludeId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($query);
    if (count($params) > 1) {
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param($types, $params[0]);
    }
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Get unique slug
function getUniqueSlug($conn, $table, $title, $excludeId = null) {
    $slug = generateSlug($title);
    $originalSlug = $slug;
    $counter = 1;
    
    while (slugExists($conn, $table, $slug, $excludeId)) {
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

// Get all users
function getAllUsers($conn) {
    $result = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get user by ID
function getUserById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get statistics
function getAdminStats($conn) {
    $stats = [];
    
    try {
        $stats['total_movies'] = $conn->query("SELECT COUNT(*) as count FROM movies")->fetch_assoc()['count'] ?? 0;
        $stats['total_tv_shows'] = $conn->query("SELECT COUNT(*) as count FROM tv_shows")->fetch_assoc()['count'] ?? 0;
        $stats['total_channels'] = $conn->query("SELECT COUNT(*) as count FROM live_tv_channels")->fetch_assoc()['count'] ?? 0;
        $stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'] ?? 0;
        $stats['total_categories'] = $conn->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'] ?? 0;
        $stats['featured_movies'] = $conn->query("SELECT COUNT(*) as count FROM movies WHERE featured = 1")->fetch_assoc()['count'] ?? 0;
        $stats['featured_tv_shows'] = $conn->query("SELECT COUNT(*) as count FROM tv_shows WHERE featured = 1")->fetch_assoc()['count'] ?? 0;
        $stats['featured_channels'] = $conn->query("SELECT COUNT(*) as count FROM live_tv_channels WHERE featured = 1")->fetch_assoc()['count'] ?? 0;
    } catch (Exception $e) {
        // Return empty stats on error
    }
    
    return $stats;
}

// Check if column exists in table
function columnExists($conn, $table, $column) {
    try {
        $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        return $result && $result->num_rows > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Get setting value
function getSetting($conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

// Get concurrent viewers for a channel
function getConcurrentViewers($conn, $channel_id) {
    try {
        // Clean up old viewers first (older than 30 seconds)
        try {
            $conn->query("DELETE FROM channel_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            // Try with last_seen if last_ping doesn't exist
            try {
                $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                // Table might not exist
            }
        }
        
        $stmt = $conn->prepare("SELECT COUNT(DISTINCT session_id) as count FROM channel_viewers WHERE channel_id = ?");
        $stmt->bind_param("i", $channel_id);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_assoc()['count'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get total concurrent viewers across all channels
function getTotalConcurrentViewers($conn) {
    try {
        // Clean up old viewers first
        try {
            $conn->query("DELETE FROM channel_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            // Try with last_seen if last_ping doesn't exist
            try {
                $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                // Table might not exist
            }
        }
        
        $result = $conn->query("SELECT COUNT(DISTINCT session_id) as total FROM channel_viewers");
        return $result->fetch_assoc()['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get concurrent viewers by channel
function getConcurrentViewersByChannel($conn) {
    try {
        // Clean up old viewers first
        try {
            $conn->query("DELETE FROM channel_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            // Try with last_seen if last_ping doesn't exist
            try {
                $conn->query("DELETE FROM channel_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                // Table might not exist
            }
        }
        
        $result = $conn->query("
            SELECT 
                cv.channel_id,
                ltc.name as channel_name,
                COUNT(DISTINCT cv.session_id) as concurrent_viewers
            FROM channel_viewers cv
            LEFT JOIN live_tv_channels ltc ON cv.channel_id = ltc.id
            GROUP BY cv.channel_id, ltc.name
            ORDER BY concurrent_viewers DESC
        ");
        
        $viewers = [];
        while ($row = $result->fetch_assoc()) {
            $viewers[] = $row;
        }
        return $viewers;
    } catch (Exception $e) {
        return [];
    }
}

// Get total concurrent episode viewers
function getTotalConcurrentEpisodeViewers($conn) {
    try {
        // Clean up old viewers first
        try {
            $conn->query("DELETE FROM episode_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            // Try with last_seen if last_ping doesn't exist
            try {
                $conn->query("DELETE FROM episode_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                // Table might not exist
            }
        }
        
        $result = $conn->query("SELECT COUNT(DISTINCT session_id) as total FROM episode_viewers");
        return $result->fetch_assoc()['total'] ?? 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Get concurrent viewers by episode
function getConcurrentViewersByEpisode($conn) {
    try {
        // Clean up old viewers first
        try {
            $conn->query("DELETE FROM episode_viewers WHERE last_ping < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        } catch (Exception $e) {
            // Try with last_seen if last_ping doesn't exist
            try {
                $conn->query("DELETE FROM episode_viewers WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
            } catch (Exception $e2) {
                // Table might not exist
            }
        }
        
        $result = $conn->query("
            SELECT 
                ev.episode_id,
                e.title as episode_title,
                e.season_number,
                e.episode_number,
                t.title as show_title,
                COUNT(DISTINCT ev.session_id) as concurrent_viewers
            FROM episode_viewers ev
            LEFT JOIN tv_episodes e ON ev.episode_id = e.id
            LEFT JOIN tv_shows t ON e.tv_show_id = t.id
            GROUP BY ev.episode_id, e.title, e.season_number, e.episode_number, t.title
            ORDER BY concurrent_viewers DESC
        ");
        
        $viewers = [];
        while ($row = $result->fetch_assoc()) {
            $viewers[] = $row;
        }
        return $viewers;
    } catch (Exception $e) {
        return [];
    }
}

// Get all settings as array
function getAllSettings($conn) {
    $settings = [];
    $result = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Check if section is enabled
function isSectionEnabled($conn, $section) {
    $key = 'enable_' . $section; // enable_movies, enable_tv_shows, enable_live_tv
    $enabled = getSetting($conn, $key, '1');
    return $enabled == '1';
}

?>
