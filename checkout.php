<?php
require_once __DIR__ . '/includes/functions.php';

// Cart shortcuts used by buttons and shareable links: ?bundle=1, ?add=ID (?ref= is kept via referral_capture).
if (isset($_GET['bundle']) || isset($_GET['add'])) {
    referral_capture();
    $cart = cart_get();
    if (isset($_GET['bundle'])) {
        cart_set(true, []);
    } else {
        cart_set(false, [...$cart['books'], (int) $_GET['add']]);
    }
    redirect(url('checkout.php'));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $cart = cart_get();
    match ($_POST['action'] ?? '') {
        'remove' => cart_set(false, array_diff($cart['books'], [(int) ($_POST['id'] ?? 0)])),
        'bundle' => cart_set(true, []),
        'clear'  => cart_set(false, []),
        default  => null,
    };
    redirect(url('checkout.php'));
}

$summary = cart_summary();
$currency = strtolower(setting('currency', 'usd'));
$paddleReady = PADDLE_CLIENT_TOKEN !== '' && PADDLE_API_KEY !== '';
$isEmpty = $summary['type'] === 'empty';
$amountMinor = to_minor_units($summary['total'], $currency);

$pageTitle = 'Checkout — ' . setting('site_name');
$hideNewsletter = true;
$extraScripts = '';
if ($paddleReady && !$isEmpty) {
    $config = [
        'clientToken' => PADDLE_CLIENT_TOKEN,
        'sandbox'     => PADDLE_ENV !== 'production',
        'amount'      => $amountMinor,
        'createUrl'   => url('create-payment.php'),
        'csrf'        => csrf_token(),
    ];
    $extraScripts = '<script src="https://cdn.paddle.com/paddle/v2/paddle.js"></script>'
        . '<script>window.CHECKOUT = ' . json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
        . '<script src="' . e(asset('assets/js/checkout.js')) . '"></script>';
}
$showItems = $summary['type'] === 'bundle' ? array_slice($summary['items'], 0, 4) : $summary['items'];

require __DIR__ . '/includes/header.php';
?>
<section class="checkout">
    <div class="container">
        <div class="checkout__steps">
            <span>1. Choose books</span><span>›</span><span class="is-active">2. Checkout</span><span>›</span><span>3. Download</span>
        </div>

        <?php if ($isEmpty): ?>
        <div class="card empty-cart narrow" style="margin:0 auto">
            <h1>Your cart is empty</h1>
            <p class="muted">Pick the cookbooks you want<?= bundle_available() ? ', or save with the ' . e(setting('bundle_name')) : '' ?>.</p>
            <p>
                <?php if (bundle_available()): ?>
                <a class="btn" href="<?= e(url('checkout.php?bundle=1')) ?>"><?= e(setting('bundle_name')) ?> (<?= count(bundle_books()) ?> books) — <?= e(money((float) setting('price'))) ?></a>
                <?php endif; ?>
                <a class="btn btn-light" href="<?= e(url('#inside')) ?>">Choose books</a>
            </p>
        </div>
        <?php else: ?>
        <div class="checkout__grid">
            <div class="card checkout__form">
                <?php if (!$paddleReady): ?>
                    <div class="notice"><strong>Payments are not configured yet.</strong> Add your Paddle keys to the .env file.</div>
                <?php endif; ?>

                <form id="checkout-form" novalidate>
                    <input type="hidden" name="expected" value="<?= $amountMinor ?>">
                    <h2>Your details</h2>
                    <div class="field-row">
                        <div class="field">
                            <label for="first_name">First name <span class="req">*</span></label>
                            <input id="first_name" name="first_name" autocomplete="given-name" required>
                        </div>
                        <div class="field">
                            <label for="last_name">Last name <span class="req">*</span></label>
                            <input id="last_name" name="last_name" autocomplete="family-name" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="email">Email address <span class="req">*</span></label>
                        <input id="email" name="email" type="email" autocomplete="email" required aria-required="true" maxlength="190" placeholder="you@example.com">
                        <small class="field-hint">Required — your invoice and receipt are sent to this email.</small>
                    </div>

                    <button class="btn btn-lg btn-block" id="pay-btn" type="submit" style="margin-top:1rem" <?= $paddleReady ? '' : 'disabled' ?>>
                        🔒 Continue to payment — <?= e(money($summary['total'])) ?>
                    </button>
                </form>

                <div class="paddle-step" id="paddle-step" hidden>
                    <div class="paddle-step__head">
                        <h2>Payment</h2>
                        <button type="button" class="link-btn" id="edit-details">Edit details</button>
                    </div>
                    <p class="muted paddle-step__who" id="paddle-who"></p>
                    <div class="hb-paddle-checkout" id="paddle-checkout"></div>
                </div>
                <p class="pay-error" id="pay-error" role="alert"></p>
                <div class="secure-row">
                    <span>🔒 Secure checkout</span><span>💳 Payments by Paddle</span><span>⚡ Instant PDF download</span>
                </div>
                <p class="field-hint" style="text-align:center">Applicable sales tax/VAT is calculated by Paddle based on your country.</p>
            </div>

            <aside class="card summary">
                <h2 style="font-size:1.2rem">Order summary</h2>
                <?php if ($summary['type'] === 'bundle'): ?>
                    <div class="summary__product">
                        <img src="<?= e(img_url(setting('product_image'))) ?>" alt="">
                        <div>
                            <h3><?= e(setting('bundle_name')) ?></h3>
                            <span class="muted" style="font-size:.85rem"><?= $summary['count'] ?> cookbooks + bonuses · Digital download</span>
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="cart-list">
                    <?php foreach ($showItems as $b): ?>
                    <li>
                        <?php if ($b['image']): ?><img src="<?= e(img_url($b['image'])) ?>" alt=""><?php endif; ?>
                        <div class="grow"><strong><?= e($b['title']) ?></strong><small><?= e($b['category']) ?></small></div>
                        <?php if ($summary['type'] === 'books'): ?>
                            <span><?= e(money((float) $b['price'])) ?></span>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>"><button class="cart-remove" title="Remove <?= e($b['title']) ?>" aria-label="Remove <?= e($b['title']) ?>">✕</button></form>
                        <?php else: ?>
                            <span class="muted">✓</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($summary['type'] === 'bundle' && $summary['count'] > count($showItems)): ?>
                    <p class="cart-more">+ <?= $summary['count'] - count($showItems) ?> more cookbooks and all bonuses</p>
                <?php endif; ?>

                <div class="summary__lines">
                    <div class="summary__line"><span><?= $summary['type'] === 'bundle' ? 'Value' : 'Subtotal (' . $summary['count'] . ' book' . ($summary['count'] === 1 ? '' : 's') . ')' ?></span><span><?= $summary['discount'] > 0 ? '<s>' . e(money($summary['subtotal'])) . '</s>' : e(money($summary['subtotal'])) ?></span></div>
                    <?php if ($summary['discount'] > 0): ?>
                        <div class="summary__line summary__line--discount"><span>Bundle discount (<?= e((string) (float) $summary['discount_pct']) ?>%)</span><span>-<?= e(money($summary['discount'])) ?></span></div>
                    <?php endif; ?>
                </div>
                <div class="summary__total"><span>Total</span><span><?= e(money($summary['total'])) ?> <small class="muted" style="font-weight:500;font-size:.8rem"><?= e(strtoupper($currency)) ?></small></span></div>

                <?php if ($summary['type'] === 'books'): ?>
                    <?php if ($summary['bundle_better']): ?>
                        <div class="upsell">Better deal: the <strong><?= e(setting('bundle_name')) ?></strong> has <?= count(bundle_books()) ?> books + bonuses for <?= e(money((float) setting('price'))) ?>.
                            <form method="post" style="margin-top:.5rem"><?= csrf_field() ?><input type="hidden" name="action" value="bundle"><button class="btn btn-sm">Switch to the bundle</button></form>
                        </div>
                    <?php endif; ?>
                    <p class="cart-more"><a href="<?= e(url('#inside')) ?>">← Continue shopping</a></p>
                <?php else: ?>
                    <p class="cart-more"><a href="<?= e(url('#inside')) ?>">Want only some books? Choose individually</a></p>
                <?php endif; ?>
            </aside>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
