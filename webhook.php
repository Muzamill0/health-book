<?php
/**
 * Stripe webhook endpoint. In the Stripe Dashboard add:
 *   URL:    {BASE_URL}/webhook.php
 *   Events: invoice.paid, payment_intent.succeeded, payment_intent.payment_failed, payment_intent.canceled, payment_intent.processing
 */
require_once __DIR__ . '/includes/stripe.php';

$payload = file_get_contents('php://input');
$secret = STRIPE_WEBHOOK_SECRET;

if ($secret === '' || !stripe_verify_signature($payload, $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '', $secret)) {
    http_response_code(400);
    exit('Invalid signature');
}

$event = json_decode($payload, true);
$type = $event['type'] ?? '';
$object = $event['data']['object'] ?? [];

if (str_starts_with($type, 'payment_intent.') && ($object['object'] ?? '') === 'payment_intent') {
    if ($type === 'payment_intent.payment_failed') {
        db()->prepare("UPDATE orders SET status = 'failed' WHERE stripe_payment_intent = ? AND status <> 'paid'")
            ->execute([$object['id']]);
    } else {
        sync_order_from_intent($object);
    }
}

if ($type === 'invoice.paid' && ($object['object'] ?? '') === 'invoice') {
    $stmt = db()->prepare('SELECT * FROM orders WHERE stripe_invoice_id = ?');
    $stmt->execute([$object['id']]);
    if ($order = $stmt->fetch()) {
        db()->prepare("UPDATE orders SET status = 'paid', paid_at = COALESCE(paid_at, NOW()),
                       invoice_number = ?, invoice_url = ?, invoice_pdf = ? WHERE id = ?")
            ->execute([$object['number'], $object['hosted_invoice_url'], $object['invoice_pdf'], $order['id']]);
    }
}

http_response_code(200);
echo 'ok';
