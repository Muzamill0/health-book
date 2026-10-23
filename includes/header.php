<?php
/** @var string $pageTitle */
referral_capture();
$pageTitle = $pageTitle ?? setting('site_name');
$messages = array_values(array_filter(array_map('trim', explode("\n", ts('announcement_text')))));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e(ts('meta_description')) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(img_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('assets/css/style.css')) ?>">
<style>
:root{
  --primary: <?= e(ts('color_primary')) ?>;
  --dark: <?= e(ts('color_dark')) ?>;
  --sage: <?= e(ts('color_sage')) ?>;
  --cream: <?= e(ts('color_cream')) ?>;
}
</style>
</head>
<body>
<?php $showTimer = setting('topbar_timer_enabled') === '1'; ?>
<?php if (setting('announcement_enabled') === '1' && ($messages || $showTimer)): ?>
<div class="announcement" data-rotate>
    <?php if ($messages): ?>
    <span class="announcement__msgs">
        <?php foreach ($messages as $i => $m): ?>
            <span class="announcement__msg<?= $i === 0 ? ' is-active' : '' ?>"><?= e($m) ?></span>
        <?php endforeach; ?>
    </span>
    <?php endif; ?>
    <?php if ($showTimer): ?>
    <span class="topbar-timer" data-topbar-timer>
        <?= e(ts('topbar_timer_text')) ?>
        <b data-t-h>01</b>h <b data-t-m>00</b>m <b data-t-s>00</b>s
    </span>
    <?php endif; ?>
</div>
<?php endif; ?>
<header class="site-header">
    <div class="container header__inner">
        <a class="logo" href="<?= e(url()) ?>">
            <?php if (setting('logo')): ?>
                <img src="<?= e(img_url(setting('logo'))) ?>" alt="<?= e(ts('site_name')) ?>">
            <?php else: ?>
                <?= e(ts('site_name')) ?>
            <?php endif; ?>
        </a>
        <button class="nav-toggle" aria-label="Menu" aria-expanded="false" data-nav-toggle>☰</button>
        <nav class="nav" data-nav>
            <a href="<?= e(url('#features')) ?>">Why It Works</a>
            <a href="<?= e(url('#inside')) ?>">What’s Inside</a>
            <a href="<?= e(url('#bonuses')) ?>">Bonuses</a>
            <a href="<?= e(url('#reviews')) ?>">Reviews</a>
            <a href="<?= e(url('#faq')) ?>">FAQ</a>
            <?php $headerCart = cart_get(); $headerCount = $headerCart['bundle'] ? count(bundle_books()) : count($headerCart['books']); ?>
            <a class="cart-link" href="<?= e(url('checkout.php')) ?>" aria-label="Cart">🛒<span class="cart-link__count" data-header-cart-count<?= $headerCount ? '' : ' hidden' ?>><?= $headerCount ?></span></a>
            <a class="btn btn-sm" href="<?= e(url('checkout.php?bundle=1')) ?>"><?= e(ts('nav_cta_text')) ?></a>
        </nav>
    </div>
</header>
<main>
