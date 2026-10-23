<?php
/**
 * Paddle webhook endpoint. In Paddle → Developer tools → Notifications, add a destination:
 *   URL:    {BASE_URL}/webhook.php
 *   Events: transaction.completed, transaction.paid, transaction.payment_failed,
 *           transaction.past_due, transaction.canceled
 * and put its secret key in PADDLE_WEBHOOK_SECRET.
 */
require_once __DIR__ . '/includes/paddle.php';

$payload = file_get_contents('php://input');

if (PADDLE_WEBHOOK_SECRET === '' || !paddle_verify_signature($payload, $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '', PADDLE_WEBHOOK_SECRET)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
$type = $event['event_type'] ?? '';
$txn = $event['data'] ?? [];

if (str_starts_with($type, 'transaction.') && !empty($txn['id'])) {
    if ($type === 'transaction.payment_failed') {
        // A declined attempt; the buyer can retry on the same transaction, so only flag unpaid orders.
        db()->prepare("UPDATE orders SET status = 'failed' WHERE paddle_transaction_id = ? AND status = 'pending'")
            ->execute([$txn['id']]);
    } else {
        sync_order_from_transaction($txn);
    }
}

http_response_code(200);
echo 'ok';
