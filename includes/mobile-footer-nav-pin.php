<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    function pinMobileFooterNav() {
        var nav = document.getElementById('mobile-footer-nav') || document.querySelector('.mobile-footer-nav');
        if (!nav) return;
        if (nav.parentElement !== document.body) {
            document.body.appendChild(nav);
        }
        nav.style.setProperty('position', 'fixed', 'important');
        nav.style.setProperty('left', '0', 'important');
        nav.style.setProperty('right', '0', 'important');
        nav.style.setProperty('top', 'auto', 'important');
        nav.style.setProperty('bottom', '0', 'important');
        nav.style.setProperty('width', '100%', 'important');
        nav.style.setProperty('max-width', '100vw', 'important');
        nav.style.setProperty('margin', '0', 'important');
        nav.style.setProperty('z-index', '2147483000', 'important');
        nav.style.setProperty('transform', 'none', 'important');
        nav.style.setProperty('-webkit-transform', 'none', 'important');
        nav.style.setProperty('visibility', 'visible', 'important');
        nav.style.setProperty('opacity', '1', 'important');
        nav.style.setProperty('pointer-events', 'auto', 'important');
        nav.style.setProperty('display', 'flex', 'important');
    }
    pinMobileFooterNav();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pinMobileFooterNav);
    }
    window.addEventListener('load', pinMobileFooterNav);
    window.addEventListener('resize', pinMobileFooterNav, { passive: true });
    window.addEventListener('orientationchange', function () {
        setTimeout(pinMobileFooterNav, 100);
    });
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', pinMobileFooterNav);
        window.visualViewport.addEventListener('scroll', pinMobileFooterNav);
    }
})();
</script>
<?php endif; ?>
