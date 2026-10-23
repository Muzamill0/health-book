<?php
/**
 * Minimal Stripe REST client (no Composer needed).
 */
require_once __DIR__ . '/functions.php';

function stripe_request(string $method, string $path, array $params = []): array
{
    $secret = STRIPE_SECRET_KEY;
    if ($secret === '') {
        throw new RuntimeException('Stripe secret key is not configured. Add STRIPE_SECRET_KEY to the .env file.');
    }
    $ch = curl_init('https://api.stripe.com/v1/' . ltrim($path, '/')
        . ($method === 'GET' && $params ? '?' . http_build_query($params) : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secret . ':',
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Stripe-Version: 2024-06-20'],
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        throw new RuntimeException('Could not reach Stripe: ' . $err);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $data = json_decode($body, true) ?: [];
    if ($status >= 400) {
        throw new RuntimeException($data['error']['message'] ?? 'Stripe error (HTTP ' . $status . ').');
    }
    return $data;
}

/**
 * Verify a webhook payload against the Stripe-Signature header.
 */
function stripe_verify_signature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $timestamp = null;
    $signatures = [];
    foreach (explode(',', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 't') {
            $timestamp = (int) $v;
        } elseif ($k === 'v1') {
            $signatures[] = $v;
        }
    }
    if (!$timestamp || !$signatures || abs(time() - $timestamp) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($signatures as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}

/** Return the Stripe Customer for this email, creating it (or refreshing its name) as needed. */
function stripe_find_or_create_customer(string $email, string $name): array
{
    $found = stripe_request('GET', 'customers', ['email' => $email, 'limit' => 1]);
    $customer = $found['data'][0] ?? null;
    if ($customer === null) {
        return stripe_request('POST', 'customers', [
            'email'    => $email,
            'name'     => $name,
            'metadata' => ['source' => 'health-book website'],
        ]);
    }
    if (($customer['name'] ?? '') !== $name) {
        $customer = stripe_request('POST', 'customers/' . $customer['id'], ['name' => $name]);
    }
    return $customer;
}

/** Void an order's unpaid Stripe invoice and mark the order cancelled. */
function stripe_void_invoice(array $order): void
{
    if ($order['stripe_invoice_id']) {
        try {
            stripe_request('POST', 'invoices/' . $order['stripe_invoice_id'] . '/void');
        } catch (RuntimeException $ex) {
            error_log('Could not void invoice ' . $order['stripe_invoice_id'] . ': ' . $ex->getMessage());
        }
    }
    db()->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status <> 'paid'")->execute([$order['id']]);
}

/** Copy the latest invoice number and links onto the order (the PDF link changes once the invoice is paid). */
function sync_order_invoice(array $order): array
{
    if (!$order['stripe_invoice_id']) {
        return $order;
    }
    try {
        $invoice = stripe_request('GET', 'invoices/' . $order['stripe_invoice_id']);
    } catch (RuntimeException $ex) {
        error_log('Could not load invoice ' . $order['stripe_invoice_id'] . ': ' . $ex->getMessage());
        return $order;
    }
    $order['invoice_number'] = $invoice['number'];
    $order['invoice_url'] = $invoice['hosted_invoice_url'];
    $order['invoice_pdf'] = $invoice['invoice_pdf'];
    db()->prepare('UPDATE orders SET invoice_number = ?, invoice_url = ?, invoice_pdf = ? WHERE id = ?')
        ->execute([$order['invoice_number'], $order['invoice_url'], $order['invoice_pdf'], $order['id']]);
    return $order;
}

/** Mark an order paid/failed based on a PaymentIntent object. */
function sync_order_from_intent(array $pi): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE stripe_payment_intent = ?');
    $stmt->execute([$pi['id']]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $status = match ($pi['status']) {
        'succeeded'                => 'paid',
        'processing'               => 'processing',
        'canceled'                 => 'cancelled',
        'requires_payment_method'  => $order['status'] === 'pending' ? 'pending' : 'failed',
        default                    => $order['status'],
    };
    if ($status !== $order['status']) {
        db()->prepare('UPDATE orders SET status = ?, paid_at = IF(? = "paid", NOW(), paid_at) WHERE id = ?')
            ->execute([$status, $status, $order['id']]);
        $order['status'] = $status;
        if ($status === 'paid') {
            $order = sync_order_invoice($order);
        }
    }
    return $order;
}
