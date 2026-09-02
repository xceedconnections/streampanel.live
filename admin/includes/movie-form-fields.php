<?php
$movie_form_action = $movie_form_action ?? '?tab=movies';
?>
    <form method="POST" action="<?php echo htmlspecialchars($movie_form_action); ?>" id="movie-form" enctype="multipart/form-data" onsubmit="return validateMovieForm()">
        <?php if (!empty($edit_movie)): ?>
        <input type="hidden" name="id" value="<?php echo (int) $edit_movie['id']; ?>">
        <?php endif; ?>

        <!-- TMDB Auto Fetch -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg border border-gray-700">
            <h3 class="text-lg font-semibold mb-3"><i class="fas fa-film mr-2"></i>Fetch from TMDB</h3>
            <div class="flex flex-col md:flex-row gap-3">
                <input type="text" id="tmdb-input" placeholder="TMDB ID or URL (e.g. 550 or https://www.themoviedb.org/movie/550-fight-club)"
                       class="flex-1 bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white text-sm">
                <button type="button" id="tmdb-fetch-btn" class="bg-blue-600 hover:bg-blue-700 px-6 py-2 rounded font-semibold whitespace-nowrap">
                    <i class="fas fa-download mr-2"></i>Fetch Movie
                </button>
            </div>
            <p class="text-xs text-gray-400 mt-2">Auto-fills title, description, cast, poster, banner &amp; rating. Images are linked directly from TMDB.</p>
            <div id="tmdb-fetch-status" class="text-sm mt-2 hidden"></div>
        </div>
        <input type="hidden" name="cast_data" id="cast_data" value="<?php echo htmlspecialchars($edit_movie['cast_data'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="genres" id="genres" value="<?php echo htmlspecialchars($edit_movie['genres'] ?? '[]', ENT_QUOTES, 'UTF-8'); ?>">
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Title *</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($edit_movie['title'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white" required>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Category</label>
                <select name="category_id" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id']; ?>" <?php echo ($edit_movie['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Description</label>
            <textarea name="description" rows="3" 
                      class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"><?php echo htmlspecialchars($edit_movie['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Movie Logo / Thumbnail</label>
                <div class="space-y-2">
                    <input type="file" name="thumbnail_file" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                    <p class="text-xs text-gray-400">Upload movie logo image (JPG, PNG, GIF, WEBP). Stored in uploads/tv-show-logos.</p>
                    <?php if (!empty($edit_movie['thumbnail'])): ?>
                    <div class="mt-2">
                        <img src="<?php echo htmlspecialchars($edit_movie['thumbnail']); ?>" alt="Current Movie Logo"
                             class="max-w-24 max-h-24 object-contain bg-gray-800 rounded p-2"
                             onerror="this.style.display='none'">
                    </div>
                    <?php endif; ?>
                    <input type="text" name="thumbnail" value="<?php echo htmlspecialchars($edit_movie['thumbnail'] ?? ''); ?>" 
                           placeholder="Or enter logo/thumbnail URL"
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Poster URL (TMDB)</label>
                <input type="text" name="poster" id="poster" value="<?php echo htmlspecialchars($edit_movie['poster'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Movie poster image (direct TMDB link).</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Banner / Backdrop URL (TMDB)</label>
                <input type="text" name="backdrop" id="backdrop" value="<?php echo htmlspecialchars($edit_movie['backdrop'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Director</label>
                <input type="text" name="director" id="director" value="<?php echo htmlspecialchars($edit_movie['director'] ?? ''); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>

        <div class="mb-4">
            <label class="block text-sm font-semibold mb-2">Trailer URL (YouTube)</label>
            <input type="text" name="trailer_url" id="trailer_url" value="<?php echo htmlspecialchars($edit_movie['trailer_url'] ?? ''); ?>"
                   placeholder="https://www.youtube.com/watch?v=..."
                   class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            <p class="text-xs text-gray-400 mt-1">Used for the Watch Trailer button on the movie page. Auto-filled from TMDB when available.</p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Duration (minutes)</label>
                <input type="number" name="duration" value="<?php echo $edit_movie['duration'] ?? ''; ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Release Year</label>
                <input type="number" name="release_year" value="<?php echo $edit_movie['release_year'] ?? date('Y'); ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Rating</label>
                <input type="number" step="0.1" name="rating" value="<?php echo $edit_movie['rating'] ?? '0'; ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-sm font-semibold mb-2">Tags (shown on poster)</label>
                <input type="text" name="tags" id="tags" value="<?php echo htmlspecialchars(implode(', ', parseMovieTags($edit_movie['tags'] ?? ''))); ?>" 
                       placeholder="Hindi Dubbed, Dual Audio, CAM"
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                <p class="text-xs text-gray-400 mt-1">Comma-separated tags displayed on movie card.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">Quality Badge</label>
                <select name="quality_label" id="quality_label" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
                    <option value="">None</option>
                    <?php foreach (['HD', 'FHD', '4K', 'CAM', 'TS', 'Low Quality', 'High Quality'] as $ql): ?>
                    <option value="<?php echo $ql; ?>" <?php echo ($edit_movie['quality_label'] ?? '') === $ql ? 'selected' : ''; ?>><?php echo $ql; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold mb-2">TMDB ID</label>
                <input type="number" name="tmdb_id" id="tmdb_id" value="<?php echo $edit_movie['tmdb_id'] ?? ''; ?>" 
                       class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white">
            </div>
        </div>

        <?php $cast_preview = parseMovieCast($edit_movie['cast_data'] ?? '[]'); ?>
        <?php if (!empty($cast_preview)): ?>
        <div class="mb-4 p-3 bg-gray-800 rounded-lg">
            <label class="block text-sm font-semibold mb-2">Cast (from TMDB)</label>
            <div class="flex flex-wrap gap-2 text-xs text-gray-300">
                <?php foreach (array_slice($cast_preview, 0, 10) as $actor): ?>
                <span class="px-2 py-1 bg-gray-700 rounded"><?php echo htmlspecialchars($actor['name'] ?? ''); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Content Access Settings -->
        <div class="mb-6 p-4 bg-gray-800 rounded-lg">
            <h3 class="text-lg font-semibold mb-3">Content Access Settings</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <label class="flex items-center">
                    <input type="checkbox" name="is_free" value="1" <?php echo ($edit_movie['is_free'] ?? 1) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Free Content (Available to all logged-in users)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_premium" value="1" <?php echo ($edit_movie['is_premium'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Premium Content (Requires subscription)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="featured" value="1" <?php echo ($edit_movie['featured'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Featured (homepage poster row)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="show_in_slider" value="1" <?php echo ($edit_movie['show_in_slider'] ?? 0) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Show in Homepage Slider (big trending banner)</span>
                </label>
                <label class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" <?php echo ($edit_movie['is_active'] ?? 1) ? 'checked' : ''; ?> 
                           class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                    <span>Active</span>
                </label>
            </div>
        </div>
        
        <!-- Multiple Sources Management -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Streaming Sources</h3>
                <button type="button" onclick="addSource()" class="bg-green-600 hover:bg-green-700 px-4 py-2 rounded text-sm font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add Source
                </button>
            </div>
            <div id="source-tabs-nav" class="flex flex-wrap gap-2 mb-4 border-b border-gray-700 pb-2">
                <?php if ($edit_movie && !empty($edit_movie['sources'])): ?>
                    <?php foreach ($edit_movie['sources'] as $index => $source): ?>
                        <?php
                        $tab_label = trim($source['label'] ?? '') !== '' ? trim($source['label']) : ('Source ' . ($index + 1));
                        ?>
                        <button type="button" class="source-tab-btn px-4 py-2 rounded text-sm font-semibold <?php echo $index === 0 ? 'bg-netflix-red text-white' : 'bg-gray-700 text-gray-200 hover:bg-gray-600'; ?>" data-index="<?php echo (int) $index; ?>" onclick="switchSourceTab(<?php echo (int) $index; ?>)">
                            <?php echo htmlspecialchars($tab_label); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div id="sources-container">
                <?php if ($edit_movie && !empty($edit_movie['sources'])): ?>
                    <?php foreach ($edit_movie['sources'] as $index => $source): ?>
                        <?php
                        $is_active_panel = ($index === 0);
                        include __DIR__ . '/movie-source-panel.php';
                        ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">Each source opens in its own tab. Set priority to 0 for the default source.</p>
        </div>

        <!-- Download Links -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold">Download Links</h3>
                <button type="button" onclick="addDownloadLink()" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded text-sm font-semibold">
                    <i class="fas fa-plus mr-2"></i>Add Download Link
                </button>
            </div>
            <div id="download-links-container" class="space-y-3">
                <?php if ($edit_movie && !empty($edit_movie['download_links'])): ?>
                    <?php foreach ($edit_movie['download_links'] as $didx => $dlink): ?>
                    <div class="download-item bg-gray-800 rounded-lg p-4 border border-gray-700">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="font-semibold">Download #<?php echo $didx + 1; ?></h4>
                            <button type="button" onclick="removeDownloadLink(this)" class="text-red-400 hover:text-red-300"><i class="fas fa-trash"></i></button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1">Label</label>
                                <input type="text" name="download_links[<?php echo $didx; ?>][label]" value="<?php echo htmlspecialchars($dlink['label'] ?? 'Download'); ?>" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                                <input type="hidden" name="download_links[<?php echo $didx; ?>][id]" value="<?php echo htmlspecialchars($dlink['id'] ?? ''); ?>">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">Quality</label>
                                <input type="text" name="download_links[<?php echo $didx; ?>][quality]" value="<?php echo htmlspecialchars($dlink['quality'] ?? ''); ?>" placeholder="720p, 1080p" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold mb-1">Download URL *</label>
                                <input type="text" name="download_links[<?php echo $didx; ?>][url]" value="<?php echo htmlspecialchars($dlink['url'] ?? ''); ?>" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1">File Size</label>
                                <input type="text" name="download_links[<?php echo $didx; ?>][size]" value="<?php echo htmlspecialchars($dlink['size'] ?? ''); ?>" placeholder="1.2 GB" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white text-sm">
                            </div>
                            <div class="flex items-center">
                                <label class="flex items-center text-xs">
                                    <input type="checkbox" name="download_links[<?php echo $didx; ?>][isActive]" <?php echo ($dlink['isActive'] ?? true) ? 'checked' : ''; ?> class="w-3 h-3 mr-2">
                                    <span>Active</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <button type="submit" class="bg-netflix-red px-6 py-2 rounded hover:bg-red-700">
            <?php echo $edit_movie ? 'Update' : 'Add'; ?> Movie
        </button>
        <?php if ($edit_movie): ?>
        <a href="?tab=movies" class="bg-gray-700 px-6 py-2 rounded hover:bg-gray-600 ml-2">Cancel</a>
        <?php endif; ?>
    </form>