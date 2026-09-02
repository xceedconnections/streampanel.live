<?php
/**
 * Cast / actor search and profile helpers.
 */

require_once __DIR__ . '/movie_helpers.php';

function actorNameToSlug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

    return trim($slug, '-');
}

function getActorProfileUrl(string $name): string
{
    return url('actors/' . actorNameToSlug($name));
}

function actorProfileImageUrl(?string $profilePath, string $size = 'w500'): string
{
    if ($profilePath === null || $profilePath === '') {
        return '';
    }

    return tmdbImageUrl($profilePath, $size);
}

function castMemberMatchesQuery(array $member, string $query): bool
{
    $name = strtolower(trim($member['name'] ?? ''));
    $q = strtolower(trim($query));

    if ($q === '' || $name === '') {
        return false;
    }

    return strpos($name, $q) !== false;
}

/**
 * @param array<int, array<string, mixed>> $movies
 * @return array<int, array<string, mixed>>
 */
function collectActorsFromMovies(array $movies, string $query, int $limit = 10): array
{
    $actors = [];
    $query = trim($query);

    foreach ($movies as $movie) {
        foreach (parseMovieCast($movie['cast_data'] ?? '') as $member) {
            if (!castMemberMatchesQuery($member, $query)) {
                continue;
            }

            $tmdbId = (int) ($member['id'] ?? 0);
            $name = trim($member['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $key = $tmdbId > 0 ? 'id:' . $tmdbId : 'name:' . strtolower($name);
            if (isset($actors[$key])) {
                continue;
            }

            $profilePath = $member['profile_path'] ?? null;
            $actors[$key] = [
                'id' => $tmdbId,
                'name' => $name,
                'slug' => actorNameToSlug($name),
                'profile_path' => $profilePath,
                'profile_url' => actorProfileImageUrl($profilePath, 'w185'),
                'url' => getActorProfileUrl($name),
            ];

            if (count($actors) >= $limit) {
                break 2;
            }
        }
    }

    return array_values($actors);
}

/**
 * @return array<int, array<string, mixed>>
 */
function searchMoviesWithCast($conn, string $query, int $limit = 50, ?string $categorySlug = null): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $searchParam = '%' . strtolower($query) . '%';
    $sql = "SELECT m.*, c.name AS category_name, c.slug AS category_slug
            FROM movies m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE (m.is_active = 1 OR m.is_active IS NULL)
            AND (
                LOWER(m.title) LIKE ?
                OR LOWER(COALESCE(m.description, '')) LIKE ?
                OR LOWER(COALESCE(m.cast_data, '')) LIKE ?
            )";
    $params = [$searchParam, $searchParam, $searchParam];
    $types = 'sss';

    if ($categorySlug) {
        $sql .= " AND c.slug = ?";
        $params[] = $categorySlug;
        $types .= 's';
    }

    $sql .= " ORDER BY m.featured DESC, m.views DESC, m.created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * @return array<int, array<string, mixed>>
 */
function searchActors($conn, string $query, int $limit = 20): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $searchParam = '%' . strtolower($query) . '%';
    $stmt = $conn->prepare(
        "SELECT id, title, slug, cast_data, poster, thumbnail, release_year, featured, views
         FROM movies
         WHERE (is_active = 1 OR is_active IS NULL)
         AND cast_data IS NOT NULL AND cast_data != '' AND cast_data != '[]'
         AND LOWER(cast_data) LIKE ?
         ORDER BY featured DESC, views DESC
         LIMIT 120"
    );
    $stmt->bind_param('s', $searchParam);
    $stmt->execute();
    $movies = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return collectActorsFromMovies($movies, $query, $limit);
}

/**
 * @return array<string, mixed>|null
 */
function getActorProfile($conn, string $slug): ?array
{
    $slug = trim($slug);
    if ($slug === '') {
        return null;
    }

    $result = $conn->query(
        "SELECT m.*, c.name AS category_name
         FROM movies m
         LEFT JOIN categories c ON m.category_id = c.id
         WHERE (m.is_active = 1 OR m.is_active IS NULL)
         AND m.cast_data IS NOT NULL AND m.cast_data != '' AND m.cast_data != '[]'"
    );

    if (!$result) {
        return null;
    }

    $actor = null;
    $filmography = [];

    while ($movie = $result->fetch_assoc()) {
        foreach (parseMovieCast($movie['cast_data'] ?? '') as $member) {
            if (actorNameToSlug($member['name'] ?? '') !== $slug) {
                continue;
            }

            if ($actor === null) {
                $actor = [
                    'id' => (int) ($member['id'] ?? 0),
                    'name' => $member['name'] ?? '',
                    'slug' => $slug,
                    'profile_path' => $member['profile_path'] ?? null,
                    'biography' => '',
                    'birthday' => '',
                    'place_of_birth' => '',
                    'known_for_department' => 'Acting',
                ];
            }

            $filmography[] = [
                'movie' => $movie,
                'character' => $member['character'] ?? '',
            ];
        }
    }

    if ($actor === null) {
        return null;
    }

    usort($filmography, static function ($a, $b) {
        return (int) ($b['movie']['release_year'] ?? 0) <=> (int) ($a['movie']['release_year'] ?? 0);
    });

    $actor['movies'] = $filmography;

    if ($actor['id'] > 0) {
        $person = tmdbApiRequest($conn, 'person/' . $actor['id'], ['language' => 'en-US']);
        if (!isset($person['error'])) {
            $actor['biography'] = trim($person['biography'] ?? '');
            $actor['birthday'] = $person['birthday'] ?? '';
            $actor['place_of_birth'] = $person['place_of_birth'] ?? '';
            $actor['known_for_department'] = $person['known_for_department'] ?? $actor['known_for_department'];
            if (!empty($person['profile_path'])) {
                $actor['profile_path'] = $person['profile_path'];
            }
        }
    }

    return $actor;
}
