<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Remove session from database before destroying
if (isset($_SESSION['user_id']) && function_exists('removeUserSession')) {
    $session_id = session_id();
    removeUserSession($session_id);
}

logout();
?>
