<?php
require __DIR__ . '/includes/init.php';
require_admin();

$status = get('status', 'open');
$cat = get('category');
$pid = (int) get('property_id');
$year = (int) get('year');

$where = ['1=1'];
$params = [];
if ($status === 'open') {
    $where[] = "m.status IN ('open','scheduled','in_progress')";
} elseif (in_array($status, ['completed', 'cancelled'], true)) {
    $where[] = 'm.status = ?';
    $params[] = $status;
}
if ($cat) { $where[] = 'm.category = ?'; $params[] = $cat; }
if ($pid) { $where[] = 'm.property_id = ?'; $params[] = $pid; }
if ($year) { $where[] = 'YEAR(m.reported_on) = ?'; $params[] = $year; }
$whereSql = implode(' AND ', $where);

$rows = q_all("SELECT m.*, p.title AS property_title, t.first_name, t.last_name,
        (SELECT COUNT(*) FROM documents d WHERE d.entity_type = 'maintenance' AND d.entity_id = m.id) AS docs
    FROM maintenance m JOIN properties p ON p.id = m.property_id LEFT JOIN tenants t ON t.id = m.tenant_id
    WHERE $whereSql ORDER BY (m.status IN ('open','scheduled','in_progress')) DESC, FIELD(m.priority,'urgent','high','normal','low'), m.reported_on DESC LIMIT 500", $params);
$totalCost = array_sum(array_column($rows, 'cost'));
$years = q_all('SELECT DISTINCT YEAR(reported_on) AS y FROM maintenance ORDER BY y DESC');

admin_header('Repairs & Maintenance', 'maintenance');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex flex-wrap gap-2" method="get">
    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><?= select_options(['open' => 'Open / in progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All jobs'], $status, true) ?></select>
    <select name="category" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All types</option><?= select_options(['repair' => 'Repair', 'maintenance' => 'Maintenance', 'inspection' => 'Inspection', 'renovation' => 'Renovation', 'cleaning' => 'Cleaning', 'other' => 'Other'], $cat, true) ?></select>
    <select name="property_id" class="form-select form-select-sm w-auto" style="max-width:260px" onchange="this.form.submit()"><option value="">All properties</option><?= property_options($pid) ?></select>
    <select name="year" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">Any year</option><?php foreach ($years as $y): ?><option<?= $year === (int) $y['y'] ? ' selected' : '' ?>><?= (int) $y['y'] ?></option><?php endforeach; ?></select>
  </form>
  <a href="<?= url('admin/maintenance-edit.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Log repair / maintenance</a>
</div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Reported</th><th>Job</th><th>Property</th><th>Contractor</th><th>Cost</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No jobs found.</td></tr><?php endif; ?>
  <?php foreach ($rows as $j): ?>
    <tr class="clickable" onclick="if(!event.target.closest('a,button'))location.href='<?= url('admin/maintenance-edit.php?id=' . $j['id']) ?>'">
      <td class="small text-nowrap"><?= fmt_date($j['reported_on']) ?><?php if ($j['scheduled_on'] && $j['status'] !== 'completed'): ?><div class="text-info">sched. <?= fmt_date($j['scheduled_on'], 'M j') ?></div><?php endif; ?></td>
      <td><strong><?= e($j['title']) ?></strong> <?php if ($j['docs']): ?><i class="bi bi-paperclip text-muted"></i><?php endif; ?><div class="small text-muted"><?= e(ucfirst($j['category'])) ?><?= $j['area'] ? ' · ' . e($j['area']) : '' ?><?= $j['first_name'] ? ' · ' . e($j['first_name'] . ' ' . $j['last_name']) : '' ?></div></td>
      <td class="small"><a href="<?= url('admin/property-view.php?id=' . $j['property_id'] . '&tab=maintenance') ?>"><?= e($j['property_title']) ?></a></td>
      <td class="small"><?= e($j['contractor'] ?: '—') ?></td>
      <td class="small text-nowrap"><?= $j['cost'] > 0 ? money($j['cost'], 2) . '<div class="text-muted">' . e($j['charged_to']) . '</div>' : '—' ?></td>
      <td><?= status_badge($j['status']) ?> <?php if ($j['status'] !== 'completed' && $j['status'] !== 'cancelled'): ?><?= status_badge($j['priority']) ?><?php endif; ?></td>
      <td class="text-end"><a href="<?= url('admin/maintenance-edit.php?id=' . $j['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
  <?php if ($rows): ?><tfoot><tr><td colspan="4" class="text-end text-muted">Total cost shown</td><td colspan="3" class="fw-semibold"><?= money($totalCost, 2) ?></td></tr></tfoot><?php endif; ?>
</table></div></div>
<?php admin_footer();
