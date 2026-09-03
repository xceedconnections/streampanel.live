<?php
/**
 * TV Channel Info Page
 * Public channel information + play CTA that links to /watch-live-tv/{slug}
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../admin/includes/functions.php';

$conn = getDBConnection();

// Check if login is required for TV channels
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
    $login_required_tv_channels = '0';
}

// Check if login is actually required
$login_required = false;
if (is_string($login_required_tv_channels)) {
    $login_required_tv_channels = trim($login_required_tv_channels);
    $login_required = ($login_required_tv_channels === '1' || $login_required_tv_channels === 'true' || $login_required_tv_channels === 'yes');
} else {
    $login_required = ($login_required_tv_channels === 1 || $login_required_tv_channels === true);
}

if (empty($login_required_tv_channels) || $login_required_tv_channels === '0' || $login_required_tv_channels === 0 || $login_required_tv_channels === false || $login_required_tv_channels === null) {
    $login_required = false;
}

$slug = $_GET['slug'] ?? null;
$id   = $_GET['id'] ?? null;

$channel = null;

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

if ($channel) {
    $site_name         = getSetting($conn, 'site_name', 'StreamFlix');
    $channel_name_raw  = $channel['name'] ?? 'Live TV Channel';
    $channel_name_lower = strtolower($channel_name_raw);
    $channel_category  = $channel['category'] ?? 'TV Channel';

    // Basic SEO for info page
    $pageTitle = "{$channel_name_raw} - Channel Info & Live Stream Details | {$site_name}";
    $metaDescription = "{$channel_name_lower} channel information, category, sources, and live stream details on {$site_name}.";
    $metaKeywords = "{$channel_name_lower} info, {$channel_name_lower} live tv channel, {$channel_name_lower} details";

    // Channel logo URL for social sharing
    $channel_logo_url = assetUrl($channel['logo'] ?? '');

    // Canonical URL should be the info page (/tv/{slug})
    $canonical_url = BASE_URL . '/tv/' . ($channel['slug'] ?? 'channel');

    // Parse and count valid sources
    $sources_raw = $channel['sources'] ?? '[]';
    $sources     = json_decode($sources_raw, true);
    if (!is_array($sources)) {
        $sources = [];
    }
    $valid_sources_count = 0;
    foreach ($sources as $source) {
        if (!empty($source['url'])) {
            $valid_sources_count++;
        }
    }
    if ($valid_sources_count === 0 && !empty($channel['stream_url'])) {
        // Fallback to single stream_url
        $valid_sources_count = 1;
    }

    $watch_url = BASE_URL . '/watch-live-tv/' . ($channel['slug'] ?? '');
} else {
    $site_name        = getSetting($conn, 'site_name', 'StreamFlix');
    $pageTitle        = "Channel Not Found - {$site_name}";
    $metaDescription  = "Requested live TV channel could not be found on {$site_name}.";
    $metaKeywords     = "live tv, tv channel, {$site_name}";
    $channel_logo_url = '';
    $canonical_url    = BASE_URL . '/tv';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Mobile Optimization -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <meta name="twitter:title" content="<?php echo htmlspecialchars("{$channel_name_raw} Live Stream | Free TV Channel"); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars("Watch {$channel_name_raw} live online - HD streaming free"); ?>">
    <?php if (!empty($channel_logo_url)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($channel_logo_url); ?>">
    <?php endif; ?>
    <?php endif; ?>
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">

    <?php if (!empty($channel_logo_url)): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($channel_logo_url); ?>">
    <?php endif; ?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
    .channel-hero {
        min-height: 100vh;
        background: radial-gradient(circle at top, rgba(229,9,20,0.35), transparent 55%) #000;
        color: #fff;
        position: relative;
        overflow: hidden;
    }
    .channel-hero::before {
        content: "";
        position: absolute;
        inset: 0;
        background-position: center;
        background-size: cover;
        background-repeat: no-repeat;
        filter: blur(24px);
        opacity: 0.4;
        transform: scale(1.05);
        z-index: 0;
        <?php if (!empty($channel_logo_url)): ?>
        background-image: url('<?php echo htmlspecialchars($channel_logo_url); ?>');
        <?php else: ?>
        background-image: linear-gradient(135deg, #111827, #000000);
        <?php endif; ?>
    }
    .channel-hero-overlay {
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top, rgba(0,0,0,0.4), rgba(0,0,0,0.95));
        z-index: 1;
    }
    .channel-hero-content {
        position: relative;
        z-index: 2;
    }
    .play-pulse {
        box-shadow: 0 0 0 0 rgba(229, 9, 20, 0.7);
        animation: pulse 1.6s infinite;
    }
    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(229, 9, 20, 0.7);
        }
        70% {
            box-shadow: 0 0 0 18px rgba(229, 9, 20, 0);
        }
        100% {
            box-shadow: 0 0 0 0 rgba(229, 9, 20, 0);
        }
    }
    </style>
</head>
<body class="bg-black">
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="channel-hero flex items-center justify-center">
    <div class="channel-hero-overlay"></div>

    <div class="channel-hero-content max-w-5xl w-full px-4 sm:px-6 lg:px-8 py-16">
        <?php if (!$channel): ?>
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl font-extrabold mb-4">Channel not found</h1>
                <p class="text-gray-300 mb-8">The TV channel you are looking for does not exist or is no longer available.</p>
                <a href="<?php echo BASE_URL; ?>/live-tv" class="inline-flex items-center px-6 py-3 rounded bg-netflix-red hover:bg-red-700 font-semibold">
                    <i class="fas fa-tv mr-2"></i> Browse Live TV
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-col md:flex-row items-center gap-10">
                <!-- Channel Card -->
                <div class="w-full md:w-5/12">
                    <div class="relative rounded-2xl overflow-hidden bg-black/60 border border-white/10 shadow-2xl backdrop-blur group cursor-pointer" onclick="goToWatchPage()">
                        <div class="aspect-video flex items-center justify-center bg-black/40 relative">
                            <?php if (!empty($channel_logo_url)): ?>
                                <img src="<?php echo htmlspecialchars($channel_logo_url); ?>"
                                     alt="<?php echo htmlspecialchars($channel['name']); ?>"
                                     class="max-h-full max-w-full object-contain transition-transform duration-300 group-hover:scale-105">
                            <?php else: ?>
                                <i class="fas fa-tv text-6xl text-gray-500"></i>
                            <?php endif; ?>
                            
                            <!-- Play Button Overlay -->
                            <div class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <div class="w-20 h-20 rounded-full bg-netflix-red hover:bg-red-700 flex items-center justify-center shadow-2xl play-pulse cursor-pointer transform scale-90 group-hover:scale-100 transition-transform duration-300">
                                    <i class="fas fa-play text-white text-2xl ml-1"></i>
                                </div>
                            </div>
                            
                            <!-- Always visible play button (smaller, top-right) -->
                            <div class="absolute top-4 right-4 w-12 h-12 rounded-full bg-netflix-red hover:bg-red-700 flex items-center justify-center shadow-lg play-pulse cursor-pointer z-10">
                                <i class="fas fa-play text-white text-sm ml-0.5"></i>
                            </div>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent pointer-events-none"></div>
                        <div class="absolute top-3 left-3 px-3 py-1 rounded-full bg-red-600 text-xs font-semibold uppercase tracking-wide flex items-center gap-1.5 z-10">
                            <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                            Live TV
                        </div>
                        <?php if (!empty($channel['category'])): ?>
                        <div class="absolute top-3 right-16 px-3 py-1 rounded-full bg-white/10 text-xs font-semibold z-10">
                            <?php echo htmlspecialchars($channel['category']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Info & CTA -->
                <div class="w-full md:w-7/12 space-y-6">
                    <div>
                        <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight mb-3">
                            <?php echo htmlspecialchars($channel['name']); ?>
                        </h1>
                        <p class="text-gray-300 max-w-2xl">
                            <?php echo !empty($channel['description'])
                                ? htmlspecialchars($channel['description'])
                                : 'Watch this live TV channel in HD quality on StreamPanel.'; ?>
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3 text-sm text-gray-200">
                        <?php if (!empty($channel['country'])): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 backdrop-blur">
                                <i class="fas fa-globe-europe mr-2 text-gray-300"></i>
                                <?php echo htmlspecialchars($channel['country']); ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($channel['category'])): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 backdrop-blur">
                                <i class="fas fa-layer-group mr-2 text-gray-300"></i>
                                <?php echo htmlspecialchars($channel['category']); ?>
                            </span>
                        <?php endif; ?>

                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-white/10 backdrop-blur">
                            <i class="fas fa-signal mr-2 text-green-400"></i>
                            <?php echo $valid_sources_count; ?> source<?php echo $valid_sources_count === 1 ? '' : 's'; ?> available
                        </span>

                        <?php if (!empty($channel['is_premium']) && intval($channel['is_premium']) === 1): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full bg-yellow-500/90 text-black font-semibold">
                                <i class="fas fa-crown mr-2"></i>
                                Premium Channel
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 mt-2">
                        <button
                            type="button"
                            onclick="goToWatchPage()"
                            class="group relative inline-flex items-center justify-center px-7 py-3 rounded-full bg-netflix-red hover:bg-red-700 font-semibold text-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-black focus:ring-red-600 transition">
                            <span class="absolute inset-0 rounded-full border border-red-500/60 opacity-50 group-hover:opacity-80 transition"></span>
                            <span class="relative flex items-center gap-3">
                                <span class="w-11 h-11 rounded-full bg-white/10 flex items-center justify-center play-pulse">
                                    <i class="fas fa-play text-white ml-0.5"></i>
                                </span>
                                <span>
                                    Watch <?php echo htmlspecialchars($channel['name']); ?> Live Now
                                </span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function goToWatchPage() {
    var watchUrl = <?php echo isset($watch_url) ? json_encode($watch_url) : "null"; ?>;
    if (!watchUrl) {
        return;
    }

    var loginRequired = <?php echo $login_required ? 'true' : 'false'; ?>;
    var isLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;

    // If login is NOT required, go directly to watch page
    if (!loginRequired) {
        window.location.href = watchUrl;
        return;
    }

    // If login IS required, check if user is logged in
    if (isLoggedIn) {
        // Logged in: go straight to player page
        window.location.href = watchUrl;
    } else {
        // Not logged in: send to login with redirect back to watch page
        var loginUrl = <?php echo json_encode(BASE_URL . '/login'); ?> +
            '?redirect=' + encodeURIComponent(watchUrl);
        window.location.href = loginUrl;
    }
}
</script>

</body>
</html>

