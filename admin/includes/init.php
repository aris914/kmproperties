<?php
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require __DIR__ . '/layout.php';

// Admin pages: IP / country rules (Admin › Security) and browser security headers
security_gate(false);
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('Cache-Control: no-store, private');
