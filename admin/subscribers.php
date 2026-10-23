<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([(int) $_POST['id']]);
    flash('Subscriber removed.');
    redirect('subscribers.php');
}
$rows = db()->query('SELECT * FROM subscribers ORDER BY id DESC')->fetchAll();

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Email', 'Subscribed']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'], $r['created_at']]);
    }
    exit;
}

$adminTitle = 'Subscribers';
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <div class="panel__head"><p class="muted" style="margin:0"><?= count($rows) ?> subscriber(s)</p><a class="btn btn-light" href="?export=csv">Export CSV</a></div>
    <?php if (!$rows): ?><p class="muted">No subscribers yet.</p><?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Email</th><th>Subscribed</th><th style="width:110px"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['email']) ?></td>
                <td><?= e(date('M j, Y H:i', strtotime($r['created_at']))) ?></td>
                <td><form method="post" data-confirm="Remove this subscriber?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger">Remove</button></form></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
