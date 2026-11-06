<?php
/** Streams a private document to logged-in admins only. */
require __DIR__ . '/includes/init.php';
require_admin();

$d = q_one('SELECT * FROM documents WHERE id = ?', [(int) get('id')]);
$path = $d ? realpath(DOC_DIR . '/' . $d['file']) : false;
$base = realpath(DOC_DIR);
if (!$d || !$path || !$base || strpos($path, $base) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$inlineTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain'];
$mime = $d['mime'] ?: 'application/octet-stream';
$inline = !get('dl') && in_array($mime, $inlineTypes, true);
$name = str_replace(['"', "\r", "\n"], '', $d['original_name']);

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($d['original_name']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
exit;
