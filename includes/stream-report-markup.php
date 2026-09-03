<?php
/**
 * Shared stream report modal markup.
 * Set before include:
 *   $report_content_type (movie|tv_episode|live_tv|...)
 *   $report_content_id (int)
 *   $report_source_index (int, optional)
 */
$report_content_type = $report_content_type ?? 'live_tv';
$report_content_id = (int) ($report_content_id ?? 0);
$report_source_index = (int) ($report_source_index ?? 0);
?>
<style id="stream-report-shared-css">
.stream-report-modal {
    display: none;
    position: fixed;
    inset: 0;
    /* Above live-TV player / Shaka logo (9999) and ads; below mobile nav */
    z-index: 10050;
    background: rgba(0, 0, 0, 0.72);
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.stream-report-modal.is-open { display: flex; }
.stream-report-dialog {
    width: 100%;
    max-width: 22rem;
    background: #141414;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 8px;
    padding: 1.25rem;
}
.stream-report-dialog h3 { margin: 0 0 0.5rem; font-size: 1.1rem; }
.stream-report-dialog p { margin: 0 0 0.75rem; color: #9ca3af; font-size: 0.875rem; }
.stream-report-dialog label {
    display: block;
    font-size: 0.8rem;
    font-weight: 600;
    margin-bottom: 0.35rem;
    color: #d1d5db;
}
.stream-report-dialog select,
.stream-report-dialog textarea,
.stream-report-dialog input[type="text"] {
    width: 100%;
    box-sizing: border-box;
    background: #1f1f1f;
    border: 1px solid #333;
    color: #fff;
    border-radius: 6px;
    padding: 0.6rem;
    margin-bottom: 0.75rem;
}
.stream-report-dialog textarea { min-height: 4.5rem; resize: vertical; }
.stream-report-captcha-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}
.stream-report-captcha-row input { margin-bottom: 0; flex: 1; }
.stream-report-captcha-q {
    flex: 1.2;
    font-size: 0.9rem;
    color: #fff;
    background: #1f1f1f;
    border: 1px solid #333;
    border-radius: 6px;
    padding: 0.6rem;
}
.stream-report-actions { display: flex; gap: 0.5rem; justify-content: flex-end; }
.stream-report-actions button {
    border: none;
    border-radius: 4px;
    padding: 0.5rem 0.9rem;
    cursor: pointer;
    font-weight: 600;
}
.stream-report-cancel { background: #2a2a2a; color: #fff; }
.stream-report-submit { background: #e50914; color: #fff; }
</style>
<div id="stream-report-modal" class="stream-report-modal" role="dialog" aria-modal="true" aria-labelledby="stream-report-title"
     data-content-type="<?php echo htmlspecialchars($report_content_type, ENT_QUOTES, 'UTF-8'); ?>"
     data-content-id="<?php echo (int) $report_content_id; ?>"
     data-source-index="<?php echo (int) $report_source_index; ?>">
    <div class="stream-report-dialog">
        <h3 id="stream-report-title">Report a problem</h3>
        <p>Tell us what's wrong. Admin will be notified.</p>
        <label for="stream-report-issue">Issue type</label>
        <select id="stream-report-issue">
            <option value="broken_link">Link / stream not working</option>
            <option value="copyright">Copyright / illegal content</option>
            <option value="wrong_content">Wrong content</option>
            <option value="quality_issue">Quality issue</option>
            <option value="other">Other</option>
        </select>
        <label for="stream-report-note">Details (optional)</label>
        <textarea id="stream-report-note" maxlength="2000" placeholder="Optional details..."></textarea>
        <label>Captcha</label>
        <div class="stream-report-captcha-row">
            <div class="stream-report-captcha-q" id="stream-report-captcha-q">Loading...</div>
            <input type="text" id="stream-report-captcha-answer" inputmode="numeric" autocomplete="off" placeholder="Answer">
        </div>
        <div class="stream-report-actions">
            <button type="button" class="stream-report-cancel" onclick="closeStreamReportModal()">Cancel</button>
            <button type="button" class="stream-report-submit" id="stream-report-submit" onclick="submitStreamReport()">Send report</button>
        </div>
    </div>
</div>
<script>
(function () {
    var CAPTCHA_API = <?php echo json_encode(apiUrl('api/report-captcha.php')); ?>;
    var REPORT_API = <?php echo json_encode(apiUrl('api/report-stream.php')); ?>;

    function getSourceIndex() {
        if (typeof currentSourceIndex === 'number') return currentSourceIndex;
        var modal = document.getElementById('stream-report-modal');
        return modal ? parseInt(modal.getAttribute('data-source-index') || '0', 10) : 0;
    }

    window.openStreamReportModal = function () {
        var modal = document.getElementById('stream-report-modal');
        if (!modal) return;
        /* Escape player stacking contexts (Shaka / video) so dialog is on top */
        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }
        modal.classList.add('is-open');
        refreshReportCaptcha();
    };
    window.closeStreamReportModal = function () {
        var modal = document.getElementById('stream-report-modal');
        if (modal) modal.classList.remove('is-open');
    };
    window.refreshReportCaptcha = function () {
        var q = document.getElementById('stream-report-captcha-q');
        var a = document.getElementById('stream-report-captcha-answer');
        if (a) a.value = '';
        if (q) q.textContent = 'Loading...';
        fetch(CAPTCHA_API, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (q) q.textContent = (data && data.question) ? data.question : 'Captcha unavailable';
            })
            .catch(function () {
                if (q) q.textContent = 'Captcha unavailable';
            });
    };
    window.submitStreamReport = function () {
        var modal = document.getElementById('stream-report-modal');
        var btn = document.getElementById('stream-report-submit');
        var noteEl = document.getElementById('stream-report-note');
        var issueEl = document.getElementById('stream-report-issue');
        var captchaEl = document.getElementById('stream-report-captcha-answer');
        if (!modal) return;
        var contentType = modal.getAttribute('data-content-type') || 'live_tv';
        var contentId = parseInt(modal.getAttribute('data-content-id') || '0', 10);
        var issue = issueEl ? issueEl.value : 'broken_link';
        var note = noteEl ? noteEl.value.trim() : '';
        var captcha = captchaEl ? captchaEl.value.trim() : '';
        if (!captcha) {
            alert('Please solve the captcha.');
            return;
        }
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Sending...';
        }
        fetch(REPORT_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                content_type: contentType,
                content_id: contentId,
                source_id: String(getSourceIndex()),
                issue_type: issue,
                captcha_answer: captcha,
                description: note || (issue === 'copyright' ? 'Copyright report' : 'Link not working')
            })
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (data && data.success) {
                alert(data.message || 'Report sent.');
                window.closeStreamReportModal();
            } else {
                alert((data && data.message) ? data.message : 'Could not send report.');
                refreshReportCaptcha();
            }
        }).catch(function () {
            alert('Could not send report. Please try again.');
            refreshReportCaptcha();
        }).finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Send report';
            }
        });
    };
})();
</script>
