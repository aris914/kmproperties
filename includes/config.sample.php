<?php
// Copy this file to includes/config.php and fill in your database login.
// config.php is never committed (see .gitignore).

define('DB_HOST', 'localhost');
define('DB_NAME', 'kmproperties');
define('DB_USER', 'root');
define('DB_PASS', '');

// Folder the site lives in, without a trailing slash.
// '' when the site is at the domain root (https://example.com),
// '/kmproperties' when it is at https://example.com/kmproperties
define('BASE_URL', '');

define('TIMEZONE', 'Asia/Manila');
define('DEBUG', false);

// Upload limits (your host may cap these lower; InfinityFree allows 10 MB)
define('MAX_PHOTO_MB', 8);
define('MAX_DOC_MB', 10);

// Emergency: locked out by Admin › Security rules? Uncomment, sign in, fix the rules, then comment it again.
// define('SECURITY_OFF', true);

// Only if the site sits behind Cloudflare or another proxy that sends the visitor's real IP address.
// define('TRUST_PROXY', true);
