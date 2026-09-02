<?php if (empty($isAndroidTV)): ?>
<script>
(function () {
    var MOBILE_NAV_MQ = window.matchMedia('(max-width: 767px)');
    var ua = navigator.userAgent || '';
    var isFirefoxAndroid = /android/i.test(ua) && /firefox|fxios/i.test(ua);
    var rafId = 0;
    var lastLayoutKey = '';

    function isMobileNavViewport() {
        return MOBILE_NAV_MQ.matches;
    }

    function getNav() {
        return document.getElementById('mobile-footer-nav') || document.querySelector('.mobile-footer-nav');
    }

    function getScrim() {
        return document.getElementById('mobile-footer-nav-scrim');
    }

    function ensureScrim() {
        var scrim = getScrim();
        if (!scrim) {
            scrim = document.createElement('div');
            scrim.id = 'mobile-footer-nav-scrim';
            scrim.setAttribute('aria-hidden', 'true');
            document.body.appendChild(scrim);
        }
        return scrim;
    }

    function removeScrim() {
        var scrim = getScrim();
        if (scrim) {
            scrim.remove();
        }
    }

    function clearMobileNavInlineStyles(nav) {
        nav.removeAttribute('style');
        document.documentElement.classList.remove('ff-android-nav');
        removeScrim();
    }

    function applyCommonMobileStyles(nav) {
        nav.style.setProperty('position', 'fixed', 'important');
        nav.style.setProperty('margin', '0', 'important');
        nav.style.setProperty('z-index', '2147483000', 'important');
        nav.style.setProperty('display', 'flex', 'important');
        nav.style.setProperty('justify-content', 'space-around', 'important');
        nav.style.setProperty('align-items', 'center', 'important');
        nav.style.setProperty('box-sizing', 'border-box', 'important');
        nav.style.setProperty('visibility', 'visible', 'important');
        nav.style.setProperty('opacity', '1', 'important');
        nav.style.setProperty('pointer-events', 'auto', 'important');
        nav.style.setProperty('padding-bottom', 'env(safe-area-inset-bottom, 0px)', 'important');
        nav.style.setProperty('padding-top', '0.35rem', 'important');
        nav.style.setProperty('padding-left', '0', 'important');
        nav.style.setProperty('padding-right', '0', 'important');
        nav.style.setProperty('background', 'rgba(20, 20, 20, 0.98)', 'important');
        nav.style.setProperty('min-height', '60px', 'important');
        nav.style.setProperty('-webkit-backface-visibility', 'hidden', 'important');
        nav.style.setProperty('backface-visibility', 'hidden', 'important');
    }

    function pinDefaultMobileNav(nav) {
        applyCommonMobileStyles(nav);
        nav.style.setProperty('left', '0', 'important');
        nav.style.setProperty('right', '0', 'important');
        nav.style.setProperty('bottom', '0', 'important');
        nav.style.setProperty('top', 'auto', 'important');
        nav.style.setProperty('width', '100%', 'important');
        nav.style.setProperty('max-width', '100%', 'important');
        nav.style.setProperty('transform', 'none', 'important');
        nav.style.setProperty('-webkit-transform', 'none', 'important');
        removeScrim();
    }

    function pinFirefoxAndroidNav(nav) {
        var vv = window.visualViewport;
        if (!vv) {
            pinDefaultMobileNav(nav);
            return;
        }

        applyCommonMobileStyles(nav);

        var navRect = nav.getBoundingClientRect();
        var navHeight = Math.max(Math.round(navRect.height), 60);
        var visualTop = vv.offsetTop;
        var visualBottom = vv.offsetTop + vv.height;
        var topPos = Math.round(visualBottom - navHeight);

        // Keep the bar inside the visible viewport.
        topPos = Math.max(visualTop, topPos);
        topPos = Math.min(topPos, Math.round(visualBottom - 40));

        var layoutKey = [
            Math.round(vv.offsetLeft),
            Math.round(vv.width),
            topPos,
            navHeight
        ].join(':');

        if (layoutKey !== lastLayoutKey) {
            lastLayoutKey = layoutKey;

            nav.style.setProperty('left', Math.round(vv.offsetLeft) + 'px', 'important');
            nav.style.setProperty('width', Math.round(vv.width) + 'px', 'important');
            nav.style.setProperty('right', 'auto', 'important');
            nav.style.setProperty('bottom', 'auto', 'important');
            nav.style.setProperty('top', topPos + 'px', 'important');
            nav.style.setProperty('transform', 'translateZ(0)', 'important');
            nav.style.setProperty('-webkit-transform', 'translateZ(0)', 'important');
        }

        requestAnimationFrame(function () {
            var rect = nav.getBoundingClientRect();
            var gap = Math.round(visualBottom - rect.bottom);
            var scrim = ensureScrim();

            if (gap > 0 && gap <= 160) {
                scrim.style.cssText = [
                    'position:fixed',
                    'left:' + Math.round(vv.offsetLeft) + 'px',
                    'width:' + Math.round(vv.width) + 'px',
                    'bottom:0',
                    'height:' + gap + 'px',
                    'background:rgba(20,20,20,0.98)',
                    'z-index:2147482999',
                    'pointer-events:none',
                    'margin:0',
                    'padding:0'
                ].join(';');
            } else {
                scrim.style.cssText = 'display:none';
            }
        });
    }

    function pinMobileFooterNav() {
        var nav = getNav();
        if (!nav) return;

        if (!isMobileNavViewport()) {
            lastLayoutKey = '';
            clearMobileNavInlineStyles(nav);
            return;
        }

        if (nav.parentElement !== document.body) {
            document.body.appendChild(nav);
        }

        if (isFirefoxAndroid) {
            document.documentElement.classList.add('ff-android-nav');
            pinFirefoxAndroidNav(nav);
        } else {
            document.documentElement.classList.remove('ff-android-nav');
            lastLayoutKey = '';
            pinDefaultMobileNav(nav);
        }
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
        lastLayoutKey = '';
        setTimeout(pinMobileFooterNav, 200);
    });

    if (isFirefoxAndroid && window.visualViewport) {
        window.visualViewport.addEventListener('resize', schedulePin, { passive: true });
        window.visualViewport.addEventListener('scroll', schedulePin, { passive: true });
    }

    if (typeof MOBILE_NAV_MQ.addEventListener === 'function') {
        MOBILE_NAV_MQ.addEventListener('change', function () {
            lastLayoutKey = '';
            pinMobileFooterNav();
        });
    } else if (typeof MOBILE_NAV_MQ.addListener === 'function') {
        MOBILE_NAV_MQ.addListener(function () {
            lastLayoutKey = '';
            pinMobileFooterNav();
        });
    }
})();
</script>
<?php endif; ?>
