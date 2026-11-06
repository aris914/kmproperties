<?php
require __DIR__ . '/includes/init.php';
$me = require_admin();

$stats = q_one("SELECT
    SUM(listing_type = 'rent' AND status <> 'inactive') AS rent_total,
    SUM(listing_type = 'sale' AND status <> 'inactive') AS sale_total,
    SUM(status = 'available') AS available,
    SUM(status = 'rented') AS rented,
    SUM(status = 'sold') AS sold
  FROM properties");
$activeTenants = (int) q_val("SELECT COUNT(*) FROM tenants WHERE status = 'active'");
$openJobs = (int) q_val("SELECT COUNT(*) FROM maintenance WHERE status IN ('open','scheduled','in_progress')");
$newInq = (int) q_val("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
$monthPaid = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE paid_on >= ? AND paid_on <= LAST_DAY(?)", [date('Y-m-01'), date('Y-m-01')]);
$monthMaint = (float) q_val("SELECT COALESCE(SUM(cost),0) FROM maintenance WHERE status = 'completed' AND completed_on >= ?", [date('Y-m-01')]);
$rentRoll = (float) q_val("SELECT COALESCE(SUM(monthly_rent),0) FROM leases WHERE status = 'active'");

$expiring = q_all("SELECT l.*, p.title, t.first_name, t.last_name FROM leases l JOIN properties p ON p.id = l.property_id JOIN tenants t ON t.id = l.tenant_id
                   WHERE l.status = 'active' AND l.end_date IS NOT NULL AND l.end_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) ORDER BY l.end_date LIMIT 8");
$jobs = q_all("SELECT m.*, p.title AS property_title FROM maintenance m JOIN properties p ON p.id = m.property_id WHERE m.status IN ('open','scheduled','in_progress')
               ORDER BY FIELD(m.priority,'urgent','high','normal','low'), m.reported_on LIMIT 8");
$inquiries = q_all("SELECT i.*, p.title FROM inquiries i LEFT JOIN properties p ON p.id = i.property_id ORDER BY i.created_at DESC LIMIT 6");
$recent = history_query('1=1', [], 10);

admin_header('Dashboard', 'dashboard');
?>
<?php $pendingSubs = (int) q_val("SELECT COUNT(*) FROM properties WHERE approval = 'pending'"); if ($pendingSubs): ?>
<div class="review-bar pending"><i class="bi bi-house-add fs-4"></i><div class="flex-fill"><b><?= $pendingSubs ?> propert<?= $pendingSubs === 1 ? 'y' : 'ies' ?> from owners waiting for approval</b><div class="small">Submitted through List Your Property. They are hidden from the website until approved.</div></div><a href="<?= url('admin/submissions.php') ?>" class="btn btn-sm btn-primary">Review now</a></div>
<?php endif; ?>
<div class="row g-3 mb-4">
  <?php
  $cards = [
      ['For Rent', (int) $stats['rent_total'], 'bi-key', 'properties.php?type=rent', 'teal'],
      ['For Sale', (int) $stats['sale_total'], 'bi-house-check', 'properties.php?type=sale', 'gold'],
      ['Rented now', (int) $stats['rented'], 'bi-door-closed', 'properties.php?status=rented', 'navy'],
      ['Active tenants', $activeTenants, 'bi-people', 'tenants.php?status=active', 'teal'],
      ['Open repairs', $openJobs, 'bi-tools', 'maintenance.php', $openJobs ? 'danger' : 'navy'],
      ['New inquiries', $newInq, 'bi-envelope', 'inquiries.php?status=new', $newInq ? 'danger' : 'gold'],
  ];
  foreach ($cards as $c): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <a href="<?= url('admin/' . $c[3]) ?>" class="stat-card stat-<?= $c[4] ?>">
        <i class="bi <?= $c[2] ?>"></i><div><div class="stat-value"><?= $c[1] ?></div><div class="stat-label"><?= $c[0] ?></div></div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4"><div class="card card-body"><small class="text-muted">Collected this month</small><div class="fs-4 fw-semibold text-teal"><?= money($monthPaid, 2) ?></div></div></div>
  <div class="col-md-4"><div class="card card-body"><small class="text-muted">Monthly rent roll (active leases)</small><div class="fs-4 fw-semibold"><?= money($rentRoll, 2) ?></div></div></div>
  <div class="col-md-4"><div class="card card-body"><small class="text-muted">Repair costs this month</small><div class="fs-4 fw-semibold text-danger"><?= money($monthMaint, 2) ?></div></div></div>
</div>

<div class="row g-4">
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-calendar-x me-2"></i>Leases ending within 60 days</span><a href="<?= url('admin/leases.php') ?>" class="small">All leases</a></div>
      <div class="card-body p-0">
        <?php if (!$expiring): ?><p class="text-muted p-3 mb-0">No leases ending soon.</p><?php else: ?>
        <table class="table table-hover mb-0"><tbody>
          <?php foreach ($expiring as $l): $days = (int) floor((strtotime($l['end_date']) - strtotime(date('Y-m-d'))) / 86400); ?>
            <tr onclick="location.href='<?= url('admin/lease-view.php?id=' . $l['id']) ?>'" class="clickable">
              <td><strong><?= e($l['first_name'] . ' ' . $l['last_name']) ?></strong><div class="small text-muted"><?= e($l['title']) ?></div></td>
              <td class="text-end"><?= fmt_date($l['end_date']) ?><div class="small <?= $days < 0 ? 'text-danger' : ($days <= 30 ? 'text-warning' : 'text-muted') ?>"><?= $days < 0 ? abs($days) . ' days overdue' : $days . ' days left' ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-tools me-2"></i>Open repairs &amp; maintenance</span><a href="<?= url('admin/maintenance-edit.php') ?>" class="small"><i class="bi bi-plus"></i> Log job</a></div>
      <div class="card-body p-0">
        <?php if (!$jobs): ?><p class="text-muted p-3 mb-0">No open jobs.</p><?php else: ?>
        <table class="table table-hover mb-0"><tbody>
          <?php foreach ($jobs as $j): ?>
            <tr onclick="location.href='<?= url('admin/maintenance-edit.php?id=' . $j['id']) ?>'" class="clickable">
              <td><strong><?= e($j['title']) ?></strong><div class="small text-muted"><?= e($j['property_title']) ?></div></td>
              <td class="text-end"><?= status_badge($j['priority']) ?> <?= status_badge($j['status']) ?><div class="small text-muted"><?= fmt_date($j['reported_on']) ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-envelope me-2"></i>Latest inquiries</span><a href="<?= url('admin/inquiries.php') ?>" class="small">All inquiries</a></div>
      <div class="card-body p-0">
        <?php if (!$inquiries): ?><p class="text-muted p-3 mb-0">No inquiries yet.</p><?php else: ?>
        <table class="table table-hover mb-0"><tbody>
          <?php foreach ($inquiries as $i): ?>
            <tr onclick="location.href='<?= url('admin/inquiries.php?view=' . $i['id']) ?>'" class="clickable">
              <td><strong><?= e($i['name']) ?></strong> <span class="badge text-bg-light"><?= e($i['inquiry_type']) ?></span><div class="small text-muted text-truncate" style="max-width:340px"><?= e($i['title'] ?: mb_substr((string) $i['message'], 0, 80)) ?></div></td>
              <td class="text-end"><?= status_badge($i['status']) ?><div class="small text-muted"><?= fmt_date($i['created_at'], 'M j, g:i A') ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-xl-6">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-clock-history me-2"></i>Recent activity</div>
      <div class="card-body"><?php history_timeline($recent, true, true); ?></div>
    </div>
  </div>
  <?php if ($me['role'] === 'admin'):
      $online = [];
      $alerts = 0;
      try {
          $online = q_all('SELECT s.ip, s.user_agent, s.last_seen, s.session_key, u.name FROM user_sessions s JOIN users u ON u.id = s.user_id
                           WHERE s.kicked_at IS NULL AND s.last_seen > NOW() - INTERVAL 5 MINUTE ORDER BY s.last_seen DESC');
          $alerts = (int) q_val("SELECT COUNT(*) FROM audit_log WHERE action IN ('failed','locked') AND at > NOW() - INTERVAL 1 DAY");
      } catch (Exception $ex) {
      } ?>
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-people me-2"></i>Users online (<?= count($online) ?>)</span><a href="<?= url('admin/security.php') ?>" class="small">Security</a></div>
      <div class="card-body">
        <?php if ($alerts): ?><div class="alert alert-warning py-2 small mb-3"><i class="bi bi-exclamation-triangle me-1"></i><?= $alerts ?> failed or locked sign-in<?= $alerts === 1 ? '' : 's' ?> in the last 24 hours. <a href="<?= url('admin/audit.php?area=session') ?>">See the audit log</a>.</div><?php endif; ?>
        <?php if (!$online): ?><span class="hint">Nobody is signed in right now.</span><?php endif; ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($online as $s): $g = ip_country_cached($s['ip']); ?>
            <span class="pill <?= $s['session_key'] === session_key() ? 'adm' : 'act' ?>" title="<?= e(ua_short($s['user_agent'])) ?>">● <?= e($s['name']) ?> · <?= e($s['ip']) ?><?= $g ? ' ' . cc_flag($g['cc']) : '' ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer();
