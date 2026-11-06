<?php
/**
 * Loaded by every page: config, database, session and shared helpers.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_VERSION', '1.6.0');
define('DB_VERSION', '1.6.0'); // newest sql/update-x.y.z.sql

if (!file_exists(__DIR__ . '/config.php')) {
    $installer = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false ? '../' : '') . 'install.php';
    header('Location: ' . $installer);
    exit;
}
require __DIR__ . '/config.php';

date_default_timezone_set(defined('TIMEZONE') ? TIMEZONE : 'Asia/Manila');
if (defined('DEBUG') && DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

define('PHOTO_DIR', APP_ROOT . '/uploads/photos');
define('SITE_UPLOAD_DIR', APP_ROOT . '/uploads/site');
define('DOC_DIR', APP_ROOT . '/storage/documents');

require __DIR__ . '/security.php';
require __DIR__ . '/migrate.php';
require __DIR__ . '/theme.php';
require __DIR__ . '/rent.php';
require __DIR__ . '/sitelayout.php';
require __DIR__ . '/calendar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('kmup_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/* ---------- Database ---------- */

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            $pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $ex) {
            http_response_code(500);
            echo '<h1>Database connection failed</h1><p>Check the login in includes/config.php.</p>';
            if (defined('DEBUG') && DEBUG) {
                echo '<pre>' . htmlspecialchars($ex->getMessage()) . '</pre>';
            }
            exit;
        }
    }
    return $pdo;
}

function q($sql, array $params = [])
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function q_all($sql, array $params = [])
{
    return q($sql, $params)->fetchAll();
}

function q_one($sql, array $params = [])
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val($sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Insert a row from an associative array, returns the new id. */
function db_insert($table, array $data)
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function db_update($table, array $data, $id)
{
    $set = [];
    foreach (array_keys($data) as $c) {
        $set[] = '`' . $c . '` = ?';
    }
    $params = array_values($data);
    $params[] = $id;
    q('UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE id = ?', $params);
}

/* ---------- Output helpers ---------- */

function e($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function url($path = '')
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Make an admin-entered link safe: allow relative paths, http(s), mailto, tel. */
function safe_link($link)
{
    $link = trim((string) $link);
    if ($link === '') {
        return '#';
    }
    if (preg_match('~^(https?:|mailto:|tel:)~i', $link)) {
        return $link;
    }
    if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $link)) {
        return '#'; // javascript:, data: etc.
    }
    if ($link[0] === '#' || $link[0] === '/') {
        return $link;
    }
    return url($link);
}

function redirect($path)
{
    if (!preg_match('~^https?://~', $path) && strpos($path, BASE_URL . '/') !== 0) {
        $path = url($path);
    }
    header('Location: ' . $path);
    exit;
}

function money($amount, $decimals = null)
{
    $amount = (float) $amount;
    if ($decimals === null) {
        $decimals = (floor($amount) == $amount) ? 0 : 2;
    }
    return setting('currency', '₱') . number_format($amount, $decimals);
}

function fmt_date($date, $format = 'M j, Y')
{
    if (!$date || $date === '0000-00-00') {
        return '—';
    }
    return date($format, strtotime($date));
}

function nl2p($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $paras = preg_split("/\R{2,}/", $text);
    $out = '';
    foreach ($paras as $p) {
        $out .= '<p>' . nl2br(e(trim($p))) . '</p>';
    }
    return $out;
}

function lines($text)
{
    return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text))));
}

function slugify($text)
{
    $text = strtolower(trim((string) $text));
    $text = strtr($text, ['ñ' => 'n', 'Ñ' => 'n', '&' => ' and ']);
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text === '' ? 'item' : $text;
}

function unique_slug($table, $title, $excludeId = 0)
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (q_val("SELECT id FROM `$table` WHERE slug = ? AND id <> ?", [$slug, (int) $excludeId])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function post($key, $default = '')
{
    return isset($_POST[$key]) ? (is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key]) : $default;
}

function get($key, $default = '')
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/** Empty string -> NULL, for optional DB columns. */
function nn($v)
{
    $v = is_string($v) ? trim($v) : $v;
    return ($v === '' || $v === null) ? null : $v;
}

function num($v)
{
    $v = str_replace([',', ' ', '₱'], '', (string) $v);
    return is_numeric($v) ? (float) $v : 0;
}

function valid_date($v)
{
    $v = trim((string) $v);
    if ($v === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/* ---------- Settings ---------- */

function setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT setting_key, setting_value FROM settings') as $r) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        } catch (Exception $ex) {
            $cache = [];
        }
    }
    return (isset($cache[$key]) && $cache[$key] !== '') ? $cache[$key] : $default;
}

function save_setting($key, $value)
{
    q('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
}

/* ---------- Security ---------- */

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $t = $_POST['csrf'] ?? '';
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(400);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

function is_post()
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/* ---------- Flash messages ---------- */

function flash($type, $msg)
{
    $_SESSION['flash'][] = [$type, $msg];
}

function flashes_html()
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $type = $f[0] === 'error' ? 'danger' : $f[0];
        $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">' . e($f[1])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

/* ---------- Auth ---------- */

/**
 * The signed-in user, or null. A session ends when the user is disabled, their password
 * changed (other sessions), or an administrator disconnected it.
 */
function current_user($reload = false)
{
    static $user = false;
    if ($user === false || $reload) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $row = q_one('SELECT id, name, email, role, password_hash FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
            if (!$row || pw_version($row['password_hash']) !== ($_SESSION['pwv'] ?? '')) {
                session_end_row();
                unset($_SESSION['user_id'], $_SESSION['pwv']);
            } elseif (session_was_kicked()) {
                session_end_row();
                unset($_SESSION['user_id'], $_SESSION['pwv']);
                $_SESSION['kicked'] = 1;
            } else {
                unset($row['password_hash']);
                $user = $row;
            }
        }
    }
    return $user;
}

function require_admin($role = null)
{
    $u = current_user();
    if ($u && !idle_check()) {
        current_user(true);
        $u = null;
    }
    if (!$u) {
        redirect('admin/index.php');
    }
    session_touch();
    if ($role === 'admin' && $u['role'] !== 'admin') {
        flash('error', 'Only administrators can open that page.');
        redirect('admin/dashboard.php');
    }
    return $u;
}

/* ---------- Uploads ---------- */

/** Re-shapes $_FILES['x'] (single or multiple) into a list of files. */
function files_list($field)
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return $f['error'] === UPLOAD_ERR_NO_FILE ? [] : [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $out[] = ['name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function upload_error_text($code)
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'the file is larger than the server allows';
        case UPLOAD_ERR_PARTIAL:
            return 'the upload was interrupted';
        default:
            return 'the upload failed (code ' . (int) $code . ')';
    }
}

function detect_mime($path)
{
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $m = finfo_file($fi, $path);
        finfo_close($fi);
        return $m ?: 'application/octet-stream';
    }
    $info = @getimagesize($path);
    return $info ? $info['mime'] : 'application/octet-stream';
}

/**
 * Saves an uploaded image into $dir (resized to max 1920px when GD is available).
 * Returns the saved file name. Throws RuntimeException with a readable message.
 */
function save_image_upload(array $file, $dir, $maxSide = 1920)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($file['name'] . ': ' . upload_error_text($file['error']));
    }
    $maxMb = defined('MAX_PHOTO_MB') ? MAX_PHOTO_MB : 8;
    if ($file['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException($file['name'] . ': larger than ' . $maxMb . ' MB');
    }
    $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $mime = detect_mime($file['tmp_name']);
    if (!isset($types[$mime]) || !@getimagesize($file['tmp_name'])) {
        throw new RuntimeException($file['name'] . ': only JPG, PNG, WebP or GIF images are allowed');
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $ext = $types[$mime];
    $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;

    if (!resize_image($file['tmp_name'], $dest, $mime, $maxSide)) {
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new RuntimeException($file['name'] . ': could not be saved (check folder permissions)');
        }
    }
    return $name;
}

/** Returns true if the image was written to $dest by GD; false means "copy as-is". */
function resize_image($src, $dest, $mime, $maxSide)
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $info = @getimagesize($src);
    if (!$info) {
        return false;
    }
    list($w, $h) = $info;
    if ($w <= $maxSide && $h <= $maxSide && $mime !== 'image/jpeg') {
        return false;
    }
    switch ($mime) {
        case 'image/jpeg': $img = @imagecreatefromjpeg($src); break;
        case 'image/png': $img = @imagecreatefrompng($src); break;
        case 'image/webp': $img = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false; break;
        default: return false; // keep GIFs untouched (animation)
    }
    if (!$img) {
        return false;
    }
    // Respect camera orientation for JPEGs
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($src);
        $o = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
        $rot = [3 => 180, 6 => -90, 8 => 90];
        if (isset($rot[$o])) {
            $img = imagerotate($img, $rot[$o], 0);
            $w = imagesx($img);
            $h = imagesy($img);
        }
    }
    $scale = min(1, $maxSide / max($w, $h));
    $nw = (int) round($w * $scale);
    $nh = (int) round($h * $scale);
    $out = imagecreatetruecolor($nw, $nh);
    if ($mime !== 'image/jpeg') {
        imagealphablending($out, false);
        imagesavealpha($out, true);
    }
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $ok = false;
    switch ($mime) {
        case 'image/jpeg': $ok = imagejpeg($out, $dest, 85); break;
        case 'image/png': $ok = imagepng($out, $dest, 6); break;
        case 'image/webp': $ok = imagewebp($out, $dest, 85); break;
    }
    imagedestroy($img);
    imagedestroy($out);
    return $ok;
}

function doc_allowed_types()
{
    return [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp'],
        'txt' => ['text/plain'], 'csv' => ['text/plain', 'text/csv'],
    ];
}

/** Saves an uploaded document privately. Returns [stored name, mime]. */
function save_document_upload(array $file)
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($file['name'] . ': ' . upload_error_text($file['error']));
    }
    $maxMb = defined('MAX_DOC_MB') ? MAX_DOC_MB : 10;
    if ($file['size'] > $maxMb * 1024 * 1024) {
        throw new RuntimeException($file['name'] . ': larger than ' . $maxMb . ' MB');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = doc_allowed_types();
    $mime = detect_mime($file['tmp_name']);
    if (!isset($allowed[$ext]) || !in_array($mime, $allowed[$ext], true)) {
        throw new RuntimeException($file['name'] . ': file type not allowed (PDF, Word, Excel, images, TXT/CSV only)');
    }
    $sub = date('Y/m');
    $dir = DOC_DIR . '/' . $sub;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = $sub . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], DOC_DIR . '/' . $name)) {
        throw new RuntimeException($file['name'] . ': could not be saved (check folder permissions)');
    }
    return [$name, $mime];
}

function human_size($bytes)
{
    $bytes = (int) $bytes;
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
}

function delete_file_safe($path)
{
    if ($path && is_file($path)) {
        @unlink($path);
    }
}

/* ---------- Domain helpers ---------- */

function photo_url($file)
{
    return $file ? url('uploads/photos/' . rawurlencode($file)) : url('assets/img/placeholder.jpg');
}

function site_image_url($file, $fallback = 'assets/img/hero.jpg')
{
    return $file ? url('uploads/site/' . rawurlencode($file)) : url($fallback);
}

function property_cover($propertyId)
{
    return q_val('SELECT file FROM property_photos WHERE property_id = ? ORDER BY is_cover DESC, sort_order, id LIMIT 1', [$propertyId]);
}

function property_price_label(array $p)
{
    $s = money($p['price']);
    if ($p['listing_type'] === 'rent') {
        $s .= ' <small>/ ' . e($p['price_period'] ?: 'month') . '</small>';
    }
    return $s;
}

/** Leaflet (map) CSS and JS tags, plus the map/photo picker script. */
function map_assets()
{
    return [
        '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">',
        '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>'
        . '<script src="' . url('assets/js/map-picker.js?v=' . APP_VERSION) . '"></script>',
    ];
}

/** SQL condition for listings the public may see. */
function public_sql($alias = 'p')
{
    return "$alias.approval = 'approved' AND $alias.is_visible = 1";
}

function amenity_choices()
{
    return ['Swimming pool', 'Gym', '24/7 security', 'Parking', 'Balcony', 'City view', 'Function room', 'Playground', 'Pet-friendly', 'Elevator', 'Back-up generator', 'Near MRT/LRT', 'Near mall', 'High-speed internet ready', 'Laundry area', 'Concierge'];
}

function unit_types()
{
    return ['Studio', '1 Bedroom', '2 Bedroom', '3 Bedroom', 'Loft', 'Penthouse'];
}

function property_types()
{
    return ['Condominium', 'Townhouse', 'House & Lot', 'Office Space', 'Commercial'];
}

/** After a lease ends: free the property and mark the tenant former, unless they have another active lease. */
function release_after_lease($propertyId, $tenantId)
{
    if (!q_val("SELECT id FROM leases WHERE property_id = ? AND status = 'active'", [$propertyId])) {
        q("UPDATE properties SET status = 'available' WHERE id = ? AND status IN ('rented','reserved')", [$propertyId]);
    }
    if (!q_val("SELECT id FROM leases WHERE tenant_id = ? AND status IN ('active','upcoming')", [$tenantId])) {
        q("UPDATE tenants SET status = 'former' WHERE id = ? AND status = 'active'", [$tenantId]);
    }
}

function ordinal_suffix($n)
{
    $n = (int) $n;
    if (in_array($n % 100, [11, 12, 13], true)) {
        return 'th';
    }
    $s = [1 => 'st', 2 => 'nd', 3 => 'rd'];
    return isset($s[$n % 10]) ? $s[$n % 10] : 'th';
}

function tenant_name(array $t)
{
    return trim(($t['first_name'] ?? '') . ' ' . ($t['last_name'] ?? ''));
}

/**
 * Records an event on the property and/or tenant timeline.
 */
function log_history($propertyId, $tenantId, $type, $summary, $details = null, $date = null)
{
    $u = current_user();
    db_insert('history', [
        'property_id' => $propertyId ? (int) $propertyId : null,
        'tenant_id' => $tenantId ? (int) $tenantId : null,
        'event_type' => $type,
        'summary' => mb_substr($summary, 0, 255),
        'details' => nn($details),
        'event_date' => $date ?: date('Y-m-d'),
        'user_id' => $u ? $u['id'] : null,
    ]);
}

function history_icon($type)
{
    $map = [
        'created' => 'bi-plus-circle', 'updated' => 'bi-pencil', 'status' => 'bi-arrow-repeat',
        'lease_start' => 'bi-key', 'lease_end' => 'bi-door-open', 'lease_update' => 'bi-file-earmark-text',
        'payment' => 'bi-cash-coin', 'maintenance' => 'bi-tools', 'maintenance_done' => 'bi-check2-circle',
        'document' => 'bi-paperclip', 'photo' => 'bi-images', 'note' => 'bi-chat-left-text', 'inquiry' => 'bi-envelope',
    ];
    return isset($map[$type]) ? $map[$type] : 'bi-dot';
}

function status_badge($status)
{
    $map = [
        'available' => 'success', 'reserved' => 'warning', 'rented' => 'primary', 'sold' => 'dark', 'inactive' => 'secondary',
        'prospect' => 'info', 'active' => 'success', 'former' => 'secondary', 'blacklisted' => 'danger',
        'ended' => 'secondary', 'terminated' => 'danger', 'upcoming' => 'info',
        'open' => 'danger', 'scheduled' => 'info', 'in_progress' => 'warning', 'completed' => 'success', 'cancelled' => 'secondary',
        'new' => 'danger', 'contacted' => 'warning', 'closed' => 'secondary',
        'low' => 'secondary', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger',
    ];
    $c = isset($map[$status]) ? $map[$status] : 'secondary';
    return '<span class="badge text-bg-' . $c . '">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function select_options(array $options, $selected, $assoc = false)
{
    $html = '';
    foreach ($options as $k => $v) {
        $val = $assoc ? $k : $v;
        $html .= '<option value="' . e($val) . '"' . ((string) $val === (string) $selected ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    return $html;
}

/* ---------- Automatic database updates (see includes/migrate.php) ---------- */
db_migrate();

/* ---------- Security gate for the public website (Admin › Security › "Whole site") ---------- */
if (strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') === false) {
    security_gate(true);
}
