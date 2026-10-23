<?php
require_once __DIR__ . '/includes/functions.php';

$stmt = db()->prepare('SELECT * FROM pages WHERE slug = ? AND is_active = 1');
$stmt->execute([(string) ($_GET['slug'] ?? '')]);
$page = $stmt->fetch();
if (!$page) {
    http_response_code(404);
    $page = ['title' => 'Page not found', 'content' => '<p>Sorry, this page does not exist.</p>'];
}
$pageTitle = $page['title'] . ' — ' . setting('site_name');
require __DIR__ . '/includes/header.php';
?>
<section class="page-content">
    <div class="container narrow">
        <div class="card">
            <h1><?= e($page['title']) ?></h1>
            <?php // Page content is HTML written by the site admin in the admin panel. ?>
            <div class="prose"><?= $page['content'] ?></div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
