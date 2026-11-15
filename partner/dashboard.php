<?php
require_once __DIR__ . '/../includes/functions.php';
$partner = require_partner();

$links = partner_links((int) $partner['id']);
$totalClicks = array_sum(array_column($links, 'clicks'));
$totalOrders = array_sum(array_column($links, 'orders'));

$partnerTitle = 'Dashboard';
require __DIR__ . '/_top.php';
?>
<h1 class="partner-title">Hi <?= e($partner['name']) ?></h1>

<div class="stats stats--2">
    <div class="stat"><span>Total clicks</span><strong><?= number_format($totalClicks) ?></strong></div>
    <div class="stat"><span>Orders placed</span><strong><?= number_format($totalOrders) ?></strong></div>
</div>

<div class="panel">
    <h2>Your referral links</h2>
    <?php if (!$links): ?>
        <p class="muted">No referral links are assigned to you yet. Please contact the site owner.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Link</th><th>Share this link</th><th>Clicks</th><th>Orders</th></tr></thead>
        <tbody>
        <?php foreach ($links as $l): ?>
            <tr class="<?= $l['is_active'] ? '' : 'is-hidden' ?>">
                <td><strong><?= e($l['name']) ?></strong><?php if (!$l['is_active']): ?><br><span class="badge badge-pending">Paused</span><?php endif; ?></td>
                <td class="link-cell">
                    <div class="copy-row"><input readonly value="<?= e(url('?ref=' . $l['code'])) ?>" aria-label="Referral link"><button type="button" class="btn btn-sm btn-light" data-copy>Copy</button></div>
                </td>
                <td><strong><?= number_format((int) $l['clicks']) ?></strong></td>
                <td><strong><?= number_format((int) $l['orders']) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <p class="muted"><small>Clicks count each visitor once per visit. Orders are completed purchases made within <?= (int) setting('referral_cookie_days', '30') ?> days of clicking your link.</small></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
