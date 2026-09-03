<?php
/**
 * TV Channel Page
 * Advanced Live TV Channel Player with Netflix Theme
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$conn = getDBConnection();

// Check if login is required for TV channels
// Use direct database query as fallback if getSetting fails
$login_required_tv_channels = '0'; // Default to '0' (login NOT required)
try {
    $setting_result = getSetting($conn, 'login_required_tv_channels', '0');
    if ($setting_result !== false && $setting_result !== null) {
        $login_required_tv_channels = $setting_result;
    } else {
        // Fallback: direct database query
        $direct_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_tv_channels' LIMIT 1");
        if ($direct_query && $direct_query->num_rows > 0) {
            $row = $direct_query->fetch_assoc();
            $login_required_tv_channels = $row['setting_value'] ?? '0';
        }
    }
} catch (Exception $e) {
    // On error, default to '0' (login NOT required)
    error_log("[TV Channel] Error reading login_required_tv_channels setting: " . $e->getMessage());
    $login_required_tv_channels = '0';
}

// Debug: Log the setting value (remove after debugging)
error_log("[TV Channel] login_required_tv_channels setting value: " . var_export($login_required_tv_channels, true) . " (type: " . gettype($login_required_tv_channels) . ")");

// Check if setting is enabled (accept '1', 1, true, or 'true')
// Also check for string 'true' and trim whitespace
$login_required = false;
if (is_string($login_required_tv_channels)) {
    $login_required_tv_channels = trim($login_required_tv_channels);
    // Only require login if explicitly set to '1', 'true', or 'yes'
    $login_required = ($login_required_tv_channels === '1' || $login_required_tv_channels === 'true' || $login_required_tv_channels === 'yes');
} else {
    $login_required = ($login_required_tv_channels === 1 || $login_required_tv_channels === true);
}

// Additional safety: if value is empty, null, or '0', don't require login
if (empty($login_required_tv_channels) || $login_required_tv_channels === '0' || $login_required_tv_channels === 0 || $login_required_tv_channels === false || $login_required_tv_channels === null) {
    $login_required = false;
}

// FINAL CHECK: Only require login if explicitly set to require it
// Default behavior: ALLOW ACCESS (login NOT required)
// NOTE: The 'is_free' field in channels is just a label - it does NOT enforce login requirement
// Only the 'login_required_tv_channels' setting controls whether login is required
if ($login_required === true) {
    // Require user to be logged in
    error_log("[TV Channel] Login required - redirecting to login page. Setting value: " . var_export($login_required_tv_channels, true));
    requireLogin();
} else {
    // Allow access without login (regardless of is_free field)
    error_log("[TV Channel] Login NOT required - allowing access. Setting value: " . var_export($login_required_tv_channels, true));
    // Continue processing the page - users can access even if not logged in
}

// BASE_URL is auto-detected from the install path in config.php

$slug = $_GET['slug'] ?? null;
$id = $_GET['id'] ?? null;
// Optional source index (0-based) to choose a specific stream/source
$source_index = isset($_GET['source']) ? intval($_GET['source']) : null;
$channel = null;
$error = '';
$isAndroidTV = isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android TV|AFT|BRAVIA|MiTV|SmartTV|GoogleTV|Tizen|Web0S|HbbTV)/i', $_SERVER['HTTP_USER_AGENT']);

// Get enabled sections for navigation
$enable_movies = isSectionEnabled($conn, 'movies');
$enable_tv_shows = isSectionEnabled($conn, 'tv_shows');
$enable_live_tv = isSectionEnabled($conn, 'live_tv');

// Get channel by ID or slug
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM live_tv_channels WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $channel = normalizeLiveTvChannel($stmt->get_result()->fetch_assoc());
} elseif ($slug) {
    $stmt = $conn->prepare("SELECT * FROM live_tv_channels WHERE slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $channel = normalizeLiveTvChannel($stmt->get_result()->fetch_assoc());
}

if (!$channel) {
    $error = 'Channel not found';
} else {
    // Check if channel is active
    $isChannelActive = ($channel['is_active'] ?? 1) == 1;
    if (!$isChannelActive) {
        $error = 'This channel is not yet active';
} else {
    // Parse sources
    $sources = parseSources($channel['sources'] ?? '[]');
    
    // Check if channel has any valid sources (JSON `sources` only)
    // We intentionally DO NOT treat plain `stream_url` as a valid source here.
    // Channels without configured sources should be shown as "Channel Not Available".
    $has_valid_sources = false;
    if (!empty($sources)) {
        foreach ($sources as $source) {
            // Check if source has a valid URL (not empty, not just whitespace)
            $source_url = trim($source['url'] ?? '');
            if (!empty($source_url) && strlen($source_url) > 3) {
                $has_valid_sources = true;
                break;
            }
        }
    }
    
    // If no valid sources, show error message
    if (!$has_valid_sources) {
        $error = 'This Channel is no Longer Available';
    } else {
    // Select source:
    // - If ?source=<index> is provided, use that 0-based index within active/visible sources
    // - Otherwise, pick best source (priority 0 first, then active and visible)
    $selected_source = null;
    if (!empty($sources)) {
        // Filter active and visible sources
        $active_sources = array_filter($sources, function($s) {
            return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true);
        });
        $active_sources = array_values($active_sources);
        
        if (!empty($active_sources)) {
            if ($source_index !== null && $source_index >= 0 && $source_index < count($active_sources)) {
                // Use the requested source index
                $selected_source = $active_sources[$source_index];
            } else {
                // Sort by priority (0 = default/highest priority) and take the first
                usort($active_sources, function($a, $b) {
                    $priority_a = intval($a['priority'] ?? 999);
                    $priority_b = intval($b['priority'] ?? 999);
                    return $priority_a <=> $priority_b;
                });
                $selected_source = $active_sources[0];
            }
        } else {
            // Fallback to first source if none are active/visible
            $selected_source = $sources[0] ?? null;
        }
    }
    
    // Note: we deliberately do NOT fall back to `stream_url` here.
    // If there are no configured JSON sources, the channel should be treated
    // as unavailable and the error page will be shown instead of a player.
    }
    } // End of isChannelActive check
    
    // Only check premium and update views if channel is active
    if ($isChannelActive) {
    // Check if channel is premium and user has subscription
    $isPremiumChannel = ($channel['is_premium'] ?? 0) == 1;
    $hasSubscription = hasActiveSubscription();
    $showPremiumGate = $isPremiumChannel && !$hasSubscription;
        
        // Debug subscription status
        if (isLoggedIn()) {
            $user_id = $_SESSION['user_id'] ?? null;
            $subscription_type = $_SESSION['subscription_type'] ?? 'free';
            $subscription_expires = null;
            if ($user_id) {
                $sub_check = $conn->prepare("SELECT subscription_type, subscription_expires_at FROM users WHERE id = ?");
                $sub_check->bind_param("i", $user_id);
                $sub_check->execute();
                $sub_result = $sub_check->get_result();
                if ($sub_result->num_rows > 0) {
                    $sub_data = $sub_result->fetch_assoc();
                    $subscription_type = $sub_data['subscription_type'] ?? 'free';
                    $subscription_expires = $sub_data['subscription_expires_at'] ?? null;
                }
            }
            error_log("[TV Channel] User ID: {$user_id}, Subscription Type: {$subscription_type}, Expires: " . ($subscription_expires ?? 'NULL') . ", hasSubscription: " . ($hasSubscription ? 'true' : 'false'));
        }
    
    // Update views only if user can access
    if (!$showPremiumGate) {
        $conn->query("UPDATE live_tv_channels SET views = views + 1 WHERE id = " . $channel['id']);
            
            // Save to watch history for logged-in users
            if (isLoggedIn()) {
                $user_id = $_SESSION['user_id'];
                $content_type = 'live_tv';
                $content_id = $channel['id'];
                
                // Check if history exists
                $history_check = $conn->prepare("SELECT id FROM watch_history WHERE user_id = ? AND content_type = ? AND content_id = ?");
                $history_check->bind_param("isi", $user_id, $content_type, $content_id);
                $history_check->execute();
                
                if ($history_check->get_result()->num_rows > 0) {
                    // Update timestamp
                    $history_update = $conn->prepare("UPDATE watch_history SET watched_at = NOW() WHERE user_id = ? AND content_type = ? AND content_id = ?");
                    $history_update->bind_param("isi", $user_id, $content_type, $content_id);
                    $history_update->execute();
                } else {
                    // Insert new record
                    $history_insert = $conn->prepare("INSERT INTO watch_history (user_id, content_type, content_id) VALUES (?, ?, ?)");
                    $history_insert->bind_param("isi", $user_id, $content_type, $content_id);
                    $history_insert->execute();
                }
            }
        }
    } else {
        // Channel is inactive, set premium gate to false (error page will show instead)
        $showPremiumGate = false;
        $isPremiumChannel = false;
        $hasSubscription = false;
    }
    
    // Get real-time viewer count (will be updated via AJAX)
    $channel['current_viewers'] = 0; // Will be updated in real-time
    
    // Get ads for this channel
    $channel_ads = [];
    $intro_ad = null;
    
    // Premium users don't see ads (except intro ads)
    $show_ads = !$hasSubscription;
    
    // Debug: Log subscription status for troubleshooting
    if (isLoggedIn()) {
        $debug_user_id = $_SESSION['user_id'] ?? null;
        $debug_sub_type = $_SESSION['subscription_type'] ?? 'free';
        $debug_sub_expires = null;
        if ($debug_user_id) {
            $debug_stmt = $conn->prepare("SELECT subscription_type, subscription_expires_at FROM users WHERE id = ?");
            $debug_stmt->bind_param("i", $debug_user_id);
            $debug_stmt->execute();
            $debug_result = $debug_stmt->get_result();
            if ($debug_result->num_rows > 0) {
                $debug_data = $debug_result->fetch_assoc();
                $debug_sub_type = $debug_data['subscription_type'] ?? 'free';
                $debug_sub_expires = $debug_data['subscription_expires_at'] ?? null;
            }
        }
        error_log("[TV Channel Debug] User ID: {$debug_user_id}, DB subscription_type: {$debug_sub_type}, subscription_expires_at: " . ($debug_sub_expires ?? 'NULL') . ", Session subscription_type: " . ($_SESSION['subscription_type'] ?? 'free') . ", hasSubscription: " . ($hasSubscription ? 'true' : 'false') . ", show_ads: " . ($show_ads ? 'true' : 'false'));
    }
    
    // Ensure intro_ad_id column exists
    try {
        $conn->query("ALTER TABLE live_tv_channels ADD COLUMN IF NOT EXISTS intro_ad_id INT NULL");
    } catch (Exception $e) {
        // Column might already exist
    }
    
    // Always get intro ads (plays to everyone)
    // First check if channel has a specific intro ad
    $channel_intro_ad_id = $channel['intro_ad_id'] ?? null;
    if (!empty($channel_intro_ad_id)) {
        $intro_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND type = 'intro-ad' AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
        $intro_stmt->bind_param("i", $channel_intro_ad_id);
        $intro_stmt->execute();
        $intro_result = $intro_stmt->get_result();
        if ($intro_result->num_rows > 0) {
            $intro_ad = $intro_result->fetch_assoc();
        }
    } else {
        // Fallback to global intro ad if channel doesn't have one
        $intro_stmt = $conn->prepare("SELECT * FROM ads WHERE type = 'intro-ad' AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY created_at DESC LIMIT 1");
        $intro_stmt->execute();
        $intro_result = $intro_stmt->get_result();
        if ($intro_result->num_rows > 0) {
            $intro_ad = $intro_result->fetch_assoc();
        }
    }
    
    // Always fetch loop ad configuration (for debugging), but only show for free users
    if (!empty($channel['loop_ad_id'])) {
        $loop_ad_id = intval($channel['loop_ad_id']);
        $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
        $ad_stmt->bind_param("i", $loop_ad_id);
        $ad_stmt->execute();
        $ad_result = $ad_stmt->get_result();
        if ($ad_result->num_rows > 0) {
            $loop_ad = $ad_result->fetch_assoc();
            $channel_ads['loop'] = $loop_ad;
            // Use ad's loop_interval (how often to show), or fallback to duration, or channel setting, then 60
            $channel_ads['loop_interval'] = !empty($loop_ad['loop_interval']) ? intval($loop_ad['loop_interval']) : 
                                          (!empty($loop_ad['duration']) ? intval($loop_ad['duration']) : 
                                          (!empty($channel['loop_interval']) ? intval($channel['loop_interval']) : 60));
        } else {
            // Debug: Log if ad exists but doesn't meet criteria
            $check_stmt = $conn->prepare("SELECT id, name, type, is_active, start_date, end_date FROM ads WHERE id = ?");
            $check_stmt->bind_param("i", $loop_ad_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $ad_info = $check_result->fetch_assoc();
                error_log("[TV Channel] Loop ad ID {$loop_ad_id} exists but not active or expired: " . json_encode($ad_info));
            } else {
                error_log("[TV Channel] Loop ad ID {$loop_ad_id} does not exist in database");
            }
        }
    } else {
        error_log("[TV Channel] No loop_ad_id configured for channel ID " . $channel['id']);
    }
    
    if ($show_ads) {
        // Get channel-specific ads (pre-roll, mid-roll, end-roll, banner, popup)
        if (!empty($channel['pre_roll_ad_id'])) {
            $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
            $ad_stmt->bind_param("i", $channel['pre_roll_ad_id']);
            $ad_stmt->execute();
            $ad_result = $ad_stmt->get_result();
            if ($ad_result->num_rows > 0) {
                $channel_ads['pre_roll'] = $ad_result->fetch_assoc();
            }
        }
        
        if (!empty($channel['mid_roll_ad_id'])) {
            $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
            $ad_stmt->bind_param("i", $channel['mid_roll_ad_id']);
            $ad_stmt->execute();
            $ad_result = $ad_stmt->get_result();
            if ($ad_result->num_rows > 0) {
                $channel_ads['mid_roll'] = $ad_result->fetch_assoc();
            }
        }
        
        if (!empty($channel['end_roll_ad_id'])) {
            $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
            $ad_stmt->bind_param("i", $channel['end_roll_ad_id']);
            $ad_stmt->execute();
            $ad_result = $ad_stmt->get_result();
            if ($ad_result->num_rows > 0) {
                $channel_ads['end_roll'] = $ad_result->fetch_assoc();
            }
        }
        
        if (!empty($channel['banner_ad_id'])) {
            $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
            $ad_stmt->bind_param("i", $channel['banner_ad_id']);
            $ad_stmt->execute();
            $ad_result = $ad_stmt->get_result();
            if ($ad_result->num_rows > 0) {
                $channel_ads['banner'] = $ad_result->fetch_assoc();
            }
        }
        
        if (!empty($channel['popup_ad_id'])) {
            $ad_stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
            $ad_stmt->bind_param("i", $channel['popup_ad_id']);
            $ad_stmt->execute();
            $ad_result = $ad_stmt->get_result();
            if ($ad_result->num_rows > 0) {
                $channel_ads['popup'] = $ad_result->fetch_assoc();
            }
        }
    }
}

// SEO meta data for TV channel page
$site_name = getSetting($conn, 'site_name', 'StreamFlix');
if ($channel) {
    $channel_name_raw = $channel['name'] ?? 'Live TV Channel';
    $channel_name_lower = strtolower($channel_name_raw);
    $channel_category = $channel['category'] ?? 'TV Channel';
    
    // Enhanced SEO title and description
    $pageTitle = "Watch {$channel_name_raw} Live Streaming Free - HD Channel Online | {$site_name}";
    $metaDescription = "{$channel_name_lower} live, {$channel_name_lower} live stream, watch {$channel_name_lower} online, {$channel_name_lower} tv channel, live {$channel_category} {$channel_name_lower}, {$channel_name_lower} hd, {$channel_name_lower} sports channel, {$channel_name_lower} free streaming, watch {$channel_name_lower} live";
    
    // Enhanced keywords
    $metaKeywords = "{$channel_name_lower} live, {$channel_name_lower} live stream, watch {$channel_name_lower} online, {$channel_name_lower} tv channel, live {$channel_category} {$channel_name_lower}, {$channel_name_lower} hd, {$channel_name_lower} sports channel, {$channel_name_lower} free streaming, watch {$channel_name_lower} live";
    
    // Channel logo URL for social sharing
    $channel_logo_url = assetUrl($channel['logo'] ?? '');
    
    // Canonical URL should point to main watch page
    $canonical_url = BASE_URL . '/watch-live-tv/' . ($channel['slug'] ?? 'channel');
    
    // Footer heading
    $footer_heading = "Watch {$channel_name_raw} Live Streaming Free - HD Channel Online";
} else {
    $pageTitle = "Channel Not Found - {$site_name}";
    $metaDescription = "Requested live TV channel could not be found on {$site_name}.";
    $metaKeywords = "live tv, tv channel, {$site_name}";
    $channel_logo_url = '';
    $canonical_url = BASE_URL . '/watch-live-tv';
    $footer_heading = '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    
    <!-- Mobile Optimization -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($metaKeywords); ?>">
    
    <!-- Open Graph / Facebook -->
    <?php if ($channel): ?>
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars("Watch {$channel_name_raw} Live Streaming | Live TV Online"); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars("Live stream {$channel_name_raw} TV - Watch in HD quality for free."); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical_url); ?>">
    <?php if (!empty($channel_logo_url)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($channel_logo_url); ?>">
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Twitter Card -->
    <?php if ($channel): ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars("{$channel_name_raw} Live Stream | Free Sports Channel"); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars("Watch {$channel_name_raw} live online - HD sports streaming free"); ?>">
    <?php if (!empty($channel_logo_url)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($channel_logo_url); ?>">
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
/* TV Channel Page Styles - Netflix Theme */
.tv-channel-page {
    min-height: 100vh;
    background: #000;
    color: #fff;
    font-family: system-ui, -apple-system, sans-serif;
}
@media (min-width: 768px) {
    .tv-channel-page {
        padding-bottom: 0; /* Remove on desktop */
    }
}

/* Sticky Header */
.sticky-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(4px);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 0.75rem 1rem;
}
@media (min-width: 768px) {
    .sticky-header {
        padding: 1rem 3rem;
    }
}

/* Mobile Header - Two Rows */
.mobile-header-row1 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.mobile-header-row2 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
@media (min-width: 768px) {
    .mobile-header-row1,
    .mobile-header-row2 {
        display: none;
    }
}

/* Desktop Header - Single Row */
.desktop-header {
    display: none;
    align-items: center;
    gap: 1rem;
}
@media (min-width: 768px) {
    .desktop-header {
        display: flex;
    }
}

.header-back-btn {
    padding: 0.5rem;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-back-btn:hover {
    background: rgba(255,255,255,0.1);
}

.channel-logo-header {
    width: 2.5rem;
    height: 2.5rem;
    object-fit: contain;
    flex-shrink: 0;
}

.channel-info-header {
    flex: 1;
    min-width: 0;
}
.channel-info-header h1 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (min-width: 768px) {
    .channel-info-header h1 {
        font-size: 1.25rem;
    }
}
.channel-info-header p {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (min-width: 768px) {
    .channel-info-header p {
        font-size: 0.875rem;
    }
}

.viewer-count-header {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    color: #4ade80;
    flex-shrink: 0;
}
.viewer-count-header svg {
    width: 18px;
    height: 18px;
    color: #4ade80;
}
.viewer-count-header span {
    font-weight: 600;
}

.source-selector-header {
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 0.375rem;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 500;
    cursor: pointer;
    outline: none;
    transition: all 0.2s;
    flex-shrink: 0;
    max-width: 120px;
}
.source-selector-header:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(255, 255, 255, 0.3);
}
.source-selector-header:focus {
    border-color: rgba(255, 255, 255, 0.4);
    background: rgba(255, 255, 255, 0.15);
}
.source-selector-header option {
    background: #1f2937;
    color: #fff;
}
@media (min-width: 768px) {
    .source-selector-header {
        font-size: 0.875rem;
        max-width: 150px;
        padding: 0.375rem 0.75rem;
    }
}

.fullscreen-btn-header {
    padding: 0.5rem;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fullscreen-btn-header:hover {
    background: rgba(255,255,255,0.1);
}
.fullscreen-btn-header svg {
    width: 20px;
    height: 20px;
}

/* Android TV Header */
.androidtv-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 50;
    background: #141414;
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 0.75rem 1rem;
}
@media (min-width: 768px) {
    .androidtv-header {
        padding: 0.75rem 3rem;
    }
}
.androidtv-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

/* Player Error Message */
.player-error-message {
    font-size: 0.75rem;
    color: #facc15;
    margin-top: 0.5rem;
}

/* Player Container */
.player-container {
    width: 100%;
    background: #000;
}
.player-container-mobile {
    /* Default size for tablets and larger mobile devices */
    height: calc(100vh - 80px - 220px); /* header (80px) + footer/nav (~60px) + extra margin */
    min-height: 250px;
    max-height: calc(100vh - 300px);
}
/* Smaller height for Android mobile devices only */
@media (max-width: 480px) {
    .player-container-mobile {
        height: calc(100vh - 80px - 380px); /* Much smaller for mobile to show suggested channels */
        min-height: 180px;
        max-height: calc(100vh - 460px);
    }
}
/* Medium mobile devices (between 480px and 768px) */
@media (min-width: 481px) and (max-width: 768px) {
    .player-container-mobile {
        height: calc(100vh - 80px - 300px);
        min-height: 220px;
        max-height: calc(100vh - 380px);
    }
}
.player-container-androidtv {
    position: fixed;
    inset: 0;
    z-index: 40;
    width: 100%;
    background: #000;
}

/* Video Player */
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
    overflow: hidden; /* Ensure logo stays within video bounds */
}
.video-player {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
    position: relative; /* Ensure proper stacking context */
    z-index: 1; /* Video behind logo */
}

/* Stream Loading Overlay - Netflix Style */
.stream-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    transition: opacity 0.3s ease;
}

.stream-loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
}

.stream-loading-spinner {
    position: relative;
    width: 80px;
    height: 80px;
}

.spinner-ring {
    position: absolute;
    width: 100%;
    height: 100%;
    border: 4px solid transparent;
    border-top-color: #e50914;
    border-radius: 50%;
    animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}

.spinner-ring:nth-child(1) {
    animation-delay: -0.45s;
}

.spinner-ring:nth-child(2) {
    animation-delay: -0.3s;
    border-top-color: #f40612;
}

.spinner-ring:nth-child(3) {
    animation-delay: -0.15s;
    border-top-color: #ff0a16;
}

.spinner-ring:nth-child(4) {
    border-top-color: #ff1a26;
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.stream-loading-text {
    color: #fff;
    font-size: 1.1rem;
    font-weight: 500;
    text-align: center;
    letter-spacing: 0.5px;
}

.stream-loading-text.error {
    color: #ff4444;
}

@media (max-width: 768px) {
    .stream-loading-spinner {
        width: 60px;
        height: 60px;
    }
    
    .spinner-ring {
        border-width: 3px;
    }
    
    .stream-loading-text {
        font-size: 0.95rem;
    }
}
#youtubePlayer {
    width: 100%;
    height: 100%;
    border: none;
    background: #000;
}

/* Shaka Player Logo - Positioned inside video frame */
.shaka-player-logo {
    position: absolute;
    left: 1rem;
    top: 1rem;
    z-index: 9999; /* Very high z-index to ensure it's above everything */
    max-width: 150px;
    max-height: 60px;
    opacity: 0.9;
    transition: opacity 0.3s ease, transform 0.3s ease;
    pointer-events: none;
    /* Ensure logo is inside video frame, not above container */
    margin: 0;
    padding: 0;
    /* Position within video bounds */
    transform: translateZ(0); /* Force hardware acceleration */
    display: block !important;
    visibility: visible !important;
}
.shaka-player-logo img {
    width: 100%;
    height: auto;
    object-fit: contain;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.5));
    display: block;
}
.shaka-player-logo:hover {
    opacity: 1;
}
/* Show logo in fullscreen on all devices */
/* Target when player-container is in fullscreen */
#player-container:fullscreen .shaka-player-logo,
#player-container:-webkit-full-screen .shaka-player-logo,
#player-container:-moz-full-screen .shaka-player-logo,
#player-container:-ms-fullscreen .shaka-player-logo,
.video-player-wrapper:fullscreen .shaka-player-logo,
.video-player-wrapper:-webkit-full-screen .shaka-player-logo,
.video-player-wrapper:-moz-full-screen .shaka-player-logo,
.video-player-wrapper:-ms-fullscreen .shaka-player-logo {
    left: 1.5rem;
    top: 1.5rem;
    max-width: 200px;
    max-height: 80px;
    opacity: 0.95;
    z-index: 9999 !important; /* Very high z-index to ensure it's above everything */
    display: block !important;
    visibility: visible !important;
    position: absolute !important;
}
/* Also show when document is in fullscreen */
:fullscreen .shaka-player-logo,
:-webkit-full-screen .shaka-player-logo,
:-moz-full-screen .shaka-player-logo,
:-ms-fullscreen .shaka-player-logo {
    left: 1.5rem;
    top: 1.5rem;
    max-width: 200px;
    max-height: 80px;
    opacity: 0.95;
    z-index: 9999 !important; /* Very high z-index to ensure it's above everything */
    display: block !important;
    visibility: visible !important;
    position: absolute !important;
}
/* Android Fullscreen Rotation */
.android-fullscreen-rotate {
    transform: rotate(0deg);
    transition: transform 0.3s ease;
}
/* Ensure video fills screen on Android in landscape fullscreen */
@media screen and (orientation: landscape) {
    .android-fullscreen-rotate .video-player {
        width: 100vw;
        height: 100vh;
        object-fit: cover;
    }
}

/* Smart TV optimizations */
@media (min-width: 1920px) {
    .shaka-player-logo {
        max-width: 200px;
        max-height: 80px;
    }
}
.smart-tv-fullscreen {
    width: 100vw;
    height: 100vh;
}
.smart-tv-fullscreen .video-player {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
/* TCL TV optimizations */
@media (min-width: 3840px) {
    .shaka-player-logo {
        max-width: 300px;
        max-height: 120px;
    }
}
/* Samsung TV optimizations */
@media (min-width: 1920px) and (max-width: 3840px) {
    .smart-tv-fullscreen .video-player {
        object-fit: fill;
    }
}
/* Sony Bravia optimizations */
@media (min-width: 1920px) {
    .smart-tv-fullscreen {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

/* Error Banner */
.error-banner {
    background: rgba(234,179,8,0.2);
    border-top: 1px solid rgba(234,179,8,0.5);
    padding: 1rem;
}
@media (min-width: 768px) {
    .error-banner {
        padding: 1rem 3rem;
    }
}
.error-banner-content {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.error-banner-text {
    flex: 1;
}
.error-banner-text h4 {
    color: #facc15;
    font-weight: 600;
    margin-bottom: 0.25rem;
}
.error-banner-text p {
    color: #fde047;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}
.error-banner-text .error-hint {
    color: rgba(253,224,71,0.8);
    font-size: 0.75rem;
}
.error-banner-close {
    background: transparent;
    border: none;
    color: #facc15;
    cursor: pointer;
    padding: 0.25rem;
    transition: color 0.2s;
}
.error-banner-close:hover {
    color: #fde047;
}

/* Channel Description */
.channel-description-section {
    padding: 1.5rem 1rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}
@media (min-width: 768px) {
    .channel-description-section {
        padding: 1.5rem 3rem;
    }
}
.channel-description-section h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
}
.channel-description-section p {
    color: #9ca3af;
    line-height: 1.6;
}

/* Try Another Source Section */
.try-another-source-section {
    padding: 1rem;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.3);
}
@media (min-width: 768px) {
    .try-another-source-section {
        padding: 1.5rem;
    }
}
.try-another-source-text {
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}
.try-another-source-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
}
.try-source-link {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: rgba(229,9,20,0.8);
    color: #fff;
    text-decoration: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
}
.try-source-link:hover {
    background: rgba(229,9,20,1);
    transform: scale(1.05);
    text-decoration: none;
    color: #fff;
}

/* Suggested Channels Section */
.suggested-channels-section {
    padding: 2rem 1rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}
@media (min-width: 768px) {
    .suggested-channels-section {
        padding: 2rem 3rem;
    }
}
.suggested-channels-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #fff;
}
.suggested-channels-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (min-width: 640px) {
    .suggested-channels-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 768px) {
    .suggested-channels-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (min-width: 1024px) {
    .suggested-channels-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
.suggested-channel-card {
    position: relative;
    background: #141414;
    border-radius: 0.5rem;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
    display: block;
    text-decoration: none;
    color: inherit;
}
.suggested-channel-card:hover {
    text-decoration: none;
    color: inherit;
    transform: scale(1.05);
}
.suggested-channel-logo {
    aspect-ratio: 16/9;
    background: linear-gradient(to bottom right, rgba(229,9,20,0.2), rgba(37,99,235,0.2));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.suggested-channel-logo img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 1rem;
}
.suggested-channel-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.6);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.suggested-channel-card:hover .suggested-channel-overlay {
    opacity: 1;
}
.suggested-channel-play-icon {
    background: #e50914;
    border-radius: 50%;
    padding: 0.75rem;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.suggested-channel-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: #e50914;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    z-index: 10;
}
.suggested-channel-card.premium {
    border: 2px solid rgba(251, 191, 36, 0.6);
    box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
}
.suggested-channel-card.premium:hover {
    border-color: rgba(251, 191, 36, 0.9);
    box-shadow: 0 6px 16px rgba(251, 191, 36, 0.4);
}
.suggested-channel-card.free {
    border: 2px solid rgba(16, 185, 129, 0.4);
}
.suggested-channel-card.free:hover {
    border-color: rgba(16, 185, 129, 0.6);
}
.suggested-channel-info {
    padding: 0.75rem;
}
.suggested-channel-name {
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
}
.suggested-channel-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: #9ca3af;
}
.suggested-channel-category,
.suggested-channel-country {
    padding: 0.25rem 0.5rem;
    background: rgba(255,255,255,0.1);
    border-radius: 0.25rem;
}

/* Ad Overlay Styles */
/* Video player wrapper needs to be relative for absolute positioned ads */
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}

.ad-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ad-container {
    position: relative;
    width: 100%;
    height: 100%;
    max-width: 1920px;
    max-height: 1080px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
#ad-content {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
#ad-content img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
#ad-content video {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
#ad-content iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.ad-controls {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 1rem;
    z-index: 1001;
}
.ad-countdown {
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
}
.ad-skip-btn {
    background: rgba(229, 9, 20, 0.9);
    color: #fff;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.ad-skip-btn:hover {
    background: rgba(229, 9, 20, 1);
}

/* Custom Ad Video Player - Hide native controls on all platforms (Android, Windows, Smart TV) */
.custom-ad-video-container {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.custom-ad-video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
    outline: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
/* Hide native video controls on Android Chrome/WebView */
.custom-ad-video::-webkit-media-controls {
    display: none !important;
}
.custom-ad-video::-webkit-media-controls-enclosure {
    display: none !important;
}
.custom-ad-video::-webkit-media-controls-panel {
    display: none !important;
}
.custom-ad-video::-webkit-media-controls-play-button {
    display: none !important;
}
.custom-ad-video::-webkit-media-controls-start-playback-button {
    display: none !important;
}
/* Hide controls on Firefox */
.custom-ad-video::-moz-media-controls {
    display: none !important;
}
/* Hide controls on Edge/IE */
.custom-ad-video::-ms-media-controls {
    display: none !important;
}
/* Ensure video doesn't show native controls on any platform */
.custom-ad-video[controls] {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}
.custom-ad-video[controls]::-webkit-media-controls {
    display: none !important;
}

/* Ad Overlay Styles */
/* Video player wrapper needs to be relative for absolute positioned ads */
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}

.ad-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
}
.ad-container {
    position: relative;
    width: 100%;
    height: 100%;
    max-width: 1920px;
    max-height: 1080px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
#ad-content {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}
#ad-content img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}
#ad-content video {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
#ad-content iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.ad-controls {
    position: absolute;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 1rem;
    z-index: 1001;
}
.ad-countdown {
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-weight: 600;
}
.ad-skip-btn {
    background: rgba(229, 9, 20, 0.9);
    color: #fff;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 0.5rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
}
.ad-skip-btn:hover {
    background: rgba(229, 9, 20, 1);
}

/* Error Page - Professional Netflix-style */
.error-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0a0a0a 100%);
    position: relative;
    overflow: hidden;
}
.error-page::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 50% 50%, rgba(229, 9, 20, 0.1) 0%, transparent 70%);
    pointer-events: none;
}
.error-content {
    text-align: center;
    position: relative;
    z-index: 1;
    padding: 2rem;
    max-width: 500px;
    animation: fadeInUp 0.6s ease-out;
}
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.error-icon-wrapper {
    width: 120px;
    height: 120px;
    margin: 0 auto 2rem;
    position: relative;
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}
.error-content svg {
    width: 100%;
    height: 100%;
    color: #e50914;
    filter: drop-shadow(0 0 20px rgba(229, 9, 20, 0.5));
}
.error-content h2 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #fff;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}
.error-content p {
    color: #9ca3af;
    margin-bottom: 2rem;
    font-size: 1.125rem;
    line-height: 1.6;
}
.error-content .error-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}
.error-content a {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 2rem;
    background: linear-gradient(135deg, #e50914 0%, #b30710 100%);
    color: #fff;
    text-decoration: none;
    border-radius: 0.5rem;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(229, 9, 20, 0.4);
    position: relative;
    overflow: hidden;
}
.error-content a::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}
.error-content a:hover::before {
    left: 100%;
}
.error-content a:hover {
    background: linear-gradient(135deg, #f40612 0%, #c40812 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(229, 9, 20, 0.6);
}
.error-content a:active {
    transform: translateY(0);
}
@media (max-width: 640px) {
    .error-content {
        padding: 1.5rem;
    }
    .error-icon-wrapper {
        width: 100px;
        height: 100px;
        margin-bottom: 1.5rem;
    }
    .error-content h2 {
        font-size: 1.5rem;
    }
    .error-content p {
        font-size: 1rem;
    }
    .error-content a {
        padding: 0.75rem 1.5rem;
        font-size: 0.875rem;
    }
}

/* Premium Gate Modal - Netflix Style */
.premium-gate-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.95);
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    animation: fadeIn 0.3s ease-out;
}
.premium-gate-modal {
    background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.5rem;
    max-width: 500px;
    width: 100%;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
    animation: slideUp 0.4s ease-out;
}
@media (min-width: 768px) {
    .premium-gate-modal {
        padding: 3rem;
    }
}
@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.premium-gate-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 1.5rem;
    background: linear-gradient(135deg, #e50914 0%, #b30710 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 10px 30px rgba(229, 9, 20, 0.4);
}
.premium-gate-icon svg {
    width: 40px;
    height: 40px;
    color: #fff;
}
.premium-gate-modal h2 {
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: #fff;
}
@media (min-width: 768px) {
    .premium-gate-modal h2 {
        font-size: 2rem;
    }
}
.premium-gate-modal p {
    color: #9ca3af;
    line-height: 1.6;
    margin-bottom: 2rem;
    font-size: 1rem;
}
.premium-gate-features {
    text-align: left;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 0.5rem;
    padding: 1.5rem;
    margin-bottom: 2rem;
}
.premium-gate-features h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #fff;
}
.premium-gate-features ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.premium-gate-features li {
    color: #d1d5db;
    padding: 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.9375rem;
}
.premium-gate-features li svg {
    width: 20px;
    height: 20px;
    color: #e50914;
    flex-shrink: 0;
}
.premium-gate-actions {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
@media (min-width: 640px) {
    .premium-gate-actions {
        flex-direction: row;
        justify-content: center;
    }
}
.premium-gate-btn {
    padding: 0.875rem 2rem;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 1rem;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}
.premium-gate-btn-primary {
    background: #e50914;
    color: #fff;
}
.premium-gate-btn-primary:hover {
    background: #b30710;
    transform: scale(1.02);
}
.premium-gate-btn-secondary {
    background: transparent;
    color: #9ca3af;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.premium-gate-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    border-color: rgba(255, 255, 255, 0.3);
}
    </style>
</head>
<body class="bg-black text-white">

<?php if ($error || !$channel): ?>
    <div class="error-page">
        <div class="error-content">
            <div class="error-icon-wrapper">
                <?php if ($error === 'This channel is not yet active'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 8v4"></path>
                        <path d="M12 16h.01"></path>
            </svg>
                <?php else: ?>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                    </svg>
                <?php endif; ?>
            </div>
            <h2><?php echo ($error === 'This channel is not yet active') ? 'Channel Not Active' : 'Channel Not Available'; ?></h2>
            <p><?php echo htmlspecialchars($error ?: 'Channel not found'); ?></p>
            <div class="error-actions">
                <a href="<?php echo BASE_URL; ?>/live-tv">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5"></path>
                    <path d="m12 19-7-7 7-7"></path>
                </svg>
                Back to Live TV
            </a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="tv-channel-page">
        <!-- Header -->
        <?php if (!$isAndroidTV): ?>
            <div class="sticky-header">
                <!-- Mobile: Two rows -->
                <div class="mobile-header-row1">
                    <button class="header-back-btn" onclick="handleBackToLiveTV()" aria-label="Back to Live TV">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5"></path>
                            <path d="m12 19-7-7 7-7"></path>
                        </svg>
                    </button>
                    <?php if ($channel['logo']): ?>
                        <img src="<?php echo htmlspecialchars(assetUrl($channel['logo'])); ?>" alt="<?php echo htmlspecialchars($channel['name']); ?>" class="channel-logo-header">
                    <?php endif; ?>
                    <div class="channel-info-header">
                        <h1><?php echo htmlspecialchars($channel['name']); ?></h1>
                        <p><?php echo htmlspecialchars($channel['category'] ?? 'N/A'); ?> | <?php echo htmlspecialchars($channel['country'] ?? 'N/A'); ?></p>
                    </div>
                    
                    <?php if (isLoggedIn() && !$showPremiumGate): ?>
                    <button id="favoriteBtnMobile" onclick="toggleFavorite()" 
                            class="header-back-btn" aria-label="Add to Favorites" title="Add to Favorites">
                        <i class="fas fa-heart" id="favoriteIconMobile"></i>
                    </button>
                    <?php endif; ?>
                    
                    
                    <div class="viewer-count-header" id="viewer-count-mobile">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <span id="viewer-count-mobile-text">0</span>
                    </div>
                    
                    <button class="fullscreen-btn-header" id="fullscreen-button-mobile" onclick="toggleFullscreen()" title="Enter Fullscreen">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path>
                        </svg>
                    </button>
                </div>
                
                <!-- Desktop: Single row -->
                <div class="desktop-header">
                    <button class="header-back-btn" onclick="handleBackToLiveTV()" aria-label="Back to Live TV">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5"></path>
                            <path d="m12 19-7-7 7-7"></path>
                        </svg>
                    </button>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0;">
                        <?php if ($channel['logo']): ?>
                            <img src="<?php echo htmlspecialchars(assetUrl($channel['logo'])); ?>" alt="<?php echo htmlspecialchars($channel['name']); ?>" class="channel-logo-header">
                        <?php endif; ?>
                        <div class="channel-info-header">
                            <h1><?php echo htmlspecialchars($channel['name']); ?></h1>
                            <p><?php echo htmlspecialchars($channel['category'] ?? 'N/A'); ?> | 
                            <?php echo htmlspecialchars($channel['country'] ?? 'N/A'); ?></p>
                        </div>
                        
                        <?php if (isLoggedIn() && !$showPremiumGate): ?>
                        <button id="favoriteBtnDesktop" onclick="toggleFavorite()" 
                                class="header-back-btn" aria-label="Add to Favorites" title="Add to Favorites">
                            <i class="fas fa-heart" id="favoriteIconDesktop"></i>
                        </button>
                        <?php endif; ?>
                        
                        
                        <div class="viewer-count-header" id="viewer-count-desktop">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <span id="viewer-count-desktop-text">0</span>
                        </div>
                        
                        <button class="fullscreen-btn-header" id="fullscreen-button-desktop" onclick="toggleFullscreen()" title="Enter Fullscreen">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <p id="player-error-message" class="player-error-message" style="display: none;">
                    &#9888;&#65039; Stream error &#65533; please refresh the page
                </p>
            </div>
        <?php endif; ?>

        <!-- Premium Gate Modal -->
        <?php if ($showPremiumGate): ?>
        <div class="premium-gate-overlay" id="premium-gate-modal">
            <div class="premium-gate-modal">
                <div class="premium-gate-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5"></path>
                        <path d="M2 12l10 5 10-5"></path>
                    </svg>
                </div>
                <h2>Premium Content</h2>
                <p>This channel is available exclusively for Premium subscribers.</p>
                
                <div class="premium-gate-features">
                    <h3>Upgrade to Premium and enjoy:</h3>
                    <ul>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Access to all premium channels
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Ad-free streaming experience
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            HD and 4K quality content
                        </li>
                        <li>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Exclusive premium movies and shows
                        </li>
                    </ul>
                </div>
                
                <div class="premium-gate-actions">
                    <a href="<?php echo BASE_URL; ?>/profile" class="premium-gate-btn premium-gate-btn-primary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Subscribe Now
                    </a>
                    <button onclick="handleBackToLiveTV()" class="premium-gate-btn premium-gate-btn-secondary">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5"></path>
                            <path d="m12 19-7-7 7-7"></path>
                        </svg>
                        Back to Live TV
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Player Container -->
        <?php if (!$error && $has_valid_sources && !empty($selected_source)): ?>
        <div 
            id="player-container"
            class="<?php echo $isAndroidTV ? 'player-container-androidtv' : 'player-container player-container-mobile'; ?>"
            style="<?php echo $showPremiumGate ? 'display: none;' : ''; ?>"
        >
            <!-- Main Player -->
            <div class="video-player-wrapper" id="video-wrapper">
                <!-- Shaka Player Logo -->
                <?php if (!empty($shaka_logo)): ?>
                <div class="shaka-player-logo" id="shaka-player-logo">
                    <img src="<?php echo BASE_URL . '/' . $shaka_logo; ?>" alt="Player Logo">
                </div>
                <?php endif; ?>
                
                <?php
                    $is_embed_source = !empty($selected_source) && in_array($selected_source['type'] ?? '', ['html-embed'], true);
                    $is_iframe_source = !empty($selected_source) && in_array($selected_source['type'] ?? '', ['iframe'], true);
                    $is_iframe_or_embed = $is_embed_source || $is_iframe_source;
                    // Compute current source index among active/visible sources for iframe src
                    $active_sources_for_iframe = array_filter($sources ?? [], function($s) { return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true); });
                    $active_sources_for_iframe = array_values($active_sources_for_iframe);
                    $current_source_index = 0;
                    if (!empty($active_sources_for_iframe) && !empty($selected_source)) {
                        $idx = array_search($selected_source, $active_sources_for_iframe, true);
                        if ($idx !== false) {
                            $current_source_index = (int)$idx;
                        }
                    }
                    // Construct query parameters properly for Firefox compatibility
                    $query_params = [];
                    if ($slug) {
                        $query_params['slug'] = $slug;
                    } else {
                        $query_params['id'] = intval($channel['id']);
                    }
                    $query_params['source'] = $current_source_index;
                    
                    $iframe_url = $is_iframe_source && !empty($selected_source['url']) ? htmlspecialchars($selected_source['url'], ENT_QUOTES, 'UTF-8') : '';
                    
                    $embed_source_url = url('embed-source.php?' . http_build_query($query_params, '', '&', PHP_QUERY_RFC3986));
                ?>
                <video id="videoPlayer" class="video-player" controls autoplay playsinline muted style="<?php echo $is_iframe_or_embed ? 'display: none;' : ''; ?>"></video>
                <iframe id="youtubePlayer" class="video-player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen style="display: none;"></iframe>
                <div id="html-embed-container" style="<?php echo $is_iframe_or_embed ? 'display: block;' : 'display: none;'; ?> width: 100%; height: 100%;">
                    <?php if ($is_embed_source): ?>
                        <iframe
                            id="embedFrame"
                            src="<?php echo htmlspecialchars($embed_source_url, ENT_QUOTES, 'UTF-8'); ?>"
                            style="width: 100%; height: 100%; border: 0;"
                            allowfullscreen
                            allow="autoplay; encrypted-media; picture-in-picture"
                            loading="eager"
                        ></iframe>
                    <?php elseif ($is_iframe_source): ?>
                        <iframe
                            id="embedFrame"
                            src="<?php echo $iframe_url; ?>"
                            style="width: 100%; height: 100%; border: 0;"
                            allowfullscreen
                        ></iframe>
                    <?php endif; ?>
                </div>
                
                <!-- Ad Overlay (inside player container) -->
                <div id="ad-overlay" class="ad-overlay" style="display: none;">
                    <div class="ad-container">
                        <div id="ad-content"></div>
                        <div id="ad-controls" class="ad-controls">
                            <div id="ad-countdown" class="ad-countdown"></div>
                            <button id="ad-skip-btn" class="ad-skip-btn" style="display: none;" onclick="skipAd()">
                                Skip Ad (<span id="skip-timer">5</span>s)
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Stream Loading Overlay -->
                <div id="stream-loading-overlay" class="stream-loading-overlay" style="display: none;">
                    <div class="stream-loading-content">
                        <div class="stream-loading-spinner">
                            <div class="spinner-ring"></div>
                            <div class="spinner-ring"></div>
                            <div class="spinner-ring"></div>
                            <div class="spinner-ring"></div>
                        </div>
                        <div class="stream-loading-text" id="stream-loading-text">Connecting to stream...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Try Another Source Section -->
        <?php
        // Get all active sources for "Try Another Source" links
        $active_sources_list = [];
        if (!empty($sources)) {
            $active_sources_list = array_filter($sources, function($s) {
                return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true);
            });
            $active_sources_list = array_values($active_sources_list);
        }
        ?>
        <?php if (count($active_sources_list) > 1): ?>
            <div class="try-another-source-section">
                <p class="try-another-source-text">If stream not playing video, Try Another Source:</p>
                <div class="try-another-source-links">
                    <?php foreach ($active_sources_list as $idx => $source_item): ?>
                        <?php
                            $source_num = $idx + 1;
                            $is_current = ($idx === $current_source_index);
                            $source_url = BASE_URL . '/watch-live-tv/' . (!empty($channel['slug']) ? htmlspecialchars($channel['slug']) : 'channel?id=' . $channel['id']);
                            $source_url .= '?source=' . $idx;
                        ?>
                        <?php if (!$is_current): ?>
                            <a href="<?php echo htmlspecialchars($source_url); ?>" class="try-source-link">
                                Source <?php echo $source_num; ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Error Banner -->
        <div id="error-banner" class="error-banner" style="display: none;">
            <div class="error-banner-content">
                <div class="error-banner-text">
                    <h4>Stream Error</h4>
                    <p id="error-banner-message"></p>
                    <p class="error-hint">&#128161; The stream may be temporarily unavailable. Please try refreshing the page.</p>
                </div>
                <button class="error-banner-close" onclick="document.getElementById('error-banner').style.display='none'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Channel Description -->
        <?php if (!empty($channel['description'])): ?>
            <div class="channel-description-section">
                <h3>About</h3>
                <p><?php echo htmlspecialchars($channel['description']); ?></p>
            </div>
        <?php endif; ?>

        <!-- Suggested Channels Section -->
        <?php
        // Get suggested channels from the same category (excluding current channel)
        $suggested_channels = [];
        if ($channel && !empty($channel['category'])) {
            $current_channel_id = $channel['id'];
            $current_category = $channel['category'];
            
            // Query channels from same category, excluding current channel
            $suggest_query = "SELECT id, name, slug, logo, category, country, is_premium 
                             FROM live_tv_channels 
                             WHERE is_active = 1 
                             AND category = ? 
                             AND id != ? 
                             AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
                             AND (sources LIKE '%\"url\"%' OR stream_url IS NOT NULL AND stream_url != '')
                             ORDER BY RAND() 
                             LIMIT 8";
            
            $suggest_stmt = $conn->prepare($suggest_query);
            if ($suggest_stmt) {
                $suggest_stmt->bind_param("si", $current_category, $current_channel_id);
                $suggest_stmt->execute();
                $suggest_result = $suggest_stmt->get_result();
                $suggested_channels = $suggest_result->fetch_all(MYSQLI_ASSOC);
            }
            
            // If not enough channels from same category, fill with random channels
            if (count($suggested_channels) < 6) {
                $additional_query = "SELECT id, name, slug, logo, category, country, is_premium 
                                     FROM live_tv_channels 
                                     WHERE is_active = 1 
                                     AND id != ? 
                                     AND category != ?
                                     AND (sources IS NOT NULL AND sources != '' AND sources != '[]' AND sources != 'null')
                                     AND (sources LIKE '%\"url\"%' OR stream_url IS NOT NULL AND stream_url != '')
                                     ORDER BY RAND() 
                                     LIMIT ?";
                
                $needed = 8 - count($suggested_channels);
                $additional_stmt = $conn->prepare($additional_query);
                if ($additional_stmt) {
                    $additional_stmt->bind_param("isi", $current_channel_id, $current_category, $needed);
                    $additional_stmt->execute();
                    $additional_result = $additional_stmt->get_result();
                    $additional_channels = $additional_result->fetch_all(MYSQLI_ASSOC);
                    $suggested_channels = array_merge($suggested_channels, $additional_channels);
                }
            }
            
            // Shuffle to randomize order
            shuffle($suggested_channels);
            // Limit to 8 channels
            $suggested_channels = array_slice($suggested_channels, 0, 8);
        }
        ?>
        
        <?php if (!empty($suggested_channels)): ?>
            <div class="suggested-channels-section">
                <h3 class="suggested-channels-title">Watch other channels you might like</h3>
                <div class="suggested-channels-grid">
                    <?php foreach ($suggested_channels as $suggested_channel): ?>
                        <?php
                            // Construct URL: use /watch-live-tv/{slug} if slug exists, otherwise use /tv/tv-channel.php?id={id}
                            if (!empty($suggested_channel['slug'])) {
                                $suggested_url = BASE_URL . '/watch-live-tv/' . htmlspecialchars($suggested_channel['slug']);
                            } else {
                                $suggested_url = BASE_URL . '/tv/tv-channel.php?id=' . intval($suggested_channel['id']);
                            }
                            $is_premium = (($suggested_channel['is_premium'] ?? 0) == 1);
                        ?>
                        <a href="<?php echo htmlspecialchars($suggested_url); ?>" 
                           class="suggested-channel-card <?php echo $is_premium ? 'premium' : 'free'; ?>">
                            <div class="suggested-channel-logo">
                                <?php if (!empty($suggested_channel['logo'])): ?>
                                    <img src="<?php echo htmlspecialchars(assetUrl($suggested_channel['logo'])); ?>" 
                                         alt="<?php echo htmlspecialchars($suggested_channel['name']); ?>" 
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2">
                                        <rect width="20" height="15" x="2" y="7" rx="2" ry="2"></rect>
                                        <polyline points="17 2 12 7 7 2"></polyline>
                                    </svg>
                                <?php endif; ?>
                                <div class="suggested-channel-overlay">
                                    <div class="suggested-channel-play-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                        </svg>
                                    </div>
                                </div>
                                <div class="suggested-channel-badge">LIVE</div>
                            </div>
                            <div class="suggested-channel-info">
                                <h4 class="suggested-channel-name"><?php echo htmlspecialchars($suggested_channel['name']); ?></h4>
                                <div class="suggested-channel-meta">
                                    <?php if (!empty($suggested_channel['category'])): ?>
                                        <span class="suggested-channel-category"><?php echo htmlspecialchars($suggested_channel['category']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($suggested_channel['country'])): ?>
                                        <span class="suggested-channel-country"><?php echo htmlspecialchars($suggested_channel['country']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Streaming libraries: HLS.js and dash.js -->
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script src="https://cdn.dashjs.org/latest/dash.all.min.js"></script>

    <?php
        // Prepare sources for JS: for embed-style types, don't include the heavy HTML in JS metadata,
        // we'll pass the raw embed HTML separately.
        $sources_for_js = $sources ?? [];
        $embed_html = '';
        // Only html-embed type uses embedHtmlSources (iframe uses URL directly)
        if (!empty($selected_source) && in_array($selected_source['type'] ?? '', ['html-embed'], true)) {
            $embed_html = $selected_source['url'] ?? '';
        }
        $sources_for_js = array_map(function ($s) {
            // Only html-embed type should have URL cleared (iframe keeps URL for direct loading)
            if (in_array($s['type'] ?? '', ['html-embed'], true)) {
                $s['url'] = ''; // avoid dumping full HTML in JS metadata
            }
            return $s;
        }, $sources_for_js);

        // Build active sources list (same filter/order as used for the selectors)
        $active_sources_js = array_filter($sources ?? [], function($s) { return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true); });
        $active_sources_js = array_values($active_sources_js);
        
        // Create active sources array with URLs preserved for iframe types (for dropdown handler)
        $active_sources_with_urls = array_map(function ($s) {
            // Preserve URL for iframe types, keep as-is for others
            return $s;
        }, $active_sources_js);

        // For each active source, keep full embed HTML only for html-embed type; others get empty string.
        // IMPORTANT: We'll base64-encode these before embedding in JS to avoid breaking </script> blocks.
        // Note: iframe type uses URLs directly, not embed HTML
        $embed_html_sources = array_map(function ($s) {
            if (in_array($s['type'] ?? '', ['html-embed'], true)) {
                return $s['url'] ?? '';
            }
            return '';
        }, $active_sources_js);

        // Base64-encode embed HTML for safe inclusion in inline JS
        $embed_html_sources_encoded = array_map(function ($html) {
            return base64_encode($html);
        }, $embed_html_sources);
    ?>

    <!-- Raw embed HTML for the currently selected source (if any) -->
    <textarea id="current-embed-html" style="display:none;"><?php echo $embed_html; ?></textarea>
    
    <script>
        // Global state
        const channelId = <?php echo $channel['id']; ?>;
        const channelSlug = <?php echo json_encode($slug ?? ''); ?>;
        // For JS we only need metadata; embed HTML is provided separately
        const channelSources = <?php echo json_encode($sources_for_js, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
        // Active sources with URLs preserved (index-aligned with dropdown options)
        const activeSources = <?php echo json_encode($active_sources_with_urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
        // Full embed HTML for each active source (index-aligned with active sources / selectors), base64-encoded for safety
        const embedHtmlSourcesEncoded = <?php echo json_encode($embed_html_sources_encoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS); ?>;
        // Decode base64 to get actual HTML strings in the browser
        const embedHtmlSources = Array.isArray(embedHtmlSourcesEncoded)
            ? embedHtmlSourcesEncoded.map(s => (s ? atob(s) : ''))
            : [];
        
        // Favorites functionality
        <?php if (isLoggedIn()): ?>
        const contentType = 'live_tv';
        const contentId = channelId;
        
        async function checkFavoriteStatus() {
            try {
                const iconMobile = document.getElementById('favoriteIconMobile');
                const iconDesktop = document.getElementById('favoriteIconDesktop');
                
                // Use normalized BASE_URL (without /tv) for API calls
                const response = await fetch(`<?php echo apiUrl('api/favorites.php'); ?>?content_type=${contentType}&content_id=${contentId}`);
                const data = await response.json();
                
                if (data.success && data.is_favorite) {
                    if (iconMobile) iconMobile.classList.add('text-red-500');
                    if (iconDesktop) iconDesktop.classList.add('text-red-500');
                }
            } catch (error) {
                console.error('[TV Channel] Error checking favorite status:', error);
            }
        }
        
        async function toggleFavorite() {
            const iconMobile = document.getElementById('favoriteIconMobile');
            const iconDesktop = document.getElementById('favoriteIconDesktop');
            const isFavorite = iconMobile ? iconMobile.classList.contains('text-red-500') : (iconDesktop ? iconDesktop.classList.contains('text-red-500') : false);
            
            try {
                // Use normalized BASE_URL (without /tv) for API calls
                const url = `<?php echo apiUrl('api/favorites.php'); ?>`;
                const method = isFavorite ? 'DELETE' : 'POST';
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        content_type: contentType,
                        content_id: contentId
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    if (data.is_favorite) {
                        if (iconMobile) iconMobile.classList.add('text-red-500');
                        if (iconDesktop) iconDesktop.classList.add('text-red-500');
                    } else {
                        if (iconMobile) iconMobile.classList.remove('text-red-500');
                        if (iconDesktop) iconDesktop.classList.remove('text-red-500');
                    }
                } else {
                    alert('Failed to update favorites: ' + (data.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('[TV Channel] Error toggling favorite:', error);
                alert('Failed to update favorites');
            }
        }
        
        // Check favorite status on page load
        checkFavoriteStatus();
        <?php endif; ?>
        let currentSourceIndex = <?php 
            $active_sources = array_filter($sources ?? [], function($s) { return ($s['isActive'] ?? true) && ($s['isVisible'] ?? true); });
            $active_sources = array_values($active_sources);
            echo $selected_source ? array_search($selected_source, $active_sources) : 0; 
        ?>;
        let selectedSource = <?php echo json_encode($selected_source ?? null); ?>;
        let streamUrl = selectedSource ? selectedSource.url : <?php echo json_encode($channel['stream_url'] ?? ''); ?>;
        let streamType = selectedSource ? selectedSource.type : 'html-embed';

            // For initially selected html-embed sources, prefer HTML from embedHtmlSources
            // Note: iframe type should use the URL directly, not embedHtmlSources
            if (selectedSource && selectedSource.type === 'html-embed') {
            if (Array.isArray(embedHtmlSources) && embedHtmlSources.length > 0) {
                streamUrl = embedHtmlSources[currentSourceIndex] || selectedSource.url || '';
            }
        }
        let isFullscreen = false;
        // Player instances (HLS.js / dash.js)
        let hlsInstance = null;
        let dashPlayer = null;
        let viewerUpdateInterval = null;
        let viewerPingInterval = null;
        
        // Ad management
        const adsData = <?php echo json_encode([
            'intro_ad' => $intro_ad ?? null,
            'pre_roll' => $channel_ads['pre_roll'] ?? null,
            'mid_roll' => $channel_ads['mid_roll'] ?? null,
            'end_roll' => $channel_ads['end_roll'] ?? null,
            'loop' => $channel_ads['loop'] ?? null,
            'loop_interval' => $channel_ads['loop_interval'] ?? null,
            'banner' => $channel_ads['banner'] ?? null,
            'popup' => $channel_ads['popup'] ?? null,
            'show_ads' => $show_ads,
            'is_premium' => $hasSubscription
        ]); ?>;
        
        console.log('[TV Channel] Ads data loaded:', adsData);
        
        // Get API base URL - use BASE_URL from PHP (database settings)
        const BASE_URL_JS = <?php echo json_encode(rtrim(BASE_URL, '/')); ?>;
        const FAVORITES_API = <?php echo json_encode(apiUrl('api/favorites.php')); ?>;
        const TV_VIEWER_API = <?php echo json_encode(apiUrl('tv/api/viewer_tracker.php')); ?>;
        const HLS_PROXY_URL = <?php echo json_encode(apiUrl('proxy/hls-proxy.php')); ?>;
        const getApiUrl = function(endpoint) {
            if (endpoint.includes('viewer_tracker')) {
                return TV_VIEWER_API;
            }
            return BASE_URL_JS + '/api/' + endpoint.replace(/^\//, '');
        };
        
        // Detect mobile/Android TV devices
        const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        const isAndroidTV = /Android.*TV|AFT[A-Z]|AFTM|AFTT/i.test(navigator.userAgent) || /SMART-TV|SmartHub/i.test(navigator.userAgent);
        const isMobileOrTV = isMobile || isAndroidTV;
        
        // Detect specific Smart TV brands
        const isTCL = /TCL/i.test(navigator.userAgent);
        const isBravo = /Bravo/i.test(navigator.userAgent);
        const isSamsung = /Samsung|SMART-TV|Tizen/i.test(navigator.userAgent);
        const isSony = /Sony|BRAVIA/i.test(navigator.userAgent);
        const isLG = /LG|WebOS/i.test(navigator.userAgent);
        const isSmartTV = isTCL || isBravo || isSamsung || isSony || isLG || isAndroidTV;
        
        // Detect Android device for rotation
        const isAndroid = /Android/i.test(navigator.userAgent);
        
        // Note: Previously used Shaka Player here. Now we use HLS.js and dash.js instead,
        // while keeping the same ad logic and video element so behavior stays consistent.
        
        // Convert YouTube URL to embed format
        function convertYouTubeUrl(url) {
            if (!url) return null;
            
            let embedUrl = null;
            
            // youtube.com/watch?v=...
            if (url.includes('youtube.com/watch')) {
                const match = url.match(/[?&]v=([^&]+)/);
                if (match && match[1]) {
                    embedUrl = `https://www.youtube.com/embed/${match[1]}?autoplay=1&rel=0&modestbranding=1`;
                }
            }
            // youtu.be/...
            else if (url.includes('youtu.be/')) {
                const match = url.match(/youtu\.be\/([^?&]+)/);
                if (match && match[1]) {
                    embedUrl = `https://www.youtube.com/embed/${match[1]}?autoplay=1&rel=0&modestbranding=1`;
                }
            }
            // youtube.com/embed/...
            else if (url.includes('youtube.com/embed/')) {
                embedUrl = url.includes('autoplay') ? url : `${url}${url.includes('?') ? '&' : '?'}autoplay=1&rel=0&modestbranding=1`;
            }
            
            return embedUrl;
        }
        
        // Ad management variables
        let currentAd = null;
        let adTimer = null;
        let skipTimer = null;
        let adCountdown = null;
        let introAdShown = false;
        
        // Stream loading state
        let streamLoaded = false;
        let isLoadingStream = false;
        let loadingOverlayShown = false; // Track if loading overlay has been shown for current stream
        let loadingTimeout = null; // Timeout for showing loading failed if stream takes too long

        // Helper: wrap http URLs behind local HTTPS proxy to avoid mixed-content blocking
        function getProxiedStreamUrl(originalUrl) {
            try {
                if (!originalUrl) return originalUrl;
                if (window.location.protocol === 'https:' && /^http:\/\//i.test(originalUrl)) {
                    const encoded = btoa(originalUrl);
                    return `${HLS_PROXY_URL}?u=${encodeURIComponent(encoded)}`;
                }
            } catch (e) {
                console.warn('[TV Channel] Failed to build proxied URL, using original:', e);
            }
            return originalUrl;
        }

        // Helper: decode HTML entities (for embed sources that may be stored escaped)
        function decodeHtmlEntities(str) {
            if (!str || typeof str !== 'string') return str;
            const txt = document.createElement('textarea');
            txt.innerHTML = str;
            return txt.value;
        }

        // Helper: safely render HTML embed code and execute its <script> tags
        function renderHtmlEmbed(container, html) {
            if (!container) return;
            container.innerHTML = '';

            const temp = document.createElement('div');
            temp.innerHTML = html;

            Array.from(temp.childNodes).forEach(node => {
                if (node.tagName && node.tagName.toLowerCase() === 'script') {
                    const script = document.createElement('script');
                    // Copy attributes
                    Array.from(node.attributes || []).forEach(attr => {
                        script.setAttribute(attr.name, attr.value);
                    });
                    // Inline script content
                    if (node.textContent) {
                        script.text = node.textContent;
                    }
                    container.appendChild(script);
                } else {
                    container.appendChild(node);
                }
            });
        }
        
        // Loading overlay functions
        function showLoadingOverlay(message = 'Connecting to stream...') {
            const overlay = document.getElementById('stream-loading-overlay');
            const loadingText = document.getElementById('stream-loading-text');
            if (overlay && loadingText) {
                loadingText.textContent = message;
                loadingText.classList.remove('error');
                overlay.style.display = 'flex';
                loadingOverlayShown = true;
                console.log('[TV Channel] Showing loading overlay:', message);
                
                // Set timeout to show "Loading Failed" if stream doesn't start within 90 seconds
                if (loadingTimeout) {
                    clearTimeout(loadingTimeout);
                }
                loadingTimeout = setTimeout(function() {
                    const video = document.getElementById('videoPlayer');
                    if (loadingOverlayShown && overlay.style.display !== 'none' && 
                        (!video || video.paused || video.readyState < 3)) {
                        showLoadingError('Loading Failed - Connection timeout - Your Region is Blocked');
                        console.log('[TV Channel] Loading timeout - stream did not start within 90 seconds');
                    }
                }, 90000); // 90 seconds timeout
            }
        }
        
        function hideLoadingOverlay() {
            const overlay = document.getElementById('stream-loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
                loadingOverlayShown = false;
                if (loadingTimeout) {
                    clearTimeout(loadingTimeout);
                    loadingTimeout = null;
                }
                console.log('[TV Channel] Hiding loading overlay');
            }
        }
        
        function showLoadingError(message = 'Loading Failed') {
            const overlay = document.getElementById('stream-loading-overlay');
            const loadingText = document.getElementById('stream-loading-text');
            if (overlay && loadingText) {
                loadingText.textContent = message;
                loadingText.classList.add('error');
                overlay.style.display = 'flex';
                if (loadingTimeout) {
                    clearTimeout(loadingTimeout);
                    loadingTimeout = null;
                }
                console.log('[TV Channel] Showing loading error:', message);
            }
        }
        
        // Show ad function
        function showAd(ad, callback) {
            if (!ad) {
                console.log('[TV Channel] No ad to show');
                if (callback) callback();
                return;
            }
            
            console.log('[TV Channel] Showing ad:', ad.name, ad);
            currentAd = ad;
            const adOverlay = document.getElementById('ad-overlay');
            const adContent = document.getElementById('ad-content');
            const adCountdownEl = document.getElementById('ad-countdown');
            const adSkipBtn = document.getElementById('ad-skip-btn');
            const skipTimerEl = document.getElementById('skip-timer');
            
            if (!adOverlay || !adContent) {
                console.error('[TV Channel] Ad overlay elements not found');
                if (callback) callback();
                return;
            }
            
            adContent.innerHTML = '';
            adOverlay.style.display = 'flex';
            
            // Render ad based on content type
            if (ad.content_type === 'image' && ad.logo) {
                const img = document.createElement('img');
                // Fix path - use normalized BASE_URL (without /tv)
                let adPath = ad.logo;
                if (adPath.startsWith('uploads/')) {
                    adPath = '<?php echo BASE_URL; ?>/' + adPath;
                } else if (!adPath.startsWith('http')) {
                    adPath = '<?php echo BASE_URL; ?>/' + adPath;
                }
                console.log('[TV Channel] Loading ad image from:', adPath);
                img.src = adPath;
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'contain';
                img.onerror = function() {
                    console.error('[TV Channel] Failed to load ad image:', adPath);
                    // Continue even if image fails
                    setTimeout(function() {
                        hideAd();
                        if (callback) callback();
                    }, 2000);
                };
                img.onload = function() {
                    console.log('[TV Channel] Ad image loaded successfully');
                };
                adContent.appendChild(img);
            } else if (ad.content_type === 'video' && ad.logo) {
                // Create a custom video player container with better styling
                const videoContainer = document.createElement('div');
                videoContainer.className = 'custom-ad-video-container';
                videoContainer.style.cssText = 'position: relative; width: 100%; height: 100%; background: #000; display: flex; align-items: center; justify-content: center;';
                
                const video = document.createElement('video');
                // Fix path - use normalized BASE_URL (without /tv)
                let adPath = ad.logo;
                if (adPath.startsWith('uploads/')) {
                    adPath = '<?php echo BASE_URL; ?>/' + adPath;
                } else if (!adPath.startsWith('http')) {
                    adPath = '<?php echo BASE_URL; ?>/' + adPath;
                }
                console.log('[TV Channel] Loading ad video from:', adPath);
                video.src = adPath;
                video.className = 'custom-ad-video';
                video.style.cssText = 'width: 100%; height: 100%; object-fit: contain; background: #000; outline: none;';
                video.controls = false;
                video.autoplay = true;
                video.playsInline = true;
                video.muted = false; // Try unmuted first
                video.setAttribute('playsinline', 'true');
                video.setAttribute('webkit-playsinline', 'true');
                video.setAttribute('muted', 'false');
                video.setAttribute('preload', 'auto');
                video.setAttribute('x-webkit-airplay', 'allow');
                
                // Hide native controls on all platforms
                video.controls = false;
                video.removeAttribute('controls');
                
                videoContainer.appendChild(video);
                adContent.appendChild(videoContainer);
                
                // For intro ads, be more aggressive with unmuted autoplay
                const isIntroAd = ad.type === 'intro-ad';
                let playAttempted = false;
                let unmuteInterval = null;
                let soundEnabledIndicator = null;
                
                // Function to show "Click to enable sound" indicator
                const showSoundIndicator = function() {
                    if (soundEnabledIndicator) return;
                    
                    soundEnabledIndicator = document.createElement('div');
                    soundEnabledIndicator.style.cssText = 'position: absolute; top: 20px; right: 20px; background: rgba(0,0,0,0.8); color: white; padding: 12px 20px; border-radius: 8px; cursor: pointer; z-index: 1001; font-size: 14px; display: flex; align-items: center; gap: 8px; animation: pulse 2s infinite;';
                    soundEnabledIndicator.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg> Click to enable sound';
                    
                    soundEnabledIndicator.onclick = function(e) {
                        e.stopPropagation();
                        video.muted = false;
                        video.volume = 1.0;
                        console.log('[TV Channel] Sound enabled via indicator click');
                        if (soundEnabledIndicator) {
                            soundEnabledIndicator.remove();
                            soundEnabledIndicator = null;
                        }
                    };
                    
                    if (adOverlay) {
                        adOverlay.style.position = 'relative';
                        adOverlay.appendChild(soundEnabledIndicator);
                    }
                };
                
                // Function to force unmuted play
                const forceUnmutedPlay = function() {
                    if (playAttempted && !video.paused) return;
                    
                    video.muted = false;
                    video.volume = 1.0;
                    video.removeAttribute('muted');
                    
                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.then(function() {
                            console.log('[TV Channel] Ad video playing unmuted successfully');
                            video.muted = false;
                            video.volume = 1.0;
                            playAttempted = true;
                            if (soundEnabledIndicator) {
                                soundEnabledIndicator.remove();
                                soundEnabledIndicator = null;
                            }
                        }).catch(function(error) {
                            console.log('[TV Channel] Unmuted autoplay blocked, starting muted then forcing unmute:', error);
                            // Start muted to get playback going (browsers allow this)
                            video.muted = true;
                            video.play().then(function() {
                                console.log('[TV Channel] Ad video started muted, attempting to unmute...');
                                playAttempted = true;
                                
                                // Show indicator for intro ads if muted
                                if (isIntroAd && video.muted) {
                                    setTimeout(function() {
                                        if (video.muted) {
                                            showSoundIndicator();
                                        }
                                    }, 500);
                                }
                                
                                // Aggressively try to unmute immediately and repeatedly
                                let unmuteAttempts = 0;
                                const maxAttempts = 100; // Try for 5 seconds (50ms intervals)
                                
                                const tryUnmute = function() {
                                    unmuteAttempts++;
                                    video.muted = false;
                                    video.volume = 1.0;
                                    video.removeAttribute('muted');
                                    
                                    // Check if successfully unmuted
                                    if (!video.muted) {
                                        console.log('[TV Channel] Successfully unmuted video after', unmuteAttempts, 'attempts');
                                        if (unmuteInterval) {
                                            clearInterval(unmuteInterval);
                                            unmuteInterval = null;
                                        }
                                        if (soundEnabledIndicator) {
                                            soundEnabledIndicator.remove();
                                            soundEnabledIndicator = null;
                                        }
                                        return;
                                    }
                                    
                                    // Continue trying
                                    if (unmuteAttempts >= maxAttempts) {
                                        console.log('[TV Channel] Could not force unmute after', maxAttempts, 'attempts - requires user interaction');
                                        if (unmuteInterval) {
                                            clearInterval(unmuteInterval);
                                            unmuteInterval = null;
                                        }
                                        // Show indicator if not already shown
                                        if (isIntroAd && !soundEnabledIndicator && video.muted) {
                                            showSoundIndicator();
                                        }
                                    }
                                };
                                
                                // Try immediately
                                tryUnmute();
                                
                                // Try every 50ms aggressively
                                unmuteInterval = setInterval(tryUnmute, 50);
                                
                                // Also try on various events
                                video.addEventListener('playing', tryUnmute);
                                video.addEventListener('timeupdate', function() {
                                    if (video.muted && unmuteAttempts < maxAttempts) {
                                        tryUnmute();
                                    }
                                });
                                
                            }).catch(function(err) {
                                console.error('[TV Channel] Failed to play ad video:', err);
                                if (unmuteInterval) {
                                    clearInterval(unmuteInterval);
                                    unmuteInterval = null;
                                }
                                // Auto-continue after duration if can't play
                                if (ad.duration) {
                                    setTimeout(function() {
                                        hideAd();
                                        if (callback) callback();
                                    }, ad.duration * 1000);
                                }
                            });
                        });
                    }
                };
                
                // Try to play immediately when video element is ready
                forceUnmutedPlay();
                
                // Also try when video metadata is loaded
                video.addEventListener('loadedmetadata', function() {
                    console.log('[TV Channel] Ad video metadata loaded');
                    if (video.paused || !playAttempted) {
                        forceUnmutedPlay();
                    }
                });
                
                video.addEventListener('loadeddata', function() {
                    console.log('[TV Channel] Ad video data loaded');
                    if (video.paused || !playAttempted) {
                        forceUnmutedPlay();
                    }
                });
                
                video.addEventListener('canplay', function() {
                    console.log('[TV Channel] Ad video can play');
                    if (video.paused || !playAttempted) {
                        forceUnmutedPlay();
                    }
                });
                
                video.addEventListener('canplaythrough', function() {
                    console.log('[TV Channel] Ad video can play through');
                    if (video.paused || !playAttempted) {
                        forceUnmutedPlay();
                    }
                });
                
                // Handle video end
                video.addEventListener('ended', function() {
                    console.log('[TV Channel] Ad video ended');
                    if (unmuteInterval) {
                        clearInterval(unmuteInterval);
                        unmuteInterval = null;
                    }
                    if (soundEnabledIndicator) {
                        soundEnabledIndicator.remove();
                        soundEnabledIndicator = null;
                    }
                    hideAd();
                    if (callback) callback();
                });
                
                // For intro ads, use any available user interaction to enable sound
                if (isIntroAd) {
                    const enableSoundOnInteraction = function() {
                        if (video.muted && !video.paused) {
                            video.muted = false;
                            video.volume = 1.0;
                            console.log('[TV Channel] Sound enabled via user interaction');
                            if (soundEnabledIndicator) {
                                soundEnabledIndicator.remove();
                                soundEnabledIndicator = null;
                            }
                        }
                    };
                    
                    // Try on various user events (click anywhere on page enables sound)
                    ['click', 'touchstart', 'keydown', 'mousedown'].forEach(function(eventType) {
                        document.addEventListener(eventType, enableSoundOnInteraction, { once: true });
                        if (adOverlay) {
                            adOverlay.addEventListener(eventType, enableSoundOnInteraction, { once: true });
                        }
                    });
                    
                    // Also make entire overlay clickable to enable sound
                    if (adOverlay) {
                        adOverlay.style.cursor = 'pointer';
                        const overlayClickHandler = function() {
                            if (video.muted) {
                                video.muted = false;
                                video.volume = 1.0;
                                console.log('[TV Channel] Sound enabled via overlay click');
                                if (soundEnabledIndicator) {
                                    soundEnabledIndicator.remove();
                                    soundEnabledIndicator = null;
                                }
                                adOverlay.removeEventListener('click', overlayClickHandler);
                                adOverlay.style.cursor = 'default';
                            }
                        };
                        adOverlay.addEventListener('click', overlayClickHandler);
                    }
                }
                
                // Add CSS animation for pulse effect
                if (!document.getElementById('sound-indicator-style')) {
                    const style = document.createElement('style');
                    style.id = 'sound-indicator-style';
                    style.textContent = '@keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.8; transform: scale(1.05); } }';
                    document.head.appendChild(style);
                }
            } else if (ad.content_type === 'html' && ad.content) {
                adContent.innerHTML = ad.content;
            }
            
            // Handle skip button
            const canSkip = ad.skipable == 1;
            if (canSkip && adSkipBtn) {
                adSkipBtn.style.display = 'block';
                let skipSeconds = 5;
                if (skipTimerEl) skipTimerEl.textContent = skipSeconds;
                
                skipTimer = setInterval(function() {
                    skipSeconds--;
                    if (skipTimerEl) skipTimerEl.textContent = skipSeconds;
                    if (skipSeconds <= 0) {
                        clearInterval(skipTimer);
                    }
                }, 1000);
            } else if (adSkipBtn) {
                adSkipBtn.style.display = 'none';
            }
            
            // Countdown timer
            const duration = ad.duration || 10;
            let remaining = duration;
            if (adCountdownEl) adCountdownEl.textContent = 'Ad: ' + remaining + 's';
            
            adCountdown = setInterval(function() {
                remaining--;
                if (adCountdownEl) adCountdownEl.textContent = 'Ad: ' + remaining + 's';
                if (remaining <= 0) {
                    clearInterval(adCountdown);
                    hideAd();
                    if (callback) callback();
                }
            }, 1000);
        }
        
        // Hide ad function
        function hideAd() {
            const adOverlay = document.getElementById('ad-overlay');
            if (adOverlay) adOverlay.style.display = 'none';
            
            if (adTimer) {
                clearInterval(adTimer);
                adTimer = null;
            }
            if (skipTimer) {
                clearInterval(skipTimer);
                skipTimer = null;
            }
            if (adCountdown) {
                clearInterval(adCountdown);
                adCountdown = null;
            }
            
            currentAd = null;
        }
        
        // Skip ad function
        function skipAd() {
            if (currentAd && currentAd.skipable == 1) {
                hideAd();
                // Continue with stream loading
                if (typeof continueAfterAd === 'function') {
                    continueAfterAd();
                }
            }
        }
        
        // Show banner ad (non-intrusive overlay)
        function showBannerAd(ad) {
            if (!ad) return;
            
            // Remove existing banner if any
            const existingBanner = document.getElementById('banner-ad');
            if (existingBanner) existingBanner.remove();
            
            const banner = document.createElement('div');
            banner.id = 'banner-ad';
            banner.style.cssText = 'position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); z-index: 999; max-width: 90%; max-height: 150px; background: rgba(0,0,0,0.8); padding: 10px; border-radius: 8px; cursor: pointer;';
            
            // Close button
            const closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&#65533;';
            closeBtn.style.cssText = 'position: absolute; top: 5px; right: 5px; background: rgba(255,255,255,0.3); border: none; color: white; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 18px; line-height: 1;';
            closeBtn.onclick = function(e) {
                e.stopPropagation();
                banner.remove();
            };
            banner.appendChild(closeBtn);
            
            // Ad content
            const adContent = document.createElement('div');
            if (ad.content_type === 'image' && ad.logo) {
                const img = document.createElement('img');
                let adPath = ad.logo;
                if (adPath.startsWith('uploads/')) {
                    adPath = '<?php echo BASE_URL; ?>/' + adPath;
                } else if (!adPath.startsWith('http')) {
                    adPath = '<?php echo BASE_URL; ?>/' + adPath;
                }
                img.src = adPath;
                img.style.cssText = 'max-width: 100%; max-height: 130px; object-fit: contain; display: block;';
                adContent.appendChild(img);
            } else if (ad.content_type === 'html' && ad.content) {
                adContent.innerHTML = ad.content;
            }
            banner.appendChild(adContent);
            
            document.body.appendChild(banner);
            
            // Auto-hide after duration
            if (ad.duration && ad.duration > 0) {
                setTimeout(function() {
                    if (banner.parentNode) banner.remove();
                }, ad.duration * 1000);
            }
        }
        
        // Show popup ad (modal overlay)
        function showPopupAd(ad) {
            if (!ad || currentAd) return; // Don't show if another ad is showing
            
            console.log('[TV Channel] Showing popup ad:', ad.name);
            showAd(ad, function() {
                console.log('[TV Channel] Popup ad finished');
            });
        }
        
        // Continue after ad function
        function continueAfterAd() {
            // This will be called after intro/pre-roll ads
            loadActualStream();
        }
        
        // Load stream (with ad checks) - this is the main entry point
        function loadStream() {
            <?php if ($showPremiumGate): ?>
            // Don't load stream if premium gate is shown
            return;
            <?php endif; ?>
            
            console.log('[TV Channel] loadStream called, adsData:', adsData);
            
            // Show intro ad first (plays to everyone)
            if (adsData.intro_ad && !introAdShown) {
                console.log('[TV Channel] Showing intro ad');
                introAdShown = true;
                showAd(adsData.intro_ad, function() {
                    console.log('[TV Channel] Intro ad finished');
                    // After intro ad, show pre-roll if free user
                    if (adsData.show_ads && adsData.pre_roll) {
                        console.log('[TV Channel] Showing pre-roll ad');
                        showAd(adsData.pre_roll, function() {
                            console.log('[TV Channel] Pre-roll ad finished');
                            continueAfterAd();
                        });
                    } else {
                        continueAfterAd();
                    }
                });
                return;
            }
            
            // Show pre-roll ad for free users (if no intro ad)
            if (adsData.show_ads && adsData.pre_roll && !introAdShown) {
                console.log('[TV Channel] Showing pre-roll ad (no intro)');
                showAd(adsData.pre_roll, function() {
                    continueAfterAd();
                });
                return;
            }
            
            // No ads, load stream directly
            console.log('[TV Channel] No ads, loading stream directly');
            continueAfterAd();
        }
        
        // Load actual stream (without ads)
        function loadActualStream() {
            <?php if ($showPremiumGate): ?>
            // Don't load stream if premium gate is shown
            return;
            <?php endif; ?>
            
            // Prevent multiple simultaneous loads
            if (isLoadingStream || streamLoaded) {
                console.log('[TV Channel] Stream already loaded or loading, skipping');
                return;
            }
            
            if (!streamUrl) {
                handleSourceError('No stream URL available');
                return;
            }
            
            const video = document.getElementById('videoPlayer');
            const youtubeIframe = document.getElementById('youtubePlayer');
            
            if (!video || !youtubeIframe) return;
            
            isLoadingStream = true;
            loadingOverlayShown = false; // Reset for new stream load
            console.log('[TV Channel] Starting to load actual stream');
            
            // Show loading overlay
            showLoadingOverlay('Connecting to stream...');
            
            // Setup mid-roll, loop, and end-roll ads for free users
            console.log('[TV Channel] Setting up ads. show_ads:', adsData.show_ads, 'loop:', adsData.loop, 'loop_interval:', adsData.loop_interval);
            console.log('[TV Channel] is_premium:', adsData.is_premium);
            
            // Debug: If loop ad is configured but show_ads is false, log why
            if (adsData.loop && !adsData.show_ads) {
                console.warn('[TV Channel] Loop ad is configured but show_ads is false. User appears to be premium. Check subscription status in database.');
                console.warn('[TV Channel] To fix: Make sure user has subscription_type = "free" and subscription_expires_at is NULL or in the past.');
            }
            
            if (adsData.show_ads) {
                // Mid-roll ad (after 30 seconds)
                if (adsData.mid_roll) {
                    const midRollHandler = function() {
                        if (video.currentTime >= 30 && !video.midRollAdShown) {
                            video.midRollAdShown = true;
                            
                            // Save current playback position before pausing
                            let savedCurrentTime = null;
                            if (isFinite(video.duration) && video.duration > 0) {
                                savedCurrentTime = video.currentTime;
                                console.log('[TV Channel] Mid-roll ad: Saved playback position:', savedCurrentTime, 'seconds');
                            }
                            
                            video.pause();
                            showAd(adsData.mid_roll, function() {
                                // Restore playback position if it was saved
                                if (savedCurrentTime !== null && isFinite(video.duration) && video.duration > 0) {
                                    console.log('[TV Channel] Mid-roll ad: Restoring playback position to:', savedCurrentTime, 'seconds');
                                    video.currentTime = savedCurrentTime;
                                }
                                
                                video.play().then(() => {
                                    console.log('[TV Channel] Playback resumed after mid-roll ad');
                                }).catch(e => {
                                    console.error('[TV Channel] Error resuming playback after mid-roll ad:', e);
                                });
                            });
                        }
                    };
                    video.addEventListener('timeupdate', midRollHandler, { once: false });
                }
                
                // Loop ad (every N seconds during playback)
                if (adsData.loop && adsData.loop_interval) {
                    let lastLoopAdTime = 0;
                    let loopAdInterval = null;
                    let loopAdStartTime = null; // Track when playback actually started
                    let loopAdSetupDone = false;
                    
                    console.log('[TV Channel] Setting up loop ad, interval:', adsData.loop_interval, 'seconds');
                    console.log('[TV Channel] Loop ad data:', adsData.loop);
                    
                    // Wait for video to be ready and playing
                    const setupLoopAd = function() {
                        if (loopAdSetupDone) {
                            console.log('[TV Channel] Loop ad already set up, skipping');
                            return; // Already set up
                        }
                        
                        console.log('[TV Channel] Setting up loop ad interval timer');
                        loopAdSetupDone = true;
                        
                        // Use setInterval for more reliable timing (works for all player types)
                        loopAdInterval = setInterval(function() {
                            // Only show if video is playing and no other ad is showing
                            if (video.paused || currentAd) {
                                return;
                            }
                            
                            // For live streams, duration might be Infinity or NaN, so check if video is ready
                            if (video.readyState < 2) {
                                return; // Video not ready yet
                            }
                            
                            // Track start time for live streams (which don't have a real currentTime progression)
                            if (loopAdStartTime === null) {
                                loopAdStartTime = Date.now();
                                console.log('[TV Channel] Loop ad start time set:', loopAdStartTime);
                            }
                            
                            // Get current time - for live streams, use elapsed time since start
                            let currentTime;
                            if (isFinite(video.duration) && video.duration > 0) {
                                // Regular video with duration
                                currentTime = Math.floor(video.currentTime);
                            } else {
                                // Live stream - use elapsed time since playback started
                                currentTime = Math.floor((Date.now() - loopAdStartTime) / 1000);
                            }
                            
                            const timeSinceLastAd = currentTime - lastLoopAdTime;
                            
                            // Debug log every 5 seconds
                            if (currentTime % 5 === 0 && currentTime > 0) {
                                console.log('[TV Channel] Loop ad check - currentTime:', currentTime, 'lastLoopAdTime:', lastLoopAdTime, 'timeSinceLastAd:', timeSinceLastAd, 'interval:', adsData.loop_interval);
                            }
                            
                            // Show loop ad every N seconds (but not at 0 seconds and ensure minimum interval)
                            if (currentTime > 0 && timeSinceLastAd >= adsData.loop_interval) {
                                console.log('[TV Channel] Loop ad triggered at', currentTime, 'seconds (interval:', adsData.loop_interval, 's)');
                                lastLoopAdTime = currentTime;
                                const wasPlaying = !video.paused;
                                
                                // Save current playback position before pausing
                                let savedCurrentTime = null;
                                if (isFinite(video.duration) && video.duration > 0) {
                                    // For regular videos, save the exact currentTime
                                    savedCurrentTime = video.currentTime;
                                    console.log('[TV Channel] Saved playback position:', savedCurrentTime, 'seconds');
                                } else {
                                    // For live streams, we'll just resume playback
                                    console.log('[TV Channel] Live stream detected, will resume playback after ad');
                                }
                                
                                video.pause();
                                showAd(adsData.loop, function() {
                                    if (wasPlaying) {
                                        // Restore playback position if it was saved
                                        if (savedCurrentTime !== null && isFinite(video.duration) && video.duration > 0) {
                                            console.log('[TV Channel] Restoring playback position to:', savedCurrentTime, 'seconds');
                                            video.currentTime = savedCurrentTime;
                                        }
                                        
                                        // Resume playback
                                        video.play().then(() => {
                                            console.log('[TV Channel] Playback resumed after loop ad');
                                        }).catch(e => {
                                            console.error('[TV Channel] Error resuming playback:', e);
                                        });
                                    }
                                });
                            }
                        }, 1000); // Check every second
                    };
                    
                    // Set up when video starts playing
                    const loopAdPlayHandler = function() {
                        console.log('[TV Channel] Video play event fired, setting up loop ad');
                        if (loopAdStartTime === null) {
                            loopAdStartTime = Date.now();
                        }
                        setupLoopAd();
                    };
                    video.addEventListener('play', loopAdPlayHandler, { once: false });
                    
                    // Also set up immediately if video is already playing
                    if (!video.paused && video.readyState >= 2) {
                        console.log('[TV Channel] Video already playing, setting up loop ad immediately');
                        setupLoopAd();
                    }
                    
                    // Set up after a short delay to ensure video is ready
                    setTimeout(function() {
                        if (!loopAdSetupDone && video.readyState >= 2) {
                            console.log('[TV Channel] Setting up loop ad after delay');
                            setupLoopAd();
                        }
                    }, 2000);
                    
                    // Clean up interval when page unloads
                    window.addEventListener('beforeunload', function() {
                        if (loopAdInterval) {
                            clearInterval(loopAdInterval);
                            loopAdInterval = null;
                        }
                    });
                } else {
                    console.log('[TV Channel] Loop ad not set up - loop:', adsData.loop, 'interval:', adsData.loop_interval);
                }
                
                // Banner ad (displays as overlay, non-intrusive)
                if (adsData.banner) {
                    console.log('[TV Channel] Setting up banner ad');
                    showBannerAd(adsData.banner);
                }
                
                // Popup ad (displays as modal, shows once after a delay)
                if (adsData.popup) {
                    console.log('[TV Channel] Setting up popup ad');
                    // Show popup ad after 60 seconds of playback
                    let popupShown = false;
                    const popupHandler = function() {
                        if (!popupShown && video.currentTime >= 60) {
                            popupShown = true;
                            showPopupAd(adsData.popup);
                        }
                    };
                    video.addEventListener('timeupdate', popupHandler, { once: false });
                }
                
            }
            
            // End-roll ad (when video/stream ends) - works for all users if configured
            if (adsData.end_roll) {
                const endRollHandler = function() {
                    console.log('[TV Channel] Video ended, showing end-roll ad');
                    showAd(adsData.end_roll, function() {
                        console.log('[TV Channel] End-roll ad finished');
                        // Optionally reload or show message
                    });
                };
                video.addEventListener('ended', endRollHandler, { once: false });
                
                // Also handle when user pauses for extended period (for live streams that don't end)
                let pauseStartTime = null;
                const pauseHandler = function() {
                    pauseStartTime = Date.now();
                };
                const playResumeHandler = function() {
                    pauseStartTime = null;
                };
                video.addEventListener('pause', pauseHandler, { once: false });
                video.addEventListener('play', playResumeHandler, { once: false });
                
                // Check every 5 seconds if paused for more than 30 seconds
                const pauseCheckInterval = setInterval(function() {
                    if (pauseStartTime && (Date.now() - pauseStartTime) > 30000) {
                        // User paused for 30+ seconds, show end-roll ad
                        if (!currentAd) {
                            console.log('[TV Channel] Stream paused for 30+ seconds, showing end-roll ad');
                            pauseStartTime = null; // Reset to prevent multiple triggers
                            showAd(adsData.end_roll, function() {
                                console.log('[TV Channel] End-roll ad finished');
                            });
                        }
                    }
                }, 5000);
                
                // Clean up interval on page unload
                window.addEventListener('beforeunload', function() {
                    clearInterval(pauseCheckInterval);
                });
            }
            
            console.log('[TV Channel] Loading stream:', streamUrl.substring(0, 100) + '...');
            console.log('[TV Channel] Stream type:', streamType);
            
            // Cleanup existing streaming instances
            if (hlsInstance) {
                try {
                    hlsInstance.destroy();
                } catch (e) {
                    console.warn('[TV Channel] Error destroying HLS.js instance:', e);
                }
                hlsInstance = null;
            }
            if (dashPlayer) {
                try {
                    dashPlayer.reset();
                } catch (e) {
                    console.warn('[TV Channel] Error resetting dash.js player:', e);
                }
                dashPlayer = null;
            }
            
            // Reset error state
            document.getElementById('player-error-message').style.display = 'none';
            document.getElementById('error-banner').style.display = 'none';
            
            // Check if YouTube
            const isYouTube = streamUrl.includes('youtube.com') || streamUrl.includes('youtu.be');
            
            // Mark stream as loaded for YouTube (iframe loads asynchronously)
            if (isYouTube) {
                streamLoaded = true;
                isLoadingStream = false;
            }

            // Detect HTML embed source types (uses embed-source.php)
            const htmlEmbedContainer = document.getElementById('html-embed-container');
            const isHtmlEmbed = streamType === 'html-embed';
            
            // Detect iframe type (loads URL directly, not through embed-source.php)
            const isIframeType = streamType === 'iframe';

            // For iframe type, load URL directly in iframe
            if (isIframeType && streamUrl) {
                console.log('[TV Channel] Iframe source detected - loading URL directly:', streamUrl);
                
                if (htmlEmbedContainer) {
                    htmlEmbedContainer.style.display = 'block';
                    // Get or create the embedFrame iframe
                    let embedFrame = document.getElementById('embedFrame');
                    if (!embedFrame) {
                        embedFrame = document.createElement('iframe');
                        embedFrame.id = 'embedFrame';
                        embedFrame.style.width = '100%';
                        embedFrame.style.height = '100%';
                        embedFrame.style.border = '0';
                        embedFrame.setAttribute('allowfullscreen', '');
                        embedFrame.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture');
                        htmlEmbedContainer.innerHTML = '';
                        htmlEmbedContainer.appendChild(embedFrame);
                    }
                    embedFrame.src = streamUrl;
                }

                // Hide JS-based players
                video.style.display = 'none';
                youtubeIframe.style.display = 'none';

                streamLoaded = true;
                isLoadingStream = false;
                hideLoadingOverlay();
                return;
            }

            // For HTML embed sources, use the iframe inside #html-embed-container
            // and skip JS video player setup.
            if (isHtmlEmbed) {
                console.log('[TV Channel] HTML embed source detected - using iframe embed, skipping JS player loading');

                if (htmlEmbedContainer) {
                    htmlEmbedContainer.style.display = 'block';
                }

                // Hide JS-based players
                video.style.display = 'none';
                youtubeIframe.style.display = 'none';

                streamLoaded = true;
                isLoadingStream = false;
                hideLoadingOverlay();
                return;
            }

            // If current source is not an HTML embed or iframe, make sure embed container is hidden/cleared
            if (htmlEmbedContainer && !isHtmlEmbed && !isIframeType) {
                htmlEmbedContainer.style.display = 'none';
                htmlEmbedContainer.innerHTML = '';
            }
            
            // Check for open-window protocol - open in new tab
            if (streamType === 'open-window') {
                console.log('[TV Channel] Detected open-window source, opening in new window');
                window.open(streamUrl, '_blank', 'noopener,noreferrer');
                // Show a message to user
                handleSourceError('Opening stream in new window...');
                return;
            }
            
            if (isYouTube) {
                console.log('[TV Channel] Detected YouTube source');
                
                // Hide HTML embed container for YouTube
                if (htmlEmbedContainer) {
                    htmlEmbedContainer.style.display = 'none';
                    htmlEmbedContainer.innerHTML = '';
                }
                
                video.style.display = 'none';
                youtubeIframe.style.display = 'block';
                
                const embedUrl = convertYouTubeUrl(streamUrl);
                
                if (!embedUrl) {
                    console.error('[TV Channel] Invalid YouTube URL:', streamUrl);
                    handleSourceError('Invalid YouTube URL');
                    return;
                }
                
                console.log('[TV Channel] Loading YouTube embed:', embedUrl);
                hideLoadingOverlay(); // Hide loading overlay for YouTube (YouTube handles its own loading)
                youtubeIframe.src = embedUrl;
                
                youtubeIframe.onload = function() {
                    console.log('[TV Channel] YouTube iframe loaded successfully');
                };
                
                youtubeIframe.onerror = function() {
                    console.error('[TV Channel] YouTube iframe load error');
                    showLoadingError('Loading Failed');
                    handleSourceError('Failed to load YouTube video');
                };
                
                return;
            }
            
            // For non-YouTube, non-HTML-embed sources
            youtubeIframe.style.display = 'none';
            youtubeIframe.src = '';
            video.style.display = 'block';

            // Use original URL for protocol detection and logs
            const originalStreamUrl = streamUrl || '';
            // Final URL that will actually be loaded by the player (may be proxied)
            const effectiveStreamUrl = getProxiedStreamUrl(originalStreamUrl);

            // Check source type and URL for streaming protocols
            const isHLS = streamType === 'm3u8' || streamType === 'hls' || originalStreamUrl.includes('.m3u8');
            const isDASH = streamType === 'dash' || originalStreamUrl.includes('.mpd');
            const isM3U = streamType === 'm3u' || originalStreamUrl.includes('.m3u');
            const isStreamingProtocol = isHLS || isDASH || isM3U || streamType === 'rtmp' || streamType === 'rtsp';
            
            console.log('[TV Channel] Player decision (HLS/DASH/native):', {
                isHLS,
                isDASH,
                isM3U,
                isStreamingProtocol,
                streamType,
                originalUrlSample: originalStreamUrl.substring(0, 80),
                effectiveUrlSample: effectiveStreamUrl.substring(0, 80)
            });
            
            // Prefer HLS.js for HLS streams
            if (isHLS && typeof Hls !== 'undefined' && Hls.isSupported()) {
                console.log('[TV Channel] Using HLS.js for HLS stream', effectiveStreamUrl);
                hlsInstance = new Hls();
                
                hlsInstance.on(Hls.Events.ERROR, function (event, data) {
                    console.error('[TV Channel] HLS.js error:', data);
                    if (data.fatal) {
                        switch (data.type) {
                            case Hls.ErrorTypes.NETWORK_ERROR:
                                // Try to recover from network error
                                try {
                                    hlsInstance.startLoad();
                                } catch (e) {
                                    // If recovery fails, show error
                                    showLoadingError('Loading Failed - Network Error');
                                    handleSourceError('Fatal HLS error: ' + data.details);
                                }
                                break;
                            case Hls.ErrorTypes.MEDIA_ERROR:
                                try {
                                    hlsInstance.recoverMediaError();
                                } catch (e) {
                                    // If recovery fails, show error
                                    showLoadingError('Loading Failed - Media Error');
                                    handleSourceError('Fatal HLS error: ' + data.details);
                                }
                                break;
                            default:
                                hlsInstance.destroy();
                                hlsInstance = null;
                                showLoadingError('Loading Failed');
                                handleSourceError('Fatal HLS error: ' + data.details);
                                break;
                        }
                    }
                });
                
                hlsInstance.loadSource(effectiveStreamUrl);
                hlsInstance.attachMedia(video);
                
                hlsInstance.on(Hls.Events.MANIFEST_PARSED, function () {
                    console.log('[TV Channel] HLS manifest parsed, starting playback');
                    streamLoaded = true;
                    isLoadingStream = false;
                    
                    // Ensure video is muted for autoplay (browser requirement)
                    video.muted = true;
                    
                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.then(() => {
                            console.log('[TV Channel] HLS video is playing');
                            hideLoadingOverlay(); // Hide loading overlay when video starts playing
                            // Unmute after playback starts (autoplay policy allows muted autoplay)
                            setTimeout(() => {
                                video.muted = false;
                                console.log('[TV Channel] HLS video unmuted');
                            }, 500);
                        }).catch(e => {
                            console.error('[TV Channel] Error playing HLS video:', e);
                            if (e.name === 'NotAllowedError') {
                                console.log('[TV Channel] Autoplay blocked for HLS, trying muted autoplay');
                                video.muted = true;
                                video.play().then(() => {
                                    console.log('[TV Channel] HLS video playing muted');
                                    hideLoadingOverlay(); // Hide loading overlay when video starts playing
                                    setTimeout(() => {
                                        video.muted = false;
                                        console.log('[TV Channel] HLS video unmuted after muted autoplay');
                                    }, 1000);
                                }).catch(e2 => {
                                    console.error('[TV Channel] Muted autoplay for HLS also blocked:', e2);
                                    showLoadingError('Loading Failed - Autoplay blocked');
                                });
                            } else {
                                showLoadingError('Loading Failed');
                            }
                        });
                    }
                });
                
                // Also hide overlay when video actually starts playing (in case of delayed play)
                video.addEventListener('play', function hideHLSLoading() {
                    if (loadingOverlayShown && streamLoaded) {
                        hideLoadingOverlay();
                        video.removeEventListener('play', hideHLSLoading);
                    }
                }, { once: true });
                
                // Also hide overlay when video has enough data to play (backup)
                video.addEventListener('canplay', function hideHLSLoadingOnCanPlay() {
                    if (loadingOverlayShown && streamLoaded && !video.paused) {
                        hideLoadingOverlay();
                        video.removeEventListener('canplay', hideHLSLoadingOnCanPlay);
                    }
                }, { once: true });
            }
            // Use dash.js for MPEG-DASH streams
            else if (isDASH && typeof dashjs !== 'undefined' && dashjs && dashjs.MediaPlayer) {
                console.log('[TV Channel] Using dash.js for DASH stream');
                dashPlayer = dashjs.MediaPlayer().create();
                
                // Listen for DASH player events
                dashPlayer.on('streamInitialized', function() {
                    console.log('[TV Channel] DASH stream initialized');
                });
                
                dashPlayer.on('error', function(error) {
                    console.error('[TV Channel] DASH.js error:', error);
                    if (error.error && error.error.code === dashjs.MediaPlayer.errors.MANIFEST_LOAD_ERROR) {
                        showLoadingError('Loading Failed - Manifest Error');
                        handleSourceError('DASH manifest load error');
                    } else if (error.error && error.error.code === dashjs.MediaPlayer.errors.NETWORK_ERROR) {
                        showLoadingError('Loading Failed - Network Error');
                        handleSourceError('DASH network error');
                    } else {
                        showLoadingError('Loading Failed');
                        handleSourceError('DASH playback error');
                    }
                });
                
                dashPlayer.initialize(video, streamUrl, true);
                
                streamLoaded = true;
                isLoadingStream = false;
                
                // Ensure video is muted for autoplay (browser requirement)
                video.muted = true;
                
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('[TV Channel] DASH video is playing');
                        hideLoadingOverlay(); // Hide loading overlay when video starts playing
                        // Unmute after playback starts (autoplay policy allows muted autoplay)
                        setTimeout(() => {
                            video.muted = false;
                            console.log('[TV Channel] DASH video unmuted');
                        }, 500);
                    }).catch(e => {
                        console.error('[TV Channel] Error playing DASH video:', e);
                        if (e.name === 'NotAllowedError') {
                            console.log('[TV Channel] Autoplay blocked for DASH, trying muted autoplay');
                            video.muted = true;
                            video.play().then(() => {
                                console.log('[TV Channel] DASH video playing muted');
                                hideLoadingOverlay(); // Hide loading overlay when video starts playing
                                setTimeout(() => {
                                    video.muted = false;
                                    console.log('[TV Channel] DASH video unmuted after muted autoplay');
                                }, 1000);
                            }).catch(e2 => {
                                console.error('[TV Channel] Muted autoplay for DASH also blocked:', e2);
                                showLoadingError('Loading Failed - Autoplay blocked');
                            });
                        } else {
                            showLoadingError('Loading Failed');
                        }
                    });
                }
                
                // Also hide overlay when video actually starts playing (in case of delayed play)
                video.addEventListener('play', function hideDASHLoading() {
                    if (loadingOverlayShown && streamLoaded) {
                        hideLoadingOverlay();
                        video.removeEventListener('play', hideDASHLoading);
                    }
                }, { once: true });
                
                // Also hide overlay when video has enough data to play (backup)
                video.addEventListener('canplay', function hideDASHLoadingOnCanPlay() {
                    if (loadingOverlayShown && streamLoaded && !video.paused) {
                        hideLoadingOverlay();
                        video.removeEventListener('canplay', hideDASHLoadingOnCanPlay);
                    }
                }, { once: true });
            }
            // Fallback: use native video element for other protocols or when libraries are unavailable
            else {
                console.log('[TV Channel] Using native video player (HLS.js/dash.js not used or not needed)');
                video.src = streamUrl;
                video.load();
                
                // Mark as loaded after setting src
                streamLoaded = true;
                isLoadingStream = false;
                
                // Ensure video is muted for autoplay (browser requirement)
                video.muted = true;
                
                // Ensure video plays after loading
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.then(() => {
                        console.log('[TV Channel] Video is playing (native)');
                        hideLoadingOverlay(); // Hide loading overlay when video starts playing
                        // Unmute after playback starts (autoplay policy allows muted autoplay)
                        setTimeout(() => {
                            video.muted = false;
                            console.log('[TV Channel] Native video unmuted');
                        }, 500);
                    }).catch(e => {
                        console.error('[TV Channel] Error playing video (native):', e);
                        if (e.name === 'NotAllowedError') {
                            console.log('[TV Channel] Autoplay blocked, trying muted autoplay (native)');
                            video.muted = true;
                            video.play().then(() => {
                                console.log('[TV Channel] Video playing muted (native)');
                                hideLoadingOverlay(); // Hide loading overlay when video starts playing
                                setTimeout(() => {
                                    video.muted = false;
                                }, 1000);
                            }).catch(e2 => {
                                console.error('[TV Channel] Muted autoplay also blocked (native):', e2);
                                showLoadingError('Loading Failed - Autoplay blocked');
                            });
                        } else {
                            showLoadingError('Loading Failed');
                        }
                    });
                }
                
                // Also hide overlay when video actually starts playing (in case of delayed play)
                video.addEventListener('play', function hideNativeLoading() {
                    if (loadingOverlayShown && streamLoaded) {
                        hideLoadingOverlay();
                        video.removeEventListener('play', hideNativeLoading);
                    }
                }, { once: true });
                
                // Also hide overlay when video has enough data to play (backup)
                video.addEventListener('canplay', function hideNativeLoadingOnCanPlay() {
                    if (loadingOverlayShown && streamLoaded && !video.paused) {
                        hideLoadingOverlay();
                        video.removeEventListener('canplay', hideNativeLoadingOnCanPlay);
                    }
                }, { once: true });
            }
            
            // Native video error handler
            video.addEventListener('error', function() {
                console.error('[TV Channel] Video playback error');
                showLoadingError('Loading Failed');
                handleSourceError('Video playback error');
            });
            
            // Add click handler to start playback if autoplay fails (only for non-YouTube)
            if (!isYouTube) {
                let autoplayFailed = false;
                let playOverlayShown = false;
                
                video.addEventListener('play', function() {
                    autoplayFailed = false;
                    // Remove play overlay if it exists
                    const existingOverlay = document.getElementById('play-overlay');
                    if (existingOverlay) {
                        existingOverlay.remove();
                    }
                }, { once: false });
                
                // If video doesn't start playing within 2 seconds, show a play button overlay
                setTimeout(function() {
                    if (video.paused && !autoplayFailed && !playOverlayShown && !isYouTube) {
                        console.log('[TV Channel] Video did not autoplay, adding click-to-play handler');
                        autoplayFailed = true;
                        playOverlayShown = true;
                        
                        // Add a play button overlay
                        const playOverlay = document.createElement('div');
                        playOverlay.id = 'play-overlay';
                        playOverlay.style.cssText = 'position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.7); z-index: 100; cursor: pointer;';
                        playOverlay.innerHTML = '<div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(229, 9, 20, 0.9); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;"><i class="fas fa-play"></i></div>';
                        playOverlay.addEventListener('click', function() {
                            const playPromise = video.play();
                            if (playPromise !== undefined) {
                                playPromise.then(() => {
                                    playOverlay.remove();
                                    autoplayFailed = false;
                                    playOverlayShown = false;
                                }).catch(e => {
                                    console.error('[TV Channel] Error playing video after click:', e);
                                });
                            }
                        });
                        
                        const videoWrapper = document.getElementById('video-wrapper');
                        if (videoWrapper) {
                            videoWrapper.style.position = 'relative';
                            videoWrapper.appendChild(playOverlay);
                        }
                    }
                }, 2000);
            }
            
            // End-roll ad for native video (if not using Shaka)
            if (adsData.show_ads && adsData.end_roll && !shakaPlayer) {
                video.addEventListener('ended', function() {
                    console.log('[TV Channel] Native video ended, showing end-roll ad');
                    showAd(adsData.end_roll, function() {
                        console.log('[TV Channel] End-roll ad finished');
                    });
                }, { once: false });
            }
        }
        
        // Handle source error
        function handleSourceError(message) {
            document.getElementById('player-error-message').style.display = 'block';
            document.getElementById('error-banner').style.display = 'block';
            document.getElementById('error-banner-message').textContent = message;
        }
        
        // Back to Live TV
        function handleBackToLiveTV() {
            window.location.href = '<?php echo BASE_URL; ?>/live-tv';
        }
        
        // Fullscreen toggle
        function toggleFullscreen() {
            const container = document.getElementById('player-container');
            if (!document.fullscreenElement) {
                if (container.requestFullscreen) {
                    container.requestFullscreen();
                } else if (container.mozRequestFullScreen) {
                    container.mozRequestFullScreen();
                } else if (container.webkitRequestFullscreen) {
                    container.webkitRequestFullscreen();
                } else if (container.msRequestFullscreen) {
                    container.msRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }
        
        // Fullscreen change handler
        document.addEventListener('fullscreenchange', () => {
            isFullscreen = !!document.fullscreenElement;
            const maximizeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>';
            const minimizeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>';
            const btnMobile = document.getElementById('fullscreen-button-mobile');
            const btnDesktop = document.getElementById('fullscreen-button-desktop');
            if (btnMobile) btnMobile.innerHTML = isFullscreen ? minimizeIcon : maximizeIcon;
            if (btnDesktop) btnDesktop.innerHTML = isFullscreen ? minimizeIcon : maximizeIcon;
            
            // Ensure logo is visible in fullscreen and positioned correctly
            const logoElement = document.getElementById('shaka-player-logo');
            if (logoElement) {
                // Logo should always be visible, but ensure it's shown in fullscreen
                logoElement.style.display = 'block';
                logoElement.style.visibility = 'visible';
                logoElement.style.position = 'absolute'; // Ensure absolute positioning
                logoElement.style.zIndex = '9999'; // Very high z-index to ensure it's above everything in fullscreen
                logoElement.style.opacity = isFullscreen ? '0.95' : '0.9';
                // Ensure it's positioned correctly in fullscreen
                if (isFullscreen) {
                    logoElement.style.left = '1.5rem';
                    logoElement.style.top = '1.5rem';
                    logoElement.style.maxWidth = '200px';
                    logoElement.style.maxHeight = '80px';
                    // Force visibility in fullscreen
                    logoElement.style.display = 'block';
                    logoElement.style.visibility = 'visible';
                    logoElement.style.opacity = '0.95';
                } else {
                    logoElement.style.left = '1rem';
                    logoElement.style.top = '1rem';
                    logoElement.style.maxWidth = '150px';
                    logoElement.style.maxHeight = '60px';
                }
            }
            
            // Handle Android phone/tablet rotation on fullscreen (exclude Smart TVs)
            // On Android mobile, lock to landscape and stretch video to fill screen.
            // On Smart TVs (including Android TV / TCL / BRAVIA), we rely on native fullscreen.
            if (isAndroid && !isSmartTV && isFullscreen) {
                // Lock orientation to landscape for Android devices in fullscreen
                if (screen.orientation && screen.orientation.lock) {
                    screen.orientation.lock('landscape').catch(err => {
                        console.log('[TV Channel] Could not lock orientation:', err);
                    });
                } else if (screen.lockOrientation) {
                    screen.lockOrientation('landscape');
                } else if (screen.mozLockOrientation) {
                    screen.mozLockOrientation('landscape');
                } else if (screen.msLockOrientation) {
                    screen.msLockOrientation('landscape');
                }
                
                // Add rotation class to video wrapper for Android
                const videoWrapper = document.getElementById('video-wrapper');
                if (videoWrapper) {
                    videoWrapper.classList.add('android-fullscreen-rotate');
                }
            } else {
                // Remove rotation class when exiting fullscreen
                const videoWrapper = document.getElementById('video-wrapper');
                if (videoWrapper) {
                    videoWrapper.classList.remove('android-fullscreen-rotate');
                }
            }
            
            // Smart TV optimizations
            if (isSmartTV && isFullscreen) {
                const videoWrapper = document.getElementById('video-wrapper');
                if (videoWrapper) {
                    videoWrapper.classList.add('smart-tv-fullscreen');
                }
            } else {
                const videoWrapper = document.getElementById('video-wrapper');
                if (videoWrapper) {
                    videoWrapper.classList.remove('smart-tv-fullscreen');
                }
            }
        });
        
        // Android TV remote "Back" support
        <?php if ($isAndroidTV): ?>
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' || e.key === 'Escape' || e.key === 'BrowserBack') {
                    e.preventDefault();
                    handleBackToLiveTV();
                }
            });
        <?php endif; ?>
        
        
        // Real-time viewer tracking
        function updateViewerCount() {
            const apiUrl = getApiUrl('viewer_tracker.php') + '?action=get&channel_id=' + channelId;
            console.log('[TV Channel] Fetching viewer count from:', apiUrl);
            fetch(apiUrl)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.viewers !== undefined) {
                        const count = parseInt(data.viewers) || 0;
                        const mobileEl = document.getElementById('viewer-count-mobile');
                        const desktopEl = document.getElementById('viewer-count-desktop');
                        const mobileText = document.getElementById('viewer-count-mobile-text');
                        const desktopText = document.getElementById('viewer-count-desktop-text');
                        
                        // Always show the viewer count element
                        if (mobileEl) {
                            mobileEl.style.display = 'flex';
                            if (mobileText) mobileText.textContent = count.toLocaleString();
                        }
                        if (desktopEl) {
                            desktopEl.style.display = 'flex';
                            if (desktopText) desktopText.textContent = count.toLocaleString();
                        }
                    } else {
                        console.warn('[TV Channel] Invalid response from viewer tracker:', data);
                    }
                })
                .catch(error => {
                    console.error('[TV Channel] Error updating viewer count:', error);
                });
        }
        
        // Ping server that user is watching
        function pingViewer() {
            const formData = new FormData();
            formData.append('action', 'ping');
            formData.append('channel_id', channelId);
            
            console.log('[TV Channel] Sending ping to viewer tracker for channel:', channelId);
            
            fetch(getApiUrl('viewer_tracker.php'), {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('[TV Channel] Ping response:', data);
                if (data.success && data.viewers !== undefined) {
                    // Update the count immediately after ping
                    const count = parseInt(data.viewers) || 0;
                    console.log('[TV Channel] Viewer count updated to:', count);
                    const mobileEl = document.getElementById('viewer-count-mobile');
                    const desktopEl = document.getElementById('viewer-count-desktop');
                    const mobileText = document.getElementById('viewer-count-mobile-text');
                    const desktopText = document.getElementById('viewer-count-desktop-text');
                    
                    // Always show the viewer count element
                    if (mobileEl) {
                        mobileEl.style.display = 'flex';
                        if (mobileText) mobileText.textContent = count.toLocaleString();
                    }
                    if (desktopEl) {
                        desktopEl.style.display = 'flex';
                        if (desktopText) desktopText.textContent = count.toLocaleString();
                    }
                } else {
                    console.warn('[TV Channel] Invalid response from ping:', data);
                }
            })
            .catch(error => {
                console.error('[TV Channel] Error pinging viewer:', error);
            });
        }
        
        // Suppress console warnings from third-party scripts (YouTube, etc.)
        const originalWarn = console.warn;
        console.warn = function(...args) {
            const message = args.join(' ');
            // Suppress known warnings we can't fix
            if (message.includes('unreachable code after return') || 
                message.includes('Feature Policy') ||
                message.includes('Cookie') ||
                message.includes('Partitioned cookie')) {
                return; // Suppress these warnings
            }
            originalWarn.apply(console, args);
        };
        
        // Initialize player on page load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($showPremiumGate): ?>
            // Don't load stream if premium gate is shown
            return;
            <?php endif; ?>
            
            if (document.getElementById('videoPlayer') && !streamLoaded) {
                loadStream();
            }
            
            // Start viewer tracking immediately (don't wait for stream to load)
            console.log('[TV Channel] Starting viewer tracking for channel:', channelId);
            pingViewer(); // Initial ping - this will also update the count
            
            // Ping every 30 seconds to keep viewer active (reduces server load)
            // The ping response already includes the viewer count, so we don't need a separate GET request
            viewerPingInterval = setInterval(function() {
                console.log('[TV Channel] Pinging viewer tracker...');
                pingViewer();
            }, 30000); // Changed from 15000 to 30000 (30 seconds)
            
            // Update viewer count display every 1 minute (60 seconds)
            // This is separate from pinging to reduce server load
            viewerUpdateInterval = setInterval(function() {
                console.log('[TV Channel] Updating viewer count display...');
                updateViewerCount();
            }, 60000); // 1 minute
        });
        
        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            // Stop viewer tracking intervals
            if (viewerPingInterval) clearInterval(viewerPingInterval);
            if (viewerUpdateInterval) clearInterval(viewerUpdateInterval);
            
            // Notify server that user left
            const formData = new FormData();
            formData.append('action', 'leave');
            formData.append('channel_id', channelId);
            navigator.sendBeacon(getApiUrl('viewer_tracker.php'), formData);
            
            if (shakaPlayer) {
                try {
                    shakaPlayer.destroy();
                } catch (e) {
                    console.warn('[TV Channel] Error destroying Shaka player on unload:', e);
                }
            }
            // Clear YouTube iframe
            const youtubeIframe = document.getElementById('youtubePlayer');
            if (youtubeIframe) {
                youtubeIframe.src = '';
            }
        });
    </script>
<?php endif; ?>

<?php if ($channel && !empty($footer_heading)): ?>
    <div class="watch-seo-heading container mx-auto px-4 py-8 text-center">
        <h1 class="text-2xl md:text-3xl font-bold text-white mb-4"><?php echo htmlspecialchars($footer_heading); ?></h1>
    </div>
<?php endif; ?>

<?php
$minimal_site_footer = true;
include __DIR__ . '/../includes/footer.php';