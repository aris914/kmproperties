<?php
/** Keep-alive from admin.js: reports idle seconds, keeps Users online up to date, signs out idle or disconnected sessions. */
require dirname(__DIR__) . '/includes/bootstrap.php';
security_gate(false);
header('Content-Type: application/json');
header('Cache-Control: no-store');

$t = $_POST['csrf'] ?? '';
if (!is_post() || !is_string($t) || !hash_equals(csrf_token(), $t)) {
    echo json_encode(['ok' => false, 'reason' => 'session']);
    exit;
}
$u = current_user();
if (!$u) {
    echo json_encode(['ok' => false, 'reason' => !empty($_SESSION['kicked']) ? 'kicked' : 'signed_out']);
    exit;
}
$idle = isset($_POST['idle']) && ctype_digit((string) $_POST['idle']) ? min((int) $_POST['idle'], 31 * 86400) : 0;
if (!idle_check($idle)) {
    audit('sign_out', 'session', $u['email'], 'Signed out automatically after ' . security_cfg()['idle'] . ' minutes without activity', $u);
    echo json_encode(['ok' => false, 'reason' => 'idle']);
    exit;
}
session_touch(true);
echo json_encode(['ok' => true, 'idle_min' => security_cfg()['idle']]);
