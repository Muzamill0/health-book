<?php
require_once __DIR__ . '/../includes/functions.php';
$partner = require_partner();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $partner['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
        $error = 'New passwords do not match.';
    } else {
        db()->prepare('UPDATE partners SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $partner['id']]);
        session_regenerate_id(true);
        flash('Password changed.');
        redirect('dashboard.php');
    }
}

$partnerTitle = 'Change password';
require __DIR__ . '/_top.php';
?>
<h1 class="partner-title">Change password</h1>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post" style="max-width:480px">
    <?= csrf_field() ?>
    <div class="form-row"><label>Current password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="form-row"><label>New password (min 8 characters)</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="form-row"><label>Confirm new password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <div class="form-actions"><button class="btn">Update password</button> <a class="btn btn-light" href="dashboard.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
