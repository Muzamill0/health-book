<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $stmt = db()->prepare('SELECT password_hash FROM admins WHERE id = ?');
    $stmt->execute([$_SESSION['admin_id']]);
    $hash = (string) $stmt->fetchColumn();
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== ($_POST['confirm_password'] ?? '')) {
        $error = 'New passwords do not match.';
    } else {
        db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
        flash('Password changed.');
        redirect('account.php');
    }
}

$adminTitle = 'Change Password';
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post" style="max-width:520px">
    <?= csrf_field() ?>
    <p class="muted">Signed in as <strong><?= e($_SESSION['admin_user'] ?? '') ?></strong></p>
    <div class="form-row"><label>Current password</label><input type="password" name="current_password" required autocomplete="current-password"></div>
    <div class="form-row"><label>New password</label><input type="password" name="new_password" required minlength="8" autocomplete="new-password"></div>
    <div class="form-row"><label>Confirm new password</label><input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></div>
    <div class="form-actions"><button class="btn">Update password</button></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
