<?php
/**
 * One-off: find and fix HTML entities in live_tv_channels names/descriptions.
 */
require_once __DIR__ . '/../config/database.php';

$conn = getDBConnection();
$rows = $conn->query('SELECT id, name, description, category FROM live_tv_channels')->fetch_all(MYSQLI_ASSOC);

$fixed = 0;
foreach ($rows as $row) {
    $updates = [];
    foreach (['name', 'description', 'category'] as $col) {
        $val = $row[$col] ?? '';
        if ($val === '' || $val === null) {
            continue;
        }
        $decoded = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Repeat decode for double-encoded values like &amp;amp;
        $prev = '';
        while ($decoded !== $prev) {
            $prev = $decoded;
            $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($decoded !== $val) {
            $updates[$col] = $decoded;
        }
    }
    if (empty($updates)) {
        continue;
    }
    $sets = [];
    $types = '';
    $params = [];
    foreach ($updates as $col => $val) {
        $sets[] = "$col = ?";
        $types .= 's';
        $params[] = $val;
    }
    $types .= 'i';
    $params[] = (int) $row['id'];
    $sql = 'UPDATE live_tv_channels SET ' . implode(', ', $sets) . ' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if ($stmt->execute()) {
        $fixed++;
        echo "#{$row['id']}: \"{$row['name']}\" => \"{$updates['name']}\"\n";
    }
}

echo "\nFixed {$fixed} channel(s).\n";
