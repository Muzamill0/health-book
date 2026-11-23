<?php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Shared PDO connection. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = DB_SOCKET !== ''
            ? 'mysql:unix_socket=' . DB_SOCKET . ';dbname=' . DB_NAME . ';charset=utf8mb4'
            : 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function settings_schema(): array
{
    static $schema = null;
    return $schema ??= require __DIR__ . '/settings_schema.php';
}

/** Read a setting, falling back to the schema default. */
function setting(string $key, string $fallback = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    foreach (settings_schema() as $group) {
        if (isset($group['fields'][$key])) {
            return (string) $group['fields'][$key]['default'];
        }
    }
    return $fallback;
}

function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
}

/** Active content items of one type (feature, book, bonus, testimonial, faq, highlight, gallery). */
function items(string $type): array
{
    $stmt = db()->prepare('SELECT * FROM items WHERE type = ? AND is_active = 1 ORDER BY sort_order, id');
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

/** Resolve a stored image path (uploads/… or assets/…) or absolute URL to a URL. */
function img_url(?string $path): string
{
    $path = (string) $path;
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return BASE_URL . '/' . ltrim($path, '/');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL of a local CSS/JS file with a version that changes whenever the file changes (beats browser/CDN caching). */
function asset(string $path): string
{
    $file = __DIR__ . '/../' . ltrim($path, '/');
    return url($path) . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/**
 * Admin-written text as safe HTML: blank lines start a new paragraph, single line breaks are kept,
 * and **double stars** make text bold. Everything else is escaped.
 */
function rich_text(string $text): string
{
    $html = '';
    foreach (preg_split('/\R\s*\R/', trim($text)) as $para) {
        if (trim($para) === '') {
            continue;
        }
        $safe = nl2br(e(trim($para)));
        $html .= '<p>' . preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $safe) . '</p>';
    }
    return $html;
}

function money(float $amount): string
{
    return setting('currency_symbol', '$') . number_format($amount, 2);
}

function discount_percent(): int
{
    $price = (float) setting('price');
    $compare = (float) setting('compare_price');
    return ($compare > $price && $compare > 0) ? (int) round(100 - ($price / $compare) * 100) : 0;
}

/** Currencies with no minor unit (amounts are sent to the payment provider as whole units). */
function to_minor_units(float $amount, string $currency): int
{
    $zeroDecimal = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];
    return in_array(strtolower($currency), $zeroDecimal, true) ? (int) round($amount) : (int) round($amount * 100);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Invalid form token. Please go back and try again.');
    }
}

function flash(?string $msg = null,string $type = 'success'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

/**
 * Store an uploaded image and return its relative path, or null if none was sent.
 * Throws RuntimeException on invalid files.
 */
function handle_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $f['error'] . ').');
    }
    if ($f['size'] > 8 * 1024 * 1024) {
        throw new RuntimeException('Image must be smaller than 8 MB.');
    }
    $allowed = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif',
        'image/webp' => 'webp', 'image/svg+xml' => 'svg', 'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, GIF, WEBP, SVG or ICO images are allowed.');
    }
    if ($mime === 'image/svg+xml' && preg_match('/<script|on\w+\s*=|javascript:/i', (string) file_get_contents($f['tmp_name']))) {
        throw new RuntimeException('SVG contains scripts and was rejected.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return 'uploads/' . $name;
}

/** Folder for paid files; blocked from direct web access by storage/.htaccess. */
function storage_dir(): string
{
    return __DIR__ . '/../storage';
}

/**
 * Store an uploaded PDF in the private storage folder and return its stored file name,
 * or null if none was sent. Throws RuntimeException on invalid files.
 */
function handle_private_upload(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $f = $_FILES[$field];
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new RuntimeException('The PDF is larger than the server upload limit (' . ini_get('upload_max_filesize') . ').');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . $f['error'] . ').');
    }
    if ((new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) !== 'application/pdf') {
        throw new RuntimeException('Only PDF files are allowed.');
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(16)) . '.pdf';
    if (!is_dir(storage_dir())) {
        mkdir(storage_dir(), 0755, true);
    }
    if (!move_uploaded_file($f['tmp_name'], storage_dir() . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return $name;
}

/* ---------- Books, bundle & cart ---------- */

/** Active books (products), in display order. */
function books(): array
{
    static $books = null;
    return $books ??= db()->query('SELECT * FROM books WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
}

function book_count(): int
{
    return count(books());
}

/** Active books the admin has put in the bundle. */
function bundle_books(): array
{
    return array_values(array_filter(books(), fn ($b) => (int) $b['in_bundle'] === 1));
}

/** The bundle can be sold only when it has books and a price. */
function bundle_available(): bool
{
    return bundle_books() && (float) setting('price') > 0;
}

/** Replace {book_count}, {bundle_count}, {bundle_name} and {bundle_price} placeholders in admin-written text. */
function fill(?string $text): string
{
    return strtr((string) $text, [
        '{book_count}'   => (string) book_count(),
        '{bundle_count}' => (string) count(bundle_books()),
        '{bundle_name}'  => setting('bundle_name'),
        '{bundle_price}' => money((float) setting('price')),
    ]);
}

/** A setting with placeholders filled in — use for text shown on the website. */
function ts(string $key): string
{
    return fill(setting($key));
}

function from_minor_units(int $amount, string $currency): float
{
    return to_minor_units(1, $currency) === 1 ? (float) $amount : $amount / 100;
}

/** The session cart: ['bundle' => bool, 'books' => [book ids]]. */
function cart_get(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return ['bundle' => !empty($cart['bundle']), 'books' => array_values(array_unique(array_map('intval', $cart['books'] ?? [])))];
}

function cart_set(bool $bundle, array $bookIds): void
{
    $valid = array_column(books(), 'id');
    $_SESSION['cart'] = [
        'bundle' => $bundle && bundle_available(),
        'books'  => array_values(array_intersect(array_unique(array_map('intval', $bookIds)), $valid)),
    ];
}

/**
 * Price the cart. All money maths happens here, on the server.
 * Books are sold at their own price; the only discount is the admin-defined bundle.
 * Returns type (empty|books|bundle), items, count, subtotal, discount, discount_pct, total,
 * bundle_better (the bundle costs no more than the bundle books in the cart) and a product name.
 */
function cart_summary(?array $cart = null): array
{
    $cart ??= cart_get();
    $currency = strtolower(setting('currency', 'usd'));
    $bundlePrice = (float) setting('price');
    $bundleBooks = bundle_books();

    if ($cart['bundle'] && bundle_available()) {
        $valueMinor = array_sum(array_map(fn ($b) => to_minor_units((float) ($b['compare_price'] ?: $b['price']), $currency), $bundleBooks));
        $compare = (float) setting('compare_price');
        $subtotal = $compare > $bundlePrice ? $compare : from_minor_units($valueMinor, $currency);
        return [
            'type' => 'bundle', 'items' => $bundleBooks, 'count' => count($bundleBooks),
            'subtotal' => max($subtotal, $bundlePrice), 'discount' => max(0, $subtotal - $bundlePrice),
            'discount_pct' => $subtotal > $bundlePrice ? (int) round(100 - $bundlePrice / $subtotal * 100) : 0,
            'total' => $bundlePrice, 'bundle_better' => false,
            'name' => setting('bundle_name') . ' (' . count($bundleBooks) . ' books)',
        ];
    }

    $byId = array_column(books(), null, 'id');
    $items = array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $cart['books'])));
    $count = count($items);
    if ($count === 0) {
        return ['type' => 'empty', 'items' => [], 'count' => 0, 'subtotal' => 0, 'discount' => 0, 'discount_pct' => 0,
                'total' => 0, 'bundle_better' => false, 'name' => ''];
    }

    $totalMinor = array_sum(array_map(fn ($b) => to_minor_units((float) $b['price'], $currency), $items));
    // Suggest the bundle when the cart only holds bundle books and they cost at least the bundle price.
    $bundleIds = array_map('intval', array_column($bundleBooks, 'id'));
    $allInBundle = !array_diff(array_map('intval', array_column($items, 'id')), $bundleIds);

    $names = array_column($items, 'title');
    return [
        'type' => 'books', 'items' => $items, 'count' => $count,
        'subtotal' => from_minor_units($totalMinor, $currency), 'discount' => 0, 'discount_pct' => 0,
        'total' => from_minor_units($totalMinor, $currency),
        'bundle_better' => bundle_available() && $allInBundle && $totalMinor >= to_minor_units($bundlePrice, $currency),
        'name' => mb_strimwidth($count === 1 ? $names[0] : $count . ' books: ' . implode(', ', $names), 0, 250, '…'),
    ];
}

/** Cart data for the browser (cart bar, checkout). */
function cart_public(array $summary): array
{
    return [
        'type'          => $summary['type'],
        'count'         => $summary['count'],
        'ids'           => $summary['type'] === 'books' ? array_map('intval', array_column($summary['items'], 'id')) : [],
        'total'         => money($summary['total']),
        'bundle_better' => $summary['bundle_better'],
        'bundle_name'   => setting('bundle_name'),
        'bundle_price'  => money((float) setting('price')),
        'bundle_count'  => count(bundle_books()),
    ];
}

/**
 * Files an order gives access to, keyed by file key ('book:ID' or 'extras').
 * Every order lists its books in order_items (bundle orders record the bundle books at the time of purchase);
 * bundle orders from before that existed fall back to the current bundle books. Bundle orders also get the extra PDF.
 */
function order_files(array $order): array
{
    $stmt = db()->prepare('SELECT b.* FROM order_items oi JOIN books b ON b.id = oi.book_id WHERE oi.order_id = ? ORDER BY b.sort_order, b.id');
    $stmt->execute([$order['id']]);
    $list = $stmt->fetchAll();
    if (!$list && $order['order_type'] === 'bundle') {
        $list = bundle_books();
    }

    $files = [];
    foreach ($list as $b) {
        $path = $b['pdf_file'] !== '' ? storage_dir() . '/' . basename($b['pdf_file']) : '';
        $files['book:' . $b['id']] = [
            'title'    => $b['title'],
            'filename' => trim(preg_replace('/[^A-Za-z0-9]+/', '-', $b['title']), '-') . '.pdf',
            'path'     => is_file($path) ? $path : '',
            'url'      => '',
        ];
    }
    if ($order['order_type'] === 'bundle') {
        $extra = setting('delivery_file');
        $path = $extra !== '' ? storage_dir() . '/' . basename($extra) : '';
        if (is_file($path) || setting('download_url') !== '') {
            $files['extras'] = [
                'title'    => 'Bundle bonuses',
                'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '-', setting('delivery_filename') ?: 'Bundle-Bonuses.pdf'),
                'path'     => is_file($path) ? $path : '',
                'url'      => is_file($path) ? '' : setting('download_url'),
            ];
        }
    }
    return $files;
}

/** How many times each file of an order has been downloaded, keyed by file key. */
function order_download_counts(int $orderId): array
{
    $stmt = db()->prepare('SELECT file_key, download_count FROM order_downloads WHERE order_id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

const REFERRAL_COOKIE = 'hb_ref';

function referrer_by_code(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM referrers WHERE code = ? AND is_active = 1');
    $stmt->execute([strtolower($code)]);
    return $stmt->fetch() ?: null;
}

/**
 * Remember the referrer from a ?ref=code link in a cookie (the most recent link wins)
 * and count one click per visitor session. Must run before any page output.
 */
function referral_capture(): void
{
    $code = strtolower(trim((string) ($_GET['ref'] ?? '')));
    if ($code === '' || !preg_match('/^[a-z0-9-]{1,50}$/', $code) || !($ref = referrer_by_code($code))) {
        return;
    }
    $days = max(1, (int) setting('referral_cookie_days', '30'));
    setcookie(REFERRAL_COOKIE, $code, [
        'expires'  => time() + $days * 86400,
        'path'     => parse_url(BASE_URL, PHP_URL_PATH) ?: '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    $_COOKIE[REFERRAL_COOKIE] = $code;
    if (empty($_SESSION['ref_clicked'][$code])) {
        $_SESSION['ref_clicked'][$code] = true;
        db()->prepare('UPDATE referrers SET clicks = clicks + 1 WHERE id = ?')->execute([$ref['id']]);
        try {
            // MySQL's CURDATE() matches orders.paid_at (set with NOW()), so clicks and orders share the same "day".
            db()->prepare('INSERT INTO referral_clicks_daily (referrer_id, day, clicks) VALUES (?, CURDATE(), 1)
                ON DUPLICATE KEY UPDATE clicks = clicks + 1')->execute([$ref['id']]);
        } catch (PDOException $e) {
            error_log('Daily referral click not saved (run database/migrations/2026-11-23-referral-daily-stats.sql): ' . $e->getMessage());
        }
    }
}

/**
 * The from/to dates of a history page, from ?from=YYYY-MM-DD&to=YYYY-MM-DD.
 * Defaults to the last 30 days; at most one year; swapped if entered the wrong way round.
 */
function referral_history_range(): array
{
    $valid = fn ($d) => is_string($d) && ($dt = DateTime::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
    $to   = $valid($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
    $from = $valid($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-d', strtotime($to . ' -29 days'));
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $earliest = date('Y-m-d', strtotime($to . ' -365 days'));
    return [max($from, $earliest), $to];
}

/**
 * Day-by-day clicks, paid orders and revenue for referral links, newest day first, one row for every day in the range.
 * Limit to one link with $referrerId, or to one partner's links with $partnerId (both null = all links).
 */
function referral_daily_stats(string $from, string $to, ?int $referrerId = null, ?int $partnerId = null): array
{
    $where  = '';
    $params = [];
    if ($referrerId !== null) {
        $where .= ' AND r.id = ?';
        $params[] = $referrerId;
    }
    if ($partnerId !== null) {
        $where .= ' AND r.partner_id = ?';
        $params[] = $partnerId;
    }

    $days = [];
    for ($d = $to; $d >= $from; $d = date('Y-m-d', strtotime($d . ' -1 day'))) {
        $days[$d] = ['day' => $d, 'clicks' => 0, 'orders' => 0, 'revenue' => 0.0];
    }

    try {
        $stmt = db()->prepare("SELECT c.day, SUM(c.clicks) AS clicks
            FROM referral_clicks_daily c JOIN referrers r ON r.id = c.referrer_id
            WHERE c.day BETWEEN ? AND ?$where
            GROUP BY c.day");
        $stmt->execute(array_merge([$from, $to], $params));
        foreach ($stmt as $row) {
            $days[$row['day']]['clicks'] = (int) $row['clicks'];
        }
    } catch (PDOException $e) {
        error_log('Daily referral clicks unavailable (run database/migrations/2026-11-23-referral-daily-stats.sql): ' . $e->getMessage());
    }

    $stmt = db()->prepare("SELECT DATE(o.paid_at) AS day, COUNT(*) AS orders, SUM(o.amount) AS revenue
        FROM orders o JOIN referrers r ON r.id = o.referrer_id
        WHERE o.status = 'paid' AND o.payment_provider = 'paddle'
          AND o.paid_at >= ? AND o.paid_at < DATE_ADD(?, INTERVAL 1 DAY)$where
        GROUP BY DATE(o.paid_at)");
    $stmt->execute(array_merge([$from, $to], $params));
    foreach ($stmt as $row) {
        if (isset($days[$row['day']])) {
            $days[$row['day']]['orders']  = (int) $row['orders'];
            $days[$row['day']]['revenue'] = (float) $row['revenue'];
        }
    }

    return array_values($days);
}

/** The active referrer remembered for this visitor, if any. */
function current_referrer(): ?array
{
    $code = (string) ($_COOKIE[REFERRAL_COOKIE] ?? '');
    return preg_match('/^[a-z0-9-]{1,50}$/', $code) ? referrer_by_code($code) : null;
}

/* ---------- Partner users (referral link owners) ---------- */

const PARTNER_MAX_FAILED_LOGINS = 5;
const PARTNER_LOCK_MINUTES = 15;

/** Digits-only phone number in international form, e.g. "0300 1234567" and "+92 300 1234567" both become "923001234567". */
function normalize_phone(string $raw): string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) === 11 && $digits[0] === '0') {
        $digits = '92' . substr($digits, 1); // Pakistani local format 03xx…
    }
    return $digits;
}

function valid_phone(string $normalized): bool
{
    return (bool) preg_match('/^\d{10,15}$/', $normalized);
}

/**
 * Check an email-or-phone + password. Returns the partner row on success, or an error message string.
 * Accounts lock for a while after repeated wrong passwords.
 */
function partner_attempt_login(string $identifier, string $password): array|string
{
    $identifier = trim($identifier);
    $generic = 'Incorrect email/phone or password.';
    if ($identifier === '' || $password === '') {
        return 'Please enter your email or phone number and your password.';
    }
    if (str_contains($identifier, '@')) {
        $stmt = db()->prepare('SELECT * FROM partners WHERE email = ?');
        $stmt->execute([mb_strtolower($identifier)]);
    } else {
        $stmt = db()->prepare('SELECT * FROM partners WHERE phone = ?');
        $stmt->execute([normalize_phone($identifier)]);
    }
    $partner = $stmt->fetch();
    if (!$partner) {
        password_verify($password, '$2y$12$Q48mZTQPpYeG5puWPWGJcekHp81w7Kjs8dhr2FRVqSD5eawfztQkK'); // same cost as a real check, so timing doesn't reveal which accounts exist
        return $generic;
    }
    if ($partner['locked_until'] && strtotime($partner['locked_until']) > time()) {
        return 'Too many wrong attempts. Please try again in ' . PARTNER_LOCK_MINUTES . ' minutes.';
    }
    if (!password_verify($password, $partner['password_hash'])) {
        $failed = (int) $partner['failed_logins'] + 1;
        $lock = $failed >= PARTNER_MAX_FAILED_LOGINS ? date('Y-m-d H:i:s', time() + PARTNER_LOCK_MINUTES * 60) : null;
        db()->prepare('UPDATE partners SET failed_logins = ?, locked_until = ? WHERE id = ?')
            ->execute([$lock ? 0 : $failed, $lock, $partner['id']]);
        return $lock ? 'Too many wrong attempts. Please try again in ' . PARTNER_LOCK_MINUTES . ' minutes.' : $generic;
    }
    if (!(int) $partner['is_active']) {
        return 'Your account is disabled. Please contact the site owner.';
    }
    db()->prepare('UPDATE partners SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')
        ->execute([$partner['id']]);
    if (password_needs_rehash($partner['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE partners SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $partner['id']]);
    }
    return $partner;
}

/** The logged-in partner (re-checked on every request, so disabling an account logs it out). */
function current_partner(): ?array
{
    static $partner = false;
    if ($partner === false) {
        $partner = null;
        if (!empty($_SESSION['partner_id'])) {
            $stmt = db()->prepare('SELECT * FROM partners WHERE id = ? AND is_active = 1');
            $stmt->execute([(int) $_SESSION['partner_id']]);
            $partner = $stmt->fetch() ?: null;
            if (!$partner) {
                unset($_SESSION['partner_id']);
            }
        }
    }
    return $partner;
}

function require_partner(): array
{
    $partner = current_partner();
    if (!$partner) {
        redirect(url('partner/index.php'));
    }
    return $partner;
}

/** "Ali Khan" → "Ali K." — enough for a partner to recognise a sale without seeing the buyer's full details. */
function mask_customer_name(string $name): string
{
    $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) {
        return '—';
    }
    $first = array_shift($parts);
    return $parts ? $first . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.' : $first;
}

/** "ali.khan@gmail.com" → "a***@gmail.com" */
function mask_email(string $email): string
{
    [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
    return $domain === '' ? '***' : mb_substr($user, 0, 1) . '***@' . $domain;
}

/**
 * Paid orders made through a partner's links, paid between $from and $to (inclusive), newest first.
 * Limit to one of their links with $referrerId.
 */
function partner_orders(int $partnerId, string $from, string $to, ?int $referrerId = null): array
{
    $sql = "SELECT o.order_no, o.customer_name, o.customer_email, o.product_name, o.amount, o.currency, o.paid_at, r.name AS link_name
        FROM orders o JOIN referrers r ON r.id = o.referrer_id
        WHERE r.partner_id = ? AND o.status = 'paid' AND o.payment_provider = 'paddle'
          AND o.paid_at >= ? AND o.paid_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $params = [$partnerId, $from, $to];
    if ($referrerId !== null) {
        $sql .= ' AND r.id = ?';
        $params[] = $referrerId;
    }
    $stmt = db()->prepare($sql . ' ORDER BY o.paid_at DESC LIMIT 500');
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** A partner's referral links with clicks and paid orders. */
function partner_links(int $partnerId): array
{
    $stmt = db()->prepare("SELECT r.id, r.name, r.code, r.is_active, r.clicks, COUNT(o.id) AS orders
        FROM referrers r
        LEFT JOIN orders o ON o.referrer_id = r.id AND o.status = 'paid' AND o.payment_provider = 'paddle'
        WHERE r.partner_id = ?
        GROUP BY r.id
        ORDER BY r.name");
    $stmt->execute([$partnerId]);
    return $stmt->fetchAll();
}

function is_admin(): bool
{
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin()) {
        redirect(url('admin/index.php'));
    }
}

function active_pages(): array
{
    return db()->query('SELECT slug, title FROM pages WHERE is_active = 1 ORDER BY sort_order, id')->fetchAll();
}

function stars(float $rating): string
{
    $full = (int) round($rating);
    return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', 5 - max(0, min(5, $full)));
}
