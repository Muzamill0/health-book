<?php
require_once __DIR__ . '/../includes/paddle.php';
require_admin();

$mask = fn (string $v) => $v === '' ? null : substr($v, 0, 10) . '…' . substr($v, -4);
$keys = [
    'PADDLE_API_KEY'        => ['API key (server-side)', PADDLE_API_KEY],
    'PADDLE_CLIENT_TOKEN'   => ['Client-side token', PADDLE_CLIENT_TOKEN],
    'PADDLE_WEBHOOK_SECRET' => ['Webhook secret key', PADDLE_WEBHOOK_SECRET],
];
$tokenMismatch = PADDLE_CLIENT_TOKEN !== ''
    && ((PADDLE_ENV === 'production') !== str_starts_with(PADDLE_CLIENT_TOKEN, 'live_'));

// Optional live check that the API key works for the chosen environment.
$check = null;
if (isset($_GET['test']) && PADDLE_API_KEY !== '') {
    try {
        paddle_request('GET', 'event-types');
        $check = ['success', 'Connected to Paddle (' . PADDLE_ENV . ') successfully.'];
    } catch (RuntimeException $ex) {
        $check = ['error', 'Paddle rejected the request: ' . $ex->getMessage()];
    }
}

$vendor = PADDLE_ENV === 'production' ? 'https://vendors.paddle.com' : 'https://sandbox-vendors.paddle.com';
$adminTitle = 'Paddle Payments';
require __DIR__ . '/_top.php';
?>
<?php if ($check): ?><div class="alert <?= e($check[0]) ?>"><?= e($check[1]) ?></div><?php endif; ?>
<div class="panel">
    <div class="panel__head">
        <h2>Configuration</h2>
        <span>
            <span class="badge <?= PADDLE_ENV === 'production' ? 'badge-paid' : 'badge-pending' ?>"><?= PADDLE_ENV === 'production' ? 'Live' : 'Sandbox' ?> mode</span>
            <?php if (PADDLE_API_KEY !== ''): ?><a class="btn btn-sm btn-light" href="?test=1">Test connection</a><?php endif; ?>
        </span>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>.env variable</th><th>Description</th><th>Value</th></tr></thead>
        <tbody>
            <tr><td><code>PADDLE_ENV</code></td><td>sandbox or production</td><td><code><?= e(PADDLE_ENV) ?></code></td></tr>
            <?php foreach ($keys as $name => [$label, $value]): ?>
            <tr>
                <td><code><?= e($name) ?></code></td>
                <td><?= e($label) ?></td>
                <td><?= $value === '' ? '<span class="badge badge-failed">Not set</span>' : '<span class="badge badge-paid">Set</span> <code>' . e($mask($value)) . '</code>' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr><td><code>PADDLE_TAX_CATEGORY</code></td><td>Tax category for products</td><td><code><?= e(PADDLE_TAX_CATEGORY) ?></code></td></tr>
        </tbody>
    </table>
    </div>
    <?php if ($tokenMismatch): ?>
        <div class="alert warning" style="margin-top:12px">The client-side token doesn’t match the environment: sandbox tokens start with <code>test_</code>, live tokens with <code>live_</code>.</div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>How to set up</h2>
    <div class="alert info" style="margin:0">
        1. In <a href="<?= e($vendor) ?>" target="_blank" rel="noopener">Paddle (<?= PADDLE_ENV === 'production' ? 'live' : 'sandbox' ?>)</a> → <strong>Developer tools → Authentication</strong>: create an <strong>API key</strong> and a <strong>client-side token</strong>; paste them into <code>.env</code> as <code>PADDLE_API_KEY</code> and <code>PADDLE_CLIENT_TOKEN</code>.<br>
        2. <strong>Checkout → Checkout settings</strong>: set the <em>Default payment link</em> to <code><?= e(url('checkout.php')) ?></code> (Paddle requires this before it will create transactions). For live, also add your domain under <em>Website approval</em>.<br>
        3. <strong>Developer tools → Notifications</strong>: add a destination <code><?= e(url('webhook.php')) ?></code> with events
           <code>transaction.completed</code>, <code>transaction.paid</code>, <code>transaction.payment_failed</code>, <code>transaction.past_due</code>, <code>transaction.canceled</code>; copy its secret key into <code>PADDLE_WEBHOOK_SECRET</code>.<br>
        4. Test in sandbox with card <code>4242 4242 4242 4242</code>, any future expiry, CVC <code>100</code>. When ready, switch <code>PADDLE_ENV=production</code> and use your live keys.<br>
        5. Paddle is the merchant of record: it adds sales tax/VAT where required, emails the buyer a receipt and generates the invoice. Only card payments are offered at checkout.
    </div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
