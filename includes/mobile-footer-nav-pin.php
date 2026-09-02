<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MOBILE_NAV_MQ = window.matchMedia('(max-width: 767px)');
    var ua = navigator.userAgent || '';
    var isFirefoxMobile = /android/i.test(ua) && /firefox|fxios/i.test(ua);
    var rafId = 0;
    var lastShift = -1;

    function isMobileNavViewport() {
        return MOBILE_NAV_MQ.matches;
    }

    function getHost() {
        return document.getElementById('mobile-footer-nav-host');
    }

    function getNav() {
        return document.getElementById('mobile-footer-nav') || document.querySelector('.mobile-footer-nav');
    }

    function clearMobileNavInlineStyles() {
        var host = getHost();
        var nav = getNav();
        if (host) {
            host.removeAttribute('style');
        }
        if (nav) {
            nav.removeAttribute('style');
        }
        document.documentElement.style.removeProperty('--mobile-nav-shift');
    }

    function getVisualBottom() {
        var vv = window.visualViewport;
        if (vv) {
            return vv.offsetTop + vv.height;
        }
        return window.innerHeight;
    }

    function measureGapBelowNav(nav) {
        var rect = nav.getBoundingClientRect();
        var visualBottom = getVisualBottom();
        var gap = Math.round(visualBottom - rect.bottom);
        if (gap < 0) {
            gap = 0;
        }
        if (gap > 240) {
            gap = 0;
        }
        return gap;
    }

    function pinMobileFooterNav() {
        var host = getHost();
        var nav = getNav();
        if (!nav) {
            return;
        }

        if (!isMobileNavViewport()) {
            lastShift = -1;
            clearMobileNavInlineStyles();
            return;
        }

        if (host) {
            if (host.parentElement !== document.body) {
                document.body.appendChild(host);
            }
            host.style.setProperty('display', 'block', 'important');
            host.style.setProperty('visibility', 'visible', 'important');
        } else if (nav.parentElement !== document.body) {
            document.body.appendChild(nav);
        }

        nav.style.setProperty('display', 'flex', 'important');
        nav.style.setProperty('visibility', 'visible', 'important');
        nav.style.setProperty('opacity', '1', 'important');
        nav.style.setProperty('pointer-events', 'auto', 'important');

        var shift = 0;
        if (isFirefoxMobile) {
            shift = measureGapBelowNav(nav);
        }

        if (shift === lastShift) {
            return;
        }
        lastShift = shift;
        document.documentElement.style.setProperty('--mobile-nav-shift', shift + 'px');
    }

    function schedulePin() {
        if (rafId) {
            cancelAnimationFrame(rafId);
        }
        rafId = requestAnimationFrame(function () {
            rafId = 0;
            lastShift = -1;
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
        lastShift = -1;
        setTimeout(pinMobileFooterNav, 250);
    });

    if (isFirefoxMobile && window.visualViewport) {
        window.visualViewport.addEventListener('resize', schedulePin, { passive: true });
        window.visualViewport.addEventListener('scroll', schedulePin, { passive: true });
    }

    if (typeof MOBILE_NAV_MQ.addEventListener === 'function') {
        MOBILE_NAV_MQ.addEventListener('change', function () {
            lastShift = -1;
            pinMobileFooterNav();
        });
    } else if (typeof MOBILE_NAV_MQ.addListener === 'function') {
        MOBILE_NAV_MQ.addListener(function () {
            lastShift = -1;
            pinMobileFooterNav();
        });
    }
})();
</script>
<?php endif; ?>
