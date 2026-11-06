<?php
require __DIR__ . '/includes/init.php';
$me = require_admin();

if (is_post()) {
    csrf_check();
    if (post('action') === 'others_out') {
        sessions_kick_user($me['id'], true);
        audit('kick', 'session', $me['email'], 'Signed out all other sessions');
        flash('success', 'Your other sessions were signed out.');
        redirect('admin/profile.php');
    }
    $row = q_one('SELECT password_hash FROM users WHERE id = ?', [$me['id']]);
    $current = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $name = post('name');
    if (!password_verify($current, $row['password_hash'])) {
        audit('failed', 'users', $me['email'], 'Wrong current password on My account');
        flash('error', 'Your current password is incorrect.');
    } elseif ($new !== '' && strlen($new) < 8) {
        flash('error', 'The new password must be at least 8 characters.');
    } elseif ($new !== '' && $new !== ($_POST['confirm'] ?? '')) {
        flash('error', 'The new passwords do not match.');
    } elseif ($new !== '' && password_verify($new, $row['password_hash'])) {
        flash('error', 'The new password is the same as the current one.');
    } else {
        $data = ['name' => $name ?: $me['name']];
        if ($new !== '') {
            $data['password_hash'] = password_hash($new, PASSWORD_DEFAULT);
        }
        db_update('users', $data, $me['id']);
        if ($new !== '') {
            // stay signed in here; every other session of this account ends
            $_SESSION['pwv'] = pw_version($data['password_hash']);
            sessions_kick_user($me['id'], true);
        }
        audit('update', 'users', $me['email'], array_filter([$data['name'] !== $me['name'] ? 'Name changed' : null, $new !== '' ? 'Password changed; other sessions signed out' : null]) ?: 'Saved');
        flash('success', $new !== '' ? 'Your password was changed. Your other sessions were signed out.' : 'Your account was updated.');
    }
    redirect('admin/profile.php');
}

$sessions = [];
$activity = [];
try {
    $sessions = q_all('SELECT * FROM user_sessions WHERE user_id = ? AND kicked_at IS NULL AND last_seen > NOW() - INTERVAL 2 DAY ORDER BY last_seen DESC', [$me['id']]);
    $activity = q_all("SELECT * FROM audit_log WHERE area = 'session' AND (user_id = ? OR record = ?) AND action IN ('sign_in','failed','locked','sign_out') ORDER BY at DESC LIMIT 10", [$me['id'], $me['email']]);
} catch (Exception $ex) {
}

admin_header('My Account');
?>
<div class="row g-4">
  <div class="col-xl-6">
    <form method="post" class="card">
      <?= csrf_field() ?>
      <div class="card-header">Profile &amp; password</div>
      <div class="card-body row g-3">
        <div class="col-12"><label class="form-label">Name</label><input name="name" class="form-control" value="<?= e($me['name']) ?>"></div>
        <div class="col-12"><label class="form-label">Email</label><input class="form-control" value="<?= e($me['email']) ?>" disabled></div>
        <div class="col-md-6"><label class="form-label">New password (optional)</label><input type="password" name="new" class="form-control" minlength="8" autocomplete="new-password"></div>
        <div class="col-md-6"><label class="form-label">Repeat new password</label><input type="password" name="confirm" class="form-control" autocomplete="new-password"></div>
        <div class="col-12"><label class="form-label">Current password (required to save)</label><input type="password" name="current" class="form-control" required autocomplete="current-password"></div>
        <div class="col-12 hint">Changing your password signs out your account everywhere else.</div>
      </div>
      <div class="card-footer text-end"><button class="btn btn-primary">Save</button></div>
    </form>
  </div>
  <div class="col-xl-6">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center"><span>Your sessions</span>
        <?php if (count($sessions) > 1): ?><form method="post" onsubmit="return confirm('Sign out all your other sessions?')"><?= csrf_field() ?><input type="hidden" name="action" value="others_out"><button class="btn btn-sm btn-outline-danger">Sign out other sessions</button></form><?php endif; ?></div>
      <div class="table-responsive"><table class="table table-sm align-middle">
        <tbody>
        <?php if (!$sessions): ?><tr><td class="text-muted p-3">No session information yet.</td></tr><?php endif; ?>
        <?php foreach ($sessions as $s): $g = ip_country_cached($s['ip']); ?>
          <tr>
            <td><b><?= e(ua_short($s['user_agent'])) ?></b><?= $s['session_key'] === session_key() ? ' <span class="pill act">this device</span>' : '' ?><div class="hint"><code><?= e($s['ip']) ?></code><?= $g ? ' · ' . cc_flag($g['cc']) . ' ' . e($g['country']) : '' ?></div></td>
            <td class="small text-end">since <?= fmt_date($s['signed_in_at'], 'M j, g:i A') ?><div class="hint">last seen <?= fmt_date($s['last_seen'], 'M j, g:i A') ?></div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <div class="card-header">Recent sign-in activity</div>
      <div class="table-responsive"><table class="table table-sm align-middle">
        <tbody>
        <?php if (!$activity): ?><tr><td class="text-muted p-3">Nothing recorded yet.</td></tr><?php endif; ?>
        <?php foreach ($activity as $a): ?>
          <tr>
            <td class="small text-nowrap"><?= fmt_date($a['at'], 'M j, g:i A') ?></td>
            <td><span class="pill <?= in_array($a['action'], ['failed', 'locked'], true) ? 'sus' : ($a['action'] === 'sign_in' ? 'act' : '') ?>"><?= e(['sign_in' => 'Signed in', 'failed' => 'Failed', 'locked' => 'Locked', 'sign_out' => 'Signed out'][$a['action']]) ?></span></td>
            <td class="small"><code><?= e($a['ip']) ?></code><?php if ($a['details']): ?><div class="hint"><?= e($a['details']) ?></div><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div class="card-footer hint">If you see a sign-in you don’t recognise, change your password right away.</div>
    </div>
  </div>
</div>
<?php admin_footer();
