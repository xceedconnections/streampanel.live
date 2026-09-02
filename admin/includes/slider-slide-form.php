<?php
/**
 * Partial: slide add/edit form + scripts for admin/sliders.php
 * Included only when $current_slider is set.
 */
$curType = $edit_slide['link_type'] ?? 'external';
$curLinkId = (int) ($edit_slide['link_id'] ?? 0);

$findTitle = static function (array $list, int $id): string {
    foreach ($list as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return (string) ($row['title'] ?? '');
        }
    }
    return '';
};
$curMovieLabel = ($curType === 'movie' && $curLinkId) ? $findTitle($movies, $curLinkId) : '';
$curTvLabel = ($curType === 'tv_show' && $curLinkId) ? $findTitle($tv_shows, $curLinkId) : '';
$curLiveLabel = ($curType === 'live_tv' && $curLinkId) ? $findTitle($live_tv_channels, $curLinkId) : '';
?>
<!-- Add/Edit Slide Form -->
<div class="bg-gray-900 rounded-lg p-6 mb-6">
    <h3 class="text-xl font-bold mb-4"><?php echo $edit_slide ? 'Edit' : 'Add'; ?> Slide</h3>
    <p class="text-sm text-gray-400 mb-4">
        Pick <strong>Movie / TV / Live TV</strong>, then type to search by name. Title, description, and banner prefill automatically.
    </p>
    <form method="POST" action="index.php?tab=sliders&amp;slider_id=<?php echo (int) $current_slider['id']; ?>" enctype="multipart/form-data" id="slide-form">
        <input type="hidden" name="tab" value="sliders">
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

        <div id="movie_select_wrap" class="mb-4 relative" style="display: <?php echo $curType === 'movie' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Search &amp; Select Movie</label>
            <input type="text" id="slide_search_movie" value="<?php echo htmlspecialchars($curMovieLabel); ?>"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="Type movie name…" autocomplete="off">
            <div id="slide_results_movie" class="hidden absolute z-50 left-0 right-0 mt-1 max-h-64 overflow-y-auto bg-gray-800 border border-gray-600 rounded shadow-xl"></div>
            <?php if (empty($movies)): ?>
            <p class="text-xs text-yellow-400 mt-1">No active movies found. Add movies under Movies tab first.</p>
            <?php else: ?>
            <p class="text-xs text-gray-400 mt-1"><?php echo count($movies); ?> movies available — type to filter</p>
            <?php endif; ?>
        </div>

        <div id="tv_show_select_wrap" class="mb-4 relative" style="display: <?php echo $curType === 'tv_show' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Search &amp; Select TV Show</label>
            <input type="text" id="slide_search_tv_show" value="<?php echo htmlspecialchars($curTvLabel); ?>"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="Type TV show name…" autocomplete="off">
            <div id="slide_results_tv_show" class="hidden absolute z-50 left-0 right-0 mt-1 max-h-64 overflow-y-auto bg-gray-800 border border-gray-600 rounded shadow-xl"></div>
            <p class="text-xs text-gray-400 mt-1"><?php echo count($tv_shows); ?> TV shows available — type to filter</p>
        </div>

        <div id="live_tv_select_wrap" class="mb-4 relative" style="display: <?php echo $curType === 'live_tv' ? 'block' : 'none'; ?>;">
            <label class="block text-sm font-semibold mb-2">Search &amp; Select Live TV Channel</label>
            <input type="text" id="slide_search_live_tv" value="<?php echo htmlspecialchars($curLiveLabel); ?>"
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                   placeholder="Type channel name…" autocomplete="off">
            <div id="slide_results_live_tv" class="hidden absolute z-50 left-0 right-0 mt-1 max-h-64 overflow-y-auto bg-gray-800 border border-gray-600 rounded shadow-xl"></div>
            <p class="text-xs text-gray-400 mt-1"><?php echo count($live_tv_channels); ?> channels available — type to filter</p>
        </div>

        <input type="hidden" name="slide_link_id" id="slide_link_id" value="<?php echo $curLinkId > 0 ? $curLinkId : ''; ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Title</label>
                <input type="text" name="slide_title" id="slide_title" value="<?php echo htmlspecialchars($edit_slide['title'] ?? ''); ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Priority <span class="text-gray-500 font-normal">(1 = first; leave blank to append)</span></label>
                <input type="number" name="slide_display_order" min="0" value="<?php echo (int) ($edit_slide['display_order'] ?? 0); ?>"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                       placeholder="0 = auto">
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

<style>
.slide-search-item {
    display: block;
    width: 100%;
    text-align: left;
    padding: 0.65rem 1rem;
    color: #e5e5e5;
    background: transparent;
    border: 0;
    border-bottom: 1px solid #333;
    cursor: pointer;
}
.slide-search-item:hover,
.slide-search-item.active {
    background: #374151;
    color: #fff;
}
.slide-search-empty {
    padding: 0.75rem 1rem;
    color: #9ca3af;
    font-size: 0.875rem;
}
</style>

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

function fillFromCatalog(type, id) {
    const list = (window.SLIDE_CATALOG && window.SLIDE_CATALOG[type]) ? window.SLIDE_CATALOG[type] : [];
    const item = list.find(function (row) { return String(row.id) === String(id); });
    if (!item) return;
    document.getElementById('slide_link_id').value = item.id;
    document.getElementById('slide_title').value = item.title || '';
    document.getElementById('slide_description').value = item.description || '';
    const searchInput = document.getElementById('slide_search_' + type);
    if (searchInput) searchInput.value = item.title || '';
    if (item.image) {
        document.getElementById('slide_image_url').value = item.image;
        updateImagePreview(item.image);
    }
}

function hideAllResultPanels() {
    ['movie', 'tv_show', 'live_tv'].forEach(function (type) {
        const panel = document.getElementById('slide_results_' + type);
        if (panel) panel.classList.add('hidden');
    });
}

function renderSearchResults(type, query) {
    const panel = document.getElementById('slide_results_' + type);
    if (!panel) return;
    const list = (window.SLIDE_CATALOG && window.SLIDE_CATALOG[type]) ? window.SLIDE_CATALOG[type] : [];
    const q = (query || '').trim().toLowerCase();
    let matches = list;
    if (q !== '') {
        matches = list.filter(function (row) {
            return String(row.title || '').toLowerCase().indexOf(q) !== -1;
        });
    }
    // Cap visible results for performance
    const limit = 80;
    const slice = matches.slice(0, limit);
    if (slice.length === 0) {
        panel.innerHTML = '<div class="slide-search-empty">No matches for “' + escapeHtml(query) + '”</div>';
        panel.classList.remove('hidden');
        return;
    }
    panel.innerHTML = slice.map(function (row) {
        return '<button type="button" class="slide-search-item" data-type="' + type + '" data-id="' + row.id + '">' +
            escapeHtml(row.title || ('#' + row.id)) +
            '</button>';
    }).join('') + (matches.length > limit
        ? '<div class="slide-search-empty">Showing ' + limit + ' of ' + matches.length + ' — type more to narrow</div>'
        : '');
    panel.classList.remove('hidden');
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
}

function bindSearchBox(type) {
    const input = document.getElementById('slide_search_' + type);
    const panel = document.getElementById('slide_results_' + type);
    if (!input || !panel) return;

    let timer = null;
    input.addEventListener('focus', function () {
        renderSearchResults(type, input.value);
    });
    input.addEventListener('input', function () {
        // Typing means user is searching again — clear previous id until they pick
        document.getElementById('slide_link_id').value = '';
        clearTimeout(timer);
        timer = setTimeout(function () {
            renderSearchResults(type, input.value);
        }, 120);
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            panel.classList.add('hidden');
        }
    });

    panel.addEventListener('click', function (e) {
        const btn = e.target.closest('.slide-search-item');
        if (!btn) return;
        fillFromCatalog(btn.getAttribute('data-type'), btn.getAttribute('data-id'));
        panel.classList.add('hidden');
    });
}

function updateLinkFields() {
    const linkType = document.getElementById('slide_link_type').value;
    document.getElementById('link_url_field').style.display = linkType === 'external' ? 'block' : 'none';
    document.getElementById('movie_select_wrap').style.display = linkType === 'movie' ? 'block' : 'none';
    document.getElementById('tv_show_select_wrap').style.display = linkType === 'tv_show' ? 'block' : 'none';
    document.getElementById('live_tv_select_wrap').style.display = linkType === 'live_tv' ? 'block' : 'none';
    hideAllResultPanels();
    if (linkType === 'external') {
        document.getElementById('slide_link_id').value = '';
    }
}

document.getElementById('slide_link_type').addEventListener('change', function () {
    document.getElementById('slide_link_id').value = '';
    ['movie', 'tv_show', 'live_tv'].forEach(function (type) {
        const input = document.getElementById('slide_search_' + type);
        if (input) input.value = '';
    });
    updateLinkFields();
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('#movie_select_wrap') &&
        !e.target.closest('#tv_show_select_wrap') &&
        !e.target.closest('#live_tv_select_wrap')) {
        hideAllResultPanels();
    }
});

document.getElementById('slide-form').addEventListener('submit', function (e) {
    const type = document.getElementById('slide_link_type').value;
    if (type !== 'external' && !document.getElementById('slide_link_id').value) {
        e.preventDefault();
        alert('Please search and select a ' + (type === 'movie' ? 'movie' : (type === 'tv_show' ? 'TV show' : 'live TV channel')) + ' from the list.');
        return false;
    }
});

bindSearchBox('movie');
bindSearchBox('tv_show');
bindSearchBox('live_tv');
updateLinkFields();
</script>
