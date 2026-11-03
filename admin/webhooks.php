<?php
require_once __DIR__ . '/../includes/paddle.php';
require_admin();

$show = $_GET['show'] ?? 'all';
$where = match ($show) {
    'problems' => "WHERE signature_ok = 0 OR result LIKE 'Error%' OR result = 'No matching order'",
    default    => '',
};
$rows = db()->query("SELECT * FROM webhook_events $where ORDER BY id DESC LIMIT 200")->fetchAll();
$last = db()->query('SELECT created_at FROM webhook_events WHERE signature_ok = 1 ORDER BY id DESC LIMIT 1')->fetchColumn();
$hookUrl = url('webhook.php');

$adminTitle = 'Webhook Log';
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <h2>Paddle webhook</h2>
    <div class="form-row">
        <label>Webhook URL — paste this in Paddle → Developer tools → Notifications → New destination</label>
        <div class="copy-row"><input readonly value="<?= e($hookUrl) ?>"><button type="button" class="btn btn-sm btn-light" data-copy>Copy</button></div>
        <?php if (str_contains($hookUrl, 'localhost')): ?>
            <small class="muted">This is your local address — Paddle can’t reach it. Use the URL shown on your live site’s admin (e.g. https://your-domain.com/webhook.php).</small>
        <?php endif; ?>
    </div>
    <p style="margin:.4rem 0">
        Events to select: <code>transaction.completed</code> <code>transaction.paid</code> <code>transaction.payment_failed</code> <code>transaction.past_due</code> <code>transaction.canceled</code>
    </p>
    <p style="margin:.4rem 0">
        Secret key (<code>PADDLE_WEBHOOK_SECRET</code> in .env):
        <?= PADDLE_WEBHOOK_SECRET === '' ? '<span class="badge badge-failed">Not set — every webhook will be rejected</span>' : '<span class="badge badge-paid">Set</span>' ?>
        &nbsp;·&nbsp; Last valid webhook: <strong><?= $last ? e(date('M j, Y H:i', strtotime($last))) : 'none yet' ?></strong>
    </p>
</div>

<div class="panel">
    <div class="panel__head">
        <h2>Received events</h2>
        <span>
            <a class="btn btn-sm <?= $show === 'all' ? '' : 'btn-light' ?>" href="?show=all">All</a>
            <a class="btn btn-sm <?= $show === 'problems' ? '' : 'btn-light' ?>" href="?show=problems">Problems only</a>
        </span>
    </div>
    <?php if (!$rows): ?>
        <p class="muted"><?= $show === 'problems' ? 'No problems — all received webhooks were processed.' : 'No webhooks received yet. Once Paddle sends one it will appear here.' ?></p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Received</th><th>Event</th><th>Order</th><th>Result</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $w):
            $bad = !$w['signature_ok'] || str_starts_with($w['result'], 'Error') || $w['result'] === 'No matching order'; ?>
            <tr>
                <td><?= e(date('M j, Y H:i:s', strtotime($w['created_at']))) ?></td>
                <td><code><?= e($w['event_type'] ?: '—') ?></code><?php if ($w['transaction_id']): ?><br><small class="muted"><?= e($w['transaction_id']) ?></small><?php endif; ?></td>
                <td><?= $w['order_no'] ? '<a href="orders.php?provider=all&amp;q=' . e(urlencode($w['order_no'])) . '">' . e($w['order_no']) . '</a>' : '<span class="muted">—</span>' ?></td>
                <td><span class="badge <?= $bad ? 'badge-failed' : 'badge-paid' ?>"><?= e($w['result']) ?></span></td>
                <td><?php if ($w['payload']): ?>
                    <details><summary class="muted" style="cursor:pointer">Data</summary>
                        <pre class="payload"><?= e(json_encode(json_decode($w['payload']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $w['payload']) ?></pre>
                    </details>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="muted"><small>Showing the latest 200. Entries older than 90 days are removed automatically.</small></p>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var input = btn.previousElementSibling;
    input.select();
    (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject()).catch(function () { document.execCommand('copy'); });
    btn.textContent = 'Copied!';
    setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
  });
});
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
