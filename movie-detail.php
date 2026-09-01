<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';
require_once __DIR__ . '/includes/movie_helpers.php';

$page_title = "Movie Details";
$conn = getDBConnection();
ensureMoviesSchema($conn);

if (!isSectionEnabled($conn, 'movies')) {
    include 'includes/header.php';
    echo '<div class="min-h-screen flex items-center justify-center py-20"><div class="text-center"><h1 class="text-4xl font-bold text-red-500 mb-4">Section is Under Maintenance</h1><a href="' . url() . '" class="bg-netflix-red px-6 py-3 rounded">Go Home</a></div></div>';
    include 'includes/footer.php';
    exit;
}

$movie_id = 0;
$movie_slug = trim($_GET['slug'] ?? '');

if (!empty($_GET['id'])) {
    $movie_id = (int) $_GET['id'];
} elseif ($movie_slug === '') {
    header('Location: ' . url('movies'));
    exit;
}

if ($movie_id > 0) {
    $movie = getMovieById($conn, $movie_id);
    if ($movie && movieHasSlug($movie) && isset($_GET['id'])) {
        header('Location: ' . getMovieDetailUrl($movie, $conn));
        exit;
    }
} else {
    $stmt = $conn->prepare("SELECT m.*, c.name as category_name FROM movies m LEFT JOIN categories c ON m.category_id = c.id WHERE m.slug = ?");
    $stmt->bind_param('s', $movie_slug);
    $stmt->execute();
    $movie = $stmt->get_result()->fetch_assoc();
}

if (!$movie || (isset($movie['is_active']) && !$movie['is_active'])) {
    header('Location: ' . url('movies'));
    exit;
}

$movieAccess = getMovieAccess($conn, $movie);
applyMovieSeoMeta($conn, $movie, 'detail');
$page_title = $GLOBALS['page_title'] ?? $movie['title'];
$meta_description = $GLOBALS['meta_description'] ?? '';
$meta_keywords = $GLOBALS['meta_keywords'] ?? '';
$canonical_url = $GLOBALS['canonical_url'] ?? '';
$og_image = $GLOBALS['og_image'] ?? '';
$og_type = $GLOBALS['og_type'] ?? 'video.movie';

$cast = parseMovieCast($movie['cast_data'] ?? '[]');
$tags = parseMovieTags($movie['tags'] ?? '');
$genres = parseMovieGenres($movie['genres'] ?? '[]');
$watchSources = getActiveWatchSources($movie);
$downloadLinks = getActiveDownloadLinks($movie);
$poster = moviePosterUrl($movie);
$backdrop = movieBackdropUrl($movie);
$trailerUrl = getMovieTrailerUrl($movie, $conn);
$trailerEmbed = $trailerUrl !== '' ? youtubeEmbedUrl($trailerUrl) : '';
$playMovieUrl = resolveMovieWatchHref($movie, $movieAccess, 0, $conn);
$sourceCount = count($watchSources);

include 'includes/header.php';
?>

<style>
.movie-detail-hero {
    position: relative;
    min-height: 320px;
    background: #111;
    overflow: hidden;
}
.movie-detail-hero-bg {
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center top;
    filter: blur(8px);
    transform: scale(1.05);
    opacity: 0.35;
}
.movie-detail-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to bottom, rgba(0,0,0,0.2), #000 85%);
}
.movie-detail-inner {
    position: relative;
    z-index: 2;
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1.5rem 3rem;
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 1.5rem;
}
@media (min-width: 768px) {
    .movie-detail-inner {
        grid-template-columns: 220px 1fr;
        gap: 2rem;
        padding: 3rem;
    }
}
.movie-detail-poster-wrap {
    position: relative;
}
.movie-detail-poster-wrap .movie-card-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    right: 0.5rem;
    z-index: 3;
}
.movie-card-badges {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    right: 0.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.25rem;
    z-index: 10;
    pointer-events: none;
}
.movie-badge {
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.4rem;
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
.movie-detail-poster {
    width: 100%;
    border-radius: 0.5rem;
    box-shadow: 0 20px 40px rgba(0,0,0,0.6);
    aspect-ratio: 2/3;
    object-fit: cover;
}
.movie-detail-title {
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 0.75rem;
}
@media (min-width: 768px) {
    .movie-detail-title { font-size: 2.5rem; }
}
.movie-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    color: #9ca3af;
    font-size: 0.875rem;
    margin-bottom: 1rem;
}
.movie-tag-pill {
    display: inline-block;
    background: rgba(229,9,20,0.85);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.5rem;
    border-radius: 0.2rem;
    text-transform: uppercase;
}
.movie-genre-pill {
    display: inline-block;
    background: rgba(255,255,255,0.1);
    color: #d1d5db;
    font-size: 0.75rem;
    padding: 0.25rem 0.6rem;
    border-radius: 999px;
    margin-right: 0.35rem;
    margin-bottom: 0.35rem;
}
.movie-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 1.5rem 3rem;
}
@media (min-width: 768px) {
    .movie-section { padding: 0 3rem 3rem; }
}
.movie-section h2 {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1rem;
    border-left: 3px solid #e50914;
    padding-left: 0.75rem;
}
.watch-btn, .download-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.65rem 1.25rem;
    border-radius: 0.375rem;
    font-weight: 600;
    font-size: 0.875rem;
    text-decoration: none;
    margin: 0.25rem 0.5rem 0.25rem 0;
    transition: opacity 0.2s;
}
.watch-btn {
    background: #e50914;
    color: #fff;
}
.download-btn {
    background: #374151;
    color: #fff;
}
.watch-btn:hover, .download-btn:hover { opacity: 0.85; }
.cast-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}
@media (min-width: 640px) {
    .cast-grid { grid-template-columns: repeat(4, 1fr); }
}
@media (min-width: 1024px) {
    .cast-grid { grid-template-columns: repeat(6, 1fr); }
}
.cast-card {
    text-align: center;
}
.cast-card img {
    width: 100%;
    aspect-ratio: 2/3;
    object-fit: cover;
    border-radius: 0.375rem;
    background: #1f2937;
    margin-bottom: 0.5rem;
}
.cast-card .name {
    font-size: 0.8rem;
    font-weight: 600;
}
.cast-card .role {
    font-size: 0.7rem;
    color: #9ca3af;
}
.movie-cta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1rem;
}
.btn-trailer, .btn-play-movie {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    text-decoration: none;
    transition: opacity 0.2s, transform 0.2s;
}
.btn-trailer {
    background: rgba(255,255,255,0.12);
    color: #fff;
    border: 1px solid rgba(255,255,255,0.2);
}
.btn-play-movie {
    background: #e50914;
    color: #fff;
}
.btn-trailer:hover, .btn-play-movie:hover { opacity: 0.9; transform: scale(1.02); }
.movie-source-count {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: #9ca3af;
    margin-bottom: 1rem;
}
.movie-quality-section {
    margin: 1.25rem 0;
}
.movie-quality-section h3 {
    font-size: 0.875rem;
    font-weight: 600;
    color: #d1d5db;
    margin-bottom: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.quality-links-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.quality-link-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.5rem 1rem;
    background: rgba(229, 9, 20, 0.9);
    color: #fff;
    font-size: 0.8rem;
    font-weight: 700;
    text-decoration: none;
    border-radius: 0.25rem;
    text-transform: uppercase;
    transition: background 0.2s, transform 0.2s;
}
.quality-link-btn:hover {
    background: #e50914;
    transform: scale(1.03);
    color: #fff;
}
.trailer-modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(0,0,0,0.85);
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.trailer-modal.active { display: flex; }
.trailer-modal-box {
    position: relative;
    width: 100%;
    max-width: 960px;
    aspect-ratio: 16/9;
    background: #000;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 25px 50px rgba(0,0,0,0.5);
}
.trailer-modal-box iframe {
    width: 100%;
    height: 100%;
    border: none;
}
.trailer-modal-close {
    position: absolute;
    top: -2.5rem;
    right: 0;
    background: transparent;
    border: none;
    color: #fff;
    font-size: 2rem;
    cursor: pointer;
    line-height: 1;
}
</style>

<div class="bg-black min-h-screen text-white">
    <div class="movie-detail-hero">
        <div class="movie-detail-hero-bg" style="background-image:url('%3C?php echo htmlspecialchars($backdrop')"></div>
        <div class="movie-detail-hero-overlay"></div>
        <div class="movie-detail-inner">
            <div class="movie-detail-poster-wrap">
                <?php renderMoviePosterBadges($movie); ?>
                <img src="<?php echo htmlspecialchars($poster); ?>" alt="<?php echo htmlspecialchars($movie['title']); ?>" class="movie-detail-poster" onerror="this.src='<?php echo FALLBACK_POSTER; ?>'">
            </div>
            <div>
                <h1 class="movie-detail-title"><?php echo htmlspecialchars($movie['title']); ?></h1>
                <div class="movie-meta-row">
                    <?php foreach ($tags as $tag): ?>
                    <span class="movie-tag-pill"><?php echo htmlspecialchars($tag); ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($movie['release_year'])): ?>
                    <span><i class="fas fa-calendar mr-1"></i><?php echo (int) $movie['release_year']; ?></span>
                    <?php endif; ?>
                    <?php $dur = formatMovieDuration((int) ($movie['duration'] ?? 0)); if ($dur): ?>
                    <span><i class="fas fa-clock mr-1"></i><?php echo $dur; ?></span>
                    <?php endif; ?>
                    <?php if (!empty($movie['rating'])): ?>
                    <span><i class="fas fa-star text-yellow-400 mr-1"></i><?php echo number_format((float) $movie['rating'], 1); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($movie['category_name'])): ?>
                    <span><i class="fas fa-folder mr-1"></i><?php echo htmlspecialchars($movie['category_name']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($genres)): ?>
                <div class="mb-3">
                    <?php foreach ($genres as $genre): ?>
                    <span class="movie-genre-pill"><?php echo htmlspecialchars($genre); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($movie['director'])): ?>
                <p class="text-sm text-gray-400 mb-3"><strong class="text-gray-300">Director:</strong> <?php echo htmlspecialchars($movie['director']); ?></p>
                <?php endif; ?>
                <?php if (!empty($movie['description'])): ?>
                <p class="text-gray-300 text-sm leading-relaxed mb-4"><?php echo nl2br(htmlspecialchars($movie['description'])); ?></p>
                <?php endif; ?>

                <?php if ($sourceCount > 0 || !empty($movie['video_url'])): ?>
                <p class="movie-source-count">
                    <i class="fas fa-film"></i>
                    <?php echo max($sourceCount, !empty($movie['video_url']) ? 1 : 0); ?> watch source<?php echo $sourceCount === 1 ? '' : 's'; ?> available
                    <?php if (!empty($downloadLinks)): ?>
                    &nbsp;·&nbsp; <?php echo count($downloadLinks); ?> download<?php echo count($downloadLinks) === 1 ? '' : 's'; ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>

                <?php if (!empty($watchSources)): ?>
                <div class="movie-quality-section">
                    <h3>Watch FROM</h3>
                    <div class="quality-links-row">
                        <?php foreach ($watchSources as $idx => $source): ?>
                        <?php
                            $qualityLabel = getMovieSourceDisplayLabel($source, $idx);
                            $qualityWatchUrl = resolveMovieWatchHref($movie, $movieAccess, $idx, $conn);
                        ?>
                        <a href="<?php echo htmlspecialchars($qualityWatchUrl); ?>" class="quality-link-btn">
                            <i class="fas fa-play"></i> <?php echo htmlspecialchars($qualityLabel); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="movie-cta-row">
                    <?php if ($trailerEmbed !== ''): ?>
                    <button type="button" class="btn-trailer" onclick="openTrailerModal()">
                        <i class="fas fa-play-circle"></i> Watch Trailer
                    </button>
                    <?php endif; ?>

                    <?php if ($sourceCount > 0 || !empty($movie['video_url'])): ?>
                    <a href="<?php echo htmlspecialchars($playMovieUrl); ?>" class="btn-play-movie">
                        <i class="fas fa-play"></i>
                        <?php
                        if (!$movieAccess['allowed'] && $movieAccess['reason'] === 'login') {
                            echo 'Login to Play Movie';
                        } elseif (!$movieAccess['allowed'] && $movieAccess['reason'] === 'premium') {
                            echo 'Premium Required';
                        } else {
                            echo 'Play Movie';
                        }
                        ?>
                    </a>
                    <?php endif; ?>
                </div>

                <p class="text-xs text-gray-400 max-w-lg">
                    Play Movie opens the full player with all streaming sources and download links.
                </p>
            </div>
        </div>
    </div>

    <?php if (!empty($cast)): ?>
    <div class="movie-section">
        <h2>Cast</h2>
        <div class="cast-grid">
            <?php foreach ($cast as $actor): ?>
            <div class="cast-card">
                <?php
                $profile = !empty($actor['profile_path'])
                    ? tmdbImageUrl($actor['profile_path'], 'w185')
                    : 'https://via.placeholder.com/185x278?text=No+Photo';
                ?>
                <img src="<?php echo htmlspecialchars($profile); ?>" alt="<?php echo htmlspecialchars($actor['name'] ?? ''); ?>" loading="lazy" onerror="this.src='https://via.placeholder.com/185x278?text=No+Photo'">
                <div class="name"><?php echo htmlspecialchars($actor['name'] ?? ''); ?></div>
                <?php if (!empty($actor['character'])): ?>
                <div class="role"><?php echo htmlspecialchars($actor['character']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($trailerEmbed !== ''): ?>
<div id="trailerModal" class="trailer-modal" onclick="if(event.target===this)closeTrailerModal()">
    <div class="trailer-modal-box">
        <button type="button" class="trailer-modal-close" onclick="closeTrailerModal()" aria-label="Close">&times;</button>
        <iframe id="trailerIframe" src="" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
    </div>
</div>
<script>
function openTrailerModal() {
    const modal = document.getElementById('trailerModal');
    const iframe = document.getElementById('trailerIframe');
    if (!modal || !iframe) return;
    iframe.src = <?php echo json_encode($trailerEmbed); ?>;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeTrailerModal() {
    const modal = document.getElementById('trailerModal');
    const iframe = document.getElementById('trailerIframe');
    if (!modal || !iframe) return;
    modal.classList.remove('active');
    iframe.src = '';
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeTrailerModal();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>