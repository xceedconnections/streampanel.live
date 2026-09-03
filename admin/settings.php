<?php
/**
 * Admin Panel - Settings
 */
$page_title = "Settings";

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    // Define all checkbox settings
    $checkbox_settings = ['enable_movies', 'enable_tv_shows', 'enable_live_tv', 'maintenance_mode', 'registration_enabled', 'login_required_tv_channels', 'login_required_tv_shows', 'login_required_movies'];
    $raw_html_settings = [
        'custom_code_head',
        'custom_code_after_header',
        'custom_code_body',
        'custom_code_after_body',
        'custom_css',
    ];
    
    // Ensure all checkbox settings are in POST (with hidden inputs, they should be, but double-check)
    foreach ($checkbox_settings as $checkbox_key) {
        if (!isset($_POST[$checkbox_key])) {
            $_POST[$checkbox_key] = '0'; // Default to unchecked if not in POST
        }
    }
    
    // Process all settings (both checkboxes and text inputs)
    foreach ($_POST as $key => $value) {
        if ($key === 'submit') {
            continue; // Skip submit button
        }
        
        // Sanitize value
        if (in_array($key, $checkbox_settings, true)) {
            // For checkboxes, value will be '1' if checked, '0' if unchecked (from hidden input)
            $value = ($value == '1') ? '1' : '0';
        } elseif (in_array($key, $raw_html_settings, true)) {
            // Keep raw HTML/CSS for ads and custom scripts (admin only)
            $value = is_string($value) ? $value : '';
        } else {
            // For text inputs, sanitize
            $value = sanitize($value);
        }
        
        // Check if setting exists, if not insert it
        $check = $conn->prepare("SELECT id FROM settings WHERE setting_key = ?");
        $check->bind_param("s", $key);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        
        if ($exists) {
            $stmt = $conn->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
        }
    }
    
    $message = 'Settings updated successfully';
    $message_type = 'success';
    
    // Reload settings after save
    $settings = [];
    $result = $conn->query("SELECT * FROM settings");
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$settings = [];
$result = $conn->query("SELECT * FROM settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
<div class="mb-8">
    <h1 class="text-4xl font-bold mb-2">Settings</h1>
    <p class="text-gray-400">Manage site settings and configuration</p>
</div>

<?php if ($message): ?>
<div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-4">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="bg-gray-900 rounded-lg p-6">
    <h2 class="text-2xl font-bold mb-4">Site Settings</h2>
    <form method="POST" action="" id="settings-form">
        <div class="space-y-6">
            <!-- Site Information -->
            <div>
                <h3 class="text-lg font-semibold mb-3 text-gray-300">Site Information</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Site Name</label>
                        <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? 'StreamFlix'); ?>" 
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                               placeholder="Enter site name">
                        <p class="text-xs text-gray-400 mt-1">This name will be used throughout the portal (header, footer, copyrights, etc.)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Site URL</label>
                        <input type="text" name="site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>" 
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                               placeholder="http://localhost/stream or http://localhost">
                        <p class="text-xs text-gray-400 mt-1">Base URL of your portal (e.g., http://localhost/stream or http://localhost). Leave empty to auto-detect.</p>
                        <?php
                        // Show auto-detected URL
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
                        $host = $_SERVER['HTTP_HOST'];
                        $script = $_SERVER['SCRIPT_NAME'];
                        $path = dirname($script);
                        $autoDetected = $protocol . $host . rtrim($path, '/');
                        ?>
                        <p class="text-xs text-blue-400 mt-1">Auto-detected: <code><?php echo htmlspecialchars($autoDetected); ?></code></p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Site Description</label>
                        <textarea name="site_description" rows="3" 
                                  class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                                  placeholder="Enter site description"><?php echo htmlspecialchars($settings['site_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold mb-2">Max Devices Default</label>
                        <input type="number" name="max_devices_default" value="<?php echo htmlspecialchars($settings['max_devices_default'] ?? '2'); ?>" 
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                               min="1" max="10" required>
                        <p class="text-xs text-gray-400 mt-1">Default number of devices a user can login on simultaneously (applied on signup)</p>
                    </div>
                </div>
            </div>
            
            <!-- TMDB Integration -->
            <div class="border-t border-gray-800 pt-6">
                <h3 class="text-lg font-semibold mb-3 text-gray-300">TMDB Integration</h3>
                <div>
                    <label class="block text-sm font-semibold mb-2">TMDB API Key</label>
                    <input type="text" name="tmdb_api_key" value="<?php echo htmlspecialchars($settings['tmdb_api_key'] ?? ''); ?>" 
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white"
                           placeholder="Enter your TMDB API key (v3)">
                    <p class="text-xs text-gray-400 mt-1">Get a free API key from <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener" class="text-blue-400 underline">themoviedb.org/settings/api</a>. Used to auto-fetch movie metadata in Admin → Movies.</p>
                </div>
            </div>

            <!-- Section Management -->
            <div class="border-t border-gray-800 pt-6">
                <h3 class="text-lg font-semibold mb-3 text-gray-300">Section Management</h3>
                <p class="text-xs text-gray-400 mb-4">Enable or disable sections. Disabled sections will be hidden from menus and show maintenance message.</p>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <input type="hidden" name="enable_movies" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_movies" value="1" 
                                   <?php echo ($settings['enable_movies'] ?? '1') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Enable Movies Section</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="hidden" name="enable_tv_shows" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_tv_shows" value="1" 
                                   <?php echo ($settings['enable_tv_shows'] ?? '1') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Enable TV Shows Section</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="hidden" name="enable_live_tv" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="enable_live_tv" value="1" 
                                   <?php echo ($settings['enable_live_tv'] ?? '1') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Enable Live TV Section</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- System Settings -->
            <div class="border-t border-gray-800 pt-6">
                <h3 class="text-lg font-semibold mb-3 text-gray-300">System Settings</h3>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <input type="hidden" name="maintenance_mode" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="maintenance_mode" value="1" 
                                   <?php echo ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Maintenance Mode</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="hidden" name="registration_enabled" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="registration_enabled" value="1" 
                                   <?php echo ($settings['registration_enabled'] ?? '1') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Allow User Registration</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="hidden" name="login_required_tv_channels" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="login_required_tv_channels" value="1" 
                                   <?php echo ($settings['login_required_tv_channels'] ?? '0') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Login Required to View TV Channels</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="hidden" name="login_required_tv_shows" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="login_required_tv_shows" value="1" 
                                   <?php echo ($settings['login_required_tv_shows'] ?? '0') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Login Required to View TV Shows</span>
                        </label>
                    </div>
                    
                    <div class="flex items-center">
                        <input type="hidden" name="login_required_movies" value="0">
                        <label class="flex items-center">
                            <input type="checkbox" name="login_required_movies" value="1" 
                                   <?php echo ($settings['login_required_movies'] ?? '0') == '1' ? 'checked' : ''; ?>
                                   class="w-4 h-4 text-netflix-red bg-gray-800 border-gray-700 rounded mr-2">
                            <span>Login Required to View Movies</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Custom HTML / CSS (Ads & Tracking) -->
            <div class="border-t border-gray-800 pt-6">
                <h3 class="text-lg font-semibold mb-2 text-gray-300">Custom HTML &amp; CSS (Public Pages)</h3>
                <p class="text-xs text-gray-400 mb-4">These codes load on user-facing website pages only (not admin). Use for ads, analytics, or custom CSS.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Custom CSS</label>
                        <textarea name="custom_css" rows="5"
                                  class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                                  placeholder=".my-ad { margin: 10px 0; }"><?php echo htmlspecialchars($settings['custom_css'] ?? ''); ?></textarea>
                        <p class="text-xs text-gray-400 mt-1">Injected inside <code>&lt;style&gt;</code> in the page head.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">HTML in Header (&lt;head&gt;)</label>
                        <textarea name="custom_code_head" rows="5"
                                  class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                                  placeholder="<!-- scripts, meta, ad head tags -->"><?php echo htmlspecialchars($settings['custom_code_head'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">HTML in Body (after &lt;body&gt;)</label>
                        <textarea name="custom_code_body" rows="5"
                                  class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                                  placeholder="<!-- body start ads -->"><?php echo htmlspecialchars($settings['custom_code_body'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">HTML After Header (below top navbar)</label>
                        <textarea name="custom_code_after_header" rows="5"
                                  class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                                  placeholder="<!-- banner under navbar -->"><?php echo htmlspecialchars($settings['custom_code_after_header'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">HTML After Body (before &lt;/body&gt;)</label>
                        <textarea name="custom_code_after_body" rows="5"
                                  class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white font-mono text-sm"
                                  placeholder="<!-- footer scripts / ads -->"><?php echo htmlspecialchars($settings['custom_code_after_body'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6 pt-6 border-t border-gray-800">
            <button type="submit" name="submit" class="bg-netflix-red px-8 py-3 rounded hover:bg-red-700 font-semibold">
                <i class="fas fa-save mr-2"></i>Save All Settings
            </button>
        </div>
    </form>
</div>
