<?php
/* Admin › Security (1.1.0) — who may reach the admin panel by IP address and country, failed sign-in
   lockouts, idle sign-out, users online (Disconnect / Ban IP) and recently refused visits.
   Modelled on the RBJocsing portal's Security page. */
require __DIR__ . '/includes/init.php';
$me = require_admin('admin');

$myIp = client_ip();
$cfg = security_cfg();
$tablesOk = true;
try {
    q_val('SELECT COUNT(*) FROM user_sessions');
    q_val('SELECT COUNT(*) FROM security_blocks');
} catch (Exception $ex) {
    $tablesOk = false;
}

function sec_lines($text)
{
    $out = [];
    foreach (preg_split('/\R/', (string) $text) as $l) {
        if (trim($l) !== '') {
            $out[] = trim($l);
        }
    }
    return $out;
}

function sec_add_line(array $list, $ip, $note = '')
{
    foreach ($list as $l) {
        if (trim(preg_replace('/#.*$/', '', $l)) === $ip) {
            return $list;
        }
    }
    $list[] = $ip . ($note !== '' ? '  # ' . $note : '');
    return $list;
}

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'save') {
        $new = [
            'on' => post('on') ? 1 : 0,
            'scope' => post('scope'),
            'idle' => (int) post('idle'),
            'lock' => ['tries' => (int) post('lock_tries'), 'mins' => (int) post('lock_mins')],
            'geo' => ['mode' => post('geo_mode'), 'unknown' => post('geo_unknown'), 'list' => array_filter(explode(',', (string) post('geo_list')))],
            'allow' => sec_lines(post('allow')),
            'block' => sec_lines(post('block')),
        ];
        $bad = [];
        foreach (array_merge($new['allow'], $new['block']) as $r) {
            if (!security_rule_ok($r)) {
                $bad[] = $r;
            }
        }
        $clean = security_clean($new);
        if ($bad) {
            flash('error', 'Not saved — these lines are not valid addresses or ranges: ' . implode(', ', array_slice($bad, 0, 5)));
            $_SESSION['sec_draft'] = $new;
        } elseif ($clean['geo']['mode'] !== 'off' && !$clean['geo']['list']) {
            flash('error', 'Not saved — choose at least one country for the country rule, or turn it off.');
            $_SESSION['sec_draft'] = $new;
        } elseif ($clean['on'] && !($v = security_verdict($myIp, $clean))['ok']) {
            flash('error', 'Not saved — these rules would block your own connection (' . $myIp . ': ' . $v['why'] . '). Add your address to Always allow first.');
            $_SESSION['sec_draft'] = $new;
        } else {
            $changes = [];
            if ($cfg['on'] !== $clean['on']) { $changes[] = 'Rules ' . ($clean['on'] ? 'turned on' : 'turned off'); }
            if ($cfg['scope'] !== $clean['scope']) { $changes[] = 'Applies to: ' . ($clean['scope'] === 'all' ? 'whole site' : 'admin panel'); }
            if ($cfg['idle'] !== $clean['idle']) { $changes[] = 'Idle sign-out: ' . $cfg['idle'] . ' → ' . $clean['idle'] . ' min'; }
            if ($cfg['lock'] !== $clean['lock']) { $changes[] = 'Lockout: ' . $clean['lock']['tries'] . ' tries, ' . $clean['lock']['mins'] . ' min'; }
            if ($cfg['geo'] !== $clean['geo']) { $changes[] = 'Country rule: ' . $clean['geo']['mode'] . ($clean['geo']['list'] ? ' (' . implode(', ', $clean['geo']['list']) . ')' : '') . ', unknown = ' . $clean['geo']['unknown']; }
            if ($cfg['allow'] !== $clean['allow']) { $changes[] = 'Always allow: ' . count($clean['allow']) . ' line(s)'; }
            if ($cfg['block'] !== $clean['block']) { $changes[] = 'Blocked: ' . count($clean['block']) . ' line(s)'; }
            security_save($clean);
            audit('security', 'security', 'Security settings', $changes ?: 'Saved (no changes)');
            flash('success', 'Security settings saved.');
        }
    } elseif ($action === 'unlock') {
        q('DELETE FROM login_attempts WHERE k = ?', [post('k')]);
        audit('unlock', 'security', substr(post('k'), 2), 'Sign-in lock removed');
        flash('success', 'Unlocked.');
    } elseif ($action === 'allow_ip') {
        $ip = post('ip');
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            $c = $cfg;
            $c['allow'] = sec_add_line($c['allow'], $ip, post('note'));
            security_save($c);
            audit('security', 'security', $ip, 'Added to Always allow');
            flash('success', $ip . ' added to Always allow.');
        }
    } elseif ($action === 'kick') {
        $s = q_one('SELECT s.*, u.email FROM user_sessions s JOIN users u ON u.id = s.user_id WHERE s.session_key = ?', [post('key')]);
        if ($s && $s['session_key'] !== session_key()) {
            q('UPDATE user_sessions SET kicked_at = NOW() WHERE session_key = ?', [$s['session_key']]);
            audit('kick', 'security', $s['email'], 'Session disconnected (' . $s['ip'] . ')');
            flash('success', $s['email'] . ' was disconnected. They will be signed out on their next click.');
        }
    } elseif ($action === 'ban') {
        $ip = post('ip');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            flash('error', 'Not a valid address.');
        } elseif ($ip === $myIp) {
            flash('error', 'You cannot ban your own address.');
        } elseif (security_is_local($ip)) {
            flash('error', 'Local network addresses are always allowed and cannot be banned.');
        } else {
            $c = $cfg;
            $c['block'] = sec_add_line($c['block'], $ip, 'banned ' . date('Y-m-d') . ' by ' . $me['name']);
            $c['on'] = true;
            security_save($c);
            q('UPDATE user_sessions SET kicked_at = NOW() WHERE ip = ? AND session_key <> ?', [$ip, session_key()]);
            audit('ban', 'security', $ip, 'IP address banned; its sessions were disconnected');
            flash('success', $ip . ' is banned and its sessions were disconnected.' . ($cfg['on'] ? '' : ' Security rules were turned on.'));
        }
    }
    redirect('admin/security.php' . (get('check') ? '?check=' . urlencode(get('check')) : ''));
}

$D = $cfg;
if (!empty($_SESSION['sec_draft'])) {
    $D = array_merge($cfg, security_clean($_SESSION['sec_draft']));
    $D['allow'] = $_SESSION['sec_draft']['allow'];
    $D['block'] = $_SESSION['sec_draft']['block'];
    unset($_SESSION['sec_draft']);
}
$meGeo = security_is_local($myIp) ? null : ip_country($myIp);
$locked = [];
$online = [];
$blocks = [];
if ($tablesOk) {
    $locked = q_all('SELECT * FROM login_attempts WHERE locked_until > NOW() ORDER BY locked_until DESC');
    $online = q_all('SELECT s.*, u.name, u.email, u.role FROM user_sessions s JOIN users u ON u.id = s.user_id
                     WHERE s.kicked_at IS NULL AND s.last_seen > NOW() - INTERVAL ? MINUTE ORDER BY s.last_seen DESC', [$cfg['idle'] + 2]);
    $blocks = q_all('SELECT * FROM security_blocks ORDER BY at DESC LIMIT 50');
}
$check = null;
if (get('check') !== '') {
    $ip = get('check');
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $c = $cfg;
        $c['on'] = true;
        $check = ['ip' => $ip, 'verdict' => security_verdict($ip, $c), 'geo' => ip_country($ip)];
    } else {
        $check = ['ip' => $ip, 'err' => 'That is not a valid IP address.'];
    }
}
$countries = ['PH' => 'Philippines', 'US' => 'United States', 'CA' => 'Canada', 'AU' => 'Australia', 'NZ' => 'New Zealand', 'GB' => 'United Kingdom', 'IE' => 'Ireland', 'SG' => 'Singapore', 'MY' => 'Malaysia', 'ID' => 'Indonesia', 'TH' => 'Thailand', 'VN' => 'Vietnam', 'HK' => 'Hong Kong', 'TW' => 'Taiwan', 'JP' => 'Japan', 'KR' => 'South Korea', 'CN' => 'China', 'IN' => 'India', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar', 'KW' => 'Kuwait', 'BH' => 'Bahrain', 'OM' => 'Oman', 'IL' => 'Israel', 'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'CH' => 'Switzerland', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'BE' => 'Belgium', 'AT' => 'Austria', 'PL' => 'Poland', 'RU' => 'Russia', 'UA' => 'Ukraine', 'TR' => 'Turkey', 'BR' => 'Brazil', 'MX' => 'Mexico', 'ZA' => 'South Africa', 'NG' => 'Nigeria', 'EG' => 'Egypt', 'PK' => 'Pakistan', 'BD' => 'Bangladesh'];
foreach ($D['geo']['list'] as $c) {
    if (!isset($countries[$c])) {
        $countries[$c] = $c;
    }
}
asort($countries);

admin_header('Security', 'security');
?>
<p class="hint" style="max-width:820px">Choose who may reach the admin panel by IP address and by country, how failed sign-ins are handled and when inactive users are signed out. The server checks every request. Local network addresses are always allowed, and the server will not save rules that would block your own connection.</p>
<?php if (security_off()): ?><div class="alert alert-warning">Security is turned off in <b>includes/config.php</b> (<code>SECURITY_OFF</code>). The rules below are saved but not used until that line is removed.</div><?php endif; ?>
<?php if (!$tablesOk): ?><div class="alert alert-danger">Run <b>sql/update-1.1.0.sql</b> once on the database (phpMyAdmin) to enable lockouts, users online, the audit log and the refused-visits list.</div><?php endif; ?>

<div class="bkbox">
  <i class="bi bi-wifi me-1"></i>Your address: <code><?= e($myIp ?: 'unknown') ?></code>
  <?php if (security_is_local($myIp)): ?> (local network: always allowed)
  <?php elseif ($meGeo): ?> · <?= cc_flag($meGeo['cc']) ?> <?= e($meGeo['country']) ?><?= $meGeo['place'] ? ' · ' . e($meGeo['place']) : '' ?><?= $meGeo['isp'] ? ' · ' . e($meGeo['isp']) : '' ?>
  <?php else: ?> · country not found<?php endif; ?>
</div>

<form method="post" id="secForm">
  <?= csrf_field() ?><input type="hidden" name="action" value="save">
  <div class="row g-4">
    <div class="col-xl-6">
      <div class="bkbox h-100">
        <h2><i class="bi bi-shield-check me-1"></i>Access rules</h2>
        <div class="form-check form-switch my-2"><input class="form-check-input" type="checkbox" name="on" value="1" id="secOn" <?= $D['on'] ? 'checked' : '' ?>><label class="form-check-label fw-semibold" for="secOn">Use the IP and country rules</label></div>
        <label class="form-label">Applies to</label>
        <select name="scope" class="form-select mb-2"><?= select_options(['admin' => 'Admin panel and sign-in only (recommended)', 'all' => 'Whole site, also the public website'], $D['scope'], true) ?></select>
        <p class="hint mb-0">Recommended: limit only the admin panel so visitors from anywhere can still browse your listings.</p>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="bkbox h-100">
        <h2><i class="bi bi-hourglass-split me-1"></i>Sign out inactive users</h2>
        <label class="form-label mt-2">Sign out after no activity for</label>
        <select name="idle" class="form-select mb-2"><?php foreach ([15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 70, 80, 90] as $m): ?><option value="<?= $m ?>"<?= $m === $D['idle'] ? ' selected' : '' ?>><?= $m ?> minutes<?= $m === 45 ? ' (default)' : '' ?></option><?php endforeach; ?></select>
        <p class="hint mb-0">Applies to everyone, whether or not the rules are used. Activity is using the mouse, keyboard or touch screen in any admin tab. A warning with “Stay signed in” shows one minute before.</p>
      </div>
    </div>
    <div class="col-12">
      <div class="bkbox">
        <h2><i class="bi bi-lock me-1"></i>Failed sign-ins</h2>
        <div class="row g-3 mt-0" style="max-width:640px">
          <div class="col-sm-6"><label class="form-label">Lock sign-in after</label><select name="lock_tries" class="form-select"><?php foreach ([3, 4, 5, 6, 7, 8, 9, 10, 12, 15, 20] as $n): ?><option value="<?= $n ?>"<?= $n === $D['lock']['tries'] ? ' selected' : '' ?>><?= $n ?> failed attempts<?= $n === 5 ? ' (default)' : '' ?></option><?php endforeach; ?></select></div>
          <div class="col-sm-6"><label class="form-label">Lock for</label><select name="lock_mins" class="form-select"><?php foreach ([1, 2, 3, 5, 10, 15, 20, 30, 45, 60] as $n): ?><option value="<?= $n ?>"<?= $n === $D['lock']['mins'] ? ' selected' : '' ?>><?= $n ?> minute<?= $n === 1 ? '' : 's' ?><?= $n === 3 ? ' (default)' : '' ?></option><?php endforeach; ?></select></div>
        </div>
        <p class="hint mt-2">Wrong passwords in a row for one email lock that email; one IP address gets 4 times as many tries across all emails. The count restarts after a successful sign-in, after the lock ends, or after 15 minutes without a failure. Every failure and lock is written to the audit log.</p>
        <?php if ($locked): ?>
          <div class="table-responsive" style="max-width:640px"><table class="table table-sm align-middle"><thead><tr><th>Locked now</th><th>Until</th><th></th></tr></thead><tbody>
            <?php foreach ($locked as $l): ?>
              <tr><td><?= $l['k'][0] === 'i' ? '<i class="bi bi-wifi"></i> ' : '<i class="bi bi-envelope"></i> ' ?><?= e(substr($l['k'], 2)) ?></td><td><?= fmt_date($l['locked_until'], 'M j, g:i A') ?></td>
                <td class="text-end"><button type="submit" form="unlock<?= md5($l['k']) ?>" class="btn btn-sm btn-light">Unlock</button></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php else: ?><div class="hint">No email or address is locked right now.</div><?php endif; ?>
      </div>
    </div>
    <div class="col-12">
      <div class="bkbox">
        <h2><i class="bi bi-globe2 me-1"></i>Countries</h2>
        <p class="hint">Recommended: <b>Allow only</b> the countries your team is in, e.g. only the Philippines. The country comes from the IP address and is approximate; a VPN shows the VPN’s country. Staff travelling abroad can be added to Always allow.</p>
        <div class="row g-3" style="max-width:820px">
          <div class="col-md-6"><label class="form-label">Country rule</label><select name="geo_mode" id="geoMode" class="form-select"><?= select_options(['off' => 'Off: any country', 'allow' => 'Allow only these countries (whitelist)', 'block' => 'Block these countries (blacklist)'], $D['geo']['mode'], true) ?></select></div>
          <div class="col-md-6"><label class="form-label">When the country cannot be found</label><select name="geo_unknown" class="form-select"><?= select_options(['allow' => 'Allow (safer: look-up services can fail)', 'deny' => 'Block'], $D['geo']['unknown'], true) ?></select></div>
        </div>
        <div id="geoBox" class="mt-3">
          <input type="hidden" name="geo_list" id="geoList" value="<?= e(implode(',', $D['geo']['list'])) ?>">
          <div id="geoChips" class="mb-2"></div>
          <div class="d-flex flex-wrap gap-2" style="max-width:520px">
            <select id="geoAdd" class="form-select w-auto flex-fill"><option value="">Add a country…</option><?php foreach ($countries as $c => $n): ?><option value="<?= e($c) ?>"><?= e($n) ?></option><?php endforeach; ?></select>
            <button type="button" class="btn btn-light" onclick="geoAddSel()">Add</button>
            <button type="button" class="btn btn-light" onclick="geoAdd('PH')">+ Philippines</button>
          </div>
        </div>
      </div>
    </div>
    <div class="col-12">
      <div class="bkbox">
        <h2><i class="bi bi-hdd-network me-1"></i>IP addresses</h2>
        <p class="hint">One per line: an address (<code>203.0.113.5</code>), a range (<code>203.0.113.0/24</code>) or a wildcard (<code>203.0.113.*</code>). Text after <code>#</code> is a note. <b>Always allow</b> wins over everything else; <b>Blocked</b> wins over the country rule.</p>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Always allow (whitelist)</label><textarea name="allow" id="allowTa" rows="6" class="form-control mono" spellcheck="false" placeholder="203.0.113.5  # main office"><?= e(implode("\n", $D['allow'])) ?></textarea>
            <?php if ($myIp && !security_is_local($myIp)): ?><a href="#" class="hint" onclick="addMine();return false">+ add my address (<?= e($myIp) ?>)</a><?php endif; ?></div>
          <div class="col-md-6"><label class="form-label">Blocked (blacklist, bans)</label><textarea name="block" rows="6" class="form-control mono" spellcheck="false" placeholder="198.51.100.7  # spam"><?= e(implode("\n", $D['block'])) ?></textarea></div>
        </div>
      </div>
    </div>
  </div>
  <div class="sticky-save d-flex justify-content-end gap-2"><a href="<?= url('admin/security.php') ?>" class="btn btn-light">Discard changes</a><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save changes</button></div>
</form>
<?php foreach ($locked as $l): ?><form method="post" id="unlock<?= md5($l['k']) ?>" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="k" value="<?= e($l['k']) ?>"></form><?php endforeach; ?>

<div class="bkbox mt-4">
  <h2><i class="bi bi-people me-1"></i>Users online</h2>
  <p class="hint">Signed-in admin sessions active in the last <?= (int) $cfg['idle'] + 2 ?> minutes. <b>Disconnect</b> signs that session out on its next click; <b>Ban IP</b> adds the address to Blocked and disconnects it.</p>
  <?php if (!$online): ?><div class="hint">Nobody else is signed in.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>User</th><th>Address</th><th>Device</th><th>Signed in</th><th>Last seen</th><th></th></tr></thead>
    <tbody>
    <?php $lookups = 0; foreach ($online as $s):
        $g = ip_country_cached($s['ip']);
        if (!$g && $lookups < 5 && !security_is_local((string) $s['ip'])) { $lookups++; $g = ip_country((string) $s['ip']); }
        $mine = $s['session_key'] === session_key();
        $mins = (int) floor((time() - strtotime($s['last_seen'])) / 60); ?>
      <tr>
        <td><span class="me-1" style="color:<?= $mins < 3 ? '#2ecc71' : '#f1c40f' ?>">●</span><b><?= e($s['name']) ?></b><?= $mine ? ' <span class="pill adm">you</span>' : '' ?><div class="hint"><?= e($s['email']) ?></div></td>
        <td><code><?= e($s['ip']) ?></code><div class="hint"><?= $g ? cc_flag($g['cc']) . ' ' . e($g['country']) . ($g['place'] ? ' · ' . e($g['place']) : '') : '—' ?></div></td>
        <td class="small"><?= e(ua_short($s['user_agent'])) ?></td>
        <td class="small"><?= fmt_date($s['signed_in_at'], 'M j, g:i A') ?></td>
        <td class="small"><?= $mins < 1 ? 'just now' : $mins . ' min ago' ?></td>
        <td class="text-end text-nowrap">
          <?php if (!$mine): ?>
            <form method="post" class="d-inline" onsubmit="return confirm('Disconnect this session?')"><?= csrf_field() ?><input type="hidden" name="action" value="kick"><input type="hidden" name="key" value="<?= e($s['session_key']) ?>"><button class="btn btn-sm btn-light">Disconnect</button></form>
            <?php if ($s['ip'] !== $myIp && !security_is_local((string) $s['ip'])): ?><form method="post" class="d-inline" onsubmit="return confirm('Ban <?= e($s['ip']) ?> and disconnect it?')"><?= csrf_field() ?><input type="hidden" name="action" value="ban"><input type="hidden" name="ip" value="<?= e($s['ip']) ?>"><button class="btn btn-sm btn-outline-danger">Ban IP</button></form><?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="bkbox">
  <h2><i class="bi bi-search me-1"></i>Check an address</h2>
  <form method="get" class="d-flex flex-wrap gap-2 align-items-end mt-2" style="max-width:520px">
    <div class="flex-fill"><label class="form-label">IP address</label><input name="check" class="form-control mono" placeholder="203.0.113.5" value="<?= e(get('check')) ?>"></div>
    <button class="btn btn-light">Check</button>
  </form>
  <?php if ($check): ?>
    <div class="mt-3">
      <?php if (!empty($check['err'])): ?><div class="text-danger"><?= e($check['err']) ?></div>
      <?php else: ?>
        <?= $check['verdict']['ok'] ? '<span class="pill act">Allowed</span>' : '<span class="pill sus">Blocked</span>' ?> <code><?= e($check['ip']) ?></code> ·
        <?= $check['geo'] ? cc_flag($check['geo']['cc']) . ' ' . e($check['geo']['country']) . ($check['geo']['place'] ? ' · ' . e($check['geo']['place']) : '') . ($check['geo']['isp'] ? ' · ' . e($check['geo']['isp']) : '') : 'country not found' ?>
        <div class="hint mt-1"><?= e($check['verdict']['why']) ?> (with the saved rules<?= $cfg['on'] ? '' : ', as if they were turned on' ?>)</div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="bkbox">
  <h2><i class="bi bi-slash-circle me-1"></i>Recently refused</h2>
  <?php if (!$blocks): ?><div class="hint">Nothing refused yet.</div><?php else: ?>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>When</th><th>IP address</th><th>Country</th><th>Reason</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($blocks as $b): ?>
      <tr>
        <td class="small text-nowrap"><?= fmt_date($b['at'], 'M j, g:i A') ?></td>
        <td><code><?= e($b['ip']) ?></code></td>
        <td class="small"><?= $b['cc'] ? cc_flag($b['cc']) . ' ' . e($b['cc']) : '—' ?></td>
        <td class="small"><?= e($b['reason']) ?><div class="hint"><?= e($b['path']) ?></div></td>
        <td class="text-end"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="allow_ip"><input type="hidden" name="ip" value="<?= e($b['ip']) ?>"><button class="btn btn-sm btn-light">Always allow</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="hint">One line per address every 10 minutes; kept 30 days.</div>
  <?php endif; ?>
</div>

<p class="hint mt-3">Locked out? Add <code>define('SECURITY_OFF', true);</code> to <b>includes/config.php</b> on the server (File Manager), sign in and fix the rules, then remove the line. Or run <code>DELETE FROM settings WHERE setting_key = 'security';</code> in phpMyAdmin. Country look-ups use ipwho.is (then ipapi.co and ip-api.com); results are kept 30 days.</p>

<script>
var GEO_NAMES = <?= json_encode($countries) ?>;
function geoFlag(c) { return String.fromCodePoint.apply(null, c.split('').map(function (x) { return 127397 + x.charCodeAt(0); })); }
function geoGet() { var v = document.getElementById('geoList').value; return v ? v.split(',') : []; }
function geoSet(L) { document.getElementById('geoList').value = L.join(','); geoPaint(); }
function geoAdd(c) { var L = geoGet(); if (c && L.indexOf(c) < 0) L.push(c); geoSet(L); }
function geoAddSel() { var s = document.getElementById('geoAdd'); geoAdd(s.value); s.value = ''; }
function geoDel(c) { geoSet(geoGet().filter(function (x) { return x !== c; })); }
function geoPaint() {
  var mode = document.getElementById('geoMode').value, L = geoGet(), box = document.getElementById('geoChips');
  document.getElementById('geoBox').style.display = mode === 'off' ? 'none' : '';
  box.innerHTML = L.length ? L.map(function (c) {
    return '<span class="pill ' + (mode === 'allow' ? 'act' : 'sus') + ' me-1 mb-1">' + geoFlag(c) + ' ' + (GEO_NAMES[c] || c) + ' <a href="#" onclick="geoDel(\'' + c + '\');return false" aria-label="Remove">✕</a></span>';
  }).join('') : '<span class="hint">No countries chosen yet.</span>';
}
document.getElementById('geoMode').addEventListener('change', function () { if (this.value === 'allow' && !geoGet().length) geoAdd('PH'); geoPaint(); });
geoPaint();
function addMine() {
  var ta = document.getElementById('allowTa'), ip = <?= json_encode($myIp) ?>;
  if (ta.value.split('\n').some(function (l) { return l.split('#')[0].trim() === ip; })) return;
  ta.value = (ta.value.trim() ? ta.value.trim() + '\n' : '') + ip + '  # ' + <?= json_encode($me['name']) ?>;
}
</script>
<?php admin_footer();
