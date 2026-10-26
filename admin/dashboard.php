<?php
require_once __DIR__ . '/../includes/paddle.php';
require_admin();
$adminTitle = 'Dashboard';

// Catch payments whose redirect or webhook never reached us.
$justPaid = paddle_sync_pending();
if ($justPaid) {
    flash($justPaid . ' Paddle payment' . ($justPaid === 1 ? '' : 's') . ' confirmed and marked as paid.');
}

// Revenue and orders come from Paddle only (older Stripe test orders are left out).

$stats = db()->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'paid') AS paid,
    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS revenue,
    COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= CURDATE() THEN amount END), 0) AS today
    FROM orders WHERE payment_provider = 'paddle'")->fetch();
$subs = (int) db()->query('SELECT COUNT(*) FROM subscribers')->fetchColumn();
$recent = db()->query('SELECT o.*, r.name AS referrer_name FROM orders o LEFT JOIN referrers r ON r.id = o.referrer_id WHERE o.payment_provider = \'paddle\' ORDER BY o.id DESC LIMIT 8')->fetchAll();
$sym = setting('currency_symbol');

require __DIR__ . '/_top.php';
?>
<?php if (PADDLE_API_KEY === '' || PADDLE_CLIENT_TOKEN === ''): ?>
    <div class="alert warning">Paddle is not configured yet — customers cannot pay. <a href="payments.php">See how to add your Paddle keys</a>.</div>
<?php endif; ?>
<?php
$noPdf = (int) db()->query("SELECT COUNT(*) FROM books WHERE is_active = 1 AND pdf_file = ''")->fetchColumn();
if ($noPdf): ?>
    <div class="alert warning"><?= $noPdf ?> book<?= $noPdf === 1 ? ' has' : 's have' ?> no PDF yet, so buyers will see “Coming soon” instead of a download. <a href="books.php">Upload book PDFs</a>.</div>
<?php endif; ?>

<div class="stats">
    <div class="stat"><span>Revenue</span><strong><?= e($sym) . number_format((float) $stats['revenue'], 2) ?></strong></div>
    <div class="stat"><span>Today</span><strong><?= e($sym) . number_format((float) $stats['today'], 2) ?></strong></div>
    <div class="stat"><span>Paid orders</span><strong><?= (int) $stats['paid'] ?></strong></div>
    <div class="stat"><span>Subscribers</span><strong><?= $subs ?></strong></div>
</div>

<div class="panel">
    <div class="panel__head"><h2>Recent orders</h2><a href="orders.php">View all</a></div>
    <?php include __DIR__ . '/_orders_table.php'; ?>
</div>

<div class="panel">
    <h2>Quick links</h2>
    <div class="quick">
        <a href="settings.php?group=hero">Edit product &amp; price</a>
        <a href="referrers.php">Referral links</a>
        <a href="books.php">Manage books</a>
        <a href="bundle.php">Bundle &amp; price</a>
        <a href="items.php?type=bonus">Manage bonuses</a>
        <a href="items.php?type=testimonial">Manage reviews</a>
        <a href="items.php?type=faq">Manage FAQs</a>
        <a href="settings.php?group=theme">Change colors</a>
    </div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
