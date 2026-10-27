<?php
require_once __DIR__ . '/includes/functions.php';
if (!file_exists(__DIR__ . '/config/installed.lock')) {
    redirect(url('install.php'));
}

$price = (float) setting('price');
$compare = (float) setting('compare_price');
$off = discount_percent();
$highlights = items('highlight');
$features = items('feature');
$books = books();
$cart = cart_summary();
$inCart = $cart['type'] === 'books' ? array_map('intval', array_column($cart['items'], 'id')) : [];
$bundleOn = bundle_available();
$bundleCount = count(bundle_books());
$bonuses = items('bonus');
$testimonials = items('testimonial');
$faqs = items('faq');
$gallery = items('gallery');

$countdownEnd = '';
if (setting('countdown_enabled') === '1') {
    $countdownEnd = setting('countdown_mode') === 'fixed' && setting('countdown_end') !== ''
        ? date('c', strtotime(ts('countdown_end')))
        : 'daily';
}

require __DIR__ . '/includes/header.php';
?>

<!-- Product hero -->
<section class="hero">
    <div class="container hero__grid">
        <div class="hero__media">
            <div class="hero__image">
                <?php if ($off): ?><span class="sale-badge">-<?= $off ?>%</span><?php endif; ?>
                <img src="<?= e(img_url(setting('product_image'))) ?>" alt="<?= e(ts('product_title')) ?>" data-main-image>
            </div>
            <?php if ($gallery): ?>
            <div class="hero__thumbs">
                <button type="button" class="thumb is-active" data-thumb="<?= e(img_url(setting('product_image'))) ?>"><img src="<?= e(img_url(setting('product_image'))) ?>" alt=""></button>
                <?php foreach ($gallery as $g): ?>
                    <button type="button" class="thumb" data-thumb="<?= e(img_url($g['image'])) ?>"><img src="<?= e(img_url($g['image'])) ?>" alt="<?= e($g['title']) ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="hero__info">
            <?php if (setting('product_badge')): ?><span class="eyebrow"><?= e(ts('product_badge')) ?></span><?php endif; ?>
            <h1><?= e(ts('product_title')) ?></h1>
            <div class="rating">
                <span class="stars"><?= stars((float) setting('product_rating')) ?></span>
                <span><?= e(ts('product_rating')) ?></span>
                <span class="muted">· <?= e(ts('product_reviews')) ?></span>
            </div>
            <div class="price-row">
                <span class="price"><?= e(money($price)) ?></span>
                <?php if ($compare > $price): ?>
                    <span class="compare"><?= e(money($compare)) ?></span>
                    <span class="save">Save <?= e(money($compare - $price)) ?></span>
                <?php endif; ?>
            </div>
            <p class="lead"><?= nl2br(e(ts('product_subtitle'))) ?></p>

            <?php if ($highlights): ?>
            <ul class="checklist">
                <?php foreach ($highlights as $h): ?>
                    <li><span><?= e($h['icon'] ?: '✓') ?></span><?= e(fill($h['title'])) ?></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <?php if ($countdownEnd): ?>
            <div class="countdown" data-countdown="<?= e($countdownEnd) ?>">
                <span class="countdown__label"><?= e(ts('countdown_label')) ?></span>
                <div class="countdown__units">
                    <div><b data-h>00</b><small>Hours</small></div><span>:</span>
                    <div><b data-m>00</b><small>Mins</small></div><span>:</span>
                    <div><b data-s>00</b><small>Secs</small></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($bundleOn): ?>
            <a class="btn btn-lg btn-block" href="<?= e(url('checkout.php?bundle=1')) ?>"><?= e(ts('buy_button_text')) ?> — <?= e(money($price)) ?></a>
            <?php else: ?>
            <a class="btn btn-lg btn-block" href="<?= e(url('#inside')) ?>">Choose your cookbooks</a>
            <?php endif; ?>
            <p class="guarantee">🔒 <?= e(ts('guarantee_text')) ?></p>
            <p class="format-note"><?= e(ts('format_note')) ?></p>
        </div>
    </div>
</section>

<?php if ($features && setting('features_enabled') === '1'): ?>
<section class="section section--sage" id="features">
    <div class="container">
        <div class="section__head">
            <h2><?= e(ts('features_heading')) ?></h2>
            <p><?= e(ts('features_subheading')) ?></p>
        </div>
        <div class="grid grid-3">
            <?php foreach ($features as $f): ?>
            <article class="card feature">
                <?php if ($f['image']): ?>
                    <img class="feature__img" src="<?= e(img_url($f['image'])) ?>" alt="">
                <?php elseif ($f['icon']): ?>
                    <div class="feature__icon"><?= e($f['icon']) ?></div>
                <?php endif; ?>
                <h3><?= e(fill($f['title'])) ?></h3>
                <p><?= nl2br(e(fill($f['body']))) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($books): ?>
<section class="section" id="inside">
    <div class="container">
        <div class="section__head">
            <h2><?= e(ts('books_heading')) ?></h2>
            <p><?= e(ts('books_subheading')) ?></p>
        </div>
        <div class="shop-bar">
            <p><?= e(ts('cart_hint_text')) ?></p>
            <?php if ($bundleOn): ?>
            <a class="btn" href="<?= e(url('checkout.php?bundle=1')) ?>">Get the <?= e(setting('bundle_name')) ?> (<?= $bundleCount ?> books) — <?= e(money($price)) ?></a>
            <?php endif; ?>
        </div>
        <div class="grid grid-5 books">
            <?php foreach ($books as $i => $b):
                $added = in_array((int) $b['id'], $inCart, true); ?>
            <article class="book<?= $added ? ' is-added' : '' ?>" data-book="<?= (int) $b['id'] ?>">
                <div class="book__cover">
                    <span class="book__num"><?= $i + 1 ?></span>
                    <?php if ($b['image']): ?><img src="<?= e(img_url($b['image'])) ?>" alt="<?= e($b['title']) ?>" loading="lazy"><?php endif; ?>
                </div>
                <?php if ($bundleOn && (int) $b['in_bundle'] === 1): ?><span class="book__bundle">In bundle</span><?php endif; ?>
                <?php if ($b['category']): ?><span class="book__cat"><?= e($b['category']) ?></span><?php endif; ?>
                <h3><?= e($b['title']) ?></h3>
                <p><?= e($b['description']) ?></p>
                <div class="book__price">
                    <strong><?= e(money((float) $b['price'])) ?></strong>
                    <?php if ((float) $b['compare_price'] > (float) $b['price']): ?><s><?= e(money((float) $b['compare_price'])) ?></s><?php endif; ?>
                </div>
                <button type="button" class="btn btn-sm book__add" data-cart-toggle="<?= (int) $b['id'] ?>" aria-pressed="<?= $added ? 'true' : 'false' ?>">
                    <span class="when-off">+ <?= e(ts('add_to_cart_text')) ?></span><span class="when-on">✓ Added</span>
                </button>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($bonuses && setting('bonuses_enabled') === '1'): ?>
<section class="section section--dark" id="bonuses">
    <div class="container">
        <div class="section__head">
            <h2><?= e(ts('bonuses_heading')) ?></h2>
            <p><?= e(ts('bonuses_subheading')) ?></p>
        </div>
        <div class="grid grid-2 bonuses">
            <?php foreach ($bonuses as $i => $b): ?>
            <article class="bonus">
                <div class="bonus__icon">
                    <?php if ($b['image']): ?><img src="<?= e(img_url($b['image'])) ?>" alt=""><?php else: ?><?= e($b['icon'] ?: '🎁') ?><?php endif; ?>
                </div>
                <div>
                    <span class="bonus__tag">Bonus #<?= $i + 1 ?><?php if ($b['value']): ?> · <s><?= e($b['value']) ?></s> FREE<?php endif; ?></span>
                    <h3><?= e($b['title']) ?></h3>
                    <p><?= e($b['body']) ?></p>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (setting('comparison_enabled') === '1' && (setting('comparison_before_image') || setting('comparison_after_image'))):
    $compareLink = setting('comparison_button_link') !== '' ? setting('comparison_button_link') : url('checkout.php?bundle=1'); ?>
<section class="section comparison" id="results">
    <div class="container comparison__grid">
        <div class="comparison__images">
            <?php foreach (['before', 'after'] as $side):
                $img = setting('comparison_' . $side . '_image');
                $label = ts('comparison_' . $side . '_label');
                if ($img === '') continue; ?>
            <figure class="comparison__img">
                <img src="<?= e(img_url($img)) ?>" alt="<?= e($label ?: ucfirst($side)) ?>" loading="lazy">
                <?php if ($label !== ''): ?><figcaption class="comparison__label comparison__label--<?= $side ?>"><?= e($label) ?></figcaption><?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
        <div class="comparison__text">
            <?php if (ts('comparison_heading') !== ''): ?><h2><?= e(ts('comparison_heading')) ?></h2><?php endif; ?>
            <?= rich_text(ts('comparison_text')) ?>
            <?php if (setting('comparison_button_text') !== ''): ?>
                <a class="btn btn-lg" href="<?= e($compareLink) ?>"><?= e(ts('comparison_button_text')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section value">
    <div class="container narrow center">
        <h2><?= e(ts('value_heading')) ?></h2>
        <p class="lead"><?= nl2br(e(ts('value_text'))) ?></p>
        <div class="value__box">
            <span class="value__total"><s><?= e(ts('value_total')) ?></s></span>
            <span class="value__today">Today only: <strong><?= e(money($price)) ?></strong></span>
            <?php if ($bundleOn): ?>
            <a class="btn btn-lg" href="<?= e(url('checkout.php?bundle=1')) ?>"><?= e(ts('buy_button_text')) ?></a>
            <?php else: ?>
            <a class="btn btn-lg" href="<?= e(url('#inside')) ?>">Choose your cookbooks</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php if ($testimonials): ?>
<section class="section section--sage" id="reviews">
    <div class="container">
        <div class="section__head">
            <h2><?= e(ts('testimonials_heading')) ?></h2>
            <div class="rating rating--center"><span class="stars"><?= stars((float) setting('product_rating')) ?></span> <?= e(ts('product_rating')) ?> · <?= e(ts('product_reviews')) ?></div>
        </div>
        <div class="grid grid-3">
            <?php foreach ($testimonials as $t): ?>
            <figure class="card review">
                <span class="stars"><?= stars((float) ($t['value'] ?: 5)) ?></span>
                <blockquote><?= nl2br(e($t['body'])) ?></blockquote>
                <figcaption>
                    <?php if ($t['image']): ?><img src="<?= e(img_url($t['image'])) ?>" alt=""><?php else: ?><span class="avatar"><?= e(mb_substr($t['title'], 0, 1)) ?></span><?php endif; ?>
                    <div><strong><?= e($t['title']) ?></strong><small><?= e($t['subtitle']) ?></small></div>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="section" id="faq">
    <div class="container narrow">
        <div class="section__head"><h2><?= e(ts('faq_heading')) ?></h2></div>
        <div class="faq">
            <?php foreach ($faqs as $q): ?>
            <details>
                <summary><?= e(fill($q['title'])) ?></summary>
                <div class="faq__body"><?= nl2br(e(fill($q['body']))) ?></div>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="cta">
    <div class="container narrow center">
        <h2><?= e(ts('cta_heading')) ?></h2>
        <p><?= e(ts('cta_text')) ?></p>
        <?php if ($bundleOn): ?>
        <a class="btn btn-lg btn-light" href="<?= e(url('checkout.php?bundle=1')) ?>"><?= e(ts('buy_button_text')) ?> — <?= e(money($price)) ?></a>
        <?php else: ?>
        <a class="btn btn-lg btn-light" href="<?= e(url('#inside')) ?>">Choose your cookbooks</a>
        <?php endif; ?>
    </div>
</section>

<div class="cart-bar<?= $cart['type'] === 'books' ? ' is-visible' : '' ?>" data-cart-bar aria-live="polite">
    <div class="container cart-bar__inner">
        <div class="cart-bar__info">
            <strong data-cart-count><?= $cart['count'] ?> book<?= $cart['count'] === 1 ? '' : 's' ?></strong>
            <span data-cart-total><?= e(money($cart['total'])) ?></span>
            <small class="cart-bar__hint" data-cart-hint></small>
        </div>
        <div class="cart-bar__actions">
            <button type="button" class="btn btn-sm btn-light" data-cart-clear>Clear</button>
            <a class="btn" href="<?= e(url('checkout.php')) ?>">Checkout →</a>
        </div>
    </div>
</div>
<?php
$extraScripts = '<script>window.CART = ' . json_encode(['url' => url('cart.php'), 'csrf' => csrf_token(), 'bundleUrl' => url('checkout.php?bundle=1')] + cart_public($cart), JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
    . '<script src="' . e(asset('assets/js/cart.js')) . '"></script>';
require __DIR__ . '/includes/footer.php';
?>
