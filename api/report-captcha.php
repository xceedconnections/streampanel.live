<?php
/**
 * Math captcha for stream reports.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$a = random_int(2, 12);
$b = random_int(1, 9);
$op = random_int(0, 1) === 1 ? '+' : '-';
if ($op === '-' && $b > $a) {
    $tmp = $a;
    $a = $b;
    $b = $tmp;
}
$answer = ($op === '+') ? ($a + $b) : ($a - $b);
$_SESSION['report_captcha_answer'] = (string) $answer;
$_SESSION['report_captcha_time'] = time();

echo json_encode([
    'success' => true,
    'question' => "What is {$a} {$op} {$b}?",
]);
