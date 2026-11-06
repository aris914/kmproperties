<?php
require __DIR__ . '/includes/init.php';
if (!is_post()) {
    redirect('admin/index.php');
}
csrf_check();
$u = current_user();
if ($u) {
    audit('sign_out', 'session', $u['email'], null, $u);
}
session_end_row();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . url('admin/index.php'));
exit;
