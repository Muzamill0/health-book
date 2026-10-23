<?php
/**
 * Called by assets/js/checkout.js when the buyer clicks "Continue to payment".
 *  1. Prices the session cart (bundle or selected books) on the server.
 *  2. Finds or creates the Paddle customer for the buyer's email.
 *  3. Creates a Paddle transaction with one line per book (or one line for the bundle).
 *  4. Returns the transaction id so Paddle.js can open the inline checkout for it.
 * Books sell at their own price; the bundle (books and price set by the admin) is the only discount.
 * Prices always come from the database, never from the browser. Paddle adds tax where required.
 */
require_once __DIR__ . '/includes/paddle.php';
header('Content-Type: application/json');

function fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function respond(array $order, string $transactionId): never
{
    echo json_encode([
        'transactionId' => $transactionId,
        'successUrl'    => url('success.php?order=' . urlencode($order['order_no']) . '&t=' . $order['access_token']),
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

// Identifies this exact purchase so a retry (e.g. after a declined card) can reuse the same transaction.
$signature = hash('sha256', json_encode([$email, $cart['type'], array_column($cart['items'], 'id'), $totalMinor, $currency]));

try {
    if (!empty($_SESSION['checkout_order'])) {
        $stmt = db()->prepare("SELECT * FROM orders WHERE id = ? AND status IN ('pending', 'failed')");
        $stmt->execute([$_SESSION['checkout_order']]);
        $previous = $stmt->fetch();
        if ($previous && $previous['paddle_transaction_id']) {
            if (($_SESSION['checkout_signature'] ?? '') === $signature) {
                $txn = paddle_request('GET', 'transactions/' . $previous['paddle_transaction_id']);
                if (in_array($txn['status'] ?? '', ['draft', 'ready'], true)) {
                    respond($previous, $txn['id']);
                }
            }
            paddle_cancel_transaction($previous);
        }
        unset($_SESSION['checkout_order'], $_SESSION['checkout_signature']);
    }

    $customer = paddle_find_or_create_customer($email, $name);
    $referrer = current_referrer();

    $orderNo = 'HB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $token = bin2hex(random_bytes(24));
    $customData = ['order_no' => $orderNo, 'order_type' => $cart['type'], 'books' => (string) $cart['count']]
        + ($referrer ? ['referrer' => $referrer['name'], 'referrer_code' => $referrer['code']] : []);

    db()->beginTransaction();
    db()->prepare('INSERT INTO orders (order_no, access_token, customer_name, customer_email, referrer_id, product_name, order_type,
                   subtotal, discount_amount, amount, currency, payment_provider, paddle_customer_id)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$orderNo, $token, $name, $email, $referrer['id'] ?? null, $cart['name'], $cart['type'],
                   $cart['subtotal'], $cart['discount'], $cart['total'], $currency, 'paddle', $customer['id']]);
    $orderId = (int) db()->lastInsertId();
    // Record the books bought (for a bundle: the bundle's books right now), so later bundle changes don't affect this order.
    $item = db()->prepare('INSERT INTO order_items (order_id, book_id, title, unit_price) VALUES (?,?,?,?)');
    foreach ($cart['items'] as $b) {
        $item->execute([$orderId, $b['id'], $b['title'], $cart['type'] === 'bundle' ? 0 : $b['price']]);
    }
    db()->commit();

    // Non-catalog items: prices come from our database, so nothing has to be set up in the Paddle catalog.
    $line = fn (string $title, string $description, int $amount) => [
        'quantity' => 1,
        'price'    => [
            'description' => mb_substr($title, 0, 200),
            'name'        => mb_substr($title, 0, 150),
            'unit_price'  => ['amount' => (string) $amount, 'currency_code' => strtoupper($currency)],
            'product'     => [
                'name'         => mb_substr($title, 0, 200),
                'description'  => mb_substr($description, 0, 500),
                'tax_category' => PADDLE_TAX_CATEGORY,
            ],
        ],
    ];
    if ($cart['type'] === 'bundle') {
        $items = [$line($cart['name'], implode(', ', array_column($cart['items'], 'title')), $totalMinor)];
    } else {
        $items = array_map(fn ($b) => $line($b['title'] . ' (cookbook PDF)', (string) $b['description'], to_minor_units((float) $b['price'], $currency)), $cart['items']);
    }

    $txn = paddle_request('POST', 'transactions', [
        'items'           => $items,
        'customer_id'     => $customer['id'],
        'currency_code'   => strtoupper($currency),
        'collection_mode' => 'automatic',
        'custom_data'     => $customData,
    ]);
    if (empty($txn['id'])) {
        throw new RuntimeException('Paddle did not return a transaction.');
    }

    db()->prepare('UPDATE orders SET paddle_transaction_id = ? WHERE id = ?')->execute([$txn['id'], $orderId]);

    $_SESSION['checkout_order'] = $orderId;
    $_SESSION['checkout_signature'] = $signature;
    respond(['order_no' => $orderNo, 'access_token' => $token], $txn['id']);
} catch (Throwable $ex) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    // Paddle refused the transaction: drop the order we just saved so no empty pending order is left behind.
    if (!empty($orderId) && empty($txn['id'])) {
        db()->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
        db()->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    }
    error_log('Checkout error: ' . $ex->getMessage());
    fail($ex instanceof RuntimeException ? $ex->getMessage() : 'Could not create the order. Please try again.', 500);
}
