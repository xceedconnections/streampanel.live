<script>
(function () {
    if (typeof adsData === 'undefined') {
        window.adsData = { show_ads: false };
    }
    var AD_BASE = <?php echo json_encode(rtrim(defined('BASE_URL') ? BASE_URL : '', '/')); ?>;
    var currentAd = null;
    var adTimer = null;
    var skipTimer = null;
    var introAdShown = false;
    var adFinishedCallback = null;

    function resolveAdPath(path) {
        if (!path) return path;
        if (path.indexOf('http') === 0) return path;
        return AD_BASE + '/' + path.replace(/^\//, '');
    }

    function hideAd() {
        var overlay = document.getElementById('ad-overlay');
        if (overlay) overlay.style.display = 'none';
        var content = document.getElementById('ad-content');
        if (content) content.innerHTML = '';
        currentAd = null;
        if (adTimer) { clearTimeout(adTimer); adTimer = null; }
        if (skipTimer) { clearInterval(skipTimer); skipTimer = null; }
    }

    window.hideAd = hideAd;

    window.skipAd = function () {
        var cb = adFinishedCallback;
        hideAd();
        if (cb) cb();
    };

    window.showAd = function (ad, callback) {
        if (!ad) {
            if (callback) callback();
            return;
        }
        currentAd = ad;
        adFinishedCallback = callback;
        var overlay = document.getElementById('ad-overlay');
        var adContent = document.getElementById('ad-content');
        var countdownEl = document.getElementById('ad-countdown');
        var skipBtn = document.getElementById('ad-skip-btn');
        var skipTimerEl = document.getElementById('skip-timer');
        if (!overlay || !adContent) {
            if (callback) callback();
            return;
        }
        adContent.innerHTML = '';
        overlay.style.display = 'flex';
        var duration = parseInt(ad.duration, 10) || 15;
        var skipable = parseInt(ad.skipable, 10) === 1;
        var remaining = duration;

        if (countdownEl) countdownEl.textContent = remaining + 's';
        if (skipBtn) skipBtn.style.display = 'none';

        if (ad.content_type === 'image' && ad.logo) {
            var img = document.createElement('img');
            img.src = resolveAdPath(ad.logo);
            img.alt = ad.name || 'Advertisement';
            adContent.appendChild(img);
        } else if (ad.content_type === 'video' && ad.logo) {
            var video = document.createElement('video');
            video.src = resolveAdPath(ad.logo);
            video.className = 'custom-ad-video';
            video.autoplay = true;
            video.playsInline = true;
            video.muted = false;
            video.controls = false;
            video.onended = function () { window.skipAd(); };
            adContent.appendChild(video);
            video.play().catch(function () {});
        } else if (ad.content) {
            adContent.innerHTML = ad.content;
        }

        adTimer = setTimeout(function () { window.skipAd(); }, duration * 1000);
        if (skipable) {
            var skipAfter = 5;
            if (skipTimerEl) skipTimerEl.textContent = skipAfter;
            skipTimer = setInterval(function () {
                remaining -= 1;
                skipAfter -= 1;
                if (countdownEl) countdownEl.textContent = Math.max(remaining, 0) + 's';
                if (skipAfter <= 0 && skipBtn) {
                    skipBtn.style.display = 'inline-block';
                    if (skipTimerEl) skipTimerEl.textContent = '0';
                    clearInterval(skipTimer);
                    skipTimer = null;
                } else if (skipTimerEl) {
                    skipTimerEl.textContent = Math.max(skipAfter, 0);
                }
            }, 1000);
        } else if (countdownEl) {
            skipTimer = setInterval(function () {
                remaining -= 1;
                countdownEl.textContent = Math.max(remaining, 0) + 's';
                if (remaining <= 0) {
                    clearInterval(skipTimer);
                    skipTimer = null;
                }
            }, 1000);
        }
    };

    window.showBannerAd = function (ad) {
        if (!ad || !adsData.show_ads) return;
        var wrapper = document.getElementById('video-wrapper') || document.getElementById('player-container');
        if (!wrapper) return;
        var banner = document.createElement('div');
        banner.className = 'ad-banner-overlay';
        if (ad.logo) {
            var img = document.createElement('img');
            img.src = resolveAdPath(ad.logo);
            banner.appendChild(img);
        } else if (ad.content) {
            banner.innerHTML = ad.content;
        }
        banner.onclick = function () { banner.remove(); };
        wrapper.appendChild(banner);
    };

    window.showPopupAd = function (ad) {
        if (!ad || !adsData.show_ads) return;
        window.showAd(ad, function () {});
    };

    window.setupPlaybackAds = function (video) {
        if (!video || !adsData.show_ads) return;
        if (adsData.mid_roll) {
            video.addEventListener('timeupdate', function () {
                if (video.currentTime >= 30 && !video.midRollAdShown) {
                    video.midRollAdShown = true;
                    var saved = video.currentTime;
                    video.pause();
                    window.showAd(adsData.mid_roll, function () {
                        if (isFinite(video.duration) && video.duration > 0) {
                            video.currentTime = saved;
                        }
                        video.play().catch(function () {});
                    });
                }
            });
        }
        if (adsData.end_roll) {
            video.addEventListener('ended', function () {
                window.showAd(adsData.end_roll, function () {});
            });
        }
        if (adsData.loop && adsData.loop_interval) {
            var loopStart = Date.now();
            setInterval(function () {
                if (video.paused || currentAd || video.readyState < 2) return;
                var elapsed = Math.floor((Date.now() - loopStart) / 1000);
                if (elapsed > 0 && elapsed % parseInt(adsData.loop_interval, 10) === 0) {
                    loopStart = Date.now();
                    var saved = video.currentTime;
                    video.pause();
                    window.showAd(adsData.loop, function () {
                        if (isFinite(video.duration) && video.duration > 0) {
                            video.currentTime = saved;
                        }
                        video.play().catch(function () {});
                    });
                }
            }, 1000);
        }
        if (adsData.banner) {
            window.showBannerAd(adsData.banner);
        }
        if (adsData.popup) {
            var popupShown = false;
            video.addEventListener('timeupdate', function () {
                if (!popupShown && video.currentTime >= 60) {
                    popupShown = true;
                    window.showPopupAd(adsData.popup);
                }
            });
        }
    };

    window.runPrerollThen = function (afterAds) {
        function finish() {
            if (typeof afterAds === 'function') afterAds();
            var video = document.getElementById('videoPlayer');
            if (video) window.setupPlaybackAds(video);
        }
        if (adsData.intro_ad && !introAdShown) {
            introAdShown = true;
            window.showAd(adsData.intro_ad, function () {
                if (adsData.show_ads && adsData.pre_roll) {
                    window.showAd(adsData.pre_roll, finish);
                } else {
                    finish();
                }
            });
            return;
        }
        if (adsData.show_ads && adsData.pre_roll && !introAdShown) {
            window.showAd(adsData.pre_roll, finish);
            return;
        }
        finish();
    };
})();
</script>
