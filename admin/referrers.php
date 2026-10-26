<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$slug = fn (string $s) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');
$editId = (int) ($_GET['edit'] ?? 0);
$form = ['id' => 0, 'name' => '', 'code' => '', 'notes' => '', 'is_active' => 1];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'save':
            $form = [
                'id'        => $id,
                'name'      => trim((string) ($_POST['name'] ?? '')),
                'code'      => substr($slug((string) ($_POST['code'] ?? '') ?: (string) ($_POST['name'] ?? '')), 0, 50),
                'notes'     => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 255),
                'is_active' => empty($_POST['is_active']) ? 0 : 1,
            ];
            $dupe = db()->prepare('SELECT COUNT(*) FROM referrers WHERE code = ? AND id <> ?');
            $dupe->execute([$form['code'], $id]);
            if ($form['name'] === '' || $form['code'] === '') {
                $error = 'Name is required.';
            } elseif ($dupe->fetchColumn()) {
                $error = 'The code “' . $form['code'] . '” is already used by another link.';
            } else {
                if ($id) {
                    db()->prepare('UPDATE referrers SET name = ?, code = ?, notes = ?, is_active = ? WHERE id = ?')
                        ->execute([$form['name'], $form['code'], $form['notes'], $form['is_active'], $id]);
                } else {
                    db()->prepare('INSERT INTO referrers (name, code, notes, is_active) VALUES (?,?,?,?)')
                        ->execute([$form['name'], $form['code'], $form['notes'], $form['is_active']]);
                }
                flash('Referral link saved.');
                redirect('referrers.php');
            }
            $editId = $id;
            break;
        case 'toggle':
            db()->prepare('UPDATE referrers SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('Link status updated.');
            redirect('referrers.php');
        case 'delete':
            $used = db()->prepare('SELECT COUNT(*) FROM orders WHERE referrer_id = ?');
            $used->execute([$id]);
            if ($used->fetchColumn()) {
                flash('This link has orders, so it can’t be deleted. Disable it instead.', 'error');
            } else {
                db()->prepare('DELETE FROM referrers WHERE id = ?')->execute([$id]);
                flash('Referral link deleted.');
            }
            redirect('referrers.php');
        case 'cookie_days':
            save_setting('referral_cookie_days', (string) max(1, min(365, (int) ($_POST['days'] ?? 30))));
            flash('Attribution window saved.');
            redirect('referrers.php');
    }
}

if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = db()->prepare('SELECT * FROM referrers WHERE id = ?');
    $stmt->execute([$editId]);
    $form = $stmt->fetch() ?: $form;
}

$rows = db()->query("SELECT r.*,
        COUNT(o.id) AS checkouts,
        COALESCE(SUM(o.status = 'paid'), 0) AS sales,
        COALESCE(SUM(CASE WHEN o.status = 'paid' THEN o.amount END), 0) AS revenue
    FROM referrers r
    LEFT JOIN orders o ON o.referrer_id = r.id AND o.payment_provider = 'paddle'
    GROUP BY r.id
    ORDER BY revenue DESC, r.name")->fetchAll();
$direct = db()->query("SELECT COALESCE(SUM(status = 'paid'), 0) AS sales,
        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS revenue
    FROM orders WHERE referrer_id IS NULL AND payment_provider = 'paddle'")->fetch();
$sym = setting('currency_symbol');
$days = (int) setting('referral_cookie_days', '30');

$adminTitle = 'Referral Links';
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<form class="panel form" method="post">
    <h2><?= $form['id'] ? 'Edit link' : 'Create a referral link' ?></h2>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $form['id'] ?>">
    <div class="form-row form-row--split">
        <div><label for="name">Person / partner name</label><input id="name" name="name" value="<?= e($form['name']) ?>" placeholder="e.g. Ali Khan" required></div>
        <div><label for="code">Link code (letters, numbers, dashes)</label><input id="code" name="code" value="<?= e($form['code']) ?>" placeholder="auto from name, e.g. ali-khan"></div>
    </div>
    <div class="form-row form-row--split">
        <div><label for="notes">Notes (optional)</label><input id="notes" name="notes" value="<?= e($form['notes']) ?>" placeholder="e.g. Facebook health groups"></div>
        <div><label>Status</label><label class="switch"><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>><span>Active</span></label></div>
    </div>
    <div class="form-actions">
        <button class="btn"><?= $form['id'] ? 'Save changes' : '+ Create link' ?></button>
        <?php if ($form['id']): ?><a class="btn btn-light" href="referrers.php">Cancel</a><?php endif; ?>
    </div>
</form>

<div class="panel">
    <div class="panel__head">
        <h2>Links &amp; results</h2>
        <form method="post" class="inline-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="cookie_days">
            <label for="days" class="muted" style="font-weight:500">Credit a sale to the link for</label>
            <input id="days" type="number" name="days" min="1" max="365" value="<?= $days ?>" style="width:80px">
            <span class="muted">days</span>
            <button class="btn btn-sm btn-light">Save</button>
        </form>
    </div>
    <?php if (!$rows): ?>
        <p class="muted">No referral links yet. Create one above for each person who will share the checkout.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Person</th><th>Share link</th><th>Clicks</th><th>Checkouts</th><th>Sales</th><th>Conversion</th><th>Revenue</th><th style="width:200px"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $link = url('?ref=' . $r['code']);
            $checkoutLink = url('checkout.php?ref=' . $r['code']); ?>
            <tr class="<?= $r['is_active'] ? '' : 'is-hidden' ?>">
                <td>
                    <strong><?= e($r['name']) ?></strong>
                    <?php if (!$r['is_active']): ?> <span class="badge badge-pending">Disabled</span><?php endif; ?>
                    <?php if ($r['notes']): ?><br><small class="muted"><?= e($r['notes']) ?></small><?php endif; ?>
                </td>
                <td class="link-cell">
                    <div class="copy-row"><input readonly value="<?= e($link) ?>" aria-label="Homepage link"><button type="button" class="btn btn-sm btn-light" data-copy>Copy</button></div>
                    <div class="copy-row"><input readonly value="<?= e($checkoutLink) ?>" aria-label="Direct checkout link"><button type="button" class="btn btn-sm btn-light" data-copy>Copy</button></div>
                    <small class="muted">1st: sales page · 2nd: straight to checkout</small>
                </td>
                <td><?= (int) $r['clicks'] ?></td>
                <td><?= (int) $r['checkouts'] ?></td>
                <td><strong><?= (int) $r['sales'] ?></strong></td>
                <td><?= $r['clicks'] ? number_format($r['sales'] / $r['clicks'] * 100, 1) . '%' : '—' ?></td>
                <td><strong><?= e($sym) . number_format((float) $r['revenue'], 2) ?></strong></td>
                <td class="actions">
                    <a class="btn btn-sm btn-light" href="orders.php?ref=<?= (int) $r['id'] ?>">Orders</a>
                    <a class="btn btn-sm btn-light" href="?edit=<?= (int) $r['id'] ?>">Edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-light"><?= $r['is_active'] ? 'Disable' : 'Enable' ?></button></form>
                    <?php if (!(int) $r['checkouts']): ?>
                    <form method="post" data-confirm="Delete this referral link?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr>
                <td><strong>Direct</strong><br><small class="muted">No referral link</small></td>
                <td colspan="3"></td>
                <td><strong><?= (int) $direct['sales'] ?></strong></td>
                <td></td>
                <td><strong><?= e($sym) . number_format((float) $direct['revenue'], 2) ?></strong></td>
                <td><a class="btn btn-sm btn-light" href="orders.php?ref=direct">Orders</a></td>
            </tr>
        </tbody>
    </table>
    </div>
    <p class="muted"><small>Clicks are counted once per visitor session. If a visitor opens two different links, the most recent one gets the sale.</small></p>
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
