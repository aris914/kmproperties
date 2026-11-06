<?php
require __DIR__ . '/includes/init.php';
$me = require_admin('admin');

if (is_post()) {
    csrf_check();
    $action = post('action');
    if ($action === 'save') {
        $id = (int) post('id');
        $name = post('name');
        $email = post('email');
        $pass = (string) ($_POST['password'] ?? '');
        $role = post('role') === 'staff' ? 'staff' : 'admin';
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a name and a valid email.');
        } elseif (q_val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
            flash('error', 'Another user already has that email.');
        } elseif (!$id && strlen($pass) < 8) {
            flash('error', 'New users need a password of at least 8 characters.');
        } elseif ($pass !== '' && strlen($pass) < 8) {
            flash('error', 'The password must be at least 8 characters.');
        } else {
            $data = ['name' => $name, 'email' => $email, 'role' => $id === (int) $me['id'] ? 'admin' : $role, 'is_active' => ($id === (int) $me['id'] || post('is_active')) ? 1 : 0];
            if ($pass !== '') {
                $data['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            }
            if ($id) {
                db_update('users', $data, $id);
                // A new password or a disabled account ends that user's other sessions (see current_user());
                // keep the person who is saving signed in when they changed their own password.
                if ($id === (int) $me['id'] && $pass !== '') {
                    $_SESSION['pwv'] = pw_version($data['password_hash']);
                    sessions_kick_user($id, true);
                }
            } else {
                db_insert('users', $data);
            }
            audit($id ? 'update' : 'insert', 'users', $email, 'Role: ' . $data['role'] . ($data['is_active'] ? '' : ' (disabled)') . ($pass !== '' ? ' · password set' : ''));
            flash('success', 'User saved.');
        }
    } elseif ($action === 'delete') {
        $id = (int) post('id');
        if ($id === (int) $me['id']) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $delEmail = (string) q_val('SELECT email FROM users WHERE id = ?', [$id]);
            sessions_kick_user($id);
            q('DELETE FROM users WHERE id = ?', [$id]);
            audit('delete', 'users', $delEmail);
            flash('success', 'User deleted.');
        }
    }
    redirect('admin/users.php');
}

$users = q_all('SELECT * FROM users ORDER BY name');
$edit = get('edit') ? q_one('SELECT * FROM users WHERE id = ?', [(int) get('edit')]) : null;
admin_header('Admin Users', 'users');
?>
<div class="row g-4">
  <div class="col-xl-7">
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
      <thead><tr><th>Name</th><th>Role</th><th>Last login</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><strong><?= e($u['name']) ?></strong><div class="small text-muted"><?= e($u['email']) ?></div></td>
          <td><?= $u['role'] === 'admin' ? 'Administrator' : 'Staff' ?></td>
          <td class="small"><?= $u['last_login'] ? fmt_date($u['last_login'], 'M j, Y g:i A') : 'Never' ?></td>
          <td><?= $u['is_active'] ? '<span class="badge text-bg-success">Active</span>' : '<span class="badge text-bg-secondary">Disabled</span>' ?></td>
          <td class="text-end text-nowrap">
            <a href="<?= url('admin/users.php?edit=' . $u['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
            <?php if ((int) $u['id'] !== (int) $me['id']): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
    <p class="small text-muted mt-2"><strong>Administrators</strong> can do everything. <strong>Staff</strong> can manage listings, tenants, leases, repairs and inquiries, but not site settings or users.</p>
  </div>
  <div class="col-xl-5">
    <form method="post" class="card" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : 0 ?>">
      <div class="card-header"><?= $edit ? 'Edit user' : 'Add user' ?></div>
      <div class="card-body row g-3">
        <div class="col-12"><label class="form-label">Full name</label><input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Email (login)</label><input type="email" name="email" class="form-control" required value="<?= e($edit['email'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label"><?= $edit ? 'New password (leave blank to keep)' : 'Password' ?></label><input type="password" name="password" class="form-control" minlength="8" autocomplete="new-password" <?= $edit ? '' : 'required' ?>></div>
        <div class="col-md-6"><label class="form-label">Role</label><select name="role" class="form-select"><?= select_options(['admin' => 'Administrator', 'staff' => 'Staff'], $edit['role'] ?? 'admin', true) ?></select></div>
        <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="uActive" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="uActive">Active</label></div></div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <?php if ($edit): ?><a href="<?= url('admin/users.php') ?>" class="btn btn-light">Cancel</a><?php else: ?><span></span><?php endif; ?>
        <button class="btn btn-primary">Save user</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer();
