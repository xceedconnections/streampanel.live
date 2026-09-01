<?php
/** Single movie source panel — expects $index, $source, $is_active_panel */
$is_active_panel = $is_active_panel ?? false;
$source_label = trim($source['label'] ?? '');
$tab_label = $source_label !== '' ? $source_label : ('Source ' . ($index + 1));
?>
<div class="source-item source-panel bg-gray-800 rounded-lg p-4 border border-gray-700 <?php echo $is_active_panel ? 'active' : ''; ?>" data-index="<?php echo (int) $index; ?>" style="<?php echo $is_active_panel ? '' : 'display:none;'; ?>">
    <div class="flex items-center justify-between mb-3">
        <h4 class="font-semibold source-panel-title"><?php echo htmlspecialchars($tab_label); ?></h4>
        <button type="button" onclick="removeSource(this)" class="text-red-400 hover:text-red-300" title="Remove source">
            <i class="fas fa-trash"></i>
        </button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-semibold mb-1">Source Label *</label>
            <input type="text" name="sources[<?php echo $index; ?>][label]" value="<?php echo htmlspecialchars($source['label'] ?? ''); ?>" placeholder="e.g., Server 1, HD Quality"
                   class="source-label-input w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
            <input type="hidden" name="sources[<?php echo $index; ?>][id]" value="<?php echo htmlspecialchars($source['id'] ?? 'src_' . time() . '_' . uniqid()); ?>">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Source Type *</label>
            <select name="sources[<?php echo $index; ?>][type]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                <optgroup label="Social Media">
                    <option value="youtube" <?php echo ($source['type'] ?? '') === 'youtube' ? 'selected' : ''; ?>>YouTube</option>
                    <option value="dailymotion" <?php echo ($source['type'] ?? '') === 'dailymotion' ? 'selected' : ''; ?>>Dailymotion</option>
                    <option value="vimeo" <?php echo ($source['type'] ?? '') === 'vimeo' ? 'selected' : ''; ?>>Vimeo</option>
                    <option value="facebook" <?php echo ($source['type'] ?? '') === 'facebook' ? 'selected' : ''; ?>>Facebook</option>
                    <option value="instagram" <?php echo ($source['type'] ?? '') === 'instagram' ? 'selected' : ''; ?>>Instagram</option>
                    <option value="tiktok" <?php echo ($source['type'] ?? '') === 'tiktok' ? 'selected' : ''; ?>>TikTok</option>
                    <option value="twitter" <?php echo ($source['type'] ?? '') === 'twitter' ? 'selected' : ''; ?>>Twitter/X</option>
                </optgroup>
                <optgroup label="Streaming Protocols (Shaka Player)">
                    <option value="m3u8" <?php echo ($source['type'] ?? '') === 'm3u8' ? 'selected' : ''; ?>>M3U8 (HLS)</option>
                    <option value="hls" <?php echo ($source['type'] ?? '') === 'hls' ? 'selected' : ''; ?>>HLS Stream</option>
                    <option value="dash" <?php echo ($source['type'] ?? '') === 'dash' ? 'selected' : ''; ?>>MPEG-DASH</option>
                    <option value="m3u" <?php echo ($source['type'] ?? '') === 'm3u' ? 'selected' : ''; ?>>M3U Playlist</option>
                    <option value="rtmp" <?php echo ($source['type'] ?? '') === 'rtmp' ? 'selected' : ''; ?>>RTMP Stream</option>
                    <option value="rtsp" <?php echo ($source['type'] ?? '') === 'rtsp' ? 'selected' : ''; ?>>RTSP Stream</option>
                </optgroup>
                <optgroup label="Direct & Embed">
                    <option value="direct" <?php echo ($source['type'] ?? '') === 'direct' ? 'selected' : ''; ?>>Direct MP4/Video</option>
                    <option value="embed" <?php echo (($source['type'] ?? '') === 'embed' || ($source['type'] ?? '') === '') ? 'selected' : ''; ?>>Iframe Embed</option>
                    <option value="html-embed" <?php echo ($source['type'] ?? '') === 'html-embed' ? 'selected' : ''; ?>>HTML Embed Code</option>
                    <option value="iframe-only" <?php echo ($source['type'] ?? '') === 'iframe-only' ? 'selected' : ''; ?>>Iframe Only</option>
                    <option value="open-window" <?php echo ($source['type'] ?? '') === 'open-window' ? 'selected' : ''; ?>>Open in New Window</option>
                </optgroup>
            </select>
        </div>
        <div class="md:col-span-2">
            <label class="block text-xs font-semibold mb-1">Stream URL *</label>
            <input type="text" name="sources[<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($source['url'] ?? ''); ?>" placeholder="https://... or embed URL"
                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Quality</label>
            <select name="sources[<?php echo $index; ?>][quality]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                <option value="Auto" <?php echo ($source['quality'] ?? 'Auto') === 'Auto' ? 'selected' : ''; ?>>Auto</option>
                <option value="SD" <?php echo ($source['quality'] ?? '') === 'SD' ? 'selected' : ''; ?>>SD</option>
                <option value="HD" <?php echo ($source['quality'] ?? '') === 'HD' ? 'selected' : ''; ?>>HD</option>
                <option value="FHD" <?php echo ($source['quality'] ?? '') === 'FHD' ? 'selected' : ''; ?>>FHD</option>
                <option value="UHD" <?php echo ($source['quality'] ?? '') === 'UHD' ? 'selected' : ''; ?>>UHD</option>
                <option value="4K" <?php echo ($source['quality'] ?? '') === '4K' ? 'selected' : ''; ?>>4K</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Language</label>
            <input type="text" name="sources[<?php echo $index; ?>][language]" value="<?php echo htmlspecialchars($source['language'] ?? 'English'); ?>"
                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
        </div>
        <div>
            <label class="block text-xs font-semibold mb-1">Priority (0 = Default)</label>
            <input type="number" name="sources[<?php echo $index; ?>][priority]" value="<?php echo $source['priority'] ?? 999; ?>" min="0"
                   class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
        </div>
        <div class="flex items-center gap-4">
            <label class="flex items-center text-xs">
                <input type="checkbox" name="sources[<?php echo $index; ?>][isActive]" <?php echo ($source['isActive'] ?? true) ? 'checked' : ''; ?>
                       class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                <span>Active</span>
            </label>
            <label class="flex items-center text-xs">
                <input type="checkbox" name="sources[<?php echo $index; ?>][isVisible]" <?php echo ($source['isVisible'] ?? true) ? 'checked' : ''; ?>
                       class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                <span>Visible</span>
            </label>
        </div>
    </div>
</div>
