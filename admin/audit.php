<?php
/* Admin › Audit log (1.1.0): who did what, when and from where. Admins only.
   Filter, download as CSV, and purge old entries after downloading a copy. */
require __DIR__ . '/includes/init.php';
require_admin('admin');

$AREAS = [
    'session' => 'Sign-in / sign-out', 'properties' => 'Properties', 'photos' => 'Property photos', 'tenants' => 'Tenants',
    'leases' => 'Leases', 'payments' => 'Payments', 'maintenance' => 'Repairs & maintenance', 'documents' => 'Documents',
    'history' => 'History notes', 'inquiries' => 'Inquiries', 'sections' => 'Landing page', 'locations' => 'Locations',
    'settings' => 'Site settings', 'theme' => 'Theme & layout', 'users' => 'Users', 'security' => 'Security', 'audit_log' => 'Audit log', 'test_data' => 'Test data', 'reports' => 'Reports',
];
$ACTIONS = [
    'insert' => 'Added', 'update' => 'Changed', 'delete' => 'Deleted', 'sign_in' => 'Signed in', 'sign_out' => 'Signed out',
    'failed' => 'Failed sign-in', 'locked' => 'Sign-in locked', 'unlock' => 'Unlocked', 'download' => 'Opened file',
    'kick' => 'Disconnected', 'ban' => 'Banned IP', 'security' => 'Security change', 'purge' => 'Purged log',
];

if (is_post()) {
    csrf_check();
    $days = (int) post('days');
    if (in_array($days, [15, 30, 60, 90, 180, 365], true)) {
        $n = q('DELETE FROM audit_log WHERE at < NOW() - INTERVAL ' . $days . ' DAY')->rowCount();
        audit('purge', 'audit_log', null, $n . ' entr' . ($n === 1 ? 'y' : 'ies') . ' older than ' . $days . ' days deleted');
        flash('success', $n . ' old entr' . ($n === 1 ? 'y was' : 'ies were') . ' deleted.');
    }
    redirect('admin/audit.php');
}

$f = ['user' => get('user'), 'action' => get('action'), 'area' => get('area'), 'from' => valid_date(get('from')), 'to' => valid_date(get('to')), 'q' => mb_substr(get('q'), 0, 80)];
$where = ['1=1'];
$params = [];
if ($f['user'] !== '') { $where[] = 'user_email = ?'; $params[] = $f['user']; }
if (isset($ACTIONS[$f['action']])) { $where[] = 'action = ?'; $params[] = $f['action']; }
if (isset($AREAS[$f['area']])) { $where[] = 'area = ?'; $params[] = $f['area']; }
if ($f['from']) { $where[] = 'at >= ?'; $params[] = $f['from'] . ' 00:00:00'; }
if ($f['to']) { $where[] = 'at <= ?'; $params[] = $f['to'] . ' 23:59:59'; }
if ($f['q'] !== '') { $where[] = '(record LIKE ? OR details LIKE ? OR ip LIKE ? OR user_email LIKE ?)'; $k = '%' . $f['q'] . '%'; array_push($params, $k, $k, $k, $k); }
$whereSql = implode(' AND ', $where);

$err = null;
try {
    if (get('csv')) {
        $rows = q_all("SELECT * FROM audit_log WHERE $whereSql ORDER BY at DESC, id DESC LIMIT 20000", $params);
        audit('download', 'audit_log', 'audit-log.csv', count($rows) . ' entries exported');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="audit-log-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['When', 'User', 'Action', 'Area', 'Record', 'Details', 'IP address']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['at'], $r['user_email'], $ACTIONS[$r['action']] ?? $r['action'], $AREAS[$r['area']] ?? $r['area'], $r['record'], $r['details'], $r['ip']]);
        }
        fclose($out);
        exit;
    }
    $total = (int) q_val("SELECT COUNT(*) FROM audit_log WHERE $whereSql", $params);
    $page = max(1, (int) get('page', 1));
    $per = 100;
    $pages = max(1, (int) ceil($total / $per));
    $page = min($page, $pages);
    $rows = q_all("SELECT * FROM audit_log WHERE $whereSql ORDER BY at DESC, id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $params);
    $users = q_all('SELECT DISTINCT user_email FROM audit_log WHERE user_email IS NOT NULL ORDER BY user_email');
    $oldest = q_val('SELECT MIN(at) FROM audit_log');
} catch (Exception $ex) {
    $err = 'Run sql/update-1.1.0.sql once on the database to create the audit log.';
    $rows = [];
    $users = [];
    $total = 0;
    $pages = 1;
    $page = 1;
    $oldest = null;
}

function audit_link($p)
{
    $qs = array_merge($_GET, $p);
    return url('admin/audit.php?' . http_build_query($qs));
}

admin_header('Audit Log', 'audit');
?>
<p class="hint">Every sign-in, failed sign-in, lock and change made in the admin panel, with the user and IP address. Download a copy before purging old entries.</p>
<?php if ($err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endif; ?>

<form method="get" class="row g-2 align-items-end mb-3">
  <div class="col-6 col-md-2"><label class="form-label">User</label><select name="user" class="form-select form-select-sm"><option value="">Everyone</option><?php foreach ($users as $u): ?><option<?= $f['user'] === $u['user_email'] ? ' selected' : '' ?>><?= e($u['user_email']) ?></option><?php endforeach; ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label">Action</label><select name="action" class="form-select form-select-sm"><option value="">Any</option><?= select_options($ACTIONS, $f['action'], true) ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label">Area</label><select name="area" class="form-select form-select-sm"><option value="">Any</option><?= select_options($AREAS, $f['area'], true) ?></select></div>
  <div class="col-6 col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control form-control-sm" value="<?= e($f['from']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control form-control-sm" value="<?= e($f['to']) ?>"></div>
  <div class="col-6 col-md-2"><label class="form-label">Search</label><input name="q" class="form-control form-control-sm" value="<?= e($f['q']) ?>" placeholder="Record, IP…"></div>
  <div class="col-12 d-flex flex-wrap gap-2">
    <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
    <a href="<?= url('admin/audit.php') ?>" class="btn btn-sm btn-light">Clear</a>
    <a href="<?= e(audit_link(['csv' => 1, 'page' => null])) ?>" class="btn btn-sm btn-light ms-auto"><i class="bi bi-download me-1"></i>Download CSV</a>
  </div>
</form>

<div class="card mb-3"><div class="table-responsive"><table class="table table-sm table-hover align-middle">
  <thead><tr><th>When</th><th>User</th><th>Action</th><th>Area</th><th>Record / details</th><th>IP address</th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No entries.</td></tr><?php endif; ?>
  <?php foreach ($rows as $r):
      $bad = in_array($r['action'], ['failed', 'locked', 'ban', 'kick', 'delete', 'purge'], true); ?>
    <tr>
      <td class="small text-nowrap"><?= fmt_date($r['at'], 'M j, Y g:i A') ?></td>
      <td class="small"><?= e($r['user_email'] ?: '—') ?></td>
      <td><span class="pill <?= $bad ? 'sus' : ($r['action'] === 'sign_in' ? 'act' : '') ?>"><?= e($ACTIONS[$r['action']] ?? $r['action']) ?></span></td>
      <td class="small"><?= e($AREAS[$r['area']] ?? $r['area']) ?></td>
      <td class="small"><?= e($r['record']) ?><?php if ($r['details']): ?><div class="hint"><?= nl2br(e(mb_substr($r['details'], 0, 400))) ?></div><?php endif; ?></td>
      <td class="small"><code><?= e($r['ip']) ?></code></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <span class="hint"><?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?><?= $oldest ? ' · oldest ' . fmt_date($oldest) : '' ?></span>
  <?php if ($pages > 1): ?>
    <div class="btn-group">
      <a class="btn btn-sm btn-light<?= $page <= 1 ? ' disabled' : '' ?>" href="<?= e(audit_link(['page' => $page - 1])) ?>">‹ Newer</a>
      <span class="btn btn-sm btn-light disabled">Page <?= $page ?> / <?= $pages ?></span>
      <a class="btn btn-sm btn-light<?= $page >= $pages ? ' disabled' : '' ?>" href="<?= e(audit_link(['page' => $page + 1])) ?>">Older ›</a>
    </div>
  <?php endif; ?>
</div>

<div class="bkbox" style="max-width:640px">
  <h2><i class="bi bi-trash3 me-1"></i>Purge old entries</h2>
  <p class="hint">Download a CSV copy first. The purge itself is written to the log.</p>
  <form method="post" class="d-flex flex-wrap gap-2" onsubmit="return confirm('Delete the older entries permanently?')">
    <?= csrf_field() ?>
    <select name="days" class="form-select w-auto"><?php foreach ([15, 30, 60, 90, 180, 365] as $d): ?><option value="<?= $d ?>"<?= $d === 90 ? ' selected' : '' ?>>Older than <?= $d ?> days</option><?php endforeach; ?></select>
    <button class="btn btn-outline-danger">Purge</button>
  </form>
</div>
<?php admin_footer();
