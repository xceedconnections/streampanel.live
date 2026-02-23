<?php
/**
 * Test page to debug TV Show Detail page
 * DELETE AFTER TESTING
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/includes/functions.php';

$conn = getDBConnection();
$test_id = isset($_GET['id']) ? intval($_GET['id']) : 3;

echo "<h2>TV Show Detail Debug Test</h2>";
echo "<style>body { font-family: Arial; padding: 20px; background: #1a1a1a; color: #fff; } .success { color: #4ade80; } .error { color: #f87171; } .info { color: #60a5fa; } pre { background: #2a2a2a; padding: 10px; border-radius: 5px; } table { border-collapse: collapse; width: 100%; margin: 20px 0; } th, td { border: 1px solid #444; padding: 10px; text-align: left; }</style>";

echo "<h3>Testing TV Show ID: $test_id</h3>";

// Test 1: Check if show exists with simple query
echo "<h3>Test 1: Simple Query</h3>";
$simple_query = $conn->query("SELECT id, title, is_active FROM tv_shows WHERE id = $test_id");
if ($simple_query && $simple_query->num_rows > 0) {
    $row = $simple_query->fetch_assoc();
    echo "<p class='success'>✓ Show found: " . htmlspecialchars($row['title']) . " (Active: " . ($row['is_active'] ? 'Yes' : 'No') . ")</p>";
} else {
    echo "<p class='error'>✗ Show ID $test_id NOT FOUND</p>";
}

// Test 2: Check with prepared statement (same as tv-show-detail.php)
echo "<h3>Test 2: Prepared Statement Query (Same as tv-show-detail.php)</h3>";
$stmt = $conn->prepare("SELECT t.*, c.name as category_name FROM tv_shows t LEFT JOIN categories c ON t.category_id = c.id WHERE t.id = ?");
if ($stmt) {
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "<p class='success'>✓ Show found with prepared statement:</p>";
        echo "<pre>" . htmlspecialchars(print_r($row, true)) . "</pre>";
    } else {
        echo "<p class='error'>✗ Show ID $test_id NOT FOUND with prepared statement</p>";
    }
    $stmt->close();
} else {
    echo "<p class='error'>✗ Failed to prepare statement: " . $conn->error . "</p>";
}

// Test 3: List all TV shows
echo "<h3>Test 3: All TV Shows in Database</h3>";
$all_shows = $conn->query("SELECT id, title, is_active FROM tv_shows ORDER BY id LIMIT 20");
if ($all_shows && $all_shows->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Title</th><th>Active</th></tr>";
    while ($row = $all_shows->fetch_assoc()) {
        $highlight = $row['id'] == $test_id ? 'background: #4ade80;' : '';
        echo "<tr style='$highlight'>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['title']) . "</td>";
        echo "<td>" . ($row['is_active'] ? 'Yes' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>No TV shows found in database!</p>";
}

// Test 4: Test the actual URL
echo "<h3>Test 4: Test Actual URL</h3>";
echo "<p><a href='tv-show-detail.php?id=$test_id' style='color: #60a5fa;'>Click here to test: tv-show-detail.php?id=$test_id</a></p>";

echo "<p style='color: #fbbf24; margin-top: 30px;'><strong>⚠️ DELETE THIS FILE AFTER TESTING</strong></p>";
?>
