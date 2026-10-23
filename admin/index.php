<?php
require_once __DIR__ . '/../includes/functions.php';
if (is_admin()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] > 10) {
        $error = 'Too many attempts. Please wait and try again later.';
    } else {
        $stmt = db()->prepare('SELECT * FROM admins WHERE username = ?');
        $stmt->execute([trim($_POST['username'] ?? '')]);
        $admin = $stmt->fetch();
        if ($admin && password_verify((string) ($_POST['password'] ?? ''), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            $_SESSION['admin_user'] = $admin['username'];
            unset($_SESSION['login_attempts']);
            redirect('dashboard.php');
        }
        $error = 'Incorrect username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin login · <?= e(setting('site_name')) ?></title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="login-body">
<form class="login-card" method="post">
    <h1><?= e(setting('site_name')) ?></h1>
    <p class="muted">Sign in to manage your website</p>
    <?php if ($error): ?><p class="alert error"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>Username<input name="username" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-block" type="submit">Sign in</button>
</form>
</body>
</html>
