<?php
require_once __DIR__ . '/includes/functions.php';

$email = trim((string) ($_POST['email'] ?? ''));
$ok = false;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $message = 'Please enter a valid email address.';
} else {
    db()->prepare('INSERT IGNORE INTO subscribers (email) VALUES (?)')->execute([mb_strtolower($email)]);
    $ok = true;
    $message = 'Thanks for subscribing! Check your inbox soon.';
}

if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $message]);
    exit;
}
flash($message, $ok ? 'success' : 'error');
redirect(url('#'));
