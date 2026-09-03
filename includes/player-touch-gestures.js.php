<?php
/**
 * Fullscreen mobile gestures (MX-style):
 * - Left half: brightness (CSS filter on video — reliable for HLS/DASH)
 * - Right half: volume
 * Vertical fill bars on left/right.
 */
?>
<style id="player-touch-gestures-css">
.player-gesture-side-hud {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 60;
    width: 44px;
    padding: 12px 0;
    border-radius: 12px;
    background: rgba(0, 0, 0, 0.55);
    color: #fff;
    display: none;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    pointer-events: none;
}
.player-gesture-side-hud.is-visible {
    display: flex;
}
.player-gesture-side-hud.is-left { left: 18px; }
.player-gesture-side-hud.is-right { right: 18px; }
.player-gesture-side-hud .gesture-icon {
    font-size: 16px;
    line-height: 1;
}
.player-gesture-side-hud .gesture-vbar {
    width: 6px;
    height: 120px;
    border-radius: 3px;
    background: rgba(255, 255, 255, 0.22);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}
.player-gesture-side-hud .gesture-vbar > span {
    display: block;
    width: 100%;
    height: 50%;
    background: #fff;
    border-radius: 3px;
}
.player-gesture-side-hud .gesture-label {
    font-size: 11px;
    font-weight: 700;
}
</style>
<script>
(function () {
    var brightness = 1;
    var hudTimer = null;

    function isTouchDevice() {
        return ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    }
    function isFs() {
        return !!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement);
    }
    function getVideo() {
        return document.getElementById('videoPlayer');
    }

    function applyBrightness() {
        var video = getVideo();
        if (!video) return;
        var v = Math.max(0.15, Math.min(1.2, brightness));
        video.style.filter = 'brightness(' + v + ')';
        video.style.webkitFilter = 'brightness(' + v + ')';
    }

    function resetBrightness() {
        brightness = 1;
        var video = getVideo();
        if (video) {
            video.style.filter = '';
            video.style.webkitFilter = '';
        }
        hideHud();
    }

    function hideHud() {
        var left = document.getElementById('player-gesture-hud-left');
        var right = document.getElementById('player-gesture-hud-right');
        if (left) left.classList.remove('is-visible');
        if (right) right.classList.remove('is-visible');
    }

    function showSideHud(kind, value01) {
        var isBright = kind === 'brightness';
        var hud = document.getElementById(isBright ? 'player-gesture-hud-left' : 'player-gesture-hud-right');
        var other = document.getElementById(isBright ? 'player-gesture-hud-right' : 'player-gesture-hud-left');
        if (!hud) return;
        if (other) other.classList.remove('is-visible');
        var pct = Math.round(Math.max(0, Math.min(1, value01)) * 100);
        var icon = hud.querySelector('.gesture-icon');
        var label = hud.querySelector('.gesture-label');
        var fill = hud.querySelector('.gesture-vbar > span');
        if (icon) icon.textContent = isBright ? '☀' : '🔊';
        if (label) label.textContent = pct + '%';
        if (fill) fill.style.height = pct + '%';
        hud.classList.add('is-visible');
        if (hudTimer) clearTimeout(hudTimer);
        hudTimer = setTimeout(hideHud, 900);
    }

    function ensureHud(wrap) {
        if (!document.getElementById('player-gesture-hud-left')) {
            var left = document.createElement('div');
            left.id = 'player-gesture-hud-left';
            left.className = 'player-gesture-side-hud is-left';
            left.innerHTML = '<div class="gesture-icon">☀</div><div class="gesture-vbar"><span></span></div><div class="gesture-label">100%</div>';
            wrap.appendChild(left);
        }
        if (!document.getElementById('player-gesture-hud-right')) {
            var right = document.createElement('div');
            right.id = 'player-gesture-hud-right';
            right.className = 'player-gesture-side-hud is-right';
            right.innerHTML = '<div class="gesture-icon">🔊</div><div class="gesture-vbar"><span></span></div><div class="gesture-label">100%</div>';
            wrap.appendChild(right);
        }
    }

    function initPlayerTouchGestures() {
        if (!isTouchDevice()) return;
        var container = document.getElementById('player-container') || document.getElementById('video-wrapper');
        if (!container || container.getAttribute('data-gestures-bound') === '1') return;
        container.setAttribute('data-gestures-bound', '1');

        var wrap = document.getElementById('video-wrapper') || container;
        if (getComputedStyle(wrap).position === 'static') {
            wrap.style.position = 'relative';
        }
        ensureHud(wrap);

        var startY = 0;
        var startVal = 0;
        var mode = null;
        var active = false;

        function onStart(e) {
            if (!isFs()) return;
            var video = getVideo();
            if (!video || video.style.display === 'none') return;
            var t = e.touches && e.touches[0];
            if (!t) return;
            var rect = wrap.getBoundingClientRect();
            var x = t.clientX - rect.left;
            startY = t.clientY;
            if (x < rect.width * 0.45) {
                mode = 'brightness';
                startVal = brightness;
            } else if (x > rect.width * 0.55) {
                mode = 'volume';
                startVal = (typeof video.volume === 'number') ? video.volume : 1;
            } else {
                mode = null;
                return;
            }
            active = true;
        }

        function onMove(e) {
            if (!active || !mode || !isFs()) return;
            var t = e.touches && e.touches[0];
            if (!t) return;
            var dy = startY - t.clientY;
            var delta = dy / 200;
            if (mode === 'brightness') {
                brightness = Math.max(0.15, Math.min(1.2, startVal + delta));
                applyBrightness();
                // Map 0.15..1.2 → 0..1 for bar
                var bar = (brightness - 0.15) / (1.2 - 0.15);
                showSideHud('brightness', bar);
                e.preventDefault();
            } else if (mode === 'volume') {
                var video = getVideo();
                if (!video) return;
                var vol = Math.max(0, Math.min(1, startVal + delta));
                try { video.volume = vol; } catch (err) {}
                video.muted = vol <= 0.01;
                showSideHud('volume', vol);
                e.preventDefault();
            }
        }

        function onEnd() {
            active = false;
            mode = null;
        }

        wrap.addEventListener('touchstart', onStart, { passive: true });
        wrap.addEventListener('touchmove', onMove, { passive: false });
        wrap.addEventListener('touchend', onEnd);
        wrap.addEventListener('touchcancel', onEnd);

        function onFsChange() {
            if (!isFs()) {
                resetBrightness();
                var vw = document.getElementById('video-wrapper');
                if (vw) {
                    vw.classList.remove('android-fullscreen-rotate');
                    vw.classList.remove('smart-tv-fullscreen');
                }
            }
        }
        document.addEventListener('fullscreenchange', onFsChange);
        document.addEventListener('webkitfullscreenchange', onFsChange);
        document.addEventListener('mozfullscreenchange', onFsChange);
        document.addEventListener('MSFullscreenChange', onFsChange);
    }

    window.resetPlayerBrightness = resetBrightness;
    window.initPlayerTouchGestures = initPlayerTouchGestures;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPlayerTouchGestures);
    } else {
        initPlayerTouchGestures();
    }
})();
</script>
