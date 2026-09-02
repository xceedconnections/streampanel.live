<?php
/**
 * Partial: slide add/edit form + scripts for admin/sliders.php
 * Included only when $current_slider is set.
 */
$curType = $edit_slide['link_type'] ?? 'external';
$curLinkId = (int) ($edit_slide['link_id'] ?? 0);
?>
<!-- Add/Edit Slide Form -->
<div class="bg-gray-900 rounded-lg p-6 mb-6">
    <h3 class="text-xl font-bold mb-4"><?php echo $edit_slide ? 'Edit' : 'Add'; ?> Slide</h3>
    <p class="text-sm text-gray-400 mb-4">
        Pick <strong>Movie / TV / Live TV</strong> first — title, description, and banner prefill automatically. Play link is automatic on the homepage.
    </p>
    <form method="POST" action="" enctype="multipart/form-data" id="slide-form">
        <input type="hidden" name="save_slide" value="1">
        <input type="hidden" name="slider_id" value="<?php echo (int) $current_slider['id']; ?>">
        <?php if ($edit_slide): ?>
        <input type="hidden" name="slide_id" value="<?php echo (int) $edit_slide['id']; ?>">
        <?php endif; ?>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Link Type</label>
            <select name="slide_link_type" id="slide_link_type" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <option value="external" <?php echo $curType === 'external' ? 'selected' : ''; ?>>Custom URL</option>
                <option value="movie" <?php echo $curType === 'movie' ? 'selected' : ''; ?>>Movie</option>
                <option value="tv_show" <?php echo $curType === 'tv_show' ? 'selected' : ''; ?>>TV Show</option>
                <option value="live_tv" <?php echo $curType === 'live_tv' ? 'selected' : ''; ?>>Live TV Channel</option>
            </select>
        </div>

        <div id="link_url_field" class="mb-4" style="display: <?php echo $curType === 'external' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Custom URL</label>
            <input type="text" name="slide_link_url" value="<?php echo htmlspecialchars($edit_slide['link_url'] ?? ''); ?>"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="https://example.com">
        </div>

        <div id="movie_select_wrap" class="mb-4" style="display: <?php echo $curType === 'movie' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Select Movie</label>
            <select id="slide_link_id_movie" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <option value="">-- Select Movie --</option>
                <?php foreach ($movies as $movie): ?>
                <option value="<?php echo (int) $movie['id']; ?>" <?php echo ($curType === 'movie' && $curLinkId === (int) $movie['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($movie['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($movies)): ?>
            <p class="text-xs text-yellow-400 mt-1">No active movies found. Add movies under Movies tab first.</p>
            <?php endif; ?>
        </div>

        <div id="tv_show_select_wrap" class="mb-4" style="display: <?php echo $curType === 'tv_show' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Select TV Show</label>
            <select id="slide_link_id_tv_show" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <option value="">-- Select TV Show --</option>
                <?php foreach ($tv_shows as $show): ?>
                <option value="<?php echo (int) $show['id']; ?>" <?php echo ($curType === 'tv_show' && $curLinkId === (int) $show['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($show['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="live_tv_select_wrap" class="mb-4" style="display: <?php echo $curType === 'live_tv' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Select Live TV Channel</label>
            <select id="slide_link_id_live_tv" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <option value="">-- Select Channel --</option>
                <?php foreach ($live_tv_channels as $channel): ?>
                <option value="<?php echo (int) $channel['id']; ?>" <?php echo ($curType === 'live_tv' && $curLinkId === (int) $channel['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($channel['title']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" name="slide_link_id" id="slide_link_id" value="<?php echo $curLinkId > 0 ? $curLinkId : ''; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Title</label>
                <input type="text" name="slide_title" id="slide_title" value="<?php echo htmlspecialchars($edit_slide['title'] ?? ''); ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Display Order</label>
                <input type="number" name="slide_display_order" value="<?php echo (int) ($edit_slide['display_order'] ?? 0); ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="slide_description" id="slide_description" rows="3"
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_slide['description'] ?? ''); ?></textarea>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Upload Image <span class="text-gray-500 font-normal">(optional if content has a banner)</span></label>
            <input type="file" name="slide_image_file" id="slide_image_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   onchange="previewImage(this)">
            <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF, WEBP — Max 5MB</p>
        </div>

        <div class="mb-4" id="image_preview_container" style="display: <?php echo !empty($edit_slide['image_url']) ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Image Preview</label>
            <div class="relative inline-block">
                <img id="image_preview" src="<?php echo !empty($edit_slide['image_url']) ? htmlspecialchars($edit_slide['image_url']) : ''; ?>"
                     alt="Preview"
                     class="max-w-full h-64 object-contain border border-gray-700 rounded"
                     onerror="this.style.display='none';">
                <button type="button" onclick="clearImagePreview()"
                        class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full w-8 h-8 flex items-center justify-center">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Image URL</label>
            <input type="text" name="slide_image_url" id="slide_image_url" value="<?php echo htmlspecialchars($edit_slide['image_url'] ?? ''); ?>"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="https://example.com/image.jpg"
                   onchange="updateImagePreview(this.value)">
            <p class="text-xs text-gray-400 mt-1">Auto-filled when you select a movie / TV show / channel</p>
        </div>

        <div class="mb-4">
            <label class="flex items-center">
                <input type="checkbox" name="slide_is_active" value="1" <?php echo ($edit_slide['is_active'] ?? true) ? 'checked' : ''; ?> class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                <span>Active</span>
            </label>
        </div>

        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700 font-semibold">
            <?php echo $edit_slide ? 'Update' : 'Add'; ?> Slide
        </button>
        <?php if ($edit_slide): ?>
        <a href="?tab=sliders&slider_id=<?php echo (int) $current_slider['id']; ?>" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<script>
window.SLIDE_CATALOG = <?php echo $slide_catalog_json ?: '{"movie":[],"tv_show":[],"live_tv":[]}'; ?>;

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('image_preview');
            const container = document.getElementById('image_preview_container');
            preview.style.display = '';
            preview.src = e.target.result;
            container.style.display = 'block';
            document.getElementById('slide_image_url').value = '';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function updateImagePreview(url) {
    const preview = document.getElementById('image_preview');
    const container = document.getElementById('image_preview_container');
    if (!url) {
        container.style.display = 'none';
        return;
    }
    preview.style.display = '';
    preview.src = url;
    preview.onerror = function() { container.style.display = 'none'; };
    preview.onload = function() { container.style.display = 'block'; };
    document.getElementById('slide_image_file').value = '';
}

function clearImagePreview() {
    document.getElementById('image_preview').src = '';
    document.getElementById('image_preview_container').style.display = 'none';
    document.getElementById('slide_image_file').value = '';
    document.getElementById('slide_image_url').value = '';
}

function syncHiddenLinkId() {
    const type = document.getElementById('slide_link_type').value;
    const hidden = document.getElementById('slide_link_id');
    if (type === 'movie') {
        hidden.value = document.getElementById('slide_link_id_movie').value || '';
    } else if (type === 'tv_show') {
        hidden.value = document.getElementById('slide_link_id_tv_show').value || '';
    } else if (type === 'live_tv') {
        hidden.value = document.getElementById('slide_link_id_live_tv').value || '';
    } else {
        hidden.value = '';
    }
}

function updateLinkFields() {
    const linkType = document.getElementById('slide_link_type').value;
    document.getElementById('link_url_field').style.display = linkType === 'external' ? 'block' : 'none';
    document.getElementById('movie_select_wrap').style.display = linkType === 'movie' ? 'block' : 'none';
    document.getElementById('tv_show_select_wrap').style.display = linkType === 'tv_show' ? 'block' : 'none';
    document.getElementById('live_tv_select_wrap').style.display = linkType === 'live_tv' ? 'block' : 'none';
    syncHiddenLinkId();
}

function fillFromCatalog(type, id) {
    const list = (window.SLIDE_CATALOG && window.SLIDE_CATALOG[type]) ? window.SLIDE_CATALOG[type] : [];
    const item = list.find(function (row) { return String(row.id) === String(id); });
    if (!item) return;
    document.getElementById('slide_title').value = item.title || '';
    document.getElementById('slide_description').value = item.description || '';
    if (item.image) {
        document.getElementById('slide_image_url').value = item.image;
        updateImagePreview(item.image);
    }
    syncHiddenLinkId();
}

document.getElementById('slide_link_type').addEventListener('change', function () {
    updateLinkFields();
});
document.getElementById('slide_link_id_movie').addEventListener('change', function () {
    syncHiddenLinkId();
    if (this.value) fillFromCatalog('movie', this.value);
});
document.getElementById('slide_link_id_tv_show').addEventListener('change', function () {
    syncHiddenLinkId();
    if (this.value) fillFromCatalog('tv_show', this.value);
});
document.getElementById('slide_link_id_live_tv').addEventListener('change', function () {
    syncHiddenLinkId();
    if (this.value) fillFromCatalog('live_tv', this.value);
});
document.getElementById('slide-form').addEventListener('submit', function () {
    syncHiddenLinkId();
});

updateLinkFields();
</script>
