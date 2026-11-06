<?php
require __DIR__ . '/includes/init.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $name = post('name');
    if (!password_verify($current, $row['password_hash'])) {
        flash('error', 'Your current password is incorrect.');
    } elseif ($new !== '' && strlen($new) < 8) {
        flash('error', 'The new password must be at least 8 characters.');
    } elseif ($new !== '' && $new !== ($_POST['confirm'] ?? '')) {
        flash('error', 'The new passwords do not match.');
    } else {
        $data = ['name' => $name ?: $me['name']];
        if ($new !== '') {
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        db_update('users', $data, $me['id']);
        flash('success', 'Your account was updated.');
    }
    redirect('admin/profile.php');
}

admin_header('My Account');
?>
<form method="post" class="card" style="max-width:560px">
  <?= csrf_field() ?>
  <div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($me['name']) ?>"></div>
    <div class="col-12"><label class="form-label">Email</label><input class="form-control" value="<?= e($me['email']) ?>" disabled></div>
    <div class="col-12"><label class="form-label">New password (optional)</label><input type="password" name="new" class="form-control" minlength="8" autocomplete="new-password"></div>
    <div class="col-12"><label class="form-label">Repeat new password</label><input type="password" name="confirm" class="form-control" autocomplete="new-password"></div>
    <div class="col-12"><label class="form-label">Current password (required to save)</label><input type="password" name="current" class="form-control" required autocomplete="current-password"></div>
  </div>
  <div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>
</form>
<?php admin_footer();
