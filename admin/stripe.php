<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$mask = fn (string $v) => $v === '' ? null : substr($v, 0, 8) . '…' . substr($v, -4);
$keys = [
    'STRIPE_PUBLISHABLE_KEY' => ['Publishable key', STRIPE_PUBLISHABLE_KEY, 'pk_'],
    'STRIPE_SECRET_KEY'      => ['Secret key', STRIPE_SECRET_KEY, 'sk_'],
    'STRIPE_WEBHOOK_SECRET'  => ['Webhook signing secret', STRIPE_WEBHOOK_SECRET, 'whsec_'],
];
$mode = str_starts_with(STRIPE_SECRET_KEY, 'sk_live_') ? 'Live' : (STRIPE_SECRET_KEY !== '' ? 'Test' : null);

$adminTitle = 'Stripe Payments';
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <div class="panel__head">
        <h2>Key status</h2>
        <?php if ($mode): ?><span class="badge <?= $mode === 'Live' ? 'badge-paid' : 'badge-pending' ?>"><?= $mode ?> mode</span><?php endif; ?>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>.env variable</th><th>Description</th><th>Value</th></tr></thead>
        <tbody>
        <?php foreach ($keys as $name => [$label, $value, $prefix]): ?>
            <tr>
                <td><code><?= e($name) ?></code></td>
                <td><?= e($label) ?></td>
                <td>
                    <?php if ($value === ''): ?>
                        <span class="badge badge-failed">Not set</span>
                    <?php elseif (!str_starts_with($value, $prefix)): ?>
                        <span class="badge badge-failed">Should start with <?= e($prefix) ?></span>
                    <?php else: ?>
                        <span class="badge badge-paid">Set</span> <code><?= e($mask($value)) ?></code>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="panel">
    <h2>How to set up</h2>
    <div class="alert info" style="margin:0">
        1. Copy your keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank" rel="noopener">Stripe Dashboard → Developers → API keys</a>.<br>
        2. Paste them into the <code>.env</code> file in the project root and save. Changes apply immediately.<br>
        3. While testing use <code>pk_test_</code>/<code>sk_test_</code> keys and card <code>4242 4242 4242 4242</code> (any future date, any CVC).<br>
        4. For live payments add a webhook at <code><?= e(url('webhook.php')) ?></code> with events
           <code>invoice.paid</code>, <code>payment_intent.succeeded</code>, <code>payment_intent.payment_failed</code>, <code>payment_intent.canceled</code>, <code>payment_intent.processing</code>,
           then put its signing secret in <code>STRIPE_WEBHOOK_SECRET</code>.<br>
        5. Every buyer is saved as a Stripe Customer and charged through a Stripe Invoice. To email paid invoices and receipts automatically, turn on
           <em>Successful payments</em> in <a href="https://dashboard.stripe.com/settings/emails" target="_blank" rel="noopener">Stripe → Settings → Customer emails</a>.
    </div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
