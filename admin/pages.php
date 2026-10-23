<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    db()->prepare('DELETE FROM pages WHERE id = ?')->execute([(int) $_POST['id']]);
    flash('Page deleted.');
    redirect('pages.php');
}
$rows = db()->query('SELECT * FROM pages ORDER BY sort_order, id')->fetchAll();

$adminTitle = 'Pages';
require __DIR__ . '/_top.php';
?>
<div class="panel">
    <div class="panel__head">
        <p class="muted" style="margin:0">Footer pages such as Contact, Refund Policy and Privacy Policy.</p>
        <a class="btn" href="page-edit.php">+ Add page</a>
    </div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Title</th><th>URL</th><th>Status</th><th style="width:220px"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $p): $link = url('page.php?slug=' . urlencode($p['slug'])); ?>
            <tr>
                <td><strong><?= e($p['title']) ?></strong></td>
                <td><a href="<?= e($link) ?>" target="_blank"><?= e($p['slug']) ?></a></td>
                <td><span class="badge <?= $p['is_active'] ? 'badge-paid' : 'badge-pending' ?>"><?= $p['is_active'] ? 'Published' : 'Hidden' ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm btn-light" href="page-edit.php?id=<?= (int) $p['id'] ?>">Edit</a>
                    <form method="post" data-confirm="Delete this page?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/_bottom.php'; ?>
