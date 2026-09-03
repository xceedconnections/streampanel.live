<?php
/**
 * Shared content ads: schema, loader, admin fields, player markup/styles.
 */

function ensureContentAdColumns($conn, string $table): void
{
    static $done = [];
    if (!empty($done[$table])) {
        return;
    }
    $allowed = ['movies', 'tv_shows', 'live_tv_channels'];
    if (!in_array($table, $allowed, true)) {
        return;
    }
    $columns = [
        'pre_roll_ad_id' => 'INT NULL',
        'mid_roll_ad_id' => 'INT NULL',
        'end_roll_ad_id' => 'INT NULL',
        'loop_ad_id' => 'INT NULL',
        'loop_interval' => 'INT NULL',
        'banner_ad_id' => 'INT NULL',
        'popup_ad_id' => 'INT NULL',
        'intro_ad_id' => 'INT NULL',
    ];
    foreach ($columns as $column => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '" . $conn->real_escape_string($column) . "'");
        if ($check && $check->num_rows === 0) {
            @$conn->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }
    $done[$table] = true;
}

function userHasActiveSubscriptionForAds($conn): bool
{
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        return false;
    }
    $user_id = $_SESSION['user_id'] ?? null;
    if (!$user_id) {
        return false;
    }
    $stmt = $conn->prepare('SELECT subscription_type, subscription_expires_at FROM users WHERE id = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        return false;
    }
    $sub_type = $user['subscription_type'] ?? 'free';
    $sub_expires = $user['subscription_expires_at'] ?? null;
    if ($sub_type === 'free' || $sub_type === null || $sub_type === '') {
        return false;
    }
    return $sub_expires === null || strtotime($sub_expires) > time();
}

function fetchActiveAdById($conn, $id): ?array
{
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    $stmt = $conn->prepare("SELECT * FROM ads WHERE id = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW())");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

/**
 * Load ads for a movie / TV show / live channel row.
 *
 * @return array{show_ads:bool,has_subscription:bool,intro_ad:?array,ads:array}
 */
function loadContentAds($conn, ?array $row): array
{
    $hasSubscription = userHasActiveSubscriptionForAds($conn);
    $show_ads = !$hasSubscription;
    $intro_ad = null;
    $ads = [];

    $introId = (int) ($row['intro_ad_id'] ?? 0);
    if ($introId > 0) {
        $intro = fetchActiveAdById($conn, $introId);
        if ($intro && ($intro['type'] ?? '') === 'intro-ad') {
            $intro_ad = $intro;
        }
    }
    if (!$intro_ad) {
        $stmt = $conn->prepare("SELECT * FROM ads WHERE type = 'intro-ad' AND is_active = 1 AND (start_date IS NULL OR start_date <= NOW()) AND (end_date IS NULL OR end_date >= NOW()) ORDER BY created_at DESC LIMIT 1");
        if ($stmt) {
            $stmt->execute();
            $intro_ad = $stmt->get_result()->fetch_assoc() ?: null;
        }
    }

    $loopId = (int) ($row['loop_ad_id'] ?? 0);
    if ($loopId > 0) {
        $loop_ad = fetchActiveAdById($conn, $loopId);
        if ($loop_ad) {
            $ads['loop'] = $loop_ad;
            $ads['loop_interval'] = !empty($loop_ad['loop_interval'])
                ? (int) $loop_ad['loop_interval']
                : (!empty($loop_ad['duration'])
                    ? (int) $loop_ad['duration']
                    : (!empty($row['loop_interval']) ? (int) $row['loop_interval'] : 60));
        }
    }

    if ($show_ads && $row) {
        foreach (['pre_roll' => 'pre_roll_ad_id', 'mid_roll' => 'mid_roll_ad_id', 'end_roll' => 'end_roll_ad_id', 'banner' => 'banner_ad_id', 'popup' => 'popup_ad_id'] as $key => $col) {
            $ad = fetchActiveAdById($conn, $row[$col] ?? 0);
            if ($ad) {
                $ads[$key] = $ad;
            }
        }
    }

    return [
        'show_ads' => $show_ads,
        'has_subscription' => $hasSubscription,
        'intro_ad' => $intro_ad,
        'ads' => $ads,
    ];
}

function contentAdsJsPayload(array $loaded): array
{
    return [
        'intro_ad' => $loaded['intro_ad'] ?? null,
        'pre_roll' => $loaded['ads']['pre_roll'] ?? null,
        'mid_roll' => $loaded['ads']['mid_roll'] ?? null,
        'end_roll' => $loaded['ads']['end_roll'] ?? null,
        'loop' => $loaded['ads']['loop'] ?? null,
        'loop_interval' => $loaded['ads']['loop_interval'] ?? null,
        'banner' => $loaded['ads']['banner'] ?? null,
        'popup' => $loaded['ads']['popup'] ?? null,
        'show_ads' => !empty($loaded['show_ads']),
        'is_premium' => !empty($loaded['has_subscription']),
    ];
}

function parseAdIdFromPost(string $key): ?int
{
    $val = $_POST[$key] ?? '';
    if ($val === '' || $val === null) {
        return null;
    }
    $id = (int) $val;
    return $id > 0 ? $id : null;
}

function saveContentAdIds($conn, string $table, int $id): void
{
    if ($id <= 0) {
        return;
    }
    ensureContentAdColumns($conn, $table);
    $pre = parseAdIdFromPost('pre_roll_ad_id');
    $mid = parseAdIdFromPost('mid_roll_ad_id');
    $end = parseAdIdFromPost('end_roll_ad_id');
    $loop = parseAdIdFromPost('loop_ad_id');
    $banner = parseAdIdFromPost('banner_ad_id');
    $popup = parseAdIdFromPost('popup_ad_id');
    $intro = parseAdIdFromPost('intro_ad_id');

    $sql = "UPDATE `{$table}` SET
        pre_roll_ad_id = " . ($pre === null ? 'NULL' : (int) $pre) . ",
        mid_roll_ad_id = " . ($mid === null ? 'NULL' : (int) $mid) . ",
        end_roll_ad_id = " . ($end === null ? 'NULL' : (int) $end) . ",
        loop_ad_id = " . ($loop === null ? 'NULL' : (int) $loop) . ",
        loop_interval = NULL,
        banner_ad_id = " . ($banner === null ? 'NULL' : (int) $banner) . ",
        popup_ad_id = " . ($popup === null ? 'NULL' : (int) $popup);
    if ($table !== 'live_tv_channels' || $intro !== null || array_key_exists('intro_ad_id', $_POST)) {
        $sql .= ", intro_ad_id = " . ($intro === null ? 'NULL' : (int) $intro);
    }
    $sql .= ' WHERE id = ' . (int) $id;
    $conn->query($sql);
}
