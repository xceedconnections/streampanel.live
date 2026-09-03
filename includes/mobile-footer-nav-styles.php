<?php
/**
 * Shared mobile bottom navigation styles (raw CSS, no <style> wrapper).
 * Mobile-first: visible on small screens, hidden on desktop only.
 */
if (!empty($mobile_footer_nav_styles_included)) {
    return;
}
$mobile_footer_nav_styles_included = true;
?>
/* Mobile bottom navigation */
:root {
    --mobile-nav-bar-height: 60px;
    --mobile-nav-safe-bottom: env(safe-area-inset-bottom, 0px);
    --mobile-nav-offset: calc(var(--mobile-nav-bar-height) + var(--mobile-nav-safe-bottom));
}

@media (max-width: 767px) {
    body.has-mobile-bottom-nav {
        padding-bottom: var(--mobile-nav-offset);
    }

    body.has-mobile-bottom-nav > .pt-16,
    body.has-mobile-bottom-nav .page-container,
    body.has-mobile-bottom-nav .home-page,
    body.has-mobile-bottom-nav .search-page,
    body.has-mobile-bottom-nav .actor-page,
    body.has-mobile-bottom-nav .movie-detail-page,
    body.has-mobile-bottom-nav .tv-channel-page,
    body.has-mobile-bottom-nav .watch-page,
    body.has-mobile-bottom-nav .movies-page-inner {
        padding-bottom: var(--mobile-nav-offset);
    }

    body.has-mobile-bottom-nav .site-footer-desktop {
        display: none !important;
    }

    body.has-mobile-bottom-nav .watch-seo-heading {
        display: none !important;
    }

    #mobile-footer-nav.mobile-footer-nav {
        display: flex;
        flex-direction: row;
        justify-content: space-around;
        align-items: center;
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        width: 100%;
        max-width: 100%;
        height: var(--mobile-nav-offset);
        min-height: var(--mobile-nav-offset);
        margin: 0;
        padding: 0.5rem 0 var(--mobile-nav-safe-bottom);
        box-sizing: border-box;
        background: #141414;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.35);
        z-index: 99999;
        -webkit-tap-highlight-color: transparent;
        touch-action: manipulation;
        overflow: visible;
        opacity: 1;
        visibility: visible;
        pointer-events: auto;
        transform: none;
        will-change: auto;
    }

    #mobile-footer-nav.mobile-footer-nav .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.2rem;
        padding: 0.35rem 0.5rem;
        color: #9ca3af;
        text-decoration: none;
        border-radius: 0.5rem;
        min-width: 52px;
        flex: 1;
        max-width: 96px;
    }

    #mobile-footer-nav.mobile-footer-nav .nav-item svg {
        width: 22px;
        height: 22px;
        stroke-width: 2;
        flex-shrink: 0;
    }

    #mobile-footer-nav.mobile-footer-nav .nav-item span {
        font-size: 0.65rem;
        font-weight: 500;
        line-height: 1.1;
        white-space: nowrap;
    }

    #mobile-footer-nav.mobile-footer-nav .nav-item.active,
    #mobile-footer-nav.mobile-footer-nav .nav-item:hover {
        color: #e50914;
        background: rgba(229, 9, 20, 0.12);
    }

    #mobile-footer-nav.mobile-footer-nav .nav-item.active span,
    #mobile-footer-nav.mobile-footer-nav .nav-item:hover span {
        font-weight: 600;
    }
}

@media (min-width: 768px) {
    #mobile-footer-nav.mobile-footer-nav {
        display: none !important;
    }
}
