<?php
/**
 * Streams purchased PDFs to a paid buyer. Requested by success.php with a POST
 * (order number + secret token + CSRF + file key), so the files' real locations are never exposed.
 * file = 'book:ID' | 'extras' | 'all' (ZIP of every file the order includes).
 */
require_once __DIR__ . '/includes/functions.php';

function deny(string $msg, int $code = 403): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $msg]);
    exit;
}

/** Local path of a file, fetching external URLs to a temp file. Returns [path, isTemp] or null. */
function resolve_file(array $f): ?array
{
    if ($f['path'] !== '') {
        return [$f['path'], false];
    }
    if ($f['url'] === '') {
        return null;
    }
    set_time_limit(0);
    $tmp = tempnam(sys_get_temp_dir(), 'hbdl');
    $fh = fopen($tmp, 'wb');
    $ch = curl_init($f['url']);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fh,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        // Some hosts refuse requests that don't look like a browser.
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: application/pdf,*/*'],
    ]);
    $ok = curl_exec($ch) && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 400;
    fclose($fh);
    if (!$ok || file_get_contents($tmp, false, null, 0, 5) !== '%PDF-') {
        unlink($tmp);
        error_log('Delivery: external URL did not return a PDF: ' . $f['url']);
        return null;
    }
    return [$tmp, true];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deny('Method not allowed.', 405);
}
if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
    deny('Your session expired. Please refresh the page and try again.');
}

$stmt = db()->prepare('SELECT * FROM orders WHERE order_no = ?');
$stmt->execute([(string) ($_POST['order'] ?? '')]);
$order = $stmt->fetch();
if (!$order || !hash_equals($order['access_token'], (string) ($_POST['t'] ?? ''))) {
    deny('Order not found.', 404);
}
if ($order['status'] !== 'paid') {
    deny('This order has not been paid yet.');
}

$files = order_files($order);
$counts = order_download_counts((int) $order['id']);
$limit = (int) setting('download_limit', '2');
$underLimit = fn (string $key) => $limit <= 0 || (int) ($counts[$key] ?? 0) < $limit;
$key = (string) ($_POST['file'] ?? '');

if ($key === 'all') {
    $wanted = array_filter($files, fn ($f, $k) => ($f['path'] !== '' || $f['url'] !== '') && $underLimit($k), ARRAY_FILTER_USE_BOTH);
} elseif (isset($files[$key])) {
    if (!$underLimit($key)) {
        deny('The download limit for this file has been reached. Please contact ' . setting('contact_email') . '.');
    }
    $wanted = [$key => $files[$key]];
} else {
    deny('This file is not part of your order.', 404);
}
if (!$wanted) {
    deny('There is nothing left to download for this order. Please contact ' . setting('contact_email') . '.', 404);
}

// Resolve every file first so a failure never counts as a download.
$resolved = [];
$temps = [];
foreach ($wanted as $k => $f) {
    $r = resolve_file($f);
    if ($r === null) {
        if ($key !== 'all') {
            array_map('unlink', $temps);
            deny('This download is temporarily unavailable. Please try again later or contact ' . setting('contact_email') . '.', 502);
        }
        continue;
    }
    $resolved[$k] = $r[0];
    if ($r[1]) {
        $temps[] = $r[0];
    }
}
if (!$resolved) {
    deny('Your downloads are temporarily unavailable. Please try again later or contact ' . setting('contact_email') . '.', 502);
}

$send = null;
$sendName = '';
$sendType = 'application/pdf';
if ($key === 'all') {
    $zipPath = tempnam(sys_get_temp_dir(), 'hbzip');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
        deny('Could not prepare the ZIP file. Please download the files one by one.', 500);
    }
    $used = [];
    foreach ($resolved as $k => $path) {
        $name = $files[$k]['filename'];
        // Keep names unique inside the ZIP.
        for ($n = 2; isset($used[strtolower($name)]); $n++) {
            $name = preg_replace('/\.pdf$/i', '', $files[$k]['filename']) . '-' . $n . '.pdf';
        }
        $used[strtolower($name)] = true;
        $zip->addFile($path, $name);
        $zip->setCompressionName($name, ZipArchive::CM_STORE); // PDFs are already compressed
    }
    $zip->close();
    $temps[] = $zipPath;
    $send = $zipPath;
    $sendName = preg_replace('/[^A-Za-z0-9]+/', '-', setting('site_name')) . '-' . $order['order_no'] . '.zip';
    $sendType = 'application/zip';
} else {
    $send = $resolved[$key];
    $sendName = $files[$key]['filename'];
}

$count = db()->prepare('INSERT INTO order_downloads (order_id, file_key, download_count) VALUES (?, ?, 1)
                        ON DUPLICATE KEY UPDATE download_count = download_count + 1');
foreach (array_keys($resolved) as $k) {
    $count->execute([$order['id'], $k]);
}
db()->prepare('UPDATE orders SET download_count = download_count + 1 WHERE id = ?')->execute([$order['id']]);

session_write_close();
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: ' . $sendType);
header('Content-Disposition: attachment; filename="' . $sendName . '"');
header('Content-Length: ' . filesize($send));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex');
readfile($send);
array_map('unlink', $temps);
