<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$page = ['title' => '', 'slug' => '', 'content' => '', 'sort_order' => 0, 'is_active' => 1];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM pages WHERE id = ?');
    $stmt->execute([$id]);
    $page = $stmt->fetch() ?: redirect('pages.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $page['title'] = trim((string) ($_POST['title'] ?? ''));
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')) ?: $page['title']);
    $page['slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    $page['content'] = (string) ($_POST['content'] ?? '');
    $page['sort_order'] = (int) ($_POST['sort_order'] ?? 0);
    $page['is_active'] = empty($_POST['is_active']) ? 0 : 1;

    $dupe = db()->prepare('SELECT COUNT(*) FROM pages WHERE slug = ? AND id <> ?');
    $dupe->execute([$page['slug'], $id]);
    if ($page['title'] === '' || $page['slug'] === '') {
        $error = 'Title is required.';
    } elseif ($dupe->fetchColumn()) {
        $error = 'Another page already uses this URL slug.';
    } else {
        $params = [$page['title'], $page['slug'], $page['content'], $page['sort_order'], $page['is_active']];
        if ($id) {
            db()->prepare('UPDATE pages SET title=?, slug=?, content=?, sort_order=?, is_active=? WHERE id=?')->execute([...$params, $id]);
        } else {
            db()->prepare('INSERT INTO pages (title, slug, content, sort_order, is_active) VALUES (?,?,?,?,?)')->execute($params);
        }
        flash('Page saved.');
        redirect('pages.php');
    }
}

$adminTitle = $id ? 'Edit page' : 'Add page';
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post">
    <?= csrf_field() ?>
    <div class="form-row"><label for="title">Title</label><input id="title" name="title" value="<?= e($page['title']) ?>" required></div>
    <div class="form-row"><label for="slug">URL slug (letters, numbers, dashes)</label><input id="slug" name="slug" value="<?= e($page['slug']) ?>" placeholder="auto from title"></div>
    <div class="form-row">
        <label for="content">Content (HTML allowed: &lt;p&gt;, &lt;h2&gt;, &lt;ul&gt;, &lt;a&gt; …)</label>
        <textarea id="content" name="content" rows="16" class="code"><?= e($page['content']) ?></textarea>
    </div>
    <div class="form-row form-row--split">
        <div><label for="sort_order">Sort order</label><input type="number" id="sort_order" name="sort_order" value="<?= (int) $page['sort_order'] ?>"></div>
        <div><label>Visibility</label><label class="switch"><input type="checkbox" name="is_active" value="1" <?= $page['is_active'] ? 'checked' : '' ?>><span>Published</span></label></div>
    </div>
    <div class="form-actions"><button class="btn">Save page</button> <a class="btn btn-light" href="pages.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
