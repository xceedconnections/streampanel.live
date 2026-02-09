<?php
/**
 * Admin Panel - Search & Check Streams by Category
 * - Filter channels by category
 * - Start a background scan (with progress) that checks HLS/M3U8 and DASH sources
 * - Uses existing api/check-stream-links.php with category + type filters
 */

$page_title = "Search & Check Streams";

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();

// Load distinct categories for filter dropdown
$categories = [];
$result = $conn->query("SELECT DISTINCT category FROM live_tv_channels WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

$selected_category = isset($_GET['category']) ? trim($_GET['category']) : '';
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';

// Quick stats for the selected category (used for preview)
$category_stats = [
    'total_channels' => 0,
    'channels_with_sources' => 0,
    'hls_sources' => 0,
    'dash_sources' => 0,
];

if ($selected_category !== '') {
    $stmt = $conn->prepare("SELECT id, name, sources FROM live_tv_channels WHERE category = ? ORDER BY name ASC");
    $stmt->bind_param("s", $selected_category);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $category_stats['total_channels']++;
        $sources = json_decode($row['sources'] ?? '[]', true);
        if (is_array($sources) && count($sources) > 0) {
            $category_stats['channels_with_sources']++;
            foreach ($sources as $source) {
                $type = strtolower($source['type'] ?? '');
                if ($type === 'hls' || $type === 'm3u8') {
                    $category_stats['hls_sources']++;
                } elseif ($type === 'dash') {
                    $category_stats['dash_sources']++;
                }
            }
        }
    }
    $stmt->close();
}

// Load channel list for current filters (category + optional search by name)
$filtered_channels = [];
if ($selected_category !== '' || $search_query !== '') {
    $sql = "SELECT id, name, category, sources FROM live_tv_channels WHERE 1";
    $params = [];
    $types = '';

    if ($selected_category !== '') {
        $sql .= " AND category = ?";
        $params[] = $selected_category;
        $types .= 's';
    }

    if ($search_query !== '') {
        $sql .= " AND name LIKE ?";
        $params[] = '%' . $search_query . '%';
        $types .= 's';
    }

    $sql .= " ORDER BY name ASC";

    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $res = false;
        }
    } else {
        $res = $conn->query($sql);
    }

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $sources = json_decode($row['sources'] ?? '[]', true);
            $hls_count = 0;
            $dash_count = 0;
            if (is_array($sources)) {
                foreach ($sources as $source) {
                    $type = strtolower($source['type'] ?? '');
                    if ($type === 'hls' || $type === 'm3u8') {
                        $hls_count++;
                    } elseif ($type === 'dash') {
                        $dash_count++;
                    }
                }
            }
            $row['hls_count'] = $hls_count;
            $row['dash_count'] = $dash_count;
            $filtered_channels[] = $row;
        }
    }
}
?>

<div class="bg-gray-900 rounded-lg p-6 mb-8">
    <h1 class="text-3xl font-bold mb-6">
        <i class="fas fa-search mr-2 text-netflix-red"></i>Search & Check Streams
    </h1>

    <p class="text-gray-400 mb-6">
        Filter channels by category and run a focused scan of their HLS (M3U8) and DASH (MPD) stream links.
        Dead links will be removed from the channel sources; channels themselves are preserved.
    </p>

    <!-- Filter Form -->
    <form method="GET" class="bg-gray-800 rounded-lg p-4 mb-6 flex flex-col md:flex-row md:items-end gap-4">
        <input type="hidden" name="tab" value="search-check-streams">
        <div class="flex-1">
            <label class="block text-sm font-semibold mb-2 text-gray-300">Category</label>
            <select name="category" id="filterCategory" class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:border-netflix-red focus:outline-none">
                <option value="">All categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selected_category === $cat ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex-1">
            <label class="block text-sm font-semibold mb-2 text-gray-300">Search by Channel Name</label>
            <input
                type="text"
                name="q"
                id="searchChannelName"
                value="<?php echo htmlspecialchars($search_query); ?>"
                placeholder="Type to search channels in this category..."
                class="w-full bg-gray-700 border border-gray-600 rounded px-4 py-2 text-white focus:border-netflix-red focus:outline-none"
            />
        </div>
        <div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded transition-colors">
                <i class="fas fa-filter mr-2"></i>Apply Filter
            </button>
        </div>
    </form>

    <?php if ($selected_category !== ''): ?>
        <!-- Preview stats for this category -->
        <div class="bg-gray-800 rounded-lg p-4 mb-6">
            <h2 class="text-xl font-bold mb-3">Category Summary: <span class="text-blue-400"><?php echo htmlspecialchars($selected_category); ?></span></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm text-gray-300">
                <div>
                    <div class="text-2xl font-bold text-blue-400"><?php echo $category_stats['total_channels']; ?></div>
                    <div>Total channels</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-green-400"><?php echo $category_stats['channels_with_sources']; ?></div>
                    <div>With any sources</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-yellow-400"><?php echo $category_stats['hls_sources']; ?></div>
                    <div>HLS / M3U8 sources</div>
                </div>
                <div>
                    <div class="text-2xl font-bold text-purple-400"><?php echo $category_stats['dash_sources']; ?></div>
                    <div>DASH / MPD sources</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Channel List for current filters -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Filtered Channels</h2>

        <?php if (!empty($filtered_channels)): ?>
            <p class="text-gray-400 text-sm mb-4">
                Showing <span class="font-semibold text-blue-400"><?php echo count($filtered_channels); ?></span> channel(s)
                matching your filters. Use the checkboxes to choose which ones to include in the scan.
            </p>

            <div class="overflow-x-auto border border-gray-700 rounded">
                <table class="min-w-full text-sm text-gray-300">
                    <thead class="bg-gray-750">
                        <tr>
                            <th class="px-3 py-2 border-b border-gray-700 text-left w-10">
                                <input type="checkbox" id="selectAllChannels" checked>
                            </th>
                            <th class="px-3 py-2 border-b border-gray-700 text-left">Channel</th>
                            <th class="px-3 py-2 border-b border-gray-700 text-left hidden md:table-cell">Category</th>
                            <th class="px-3 py-2 border-b border-gray-700 text-center">HLS/M3U8</th>
                            <th class="px-3 py-2 border-b border-gray-700 text-center">DASH/MPD</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_channels as $ch): ?>
                            <tr class="hover:bg-gray-750">
                                <td class="px-3 py-2 border-b border-gray-800">
                                    <input
                                        type="checkbox"
                                        class="channel-select"
                                        value="<?php echo (int)$ch['id']; ?>"
                                        checked
                                    >
                                </td>
                                <td class="px-3 py-2 border-b border-gray-800">
                                    <div class="font-semibold"><?php echo htmlspecialchars($ch['name']); ?></div>
                                    <div class="text-xs text-gray-500">ID: <?php echo (int)$ch['id']; ?></div>
                                </td>
                                <td class="px-3 py-2 border-b border-gray-800 hidden md:table-cell">
                                    <?php echo htmlspecialchars($ch['category'] ?? ''); ?>
                                </td>
                                <td class="px-3 py-2 border-b border-gray-800 text-center">
                                    <?php echo (int)$ch['hls_count']; ?>
                                </td>
                                <td class="px-3 py-2 border-b border-gray-800 text-center">
                                    <?php echo (int)$ch['dash_count']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-400 text-sm">
                No channels match the current filters. Choose a category and/or search term above, then click
                <span class="font-semibold">Apply Filter</span>.
            </p>
        <?php endif; ?>
    </div>

    <!-- Scan Controls & Progress -->
    <div class="bg-gray-800 rounded-lg p-6 mb-6">
        <h2 class="text-xl font-bold mb-4">Check Streams in Selected / Filtered Channels</h2>

        <p class="text-gray-400 text-sm mb-4">
            This scan will check sources of type <code>hls</code>/<code>m3u8</code> and/or <code>dash</code> only for the channels
            that match your filters and are selected in the list above. If no channels are listed, it will fall back to scanning
            all channels (respecting the selected category and type filters).
        </p>

        <div class="mb-4 flex flex-wrap items-center gap-4">
            <div class="flex items-center space-x-2">
                <input type="checkbox" id="scanTypeHLS" class="form-checkbox h-4 w-4" checked>
                <label for="scanTypeHLS" class="text-sm text-gray-300">HLS / M3U8</label>
            </div>
            <div class="flex items-center space-x-2">
                <input type="checkbox" id="scanTypeDASH" class="form-checkbox h-4 w-4" checked>
                <label for="scanTypeDASH" class="text-sm text-gray-300">DASH / MPD</label>
            </div>
        </div>

        <div class="mb-4">
            <button id="startCategoryScanBtn" class="bg-blue-600 px-6 py-2 rounded hover:bg-blue-700 mr-2">
                <i class="fas fa-play mr-2"></i>Start Scan
            </button>
            <button id="pauseCategoryScanBtn" class="bg-yellow-600 px-6 py-2 rounded hover:bg-yellow-700 mr-2 hidden">
                <i class="fas fa-pause mr-2"></i>Pause
            </button>
            <button id="resumeCategoryScanBtn" class="bg-green-600 px-6 py-2 rounded hover:bg-green-700 mr-2 hidden">
                <i class="fas fa-play mr-2"></i>Resume
            </button>
            <button id="stopCategoryScanBtn" class="bg-red-600 px-6 py-2 rounded hover:bg-red-700 hidden">
                <i class="fas fa-stop mr-2"></i>Stop (Force)
            </button>
        </div>

        <div id="categoryScanProgress" class="hidden">
            <div class="mb-2 flex items-center justify-between text-sm text-gray-300">
                <span id="categoryScanStatus">Waiting to start...</span>
                <span><span id="categoryScanChecked">0</span> / <span id="categoryScanTotal">0</span> sources checked</span>
            </div>
            <div class="w-full bg-gray-700 rounded-full h-2.5 mb-2">
                <div id="categoryScanProgressBar" class="bg-netflix-red h-2.5 rounded-full" style="width: 0%;"></div>
            </div>
            <div class="text-xs text-gray-400 flex flex-wrap gap-4">
                <span>Alive: <span id="categoryScanAlive">0</span></span>
                <span>Dead (removed): <span id="categoryScanDead">0</span></span>
                <span>Remaining: <span id="categoryScanRemaining">0</span></span>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div id="categoryScanResults" class="bg-gray-800 rounded-lg p-6 hidden">
        <h2 class="text-xl font-bold mb-4">Scan Results</h2>
        <div id="categoryScanResultsList" class="space-y-2 text-sm text-gray-300 max-h-96 overflow-y-auto border border-gray-700 rounded p-3"></div>
    </div>
</div>

<script>
    (function() {
        let isScanning = false;
        let scanInterval = null;

        const startBtn = document.getElementById('startCategoryScanBtn');
        const pauseBtn = document.getElementById('pauseCategoryScanBtn');
        const resumeBtn = document.getElementById('resumeCategoryScanBtn');
        const stopBtn = document.getElementById('stopCategoryScanBtn');

        const progressContainer = document.getElementById('categoryScanProgress');
        const statusText = document.getElementById('categoryScanStatus');
        const checkedEl = document.getElementById('categoryScanChecked');
        const totalEl = document.getElementById('categoryScanTotal');
        const aliveEl = document.getElementById('categoryScanAlive');
        const deadEl = document.getElementById('categoryScanDead');
        const remainingEl = document.getElementById('categoryScanRemaining');
        const progressBar = document.getElementById('categoryScanProgressBar');

        const resultsContainer = document.getElementById('categoryScanResults');
        const resultsList = document.getElementById('categoryScanResultsList');

        function appendResultRow(item) {
            const div = document.createElement('div');
            div.className = 'border border-gray-700 rounded px-3 py-2 flex flex-col md:flex-row md:items-center md:justify-between gap-2';
            const statusColor = item.status === 'alive' ? 'text-green-400' : 'text-red-400';
            div.innerHTML = `
                <div>
                    <div class="font-semibold">${item.channel}</div>
                    <div class="text-xs text-gray-400 break-all">${item.url}</div>
                </div>
                <div class="text-right text-xs">
                    <div class="${statusColor} font-semibold uppercase">${item.status}</div>
                    ${item.error ? `<div class="text-gray-400 mt-1">${item.error}</div>` : ''}
                </div>
            `;
            resultsList.appendChild(div);
        }

        function pollScan() {
            if (!isScanning) return;

            fetch('api/check-stream-links.php?action=check&batch=20')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        if (data.paused) {
                            statusText.textContent = 'Scan paused';
                        } else {
                            statusText.textContent = data.message || 'Scan error';
                        }
                        return;
                    }

                    // Update progress UI
                    checkedEl.textContent = data.checked;
                    totalEl.textContent = data.total;
                    aliveEl.textContent = data.alive;
                    deadEl.textContent = data.dead;
                    remainingEl.textContent = data.remaining;
                    progressBar.style.width = (data.progress || 0) + '%';
                    statusText.textContent = data.completed ? 'Scan completed' : 'Scanning...';

                    if (Array.isArray(data.dead_links)) {
                        data.dead_links.forEach(link => {
                            appendResultRow({
                                channel: link.channel_name,
                                url: link.url,
                                status: 'dead',
                                error: link.error || ''
                            });
                        });
                        resultsContainer.classList.remove('hidden');
                    }

                    if (data.completed) {
                        isScanning = false;
                        clearInterval(scanInterval);
                        pauseBtn.classList.add('hidden');
                        resumeBtn.classList.add('hidden');
                        stopBtn.classList.add('hidden');
                        startBtn.classList.remove('hidden');
                    }
                })
                .catch(err => {
                    console.error('Error checking scan status:', err);
                });
        }

        function startScanForCategory() {
            if (isScanning) return;

            const categorySelect = document.getElementById('filterCategory');
            const category = categorySelect ? categorySelect.value : '';
            const hlsCheckbox = document.getElementById('scanTypeHLS');
            const dashCheckbox = document.getElementById('scanTypeDASH');

            const selectedTypes = [];
            if (hlsCheckbox && hlsCheckbox.checked) selectedTypes.push('hls');
            if (dashCheckbox && dashCheckbox.checked) selectedTypes.push('dash');

            if (selectedTypes.length === 0) {
                alert('Please select at least one stream type (HLS/M3U8 or DASH) to scan.');
                return;
            }

            // Collect selected channel IDs from the list (if present)
            const channelCheckboxes = document.querySelectorAll('.channel-select');
            const selectedChannelIds = [];
            channelCheckboxes.forEach(cb => {
                if (cb.checked) {
                    const val = parseInt(cb.value, 10);
                    if (!Number.isNaN(val)) {
                        selectedChannelIds.push(val);
                    }
                }
            });

            if (channelCheckboxes.length > 0 && selectedChannelIds.length === 0) {
                alert('Please select at least one channel from the list to scan.');
                return;
            }

            const payload = {
                action: 'start',
                types: selectedTypes.join(','),
                category: category || '',
                channel_ids: selectedChannelIds
            };

            // Reset UI
            resultsList.innerHTML = '';
            resultsContainer.classList.add('hidden');
            checkedEl.textContent = '0';
            aliveEl.textContent = '0';
            deadEl.textContent = '0';
            remainingEl.textContent = '0';
            totalEl.textContent = '0';
            progressBar.style.width = '0%';
            statusText.textContent = 'Starting scan...';
            progressContainer.classList.remove('hidden');

            fetch('api/check-stream-links.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        alert('Failed to start scan: ' + (data.message || 'Unknown error'));
                        return;
                    }

                    totalEl.textContent = data.total || 0;
                    statusText.textContent = 'Scan started';
                    isScanning = true;

                    startBtn.classList.add('hidden');
                    pauseBtn.classList.remove('hidden');
                    stopBtn.classList.remove('hidden');

                    // Begin polling for progress
                    pollScan();
                    scanInterval = setInterval(pollScan, 3000);
                })
                .catch(err => {
                    console.error('Error starting scan:', err);
                    alert('Error starting scan. Check console for details.');
                });
        }

        function pauseScan() {
            fetch('api/check-stream-links.php?action=pause')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        statusText.textContent = 'Scan paused';
                        pauseBtn.classList.add('hidden');
                        resumeBtn.classList.remove('hidden');
                    } else {
                        alert('Failed to pause scan');
                    }
                });
        }

        function resumeScan() {
            fetch('api/check-stream-links.php?action=resume')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        statusText.textContent = 'Scanning...';
                        pauseBtn.classList.remove('hidden');
                        resumeBtn.classList.add('hidden');
                        isScanning = true;
                        pollScan();
                        if (!scanInterval) {
                            scanInterval = setInterval(pollScan, 3000);
                        }
                    } else {
                        alert('Failed to resume scan');
                    }
                });
        }

        function stopScan() {
            if (!confirm('Are you sure you want to stop the scan? This will immediately stop and clear the current scan progress.')) {
                return;
            }

            fetch('api/check-stream-links.php?action=stop', { method: 'POST' })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        isScanning = false;
                        clearInterval(scanInterval);
                        scanInterval = null;
                        statusText.textContent = 'Scan stopped by user';
                        pauseBtn.classList.add('hidden');
                        resumeBtn.classList.add('hidden');
                        stopBtn.classList.add('hidden');
                        startBtn.classList.remove('hidden');
                    } else {
                        alert('Failed to stop scan');
                    }
                })
                .catch(err => {
                    console.error('Error stopping scan:', err);
                });
        }

        // "Select all" checkbox for channel list
        const selectAllCheckbox = document.getElementById('selectAllChannels');
        if (selectAllCheckbox) {
            const channelCheckboxes = document.querySelectorAll('.channel-select');
            selectAllCheckbox.addEventListener('change', function() {
                const checked = this.checked;
                channelCheckboxes.forEach(cb => {
                    cb.checked = checked;
                });
            });
        }

        if (startBtn) startBtn.addEventListener('click', startScanForCategory);
        if (pauseBtn) pauseBtn.addEventListener('click', pauseScan);
        if (resumeBtn) resumeBtn.addEventListener('click', resumeScan);
        if (stopBtn) stopBtn.addEventListener('click', stopScan);
    })();
</script>

