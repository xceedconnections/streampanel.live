<?php
/**
 * Shared live progress UI for admin Tools pages.
 * Include once per tools page; wire buttons/forms with data-tool-progress.
 */
if (defined('TOOL_PROGRESS_UI_LOADED')) {
    return;
}
define('TOOL_PROGRESS_UI_LOADED', true);
?>
<div id="toolProgressOverlay" class="hidden fixed inset-0 z-[9999] bg-black bg-opacity-70 flex items-center justify-center p-4">
    <div class="bg-gray-900 border border-gray-700 rounded-xl p-6 w-full max-w-lg shadow-2xl">
        <h3 id="toolProgressTitle" class="text-xl font-bold mb-2">Working…</h3>
        <p id="toolProgressStatus" class="text-gray-400 text-sm mb-4">Please wait. Do not close this page.</p>
        <div class="w-full bg-gray-700 rounded-full h-3 mb-2 overflow-hidden">
            <div id="toolProgressBar" class="bg-netflix-red h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-400 mb-4">
            <span id="toolProgressCounts">0 / 0</span>
            <span id="toolProgressPct">0%</span>
        </div>
        <div id="toolProgressLog" class="hidden max-h-48 overflow-y-auto text-xs text-gray-300 bg-gray-800 rounded p-3 border border-gray-700 space-y-1"></div>
        <div id="toolProgressActions" class="hidden mt-4 flex gap-2 flex-wrap">
            <button type="button" id="toolProgressPauseBtn" class="bg-yellow-600 hover:bg-yellow-700 px-4 py-2 rounded text-sm">Pause</button>
            <button type="button" id="toolProgressResumeBtn" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm hidden">Resume</button>
            <button type="button" id="toolProgressStopBtn" class="bg-red-600 hover:bg-red-700 px-4 py-2 rounded text-sm">Stop</button>
        </div>
        <div id="toolProgressDone" class="hidden mt-4">
            <button type="button" id="toolProgressCloseBtn" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded text-sm">Close</button>
        </div>
    </div>
</div>
<script>
window.ToolProgress = (function () {
    const overlay = () => document.getElementById('toolProgressOverlay');
    const titleEl = () => document.getElementById('toolProgressTitle');
    const statusEl = () => document.getElementById('toolProgressStatus');
    const barEl = () => document.getElementById('toolProgressBar');
    const countsEl = () => document.getElementById('toolProgressCounts');
    const pctEl = () => document.getElementById('toolProgressPct');
    const logEl = () => document.getElementById('toolProgressLog');
    const actionsEl = () => document.getElementById('toolProgressActions');
    const doneEl = () => document.getElementById('toolProgressDone');

    let pollTimer = null;
    let apiUrl = '';
    let onComplete = null;

    function show(opts) {
        opts = opts || {};
        titleEl().textContent = opts.title || 'Working…';
        statusEl().textContent = opts.status || 'Please wait. Do not close this page.';
        barEl().style.width = '0%';
        countsEl().textContent = '0 / 0';
        pctEl().textContent = '0%';
        logEl().innerHTML = '';
        logEl().classList.add('hidden');
        doneEl().classList.add('hidden');
        actionsEl().classList.toggle('hidden', !opts.controls);
        document.getElementById('toolProgressResumeBtn').classList.add('hidden');
        document.getElementById('toolProgressPauseBtn').classList.remove('hidden');
        overlay().classList.remove('hidden');
    }

    function update(data) {
        const checked = data.checked || 0;
        const total = data.total || 0;
        const progress = data.progress != null ? data.progress : (total ? Math.round((checked / total) * 100) : 0);
        barEl().style.width = Math.min(100, progress) + '%';
        countsEl().textContent = checked + ' / ' + total;
        pctEl().textContent = Math.min(100, Math.round(progress)) + '%';
        if (data.status) statusEl().textContent = data.status;
        if (data.log_html) {
            logEl().classList.remove('hidden');
            logEl().insertAdjacentHTML('beforeend', data.log_html);
            logEl().scrollTop = logEl().scrollHeight;
        }
        if (Array.isArray(data.dead_links)) {
            logEl().classList.remove('hidden');
            data.dead_links.forEach(function (link) {
                const err = link.error ? (' — ' + link.error) : '';
                logEl().insertAdjacentHTML('beforeend',
                    '<div class="text-red-300"><strong>' + escapeHtml(link.channel_name || '') + '</strong>: ' +
                    escapeHtml(link.url || '') + escapeHtml(err) + '</div>');
            });
            logEl().scrollTop = logEl().scrollHeight;
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
        });
    }

    function finish(message) {
        clearInterval(pollTimer);
        pollTimer = null;
        statusEl().textContent = message || 'Completed';
        barEl().style.width = '100%';
        pctEl().textContent = '100%';
        actionsEl().classList.add('hidden');
        doneEl().classList.remove('hidden');
        if (typeof onComplete === 'function') onComplete();
    }

    function hide() {
        clearInterval(pollTimer);
        pollTimer = null;
        overlay().classList.add('hidden');
    }

    function startBatchScan(opts) {
        apiUrl = opts.apiUrl;
        onComplete = opts.onComplete || null;
        show({ title: opts.title || 'Scanning…', status: 'Starting…', controls: true });

        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(opts.startPayload || { action: 'start' })
        })
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                statusEl().textContent = data.message || 'Failed to start';
                doneEl().classList.remove('hidden');
                actionsEl().classList.add('hidden');
                return;
            }
            update({ checked: 0, total: data.total || 0, progress: 0, status: 'Scan started…' });
            poll();
            pollTimer = setInterval(poll, opts.pollMs || 1500);
        })
        .catch(err => {
            statusEl().textContent = 'Error: ' + err.message;
            doneEl().classList.remove('hidden');
            actionsEl().classList.add('hidden');
        });
    }

    function poll() {
        const batch = 8;
        fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'action=check&batch=' + batch)
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    if (data.paused) {
                        statusEl().textContent = 'Paused';
                    }
                    return;
                }
                const extra = [];
                if (data.alive != null) extra.push('Alive: ' + data.alive);
                if (data.dead != null) extra.push('Dead: ' + data.dead);
                update({
                    checked: data.checked,
                    total: data.total,
                    progress: data.progress,
                    status: data.completed ? 'Completed' : ('Scanning… ' + extra.join(' · ')),
                    dead_links: data.dead_links
                });
                if (data.completed) {
                    finish('Completed. Alive: ' + (data.alive || 0) + ', Dead removed/found: ' + (data.dead || 0));
                }
            })
            .catch(err => console.error(err));
    }

    document.addEventListener('DOMContentLoaded', function () {
        const pauseBtn = document.getElementById('toolProgressPauseBtn');
        const resumeBtn = document.getElementById('toolProgressResumeBtn');
        const stopBtn = document.getElementById('toolProgressStopBtn');
        const closeBtn = document.getElementById('toolProgressCloseBtn');

        if (pauseBtn) pauseBtn.addEventListener('click', function () {
            fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'action=pause').then(r => r.json()).then(function () {
                pauseBtn.classList.add('hidden');
                resumeBtn.classList.remove('hidden');
                statusEl().textContent = 'Paused';
            });
        });
        if (resumeBtn) resumeBtn.addEventListener('click', function () {
            fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'action=resume').then(r => r.json()).then(function () {
                resumeBtn.classList.add('hidden');
                pauseBtn.classList.remove('hidden');
                statusEl().textContent = 'Scanning…';
                if (!pollTimer) pollTimer = setInterval(poll, 1500);
            });
        });
        if (stopBtn) stopBtn.addEventListener('click', function () {
            if (!confirm('Stop this job?')) return;
            fetch(apiUrl + (apiUrl.indexOf('?') >= 0 ? '&' : '?') + 'action=stop', { method: 'POST' })
                .then(r => r.json())
                .then(function () { finish('Stopped by user'); });
        });
        if (closeBtn) closeBtn.addEventListener('click', function () {
            hide();
            if (window.location.search.indexOf('tab=') >= 0) {
                window.location.reload();
            }
        });

        // Indeterminate overlay for classic form posts
        document.querySelectorAll('form[data-tool-progress]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (e.defaultPrevented) return; // confirm() cancelled
                const title = form.getAttribute('data-tool-progress') || 'Processing…';
                show({ title: title, status: 'Running on server. Please wait…', controls: false });
                barEl().style.width = '15%';
                // Fake slow fill so user sees activity while waiting for full page response
                let w = 15;
                const fake = setInterval(function () {
                    w = Math.min(90, w + 2);
                    barEl().style.width = w + '%';
                    pctEl().textContent = w + '%';
                }, 800);
                form.dataset._fake = String(fake);
            });
        });
    });

    return { show, update, finish, hide, startBatchScan };
})();
</script>
