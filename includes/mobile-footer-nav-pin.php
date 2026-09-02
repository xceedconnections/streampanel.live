<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MOBILE_NAV_MQ = window.matchMedia('(max-width: 767px)');
    var resizeTimer = null;

    function isMobileNavViewport() {
        return MOBILE_NAV_MQ.matches;
    }

    function getNav() {
        return document.getElementById('mobile-footer-nav') || document.querySelector('.mobile-footer-nav');
    }

    function clearMobileNavInlineStyles(nav) {
        nav.removeAttribute('style');
    }

    function getVisualBottom() {
        var vv = window.visualViewport;
        if (vv) {
            return vv.offsetTop + vv.height;
        }
        return window.innerHeight;
    }

    function applyMobileNavFixedStyles(nav) {
        nav.style.setProperty('position', 'fixed', 'important');
        nav.style.setProperty('left', '0', 'important');
        nav.style.setProperty('right', '0', 'important');
        nav.style.setProperty('bottom', '0', 'important');
        nav.style.setProperty('top', 'auto', 'important');
        nav.style.setProperty('width', '100%', 'important');
        nav.style.setProperty('max-width', '100%', 'important');
        nav.style.setProperty('margin', '0', 'important');
        nav.style.setProperty('z-index', '2147483000', 'important');
        nav.style.setProperty('display', 'flex', 'important');
        nav.style.setProperty('justify-content', 'space-around', 'important');
        nav.style.setProperty('align-items', 'center', 'important');
        nav.style.setProperty('box-sizing', 'border-box', 'important');
        nav.style.setProperty('transform', 'none', 'important');
        nav.style.setProperty('-webkit-transform', 'none', 'important');
        nav.style.setProperty('visibility', 'visible', 'important');
        nav.style.setProperty('opacity', '1', 'important');
        nav.style.setProperty('pointer-events', 'auto', 'important');
        nav.style.setProperty('padding-bottom', 'env(safe-area-inset-bottom, 0px)', 'important');
    }

    function adjustMobileNavGap(nav) {
        requestAnimationFrame(function () {
            if (!nav || !isMobileNavViewport()) return;

            var visualBottom = getVisualBottom();
            var rect = nav.getBoundingClientRect();
            var gap = Math.round(visualBottom - rect.bottom);

            if (gap > 0 && gap <= 120) {
                nav.style.setProperty(
                    'padding-bottom',
                    'calc(' + gap + 'px + env(safe-area-inset-bottom, 0px))',
                    'important'
                );
            } else {
                nav.style.setProperty('padding-bottom', 'env(safe-area-inset-bottom, 0px)', 'important');
            }
        });
    }

    function pinMobileFooterNav() {
        var nav = getNav();
        if (!nav) return;

        if (!isMobileNavViewport()) {
            clearMobileNavInlineStyles(nav);
            return;
        }

        if (nav.parentElement !== document.body) {
            document.body.appendChild(nav);
        }

        applyMobileNavFixedStyles(nav);
        adjustMobileNavGap(nav);
    }

    function schedulePin() {
        if (resizeTimer) {
            clearTimeout(resizeTimer);
        }
        resizeTimer = setTimeout(pinMobileFooterNav, 50);
    }

    pinMobileFooterNav();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pinMobileFooterNav);
    }

    window.addEventListener('load', pinMobileFooterNav);
    window.addEventListener('resize', schedulePin, { passive: true });
    window.addEventListener('orientationchange', function () {
        setTimeout(pinMobileFooterNav, 150);
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', schedulePin);
    }

    if (typeof MOBILE_NAV_MQ.addEventListener === 'function') {
        MOBILE_NAV_MQ.addEventListener('change', pinMobileFooterNav);
    } else if (typeof MOBILE_NAV_MQ.addListener === 'function') {
        MOBILE_NAV_MQ.addListener(pinMobileFooterNav);
    }
})();
</script>
<?php endif; ?>
