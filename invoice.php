<?php
/**
 * Sends a paid buyer to their Paddle invoice PDF. Paddle's invoice links expire,
 * so a fresh one is requested every time (order number + secret token required).
 */
require_once __DIR__ . '/includes/paddle.php';

$stmt = db()->prepare('SELECT * FROM orders WHERE order_no = ?');
$stmt->execute([(string) ($_GET['order'] ?? '')]);
$order = $stmt->fetch();
if (!$order || !hash_equals($order['access_token'], (string) ($_GET['t'] ?? '')) || !$order['paddle_transaction_id']) {
    http_response_code(404);
    exit('Invoice not found.');
}
if ($order['status'] !== 'paid') {
    http_response_code(403);
    exit('The invoice is available once the payment is complete.');
}
$link = paddle_invoice_url($order['paddle_transaction_id']);
if (!$link) {
    http_response_code(502);
    exit('The invoice is not ready yet. Please try again in a minute.');
}
header('Cache-Control: no-store');
redirect($link);
