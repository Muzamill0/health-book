</main>
<?php if (setting('newsletter_enabled') === '1' && empty($hideNewsletter)): ?>
<section class="newsletter">
    <div class="container narrow center">
        <h2><?= e(ts('newsletter_heading')) ?></h2>
        <p><?= e(ts('newsletter_text')) ?></p>
        <form class="newsletter__form" method="post" action="<?= e(url('subscribe.php')) ?>" data-subscribe>
            <input type="email" name="email" placeholder="Your email address" required aria-label="Email">
            <button class="btn" type="submit">Subscribe</button>
        </form>
        <p class="newsletter__msg" data-subscribe-msg></p>
    </div>
</section>
<?php endif; ?>
<footer class="site-footer">
    <div class="container footer__grid">
        <div>
            <div class="logo logo--light"><?= e(ts('site_name')) ?></div>
            <p><?= e(ts('footer_about')) ?></p>
        </div>
        <div>
            <h4>Information</h4>
            <ul>
                <?php foreach (active_pages() as $p): ?>
                    <li><a href="<?= e(url('page.php?slug=' . urlencode($p['slug']))) ?>"><?= e($p['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h4>Support</h4>
            <p><a href="mailto:<?= e(ts('contact_email')) ?>"><?= e(ts('contact_email')) ?></a></p>
            <p class="payments">Secure payments by <strong>Paddle</strong></p>
        </div>
    </div>
    <div class="container footer__bottom">
        <p><?= e(ts('footer_disclaimer')) ?></p>
        <p><?= e(str_replace('{year}', date('Y'), setting('copyright'))) ?></p>
    </div>
</footer>
<script src="<?= e(url('assets/js/main.js')) ?>"></script>
<?= $extraScripts ?? '' ?>
</body>
</html>
