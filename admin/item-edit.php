<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$types = require __DIR__ . '/../includes/item_types.php';
$type = $_GET['type'] ?? '';
if (!isset($types[$type])) {
    redirect('dashboard.php');
}
$cfg = $types[$type];
$id = (int) ($_GET['id'] ?? 0);

$item = ['title' => '', 'subtitle' => '', 'body' => '', 'image' => '', 'icon' => '', 'value' => '', 'sort_order' => 0, 'is_active' => 1];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM items WHERE id = ? AND type = ?');
    $stmt->execute([$id, $type]);
    $item = $stmt->fetch() ?: redirect('items.php?type=' . urlencode($type));
} else {
    $stmt = db()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM items WHERE type = ?');
    $stmt->execute([$type]);
    $item['sort_order'] = (int) $stmt->fetchColumn();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['title', 'subtitle', 'body', 'icon', 'value'] as $col) {
        if (isset($cfg['fields'][$col])) {
            $item[$col] = trim((string) ($_POST[$col] ?? ''));
        }
    }
    $item['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $item['is_active'] = empty($_POST['is_active']) ? 0 : 1;
    try {
        if (isset($cfg['fields']['image'])) {
            if (!empty($_POST['image_remove'])) {
                $item['image'] = '';
            }
            if (($path = handle_upload('image')) !== null) {
                $item['image'] = $path;
            }
        }
        if ($item['title'] === '') {
            throw new RuntimeException($cfg['fields']['title'] . ' is required.');
        }
        $params = [$item['title'], $item['subtitle'], $item['body'], $item['image'], $item['icon'], $item['value'], $item['sort_order'], $item['is_active']];
        if ($id) {
            db()->prepare('UPDATE items SET title=?, subtitle=?, body=?, image=?, icon=?, value=?, sort_order=?, is_active=? WHERE id=? AND type=?')
                ->execute([...$params, $id, $type]);
        } else {
            db()->prepare('INSERT INTO items (title, subtitle, body, image, icon, value, sort_order, is_active, type) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([...$params, $type]);
        }
        flash('Saved.');
        redirect('items.php?type=' . urlencode($type));
    } catch (RuntimeException $ex) {
        $error = $ex->getMessage();
    }
}

$adminTitle = ($id ? 'Edit' : 'Add') . ' — ' . $cfg['label'];
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php foreach ($cfg['fields'] as $col => $label): ?>
        <div class="form-row">
            <label for="<?= e($col) ?>"><?= e($label) ?></label>
            <?php if ($col === 'body'): ?>
                <textarea id="body" name="body" rows="5"><?= e($item['body']) ?></textarea>
            <?php elseif ($col === 'image'): ?>
                <div class="image-field">
                    <img id="prev-image" src="<?= e(img_url($item['image'])) ?>" alt="" <?= $item['image'] === '' ? 'hidden' : '' ?>>
                    <div>
                        <input type="file" id="image" name="image" accept="image/*" data-preview="prev-image">
                        <?php if ($item['image'] !== ''): ?><label class="inline"><input type="checkbox" name="image_remove" value="1"> Remove image</label><?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <input type="text" id="<?= e($col) ?>" name="<?= e($col) ?>" value="<?= e($item[$col]) ?>" <?= $col === 'title' ? 'required' : '' ?>>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="form-row form-row--split">
        <div>
            <label for="sort_order">Sort order (lower shows first)</label>
            <input type="number" id="sort_order" name="sort_order" value="<?= (int) $item['sort_order'] ?>">
        </div>
        <div>
            <label>Visibility</label>
            <label class="switch"><input type="checkbox" name="is_active" value="1" <?= $item['is_active'] ? 'checked' : '' ?>><span>Show on website</span></label>
        </div>
    </div>
    <div class="form-actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="items.php?type=<?= e($type) ?>">Cancel</a>
    </div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
