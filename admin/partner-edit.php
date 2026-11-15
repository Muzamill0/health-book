<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$partner = ['name' => '', 'email' => '', 'phone' => '', 'is_active' => 1, 'locked_until' => null];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM partners WHERE id = ?');
    $stmt->execute([$id]);
    $partner = $stmt->fetch() ?: redirect('partners.php');
}
$allLinks = db()->query('SELECT r.id, r.name, r.code, r.is_active, r.partner_id, p.name AS owner
    FROM referrers r LEFT JOIN partners p ON p.id = r.partner_id ORDER BY r.name')->fetchAll();
$chosen = array_map('intval', array_column(array_filter($allLinks, fn ($l) => $id && (int) $l['partner_id'] === $id), 'id'));

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $partner['name'] = trim((string) ($_POST['name'] ?? ''));
    $partner['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $phoneInput = trim((string) ($_POST['phone'] ?? ''));
    $partner['phone'] = normalize_phone($phoneInput);
    $partner['is_active'] = empty($_POST['is_active']) ? 0 : 1;
    $password = (string) ($_POST['password'] ?? '');
    $chosen = array_map('intval', (array) ($_POST['links'] ?? []));

    $dupe = db()->prepare('SELECT
        (SELECT COUNT(*) FROM partners WHERE email = ? AND id <> ?) AS email_taken,
        (SELECT COUNT(*) FROM partners WHERE phone = ? AND id <> ?) AS phone_taken');
    $dupe->execute([$partner['email'], $id, $partner['phone'], $id]);
    $taken = $dupe->fetch();

    if ($partner['name'] === '') {
        $error = 'Name is required.';
    } elseif (!filter_var($partner['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!valid_phone($partner['phone'])) {
        $error = 'Please enter a valid phone number, e.g. 0300 1234567 or +92 300 1234567.';
    } elseif ($taken['email_taken']) {
        $error = 'Another user already uses this email.';
    } elseif ($taken['phone_taken']) {
        $error = 'Another user already uses this phone number.';
    } elseif (!$id && strlen($password) < 8) {
        $error = 'Set a password of at least 8 characters.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'The new password must be at least 8 characters.';
    } else {
        db()->beginTransaction();
        if ($id) {
            db()->prepare('UPDATE partners SET name = ?, email = ?, phone = ?, is_active = ? WHERE id = ?')
                ->execute([$partner['name'], $partner['email'], $partner['phone'], $partner['is_active'], $id]);
            if ($password !== '') {
                // A password reset also lifts any lock.
                db()->prepare('UPDATE partners SET password_hash = ?, failed_logins = 0, locked_until = NULL WHERE id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            }
        } else {
            db()->prepare('INSERT INTO partners (name, email, phone, password_hash, is_active) VALUES (?,?,?,?,?)')
                ->execute([$partner['name'], $partner['email'], $partner['phone'], password_hash($password, PASSWORD_DEFAULT), $partner['is_active']]);
            $id = (int) db()->lastInsertId();
        }
        // Links: unassign the ones unticked, assign the ticked ones (taking them from any other user).
        db()->prepare('UPDATE referrers SET partner_id = NULL WHERE partner_id = ?')->execute([$id]);
        if ($chosen) {
            $in = implode(',', array_fill(0, count($chosen), '?'));
            db()->prepare("UPDATE referrers SET partner_id = ? WHERE id IN ($in)")->execute([$id, ...$chosen]);
        }
        if (!empty($_POST['unlock'])) {
            db()->prepare('UPDATE partners SET failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([$id]);
        }
        db()->commit();
        flash('User saved.');
        redirect('partners.php');
    }
    $partner['phone'] = $phoneInput;
}

$isLocked = !empty($partner['locked_until']) && strtotime($partner['locked_until']) > time();
$adminTitle = $id ? 'Edit user' : 'Add user';
require __DIR__ . '/_top.php';
?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form class="panel form" method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-row form-row--split">
        <div><label for="name">Name</label><input id="name" name="name" value="<?= e($partner['name']) ?>" required></div>
        <div><label>Status</label><label class="switch"><input type="checkbox" name="is_active" value="1" <?= $partner['is_active'] ? 'checked' : '' ?>><span>Active (can log in)</span></label></div>
    </div>
    <div class="form-row form-row--split">
        <div><label for="email">Email (used to log in)</label><input type="email" id="email" name="email" value="<?= e($partner['email']) ?>" required></div>
        <div><label for="phone">Phone number (used to log in)</label><input id="phone" name="phone" value="<?= e($id && ctype_digit((string) $partner['phone']) ? '+' . $partner['phone'] : $partner['phone']) ?>" placeholder="0300 1234567 or +92 300 1234567" required></div>
    </div>
    <div class="form-row form-row--split">
        <div>
            <label for="password"><?= $id ? 'New password (leave empty to keep the current one)' : 'Password (min 8 characters)' ?></label>
            <input type="password" id="password" name="password" minlength="8" autocomplete="new-password" <?= $id ? '' : 'required' ?>>
        </div>
        <div>
            <?php if ($isLocked): ?>
                <label>Account locked</label>
                <label class="switch"><input type="checkbox" name="unlock" value="1"><span>Unlock now (locked after too many wrong passwords until <?= e(date('H:i', strtotime($partner['locked_until']))) ?>)</span></label>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-row">
        <label>Referral links for this user</label>
        <?php if (!$allLinks): ?>
            <p class="muted">No referral links yet. <a href="referrers.php">Create one</a> first.</p>
        <?php else: ?>
        <div class="bundle-books">
            <?php foreach ($allLinks as $l):
                $mine = in_array((int) $l['id'], $chosen, true);
                $otherOwner = $l['partner_id'] && (int) $l['partner_id'] !== $id ? $l['owner'] : null; ?>
            <label class="bundle-book<?= $l['is_active'] ? '' : ' is-hidden' ?>">
                <input type="checkbox" name="links[]" value="<?= (int) $l['id'] ?>" <?= $mine ? 'checked' : '' ?>>
                <span><strong><?= e($l['name']) ?></strong><small>?ref=<?= e($l['code']) ?><?= $otherOwner ? ' · now with ' . e($otherOwner) . ' (ticking moves it here)' : '' ?></small></span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div class="form-actions"><button class="btn">Save user</button> <a class="btn btn-light" href="partners.php">Cancel</a></div>
</form>
<?php require __DIR__ . '/_bottom.php'; ?>
