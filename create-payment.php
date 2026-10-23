<?php
/**
 * Called by assets/js/checkout.js when the buyer clicks Pay.
 *  1. Prices the session cart (complete bundle or selected books) on the server.
 *  2. Finds or creates the Stripe Customer for the buyer's email.
 *  3. Creates and finalizes a Stripe Invoice with one line per book (plus the discount line).
 *  4. Returns the client secret of the invoice's PaymentIntent so the browser can pay it.
 * Books sell at their own price; the bundle (books and price set by the admin) is the only discount.
 * Prices always come from the database, never from the browser.
 */
require_once __DIR__ . '/includes/stripe.php';
header('Content-Type: application/json');

function fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function respond(array $order, string $clientSecret): never
{
    echo json_encode([
        'clientSecret' => $clientSecret,
        'returnUrl'    => url('success.php?order=' . urlencode($order['order_no']) . '&t=' . $order['access_token']),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Method not allowed.', 405);
}
if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
    fail('Your session expired. Please refresh the page.');
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
if ($name === '' || mb_strlen($name) > 150) {
    fail('Please enter your name.');
}
if ($email === '') {
    fail('Email address is required.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) {
    fail('Please enter a valid email address.');
}

$cart = cart_summary();
$currency = strtolower(setting('currency', 'usd'));
if ($cart['type'] === 'empty') {
    fail('Your cart is empty. Please choose your books again.');
}
$totalMinor = to_minor_units($cart['total'], $currency);
if ((int) ($_POST['expected'] ?? -1) !== $totalMinor) {
    fail('Your cart or prices changed. Please refresh the page to see the latest total.');
}
if ($totalMinor < 50) {
    fail('The order total is too low to process a card payment.');
}

// Identifies this exact purchase so a retry (e.g. after a declined card) can reuse the same invoice.
$signature = hash('sha256', json_encode([$email, $cart['type'], array_column($cart['items'], 'id'), $totalMinor, $currency]));

try {
    if (!empty($_SESSION['checkout_order'])) {
        $stmt = db()->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('pending', 'failed')");
        $stmt->execute([$_SESSION['checkout_order']]);
        $previous = $stmt->fetch();
        if ($previous && $previous['stripe_payment_intent']) {
            if (($_SESSION['checkout_signature'] ?? '') === $signature) {
                $pi = stripe_request('GET', 'payment_intents/' . $previous['stripe_payment_intent']);
                if (in_array($pi['status'], ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                    respond($previous, $pi['client_secret']);
                }
            }
            stripe_void_invoice($previous);
        }
        unset($_SESSION['checkout_order'], $_SESSION['checkout_signature']);
    }

    $customer = stripe_find_or_create_customer($email, $name);
    $referrer = current_referrer();

    $orderNo = 'HB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $token = bin2hex(random_bytes(24));
    $meta = ['order_no' => $orderNo, 'order_type' => $cart['type'], 'books' => (string) $cart['count']]
        + ($referrer ? ['referrer' => $referrer['name'], 'referrer_code' => $referrer['code']] : []);

    db()->beginTransaction();
    db()->prepare('INSERT INTO orders (order_no, access_token, customer_name, customer_email, referrer_id, product_name, order_type,
                   subtotal, discount_amount, amount, currency, stripe_customer_id)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$orderNo, $token, $name, $email, $referrer['id'] ?? null, $cart['name'], $cart['type'],
                   $cart['subtotal'], $cart['discount'], $cart['total'], $currency, $customer['id']]);
    $orderId = (int) db()->lastInsertId();
    // Record the books bought (for a bundle: the bundle's books right now), so later bundle changes don't affect this order.
    $item = db()->prepare('INSERT INTO order_items (order_id, book_id, title, unit_price) VALUES (?,?,?,?)');
    foreach ($cart['items'] as $b) {
        $item->execute([$orderId, $b['id'], $b['title'], $cart['type'] === 'bundle' ? 0 : $b['price']]);
    }
    db()->commit();

    $invoice = stripe_request('POST', 'invoices', [
        'customer'                       => $customer['id'],
        'currency'                       => $currency,
        'collection_method'              => 'charge_automatically',
        'auto_advance'                   => 'false',
        'pending_invoice_items_behavior' => 'exclude',
        'description'                    => 'Order ' . $orderNo,
        'metadata'                       => $meta,
        'payment_settings'               => ['payment_method_types' => ['card']],
    ]);

    $lines = [];
    if ($cart['type'] === 'bundle') {
        $lines[] = [to_minor_units($cart['total'], $currency), $cart['name'] . ': ' . mb_strimwidth(implode(', ', array_column($cart['items'], 'title')), 0, 200, '…')];
    } else {
        foreach ($cart['items'] as $b) {
            $lines[] = [to_minor_units((float) $b['price'], $currency), $b['title'] . ' (cookbook PDF)'];
        }
    }
    foreach ($lines as [$amount, $description]) {
        stripe_request('POST', 'invoiceitems', [
            'customer'    => $customer['id'],
            'invoice'     => $invoice['id'],
            'amount'      => $amount,
            'currency'    => $currency,
            'description' => mb_substr($description, 0, 250),
        ]);
    }

    $invoice = stripe_request('POST', 'invoices/' . $invoice['id'] . '/finalize', [
        'auto_advance' => 'false',
        'expand'       => ['payment_intent'],
    ]);
    $pi = $invoice['payment_intent'];
    if (!is_array($pi) || empty($pi['client_secret'])) {
        throw new RuntimeException('Stripe did not return a payment for this invoice.');
    }
    if ((int) $pi['amount'] !== $totalMinor) {
        throw new RuntimeException('The invoice total did not match your cart. Please try again.');
    }
    // Tag the PaymentIntent so it is easy to find in the Stripe Dashboard.
    stripe_request('POST', 'payment_intents/' . $pi['id'], ['metadata' => $meta]);

    db()->prepare('UPDATE orders SET stripe_invoice_id = ?, invoice_number = ?, invoice_url = ?, invoice_pdf = ?, stripe_payment_intent = ? WHERE id = ?')
        ->execute([$invoice['id'], $invoice['number'], $invoice['hosted_invoice_url'], $invoice['invoice_pdf'], $pi['id'], $orderId]);

    $_SESSION['checkout_order'] = $orderId;
    $_SESSION['checkout_signature'] = $signature;
    respond(['order_no' => $orderNo, 'access_token' => $token], $pi['client_secret']);
} catch (Throwable $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('Checkout error: ' . $ex->getMessage());
    fail($ex instanceof RuntimeException ? $ex->getMessage() : 'Could not create the order. Please try again.', 500);
}
