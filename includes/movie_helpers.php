<?php
/**
 * Movie display and data helpers.
 */

require_once __DIR__ . '/movies_schema.php';
require_once __DIR__ . '/tmdb.php';

function movieImageUrl(?string $path, string $size = 'w500'): string
{
    if ($path === null || $path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (isset($path[0]) && $path[0] === '/') {
        return tmdbImageUrl($path, $size);
    }
    return assetUrl($path);
}

function moviePosterUrl(array $movie): string
{
    $url = movieImageUrl($movie['poster'] ?? '', 'w500');
    if ($url === '') {
        $url = movieImageUrl($movie['thumbnail'] ?? '', 'w342');
    }
    return $url !== '' ? $url : FALLBACK_POSTER;
}

function movieLogoUrl(array $movie): string
{
    return movieImageUrl($movie['thumbnail'] ?? '', 'w300');
}

function movieBackdropUrl(array $movie): string
{
    $url = movieImageUrl($movie['backdrop'] ?? '', 'w1280');
    if ($url === '') {
        $url = moviePosterUrl($movie);
    }
    return $url;
}

function parseMovieTags($tags): array
{
    if (is_array($tags)) {
        return array_values(array_filter(array_map('trim', $tags)));
    }
    if ($tags === null || $tags === '') {
        return [];
    }
    $decoded = json_decode($tags, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded)));
    }
    return array_values(array_filter(array_map('trim', explode(',', (string) $tags))));
}

function parseMovieCast($castData): array
{
    if (is_array($castData)) {
        return $castData;
    }
    if ($castData === null || $castData === '') {
        return [];
    }
    $decoded = json_decode($castData, true);
    return is_array($decoded) ? $decoded : [];
}

function parseMovieGenres($genres): array
{
    return parseMovieTags($genres);
}

function parseDownloadLinks($json): array
{
    if (empty($json)) {
        return [];
    }
    $links = json_decode($json, true);
    return is_array($links) ? $links : [];
}

function encodeDownloadLinks(array $links): string
{
    return json_encode($links, JSON_UNESCAPED_SLASHES);
}

function formatMovieDuration(?int $minutes): string
{
    if (empty($minutes) || $minutes <= 0) {
        return '';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($h > 0) {
        return $h . 'h ' . $m . 'm';
    }
    return $m . ' min';
}

function movieHasSlug(array $movie): bool
{
    return isset($movie['slug']) && $movie['slug'] !== '' && $movie['slug'] !== null;
}

function ensureMovieSlug($conn, array $movie): string
{
    if (movieHasSlug($movie)) {
        return $movie['slug'];
    }

    if (!function_exists('getUniqueSlug')) {
        require_once __DIR__ . '/../admin/includes/functions.php';
    }

    $movieId = (int) ($movie['id'] ?? 0);
    $slug = getUniqueSlug($conn, 'movies', $movie['title'] ?? 'movie', $movieId > 0 ? $movieId : null);

    if ($movieId > 0) {
        $stmt = $conn->prepare('UPDATE movies SET slug = ? WHERE id = ?');
        if ($stmt) {
            $stmt->bind_param('si', $slug, $movieId);
            $stmt->execute();
        }
    }

    return $slug;
}

function getMovieDetailUrl(array $movie, $conn = null): string
{
    if ($conn !== null) {
        return url('movies/' . ensureMovieSlug($conn, $movie));
    }
    if (movieHasSlug($movie)) {
        return url('movies/' . $movie['slug']);
    }
    return url('movie-detail?id=' . (int) ($movie['id'] ?? 0));
}

function getMovieWatchUrl(array $movie, int $sourceIndex = 0, $conn = null): string
{
    if ($conn !== null) {
        $movie['slug'] = ensureMovieSlug($conn, $movie);
    }

    if (movieHasSlug($movie)) {
        $watchUrl = url('movies/' . $movie['slug'] . '/watch');
        if ($sourceIndex <= 0) {
            return $watchUrl;
        }
        return $watchUrl . '?source=' . movieSourceToUrlParam($sourceIndex);
    }
    $params = 'type=movie&id=' . (int) ($movie['id'] ?? 0);
    if ($sourceIndex > 0) {
        $params .= '&source=' . movieSourceToUrlParam($sourceIndex);
    }
    return url('watch?' . $params);
}

/**
 * Convert 0-based source array index to 1-based ?source= URL value.
 */
function movieSourceToUrlParam(int $sourceIndex): int
{
    return $sourceIndex + 1;
}

/**
 * Resolve 0-based source array index from ?source= (1-based in the URL).
 */
function movieSourceIndexFromUrlParam($urlSource, int $sourceCount): int
{
    if ($urlSource === null || $urlSource === '' || (int) $urlSource <= 0) {
        return 0;
    }
    $index = (int) $urlSource - 1;
    if ($index < 0 || $index >= $sourceCount) {
        return 0;
    }
    return $index;
}

function getActiveWatchSources(array $movie): array
{
    if (!function_exists('parseSources')) {
        require_once __DIR__ . '/../admin/includes/functions.php';
    }
    $sources = parseSources($movie['sources'] ?? '[]');
    $active = array_filter($sources, function ($s) {
        if (!($s['isActive'] ?? true) || !($s['isVisible'] ?? true)) {
            return false;
        }
        $type = strtolower($s['type'] ?? '');
        return !in_array($type, ['torrent', 'magnet'], true);
    });
    $active = array_values($active);
    usort($active, function ($a, $b) {
        return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
    });
    return $active;
}

function getActiveDownloadLinks(array $movie): array
{
    $links = parseDownloadLinks($movie['download_links'] ?? '[]');
    return array_values(array_filter($links, function ($l) {
        return !empty($l['url']) && ($l['isActive'] ?? true);
    }));
}

function encodeMovieTagsInput(string $input): string
{
    $tags = array_values(array_unique(array_filter(array_map('trim', preg_split('/[,;]+/', $input)))));
    return json_encode($tags, JSON_UNESCAPED_UNICODE);
}

function isMoviesLoginRequiredSetting($conn): bool
{
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/../admin/includes/functions.php';
    }
    $value = trim((string) getSetting($conn, 'login_required_movies', '0'));
    if ($value === '' || $value === '0' || strtolower($value) === 'false' || strtolower($value) === 'no') {
        return false;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes'], true);
}

function isMoviePremiumContent(array $movie): bool
{
    if (((int) ($movie['is_free'] ?? 1)) === 1) {
        return false;
    }
    return ((int) ($movie['is_premium'] ?? 0)) === 1;
}

/**
 * Determine whether the current user can watch/download a movie.
 *
 * @return array{allowed:bool,reason:string,requires_login:bool,requires_premium:bool}
 */
function getMovieAccess($conn, array $movie): array
{
    if (!function_exists('isLoggedIn')) {
        require_once __DIR__ . '/auth.php';
    }

    $isPremium = isMoviePremiumContent($movie);
    $loginRequiredForAll = isMoviesLoginRequiredSetting($conn);
    $loggedIn = isLoggedIn();
    $hasSubscription = $loggedIn && hasActiveSubscription();

    // Admin setting: require login to view any movie (free or premium).
    if ($loginRequiredForAll && !$loggedIn) {
        return [
            'allowed' => false,
            'reason' => 'login',
            'requires_login' => true,
            'requires_premium' => $isPremium,
        ];
    }

    if ($isPremium) {
        if (!$loggedIn) {
            return [
                'allowed' => false,
                'reason' => 'login',
                'requires_login' => true,
                'requires_premium' => true,
            ];
        }
        if (!$hasSubscription) {
            return [
                'allowed' => false,
                'reason' => 'premium',
                'requires_login' => true,
                'requires_premium' => true,
            ];
        }
        return [
            'allowed' => true,
            'reason' => '',
            'requires_login' => true,
            'requires_premium' => true,
        ];
    }

    return [
        'allowed' => true,
        'reason' => '',
        'requires_login' => $loginRequiredForAll,
        'requires_premium' => false,
    ];
}

function getMovieLoginUrl(array $movie, int $sourceIndex = 0, $conn = null): string
{
    return url('login') . '?redirect=' . urlencode(getMovieWatchUrl($movie, $sourceIndex, $conn));
}

/** Redirect guests to login when required; returns access info otherwise. */
function enforceMovieWatchAccess($conn, array $movie): array
{
    $access = getMovieAccess($conn, $movie);
    if (!$access['allowed'] && $access['reason'] === 'login') {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . url('login'));
        exit;
    }
    return $access;
}

function resolveMovieWatchHref(array $movie, array $access, int $sourceIndex = 0, $conn = null): string
{
    if ($access['allowed']) {
        return getMovieWatchUrl($movie, $sourceIndex, $conn);
    }
    if ($access['reason'] === 'login') {
        return getMovieLoginUrl($movie, $sourceIndex, $conn);
    }
    return getMovieWatchUrl($movie, $sourceIndex, $conn);
}

function getMovieTrailerUrl(array $movie, $conn = null): string
{
    if (!empty($movie['trailer_url'])) {
        return trim($movie['trailer_url']);
    }
    if ($conn && !empty($movie['tmdb_id'])) {
        return fetchTmdbMovieTrailerUrl($conn, (int) $movie['tmdb_id']);
    }
    return '';
}

function fetchTmdbMovieTrailerUrl($conn, int $tmdbId): string
{
    $videos = tmdbApiRequest($conn, "movie/$tmdbId/videos", ['language' => 'en-US']);
    if (isset($videos['error']) || empty($videos['results'])) {
        return '';
    }
    foreach ($videos['results'] as $video) {
        if (($video['type'] ?? '') === 'Trailer' && ($video['site'] ?? '') === 'YouTube' && !empty($video['key'])) {
            return 'https://www.youtube.com/watch?v=' . $video['key'];
        }
    }
    foreach ($videos['results'] as $video) {
        if (($video['site'] ?? '') === 'YouTube' && !empty($video['key'])) {
            return 'https://www.youtube.com/watch?v=' . $video['key'];
        }
    }
    return '';
}

function youtubeEmbedUrl(string $url): string
{
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]+)#', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1&rel=0&modestbranding=1';
    }
    return $url;
}

/**
 * Quality badges for movie poster overlays (admin Quality Badge + source quality, never source label).
 *
 * @return string[]
 */
function getMovieQualityBadges(array $movie): array
{
    $badges = [];
    $qualityLabel = trim((string) ($movie['quality_label'] ?? ''));
    if ($qualityLabel !== '') {
        return [$qualityLabel];
    }

    foreach (getActiveWatchSources($movie) as $source) {
        $quality = trim($source['quality'] ?? '');
        if ($quality === '' || strcasecmp($quality, 'Auto') === 0) {
            continue;
        }
        if (!in_array($quality, $badges, true)) {
            $badges[] = $quality;
        }
    }

    return $badges;
}

/** @deprecated Use getMovieQualityBadges() */
function getMovieQualityLabels(array $movie): array
{
    return getMovieQualityBadges($movie);
}

function renderMoviePosterBadges(array $movie): void
{
    $badges = getMovieQualityBadges($movie);
    $tags = parseMovieTags($movie['tags'] ?? '');
    if (empty($badges) && empty($tags)) {
        return;
    }

    echo '<div class="movie-card-badges">';
    foreach ($badges as $badge) {
        echo '<span class="movie-badge movie-badge-quality">' . htmlspecialchars($badge) . '</span>';
    }
    foreach ($tags as $tag) {
        echo '<span class="movie-badge movie-badge-tag">' . htmlspecialchars($tag) . '</span>';
    }
    echo '</div>';
}

function getMovieSourceDisplayLabel(array $source, int $index = 0): string
{
    $quality = trim($source['quality'] ?? '');
    if ($quality !== '' && strcasecmp($quality, 'Auto') !== 0) {
        return $quality;
    }
    $label = trim($source['label'] ?? '');
    if ($label !== '') {
        return $label;
    }
    return 'Source ' . ($index + 1);
}

/**
 * @return array{mode:string,url:string}
 */
function resolveMovieSourceEmbedMode(array $source, $conn = null): array
{
    $type = strtolower($source['type'] ?? 'embed');
    $url = trim($source['url'] ?? '');

    if ($url === '') {
        return ['mode' => 'video', 'url' => ''];
    }

    $isHtml = isset($url[0]) && $url[0] === '<';
    $isHttpUrl = (bool) preg_match('#^https?://#i', $url);

    if (in_array($type, ['iframe', 'iframe-only'], true) || ($type === 'embed' && $isHttpUrl)) {
        return ['mode' => 'iframe_url', 'url' => $url];
    }

    if (in_array($type, ['embed', 'html-embed', 'html', 'iframe-only'], true) && ($isHtml || $url !== '')) {
        return ['mode' => 'embed_proxy', 'url' => $url];
    }

    return ['mode' => 'video', 'url' => $url];
}

/**
 * Build SEO meta for movie detail or watch pages.
 *
 * @return array{page_title:string,meta_description:string,meta_keywords:string,canonical_url:string,og_image:string,og_type:string}
 */
function buildMovieSeoMeta($conn, array $movie, string $context = 'detail'): array
{
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/../admin/includes/functions.php';
    }

    $siteName = getSetting($conn, 'site_name', 'StreamFlix');
    $title = trim($movie['title'] ?? 'Movie');
    $year = !empty($movie['release_year']) ? (int) $movie['release_year'] : 0;
    $genres = parseMovieGenres($movie['genres'] ?? '[]');
    $tags = parseMovieTags($movie['tags'] ?? '');
    $category = trim($movie['category_name'] ?? '');
    $yearText = $year > 0 ? " ({$year})" : '';

    $pageTitle = "Watch {$title} Full Movie online";
    $canonicalUrl = $context === 'watch'
        ? getMovieWatchUrl($movie, 0, $conn)
        : getMovieDetailUrl($movie, $conn);

    $overview = trim(strip_tags($movie['description'] ?? ''));
    if ($overview !== '') {
        $metaDescription = mb_strlen($overview) > 158 ? mb_substr($overview, 0, 155) . '...' : $overview;
    } else {
        $metaDescription = "Watch {$title}{$yearText} full movie online in HD on {$siteName}. Stream or download {$title} free with multiple sources.";
    }

    $keywordParts = [
        "watch {$title} online",
        "{$title} full movie",
        "{$title} full movie online",
        "stream {$title}",
        "{$title} {$year}",
        "{$title} download",
        "{$title} HD",
        "{$siteName} movies",
    ];
    if ($category !== '') {
        $keywordParts[] = "{$category} movies";
        $keywordParts[] = "watch {$category} movies online";
    }
    foreach ($genres as $genre) {
        $keywordParts[] = "{$genre} movies";
    }
    foreach ($tags as $tag) {
        $keywordParts[] = "{$title} {$tag}";
    }
    $keywordParts = array_values(array_unique(array_filter(array_map('trim', $keywordParts))));
    $metaKeywords = implode(', ', array_slice($keywordParts, 0, 25));

    return [
        'page_title' => $pageTitle,
        'meta_description' => $metaDescription,
        'meta_keywords' => $metaKeywords,
        'canonical_url' => $canonicalUrl,
        'og_image' => moviePosterUrl($movie),
        'og_type' => 'video.movie',
    ];
}

function applyMovieSeoMeta($conn, array $movie, string $context = 'detail'): void
{
    $seo = buildMovieSeoMeta($conn, $movie, $context);
    $GLOBALS['page_title'] = $seo['page_title'];
    $GLOBALS['meta_description'] = $seo['meta_description'];
    $GLOBALS['meta_keywords'] = $seo['meta_keywords'];
    $GLOBALS['canonical_url'] = $seo['canonical_url'];
    $GLOBALS['og_image'] = $seo['og_image'];
    $GLOBALS['og_type'] = $seo['og_type'];
}
