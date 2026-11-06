<?php
require __DIR__ . '/includes/init.php';
require_admin();

$type = in_array(get('type'), ['rent', 'sale'], true) ? get('type') : '';
$status = get('status');
$loc = (int) get('location');
$kw = get('q');

$where = ['1=1'];
$params = [];
if ($type) { $where[] = 'p.listing_type = ?'; $params[] = $type; }
if ($status) { $where[] = 'p.status = ?'; $params[] = $status; }
if ($loc) { $where[] = 'p.location_id = ?'; $params[] = $loc; }
if ($kw !== '') {
    $where[] = '(p.title LIKE ? OR p.code LIKE ? OR p.building LIKE ? OR p.unit_no LIKE ? OR p.owner_name LIKE ?)';
    $k = '%' . $kw . '%';
    array_push($params, $k, $k, $k, $k, $k);
}
$rows = q_all("SELECT p.*, l.name AS location_name,
        (SELECT file FROM property_photos ph WHERE ph.property_id = p.id ORDER BY ph.is_cover DESC, ph.sort_order, ph.id LIMIT 1) AS cover,
        (SELECT COUNT(*) FROM property_photos ph WHERE ph.property_id = p.id) AS photo_count,
        (SELECT CONCAT(t.first_name, ' ', t.last_name) FROM leases le JOIN tenants t ON t.id = le.tenant_id WHERE le.property_id = p.id AND le.status = 'active' ORDER BY le.start_date DESC LIMIT 1) AS current_tenant,
        (SELECT COUNT(*) FROM maintenance m WHERE m.property_id = p.id AND m.status IN ('open','scheduled','in_progress')) AS open_jobs
    FROM properties p LEFT JOIN locations l ON l.id = p.location_id
    WHERE " . implode(' AND ', $where) . ' ORDER BY p.updated_at DESC', $params);
$locations = q_all('SELECT id, name FROM locations ORDER BY sort_order, name');

admin_header('Properties', 'properties');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex flex-wrap gap-2" method="get">
    <select name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">Rent &amp; sale</option><?= select_options(['rent' => 'For rent', 'sale' => 'For sale'], $type, true) ?></select>
    <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">Any status</option><?= select_options(['available' => 'Available', 'reserved' => 'Reserved', 'rented' => 'Rented', 'sold' => 'Sold', 'inactive' => 'Inactive (hidden)'], $status, true) ?></select>
    <select name="location" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All locations</option><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>"<?= $loc === (int) $l['id'] ? ' selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select>
    <input name="q" class="form-control form-control-sm w-auto" placeholder="Search title, code, owner…" value="<?= e($kw) ?>">
    <button class="btn btn-sm btn-light"><i class="bi bi-search"></i></button>
  </form>
  <a href="<?= url('admin/property-edit.php') ?>" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add property</a>
</div>

<div class="card">
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th></th><th>Property</th><th>Type</th><th>Price</th><th>Status</th><th>Tenant / Repairs</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No properties found.</td></tr><?php endif; ?>
    <?php foreach ($rows as $p): ?>
      <tr>
        <td><a href="<?= url('admin/property-view.php?id=' . $p['id']) ?>"><img src="<?= e(photo_url($p['cover'])) ?>" class="thumb" alt=""></a></td>
        <td>
          <a href="<?= url('admin/property-view.php?id=' . $p['id']) ?>" class="fw-semibold text-decoration-none"><?= e($p['title']) ?></a>
          <?php if ($p['is_featured']): ?><i class="bi bi-star-fill text-warning" title="Featured"></i><?php endif; ?>
          <div class="small text-muted"><?= e(implode(' · ', array_filter([$p['code'], $p['building'], $p['unit_no'] ? 'Unit ' . $p['unit_no'] : '', $p['location_name']]))) ?></div>
          <div class="small text-muted"><i class="bi bi-images"></i> <?= (int) $p['photo_count'] ?> · <i class="bi bi-eye"></i> <?= (int) $p['views'] ?></div>
        </td>
        <td><span class="badge <?= $p['listing_type'] === 'rent' ? 'bg-teal' : 'bg-gold' ?>"><?= $p['listing_type'] === 'rent' ? 'Rent' : 'Sale' ?></span><div class="small text-muted"><?= e($p['unit_type']) ?></div></td>
        <td class="text-nowrap"><?= money($p['price']) ?><?= $p['listing_type'] === 'rent' ? '<small class="text-muted">/' . e($p['price_period'] ?: 'mo') . '</small>' : '' ?></td>
        <td><?= status_badge($p['status']) ?></td>
        <td class="small">
          <?= $p['current_tenant'] ? '<i class="bi bi-person"></i> ' . e($p['current_tenant']) : '<span class="text-muted">—</span>' ?>
          <?php if ($p['open_jobs']): ?><div class="text-danger"><i class="bi bi-tools"></i> <?= (int) $p['open_jobs'] ?> open</div><?php endif; ?>
        </td>
        <td class="text-end text-nowrap">
          <a href="<?= url('admin/property-view.php?id=' . $p['id']) ?>" class="btn btn-sm btn-light" title="Open"><i class="bi bi-folder2-open"></i></a>
          <a href="<?= url('admin/property-edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-light" title="Edit"><i class="bi bi-pencil"></i></a>
          <a href="<?= url('property.php?slug=' . urlencode($p['slug'])) ?>" target="_blank" class="btn btn-sm btn-light" title="View on site"><i class="bi bi-box-arrow-up-right"></i></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php admin_footer();
