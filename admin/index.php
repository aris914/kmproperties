<?php
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('admin/dashboard.php');
}

$error = '';
$notice = '';
if (!empty($_SESSION['idle_out'])) {
    $notice = 'You were signed out after ' . (int) $_SESSION['idle_out'] . ' minutes without activity.';
    unset($_SESSION['idle_out']);
} elseif (!empty($_SESSION['kicked'])) {
    $notice = 'Your session was ended by an administrator. Please sign in again.';
    unset($_SESSION['kicked']);
}

if (is_post()) {
    csrf_check();
    $email = mb_substr(post('email'), 0, 190);
    $pass = (string) ($_POST['password'] ?? '');
    $mins = login_locked_minutes($email);
    if ($mins > 0) {
        $error = 'Too many failed sign-ins. Please try again in ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . '.';
    } else {
        $user = q_one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($user && $user['is_active'] && password_verify($pass, $user['password_hash'])) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $user['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
                q('UPDATE users SET password_hash = ? WHERE id = ?', [$user['password_hash'], $user['id']]);
            }
            login_success($email);
            session_sign_in($user);
            q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
            $geo = ip_country(client_ip());
            audit('sign_in', 'session', $user['email'], $geo ? trim(cc_flag($geo['cc']) . ' ' . $geo['country'] . ($geo['place'] ? ', ' . $geo['place'] : '')) : null, $user);
            redirect('admin/dashboard.php');
        }
        $why = !$user ? 'unknown email' : (!$user['is_active'] ? 'account disabled' : 'wrong password');
        audit('failed', 'session', $email, 'Failed sign-in (' . $why . ')', $user ? $user : null);
        $locked = login_fail($email);
        usleep(400000);
        $error = $locked
            ? 'Too many failed sign-ins. Sign-in is locked for ' . security_cfg()['lock']['mins'] . ' minute(s).'
            : 'Incorrect email or password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin login · <?= e(setting('site_name', 'K&M Urban Properties')) ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.png') ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/admin.css?v=' . APP_VERSION) ?>" rel="stylesheet">
<?= theme_head() ?>
</head>
<body class="login-page">
  <header class="adm-head"><a class="adm-logo" href="<?= url('index.php') ?>" title="Back to website"><img src="<?= e(setting('logo_dark') ? site_image_url(setting('logo_dark')) : url('assets/img/logo-dark.png?v=' . APP_VERSION)) ?>" alt="<?= e(setting('site_name', 'K&M Urban Properties')) ?>"></a></header>
  <div class="login-wrap">
    <div class="login-card">
      <h1>Sign in</h1>
      <p class="hint mb-4">Admin panel · <?= e(setting('site_name', 'K&M Urban Properties')) ?></p>
      <?php if ($notice): ?><div class="alert alert-info py-2 small"><i class="bi bi-info-circle me-1"></i><?= e($notice) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= e($error) ?></div><?php endif; ?>
      <?= flashes_html() ?>
      <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="em">Email</label><input type="email" id="em" name="email" class="form-control" required autofocus autocomplete="username" value="<?= e(post('email')) ?>"></div>
        <div class="mb-4"><label class="form-label" for="pw">Password</label><input type="password" id="pw" name="password" class="form-control" required autocomplete="current-password"></div>
        <button class="btn btn-primary w-100 py-2">Sign in</button>
      </form>
      <div class="text-center mt-4"><a href="<?= url('index.php') ?>" class="small"><i class="bi bi-arrow-left"></i> Back to website</a></div>
    </div>
  </div>
  <footer class="adm-foot">© <?= date('Y') ?> – <?= e(setting('site_name', 'K&M Urban Properties')) ?></footer>
</body>
</html>
