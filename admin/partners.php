<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'toggle':
            db()->prepare('UPDATE partners SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('User status updated.');
            break;
        case 'delete':
            db()->prepare('UPDATE referrers SET partner_id = NULL WHERE partner_id = ?')->execute([$id]);
            db()->prepare('DELETE FROM partners WHERE id = ?')->execute([$id]);
            flash('User deleted. Their referral links are kept and can be assigned to someone else.');
            break;
    }
    redirect('partners.php');
}

$rows = db()->query("SELECT p.*,
        (SELECT COUNT(*) FROM referrers r WHERE r.partner_id = p.id) AS links,
        (SELECT COALESCE(SUM(r.clicks), 0) FROM referrers r WHERE r.partner_id = p.id) AS clicks,
        (SELECT COUNT(*) FROM orders o JOIN referrers r ON r.id = o.referrer_id
          WHERE r.partner_id = p.id AND o.status = 'paid' AND o.payment_provider = 'paddle') AS orders
    FROM partners p ORDER BY p.name")->fetchAll();

$adminTitle = 'Partner Users';
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <div class="panel__head">
        <p class="muted" style="margin:0">People who can log in with their email or phone number to see clicks and orders for their referral links.<br>
            Login page for partners: <a href="<?= e(url('partner/')) ?>" target="_blank"><?= e(url('partner/')) ?></a></p>
        <a class="btn" href="partner-edit.php">+ Add user</a>
    </div>
    <?php if (!$rows): ?>
        <p class="muted">No users yet. Click “Add user” to create the first one.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>User</th><th>Login</th><th>Links</th><th>Clicks</th><th>Orders</th><th>Last login</th><th>Status</th><th style="width:230px"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): ?>
            <tr class="<?= $p['is_active'] ? '' : 'is-hidden' ?>">
                <td><strong><?= e($p['name']) ?></strong></td>
                <td><?= e($p['email']) ?><br><small class="muted">+<?= e($p['phone']) ?></small></td>
                <td><?= (int) $p['links'] ?></td>
                <td><?= number_format((int) $p['clicks']) ?></td>
                <td><?= number_format((int) $p['orders']) ?></td>
                <td><?= $p['last_login_at'] ? e(date('M j, Y H:i', strtotime($p['last_login_at']))) : '<span class="muted">never</span>' ?></td>
                <td>
                    <span class="badge <?= $p['is_active'] ? 'badge-paid' : 'badge-pending' ?>"><?= $p['is_active'] ? 'Active' : 'Disabled' ?></span>
                    <?php if ($p['locked_until'] && strtotime($p['locked_until']) > time()): ?><br><span class="badge badge-failed">Locked</span><?php endif; ?>
                </td>
                <td class="actions">
                    <a class="btn btn-sm btn-light" href="partner-edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-light"><?= $p['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                    <form method="post" data-confirm="Delete this user? Their referral links stay and can be reassigned."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
