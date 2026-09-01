<?php
/** Shared movie form JavaScript for add/edit pages. */
?>
<script>
let sourceCount = <?php echo (!empty($edit_movie['sources'])) ? count($edit_movie['sources']) : 0; ?>;
let downloadCount = <?php echo (!empty($edit_movie['download_links'])) ? count($edit_movie['download_links']) : 0; ?>;

document.getElementById('tmdb-fetch-btn')?.addEventListener('click', async function() {
    const input = document.getElementById('tmdb-input').value.trim();
    const status = document.getElementById('tmdb-fetch-status');
    if (!input) {
        status.textContent = 'Enter a TMDB ID or URL';
        status.className = 'text-sm mt-2 text-red-400';
        status.classList.remove('hidden');
        return;
    }
    status.textContent = 'Fetching from TMDB...';
    status.className = 'text-sm mt-2 text-blue-400';
    status.classList.remove('hidden');
    this.disabled = true;
    try {
        const res = await fetch('<?php echo BASE_URL; ?>/admin/api/tmdb-fetch.php?input=' + encodeURIComponent(input));
        const json = await res.json();
        if (!json.success) throw new Error(json.error || 'Fetch failed');
        const d = json.data;
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
        setVal('tmdb_id', d.tmdb_id);
        document.querySelector('[name=title]').value = d.title || '';
        document.querySelector('[name=description]').value = d.description || '';
        setVal('thumbnail', d.thumbnail);
        setVal('poster', d.poster);
        setVal('backdrop', d.backdrop);
        setVal('trailer_url', d.trailer_url);
        setVal('director', d.director);
        document.querySelector('[name=duration]').value = d.duration || '';
        document.querySelector('[name=release_year]').value = d.release_year || '';
        document.querySelector('[name=rating]').value = d.rating || '';
        document.getElementById('cast_data').value = JSON.stringify(d.cast_data || []);
        document.getElementById('genres').value = JSON.stringify(d.genres || []);
        status.textContent = 'Movie data fetched successfully!';
        status.className = 'text-sm mt-2 text-green-400';
    } catch (e) {
        status.textContent = e.message;
        status.className = 'text-sm mt-2 text-red-400';
    }
    this.disabled = false;
});

function addDownloadLink() {
    downloadCount++;
    const container = document.getElementById('download-links-container');
    container.insertAdjacentHTML('beforeend', `
        <div class="download-item bg-gray-800 rounded-lg p-4 border border-gray-700">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold">Download #${downloadCount}</h4>
                <button type="button" onclick="removeDownloadLink(this)" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">Label</label>
                    <input type="text" name="download_links[${downloadCount}][label]" value="Download" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                    <input type="hidden" name="download_links[${downloadCount}][id]" value="dl_${Date.now()}">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Quality</label>
                    <input type="text" name="download_links[${downloadCount}][quality]" placeholder="720p, 1080p" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold mb-1">Download URL *</label>
                    <input type="text" name="download_links[${downloadCount}][url]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">File Size</label>
                    <input type="text" name="download_links[${downloadCount}][size]" placeholder="1.2 GB" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div class="flex items-center">
                    <label class="flex items-center text-xs">
                        <input type="checkbox" name="download_links[${downloadCount}][isActive]" checked class="w-3 h-3 mr-2">
                        <span>Active</span>
                    </label>
                </div>
            </div>
        </div>
    `);
}

function removeDownloadLink(btn) {
    if (confirm('Remove this download link?')) btn.closest('.download-item').remove();
}

function switchSourceTab(index) {
    document.querySelectorAll('.source-tab-btn').forEach(btn => {
        const active = parseInt(btn.dataset.index, 10) === index;
        btn.classList.toggle('bg-netflix-red', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('bg-gray-700', !active);
        btn.classList.toggle('text-gray-200', !active);
    });
    document.querySelectorAll('.source-panel').forEach(panel => {
        const active = parseInt(panel.dataset.index, 10) === index;
        panel.style.display = active ? 'block' : 'none';
        panel.classList.toggle('active', active);
    });
}

function getSourceTabLabel(panel, index) {
    const input = panel.querySelector('.source-label-input');
    const label = input && input.value.trim() ? input.value.trim() : ('Source ' + (index + 1));
    return label;
}

function syncSourceTabLabels() {
    document.querySelectorAll('.source-panel').forEach((panel, i) => {
        const index = parseInt(panel.dataset.index, 10);
        const label = getSourceTabLabel(panel, index);
        const tab = document.querySelector('.source-tab-btn[data-index="' + index + '"]');
        const title = panel.querySelector('.source-panel-title');
        if (tab) tab.textContent = label;
        if (title) title.textContent = label;
    });
}

function reindexSourceTabs() {
    const panels = Array.from(document.querySelectorAll('.source-panel'));
    const nav = document.getElementById('source-tabs-nav');
    nav.innerHTML = '';
    panels.forEach((panel, i) => {
        panel.dataset.index = String(i);
        panel.querySelectorAll('[name^="sources["]').forEach(el => {
            el.name = el.name.replace(/sources\[\d+\]/, 'sources[' + i + ']');
        });
        const label = getSourceTabLabel(panel, i);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'source-tab-btn px-4 py-2 rounded text-sm font-semibold bg-gray-700 text-gray-200 hover:bg-gray-600';
        btn.dataset.index = String(i);
        btn.textContent = label;
        btn.onclick = () => switchSourceTab(i);
        nav.appendChild(btn);
        const title = panel.querySelector('.source-panel-title');
        if (title) title.textContent = label;
    });
    if (panels.length) switchSourceTab(0);
    sourceCount = panels.length;
}

function addSource() {
    const index = document.querySelectorAll('.source-panel').length;
    sourceCount = index + 1;
    const nav = document.getElementById('source-tabs-nav');
    const container = document.getElementById('sources-container');

    const tabBtn = document.createElement('button');
    tabBtn.type = 'button';
    tabBtn.className = 'source-tab-btn px-4 py-2 rounded text-sm font-semibold bg-gray-700 text-gray-200 hover:bg-gray-600';
    tabBtn.dataset.index = String(index);
    tabBtn.textContent = 'Source ' + sourceCount;
    tabBtn.onclick = () => switchSourceTab(index);
    nav.appendChild(tabBtn);

    const sourceHtml = `
        <div class="source-item source-panel bg-gray-800 rounded-lg p-4 border border-gray-700" data-index="${index}" style="display:none;">
            <div class="flex items-center justify-between mb-3">
                <h4 class="font-semibold source-panel-title">Source ${sourceCount}</h4>
                <button type="button" onclick="removeSource(this)" class="text-red-400 hover:text-red-300" title="Remove source">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold mb-1">Source Label *</label>
                    <input type="text" name="sources[${index}][label]" placeholder="e.g., Server 1, HD Quality"
                           class="source-label-input w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                    <input type="hidden" name="sources[${index}][id]" value="src_${Date.now()}_${Math.random().toString(36).substr(2, 9)}">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Source Type *</label>
                    <select name="sources[${index}][type]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                        <optgroup label="Social Media">
                            <option value="youtube">YouTube</option>
                            <option value="dailymotion">Dailymotion</option>
                            <option value="vimeo">Vimeo</option>
                            <option value="facebook">Facebook</option>
                            <option value="instagram">Instagram</option>
                            <option value="tiktok">TikTok</option>
                            <option value="twitter">Twitter/X</option>
                        </optgroup>
                        <optgroup label="Streaming Protocols (Shaka Player)">
                            <option value="m3u8">M3U8 (HLS)</option>
                            <option value="hls">HLS Stream</option>
                            <option value="dash">MPEG-DASH</option>
                            <option value="m3u">M3U Playlist</option>
                            <option value="rtmp">RTMP Stream</option>
                            <option value="rtsp">RTSP Stream</option>
                        </optgroup>
                        <optgroup label="Direct & Embed">
                            <option value="direct">Direct MP4/Video</option>
                            <option value="embed" selected>Iframe Embed</option>
                            <option value="html-embed">HTML Embed Code</option>
                            <option value="iframe-only">Iframe Only</option>
                            <option value="open-window">Open in New Window</option>
                        </optgroup>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold mb-1">Stream URL *</label>
                    <input type="text" name="sources[${index}][url]" placeholder="https://... or embed URL"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm" required>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Quality</label>
                    <select name="sources[${index}][quality]" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                        <option value="Auto" selected>Auto</option>
                        <option value="SD">SD</option>
                        <option value="HD">HD</option>
                        <option value="FHD">FHD</option>
                        <option value="UHD">UHD</option>
                        <option value="4K">4K</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Language</label>
                    <input type="text" name="sources[${index}][language]" value="English"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold mb-1">Priority (0 = Default)</label>
                    <input type="number" name="sources[${index}][priority]" value="999" min="0"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                </div>
                <div class="flex items-center gap-4">
                    <label class="flex items-center text-xs">
                        <input type="checkbox" name="sources[${index}][isActive]" checked
                               class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                        <span>Active</span>
                    </label>
                    <label class="flex items-center text-xs">
                        <input type="checkbox" name="sources[${index}][isVisible]" checked
                               class="w-3 h-3 text-netflix-red bg-gray-700 border-gray-600 rounded mr-2">
                        <span>Visible</span>
                    </label>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', sourceHtml);
    switchSourceTab(index);
}

function removeSource(btn) {
    if (!confirm('Remove this source?')) return;
    const panel = btn.closest('.source-panel');
    if (!panel) return;
    panel.remove();
    reindexSourceTabs();
}

document.getElementById('sources-container')?.addEventListener('input', function(e) {
    if (e.target && e.target.matches('.source-label-input')) {
        syncSourceTabLabels();
    }
});

function validateMovieForm() {
    const sources = document.querySelectorAll('.source-item');
    if (sources.length === 0) {
        if (!confirm('No sources added. Continue without sources?')) {
            return false;
        }
    }
    return true;
}
</script>
