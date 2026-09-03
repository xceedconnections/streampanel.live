<?php
/**
 * Shared fullscreen helpers for watch pages.
 * Include once near player scripts.
 */
?>
<script>
(function () {
    function fsElement() {
        return document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement || null;
    }

    window.streamIsFullscreen = function () {
        return !!fsElement();
    };

    window.streamToggleFullscreen = function (containerId) {
        var container = document.getElementById(containerId || 'player-container');
        if (!container) return;

        if (!fsElement()) {
            var req = container.requestFullscreen || container.webkitRequestFullscreen || container.mozRequestFullScreen || container.msRequestFullscreen;
            if (req) {
                try {
                    var p = req.call(container);
                    if (p && typeof p.catch === 'function') p.catch(function () {});
                } catch (e) {}
            }
            return;
        }

        var exit = document.exitFullscreen || document.webkitExitFullscreen || document.mozCancelFullScreen || document.msExitFullscreen;
        if (exit) {
            try {
                var p2 = exit.call(document);
                if (p2 && typeof p2.catch === 'function') p2.catch(function () {});
            } catch (e2) {}
        }

        // Cleanup after exit attempt
        setTimeout(function () {
            if (typeof window.resetPlayerBrightness === 'function') {
                window.resetPlayerBrightness();
            }
            var video = document.getElementById('videoPlayer');
            if (video) {
                video.style.filter = '';
                video.style.webkitFilter = '';
                video.style.opacity = '';
            }
            var vw = document.getElementById('video-wrapper');
            if (vw) {
                vw.classList.remove('android-fullscreen-rotate');
                vw.classList.remove('smart-tv-fullscreen');
            }
        }, 50);
    };

    window.streamBindFullscreenButtons = function () {
        function syncIcons() {
            var on = window.streamIsFullscreen();
            var maximizeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>';
            var minimizeIcon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v3a2 2 0 0 1-2 2H3m18 0h-3a2 2 0 0 1-2-2V3m0 18h3a2 2 0 0 0 2-2v-3m-18 0v3a2 2 0 0 0 2 2h3"></path></svg>';
            var btnMobile = document.getElementById('fullscreen-button-mobile');
            var btnDesktop = document.getElementById('fullscreen-button-desktop');
            if (btnMobile) btnMobile.innerHTML = on ? minimizeIcon : maximizeIcon;
            if (btnDesktop) btnDesktop.innerHTML = on ? minimizeIcon : maximizeIcon;
            if (typeof window.isFullscreen !== 'undefined') {
                window.isFullscreen = on;
            }
        }
        ['fullscreenchange', 'webkitfullscreenchange', 'mozfullscreenchange', 'MSFullscreenChange'].forEach(function (evt) {
            document.addEventListener(evt, syncIcons);
        });
        syncIcons();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.streamBindFullscreenButtons();
        });
    } else {
        window.streamBindFullscreenButtons();
    }
})();
</script>
