<?php
/**
 * Admin advertisement settings — same fields as Live TV.
 * Expects $conn and $ad_settings_row (array of current values, may be empty).
 * Optional $ad_settings_audience (e.g. "live TV", "movies", "TV show episodes").
 */
$ad_settings_row = $ad_settings_row ?? [];
$ad_settings_audience = $ad_settings_audience ?? 'playback';
$all_ads = $conn->query("SELECT id, name, type, content_type, duration FROM ads WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
if (!is_array($all_ads)) {
    $all_ads = [];
}
?>
<div class="mb-6 p-4 bg-gray-800 rounded-lg">
    <h3 class="text-lg font-semibold mb-3">Advertisement Settings</h3>
    <p class="text-sm text-gray-400 mb-4">Select ads to play during <?php echo htmlspecialchars($ad_settings_audience); ?>. Premium users will not see ads. Intro ads are controlled globally from the Ads page.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-semibold mb-2">Pre-roll Ad (before channel plays)</label>
            <select name="pre_roll_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="">-- No Ad --</option>
                <?php foreach ($all_ads as $ad): ?>
                    <?php if (in_array($ad['type'], ['pre-roll', 'intro-ad'], true)): ?>
                    <option value="<?php echo (int) $ad['id']; ?>" <?php echo (($ad_settings_row['pre_roll_ad_id'] ?? null) == $ad['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ad['name']); ?> (<?php echo htmlspecialchars($ad['type']); ?>)
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Mid-roll Ad</label>
            <select name="mid_roll_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="">-- No Ad --</option>
                <?php foreach ($all_ads as $ad): ?>
                    <?php if ($ad['type'] === 'mid-roll'): ?>
                    <option value="<?php echo (int) $ad['id']; ?>" <?php echo (($ad_settings_row['mid_roll_ad_id'] ?? null) == $ad['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ad['name']); ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">End-roll Ad (after channel stops)</label>
            <select name="end_roll_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="">-- No Ad --</option>
                <?php foreach ($all_ads as $ad): ?>
                    <?php if ($ad['type'] === 'post-roll'): ?>
                    <option value="<?php echo (int) $ad['id']; ?>" <?php echo (($ad_settings_row['end_roll_ad_id'] ?? null) == $ad['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ad['name']); ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Loop Ad (runs every N seconds based on ad duration)</label>
            <select name="loop_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="">-- No Ad --</option>
                <?php foreach ($all_ads as $ad): ?>
                    <?php if (in_array($ad['type'], ['loop', 'mid-roll', 'banner'], true)): ?>
                    <option value="<?php echo (int) $ad['id']; ?>" <?php echo (($ad_settings_row['loop_ad_id'] ?? null) == $ad['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ad['name']); ?> (<?php echo htmlspecialchars($ad['type']); ?>) - Duration: <?php echo (int) ($ad['duration'] ?? 30); ?>s
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">The loop ad will play every N seconds based on the ad's duration setting (configured in Ads page)</p>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Banner Ad (displays as overlay during playback)</label>
            <select name="banner_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="">-- No Ad --</option>
                <?php foreach ($all_ads as $ad): ?>
                    <?php if ($ad['type'] === 'banner'): ?>
                    <option value="<?php echo (int) $ad['id']; ?>" <?php echo (($ad_settings_row['banner_ad_id'] ?? null) == $ad['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ad['name']); ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Banner ads display as overlay during playback (non-intrusive)</p>
        </div>
        <div>
            <label class="block text-sm font-semibold mb-2">Popup Ad (displays as popup during playback)</label>
            <select name="popup_ad_id" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="">-- No Ad --</option>
                <?php foreach ($all_ads as $ad): ?>
                    <?php if ($ad['type'] === 'popup'): ?>
                    <option value="<?php echo (int) $ad['id']; ?>" <?php echo (($ad_settings_row['popup_ad_id'] ?? null) == $ad['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ad['name']); ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <p class="text-xs text-gray-400 mt-1">Popup ads display as modal overlay during playback</p>
        </div>
    </div>
</div>
