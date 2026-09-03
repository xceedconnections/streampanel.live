<?php
/**
 * Public endpoint: report a broken stream / content issue.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = getDBConnection();

$conn->query("CREATE TABLE IF NOT EXISTS reports (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) DEFAULT NULL,
    content_type ENUM('movie','tv_show','tv_episode','live_tv') NOT NULL,
    content_id INT(11) NOT NULL,
    source_id VARCHAR(100) DEFAULT NULL,
    issue_type ENUM('broken_link','wrong_content','quality_issue','copyright','other') DEFAULT 'broken_link',
    description TEXT DEFAULT NULL,
    status ENUM('pending','resolved','dismissed') DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME DEFAULT NULL,
    admin_reply TEXT DEFAULT NULL,
    reply_read TINYINT(1) DEFAULT 0,
    PRIMARY KEY (id),
    KEY user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Upgrade enum if table already existed without copyright
@$conn->query("ALTER TABLE reports MODIFY issue_type ENUM('broken_link','wrong_content','quality_issue','copyright','other') DEFAULT 'broken_link'");

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$captcha = trim((string) ($data['captcha_answer'] ?? ''));
$expected = (string) ($_SESSION['report_captcha_answer'] ?? '');
$captchaTime = (int) ($_SESSION['report_captcha_time'] ?? 0);
unset($_SESSION['report_captcha_answer'], $_SESSION['report_captcha_time']);

if ($expected === '' || $captcha === '' || !hash_equals($expected, $captcha) || ($captchaTime > 0 && (time() - $captchaTime) > 600)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Captcha failed. Please try again.']);
    exit;
}

$allowed_types = ['movie', 'tv_show', 'tv_episode', 'live_tv'];
$allowed_issues = ['broken_link', 'wrong_content', 'quality_issue', 'copyright', 'other'];

$content_type = strtolower(trim((string) ($data['content_type'] ?? 'live_tv')));
if (!in_array($content_type, $allowed_types, true)) {
    $content_type = 'live_tv';
}

$content_id = (int) ($data['content_id'] ?? 0);
$source_id = substr(trim((string) ($data['source_id'] ?? '')), 0, 100);
$issue_type = strtolower(trim((string) ($data['issue_type'] ?? 'broken_link')));
if (!in_array($issue_type, $allowed_issues, true)) {
    $issue_type = 'broken_link';
}

$description = trim((string) ($data['description'] ?? ''));
if ($description === '') {
    $description = ($issue_type === 'copyright') ? 'Copyright report' : 'Stream is not working';
}
if (strlen($description) > 2000) {
    $description = substr($description, 0, 2000);
}

if ($content_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid content']);
    exit;
}

$user_id = isLoggedIn() ? (int) $_SESSION['user_id'] : null;
$session_key = 'reported_' . $content_type . '_' . $content_id . '_' . $issue_type;

if (!empty($_SESSION[$session_key])) {
    echo json_encode(['success' => true, 'message' => 'Already reported. Thank you.']);
    exit;
}

if ($user_id) {
    $dup = $conn->prepare("SELECT id FROM reports WHERE user_id = ? AND content_type = ? AND content_id = ? AND issue_type = ? AND status = 'pending' AND created_at > DATE_SUB(NOW(), INTERVAL 6 HOUR) LIMIT 1");
    if ($dup) {
        $dup->bind_param('isis', $user_id, $content_type, $content_id, $issue_type);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $_SESSION[$session_key] = 1;
            echo json_encode(['success' => true, 'message' => 'Already reported. Thank you.']);
            exit;
        }
    }
}

if ($user_id) {
    $stmt = $conn->prepare("INSERT INTO reports (user_id, content_type, content_id, source_id, issue_type, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isisss', $user_id, $content_type, $content_id, $source_id, $issue_type, $description);
} else {
    $stmt = $conn->prepare("INSERT INTO reports (user_id, content_type, content_id, source_id, issue_type, description) VALUES (NULL, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sisss', $content_type, $content_id, $source_id, $issue_type, $description);
}

if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not submit report']);
    exit;
}

$_SESSION[$session_key] = 1;
echo json_encode(['success' => true, 'message' => 'Report sent. We will check this stream.']);
