<?php
require __DIR__ . '/includes/init.php';
require_admin();

$status = get('status', 'active');
$where = ['1=1'];
$params = [];
if ($status !== 'all' && in_array($status, ['active', 'ended', 'terminated', 'upcoming'], true)) {
    $where[] = 'l.status = ?';
    $params[] = $status;
}
$rows = q_all("SELECT l.*, p.title, p.code, t.first_name, t.last_name,
        (SELECT COALESCE(SUM(amount),0) FROM payments pa WHERE pa.lease_id = l.id) AS paid,
        (SELECT MAX(paid_on) FROM payments pa WHERE pa.lease_id = l.id AND pa.payment_type = 'rent') AS last_rent
    FROM leases l JOIN properties p ON p.id = l.property_id JOIN tenants t ON t.id = l.tenant_id
    WHERE " . implode(' AND ', $where) . ' ORDER BY l.status = \'active\' DESC, l.end_date IS NULL, l.end_date, l.start_date DESC', $params);

$recentPays = q_all('SELECT pa.*, t.first_name, t.last_name, p.title FROM payments pa JOIN leases l ON l.id = pa.lease_id JOIN tenants t ON t.id = l.tenant_id JOIN properties p ON p.id = l.property_id ORDER BY pa.paid_on DESC, pa.id DESC LIMIT 10');

admin_header('Leases & Payments', 'leases');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <div class="btn-group">
    <?php foreach (['active' => 'Active', 'upcoming' => 'Upcoming', 'ended' => 'Ended', 'terminated' => 'Terminated', 'all' => 'All'] as $k => $label): ?>
      <a href="<?= url('admin/leases.php?status=' . $k) ?>" class="btn btn-sm <?= $status === $k ? 'btn-navy' : 'btn-light' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  <a href="<?= url('admin/lease-edit.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New lease</a>
</div>

<div class="card mb-4"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Tenant</th><th>Property</th><th>Period</th><th>Rent</th><th>Last rent paid</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No leases.</td></tr><?php endif; ?>
  <?php foreach ($rows as $l):
      $endingSoon = $l['status'] === 'active' && $l['end_date'] && strtotime($l['end_date']) <= strtotime('+60 days'); ?>
    <tr>
      <td><a href="<?= url('admin/tenant-view.php?id=' . $l['tenant_id']) ?>" class="fw-semibold"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></a></td>
      <td class="small"><a href="<?= url('admin/property-view.php?id=' . $l['property_id']) ?>"><?= e($l['title']) ?></a></td>
      <td class="small"><?= fmt_date($l['start_date']) ?> – <span class="<?= $endingSoon ? 'text-danger fw-semibold' : '' ?>"><?= fmt_date($l['end_date']) ?></span></td>
      <td><?= money($l['monthly_rent'], 2) ?></td>
      <td class="small"><?= fmt_date($l['last_rent']) ?></td>
      <td><?= status_badge($l['status']) ?></td>
      <td class="text-end"><a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-folder2-open"></i> Open</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<div class="card">
  <div class="card-header">Latest payments</div>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <tbody>
    <?php if (!$recentPays): ?><tr><td class="text-muted p-3">No payments recorded yet. Open a lease to record one.</td></tr><?php endif; ?>
    <?php foreach ($recentPays as $pa): ?>
      <tr>
        <td class="small"><?= fmt_date($pa['paid_on']) ?></td>
        <td class="small"><a href="<?= url('admin/lease-view.php?id=' . $pa['lease_id']) ?>"><?= e($pa['first_name'] . ' ' . $pa['last_name']) ?></a> · <?= e($pa['title']) ?></td>
        <td class="small"><?= e(ucfirst($pa['payment_type'])) ?> <?= e($pa['period_label']) ?></td>
        <td class="text-end"><?= money($pa['amount'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php admin_footer();
