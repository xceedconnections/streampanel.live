<?php
/**
 * Dynamic SEO helpers for public pages.
 */

if (!function_exists('seoTruncate')) {
    function seoTruncate(string $text, int $max = 158): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $max) {
                return $text;
            }
            return rtrim(mb_substr($text, 0, $max - 3)) . '...';
        }
        if (strlen($text) <= $max) {
            return $text;
        }
        return rtrim(substr($text, 0, $max - 3)) . '...';
    }
}

if (!function_exists('seoUniqueJoin')) {
    function seoUniqueJoin(array $parts, int $limit = 30): string
    {
        $clean = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $key = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);
            if (!isset($clean[$key])) {
                $clean[$key] = $part;
            }
        }
        return implode(', ', array_slice(array_values($clean), 0, $limit));
    }
}

if (!function_exists('seoCastNames')) {
    function seoCastNames($castData, int $limit = 8): array
    {
        if (!function_exists('parseMovieCast')) {
            require_once __DIR__ . '/movie_helpers.php';
        }
        $names = [];
        foreach (parseMovieCast($castData) as $member) {
            if (is_array($member)) {
                $name = trim((string) ($member['name'] ?? ''));
            } else {
                $name = trim((string) $member);
            }
            if ($name !== '') {
                $names[] = $name;
            }
            if (count($names) >= $limit) {
                break;
            }
        }
        return $names;
    }
}

if (!function_exists('seoApplyMeta')) {
    function seoApplyMeta(array $seo): void
    {
        foreach (['page_title', 'meta_description', 'meta_keywords', 'canonical_url', 'og_image', 'og_type', 'seo_json_ld'] as $key) {
            if (array_key_exists($key, $seo)) {
                $GLOBALS[$key] = $seo[$key];
            }
        }
    }
}

if (!function_exists('buildHomeSeoMeta')) {
    function buildHomeSeoMeta($conn): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        $siteName = getSetting($conn, 'site_name', 'StreamPanel');
        $siteDesc = trim(getSetting($conn, 'site_description', ''));

        $pageTitle = "Watch Free Movies Online, TV Shows & Live TV Channels HD | {$siteName}";
        $metaDescription = $siteDesc !== ''
            ? seoTruncate($siteDesc, 160)
            : "Watch free movies online in HD, stream TV shows, and enjoy live TV channels free on {$siteName}. Watch full movies online, free Hindi movies, live news channels, and HD streaming with no signup required.";

        $metaKeywords = seoUniqueJoin([
            'watch free movies online',
            'free movies online HD',
            'watch movies online free',
            'download movies free',
            'free Hindi movies',
            'watch TV shows online free',
            'stream TV series free',
            'live TV free',
            'watch live TV online',
            'free live TV channels HD',
            'online movies free to watch',
            'HD streaming free',
            "{$siteName} movies",
            "{$siteName} live TV",
        ], 40);

        return [
            'page_title' => $pageTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'canonical_url' => rtrim(BASE_URL, '/') . '/',
            'og_image' => '',
            'og_type' => 'website',
            'seo_json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $siteName,
                'url' => rtrim(BASE_URL, '/') . '/',
                'description' => $metaDescription,
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => rtrim(BASE_URL, '/') . '/search?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }
}

if (!function_exists('buildMoviesListingSeoMeta')) {
    function buildMoviesListingSeoMeta($conn, array $filters = []): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        $siteName = getSetting($conn, 'site_name', 'StreamPanel');
        $category = trim((string) ($filters['category'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $year = (int) ($filters['year'] ?? 0);

        $focus = 'Free Movies Online HD';
        if ($search !== '') {
            $focus = "{$search} Movies";
        } elseif ($category !== '') {
            $focus = "{$category} Movies";
        } elseif ($year > 0) {
            $focus = "{$year} Movies";
        }

        $pageTitle = "Watch {$focus} Free - Full Movies Online | {$siteName}";
        $metaDescription = "Watch free movies online in HD on {$siteName}. Stream full movies free to watch, download movies free, browse latest Hindi movies, Hollywood movies, cast, directors and plot summaries.";
        if ($category !== '') {
            $metaDescription = "Watch free {$category} movies online in HD on {$siteName}. Stream {$category} full movies free, download {$category} movies, and browse plot, cast and director details.";
        }

        $metaKeywords = seoUniqueJoin([
            'watch free movies online',
            'free movies HD',
            'full movies online free',
            'download movies free',
            'Hindi movies free',
            'Hollywood movies online',
            'movie cast and plot',
            'watch movies free',
            $category !== '' ? "{$category} movies free" : '',
            $year > 0 ? "{$year} movies online" : '',
            "{$siteName} movies",
        ]);

        return [
            'page_title' => $pageTitle,
            'meta_description' => seoTruncate($metaDescription),
            'meta_keywords' => $metaKeywords,
            'canonical_url' => rtrim(BASE_URL, '/') . '/movies',
            'og_image' => '',
            'og_type' => 'website',
        ];
    }
}

if (!function_exists('buildTvShowsListingSeoMeta')) {
    function buildTvShowsListingSeoMeta($conn, array $filters = []): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        $siteName = getSetting($conn, 'site_name', 'StreamPanel');
        $category = trim((string) ($filters['category'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $focus = 'TV Shows';
        if ($search !== '') {
            $focus = "{$search} TV Shows";
        } elseif ($category !== '') {
            $focus = "{$category} TV Shows";
        }

        $pageTitle = "Watch {$focus} Online Free HD - Stream Series | {$siteName}";
        $metaDescription = "Watch TV shows online free in HD on {$siteName}. Stream full seasons and episodes free, watch series online, and enjoy the latest TV shows streaming without signup.";
        if ($category !== '') {
            $metaDescription = "Watch {$category} TV shows online free in HD on {$siteName}. Stream {$category} series episodes free and watch full seasons online.";
        }

        return [
            'page_title' => $pageTitle,
            'meta_description' => seoTruncate($metaDescription),
            'meta_keywords' => seoUniqueJoin([
                'watch TV shows online free',
                'stream TV series free',
                'TV shows HD free',
                'watch series online',
                'free TV episodes',
                $category !== '' ? "{$category} TV shows" : '',
                "{$siteName} TV shows",
            ]),
            'canonical_url' => rtrim(BASE_URL, '/') . '/tv-shows',
            'og_image' => '',
            'og_type' => 'website',
        ];
    }
}

if (!function_exists('buildLiveTvListingSeoMeta')) {
    function buildLiveTvListingSeoMeta($conn): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        $siteName = getSetting($conn, 'site_name', 'StreamPanel');

        return [
            'page_title' => "Watch Live TV Online Free - HD Live TV Channels Streaming | {$siteName}",
            'meta_description' => seoTruncate("Watch live TV online free on {$siteName}. Stream HD live TV channels including news, sports, entertainment and more. Watch live streaming TV free with no registration."),
            'meta_keywords' => seoUniqueJoin([
                'watch live TV online free',
                'live TV channels HD',
                'free live TV streaming',
                'watch live news channels',
                'HD TV channels free',
                'online live TV',
                'live streaming free',
                "{$siteName} live TV",
            ]),
            'canonical_url' => rtrim(BASE_URL, '/') . '/live-tv',
            'og_image' => '',
            'og_type' => 'website',
        ];
    }
}

if (!function_exists('buildMovieSeoMeta')) {
    /**
     * @return array{page_title:string,meta_description:string,meta_keywords:string,canonical_url:string,og_image:string,og_type:string,seo_json_ld?:array}
     */
    function buildMovieSeoMeta($conn, array $movie, string $context = 'detail'): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        if (!function_exists('parseMovieTags')) {
            require_once __DIR__ . '/movie_helpers.php';
        }

        $siteName = getSetting($conn, 'site_name', 'StreamPanel');
        $title = trim($movie['title'] ?? 'Movie');
        $year = !empty($movie['release_year']) ? (int) $movie['release_year'] : 0;
        $yearText = $year > 0 ? " ({$year})" : '';
        $genres = parseMovieGenres($movie['genres'] ?? '[]');
        $tags = parseMovieTags($movie['tags'] ?? '');
        $category = trim($movie['category_name'] ?? '');
        $director = trim($movie['director'] ?? '');
        $quality = trim($movie['quality_label'] ?? 'HD');
        if ($quality === '') {
            $quality = 'HD';
        }
        $castNames = seoCastNames($movie['cast_data'] ?? '', 8);
        $castText = !empty($castNames) ? implode(', ', $castNames) : '';
        $langTags = array_values(array_filter($tags, static function ($tag) {
            return (bool) preg_match('/^(hindi|english|urdu|punjabi|tamil|telugu|bengali|malayalam|kannada|arabic|spanish|french|korean|chinese)$/i', trim($tag));
        }));
        $primaryLang = $langTags[0] ?? '';

        $pageTitle = $context === 'watch'
            ? "Watch {$title} Movie {$quality} Free Online | {$siteName}"
            : "Watch {$title} Full Movie Online Free {$quality} | {$siteName}";

        $plot = trim(strip_tags($movie['description'] ?? ''));
        $descParts = [
            "Watch {$title}{$yearText} movie {$quality} free online on {$siteName}.",
            "Watch {$title} full movie online free.",
            "Download {$title} movie free.",
        ];
        if ($primaryLang !== '') {
            $descParts[] = "Download {$title} {$primaryLang} movie free.";
            $descParts[] = "Watch {$title} {$primaryLang} full movie online.";
        }
        if ($director !== '') {
            $descParts[] = "Directed by {$director}.";
        }
        if ($castText !== '') {
            $descParts[] = "Starring {$castText}.";
        }
        if ($plot !== '') {
            $descParts[] = $plot;
        } else {
            $descParts[] = "Stream {$title} with multiple sources free.";
        }
        $metaDescription = seoTruncate(implode(' ', $descParts), 165);

        $keywordParts = [
            "watch {$title} movie HD free",
            "watch {$title} full movie online",
            "download {$title} movie free",
            "{$title} full movie",
            "stream {$title} online free",
            "{$title} {$quality}",
            "{$title} movie download",
            "watch {$title} online",
        ];
        if ($year > 0) {
            $keywordParts[] = "{$title} {$year}";
            $keywordParts[] = "watch {$title} {$year} movie";
        }
        if ($primaryLang !== '') {
            $keywordParts[] = "download {$title} {$primaryLang} movie free";
            $keywordParts[] = "watch {$title} {$primaryLang} movie free";
            $keywordParts[] = "{$title} {$primaryLang} full movie";
        }
        if ($category !== '') {
            $keywordParts[] = "{$category} movies free";
            $keywordParts[] = "watch {$category} movies online";
        }
        if ($director !== '') {
            $keywordParts[] = "{$title} director {$director}";
            $keywordParts[] = "movies by {$director}";
        }
        foreach ($castNames as $actor) {
            $keywordParts[] = "{$title} {$actor}";
            $keywordParts[] = "{$actor} movies";
        }
        foreach ($genres as $genre) {
            $keywordParts[] = "{$genre} movies free";
            $keywordParts[] = "{$title} {$genre}";
        }
        foreach ($tags as $tag) {
            $keywordParts[] = "{$title} {$tag}";
            $keywordParts[] = "{$tag} movies free";
        }
        $keywordParts[] = "{$siteName} movies";

        $canonicalUrl = $context === 'watch'
            ? getMovieWatchUrl($movie, 0, $conn)
            : getMovieDetailUrl($movie, $conn);

        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => 'Movie',
            'name' => $title,
            'description' => $metaDescription,
            'url' => $canonicalUrl,
            'image' => function_exists('moviePosterUrl') ? moviePosterUrl($movie) : '',
        ];
        if ($year > 0) {
            $jsonLd['dateCreated'] = (string) $year;
        }
        if ($director !== '') {
            $jsonLd['director'] = ['@type' => 'Person', 'name' => $director];
        }
        if (!empty($castNames)) {
            $jsonLd['actor'] = array_map(static function ($name) {
                return ['@type' => 'Person', 'name' => $name];
            }, $castNames);
        }
        if (!empty($genres)) {
            $jsonLd['genre'] = $genres;
        }

        return [
            'page_title' => $pageTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => seoUniqueJoin($keywordParts, 40),
            'canonical_url' => $canonicalUrl,
            'og_image' => function_exists('moviePosterUrl') ? moviePosterUrl($movie) : '',
            'og_type' => 'video.movie',
            'seo_json_ld' => $jsonLd,
        ];
    }
}

if (!function_exists('applyMovieSeoMeta')) {
    function applyMovieSeoMeta($conn, array $movie, string $context = 'detail'): void
    {
        seoApplyMeta(buildMovieSeoMeta($conn, $movie, $context));
    }
}

if (!function_exists('buildTvShowSeoMeta')) {
    function buildTvShowSeoMeta($conn, array $show): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        $siteName = getSetting($conn, 'site_name', 'StreamPanel');
        $title = trim($show['title'] ?? 'TV Show');
        $year = !empty($show['release_year']) ? (int) $show['release_year'] : 0;
        $yearText = $year > 0 ? " ({$year})" : '';
        $category = trim($show['category_name'] ?? '');
        $plot = trim(strip_tags($show['description'] ?? ''));
        $slug = trim($show['slug'] ?? '');
        $canonical = $slug !== ''
            ? rtrim(BASE_URL, '/') . '/tv-show/' . rawurlencode($slug)
            : rtrim(BASE_URL, '/') . '/tv-show-detail?id=' . (int) ($show['id'] ?? 0);

        $pageTitle = "Watch {$title} TV Show Online Free HD - Full Episodes | {$siteName}";
        $descParts = [
            "Watch {$title}{$yearText} TV show online free in HD on {$siteName}.",
            "Stream {$title} full episodes free.",
            "Watch {$title} series online free.",
            "Download {$title} episodes free.",
        ];
        if ($category !== '') {
            $descParts[] = "{$category} TV series streaming free.";
        }
        if ($plot !== '') {
            $descParts[] = $plot;
        }
        $metaDescription = seoTruncate(implode(' ', $descParts), 165);

        $keywords = [
            "watch {$title} online free",
            "{$title} full episodes",
            "stream {$title} free",
            "{$title} TV show HD",
            "watch {$title} series online",
            "download {$title} episodes free",
            "{$title} season episodes",
        ];
        if ($year > 0) {
            $keywords[] = "{$title} {$year}";
        }
        if ($category !== '') {
            $keywords[] = "{$category} TV shows free";
        }
        $keywords[] = "{$siteName} TV shows";

        $poster = '';
        if (!empty($show['poster'])) {
            $poster = function_exists('assetUrl') ? assetUrl($show['poster']) : $show['poster'];
        } elseif (!empty($show['thumbnail'])) {
            $poster = function_exists('assetUrl') ? assetUrl($show['thumbnail']) : $show['thumbnail'];
        }

        return [
            'page_title' => $pageTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => seoUniqueJoin($keywords, 35),
            'canonical_url' => $canonical,
            'og_image' => $poster,
            'og_type' => 'video.tv_show',
            'seo_json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'TVSeries',
                'name' => $title,
                'description' => $metaDescription,
                'url' => $canonical,
                'image' => $poster,
            ],
        ];
    }
}

if (!function_exists('buildLiveChannelSeoMeta')) {
    function buildLiveChannelSeoMeta($conn, array $channel): array
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        $siteName = getSetting($conn, 'site_name', 'StreamPanel');
        $name = trim($channel['name'] ?? 'Live TV Channel');
        $category = trim($channel['category'] ?? 'TV');
        $country = trim($channel['country'] ?? '');
        $language = trim($channel['language'] ?? '');
        $slug = trim($channel['slug'] ?? '');
        $plot = trim(strip_tags($channel['description'] ?? ''));
        $canonical = $slug !== ''
            ? rtrim(BASE_URL, '/') . '/watch-live-tv/' . rawurlencode($slug)
            : rtrim(BASE_URL, '/') . '/live-tv';

        $pageTitle = "Watch {$name} Live - {$name} Live TV Channel Free HD | {$siteName}";
        $descParts = [
            "Watch {$name} Live free on {$siteName}.",
            "Watch {$name} Live TV channel free HD.",
            "Watch {$name} streaming free online.",
            "Stream {$name} live HD TV channels free.",
        ];
        if ($category !== '') {
            $descParts[] = "{$name} {$category} live streaming.";
        }
        if ($language !== '') {
            $descParts[] = "{$name} {$language} live TV free.";
        }
        if ($plot !== '') {
            $descParts[] = $plot;
        }
        $metaDescription = seoTruncate(implode(' ', $descParts), 165);

        $keywords = [
            "watch {$name} Live",
            "Watch {$name} Live TV channel Free HD",
            "Watch {$name} Streaming free",
            "{$name} live stream",
            "{$name} live online",
            "{$name} HD live TV",
            "HD TV channels",
            "free live TV channels",
            "{$name} live free",
        ];
        if ($category !== '') {
            $keywords[] = "{$category} live TV free";
            $keywords[] = "{$name} {$category}";
        }
        if ($country !== '') {
            $keywords[] = "{$country} live TV channels";
        }
        if ($language !== '') {
            $keywords[] = "{$language} live TV";
        }
        $keywords[] = "{$siteName} live TV";

        $logo = !empty($channel['logo'])
            ? (function_exists('assetUrl') ? assetUrl($channel['logo']) : $channel['logo'])
            : '';

        return [
            'page_title' => $pageTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => seoUniqueJoin($keywords, 35),
            'canonical_url' => $canonical,
            'og_image' => $logo,
            'og_type' => 'website',
            'seo_json_ld' => [
                '@context' => 'https://schema.org',
                '@type' => 'BroadcastService',
                'name' => $name,
                'description' => $metaDescription,
                'url' => $canonical,
                'image' => $logo,
                'broadcastDisplayName' => $name,
            ],
        ];
    }
}

if (!function_exists('renderSeoJsonLd')) {
    function renderSeoJsonLd($jsonLd): void
    {
        if (empty($jsonLd) || !is_array($jsonLd)) {
            return;
        }
        echo '<script type="application/ld+json">' .
            json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) .
            '</script>' . "\n";
    }
}

if (!function_exists('getPublicCustomCode')) {
    function getPublicCustomCode($conn, string $key): string
    {
        if (!function_exists('getSetting')) {
            require_once __DIR__ . '/../admin/includes/functions.php';
        }
        return (string) getSetting($conn, $key, '');
    }
}

if (!function_exists('renderPublicCustomCode')) {
    /**
     * Echo admin-configured HTML/CSS for public pages only (ads, analytics, etc.).
     */
    function renderPublicCustomCode($conn, string $key): void
    {
        $code = getPublicCustomCode($conn, $key);
        if ($code === '') {
            return;
        }
        echo "\n" . $code . "\n";
    }
}
