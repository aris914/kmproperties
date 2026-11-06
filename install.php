<?php
/**
 * One-time installer: checks the config, creates the tables, loads default
 * content and creates the first administrator. Delete this file after use.
 */
ini_set('display_errors', '1');
$root = __DIR__;
$configFile = $root . '/includes/config.php';
$errors = [];
$done = false;

function inst_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

function run_sql_file(PDO $pdo, $file)
{
    $sql = file_get_contents($file);
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

$checks = [
    'PHP 7.4 or newer' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL extension' => extension_loaded('pdo_mysql'),
    'GD image extension (photo resizing — optional)' => extension_loaded('gd'),
    'fileinfo extension (upload checks — recommended)' => extension_loaded('fileinfo'),
    'uploads/ folder writable' => is_writable($root . '/uploads'),
    'storage/ folder writable' => is_writable($root . '/storage'),
    'includes/config.php exists' => file_exists($configFile),
];

$pdo = null;
$installed = false;
if (file_exists($configFile)) {
    require $configFile;
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $checks['Database connection'] = true;
        $installed = (bool) $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn()
            && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
    } catch (PDOException $ex) {
        $checks['Database connection'] = false;
        $errors[] = 'Database: ' . $ex->getMessage();
    }
}

if (!$installed && $pdo && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a name and a valid email.';
    }
    if (strlen($pass) < 8) {
        $errors[] = 'The password must be at least 8 characters.';
    }
    if (!$errors) {
        try {
            run_sql_file($pdo, $root . '/sql/schema.sql');
            if (!(int) $pdo->query('SELECT COUNT(*) FROM landing_sections')->fetchColumn()) {
                run_sql_file($pdo, $root . '/sql/seed.sql');
            }
            if (!empty($_POST['sample']) && !(int) $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn()) {
                run_sql_file($pdo, $root . '/sql/sample-data.sql');
            }
            // the full schema already contains every update: start at the newest database version
            if (preg_match("/define\('DB_VERSION', '([0-9.]+)'\)/", (string) file_get_contents($root . '/includes/bootstrap.php'), $dv)) {
                $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)')->execute(['db_version', $dv[1]]);
            }
            $st = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');
            $st->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
            $done = true;
        } catch (PDOException $ex) {
            $errors[] = 'Install failed: ' . $ex->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install — K&amp;M Urban Properties</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f6f9}.brand{background:#002c4c}</style>
</head>
<body>
<div class="container py-5" style="max-width:720px">
  <div class="brand rounded-top p-3 text-center"><img src="assets/img/logo-dark.png" alt="K&amp;M Urban Properties" height="70"></div>
  <div class="card rounded-0 rounded-bottom shadow-sm"><div class="card-body p-4">
  <h1 class="h4 mb-3">Website installer</h1>

  <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= inst_e($err) ?></div><?php endforeach; ?>

  <?php if ($done): ?>
    <div class="alert alert-success"><strong>Installed.</strong> Your administrator account is ready.</div>
    <p class="fw-semibold text-danger">For security, delete <code>install.php</code> from the server now.</p>
    <a class="btn btn-primary" href="admin/index.php">Go to admin login</a>
    <a class="btn btn-outline-secondary" href="index.php">View website</a>
  <?php elseif ($installed): ?>
    <div class="alert alert-info">The website is already installed. Delete <code>install.php</code> from the server.</div>
    <a class="btn btn-primary" href="admin/index.php">Admin login</a>
  <?php else: ?>
    <h2 class="h6 text-uppercase text-muted">Server check</h2>
    <ul class="list-group mb-4">
      <?php foreach ($checks as $label => $ok): ?>
        <li class="list-group-item d-flex justify-content-between"><?= inst_e($label) ?>
          <span class="badge text-bg-<?= $ok ? 'success' : (strpos($label, 'optional') || strpos($label, 'recommended') ? 'warning' : 'danger') ?>"><?= $ok ? 'OK' : 'Missing' ?></span></li>
      <?php endforeach; ?>
    </ul>

    <?php if (!file_exists($configFile)): ?>
      <div class="alert alert-warning">Copy <code>includes/config.sample.php</code> to <code>includes/config.php</code>, enter your MySQL database name, user and password, then reload this page.</div>
    <?php elseif ($pdo): ?>
      <h2 class="h6 text-uppercase text-muted">First administrator</h2>
      <form method="post" class="row g-3">
        <div class="col-md-6"><label class="form-label">Full name</label><input name="name" class="form-control" required value="<?= inst_e($_POST['name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Email (used to log in)</label><input type="email" name="email" class="form-control" required value="<?= inst_e($_POST['email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Password (8+ characters)</label><input type="password" name="password" class="form-control" minlength="8" required></div>
        <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="sample" value="1" id="sample" checked><label class="form-check-label" for="sample">Add 6 sample listings (you can delete them later)</label></div>
        <div class="col-12"><button class="btn btn-primary px-4">Install</button></div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
  </div></div>
</div>
</body>
</html>
