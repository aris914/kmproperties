<?php
require __DIR__ . '/includes/init.php';

if (current_user()) {
    redirect('admin/dashboard.php');
}

$error = '';
$_SESSION['login_fails'] = $_SESSION['login_fails'] ?? 0;
$_SESSION['login_lock'] = $_SESSION['login_lock'] ?? 0;

if (is_post()) {
    csrf_check();
    if ($_SESSION['login_lock'] > time()) {
        $error = 'Too many failed attempts. Please wait a few minutes and try again.';
    } else {
        $email = post('email');
        $pass = (string) ($_POST['password'] ?? '');
        $user = q_one('SELECT * FROM users WHERE email = ? AND is_active = 1', [$email]);
        if ($user && password_verify($pass, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['login_fails'] = 0;
            q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
            }
            redirect('admin/dashboard.php');
        }
        $_SESSION['login_fails']++;
        if ($_SESSION['login_fails'] >= 5) {
            $_SESSION['login_lock'] = time() + 300;
            $_SESSION['login_fails'] = 0;
        }
        usleep(400000);
        $error = 'Incorrect email or password.';
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin login · <?= e(setting('site_name', 'K&M Urban Properties')) ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.png') ?>">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/admin.css?v=' . APP_VERSION) ?>" rel="stylesheet">
</head>
<body class="login-page">
  <div class="login-card">
    <div class="login-brand"><img src="<?= url('assets/img/logo-dark.png') ?>" alt="K&amp;M Urban Properties"></div>
    <div class="p-4 p-md-5">
      <h1 class="h4 mb-1">Welcome back</h1>
      <p class="text-muted mb-4">Sign in to manage your properties.</p>
      <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
      <?= flashes_html() ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control form-control-lg" required autofocus value="<?= e(post('email')) ?>"></div>
        <div class="mb-4"><label class="form-label">Password</label><input type="password" name="password" class="form-control form-control-lg" required></div>
        <button class="btn btn-gold btn-lg w-100">Sign in</button>
      </form>
      <div class="text-center mt-4"><a href="<?= url('index.php') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Back to website</a></div>
    </div>
  </div>
</body>
</html>
