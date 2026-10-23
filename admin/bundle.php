<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$allBooks = db()->query('SELECT * FROM books ORDER BY sort_order, id')->fetchAll();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['bundle_name'] ?? ''));
    $price = max(0, (float) ($_POST['price'] ?? 0));
    $compare = max(0, (float) ($_POST['compare_price'] ?? 0));
    $chosen = array_map('intval', (array) ($_POST['books'] ?? []));
    if ($name === '') {
        $error = 'Bundle name is required.';
    } elseif ($chosen && $price < 0.5) {
        $error = 'Bundle price must be at least 0.50 (card payment minimum).';
    } else {
        save_setting('bundle_name', $name);
        save_setting('price', number_format($price, 2, '.', ''));
        save_setting('compare_price', number_format($compare, 2, '.', ''));
        db()->exec('UPDATE books SET in_bundle = 0');
        if ($chosen) {
            $in = implode(',', array_fill(0, count($chosen), '?'));
            db()->prepare("UPDATE books SET in_bundle = 1 WHERE id IN ($in)")->execute($chosen);
        }
        flash($chosen ? 'Bundle saved.' : 'Bundle saved with no books — it is hidden from the website until you add some.');
        redirect('bundle.php');
    }
}

$sym = setting('currency_symbol');
$adminTitle = 'Bundle';
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post" data-bundle-form>
    <?= csrf_field() ?>
    <p class="muted">Choose which books are in the bundle and its price. Buyers of the bundle get the selected books (as they are at the time of purchase) plus the bundle extra PDF from <a href="settings.php?group=delivery">Delivery</a>. Single books are always sold at their own price.</p>
    <div class="form-row form-row--split">
        <div><label for="bundle_name">Bundle name</label><input id="bundle_name" name="bundle_name" value="<?= e(setting('bundle_name')) ?>" required></div>
        <div class="form-row--split" style="display:grid;gap:12px">
            <div><label for="price">Bundle price (<?= e(strtoupper(setting('currency'))) ?>)</label><input type="number" step="0.01" min="0" id="price" name="price" value="<?= e(setting('price')) ?>" data-bundle-price></div>
            <div><label for="compare_price">Compare-at price (0 = total of the books)</label><input type="number" step="0.01" min="0" id="compare_price" name="compare_price" value="<?= e(setting('compare_price')) ?>"></div>
        </div>
    </div>

    <div class="form-row">
        <div class="panel__head" style="margin-bottom:8px">
            <label style="margin:0">Books in the bundle</label>
            <span>
                <button type="button" class="btn btn-sm btn-light" data-select="all">Select all</button>
                <button type="button" class="btn btn-sm btn-light" data-select="none">Clear</button>
            </span>
        </div>
        <?php if (!$allBooks): ?>
            <p class="muted">No books yet. <a href="book-edit.php">Add a book</a> first.</p>
        <?php else: ?>
        <div class="bundle-books">
            <?php foreach ($allBooks as $b): ?>
            <label class="bundle-book<?= $b['is_active'] ? '' : ' is-hidden' ?>">
                <input type="checkbox" name="books[]" value="<?= (int) $b['id'] ?>" data-price="<?= e((string) $b['price']) ?>" <?= (int) $b['in_bundle'] === 1 ? 'checked' : '' ?>>
                <?php if ($b['image']): ?><img src="<?= e(img_url($b['image'])) ?>" alt=""><?php endif; ?>
                <span><strong><?= e($b['title']) ?></strong><small><?= e($sym) . number_format((float) $b['price'], 2) ?><?= $b['is_active'] ? '' : ' · hidden book (not sold)' ?></small></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="bundle-total" data-bundle-summary></div>
    <div class="form-actions"><button class="btn">Save bundle</button></div>
</form>

<script>
(function () {
  var form = document.querySelector('[data-bundle-form]');
  var boxes = form.querySelectorAll('input[name="books[]"]');
  var priceInput = form.querySelector('[data-bundle-price]');
  var out = form.querySelector('[data-bundle-summary]');
  var sym = <?= json_encode($sym) ?>;
  function update() {
    var n = 0, total = 0;
    boxes.forEach(function (b) { if (b.checked) { n++; total += parseFloat(b.dataset.price) || 0; } });
    var price = parseFloat(priceInput.value) || 0;
    var save = total > 0 && price < total ? Math.round(100 - price / total * 100) : 0;
    out.textContent = n + ' book' + (n === 1 ? '' : 's') + ' selected · bought separately: ' + sym + total.toFixed(2)
      + ' · bundle price: ' + sym + price.toFixed(2) + (save ? ' · customers save ' + save + '%' : '');
  }
  boxes.forEach(function (b) { b.addEventListener('change', update); });
  priceInput.addEventListener('input', update);
  form.querySelectorAll('[data-select]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      boxes.forEach(function (b) { b.checked = btn.dataset.select === 'all'; });
      update();
    });
  });
  update();
})();
</script>
<?php require __DIR__ . '/_bottom.php'; ?>
