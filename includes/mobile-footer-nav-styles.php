<?php
/**
 * Shared mobile bottom navigation styles (raw CSS, no <style> wrapper).
 */
if (!empty($mobile_footer_nav_styles_included)) {
    return;
}
$mobile_footer_nav_styles_included = true;
?>
/* Mobile Footer Navigation */
:root {
    --mobile-nav-height: 60px;
    --mobile-nav-safe-bottom: env(safe-area-inset-bottom, 0px);
    --mobile-nav-total-height: calc(var(--mobile-nav-height) + var(--mobile-nav-safe-bottom));
}
html {
    margin: 0;
    padding: 0;
    width: 100%;
    max-width: 100%;
}
body {
    margin: 0;
    padding: 0;
    width: 100%;
    max-width: 100%;
    overscroll-behavior-y: none;
}
/* Hidden by default — desktop and tablet landscape */
.mobile-footer-nav {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
    box-sizing: border-box;
}
@media (min-width: 768px) {
    .mobile-footer-nav {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
        position: absolute !important;
        width: 0 !important;
        height: 0 !important;
        overflow: hidden !important;
        opacity: 0 !important;
    }
}
@media (max-width: 767px) {
    .mobile-footer-nav {
        display: flex !important;
        visibility: visible !important;
        pointer-events: auto !important;
        opacity: 1 !important;
        position: fixed !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        top: auto !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0.35rem 0 var(--mobile-nav-safe-bottom) !important;
        background: rgba(20, 20, 20, 0.98);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-bottom: none;
        justify-content: space-around;
        align-items: center;
        z-index: 2147483000 !important;
        height: var(--mobile-nav-total-height);
        min-height: var(--mobile-nav-total-height);
        max-height: var(--mobile-nav-total-height);
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
        transform: none !important;
        -webkit-transform: none !important;
        overflow: visible;
    }
    body > .pt-16,
    body .page-container,
    body .home-page,
    body .search-page,
    body .actor-page,
    body .movie-detail-page,
    body .tv-channel-page,
    body .watch-page {
        padding-bottom: var(--mobile-nav-total-height) !important;
    }
    .site-footer-desktop {
        display: none !important;
    }
    .watch-seo-heading {
        display: none !important;
    }
}
.mobile-footer-nav .nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.25rem;
    padding: 0.5rem 0.75rem;
    color: #9ca3af;
    text-decoration: none;
    transition: color 0.2s ease, background 0.2s ease;
    border-radius: 0.5rem;
    min-width: 60px;
    flex: 1;
    max-width: 100px;
}
.mobile-footer-nav .nav-item svg {
    width: 24px;
    height: 24px;
    stroke-width: 2;
    transition: transform 0.2s ease;
}
.mobile-footer-nav .nav-item span {
    font-size: 0.7rem;
    font-weight: 500;
}
.mobile-footer-nav .nav-item:active {
    transform: scale(0.95);
}
.mobile-footer-nav .nav-item.active,
.mobile-footer-nav .nav-item:hover {
    color: #e50914;
    background: rgba(229, 9, 20, 0.1);
}
.mobile-footer-nav .nav-item.active svg,
.mobile-footer-nav .nav-item:hover svg {
    stroke-width: 2.5;
    transform: scale(1.1);
}
.mobile-footer-nav .nav-item.active span,
.mobile-footer-nav .nav-item:hover span {
    font-weight: 600;
}
