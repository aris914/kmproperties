<?php
/* =====================================================================
   Admin security (1.1.0) — modelled on the RBJocsing portal.

   Who may reach the admin panel (or the whole site), checked on every request:
     1. Security off (Admin › Security, or define('SECURITY_OFF', true) in includes/config.php) → allowed
     2. Local / private network addresses (127.0.0.1, 192.168.x.x, 10.x.x.x …)                 → allowed
     3. "Always allow" list (whitelist)                                                        → allowed
     4. "Blocked" list (blacklist, bans)                                                       → refused
     5. Country rule: allow only the listed countries, or block the listed ones;
        when the country cannot be found, the "unknown" setting decides.
   Also here: failed sign-in lockouts, idle sign-out, signed-in sessions (Users online,
   Disconnect) and the audit log.
   Settings: settings.security (JSON). Countries come from ipwho.is / ipapi.co / ip-api.com
   and are kept in ip_geo for 30 days. Refused requests go to security_blocks.
   ===================================================================== */

function security_defaults()
{
    return [
        'on' => false, 'scope' => 'admin', 'allow' => [], 'block' => [], 'idle' => 45,
        'lock' => ['tries' => 5, 'mins' => 3],
        'geo' => ['mode' => 'off', 'list' => [], 'unknown' => 'allow'],
    ];
}

/** Saved settings merged with the defaults. */
function security_cfg($fresh = false)
{
    static $c = null;
    if ($c !== null && !$fresh) {
        return $c;
    }
    $s = [];
    try {
        $v = q_val("SELECT setting_value FROM settings WHERE setting_key = 'security'");
        $s = $v ? (json_decode((string) $v, true) ?: []) : [];
    } catch (Exception $ex) {
        $s = [];
    }
    $c = security_clean($s);
    return $c;
}

/** Keeps only valid values (also used when saving). */
function security_clean(array $s)
{
    $rules = function ($L) {
        $L = is_array($L) ? $L : preg_split('/\R/', (string) $L);
        $out = [];
        foreach ($L as $x) {
            $x = mb_substr(trim((string) $x), 0, 200);
            if ($x !== '') {
                $out[] = $x;
            }
        }
        return array_slice($out, 0, 500);
    };
    $g = isset($s['geo']) && is_array($s['geo']) ? $s['geo'] : [];
    $cc = [];
    foreach ((isset($g['list']) && is_array($g['list']) ? $g['list'] : []) as $x) {
        $x = strtoupper(trim((string) $x));
        if (preg_match('/^[A-Z]{2}$/', $x) && !in_array($x, $cc, true)) {
            $cc[] = $x;
        }
    }
    $lock = isset($s['lock']) && is_array($s['lock']) ? $s['lock'] : [];
    return [
        'on' => !empty($s['on']),
        'scope' => (isset($s['scope']) && $s['scope'] === 'all') ? 'all' : 'admin',
        'allow' => $rules(isset($s['allow']) ? $s['allow'] : []),
        'block' => $rules(isset($s['block']) ? $s['block'] : []),
        'idle' => max(15, min(90, (int) (isset($s['idle']) ? $s['idle'] : 45) ?: 45)),
        'lock' => [
            'tries' => max(3, min(20, (int) (isset($lock['tries']) ? $lock['tries'] : 5) ?: 5)),
            'mins' => max(1, min(60, (int) (isset($lock['mins']) ? $lock['mins'] : 3) ?: 3)),
        ],
        'geo' => [
            'mode' => (isset($g['mode']) && in_array($g['mode'], ['allow', 'block'], true)) ? $g['mode'] : 'off',
            'list' => $cc,
            'unknown' => (isset($g['unknown']) && $g['unknown'] === 'deny') ? 'deny' : 'allow',
        ],
    ];
}

function security_save(array $cfg)
{
    $cfg = security_clean($cfg);
    save_setting('security', json_encode($cfg));
    security_cfg(true);
    return $cfg;
}

function security_off()
{
    return defined('SECURITY_OFF') && SECURITY_OFF;
}

/** The visitor's address (set define('TRUST_PROXY', true) only if the host sits behind Cloudflare or another proxy). */
function client_ip()
{
    $ip = '';
    if (defined('TRUST_PROXY') && TRUST_PROXY) {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = trim(explode(',', $_SERVER[$h])[0]);
                break;
            }
        }
    }
    if ($ip === '') {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    }
    $ip = substr($ip, 0, 45);
    if (stripos($ip, '::ffff:') === 0 && strpos($ip, '.') !== false) {
        $ip = substr($ip, 7);
    }
    return $ip;
}

function security_is_local($ip)
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false
        && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/** Does the address match one rule: an exact address, a CIDR range (a.b.c.d/nn) or a * wildcard? Text after # is a note. */
function ip_rule_match($ip, $rule)
{
    $rule = trim((string) preg_replace('/#.*$/', '', $rule));
    if ($rule === '' || $ip === '') {
        return false;
    }
    if (strpos($rule, '*') !== false) {
        return (bool) preg_match('/^' . str_replace('\*', '[0-9a-f]{1,4}', preg_quote($rule, '/')) . '$/i', $ip);
    }
    if (strpos($rule, '/') !== false) {
        list($net, $bits) = explode('/', $rule, 2);
        $a = @inet_pton($ip);
        $b = @inet_pton(trim($net));
        $bits = trim($bits);
        if ($a === false || $b === false || strlen($a) !== strlen($b) || !ctype_digit($bits)) {
            return false;
        }
        $bits = (int) $bits;
        if ($bits > strlen($a) * 8) {
            return false;
        }
        $full = intdiv($bits, 8);
        $rest = $bits % 8;
        if (substr($a, 0, $full) !== substr($b, 0, $full)) {
            return false;
        }
        if ($rest === 0) {
            return true;
        }
        $m = (0xff << (8 - $rest)) & 0xff;
        return (ord($a[$full]) & $m) === (ord($b[$full]) & $m);
    }
    $a = @inet_pton($ip);
    $b = @inet_pton($rule);
    return $a !== false && $a === $b;
}

/** Is a rule line valid? An address, a CIDR range or a * wildcard, optionally followed by # a note. */
function security_rule_ok($rule)
{
    $r = trim((string) preg_replace('/#.*$/', '', $rule));
    if ($r === '') {
        return true;
    }
    if (strpos($r, '*') !== false) {
        return (bool) preg_match('/^[0-9a-f:.*]+$/i', $r) && filter_var(str_replace('*', '1', $r), FILTER_VALIDATE_IP) !== false;
    }
    if (strpos($r, '/') !== false) {
        list($n, $b) = explode('/', $r, 2);
        $ip = filter_var(trim($n), FILTER_VALIDATE_IP);
        return $ip !== false && ctype_digit(trim($b)) && (int) $b <= (strpos($ip, ':') !== false ? 128 : 32);
    }
    return filter_var($r, FILTER_VALIDATE_IP) !== false;
}

function security_http($url)
{
    $body = null;
    if (function_exists('curl_init')) {
        $h = curl_init($url);
        curl_setopt_array($h, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 4, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'KMUrbanProperties/1.0', CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $b = curl_exec($h);
        if ($b !== false && curl_getinfo($h, CURLINFO_RESPONSE_CODE) < 400) {
            $body = (string) $b;
        }
        curl_close($h);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'header' => "User-Agent: KMUrbanProperties/1.0\r\nAccept: application/json\r\n"]]);
        $b = @file_get_contents($url, false, $ctx);
        if ($b !== false) {
            $body = (string) $b;
        }
    }
    if ($body === null) {
        return null;
    }
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

/** Country of an address: ['cc' => 'PH', 'country' => 'Philippines', 'place' => …, 'isp' => …] or null. */
function ip_country($ip, $useCache = true)
{
    if ($ip === '' || security_is_local($ip)) {
        return null;
    }
    if ($useCache) {
        try {
            $r = q_one('SELECT cc, country, place, isp, fetched_at FROM ip_geo WHERE ip = ?', [$ip]);
            if ($r) {
                $age = time() - strtotime($r['fetched_at']);
                if ($r['cc'] !== null && $age < 30 * 86400) {
                    return ['cc' => $r['cc'], 'country' => $r['country'], 'place' => $r['place'], 'isp' => $r['isp']];
                }
                if ($r['cc'] === null && $age < 3600) {
                    return null; // the lookup failed less than an hour ago
                }
            }
        } catch (Exception $ex) {
            // table missing: look it up without keeping it
        }
    }
    $e = rawurlencode($ip);
    $out = null;
    $j = security_http("https://ipwho.is/$e");
    if ($j && (!isset($j['success']) || $j['success'] !== false) && !empty($j['country_code'])) {
        $out = ['cc' => $j['country_code'], 'country' => $j['country'] ?? '', 'place' => implode(', ', array_filter([$j['city'] ?? '', $j['region'] ?? ''])), 'isp' => $j['connection']['isp'] ?? ($j['connection']['org'] ?? '')];
    } else {
        $j = security_http("https://ipapi.co/$e/json/");
        if ($j && empty($j['error']) && !empty($j['country_code'])) {
            $out = ['cc' => $j['country_code'], 'country' => $j['country_name'] ?? '', 'place' => implode(', ', array_filter([$j['city'] ?? '', $j['region'] ?? ''])), 'isp' => $j['org'] ?? ''];
        } else {
            $j = security_http("http://ip-api.com/json/$e?fields=status,countryCode,country,regionName,city,isp");
            if ($j && ($j['status'] ?? '') === 'success') {
                $out = ['cc' => $j['countryCode'], 'country' => $j['country'] ?? '', 'place' => implode(', ', array_filter([$j['city'] ?? '', $j['regionName'] ?? ''])), 'isp' => $j['isp'] ?? ''];
            }
        }
    }
    if ($out) {
        foreach ($out as $k => $v) {
            $out[$k] = mb_substr((string) $v, 0, 120);
        }
        $out['cc'] = strtoupper(substr($out['cc'], 0, 2));
    }
    try {
        q('INSERT INTO ip_geo (ip, cc, country, place, isp, fetched_at) VALUES (?,?,?,?,?,NOW())
           ON DUPLICATE KEY UPDATE cc = VALUES(cc), country = VALUES(country), place = VALUES(place), isp = VALUES(isp), fetched_at = VALUES(fetched_at)',
            [$ip, $out['cc'] ?? null, $out['country'] ?? null, $out['place'] ?? null, $out['isp'] ?? null]);
    } catch (Exception $ex) {
    }
    return $out;
}

/** Cached country only (no web lookup) — for lists. */
function ip_country_cached($ip)
{
    try {
        $r = q_one('SELECT cc, country, place, isp FROM ip_geo WHERE ip = ? AND cc IS NOT NULL', [$ip]);
        return $r ?: null;
    } catch (Exception $ex) {
        return null;
    }
}

/** May this address in? ['ok' => bool, 'why' => text, 'geo' => country or null] */
function security_verdict($ip, $c = null)
{
    $c = $c ?: security_cfg();
    if (security_off()) {
        return ['ok' => true, 'why' => 'Security is turned off in includes/config.php', 'geo' => null];
    }
    if (!$c['on']) {
        return ['ok' => true, 'why' => 'Security rules are off', 'geo' => null];
    }
    if ($ip === '') {
        return ['ok' => true, 'why' => 'No address', 'geo' => null];
    }
    if (security_is_local($ip)) {
        return ['ok' => true, 'why' => 'Local network address', 'geo' => null];
    }
    foreach ($c['allow'] as $r) {
        if (ip_rule_match($ip, $r)) {
            return ['ok' => true, 'why' => 'On the Always allow list (' . trim($r) . ')', 'geo' => null];
        }
    }
    foreach ($c['block'] as $r) {
        if (ip_rule_match($ip, $r)) {
            return ['ok' => false, 'why' => 'On the Blocked list (' . trim($r) . ')', 'geo' => null];
        }
    }
    if ($c['geo']['mode'] === 'off') {
        return ['ok' => true, 'why' => 'Not on any list', 'geo' => null];
    }
    $g = ip_country($ip);
    if (!$g) {
        return $c['geo']['unknown'] === 'deny'
            ? ['ok' => false, 'why' => 'Country could not be found', 'geo' => null]
            : ['ok' => true, 'why' => 'Country could not be found (allowed)', 'geo' => null];
    }
    $in = in_array($g['cc'], $c['geo']['list'], true);
    if ($c['geo']['mode'] === 'allow') {
        return $in ? ['ok' => true, 'why' => 'Allowed country (' . $g['cc'] . ')', 'geo' => $g]
                   : ['ok' => false, 'why' => 'Country not allowed (' . $g['cc'] . ')', 'geo' => $g];
    }
    return $in ? ['ok' => false, 'why' => 'Blocked country (' . $g['cc'] . ')', 'geo' => $g]
               : ['ok' => true, 'why' => 'Country not blocked (' . $g['cc'] . ')', 'geo' => $g];
}

/** Stops the request if the visitor may not in. $public = the public website. */
function security_gate($public = false)
{
    if (security_off()) {
        return;
    }
    $c = security_cfg();
    if (!$c['on'] || ($public && $c['scope'] !== 'all')) {
        return;
    }
    $ip = client_ip();
    $v = security_verdict($ip, $c);
    if ($v['ok']) {
        return;
    }
    try { // one log line per address per 10 minutes
        if (!q_val('SELECT 1 FROM security_blocks WHERE ip = ? AND at > NOW() - INTERVAL 10 MINUTE LIMIT 1', [$ip])) {
            q('INSERT INTO security_blocks (ip, cc, reason, path) VALUES (?,?,?,?)',
                [$ip, $v['geo']['cc'] ?? null, mb_substr($v['why'], 0, 200), mb_substr((string) ($_SERVER['SCRIPT_NAME'] ?? ''), 0, 120)]);
            if (random_int(1, 50) === 1) {
                q('DELETE FROM security_blocks WHERE at < NOW() - INTERVAL 30 DAY');
            }
        }
    } catch (Exception $ex) {
    }
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access not allowed</title>'
        . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:15vh auto;padding:24px;text-align:center;color:#1f2d3a">'
        . '<h1 style="font-size:22px">Access not allowed</h1><p>Access from your network or location is not allowed (' . e($ip) . ').</p>'
        . '<p style="color:#5d6d7e">If you think this is a mistake, please contact the administrator.</p></div>';
    exit;
}

/* ---------------- Audit log ---------------- */

/**
 * Records who did what. $action: insert, update, delete, sign_in, sign_out, failed, locked, unlock,
 * download, kick, ban, security, purge … $area: properties, tenants, leases, …
 */
function audit($action, $area, $record = null, $details = null, $user = null)
{
    try {
        $u = $user ?: current_user();
        if (is_array($details)) {
            $details = implode("\n", array_filter($details, function ($x) { return $x !== null && $x !== ''; }));
        }
        db_insert('audit_log', [
            'user_id' => $u ? $u['id'] : null,
            'user_email' => $u ? $u['email'] : null,
            'action' => mb_substr($action, 0, 20),
            'area' => mb_substr($area, 0, 40),
            'record' => $record !== null ? mb_substr((string) $record, 0, 255) : null,
            'details' => $details !== null && $details !== '' ? mb_substr((string) $details, 0, 5000) : null,
            'ip' => client_ip() ?: null,
        ]);
    } catch (Exception $ex) {
        // never break the page because the log could not be written (e.g. update not run yet)
    }
}

/** "Field: old → new" lines for the fields that changed. */
function audit_diff(array $old, array $new, array $labels)
{
    $out = [];
    foreach ($labels as $k => $label) {
        if (!array_key_exists($k, $new)) {
            continue;
        }
        $a = isset($old[$k]) ? (string) $old[$k] : '';
        $b = $new[$k] === null ? '' : (string) $new[$k];
        if (is_numeric($a) && is_numeric($b) && (float) $a === (float) $b) {
            continue;
        }
        if ($a !== $b) {
            $out[] = $label . ': ' . ($a === '' ? '—' : mb_substr($a, 0, 60)) . ' → ' . ($b === '' ? '—' : mb_substr($b, 0, 60));
        }
    }
    return $out;
}

/* ---------------- Failed sign-ins ---------------- */

/** Minutes left on a lock for this email or this address, or 0. */
function login_locked_minutes($email)
{
    try {
        $rows = q_all('SELECT locked_until FROM login_attempts WHERE k IN (?, ?) AND locked_until > NOW()', ['e:' . strtolower($email), 'i:' . client_ip()]);
    } catch (Exception $ex) {
        return 0;
    }
    $max = 0;
    foreach ($rows as $r) {
        $max = max($max, (int) ceil((strtotime($r['locked_until']) - time()) / 60));
    }
    return $max;
}

/** Counts a failed sign-in. Returns true when this failure caused a lock. */
function login_fail($email)
{
    $cfg = security_cfg()['lock'];
    $locked = false;
    $keys = ['e:' . strtolower(mb_substr($email, 0, 190)) => $cfg['tries'], 'i:' . client_ip() => $cfg['tries'] * 4];
    try {
        foreach ($keys as $k => $limit) {
            $r = q_one('SELECT * FROM login_attempts WHERE k = ?', [$k]);
            $fails = 1;
            if ($r) {
                $stale = strtotime($r['last_at']) < time() - 900 || ($r['locked_until'] && strtotime($r['locked_until']) <= time());
                $fails = $stale ? 1 : (int) $r['fails'] + 1;
            }
            $until = null;
            if ($fails >= $limit) {
                $until = date('Y-m-d H:i:s', time() + $cfg['mins'] * 60);
                $locked = true;
                audit('locked', 'session', substr($k, 2), 'Sign-in locked for ' . $cfg['mins'] . ' min after ' . $fails . ' failed attempts (' . ($k[0] === 'e' ? 'email' : 'IP address') . ')');
                $fails = 0;
            }
            q('INSERT INTO login_attempts (k, fails, last_at, locked_until) VALUES (?,?,NOW(),?)
               ON DUPLICATE KEY UPDATE fails = VALUES(fails), last_at = VALUES(last_at), locked_until = VALUES(locked_until)', [$k, $fails, $until]);
        }
    } catch (Exception $ex) {
    }
    return $locked;
}

function login_success($email)
{
    try {
        q('DELETE FROM login_attempts WHERE k IN (?, ?)', ['e:' . strtolower($email), 'i:' . client_ip()]);
        if (random_int(1, 20) === 1) {
            q('DELETE FROM login_attempts WHERE last_at < NOW() - INTERVAL 1 DAY AND (locked_until IS NULL OR locked_until < NOW())');
        }
    } catch (Exception $ex) {
    }
}

/* ---------------- Signed-in sessions (Users online, Disconnect) ---------------- */

/** This browser's row in user_sessions: a hash of the session id (the id itself is never stored). */
function session_key()
{
    return hash('sha256', 'kmup|' . session_id());
}

function pw_version($hash)
{
    return substr(hash('sha256', (string) $hash), 0, 16);
}

function session_sign_in(array $user)
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['pwv'] = pw_version($user['password_hash']);
    $_SESSION['act'] = time();
    $_SESSION['sat'] = date('Y-m-d H:i:s');
    unset($_SESSION['idle_out'], $_SESSION['kicked']);
    session_touch(true);
}

/** Updates Users online (at most every 30 seconds). */
function session_touch($force = false)
{
    if (empty($_SESSION['user_id'])) {
        return;
    }
    if (!$force && isset($_SESSION['seen']) && $_SESSION['seen'] > time() - 30) {
        return;
    }
    $_SESSION['seen'] = time();
    try {
        q('INSERT INTO user_sessions (session_key, user_id, ip, user_agent, signed_in_at, last_seen) VALUES (?,?,?,?,?,NOW())
           ON DUPLICATE KEY UPDATE ip = VALUES(ip), user_agent = VALUES(user_agent), last_seen = NOW()',
            [session_key(), (int) $_SESSION['user_id'], client_ip(), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $_SESSION['sat'] ?? date('Y-m-d H:i:s')]);
        if (random_int(1, 50) === 1) {
            q('DELETE FROM user_sessions WHERE last_seen < NOW() - INTERVAL 2 DAY');
        }
    } catch (Exception $ex) {
    }
}

function session_end_row()
{
    try {
        q('DELETE FROM user_sessions WHERE session_key = ?', [session_key()]);
    } catch (Exception $ex) {
    }
}

/** True if an administrator disconnected this session. */
function session_was_kicked()
{
    try {
        return (bool) q_val('SELECT kicked_at FROM user_sessions WHERE session_key = ? AND kicked_at IS NOT NULL', [session_key()]);
    } catch (Exception $ex) {
        return false;
    }
}

/** Ends every session of a user (except, optionally, the current one). */
function sessions_kick_user($userId, $exceptCurrent = false)
{
    try {
        if ($exceptCurrent) {
            q('UPDATE user_sessions SET kicked_at = NOW() WHERE user_id = ? AND session_key <> ?', [(int) $userId, session_key()]);
        } else {
            q('UPDATE user_sessions SET kicked_at = NOW() WHERE user_id = ?', [(int) $userId]);
        }
    } catch (Exception $ex) {
    }
}

/* ---------------- Idle sign-out ----------------
   Every admin page load counts as activity. While a page stays open, admin.js sends the seconds
   since the last mouse / keyboard / touch input (ping.php); a session idle longer than
   Admin › Security › "Sign out inactive users" (15–90 minutes, default 45) is signed out. */

/** Returns false (and signs out) when the session has been idle too long. */
function idle_check($idleSeconds = null)
{
    if (empty($_SESSION['user_id'])) {
        return true;
    }
    $now = time();
    $min = security_cfg()['idle'];
    $last = (int) ($_SESSION['act'] ?? $now);
    if ($idleSeconds !== null) {
        $last = max($last, $now - max(0, (int) $idleSeconds));
    }
    if ($now - $last > $min * 60 + 30) { // 30 seconds of slack
        session_end_row();
        unset($_SESSION['user_id'], $_SESSION['pwv'], $_SESSION['act']);
        $_SESSION['idle_out'] = $min;
        return false;
    }
    $_SESSION['act'] = $idleSeconds === null ? $now : $last;
    return true;
}

/** Short device name from a user agent. */
function ua_short($ua)
{
    $ua = (string) $ua;
    $os = preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/iPhone|iPad/i', $ua) ? 'iOS' : (preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/Mac OS/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : ''))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\//', $ua) ? 'Opera' : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Firefox\//', $ua) ? 'Firefox' : (preg_match('/Safari\//', $ua) ? 'Safari' : ''))));
    return trim($br . ($br && $os ? ' on ' : '') . $os) ?: 'Browser';
}

function cc_flag($cc)
{
    if (!preg_match('/^[A-Z]{2}$/', (string) $cc)) {
        return '';
    }
    $out = '';
    foreach (str_split($cc) as $ch) {
        $out .= html_entity_decode('&#' . (127397 + ord($ch)) . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $out;
}
