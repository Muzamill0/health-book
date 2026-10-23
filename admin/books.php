<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    switch ($_POST['action'] ?? '') {
        case 'toggle':
            db()->prepare('UPDATE books SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
            flash('Visibility updated.');
            break;
        case 'order':
            $stmt = db()->prepare('UPDATE books SET sort_order = ? WHERE id = ?');
            foreach ((array) ($_POST['sort'] ?? []) as $bookId => $pos) {
                $stmt->execute([(int) $pos, (int) $bookId]);
            }
            flash('Order saved.');
            break;
        case 'delete':
            $sold = db()->prepare('SELECT COUNT(*) FROM order_items WHERE book_id = ?');
            $sold->execute([$id]);
            if ($sold->fetchColumn()) {
                flash('This book has been sold, so it can’t be deleted (buyers still need its PDF). Hide it instead.', 'error');
                break;
            }
            $stmt = db()->prepare('SELECT pdf_file FROM books WHERE id = ?');
            $stmt->execute([$id]);
            $pdf = (string) $stmt->fetchColumn();
            db()->prepare('DELETE FROM books WHERE id = ?')->execute([$id]);
            if ($pdf !== '' && is_file(storage_dir() . '/' . basename($pdf))) {
                unlink(storage_dir() . '/' . basename($pdf));
            }
            flash('Book deleted.');
            break;
    }
    redirect('books.php');
}

$rows = db()->query("SELECT b.*,
        (SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.book_id = b.id AND o.status = 'paid') AS sold
    FROM books b ORDER BY b.sort_order, b.id")->fetchAll();
$missingPdf = count(array_filter($rows, fn ($b) => $b['is_active'] && ($b['pdf_file'] === '' || !is_file(storage_dir() . '/' . basename($b['pdf_file'])))));

$adminTitle = 'Books';
require __DIR__ . '/_top.php';
?>
<?php if ($missingPdf): ?>
    <div class="alert warning"><?= $missingPdf ?> visible book<?= $missingPdf === 1 ? ' has' : 's have' ?> no PDF yet — buyers will see “Coming soon” for <?= $missingPdf === 1 ? 'it' : 'them' ?>. Click Edit to upload.</div>
<?php endif; ?>
<div class="panel">
    <div class="panel__head">
        <p class="muted" style="margin:0">Every visible book can be bought on its own at its own price. Choose the bundle’s books and price on the <a href="bundle.php">Bundle</a> page.</p>
        <a class="btn" href="book-edit.php">+ Add book</a>
    </div>
    <?php if (!$rows): ?>
        <p class="muted">No books yet.</p>
    <?php else: ?>
    <form method="post" id="order-form"><?= csrf_field() ?><input type="hidden" name="action" value="order"></form>
    <div class="table-wrap">
    <table>
        <thead><tr><th style="width:80px">Order</th><th style="width:60px"></th><th>Book</th><th>Price</th><th>PDF</th><th>In bundle</th><th>Sold</th><th>Status</th><th style="width:220px"></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $b):
            $hasPdf = $b['pdf_file'] !== '' && is_file(storage_dir() . '/' . basename($b['pdf_file'])); ?>
            <tr class="<?= $b['is_active'] ? '' : 'is-hidden' ?>">
                <td><input form="order-form" class="sort-input" type="number" name="sort[<?= (int) $b['id'] ?>]" value="<?= (int) $b['sort_order'] ?>"></td>
                <td><?php if ($b['image']): ?><img class="thumb" src="<?= e(img_url($b['image'])) ?>" alt=""><?php endif; ?></td>
                <td><strong><?= e($b['title']) ?></strong><?php if ($b['category']): ?><br><small class="muted"><?= e($b['category']) ?></small><?php endif; ?></td>
                <td><?= e(money((float) $b['price'])) ?><?php if ((float) $b['compare_price'] > (float) $b['price']): ?><br><small class="muted"><s><?= e(money((float) $b['compare_price'])) ?></s></small><?php endif; ?></td>
                <td><?= $hasPdf ? '<span class="badge badge-paid">Uploaded</span>' : '<span class="badge badge-failed">Missing</span>' ?></td>
                <td><?= (int) $b['in_bundle'] === 1 ? '🎁 Yes' : '<span class="muted">—</span>' ?></td>
                <td><?= (int) $b['sold'] ?></td>
                <td><span class="badge <?= $b['is_active'] ? 'badge-paid' : 'badge-pending' ?>"><?= $b['is_active'] ? 'Visible' : 'Hidden' ?></span></td>
                <td class="actions">
                    <a class="btn btn-sm btn-light" href="book-edit.php?id=<?= (int) $b['id'] ?>">Edit</a>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-light"><?= $b['is_active'] ? 'Hide' : 'Show' ?></button></form>
                    <?php if (!(int) $b['sold']): ?>
                    <form method="post" data-confirm="Delete this book and its PDF?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
                    <?php endif; ?>
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
