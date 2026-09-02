<?php
/**
 * Shared watch/player page layout (live TV + movies) — raw CSS only.
 */
if (!empty($watch_player_layout_styles_included)) {
    return;
}
$watch_player_layout_styles_included = true;
?>
.tv-channel-page {
    min-height: 100vh;
    background: #000;
    color: #fff;
    font-family: system-ui, -apple-system, sans-serif;
}
.sticky-header {
    position: sticky;
    top: 0;
    z-index: 40;
    background: rgba(0,0,0,0.8);
    backdrop-filter: blur(4px);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 0.75rem 1rem;
}
@media (min-width: 768px) {
    .sticky-header {
        padding: 1rem 3rem;
    }
}
.mobile-header-row1 {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}
.mobile-header-row2 {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
@media (min-width: 768px) {
    .mobile-header-row1,
    .mobile-header-row2 {
        display: none;
    }
}
.desktop-header {
    display: none;
    align-items: center;
    gap: 1rem;
}
@media (min-width: 768px) {
    .desktop-header {
        display: flex;
    }
}
.header-back-btn {
    padding: 0.5rem;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-back-btn:hover {
    background: rgba(255,255,255,0.1);
}
.channel-logo-header {
    width: 2.5rem;
    height: 2.5rem;
    object-fit: cover;
    border-radius: 0.25rem;
    flex-shrink: 0;
}
.channel-info-header {
    flex: 1;
    min-width: 0;
}
.channel-info-header h1 {
    font-size: 1.125rem;
    font-weight: 700;
    color: #fff;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (min-width: 768px) {
    .channel-info-header h1 {
        font-size: 1.25rem;
    }
}
.channel-info-header p {
    font-size: 0.75rem;
    color: #9ca3af;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
@media (min-width: 768px) {
    .channel-info-header p {
        font-size: 0.875rem;
    }
}
.viewer-count-header {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.875rem;
    color: #4ade80;
    flex-shrink: 0;
}
.viewer-count-header svg {
    width: 18px;
    height: 18px;
    color: #4ade80;
}
.viewer-count-header span {
    font-weight: 600;
}
.fullscreen-btn-header {
    padding: 0.5rem;
    background: transparent;
    border: none;
    color: #fff;
    cursor: pointer;
    border-radius: 0.5rem;
    transition: background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.fullscreen-btn-header:hover {
    background: rgba(255,255,255,0.1);
}
.player-container {
    width: 100%;
    background: #000;
}
.player-container-mobile {
    height: calc(100vh - 80px - 220px);
    min-height: 250px;
    max-height: calc(100vh - 300px);
}
@media (max-width: 480px) {
    .player-container-mobile {
        height: calc(100vh - 80px - 380px);
        min-height: 180px;
        max-height: calc(100vh - 460px);
    }
}
@media (min-width: 481px) and (max-width: 768px) {
    .player-container-mobile {
        height: calc(100vh - 80px - 300px);
        min-height: 220px;
        max-height: calc(100vh - 380px);
    }
}
.player-container-androidtv {
    position: fixed;
    inset: 0;
    z-index: 40;
    width: 100%;
    background: #000;
}
.video-player-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
    background: #000;
    overflow: hidden;
}
.video-player {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
    position: relative;
    z-index: 1;
}
#html-embed-container {
    width: 100%;
    height: 100%;
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2;
}
#html-embed-container iframe {
    width: 100%;
    height: 100%;
    border: 0;
}
.try-another-source-section {
    padding: 1rem;
    text-align: center;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.3);
}
@media (min-width: 768px) {
    .try-another-source-section {
        padding: 1.5rem;
    }
}
.try-another-source-text {
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 0.75rem;
}
.try-another-source-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: center;
    align-items: center;
}
.try-source-link {
    display: inline-block;
    padding: 0.5rem 1rem;
    background: rgba(229,9,20,0.8);
    color: #fff;
    text-decoration: none;
    border-radius: 0.25rem;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s;
}
.try-source-link:hover {
    background: rgba(229,9,20,1);
    transform: scale(1.05);
    text-decoration: none;
    color: #fff;
}
.channel-description-section {
    padding: 1.5rem 1rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}
@media (min-width: 768px) {
    .channel-description-section {
        padding: 2rem 3rem;
    }
}
.channel-description-section h3 {
    font-size: 1.125rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #fff;
}
.channel-description-section p {
    color: #9ca3af;
    line-height: 1.6;
}
.suggested-channels-section {
    padding: 2rem 1rem;
    border-top: 1px solid rgba(255,255,255,0.1);
}
@media (min-width: 768px) {
    .suggested-channels-section {
        padding: 2rem 3rem;
    }
}
.suggested-channels-title {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #fff;
}
.suggested-channels-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
}
@media (min-width: 640px) {
    .suggested-channels-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}
@media (min-width: 768px) {
    .suggested-channels-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
.suggested-channel-card {
    text-decoration: none;
    color: inherit;
    display: block;
}
.suggested-channel-logo {
    position: relative;
    aspect-ratio: 2/3;
    background: #1f2937;
    border-radius: 0.5rem;
    overflow: hidden;
}
.suggested-channel-logo .movie-card-badges {
    position: absolute;
    top: 0.35rem;
    left: 0.35rem;
    right: 0.35rem;
    z-index: 2;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    pointer-events: none;
}
.movie-card-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    pointer-events: none;
}
.movie-badge {
    font-size: 0.6rem;
    font-weight: 700;
    padding: 0.12rem 0.35rem;
    border-radius: 0.2rem;
    text-transform: uppercase;
    line-height: 1.2;
}
.movie-badge-quality {
    background: #e50914;
    color: #fff;
}
.movie-badge-tag {
    background: rgba(0,0,0,0.75);
    color: #fbbf24;
    border: 1px solid rgba(251,191,36,0.4);
}
.suggested-channel-logo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.suggested-channel-info {
    padding: 0.5rem 0;
}
.suggested-channel-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #fff;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.suggested-channel-meta {
    font-size: 0.75rem;
    color: #9ca3af;
}
.error-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    color: #fff;
    padding: 2rem;
}
.error-content {
    text-align: center;
    max-width: 28rem;
}
.error-content h2 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}
.error-content p {
    color: #9ca3af;
    margin-bottom: 1.5rem;
}
.error-actions a {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: #e50914;
    color: #fff;
    text-decoration: none;
    border-radius: 0.375rem;
    font-weight: 600;
}
