<?php
/** Inline search suggest script — expects BASE_URL to be defined. */
?>
<style>
.search-suggest-dropdown {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 0.5rem);
    z-index: 60;
    background: #141414;
    border: 1px solid #333;
    border-radius: 0.5rem;
    max-height: 420px;
    overflow-y: auto;
    box-shadow: 0 16px 40px rgba(0,0,0,0.55);
    display: none;
}
.search-suggest-dropdown.is-open {
    display: block;
}
.search-suggest-group {
    padding: 0.75rem 0;
    border-bottom: 1px solid #222;
}
.search-suggest-group:last-child {
    border-bottom: none;
}
.search-suggest-group-title {
    padding: 0 1rem 0.5rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #9ca3af;
}
.search-suggest-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.65rem 1rem;
    color: #fff;
    text-decoration: none;
    transition: background 0.15s;
}
.search-suggest-item:hover,
.search-suggest-item.is-active {
    background: rgba(229, 9, 20, 0.15);
    color: #fff;
    text-decoration: none;
}
.search-suggest-thumb {
    width: 40px;
    height: 56px;
    border-radius: 0.25rem;
    object-fit: cover;
    background: #222;
    flex-shrink: 0;
}
.search-suggest-thumb.actor {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
}
.search-suggest-thumb.channel {
    width: 48px;
    height: 48px;
    object-fit: contain;
    padding: 0.25rem;
    background: linear-gradient(to bottom right, rgba(229,9,20,0.15), rgba(37,99,235,0.15));
}
.search-suggest-meta {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.15rem;
}
.search-suggest-empty {
    padding: 1rem;
    color: #9ca3af;
    text-align: center;
    font-size: 0.875rem;
}
.search-suggest-loading {
    padding: 1rem;
    color: #9ca3af;
    text-align: center;
    font-size: 0.875rem;
}
</style>
<script>
(function () {
    const BASE = <?php echo json_encode(BASE_URL); ?>;
    const DEBOUNCE_MS = 1000;

    function initSearchSuggest(input, options) {
        if (!input || input.dataset.suggestReady === '1') {
            return;
        }
        input.dataset.suggestReady = '1';

        const scope = options.scope || 'all';
        const form = options.form || input.closest('form');
        const wrapper = options.wrapper || input.parentElement;
        if (getComputedStyle(wrapper).position === 'static') {
            wrapper.style.position = 'relative';
        }

        const dropdown = document.createElement('div');
        dropdown.className = 'search-suggest-dropdown';
        dropdown.setAttribute('role', 'listbox');
        wrapper.appendChild(dropdown);

        let timer = null;
        let requestId = 0;
        let activeIndex = -1;
        let currentItems = [];

        function closeDropdown() {
            dropdown.classList.remove('is-open');
            activeIndex = -1;
            currentItems = [];
        }

        function openDropdown() {
            dropdown.classList.add('is-open');
        }

        function renderGroup(title, itemsHtml) {
            if (!itemsHtml) return '';
            return '<div class="search-suggest-group"><div class="search-suggest-group-title">' + title + '</div>' + itemsHtml + '</div>';
        }

        function actorPlaceholder() {
            return 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%236b7280"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>');
        }

        function renderResults(data) {
            currentItems = [];
            let html = '';

            function addItem(url, title, meta, img, imgClass, type) {
                const index = currentItems.length;
                currentItems.push({ url: url, type: type });
                const thumb = img
                    ? '<img src="' + img + '" alt="" class="search-suggest-thumb ' + (imgClass || '') + '" onerror="this.style.display=\'none\'">'
                    : '<div class="search-suggest-thumb ' + (imgClass || '') + '"></div>';
                return '<a href="' + url + '" class="search-suggest-item" data-suggest-index="' + index + '">' +
                    thumb +
                    '<div><div class="font-semibold text-sm">' + title + '</div>' +
                    (meta ? '<div class="search-suggest-meta">' + meta + '</div>' : '') +
                    '</div></a>';
            }

            let moviesHtml = '';
            (data.movies || []).forEach(function (item) {
                moviesHtml += addItem(item.url, escapeHtml(item.title), item.year ? item.year : 'Movie', item.poster, '', 'movie');
            });

            let actorsHtml = '';
            (data.actors || []).forEach(function (item) {
                const img = item.profile_url || actorPlaceholder();
                actorsHtml += addItem(item.url, escapeHtml(item.name), 'Actor / Actress', img, 'actor', 'actor');
            });

            let tvHtml = '';
            (data.tv_shows || []).forEach(function (item) {
                tvHtml += addItem(item.url, escapeHtml(item.title), item.year ? item.year + ' · TV Show' : 'TV Show', item.poster, '', 'tv');
            });

            let liveHtml = '';
            (data.live_tv || []).forEach(function (item) {
                liveHtml += addItem(item.url, escapeHtml(item.title), item.category ? item.category + ' · Live TV' : 'Live TV', item.logo, 'channel', 'live');
            });

            html += renderGroup('Movies', moviesHtml);
            html += renderGroup('Actors & Actresses', actorsHtml);
            if (scope === 'all') {
                html += renderGroup('TV Shows', tvHtml);
                html += renderGroup('Live TV', liveHtml);
            }

            if (!html) {
                html = '<div class="search-suggest-empty">No results found</div>';
            }

            dropdown.innerHTML = html;
            openDropdown();
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function fetchSuggestions(value) {
            const q = value.trim();
            if (q.length < 2) {
                closeDropdown();
                dropdown.innerHTML = '';
                return;
            }

            dropdown.innerHTML = '<div class="search-suggest-loading">Searching...</div>';
            openDropdown();

            const currentRequest = ++requestId;
            fetch(BASE + '/api/search-suggest.php?q=' + encodeURIComponent(q) + '&scope=' + encodeURIComponent(scope), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (currentRequest !== requestId) return;
                    renderResults(data);
                })
                .catch(function () {
                    if (currentRequest !== requestId) return;
                    dropdown.innerHTML = '<div class="search-suggest-empty">Search unavailable</div>';
                });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                fetchSuggestions(input.value);
            }, DEBOUNCE_MS);
        });

        input.addEventListener('focus', function () {
            if (input.value.trim().length >= 2 && dropdown.innerHTML) {
                openDropdown();
            }
        });

        input.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('.search-suggest-item');
            if (!items.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
            } else if (e.key === 'Enter' && activeIndex >= 0) {
                e.preventDefault();
                const target = currentItems[activeIndex];
                if (target && target.url) {
                    window.location.href = target.url;
                }
                return;
            } else if (e.key === 'Escape') {
                closeDropdown();
                return;
            } else {
                return;
            }

            items.forEach(function (el, idx) {
                el.classList.toggle('is-active', idx === activeIndex);
            });
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].scrollIntoView({ block: 'nearest' });
            }
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) {
                closeDropdown();
            }
        });

        if (form) {
            form.addEventListener('submit', function () {
                closeDropdown();
            });
        }
    }

    window.initSearchSuggest = initSearchSuggest;
})();
</script>
