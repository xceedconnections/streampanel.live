<?php
/**
 * TMDB API helpers.
 */

require_once __DIR__ . '/../config/config.php';

function getTmdbApiKey($conn): string
{
    if (!function_exists('getSetting')) {
        require_once __DIR__ . '/../admin/includes/functions.php';
    }
    return trim(getSetting($conn, 'tmdb_api_key', ''));
}

function parseTmdbIdFromInput(string $input): ?int
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (preg_match('/^\d+$/', $input)) {
        return (int) $input;
    }
    if (preg_match('#themoviedb\.org/movie/(\d+)#i', $input, $m)) {
        return (int) $m[1];
    }
    if (preg_match('#tmdb\.org/movie/(\d+)#i', $input, $m)) {
        return (int) $m[1];
    }
    return null;
}

function tmdbImageUrl(?string $path, string $size = 'w500'): string
{
    if ($path === null || $path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return IMAGE_BASE_URL . '/' . $size . $path;
}

function tmdbApiRequest($conn, string $endpoint, array $params = []): array
{
    $apiKey = getTmdbApiKey($conn);
    if ($apiKey === '') {
        return ['error' => 'TMDB API key is not configured. Add it in Admin → Settings.'];
    }

    $params['api_key'] = $apiKey;
    $url = 'https://api.themoviedb.org/3/' . ltrim($endpoint, '/') . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return ['error' => 'TMDB request failed: ' . $err];
    }

    $data = json_decode($body, true);
    if ($code >= 400) {
        $msg = $data['status_message'] ?? ('HTTP ' . $code);
        return ['error' => $msg];
    }

    return is_array($data) ? $data : ['error' => 'Invalid TMDB response'];
}

function fetchTmdbMovieData($conn, int $tmdbId): array
{
    $movie = tmdbApiRequest($conn, "movie/$tmdbId", ['language' => 'en-US']);
    if (isset($movie['error'])) {
        return $movie;
    }

    $credits = tmdbApiRequest($conn, "movie/$tmdbId/credits", ['language' => 'en-US']);
    $cast = [];
    if (!isset($credits['error']) && !empty($credits['cast'])) {
        foreach (array_slice($credits['cast'], 0, 15) as $person) {
            $cast[] = [
                'id' => $person['id'] ?? null,
                'name' => $person['name'] ?? '',
                'character' => $person['character'] ?? '',
                'profile_path' => $person['profile_path'] ?? null,
            ];
        }
    }

    $director = '';
    if (!isset($credits['error']) && !empty($credits['crew'])) {
        foreach ($credits['crew'] as $person) {
            if (($person['job'] ?? '') === 'Director') {
                $director = $person['name'] ?? '';
                break;
            }
        }
    }

    $genres = [];
    if (!empty($movie['genres'])) {
        foreach ($movie['genres'] as $g) {
            if (!empty($g['name'])) {
                $genres[] = $g['name'];
            }
        }
    }

    $year = null;
    if (!empty($movie['release_date'])) {
        $year = (int) substr($movie['release_date'], 0, 4);
    }

    $videos = tmdbApiRequest($conn, "movie/$tmdbId/videos", ['language' => 'en-US']);
    $trailerUrl = '';
    if (!isset($videos['error']) && !empty($videos['results'])) {
        foreach ($videos['results'] as $video) {
            if (($video['type'] ?? '') === 'Trailer' && ($video['site'] ?? '') === 'YouTube' && !empty($video['key'])) {
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['key'];
                break;
            }
        }
        if ($trailerUrl === '') {
            foreach ($videos['results'] as $video) {
                if (($video['site'] ?? '') === 'YouTube' && !empty($video['key'])) {
                    $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['key'];
                    break;
                }
            }
        }
    }

    return [
        'tmdb_id' => $tmdbId,
        'title' => $movie['title'] ?? '',
        'description' => $movie['overview'] ?? '',
        'thumbnail' => tmdbImageUrl($movie['poster_path'] ?? null, 'w342'),
        'poster' => tmdbImageUrl($movie['poster_path'] ?? null, 'w500'),
        'backdrop' => tmdbImageUrl($movie['backdrop_path'] ?? null, 'w1280'),
        'logo' => '',
        'trailer_url' => $trailerUrl,
        'duration' => (int) ($movie['runtime'] ?? 0),
        'release_year' => $year,
        'rating' => round((float) ($movie['vote_average'] ?? 0), 1),
        'director' => $director,
        'genres' => $genres,
        'cast_data' => $cast,
    ];
}
