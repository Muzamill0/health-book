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
    }
}

/** The active referrer remembered for this visitor, if any. */
function current_referrer(): ?array
{
    $code = (string) ($_COOKIE[REFERRAL_COOKIE] ?? '');
    return preg_match('/^[a-z0-9-]{1,50}$/', $code) ? referrer_by_code($code) : null;
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
