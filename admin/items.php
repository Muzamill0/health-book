<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$types = require __DIR__ . '/../includes/item_types.php';
$type = $_GET['type'] ?? '';
if (!isset($types[$type])) {
    redirect('dashboard.php');
}
$cfg = $types[$type];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'delete':
            db()->prepare('DELETE FROM items WHERE id = ? AND type = ?')->execute([$id, $type]);
            flash('Item deleted.');
            break;
        case 'toggle':
            db()->prepare('UPDATE items SET is_active = 1 - is_active WHERE id = ? AND type = ?')->execute([$id, $type]);
            flash('Visibility updated.');
            break;
        case 'order':
            $stmt = db()->prepare('UPDATE items SET sort_order = ? WHERE id = ? AND type = ?');
            foreach ((array) ($_POST['sort'] ?? []) as $itemId => $pos) {
                $stmt->execute([(int) $pos, (int) $itemId, $type]);
            }
            flash('Order saved.');
            break;
    }
    redirect('items.php?type=' . urlencode($type));
}

$stmt = db()->prepare('SELECT * FROM items WHERE type = ? ORDER BY sort_order, id');
$stmt->execute([$type]);
$rows = $stmt->fetchAll();

$adminTitle = $cfg['label'];
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <div class="panel__head">
        <p class="muted" style="margin:0"><?= e($cfg['help']) ?></p>
        <a class="btn" href="item-edit.php?type=<?= e($type) ?>">+ Add new</a>
    </div>

    <?php if (!$rows): ?>
        <p class="muted">Nothing here yet. Click “Add new” to create the first item.</p>
    <?php else: ?>
    <form method="post" id="order-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="order">
    </form>
    <div class="table-wrap">
    <table>
        <thead><tr><th style="width:80px">Order</th><?php if (isset($cfg['fields']['image']) || isset($cfg['fields']['icon'])): ?><th style="width:70px"></th><?php endif; ?><th><?= e($cfg['fields']['title']) ?></th><th>Status</th><th style="width:230px"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr class="<?= $r['is_active'] ? '' : 'is-hidden' ?>">
                <td><input form="order-form" class="sort-input" type="number" name="sort[<?= (int) $r['id'] ?>]" value="<?= (int) $r['sort_order'] ?>"></td>
                <?php if (isset($cfg['fields']['image']) || isset($cfg['fields']['icon'])): ?>
                <td><?php if ($r['image']): ?><img class="thumb" src="<?= e(img_url($r['image'])) ?>" alt=""><?php else: ?><span class="icon-cell"><?= e($r['icon']) ?></span><?php endif; ?></td>
                <?php endif; ?>
                <td>
                    <strong><?= e($r['title']) ?></strong>
                    <?php if ($r['subtitle']): ?><br><small class="muted"><?= e($r['subtitle']) ?></small><?php endif; ?>
                    <?php if ($r['body']): ?><br><small class="muted"><?= e(mb_strimwidth($r['body'], 0, 110, '…')) ?></small><?php endif; ?>
                </td>
                <td><span class="badge <?= $r['is_active'] ? 'badge-paid' : 'badge-pending' ?>"><?= $r['is_active'] ? 'Visible' : 'Hidden' ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm btn-light" href="item-edit.php?type=<?= e($type) ?>&id=<?= (int) $r['id'] ?>">Edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-light"><?= $r['is_active'] ? 'Hide' : 'Show' ?></button></form>
                    <form method="post" data-confirm="Delete this item?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="form-actions"><button form="order-form" class="btn btn-light">Save order</button></div>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
