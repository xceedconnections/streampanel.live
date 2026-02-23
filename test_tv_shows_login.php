<?php
/**
 * Test page to debug TV Shows login requirement
 * DELETE AFTER TESTING
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();

echo "<h2>TV Shows Login Requirement Test</h2>";
echo "<style>body { font-family: Arial; padding: 20px; background: #1a1a1a; color: #fff; } .success { color: #4ade80; } .error { color: #f87171; } .info { color: #60a5fa; } pre { background: #2a2a2a; padding: 10px; border-radius: 5px; }</style>";

// Test 1: Direct query
echo "<h3>Test 1: Direct Database Query</h3>";
$direct_query = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'login_required_tv_shows' LIMIT 1");
if ($direct_query && $direct_query->num_rows > 0) {
    $row = $direct_query->fetch_assoc();
    $value1 = $row['setting_value'] ?? 'NOT_FOUND';
    echo "<p class='info'>Raw value from DB: <strong>" . htmlspecialchars(var_export($value1, true)) . "</strong></p>";
    echo "<p class='info'>Type: " . gettype($value1) . "</p>";
} else {
    echo "<p class='error'>No result found in database!</p>";
    $value1 = 'NOT_FOUND';
}

// Test 2: Prepared statement
echo "<h3>Test 2: Prepared Statement Query</h3>";
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
if ($stmt) {
    $key = 'login_required_tv_shows';
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $value2 = $row['setting_value'] ?? 'NOT_FOUND';
        echo "<p class='info'>Raw value from prepared: <strong>" . htmlspecialchars(var_export($value2, true)) . "</strong></p>";
        echo "<p class='info'>Type: " . gettype($value2) . "</p>";
    } else {
        echo "<p class='error'>No result found!</p>";
        $value2 = 'NOT_FOUND';
    }
    $stmt->close();
} else {
    echo "<p class='error'>Failed to prepare statement!</p>";
    $value2 = 'NOT_FOUND';
}

// Test 3: getSetting function
echo "<h3>Test 3: getSetting() Function</h3>";
$value3 = getSetting($conn, 'login_required_tv_shows', 'DEFAULT_NOT_FOUND');
echo "<p class='info'>Value from getSetting(): <strong>" . htmlspecialchars(var_export($value3, true)) . "</strong></p>";
echo "<p class='info'>Type: " . gettype($value3) . "</p>";

// Test 4: Normalization and comparison
echo "<h3>Test 4: Normalization and Comparison</h3>";
$test_values = [$value1, $value2, $value3, '0', 0, '1', 1, '', null, false];

foreach ($test_values as $test_val) {
    $normalized = trim((string)$test_val);
    $is_one = ($normalized === '1');
    $will_require = ($is_one === true);
    
    echo "<p>";
    echo "Value: " . htmlspecialchars(var_export($test_val, true)) . " ";
    echo "→ Normalized: '" . htmlspecialchars($normalized) . "' ";
    echo "→ === '1'? " . ($is_one ? '<span class="error">YES</span>' : '<span class="success">NO</span>') . " ";
    echo "→ Will require login? " . ($will_require ? '<span class="error">YES</span>' : '<span class="success">NO</span>');
    echo "</p>";
}

// Test 5: Actual code logic
echo "<h3>Test 5: Actual Code Logic (from tv-shows.php)</h3>";
$login_required_tv_shows = '0';
try {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    if ($stmt) {
        $key = 'login_required_tv_shows';
        $stmt->bind_param("s", $key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $login_required_tv_shows = $row['setting_value'] ?? '0';
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $login_required_tv_shows = '0';
}

$login_required_tv_shows = trim((string)$login_required_tv_shows);
$login_required = ($login_required_tv_shows === '1');

echo "<pre>";
echo "Step 1: Raw value from DB: " . var_export($login_required_tv_shows, true) . "\n";
echo "Step 2: After trim((string)): '" . $login_required_tv_shows . "'\n";
echo "Step 3: Comparison (=== '1'): " . ($login_required ? 'true' : 'false') . "\n";
echo "Step 4: Will call requireLogin()? " . ($login_required === true ? '<span class="error">YES - THIS IS THE PROBLEM!</span>' : '<span class="success">NO - CORRECT</span>') . "\n";
echo "</pre>";

if ($login_required === true) {
    echo "<p class='error'><strong>PROBLEM FOUND: Code would call requireLogin() even though setting is '0'!</strong></p>";
} else {
    echo "<p class='success'><strong>Code logic is correct - will NOT call requireLogin()</strong></p>";
}

echo "<p><a href='tv-shows.php' style='color: #60a5fa;'>Try accessing TV Shows page</a></p>";
echo "<p style='color: #fbbf24; margin-top: 30px;'><strong>⚠️ DELETE THIS FILE AFTER TESTING</strong></p>";
?>
