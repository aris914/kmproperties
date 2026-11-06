<?php
require __DIR__ . '/includes/init.php';
require_admin();

$status = get('status');
$kw = get('q');
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 't.status = ?'; $params[] = $status; }
if ($kw !== '') {
    $where[] = "(t.first_name LIKE ? OR t.last_name LIKE ? OR t.email LIKE ? OR t.phone LIKE ? OR CONCAT(t.first_name, ' ', t.last_name) LIKE ?)";
    $k = '%' . $kw . '%';
    array_push($params, $k, $k, $k, $k, $k);
}
$rows = q_all("SELECT t.*,
        (SELECT p.title FROM leases l JOIN properties p ON p.id = l.property_id WHERE l.tenant_id = t.id AND l.status = 'active' ORDER BY l.start_date DESC LIMIT 1) AS current_property,
        (SELECT l.id FROM leases l WHERE l.tenant_id = t.id AND l.status = 'active' ORDER BY l.start_date DESC LIMIT 1) AS current_lease,
        (SELECT COUNT(*) FROM leases l WHERE l.tenant_id = t.id) AS lease_count
    FROM tenants t WHERE " . implode(' AND ', $where) . ' ORDER BY t.last_name, t.first_name', $params);

admin_header('Tenants', 'tenants');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex flex-wrap gap-2" method="get">
    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All tenants</option><?= select_options(['active' => 'Active', 'prospect' => 'Prospect / applicant', 'former' => 'Former', 'blacklisted' => 'Blacklisted'], $status, true) ?></select>
    <input name="q" class="form-control form-control-sm w-auto" placeholder="Name, phone, email…" value="<?= e($kw) ?>">
    <button class="btn btn-sm btn-light"><i class="bi bi-search"></i></button>
  </form>
  <a href="<?= url('admin/tenant-edit.php') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add tenant</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Tenant</th><th>Contact</th><th>Current unit</th><th>Leases</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No tenants found.</td></tr><?php endif; ?>
  <?php foreach ($rows as $t): ?>
    <tr class="clickable" onclick="if(!event.target.closest('a,button'))location.href='<?= url('admin/tenant-view.php?id=' . $t['id']) ?>'">
      <td><span class="avatar-sm"><?= e(mb_strtoupper(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1))) ?></span> <strong><?= e($t['last_name'] . ', ' . $t['first_name']) ?></strong><?php if (!empty($t['is_test'])): ?> <span class="badge text-bg-secondary">TEST</span><?php endif; ?><?php if ($t['occupation']): ?><div class="small text-muted ms-5"><?= e($t['occupation']) ?></div><?php endif; ?></td>
      <td class="small"><?= e($t['phone']) ?><div class="text-muted"><?= e($t['email']) ?></div></td>
      <td class="small"><?= $t['current_property'] ? '<a href="' . url('admin/lease-view.php?id=' . $t['current_lease']) . '">' . e($t['current_property']) . '</a>' : '<span class="text-muted">—</span>' ?></td>
      <td><?= (int) $t['lease_count'] ?></td>
      <td><?= status_badge($t['status']) ?></td>
      <td class="text-end text-nowrap">
        <a href="<?= url('admin/tenant-view.php?id=' . $t['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-folder2-open"></i></a>
        <a href="<?= url('admin/tenant-edit.php?id=' . $t['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php admin_footer();
