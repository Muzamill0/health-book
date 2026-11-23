<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$referrers = db()->query("SELECT r.id, r.name, p.name AS owner_name
    FROM referrers r LEFT JOIN partners p ON p.id = r.partner_id
    ORDER BY r.name")->fetchAll();
$partners = db()->query('SELECT id, name FROM partners ORDER BY name')->fetchAll();

// ?ref=ID for one link, ?partner=ID for all links of one partner user, neither for every link
$refId     = (int) ($_GET['ref'] ?? 0);
$partnerId = $refId ? 0 : (int) ($_GET['partner'] ?? 0);

[$from, $to] = referral_history_range();
$rows = referral_daily_stats($from, $to, $refId ?: null, $partnerId ?: null);
$totalClicks  = array_sum(array_column($rows, 'clicks'));
$totalOrders  = array_sum(array_column($rows, 'orders'));
$totalRevenue = array_sum(array_column($rows, 'revenue'));
$sym = setting('currency_symbol');
$pct = fn ($orders, $clicks) => $clicks ? number_format($orders / $clicks * 100, 1) . '%' : '—';

$adminTitle = 'Referral History';
require __DIR__ . '/_top.php';
?>
<form class="panel filters" method="get">
    <select name="ref" aria-label="Referral link">
        <option value="">All links</option>
        <?php foreach ($referrers as $rf): ?>
            <option value="<?= (int) $rf['id'] ?>" <?= $refId === (int) $rf['id'] ? 'selected' : '' ?>><?= e($rf['name']) ?><?= $rf['owner_name'] ? ' (' . e($rf['owner_name']) . ')' : '' ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($partners): ?>
    <select name="partner" aria-label="Partner user">
        <option value="">All partner users</option>
        <?php foreach ($partners as $p): ?>
            <option value="<?= (int) $p['id'] ?>" <?= $partnerId === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <label class="inline-form">From <input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="inline-form">To <input type="date" name="to" value="<?= e($to) ?>"></label>
    <button class="btn btn-sm">Show</button>
</form>

<div class="stats">
    <div class="stat"><span>Clicks</span><strong><?= number_format($totalClicks) ?></strong></div>
    <div class="stat"><span>Sales</span><strong><?= number_format($totalOrders) ?></strong></div>
    <div class="stat"><span>Conversion</span><strong><?= $pct($totalOrders, $totalClicks) ?></strong></div>
    <div class="stat"><span>Revenue</span><strong><?= e($sym) . number_format($totalRevenue, 2) ?></strong></div>
</div>

<div class="panel">
    <div class="table-wrap">
    <table>
        <thead><tr><th>Date</th><th>Clicks</th><th>Sales</th><th>Conversion</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= ($r['clicks'] || $r['orders']) ? '' : 'is-hidden' ?>">
                <td><?= e(date('D, j M Y', strtotime($r['day']))) ?></td>
                <td><?= number_format($r['clicks']) ?></td>
                <td><strong><?= number_format($r['orders']) ?></strong></td>
                <td><?= $pct($r['orders'], $r['clicks']) ?></td>
                <td><?= e($sym) . number_format($r['revenue'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="muted"><small>Sales are paid orders through a referral link, shown on the day they were paid. Daily clicks are recorded from 23 Nov 2026; clicks before that only appear in the all-time totals on Referral Links.</small></p>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
