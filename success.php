<?php
require_once __DIR__ . '/includes/stripe.php';

$stmt = db()->prepare('SELECT * FROM orders WHERE order_no = ?');
$stmt->execute([(string) ($_GET['order'] ?? '')]);
$order = $stmt->fetch();
if (!$order || !hash_equals($order['access_token'], (string) ($_GET['t'] ?? ''))) {
    $order = null;
}

$error = '';
if ($order && $order['status'] !== 'paid' && $order['stripe_payment_intent']) {
    try {
        // Always confirm with Stripe; never trust the redirect query string alone.
        $pi = stripe_request('GET', 'payment_intents/' . $order['stripe_payment_intent']);
        $order = sync_order_from_intent($pi) ?? $order;
    } catch (Throwable $ex) {
        $error = 'We could not confirm your payment yet. Please refresh this page in a moment.';
    }
}

if ($order && $order['status'] === 'paid') {
    unset($_SESSION['checkout_order'], $_SESSION['checkout_signature']);
    if (($_SESSION['paid_cart_cleared'] ?? '') !== $order['order_no']) {
        // Empty the cart once after a successful purchase.
        cart_set(false, []);
        $_SESSION['paid_cart_cleared'] = $order['order_no'];
    }
    if (!$order['invoice_pdf'] && $order['stripe_invoice_id']) {
        $order = sync_order_invoice($order);
    }
}

$pageTitle = 'Order status — ' . setting('site_name');
$hideNewsletter = true;
require __DIR__ . '/includes/header.php';
?>
<section class="page-content">
    <div class="container narrow center">
        <div class="card">
        <?php if (!$order): ?>
            <h1>Order not found</h1>
            <p class="muted">We couldn’t find this order. If you were charged, please contact <a href="mailto:<?= e(setting('contact_email')) ?>"><?= e(setting('contact_email')) ?></a>.</p>
        <?php elseif ($order['status'] === 'paid'): ?>
            <div class="success-icon">✓</div>
            <h1><?= e(setting('thankyou_heading')) ?></h1>
            <p class="lead"><?= nl2br(e(setting('thankyou_text'))) ?></p>
            <div class="order-meta">
                <div><strong>Order:</strong> <?= e($order['order_no']) ?></div>
                <?php if ($order['invoice_number']): ?><div><strong>Invoice:</strong> <?= e($order['invoice_number']) ?></div><?php endif; ?>
                <div><strong>Product:</strong> <?= e($order['product_name']) ?></div>
                <div><strong>Amount:</strong> <?= e(setting('currency_symbol')) . number_format((float) $order['amount'], 2) ?> <?= e(strtoupper($order['currency'])) ?></div>
                <div><strong>Email:</strong> <?= e($order['customer_email']) ?></div>
            </div>
            <?php if ($order['invoice_pdf'] || $order['invoice_url']): ?>
                <div class="invoice-actions" style="margin:0 0 1.5rem">
                    <?php if ($order['invoice_pdf']): ?><a class="btn btn-light" href="<?= e($order['invoice_pdf']) ?>" target="_blank" rel="noopener">📄 Download invoice (PDF)</a><?php endif; ?>
                    <?php if ($order['invoice_url']): ?><a class="btn btn-light" href="<?= e($order['invoice_url']) ?>" target="_blank" rel="noopener">View invoice online</a><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php
            $files = order_files($order);
            $counts = order_download_counts((int) $order['id']);
            $limit = (int) setting('download_limit', '10');
            $ready = array_filter($files, fn ($f) => $f['path'] !== '' || $f['url'] !== '');
            if ($ready):
                $extraScripts = '<script>window.DOWNLOAD = ' . json_encode([
                    'url'   => url('download.php'),
                    'order' => $order['order_no'],
                    't'     => $order['access_token'],
                    'csrf'  => csrf_token(),
                    'zipName' => preg_replace('/[^A-Za-z0-9]+/', '-', setting('site_name')) . '-' . $order['order_no'] . '.zip',
                    // Start the download automatically on the first visit only.
                    'auto'  => $counts ? null : (count($ready) > 1 ? 'all' : array_key_first($ready)),
                    'names' => array_map(fn ($f) => $f['filename'], $ready),
                ], JSON_HEX_TAG | JSON_HEX_AMP) . ';</script><script src="' . e(url('assets/js/download.js')) . '"></script>'; ?>
                <?php if (count($ready) > 1): ?>
                    <button type="button" class="btn btn-lg" data-download="all">⬇ Download all (<?= count($ready) ?> files, ZIP)</button>
                <?php endif; ?>
                <p class="download-status" data-download-status role="status"></p>
                <ul class="files">
                    <?php foreach ($files as $key => $f):
                        $used = (int) ($counts[$key] ?? 0); ?>
                        <li>
                            <span>📘 <?= e($f['title']) ?></span>
                            <?php if (!isset($ready[$key])): ?>
                                <small class="muted">Coming soon</small>
                            <?php elseif ($limit > 0 && $used >= $limit): ?>
                                <small class="muted">Download limit reached</small>
                            <?php else: ?>
                                <button type="button" class="btn btn-sm btn-light" data-download="<?= e($key) ?>">⬇ Download</button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="muted" style="font-size:.9rem">Bookmark this page — you can come back to download again<?= $limit > 0 ? ' (up to ' . $limit . ' times per file)' : '' ?>.</p>
            <?php else: ?>
                <p class="muted">Your files are being prepared and will be available on this page shortly.</p>
            <?php endif; ?>
        <?php elseif ($order['status'] === 'processing'): ?>
            <h1>Payment processing…</h1>
            <p class="muted">Your payment is being processed. Refresh this page in a minute.</p>
        <?php else: ?>
            <h1>Payment not completed</h1>
            <p class="muted"><?= e($error ?: 'Your payment was not completed and you have not been charged.') ?></p>
            <a class="btn" href="<?= e(url('checkout.php')) ?>">Try again</a>
        <?php endif; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
