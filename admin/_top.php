<?php
/** @var string $adminTitle */
require_admin();
$types = require __DIR__ . '/../includes/item_types.php';
$current = basename($_SERVER['SCRIPT_NAME']);
$currentType = $_GET['type'] ?? '';
$currentGroup = $_GET['group'] ?? '';
$flash = flash();
$nav = function (string $href, string $label, bool $active) {
    return '<a href="' . e($href) . '" class="' . ($active ? 'active' : '') . '">' . $label . '</a>';
};
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle ?? 'Admin') ?> · <?= e(setting('site_name')) ?> Admin</title>
<link rel="stylesheet" href="<?= e(url('admin/admin.css')) ?>">
</head>
<body>
<div class="admin">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand"><?= e(setting('site_name')) ?><small>Admin panel</small></div>
        <nav>
            <?= $nav('dashboard.php', '🏠 Dashboard', $current === 'dashboard.php') ?>
            <div class="nav-label">Website Settings</div>
            <?php foreach (settings_schema() as $key => $g):
                if ($key === 'pricing' || $key === 'bundle') continue; ?>
                <?= $nav('settings.php?group=' . $key, e($g['label']), $current === 'settings.php' && $currentGroup === $key) ?>
            <?php endforeach; ?>
            <div class="nav-label">Products</div>
            <?= $nav('books.php', '📚 Books', in_array($current, ['books.php', 'book-edit.php'], true)) ?>
            <?= $nav('bundle.php', '🎁 Bundle', $current === 'bundle.php') ?>
            <?= $nav('settings.php?group=pricing', '🏷 Currency &amp; Cart', $current === 'settings.php' && $currentGroup === 'pricing') ?>
            <div class="nav-label">Homepage Content</div>
            <?php foreach ($types as $key => $t): ?>
                <?= $nav('items.php?type=' . $key, e($t['icon'] . ' ' . $t['label']), in_array($current, ['items.php', 'item-edit.php'], true) && $currentType === $key) ?>
            <?php endforeach; ?>
            <?= $nav('pages.php', '📄 Pages', in_array($current, ['pages.php', 'page-edit.php'], true)) ?>
            <div class="nav-label">Sales</div>
            <?= $nav('orders.php', '🧾 Orders', $current === 'orders.php') ?>
            <?= $nav('referrers.php', '🔗 Referral Links', $current === 'referrers.php') ?>
            <?= $nav('subscribers.php', '✉️ Subscribers', $current === 'subscribers.php') ?>
            <?= $nav('stripe.php', '💳 Stripe Payments', $current === 'stripe.php') ?>
            <div class="nav-label">Account</div>
            <?= $nav('account.php', '🔑 Change Password', $current === 'account.php') ?>
            <a href="<?= e(url()) ?>" target="_blank">↗ View Website</a>
            <a href="logout.php">⎋ Log out</a>
        </nav>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
            <h1><?= e($adminTitle ?? 'Admin') ?></h1>
        </header>
        <div class="content">
            <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
