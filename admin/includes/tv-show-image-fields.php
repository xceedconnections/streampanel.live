<?php
/**
 * Shared logo / poster / banner fields for TV show add & edit forms.
 * Expects optional $show array (edit) or empty for add.
 */
$tv_thumb_val = $show['thumbnail'] ?? ($_POST['thumbnail'] ?? '');
$tv_poster_val = $show['poster'] ?? ($_POST['poster'] ?? '');
$tv_banner_val = $show['backdrop'] ?? ($_POST['backdrop'] ?? '');
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-semibold mb-2">Logo / Thumbnail</label>
        <input type="url" name="thumbnail" id="thumbnail" value="<?php echo htmlspecialchars($tv_thumb_val); ?>"
               placeholder="https://... logo/thumbnail URL"
               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm"
               oninput="updateTvImagePreview('thumbnail', this.value)">
        <p class="text-xs text-gray-400 mt-1">Small logo/thumbnail. Paste a URL or upload below.</p>
        <div id="thumbnail-preview-wrap" class="mt-2 <?php echo $tv_thumb_val === '' ? 'hidden' : ''; ?>">
            <img id="thumbnail-preview" src="<?php echo htmlspecialchars($tv_thumb_val); ?>" alt="Thumbnail preview"
                 class="max-h-24 max-w-24 object-contain bg-black rounded border border-gray-700 p-1"
                 onerror="this.style.display='none'">
        </div>
        <div class="mt-3">
            <label class="block text-xs font-semibold mb-1 text-gray-400">Or upload logo file</label>
            <input type="file" name="thumbnail_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white text-sm">
        </div>
    </div>
    <div>
        <label class="block text-sm font-semibold mb-2">Poster URL</label>
        <input type="url" name="poster" id="poster" value="<?php echo htmlspecialchars($tv_poster_val); ?>"
               placeholder="https://image.tmdb.org/t/p/w500/..."
               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm"
               oninput="updateTvImagePreview('poster', this.value)">
        <p class="text-xs text-gray-400 mt-1">Portrait poster (cards / listings). Paste a URL or use TMDB Fetch.</p>
        <div id="poster-preview-wrap" class="mt-2 <?php echo $tv_poster_val === '' ? 'hidden' : ''; ?>">
            <img id="poster-preview" src="<?php echo htmlspecialchars($tv_poster_val); ?>" alt="Poster preview"
                 class="max-h-48 rounded border border-gray-700 object-contain bg-black"
                 onerror="this.style.display='none'">
        </div>
        <div class="mt-3">
            <label class="block text-xs font-semibold mb-1 text-gray-400">Or upload poster file</label>
            <input type="file" name="poster_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white text-sm">
        </div>
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
    <div>
        <label class="block text-sm font-semibold mb-2">Banner / Backdrop URL</label>
        <input type="url" name="backdrop" id="backdrop" value="<?php echo htmlspecialchars($tv_banner_val); ?>"
               placeholder="https://image.tmdb.org/t/p/w1280/..."
               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm"
               oninput="updateTvImagePreview('backdrop', this.value)">
        <p class="text-xs text-gray-400 mt-1">Wide banner image. Paste a URL or use TMDB Fetch.</p>
        <div id="backdrop-preview-wrap" class="mt-2 <?php echo $tv_banner_val === '' ? 'hidden' : ''; ?>">
            <img id="backdrop-preview" src="<?php echo htmlspecialchars($tv_banner_val); ?>" alt="Banner preview"
                 class="max-h-40 w-full rounded border border-gray-700 object-cover bg-black"
                 onerror="this.style.display='none'">
        </div>
        <div class="mt-3">
            <label class="block text-xs font-semibold mb-1 text-gray-400">Or upload banner file</label>
            <input type="file" name="backdrop_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white text-sm">
        </div>
    </div>
</div>
<script>
window.updateTvImagePreview = function (kind, url) {
    var img = document.getElementById(kind + '-preview');
    var wrap = document.getElementById(kind + '-preview-wrap');
    if (!img || !wrap) return;
    url = (url || '').trim();
    if (!url) {
        wrap.classList.add('hidden');
        img.removeAttribute('src');
        return;
    }
    img.style.display = '';
    img.src = url;
    wrap.classList.remove('hidden');
};
</script>
