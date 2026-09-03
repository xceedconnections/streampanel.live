.video-player-wrapper { position: relative; width: 100%; height: 100%; }
.ad-overlay {
    position: absolute; top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.95); z-index: 1000;
    display: none; align-items: center; justify-content: center;
}
.ad-container {
    position: relative; width: 100%; height: 100%; max-width: 1920px; max-height: 1080px;
    display: flex; flex-direction: column; align-items: center; justify-content: center;
}
#ad-content { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
#ad-content img, #ad-content video { max-width: 100%; max-height: 100%; object-fit: contain; }
.ad-controls { position: absolute; bottom: 16px; right: 16px; display: flex; gap: 12px; align-items: center; z-index: 2; }
.ad-countdown { color: #fff; font-size: 0.9rem; background: rgba(0,0,0,0.6); padding: 6px 10px; border-radius: 4px; }
.ad-skip-btn { background: #e50914; color: #fff; border: 0; padding: 8px 14px; border-radius: 4px; cursor: pointer; font-weight: 600; }
.custom-ad-video-container { position: relative; width: 100%; height: 100%; background: #000; }
.custom-ad-video { width: 100%; height: 100%; object-fit: contain; background: #000; outline: none; }
.custom-ad-video::-webkit-media-controls { display: none !important; }
.ad-banner-overlay {
    position: absolute; bottom: 56px; left: 50%; transform: translateX(-50%);
    z-index: 999; max-width: 90%; max-height: 120px; background: rgba(0,0,0,0.8);
    padding: 8px; border-radius: 8px; cursor: pointer;
}
.ad-banner-overlay img { max-height: 100px; max-width: 100%; display: block; }
.ad-popup-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 10001;
    display: flex; align-items: center; justify-content: center;
}
