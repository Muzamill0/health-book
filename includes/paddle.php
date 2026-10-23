<?php
/**
 * Minimal Paddle Billing API client (no Composer needed).
 * Docs: https://developer.paddle.com/api-reference/overview
 */
require_once __DIR__ . '/functions.php';

function paddle_api_base(): string
{
    return PADDLE_ENV === 'production' ? 'https://api.paddle.com' : 'https://sandbox-api.paddle.com';
}

/** Paddle dashboard link for a transaction or customer (admin convenience). */
function paddle_dashboard_url(string $type, string $id): string
{
    $host = PADDLE_ENV === 'production' ? 'https://vendors.paddle.com' : 'https://sandbox-vendors.paddle.com';
    return $host . ($type === 'customer' ? '/customers-v2/' : '/transactions-v2/') . rawurlencode($id);
}

/** Call the Paddle API and return the response's "data" member. */
function paddle_request(string $method, string $path, ?array $body = null, array $query = []): array
{
    if (PADDLE_API_KEY === '') {
        throw new RuntimeException('Paddle API key is not configured. Add PADDLE_API_KEY to the .env file.');
    }
    $ch = curl_init(paddle_api_base() . '/' . ltrim($path, '/') . ($query ? '?' . http_build_query($query) : ''));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . PADDLE_API_KEY,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        throw new RuntimeException('Could not reach Paddle: ' . curl_error($ch));
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $json = json_decode($raw, true) ?: [];
    if ($status >= 400) {
        $err = $json['error'] ?? [];
        $detail = $err['detail'] ?? ('Paddle error (HTTP ' . $status . ').');
        foreach ($err['errors'] ?? [] as $fieldError) {
            $detail .= ' ' . ($fieldError['field'] ?? '') . ': ' . ($fieldError['message'] ?? '');
        }
        throw new RuntimeException(trim($detail));
    }
    return $json['data'] ?? [];
}

/**
 * Verify a webhook against the Paddle-Signature header ("ts=...;h1=...").
 * https://developer.paddle.com/webhooks/signature-verification
 */
function paddle_verify_signature(string $payload, string $header, string $secret, int $tolerance = 300): bool
{
    $timestamp = null;
    $hashes = [];
    foreach (explode(';', $header) as $part) {
        [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
        if ($k === 'ts') {
            $timestamp = (int) $v;
        } elseif ($k === 'h1') {
            $hashes[] = $v;
        }
    }
    if (!$timestamp || !$hashes || abs(time() - $timestamp) > $tolerance) {
        return false;
    }
    $expected = hash_hmac('sha256', $timestamp . ':' . $payload, $secret);
    foreach ($hashes as $h) {
        if (hash_equals($expected, $h)) {
            return true;
        }
    }
    return false;
}

/** Return the Paddle customer for this email, creating it (or refreshing its name) as needed. */
function paddle_find_or_create_customer(string $email, string $name): array
{
    $found = paddle_request('GET', 'customers', null, ['email' => $email]);
    $customer = $found[0] ?? null;
    if ($customer === null) {
        return paddle_request('POST', 'customers', ['email' => $email, 'name' => $name]);
    }
    if (($customer['name'] ?? '') !== $name) {
        $customer = paddle_request('PATCH', 'customers/' . $customer['id'], ['name' => $name]);
    }
    return $customer;
}

/** Cancel an order's unpaid Paddle transaction and mark the order cancelled. */
function paddle_cancel_transaction(array $order): void
{
    if ($order['paddle_transaction_id']) {
        try {
            paddle_request('PATCH', 'transactions/' . $order['paddle_transaction_id'], ['status' => 'canceled']);
        } catch (RuntimeException $ex) {
            error_log('Could not cancel transaction ' . $order['paddle_transaction_id'] . ': ' . $ex->getMessage());
        }
    }
    db()->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status <> 'paid'")->execute([$order['id']]);
}

/** A fresh link to the transaction's invoice PDF (Paddle links expire, so fetch one each time). */
function paddle_invoice_url(string $transactionId): ?string
{
    try {
        return paddle_request('GET', 'transactions/' . $transactionId . '/invoice')['url'] ?? null;
    } catch (RuntimeException $ex) {
        error_log('Could not get invoice for ' . $transactionId . ': ' . $ex->getMessage());
        return null;
    }
}

/** Update an order from a Paddle transaction object (from the API or a webhook). */
function sync_order_from_transaction(array $txn): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE paddle_transaction_id = ?');
    $stmt->execute([$txn['id'] ?? '']);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }
    $status = match ($txn['status'] ?? '') {
        'paid', 'completed' => 'paid',
        'past_due'          => 'failed',
        'canceled'          => 'cancelled',
        default             => $order['status'] === 'paid' ? 'paid' : 'pending',
    };

    $updates = ['status' => $status];
    if ($status === 'paid') {
        $currency = strtolower($txn['currency_code'] ?? $order['currency']);
        $totals = $txn['details']['totals'] ?? [];
        if (isset($totals['grand_total'])) {
            // What the buyer actually paid, including any tax Paddle added.
            $updates['amount'] = from_minor_units((int) $totals['grand_total'], $currency);
            $updates['tax_amount'] = from_minor_units((int) ($totals['tax'] ?? 0), $currency);
        }
        if (!empty($txn['invoice_number'])) {
            $updates['invoice_number'] = $txn['invoice_number'];
        }
    }

    $changed = array_filter($updates, fn ($v, $k) => (string) $order[$k] !== (string) $v, ARRAY_FILTER_USE_BOTH);
    if ($changed) {
        $set = implode(', ', array_map(fn ($k) => "$k = ?", array_keys($changed)));
        if (($changed['status'] ?? '') === 'paid') {
            $set .= ', paid_at = COALESCE(paid_at, NOW())';
        }
        db()->prepare("UPDATE orders SET $set WHERE id = ?")->execute([...array_values($changed), $order['id']]);
        $order = array_merge($order, $changed);
    }
    return $order;
}
