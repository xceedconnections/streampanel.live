<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MQ = '(max-width: 767px)';

    function isMobileNavViewport() {
        return window.matchMedia(MQ).matches;
    }

    function mountMobileFooterNav() {
        var nav = document.getElementById('mobile-footer-nav');
        if (!nav) {
            return;
        }

        if (isMobileNavViewport()) {
            document.body.classList.add('has-mobile-bottom-nav');
            if (nav.parentElement !== document.body) {
                document.body.appendChild(nav);
            }
        } else {
            document.body.classList.remove('has-mobile-bottom-nav');
        }
    }

    mountMobileFooterNav();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountMobileFooterNav);
    }

    window.addEventListener('load', mountMobileFooterNav);

    var mq = window.matchMedia(MQ);
    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', mountMobileFooterNav);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(mountMobileFooterNav);
    }
})();
</script>
<?php endif; ?>
