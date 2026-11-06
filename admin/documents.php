<?php
require __DIR__ . '/includes/init.php';
require_admin();

$type = in_array(get('type'), ['property', 'tenant', 'lease', 'maintenance'], true) ? get('type') : '';
$kw = get('q');
$where = ['1=1'];
$params = [];
if ($type) { $where[] = 'd.entity_type = ?'; $params[] = $type; }
if ($kw !== '') { $where[] = '(d.title LIKE ? OR d.original_name LIKE ? OR d.category LIKE ?)'; $k = '%' . $kw . '%'; array_push($params, $k, $k, $k); }

$docs = q_all("SELECT d.*, u.name AS uploader,
        CASE d.entity_type
          WHEN 'property' THEN (SELECT title FROM properties WHERE id = d.entity_id)
          WHEN 'tenant' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM tenants WHERE id = d.entity_id)
          WHEN 'lease' THEN (SELECT CONCAT(t.first_name, ' ', t.last_name, ' — ', p.title) FROM leases l JOIN tenants t ON t.id = l.tenant_id JOIN properties p ON p.id = l.property_id WHERE l.id = d.entity_id)
          WHEN 'maintenance' THEN (SELECT CONCAT(m.title, ' — ', p.title) FROM maintenance m JOIN properties p ON p.id = m.property_id WHERE m.id = d.entity_id)
        END AS belongs_to
    FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by
    WHERE " . implode(' AND ', $where) . ' ORDER BY d.created_at DESC LIMIT 500', $params);

$links = [
    'property' => 'admin/property-view.php?tab=documents&id=',
    'tenant' => 'admin/tenant-view.php?tab=documents&id=',
    'lease' => 'admin/lease-view.php?id=',
    'maintenance' => 'admin/maintenance-edit.php?id=',
];
$totalSize = array_sum(array_column($docs, 'size'));

admin_header('Documents', 'documents');
?>
<div class="d-flex flex-wrap gap-2 justify-content-between mb-3">
  <form class="d-flex flex-wrap gap-2" method="get">
    <select name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All records</option><?= select_options(['property' => 'Properties', 'tenant' => 'Tenants', 'lease' => 'Leases', 'maintenance' => 'Repairs & maintenance'], $type, true) ?></select>
    <input name="q" class="form-control form-control-sm w-auto" placeholder="Search documents…" value="<?= e($kw) ?>">
    <button class="btn btn-sm btn-light"><i class="bi bi-search"></i></button>
  </form>
  <span class="text-muted small align-self-center"><?= count($docs) ?> file(s) · <?= human_size($totalSize) ?></span>
</div>
<div class="alert alert-light border small"><i class="bi bi-info-circle me-1"></i>To upload, open the property, tenant, lease or repair job and use its Documents section.</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Document</th><th>Belongs to</th><th>Category</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
  <tbody>
  <?php if (!$docs): ?><tr><td colspan="6" class="text-center text-muted py-4">No documents found.</td></tr><?php endif; ?>
  <?php foreach ($docs as $d): ?>
    <tr>
      <td><i class="bi <?= doc_icon($d['original_name']) ?> text-teal me-1"></i><a href="<?= url('admin/download.php?id=' . $d['id']) ?>" target="_blank"><?= e($d['title']) ?></a><div class="small text-muted"><?= e($d['original_name']) ?></div></td>
      <td class="small"><span class="badge text-bg-light"><?= e($d['entity_type']) ?></span> <a href="<?= url($links[$d['entity_type']] . $d['entity_id']) ?>"><?= e($d['belongs_to'] ?: '(deleted)') ?></a></td>
      <td class="small"><?= e($d['category']) ?></td>
      <td class="small"><?= human_size($d['size']) ?></td>
      <td class="small"><?= fmt_date($d['created_at']) ?><div class="text-muted"><?= e($d['uploader']) ?></div></td>
      <td class="text-end"><a href="<?= url('admin/download.php?id=' . $d['id'] . '&dl=1') ?>" class="btn btn-sm btn-light"><i class="bi bi-download"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>
<?php admin_footer();
