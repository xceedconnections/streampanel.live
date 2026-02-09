<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/config.php';

$page_title = "Report";
$conn = getDBConnection();
$message = '';
$message_type = '';

// Check if user is banned (allow access to report page even if banned)
$is_banned = false;
$banned_user_id = null;

if (isset($_GET['banned']) && isset($_SESSION['banned_user_id'])) {
    $banned_user_id = $_SESSION['banned_user_id'];
    $is_banned = true;
    
    // Verify user is actually banned
    $stmt = $conn->prepare("SELECT id, username, email, full_name FROM users WHERE id = ? AND banned = 1");
    $stmt->bind_param("i", $banned_user_id);
    $stmt->execute();
    $banned_user = $stmt->get_result()->fetch_assoc();
    
    if (!$banned_user) {
        // User is not banned, redirect to login
        header('Location: login.php');
        exit();
    }
} else {
    // Check if user is logged in normally
    require_once __DIR__ . '/includes/auth.php';
    if (isLoggedIn()) {
        $banned_user_id = $_SESSION['user_id'];
        // Check if user is banned
        $stmt = $conn->prepare("SELECT banned FROM users WHERE id = ?");
        $stmt->bind_param("i", $banned_user_id);
        $stmt->execute();
        $user_check = $stmt->get_result()->fetch_assoc();
        if ($user_check && $user_check['banned']) {
            $is_banned = true;
        } else {
            // User is logged in and not banned, allow access to general report page
            $is_banned = false;
        }
    } else {
        // Not logged in and not banned, redirect to login
        header('Location: login.php');
        exit();
    }
}

// Handle message submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_message'])) {
    $subject = trim($_POST['subject'] ?? '');
    $message_text = trim($_POST['message'] ?? '');
    
    if (empty($subject) || empty($message_text)) {
        $message = 'Please fill in all fields';
        $message_type = 'error';
    } else {
        // Insert message
        $stmt = $conn->prepare("INSERT INTO user_messages (user_id, subject, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $banned_user_id, $subject, $message_text);
        
        if ($stmt->execute()) {
            $message = 'Your message has been sent. We will review it and get back to you soon.';
            $message_type = 'success';
        } else {
            $message = 'Failed to send message. Please try again.';
            $message_type = 'error';
        }
    }
}

// Get user's messages and replies
$user_messages = [];
$unread_replies_count = 0;
if ($banned_user_id) {
    $stmt = $conn->prepare("SELECT * FROM user_messages WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $banned_user_id);
    $stmt->execute();
    $user_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Count unread replies (messages with admin_reply that were replied after last view)
    // For simplicity, we'll count messages with replies that have status 'replied' or 'resolved'
    foreach ($user_messages as $msg) {
        if (!empty($msg['admin_reply']) && in_array($msg['status'], ['replied', 'resolved'])) {
            $unread_replies_count++;
        }
    }
}

// Get user info
$user_info = null;
if ($banned_user_id) {
    $stmt = $conn->prepare("SELECT username, email, full_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $banned_user_id);
    $stmt->execute();
    $user_info = $stmt->get_result()->fetch_assoc();
}

include 'includes/header.php';
?>

<div class="min-h-screen bg-gradient-to-b from-black via-gray-900 to-black py-20">
    <div class="container mx-auto px-4 max-w-4xl">
        
        <?php if ($is_banned): ?>
        <!-- Banned User Message -->
        <div class="bg-red-900 bg-opacity-50 border-2 border-red-600 rounded-lg p-6 mb-8 text-center">
            <h1 class="text-4xl font-bold text-red-400 mb-4">YOU ARE BANNED</h1>
            <p class="text-xl text-red-200 mb-2">For violating our terms and conditions</p>
            <p class="text-lg text-red-300">Please contact us using the form below if you believe this is a mistake.</p>
        </div>
        <?php endif; ?>
        
        <div class="bg-gray-900 bg-opacity-90 rounded-lg p-8 mb-8">
            <h2 class="text-3xl font-bold mb-6"><?php echo $is_banned ? 'Contact Support' : 'Report an Issue'; ?></h2>
            
            <?php if ($message): ?>
            <div class="bg-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-900 bg-opacity-50 border border-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-700 text-<?php echo $message_type === 'success' ? 'green' : 'red'; ?>-200 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            
            <!-- Submit Message Form -->
            <form method="POST" action="" class="mb-8">
                <input type="hidden" name="submit_message" value="1">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Subject *</label>
                    <input type="text" name="subject" placeholder="Enter subject" required
                           class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Message *</label>
                    <textarea name="message" rows="6" placeholder="Write your message here..." required
                              class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:border-netflix-red"></textarea>
                </div>
                <button type="submit" class="bg-netflix-red px-6 py-3 rounded hover:bg-red-700 font-semibold">
                    Send Message
                </button>
            </form>
        </div>
        
        <!-- User Messages/Reports -->
        <?php if (!empty($user_messages)): ?>
        <div class="bg-gray-900 bg-opacity-90 rounded-lg p-8">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold">Your Messages</h3>
                <?php if ($unread_replies_count > 0): ?>
                <span class="bg-netflix-red text-white px-3 py-1 rounded-full text-sm font-semibold">
                    <?php echo $unread_replies_count; ?> New Reply<?php echo $unread_replies_count > 1 ? 'ies' : ''; ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="space-y-6">
                <?php foreach ($user_messages as $msg): ?>
                <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h4 class="text-lg font-semibold text-white"><?php echo htmlspecialchars($msg['subject']); ?></h4>
                            <p class="text-sm text-gray-400"><?php echo date('F j, Y g:i A', strtotime($msg['created_at'])); ?></p>
                        </div>
                        <span class="px-3 py-1 rounded text-xs font-semibold
                            <?php 
                            if ($msg['status'] === 'replied') echo 'bg-green-900 text-green-200';
                            elseif ($msg['status'] === 'resolved') echo 'bg-blue-900 text-blue-200';
                            else echo 'bg-yellow-900 text-yellow-200';
                            ?>">
                            <?php echo strtoupper($msg['status']); ?>
                        </span>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($msg['message']); ?></p>
                    </div>
                    
                    <?php if (!empty($msg['admin_reply'])): ?>
                    <div class="mt-4 pt-4 border-t border-gray-700">
                        <div class="flex items-center mb-2">
                            <span class="text-sm font-semibold text-green-400">Admin Reply:</span>
                            <span class="text-sm text-gray-400 ml-2"><?php echo date('F j, Y g:i A', strtotime($msg['replied_at'])); ?></span>
                        </div>
                        <p class="text-gray-300 bg-gray-700 rounded p-3 whitespace-pre-wrap"><?php echo htmlspecialchars($msg['admin_reply']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-gray-900 bg-opacity-90 rounded-lg p-8 text-center">
            <p class="text-gray-400">No messages yet. Use the form above to contact us.</p>
        </div>
        <?php endif; ?>
        
        <!-- General Report Section (for non-banned users) -->
        <?php if (!$is_banned && isLoggedIn()): ?>
        <div class="bg-gray-900 bg-opacity-90 rounded-lg p-8 mt-8">
            <h3 class="text-2xl font-bold mb-6">Report Content Issue</h3>
            
            <?php
            // Handle content report submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
                $content_type = $_POST['content_type'] ?? '';
                $content_id = intval($_POST['content_id'] ?? 0);
                $source_id = $_POST['source_id'] ?? '';
                $issue_type = $_POST['issue_type'] ?? 'broken_link';
                $description = trim($_POST['description'] ?? '');
                
                if ($content_id > 0 && !empty($description)) {
                    $user_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("INSERT INTO reports (user_id, content_type, content_id, source_id, issue_type, description) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isisss", $user_id, $content_type, $content_id, $source_id, $issue_type, $description);
                    
                    if ($stmt->execute()) {
                        echo '<div class="bg-green-900 bg-opacity-50 border border-green-700 text-green-200 px-4 py-3 rounded mb-6">Report submitted successfully. Thank you!</div>';
                    } else {
                        echo '<div class="bg-red-900 bg-opacity-50 border border-red-700 text-red-200 px-4 py-3 rounded mb-6">Failed to submit report. Please try again.</div>';
                    }
                }
            }
            
            // Get user's reports
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT r.*, 
                CASE 
                    WHEN r.content_type = 'movie' THEN m.title
                    WHEN r.content_type = 'tv_show' THEN t.title
                    WHEN r.content_type = 'tv_episode' THEN CONCAT(t.title, ' - S', e.season_number, 'E', e.episode_number)
                    WHEN r.content_type = 'live_tv' THEN l.name
                END as content_title
                FROM reports r
                LEFT JOIN movies m ON r.content_type = 'movie' AND r.content_id = m.id
                LEFT JOIN tv_shows t ON r.content_type = 'tv_show' AND r.content_id = t.id
                LEFT JOIN tv_episodes e ON r.content_type = 'tv_episode' AND r.content_id = e.id
                LEFT JOIN tv_shows t2 ON e.tv_show_id = t2.id
                LEFT JOIN live_tv_channels l ON r.content_type = 'live_tv' AND r.content_id = l.id
                WHERE r.user_id = ? 
                ORDER BY r.created_at DESC");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Count unread report replies
            $unread_report_replies = 0;
            foreach ($user_reports as $report) {
                if (!empty($report['admin_reply']) && !($report['reply_read'] ?? false)) {
                    $unread_report_replies++;
                }
            }
            ?>
            
            <form method="POST" action="" class="mb-8">
                <input type="hidden" name="submit_report" value="1">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Content Type *</label>
                        <select name="content_type" required class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white">
                            <option value="">Select...</option>
                            <option value="movie">Movie</option>
                            <option value="tv_show">TV Show</option>
                            <option value="tv_episode">TV Episode</option>
                            <option value="live_tv">Live TV</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Content ID *</label>
                        <input type="number" name="content_id" placeholder="Enter content ID" required
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2">Issue Type *</label>
                        <select name="issue_type" required class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white">
                            <option value="broken_link">Broken Link</option>
                            <option value="wrong_content">Wrong Content</option>
                            <option value="quality_issue">Quality Issue</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-2">Source ID (Optional)</label>
                        <input type="text" name="source_id" placeholder="Source ID if applicable"
                               class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-2">Description *</label>
                    <textarea name="description" rows="4" placeholder="Describe the issue..." required
                              class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white"></textarea>
                </div>
                <button type="submit" class="bg-netflix-red px-6 py-3 rounded hover:bg-red-700 font-semibold">
                    Submit Report
                </button>
            </form>
            
            <!-- User's Reports -->
            <?php if (!empty($user_reports)): ?>
            <div class="mt-8">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-xl font-bold">Your Reports</h4>
                    <?php if ($unread_report_replies > 0): ?>
                    <span class="bg-netflix-red text-white px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo $unread_report_replies; ?> New Reply<?php echo $unread_report_replies > 1 ? 'ies' : ''; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="space-y-4">
                    <?php foreach ($user_reports as $report): ?>
                    <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <p class="font-semibold"><?php echo htmlspecialchars($report['content_title'] ?? 'Unknown'); ?></p>
                                <p class="text-sm text-gray-400"><?php echo ucfirst(str_replace('_', ' ', $report['content_type'])); ?> - <?php echo ucfirst(str_replace('_', ' ', $report['issue_type'])); ?></p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs
                                <?php 
                                if ($report['status'] === 'resolved') echo 'bg-green-900 text-green-200';
                                elseif ($report['status'] === 'dismissed') echo 'bg-gray-700 text-gray-300';
                                else echo 'bg-yellow-900 text-yellow-200';
                                ?>">
                                <?php echo strtoupper($report['status']); ?>
                            </span>
                        </div>
                        <p class="text-sm text-gray-300 mb-2"><?php echo htmlspecialchars($report['description']); ?></p>
                        <?php if (!empty($report['admin_reply'])): ?>
                        <div class="mt-2 pt-2 border-t border-gray-700 <?php echo !($report['reply_read'] ?? false) ? 'bg-green-900 bg-opacity-30 rounded p-2 border border-green-700' : ''; ?>">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-xs text-green-400 font-semibold">Admin Reply:</p>
                                <?php if (!($report['reply_read'] ?? false)): ?>
                                <span class="text-xs bg-green-600 text-white px-2 py-1 rounded">NEW</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-300"><?php echo htmlspecialchars($report['admin_reply']); ?></p>
                            <?php
                            // Mark as read when viewed
                            if (!($report['reply_read'] ?? false)) {
                                $update_stmt = $conn->prepare("UPDATE reports SET reply_read = 1 WHERE id = ?");
                                $update_stmt->bind_param("i", $report['id']);
                                $update_stmt->execute();
                            }
                            ?>
                        </div>
                        <?php endif; ?>
                        <p class="text-xs text-gray-500 mt-2"><?php echo date('F j, Y g:i A', strtotime($report['created_at'])); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<?php include 'includes/footer.php'; ?>
