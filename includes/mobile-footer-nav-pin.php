<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MOBILE_NAV_MQ = window.matchMedia('(max-width: 767px)');
    var rafId = 0;
    var lastBottomInset = -1;

    function isMobileNavViewport() {
        return MOBILE_NAV_MQ.matches;
    }

    function getNav() {
        return document.getElementById('mobile-footer-nav') || document.querySelector('.mobile-footer-nav');
    }

    function clearMobileNavInlineStyles(nav) {
        nav.removeAttribute('style');
    }

    function getBottomInset() {
        var vv = window.visualViewport;
        if (!vv) {
            return 0;
        }

        var inset = Math.round(window.innerHeight - vv.height - vv.offsetTop);
        if (inset < 0) {
            inset = 0;
        }
        if (inset > 200) {
            inset = 0;
        }
        return inset;
    }

    function applyMobileNavStyles(nav, bottomInset) {
        nav.style.setProperty('position', 'fixed', 'important');
        nav.style.setProperty('left', '0', 'important');
        nav.style.setProperty('right', '0', 'important');
        nav.style.setProperty('top', 'auto', 'important');
        nav.style.setProperty('bottom', bottomInset + 'px', 'important');
        nav.style.setProperty('width', '100%', 'important');
        nav.style.setProperty('max-width', '100%', 'important');
        nav.style.setProperty('margin', '0', 'important');
        nav.style.setProperty('z-index', '2147483000', 'important');
        nav.style.setProperty('display', 'flex', 'important');
        nav.style.setProperty('justify-content', 'space-around', 'important');
        nav.style.setProperty('align-items', 'center', 'important');
        nav.style.setProperty('box-sizing', 'border-box', 'important');
        nav.style.setProperty('transform', 'translateZ(0)', 'important');
        nav.style.setProperty('-webkit-transform', 'translateZ(0)', 'important');
        nav.style.setProperty('visibility', 'visible', 'important');
        nav.style.setProperty('opacity', '1', 'important');
        nav.style.setProperty('pointer-events', 'auto', 'important');
        nav.style.setProperty('padding-top', '0.35rem', 'important');
        nav.style.setProperty('padding-left', '0', 'important');
        nav.style.setProperty('padding-right', '0', 'important');
        nav.style.setProperty('padding-bottom', 'env(safe-area-inset-bottom, 0px)', 'important');
        nav.style.setProperty('background', 'rgba(20, 20, 20, 0.98)', 'important');
        nav.style.setProperty('min-height', '60px', 'important');
        nav.style.setProperty('-webkit-backface-visibility', 'hidden', 'important');
        nav.style.setProperty('backface-visibility', 'hidden', 'important');
    }

    function pinMobileFooterNav() {
        var nav = getNav();
        if (!nav) return;

        if (!isMobileNavViewport()) {
            lastBottomInset = -1;
            clearMobileNavInlineStyles(nav);
            return;
        }

        if (nav.parentElement !== document.body) {
            document.body.appendChild(nav);
        }

        var bottomInset = getBottomInset();
        if (bottomInset === lastBottomInset) {
            return;
        }
        lastBottomInset = bottomInset;
        applyMobileNavStyles(nav, bottomInset);
    }

    function schedulePin() {
        if (rafId) {
            cancelAnimationFrame(rafId);
        }
        rafId = requestAnimationFrame(function () {
            rafId = 0;
            pinMobileFooterNav();
        });
    }

    pinMobileFooterNav();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', pinMobileFooterNav);
    }

    window.addEventListener('load', pinMobileFooterNav);
    window.addEventListener('resize', schedulePin, { passive: true });
    window.addEventListener('orientationchange', function () {
        lastBottomInset = -1;
        setTimeout(pinMobileFooterNav, 200);
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', schedulePin, { passive: true });
        window.visualViewport.addEventListener('scroll', schedulePin, { passive: true });
    }

    if (typeof MOBILE_NAV_MQ.addEventListener === 'function') {
        MOBILE_NAV_MQ.addEventListener('change', function () {
            lastBottomInset = -1;
            pinMobileFooterNav();
        });
    } else if (typeof MOBILE_NAV_MQ.addListener === 'function') {
        MOBILE_NAV_MQ.addListener(function () {
            lastBottomInset = -1;
            pinMobileFooterNav();
        });
    }
})();
</script>
<?php endif; ?>
