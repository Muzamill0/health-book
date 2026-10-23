<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$book = ['title' => '', 'category' => '', 'description' => '', 'image' => '', 'price' => '4.99', 'compare_price' => '', 'pdf_file' => '', 'in_bundle' => 0, 'sort_order' => 0, 'is_active' => 1];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM books WHERE id = ?');
    $stmt->execute([$id]);
    $book = $stmt->fetch() ?: redirect('books.php');
} else {
    $book['sort_order'] = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM books')->fetchColumn();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $book['title'] = trim((string) ($_POST['title'] ?? ''));
    $book['category'] = trim((string) ($_POST['category'] ?? ''));
    $book['description'] = trim((string) ($_POST['description'] ?? ''));
    $book['price'] = number_format(max(0, (float) ($_POST['price'] ?? 0)), 2, '.', '');
    $compare = (float) ($_POST['compare_price'] ?? 0);
    $book['compare_price'] = $compare > 0 ? number_format($compare, 2, '.', '') : null;
    $book['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $book['is_active'] = empty($_POST['is_active']) ? 0 : 1;
    $book['in_bundle'] = empty($_POST['in_bundle']) ? 0 : 1;
    $oldPdf = $book['pdf_file'];
    try {
        if ($book['title'] === '') {
            throw new RuntimeException('Title is required.');
        }
        if ((float) $book['price'] < 0.5) {
            throw new RuntimeException('Price must be at least 0.50 (card payment minimum).');
        }
        if (!empty($_POST['image_remove'])) {
            $book['image'] = '';
        }
        if (($path = handle_upload('image')) !== null) {
            $book['image'] = $path;
        }
        if (!empty($_POST['pdf_remove'])) {
            $book['pdf_file'] = '';
        }
        if (($pdf = handle_private_upload('pdf')) !== null) {
            $book['pdf_file'] = $pdf;
        }
        $params = [$book['title'], $book['category'], $book['description'], $book['image'], $book['price'], $book['compare_price'], $book['pdf_file'], $book['in_bundle'], $book['sort_order'], $book['is_active']];
        if ($id) {
            db()->prepare('UPDATE books SET title=?, category=?, description=?, image=?, price=?, compare_price=?, pdf_file=?, in_bundle=?, sort_order=?, is_active=? WHERE id=?')
                ->execute([...$params, $id]);
        } else {
            db()->prepare('INSERT INTO books (title, category, description, image, price, compare_price, pdf_file, in_bundle, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?)')
                ->execute($params);
        }
        if ($oldPdf !== '' && $oldPdf !== $book['pdf_file'] && is_file(storage_dir() . '/' . basename($oldPdf))) {
            unlink(storage_dir() . '/' . basename($oldPdf));
        }
        flash('Book saved.');
        redirect('books.php');
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$pdfPath = $book['pdf_file'] !== '' ? storage_dir() . '/' . basename($book['pdf_file']) : '';
$adminTitle = $id ? 'Edit book' : 'Add book';
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-row form-row--split">
        <div><label for="title">Title</label><input id="title" name="title" value="<?= e($book['title']) ?>" required></div>
        <div><label for="category">Category</label><input id="category" name="category" value="<?= e($book['category']) ?>" placeholder="e.g. Breakfast &amp; Brunch"></div>
    </div>
    <div class="form-row"><label for="description">Short description</label><textarea id="description" name="description" rows="3"><?= e($book['description']) ?></textarea></div>
    <div class="form-row form-row--split">
        <div><label for="price">Price (<?= e(strtoupper(setting('currency'))) ?>)</label><input type="number" step="0.01" min="0.5" id="price" name="price" value="<?= e((string) $book['price']) ?>" required></div>
        <div><label for="compare_price">Compare-at price (optional, shown crossed out)</label><input type="number" step="0.01" min="0" id="compare_price" name="compare_price" value="<?= e((string) $book['compare_price']) ?>"></div>
    </div>
    <div class="form-row">
        <label for="image">Cover image</label>
        <div class="image-field">
            <img id="prev-image" src="<?= e(img_url($book['image'])) ?>" alt="" <?= $book['image'] === '' ? 'hidden' : '' ?>>
            <div>
                <input type="file" id="image" name="image" accept="image/*" data-preview="prev-image">
                <?php if ($book['image'] !== ''): ?><label class="inline"><input type="checkbox" name="image_remove" value="1"> Remove image</label><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="form-row">
        <label for="pdf">Book PDF (private — only buyers of this book or the complete bundle can download it)</label>
        <div class="image-field"><div>
            <?php if ($pdfPath !== '' && is_file($pdfPath)): ?>
                <p style="margin:0">📄 PDF uploaded — <?= e(number_format(filesize($pdfPath) / 1048576, 2)) ?> MB, <?= e(date('M j, Y H:i', filemtime($pdfPath))) ?></p>
                <label class="inline"><input type="checkbox" name="pdf_remove" value="1"> Remove PDF</label>
            <?php else: ?>
                <p class="muted" style="margin:0">No PDF uploaded yet.</p>
            <?php endif; ?>
            <input type="file" id="pdf" name="pdf" accept="application/pdf,.pdf">
            <small class="muted">Max size: <?= e(ini_get('upload_max_filesize')) ?>. Uploading a new PDF replaces the old one.</small>
        </div></div>
    </div>
    <div class="form-row form-row--split">
        <div><label for="sort_order">Sort order (lower shows first)</label><input type="number" id="sort_order" name="sort_order" value="<?= (int) $book['sort_order'] ?>"></div>
        <div><label>Visibility</label><label class="switch"><input type="checkbox" name="is_active" value="1" <?= $book['is_active'] ? 'checked' : '' ?>><span>Show on website</span></label>
            <label class="switch" style="margin-top:8px"><input type="checkbox" name="in_bundle" value="1" <?= $book['in_bundle'] ? 'checked' : '' ?>><span>Include in the bundle (<a href="bundle.php">manage bundle</a>)</span></label></div>
    </div>
    <div class="form-actions"><button class="btn">Save book</button> <a class="btn btn-light" href="books.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
