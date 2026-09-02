<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MOBILE_NAV_MQ = window.matchMedia('(max-width: 767px)');

    function mountMobileFooterNav() {
        if (!MOBILE_NAV_MQ.matches) {
            return;
        }

        var host = document.getElementById('mobile-footer-nav-host');
        var nav = document.getElementById('mobile-footer-nav');
        var target = host || nav;
        if (target && target.parentElement !== document.body) {
            document.body.appendChild(target);
        }
    }

    mountMobileFooterNav();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountMobileFooterNav);
    }

    window.addEventListener('load', mountMobileFooterNav);

    if (typeof MOBILE_NAV_MQ.addEventListener === 'function') {
        MOBILE_NAV_MQ.addEventListener('change', mountMobileFooterNav);
    } else if (typeof MOBILE_NAV_MQ.addListener === 'function') {
        MOBILE_NAV_MQ.addListener(mountMobileFooterNav);
    }
})();
</script>
<?php endif; ?>
