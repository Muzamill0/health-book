<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$schema = settings_schema();
$group = $_GET['group'] ?? 'general';
if ($group === 'bundle') {
    redirect('bundle.php');
}
if (!isset($schema[$group])) {
    $group = 'general';
}
$fields = $schema[$group]['fields'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        foreach ($fields as $key => $f) {
            switch ($f['type']) {
                case 'image':
                    if (!empty($_POST[$key . '_remove'])) {
                        save_setting($key, '');
                    }
                    if (($path = handle_upload($key)) !== null) {
                        save_setting($key, $path);
                    }
                    break;
                case 'file':
                    $old = setting($key);
                    $new = handle_private_upload($key);
                    if ($new !== null || !empty($_POST[$key . '_remove'])) {
                        save_setting($key, $new ?? '');
                        if ($old !== '' && is_file(storage_dir() . '/' . basename($old))) {
                            unlink(storage_dir() . '/' . basename($old));
                        }
                    }
                    break;
                case 'toggle':
                    save_setting($key, empty($_POST[$key]) ? '0' : '1');
                    break;
                case 'number':
                    save_setting($key, number_format(max(0, (float) ($_POST[$key] ?? 0)), 2, '.', ''));
                    break;
                case 'color':
                    $v = (string) ($_POST[$key] ?? '');
                    if (preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                        save_setting($key, $v);
                    }
                    break;
                default:
                    save_setting($key, trim((string) ($_POST[$key] ?? '')));
            }
        }
        flash('Settings saved.');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    }
    redirect('settings.php?group=' . urlencode($group));
}

$adminTitle = $schema[$group]['label'];
require __DIR__ . '/_top.php';
?>
<form class="panel form" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php foreach ($fields as $key => $f):
        $val = setting($key); ?>
        <div class="form-row">
            <label for="<?= e($key) ?>"><?= e($f['label']) ?></label>
            <?php switch ($f['type']):
                case 'textarea': ?>
                    <textarea id="<?= e($key) ?>" name="<?= e($key) ?>" rows="4"><?= e($val) ?></textarea>
                <?php break; case 'toggle': ?>
                    <label class="switch"><input type="checkbox" id="<?= e($key) ?>" name="<?= e($key) ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>><span>Enabled</span></label>
                <?php break; case 'color': ?>
                    <input type="color" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>">
                <?php break; case 'number': ?>
                    <input type="number" step="0.01" min="0" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>">
                <?php break; case 'datetime': ?>
                    <input type="datetime-local" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>">
                <?php break; case 'file':
                    $stored = $val !== '' ? storage_dir() . '/' . basename($val) : ''; ?>
                    <div class="image-field">
                        <div>
                            <?php if ($stored !== '' && is_file($stored)): ?>
                                <p style="margin:0">📄 PDF uploaded — <?= e(number_format(filesize($stored) / 1048576, 2)) ?> MB, <?= e(date('M j, Y H:i', filemtime($stored))) ?></p>
                                <label class="inline"><input type="checkbox" name="<?= e($key) ?>_remove" value="1"> Remove PDF</label>
                            <?php else: ?>
                                <p class="muted" style="margin:0">No PDF uploaded yet.</p>
                            <?php endif; ?>
                            <input type="file" id="<?= e($key) ?>" name="<?= e($key) ?>" accept="application/pdf,.pdf">
                            <small class="muted">Max size: <?= e(ini_get('upload_max_filesize')) ?>. Uploading a new PDF replaces the old one.</small>
                        </div>
                    </div>
                <?php break; case 'image': ?>
                    <div class="image-field">
                        <img id="prev-<?= e($key) ?>" src="<?= e(img_url($val)) ?>" alt="" <?= $val === '' ? 'hidden' : '' ?>>
                        <div>
                            <input type="file" id="<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*" data-preview="prev-<?= e($key) ?>">
                            <?php if ($val !== ''): ?><label class="inline"><input type="checkbox" name="<?= e($key) ?>_remove" value="1"> Remove image</label><?php endif; ?>
                        </div>
                    </div>
                <?php break; default: ?>
                    <input type="text" id="<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($val) ?>">
            <?php endswitch; ?>
        </div>
    <?php endforeach; ?>
    <div class="form-actions"><button class="btn" type="submit">Save changes</button></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
