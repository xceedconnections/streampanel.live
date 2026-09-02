<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';
require_once __DIR__ . '/includes/cast_helpers.php';

$conn = getDBConnection();
ensureMoviesSchema($conn);

if (!isSectionEnabled($conn, 'movies')) {
    include 'includes/header.php';
    echo '<div class="min-h-screen flex items-center justify-center py-20"><div class="text-center"><h1 class="text-4xl font-bold text-red-500 mb-4">Section is Under Maintenance</h1><a href="' . url() . '" class="bg-netflix-red px-6 py-3 rounded">Go Home</a></div></div>';
    include 'includes/footer.php';
    exit;
}

$slug = trim($_GET['slug'] ?? '');
if ($slug === '') {
    header('Location: ' . url('movies'));
    exit;
}

$actor = getActorProfile($conn, $slug);
if (!$actor) {
    http_response_code(404);
    $page_title = 'Actor Not Found';
    include 'includes/header.php';
    echo '<div class="min-h-screen flex items-center justify-center py-20"><div class="text-center"><h1 class="text-4xl font-bold mb-4">Actor Not Found</h1><p class="text-gray-400 mb-6">We could not find this artist in our movie catalog.</p><a href="' . url('movies') . '" class="bg-netflix-red px-6 py-3 rounded">Browse Movies</a></div></div>';
    include 'includes/footer.php';
    exit;
}

$page_title = $actor['name'];
$meta_description = 'Watch movies starring ' . $actor['name'] . ' online.';
$profileImage = actorProfileImageUrl($actor['profile_path'] ?? null, 'w500');
$filmography = $actor['movies'] ?? [];

include 'includes/header.php';
?>

<style>
.actor-page {
    min-height: 100vh;
    background: #000;
    color: #fff;
    padding: 2rem 0 3rem;
}
.actor-hero {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .actor-hero {
        flex-direction: row;
        align-items: flex-start;
        padding: 0 3rem 2.5rem;
        gap: 2rem;
    }
}
.actor-photo-wrap {
    flex-shrink: 0;
    width: 180px;
    margin: 0 auto;
}
@media (min-width: 768px) {
    .actor-photo-wrap {
        width: 220px;
        margin: 0;
    }
}
.actor-photo {
    width: 100%;
    aspect-ratio: 2/3;
    object-fit: cover;
    border-radius: 0.75rem;
    background: #141414;
    border: 2px solid rgba(229, 9, 20, 0.35);
    box-shadow: 0 12px 30px rgba(0,0,0,0.45);
}
.actor-photo.placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6b7280;
    font-size: 4rem;
}
.actor-info {
    flex: 1;
    min-width: 0;
}
.actor-name {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 0.5rem;
}
@media (min-width: 768px) {
    .actor-name {
        font-size: 3rem;
    }
}
.actor-role {
    color: #e50914;
    font-weight: 600;
    margin-bottom: 1rem;
}
.actor-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem 1.25rem;
    color: #9ca3af;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}
.actor-bio {
    color: #d1d5db;
    line-height: 1.7;
    font-size: 0.95rem;
    max-width: 52rem;
}
.actor-section {
    padding: 0 1.5rem 2rem;
}
@media (min-width: 768px) {
    .actor-section {
        padding: 0 3rem 2rem;
    }
}
.actor-section-title {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.actor-movie-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
}
@media (min-width: 640px) {
    .actor-movie-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
@media (min-width: 768px) {
    .actor-movie-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}
@media (min-width: 1024px) {
    .actor-movie-grid {
        grid-template-columns: repeat(6, 1fr);
    }
}
@media (min-width: 1280px) {
    .actor-movie-grid {
        grid-template-columns: repeat(7, 1fr);
    }
}
.actor-movie-card {
    position: relative;
    aspect-ratio: 2/3;
    border-radius: 0.5rem;
    overflow: hidden;
    background: #141414;
    border: 2px solid transparent;
    display: block;
    text-decoration: none;
    color: inherit;
    transition: transform 0.2s, border-color 0.2s;
}
.actor-movie-card:hover {
    transform: scale(1.04);
    border-color: rgba(229, 9, 20, 0.35);
    text-decoration: none;
    color: inherit;
}
.actor-movie-card img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.actor-movie-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.55);
    opacity: 0;
    transition: opacity 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.actor-movie-card:hover .actor-movie-overlay {
    opacity: 1;
}
.actor-movie-play {
    background: #e50914;
    border-radius: 50%;
    padding: 0.65rem;
    display: flex;
}
.actor-movie-info {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 0.5rem;
    background: linear-gradient(to top, rgba(0,0,0,0.95), transparent);
}
.actor-movie-info h3 {
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.actor-movie-info p {
    font-size: 0.65rem;
    color: #9ca3af;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<div class="actor-page animate-in fade-in">
    <div class="actor-hero">
        <div class="actor-photo-wrap">
            <?php if ($profileImage !== ''): ?>
            <img src="<?php echo htmlspecialchars($profileImage); ?>"
                 alt="<?php echo htmlspecialchars($actor['name']); ?>"
                 class="actor-photo"
                 onerror="this.outerHTML='<div class=\'actor-photo placeholder\'><i class=\'fas fa-user\'></i></div>'">
            <?php else: ?>
            <div class="actor-photo placeholder"><i class="fas fa-user"></i></div>
            <?php endif; ?>
        </div>
        <div class="actor-info">
            <h1 class="actor-name"><?php echo htmlspecialchars($actor['name']); ?></h1>
            <div class="actor-role"><?php echo htmlspecialchars($actor['known_for_department'] ?: 'Actor'); ?></div>
            <div class="actor-meta">
                <?php if (!empty($actor['birthday'])): ?>
                <span><i class="fas fa-birthday-cake mr-1"></i> <?php echo htmlspecialchars($actor['birthday']); ?></span>
                <?php endif; ?>
                <?php if (!empty($actor['place_of_birth'])): ?>
                <span><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($actor['place_of_birth']); ?></span>
                <?php endif; ?>
                <span><i class="fas fa-film mr-1"></i> <?php echo count($filmography); ?> movie<?php echo count($filmography) === 1 ? '' : 's'; ?></span>
            </div>
            <?php if (!empty($actor['biography'])): ?>
            <p class="actor-bio"><?php echo nl2br(htmlspecialchars($actor['biography'])); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($filmography)): ?>
    <div class="actor-section">
        <h2 class="actor-section-title">
            <i class="fas fa-film text-netflix-red"></i>
            Movies
        </h2>
        <div class="actor-movie-grid">
            <?php foreach ($filmography as $entry): ?>
                <?php
                $movie = $entry['movie'];
                $detailUrl = getMovieDetailUrl($movie, $conn);
                $poster = moviePosterUrl($movie);
                ?>
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="actor-movie-card">
                <img src="<?php echo htmlspecialchars($poster); ?>"
                     alt="<?php echo htmlspecialchars($movie['title']); ?>"
                     loading="lazy"
                     onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
                <div class="actor-movie-overlay">
                    <div class="actor-movie-play">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
                            <polygon points="5 3 19 12 5 21 5 3"></polygon>
                        </svg>
                    </div>
                </div>
                <div class="actor-movie-info">
                    <h3><?php echo htmlspecialchars($movie['title']); ?></h3>
                    <?php if (!empty($entry['character'])): ?>
                    <p>as <?php echo htmlspecialchars($entry['character']); ?></p>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
