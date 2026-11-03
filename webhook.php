<?php
/**
 * Paddle webhook endpoint. In Paddle → Developer tools → Notifications, add a destination:
 *   URL:    {BASE_URL}/webhook.php
 *   Events: transaction.completed, transaction.paid, transaction.payment_failed,
 *           transaction.past_due, transaction.canceled
 * and put its secret key in PADDLE_WEBHOOK_SECRET.
 * Every request is recorded in webhook_events (Admin → Webhook Log).
 */
require_once __DIR__ . '/includes/paddle.php';

function webhook_log(array $event, bool $signatureOk, string $result, ?string $orderNo = null, ?string $payload = null): void
{
    try {
        db()->prepare('INSERT INTO webhook_events (event_id, event_type, transaction_id, order_no, signature_ok, result, payload)
                       VALUES (?,?,?,?,?,?,?)')
            ->execute([
                mb_substr((string) ($event['event_id'] ?? ''), 0, 100) ?: null,
                mb_substr((string) ($event['event_type'] ?? ''), 0, 100),
                mb_substr((string) ($event['data']['id'] ?? ''), 0, 100) ?: null,
                $orderNo,
                $signatureOk ? 1 : 0,
                mb_substr($result, 0, 255),
                $payload,
            ]);
        // Keep the log small: drop entries older than 90 days now and then.
        if (random_int(1, 50) === 1) {
            db()->exec('DELETE FROM webhook_events WHERE created_at < NOW() - INTERVAL 90 DAY');
        }
    } catch (Throwable $ex) {
        error_log('Webhook log failed: ' . $ex->getMessage());
    }
}

$payload = file_get_contents('php://input');
$event = json_decode($payload, true) ?: [];

if (PADDLE_WEBHOOK_SECRET === '' || !paddle_verify_signature($payload, $_SERVER['HTTP_PADDLE_SIGNATURE'] ?? '', PADDLE_WEBHOOK_SECRET)) {
    // Not stored with its payload: an unsigned request may not come from Paddle.
    webhook_log($event, false, PADDLE_WEBHOOK_SECRET === '' ? 'Rejected: PADDLE_WEBHOOK_SECRET is not set' : 'Rejected: invalid signature');
    http_response_code(400);
    exit('Invalid signature');
}

$type = $event['event_type'] ?? '';
$txn = $event['data'] ?? [];

try {
    if (!str_starts_with($type, 'transaction.') || empty($txn['id'])) {
        webhook_log($event, true, 'Ignored (not a transaction event)', null, $payload);
    } elseif ($type === 'transaction.payment_failed') {
        // A declined attempt; the buyer can retry on the same transaction, so only flag unpaid orders.
        $stmt = db()->prepare("UPDATE orders SET status = 'failed' WHERE paddle_transaction_id = ? AND status = 'pending'");
        $stmt->execute([$txn['id']]);
        $find = db()->prepare('SELECT order_no, status FROM orders WHERE paddle_transaction_id = ?');
        $find->execute([$txn['id']]);
        $order = $find->fetch();
        webhook_log($event, true, $order ? 'Payment declined — order is ' . $order['status'] : 'No matching order', $order['order_no'] ?? null, $payload);
    } else {
        $order = sync_order_from_transaction($txn);
        webhook_log($event, true, $order ? 'Order is ' . $order['status'] : 'No matching order', $order['order_no'] ?? null, $payload);
    }
} catch (Throwable $ex) {
    // Tell Paddle it failed so it retries the event later.
    error_log('Webhook error: ' . $ex->getMessage());
    webhook_log($event, true, 'Error: ' . $ex->getMessage(), null, $payload);
    http_response_code(500);
    exit('Error');
}

http_response_code(200);
echo 'ok';
