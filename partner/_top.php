<?php
/** @var string $partnerTitle @var array $partner */
$flash = flash();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($partnerTitle ?? 'Partner') ?> · <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
</head>
<body class="partner-body">
<header class="partner-top">
    <div class="partner-wrap partner-top__inner">
        <strong><?= e(setting('site_name')) ?> <span class="muted">· Partner</span></strong>
        <nav>
            <span class="muted"><?= e($partner['name']) ?></span>
            <a href="dashboard.php">Dashboard</a>
            <a href="password.php">Change password</a>
            <a href="logout.php">Log out</a>
        </nav>
    </div>
</header>
<main class="partner-wrap">
    <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div><?php endif; ?>
