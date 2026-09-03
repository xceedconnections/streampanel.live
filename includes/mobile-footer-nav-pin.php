<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MQ = '(max-width: 767px)';
    var shellId = 'mobile-footer-nav-shell';

    function isMobileNavViewport() {
        return window.matchMedia(MQ).matches;
    }

    function mountMobileFooterNav() {
        var shell = document.getElementById(shellId);
        if (!shell || !isMobileNavViewport()) {
            return;
        }

        if (shell.parentElement !== document.body) {
            document.body.appendChild(shell);
        }

        shell.style.bottom = '0px';
    }

    mountMobileFooterNav();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountMobileFooterNav);
    }

    window.addEventListener('load', mountMobileFooterNav);
    window.addEventListener('pageshow', mountMobileFooterNav);

    var mq = window.matchMedia(MQ);
    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', mountMobileFooterNav);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(mountMobileFooterNav);
    }
})();
</script>
<?php endif; ?>
