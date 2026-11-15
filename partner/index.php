<?php
require_once __DIR__ . '/../includes/functions.php';
if (current_partner()) {
    redirect('dashboard.php');
}

$error = '';
$identifier = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identifier = trim((string) ($_POST['identifier'] ?? ''));
    // Per-browser brake on top of the per-account lock.
    $_SESSION['partner_login_attempts'] = ($_SESSION['partner_login_attempts'] ?? 0) + 1;
    if ($_SESSION['partner_login_attempts'] > 20) {
        $error = 'Too many attempts. Please try again later.';
    } else {
        $result = partner_attempt_login($identifier, (string) ($_POST['password'] ?? ''));
        if (is_array($result)) {
            session_regenerate_id(true);
            $_SESSION['partner_id'] = (int) $result['id'];
            unset($_SESSION['partner_login_attempts']);
            redirect('dashboard.php');
        }
        $error = $result;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Partner login · <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('admin/admin.css')) ?>">
</head>
<body class="login-body">
<form class="login-card" method="post">
    <h1><?= e(setting('site_name')) ?></h1>
    <p class="muted">Partner login — see how your referral links are doing</p>
    <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>Email or phone number<input name="identifier" value="<?= e($identifier) ?>" required autofocus autocomplete="username" placeholder="you@example.com or 0300 1234567"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-block" type="submit">Log in</button>
    <p class="muted" style="font-size:.85rem">Forgot your password? Ask the site owner to reset it.</p>
</form>
</body>
</html>
