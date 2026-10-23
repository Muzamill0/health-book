<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$status = $_GET['status'] ?? '';
$q = trim((string) ($_GET['q'] ?? ''));
$ref = (string) ($_GET['ref'] ?? '');
$where = [];
$args = [];
if ($status !== '') {
    $where[] = 'o.status = ?';
    $args[] = $status;
}
if ($q !== '') {
    $where[] = '(o.order_no LIKE ? OR o.customer_email LIKE ? OR o.customer_name LIKE ? OR o.invoice_number LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%", "%$q%");
}
if ($ref === 'direct') {
    $where[] = 'o.referrer_id IS NULL';
} elseif ($ref !== '') {
    $where[] = 'o.referrer_id = ?';
    $args[] = (int) $ref;
}
$sql = 'SELECT o.*, r.name AS referrer_name FROM orders o LEFT JOIN referrers r ON r.id = o.referrer_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.id DESC LIMIT 500';
$stmt = db()->prepare($sql);
$stmt->execute($args);
$recent = $stmt->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="orders-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Name', 'Email', 'Referred by', 'Type', 'Product', 'Amount', 'Currency', 'Status', 'Invoice', 'Invoice PDF', 'Stripe Customer', 'Stripe PaymentIntent', 'Created', 'Paid', 'Downloads']);
    foreach ($recent as $o) {
        fputcsv($out, [$o['order_no'], $o['customer_name'], $o['customer_email'], $o['referrer_name'] ?? 'Direct', $o['order_type'], $o['product_name'], $o['amount'], $o['currency'], $o['status'], $o['invoice_number'], $o['invoice_pdf'], $o['stripe_customer_id'], $o['stripe_payment_intent'], $o['created_at'], $o['paid_at'], $o['download_count']]);
    }
    exit;
}

$adminTitle = 'Orders';
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <form class="filters" method="get">
        <input name="q" value="<?= e($q) ?>" placeholder="Search order, invoice, name or email">
        <select name="status">
            <option value="">All statuses</option>
            <?php foreach (['paid', 'pending', 'processing', 'failed', 'cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="ref">
            <option value="">All sources</option>
            <option value="direct" <?= $ref === 'direct' ? 'selected' : '' ?>>Direct (no link)</option>
            <?php foreach (db()->query('SELECT id, name FROM referrers ORDER BY name') as $rf): ?>
                <option value="<?= (int) $rf['id'] ?>" <?= $ref === (string) $rf['id'] ? 'selected' : '' ?>><?= e($rf['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-light">Filter</button>
        <a class="btn btn-light" href="?<?= e(http_build_query(['q' => $q, 'status' => $status, 'ref' => $ref, 'export' => 'csv'])) ?>">Export CSV</a>
    </form>
    <p class="muted"><small>“Pending” orders are checkouts that were started but not paid.</small></p>
    <?php include __DIR__ . '/_orders_table.php'; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
