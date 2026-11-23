<?php
require_once __DIR__ . '/../includes/functions.php';
$partner = require_partner();
$partnerId = (int) $partner['id'];

$links = partner_links($partnerId);
$linkIds = array_map('intval', array_column($links, 'id'));

// Only the partner's own links can be picked
$linkId = (int) ($_GET['link'] ?? 0);
if (!in_array($linkId, $linkIds, true)) {
    $linkId = 0;
}

[$from, $to] = referral_history_range();
$rows   = $links ? referral_daily_stats($from, $to, $linkId ?: null, $partnerId) : [];
$orders = $links ? partner_orders($partnerId, $from, $to, $linkId ?: null) : [];

$totalClicks = array_sum(array_column($rows, 'clicks'));
$totalOrders = array_sum(array_column($rows, 'orders'));
$salesByCurrency = [];
foreach ($orders as $o) {
    $salesByCurrency[$o['currency']] = ($salesByCurrency[$o['currency']] ?? 0) + (float) $o['amount'];
}
$money = fn ($amount, $currency) => e($currency) . ' ' . number_format((float) $amount, 2);

// Quick ranges
$today = date('Y-m-d');
$quick = [
    'Last 7 days'  => [date('Y-m-d', strtotime('-6 days')), $today],
    'Last 30 days' => [date('Y-m-d', strtotime('-29 days')), $today],
    'This month'   => [date('Y-m-01'), $today],
    'Last month'   => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
];
$qs = fn ($f, $t) => '?' . http_build_query(array_filter(['link' => $linkId ?: null, 'from' => $f, 'to' => $t]));

$partnerTitle = 'Statistics';
require __DIR__ . '/_top.php';
?>
<h1 class="partner-title">Statistics &amp; orders</h1>

<?php if (!$links): ?>
    <div class="panel"><p class="muted">No referral links are assigned to you yet. Please contact the site owner.</p></div>
<?php else: ?>
<form class="panel filters" method="get">
    <?php if (count($links) > 1): ?>
    <select name="link" aria-label="Referral link">
        <option value="">All my links</option>
        <?php foreach ($links as $l): ?>
            <option value="<?= (int) $l['id'] ?>" <?= $linkId === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <label class="inline-form">From <input type="date" name="from" value="<?= e($from) ?>" max="<?= e($today) ?>"></label>
    <label class="inline-form">To <input type="date" name="to" value="<?= e($to) ?>" max="<?= e($today) ?>"></label>
    <button class="btn btn-sm">Show</button>
    <div class="inline-form" style="flex-basis:100%">
        <?php foreach ($quick as $label => [$f, $t]): ?>
            <a class="btn btn-sm <?= ($f === $from && $t === $to) ? '' : 'btn-light' ?>" href="<?= e($qs($f, $t)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</form>

<p class="muted"><?= e(date('j M Y', strtotime($from))) ?> – <?= e(date('j M Y', strtotime($to))) ?></p>
<div class="stats">
    <div class="stat"><span>Clicks</span><strong><?= number_format($totalClicks) ?></strong></div>
    <div class="stat"><span>Orders</span><strong><?= number_format($totalOrders) ?></strong></div>
    <div class="stat"><span>Conversion</span><strong><?= $totalClicks ? number_format($totalOrders / $totalClicks * 100, 1) . '%' : '—' ?></strong></div>
    <div class="stat"><span>Sales amount</span><strong><?php
        if (!$salesByCurrency) {
            echo '0.00';
        }
        foreach ($salesByCurrency as $cur => $sum) {
            echo $money($sum, $cur) . '<br>';
        }
    ?></strong></div>
</div>

<div class="panel">
    <h2>Orders</h2>
    <?php if (!$orders): ?>
        <p class="muted">No orders in this period.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Order</th><th>Customer</th><th>Product</th><?php if (count($links) > 1): ?><th>Link</th><?php endif; ?><th>Amount</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><?= e(date('j M Y, g:i a', strtotime($o['paid_at']))) ?></td>
                <td><?= e($o['order_no']) ?></td>
                <td><?= e(mask_customer_name($o['customer_name'])) ?><br><small class="muted"><?= e(mask_email($o['customer_email'])) ?></small></td>
                <td><?= e($o['product_name']) ?></td>
                <?php if (count($links) > 1): ?><td><?= e($o['link_name']) ?></td><?php endif; ?>
                <td><strong><?= $money($o['amount'], $o['currency']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if (count($orders) === 500): ?><p class="muted"><small>Showing the latest 500 orders. Pick a shorter date range to see the rest.</small></p><?php endif; ?>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Day by day</h2>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Clicks</th><th>Orders</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= ($r['clicks'] || $r['orders']) ? '' : 'is-hidden' ?>">
                <td><?= e(date('D, j M Y', strtotime($r['day']))) ?></td>
                <td><?= number_format($r['clicks']) ?></td>
                <td><strong><?= number_format($r['orders']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="muted"><small>Clicks count each visitor once per visit. An order is shown on the day it was paid. Daily click history starts from 23 Nov 2026; earlier clicks are only included in the totals on your dashboard.</small></p>
</div>
<?php endif; ?>
<?php require __DIR__ . '/_bottom.php'; ?>
