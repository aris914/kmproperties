<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) get('id');
$p = q_one('SELECT p.*, l.name AS location_name FROM properties p LEFT JOIN locations l ON l.id = p.location_id WHERE p.id = ?', [$id]);
if (!$p) {
    flash('error', 'Property not found.');
    redirect('admin/properties.php');
}
$tabs = ['overview' => 'Overview', 'photos' => 'Photos', 'documents' => 'Documents', 'tenancy' => 'Tenancy history', 'maintenance' => 'Repairs & maintenance', 'history' => 'History'];
$tab = isset($tabs[get('tab')]) ? get('tab') : 'overview';
$self = url('admin/property-view.php?id=' . $id . '&tab=' . $tab);

$photoCount = (int) q_val('SELECT COUNT(*) FROM property_photos WHERE property_id = ?', [$id]);
$docCount = (int) q_val("SELECT COUNT(*) FROM documents WHERE entity_type = 'property' AND entity_id = ?", [$id]);
$leaseCount = (int) q_val('SELECT COUNT(*) FROM leases WHERE property_id = ?', [$id]);
$jobStats = q_one("SELECT COUNT(*) AS total, SUM(status IN ('open','scheduled','in_progress')) AS open_jobs, COALESCE(SUM(CASE WHEN status = 'completed' THEN cost END),0) AS spent FROM maintenance WHERE property_id = ?", [$id]);
$current = q_one("SELECT l.*, t.first_name, t.last_name, t.phone, t.email FROM leases l JOIN tenants t ON t.id = l.tenant_id WHERE l.property_id = ? AND l.status = 'active' ORDER BY l.start_date DESC LIMIT 1", [$id]);
$cover = property_cover($id);

admin_header($p['title'], 'properties');
?>
<div class="record-head">
  <img src="<?= e(photo_url($cover)) ?>" alt="" class="record-img">
  <div class="flex-fill">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
      <span class="badge <?= $p['listing_type'] === 'rent' ? 'bg-teal' : 'bg-gold' ?>"><?= $p['listing_type'] === 'rent' ? 'For Rent' : 'For Sale' ?></span>
      <?= status_badge($p['status']) ?>
      <?php if ($p['is_featured']): ?><span class="badge text-bg-warning"><i class="bi bi-star-fill"></i> Featured</span><?php endif; ?>
      <?php if ($p['code']): ?><span class="text-muted small"><?= e($p['code']) ?></span><?php endif; ?>
    </div>
    <h2 class="h4 mb-1"><?= e($p['title']) ?></h2>
    <div class="text-muted small"><i class="bi bi-geo-alt"></i> <?= e(implode(', ', array_filter([$p['building'], $p['unit_no'] ? 'Unit ' . $p['unit_no'] : '', $p['location_name']]))) ?></div>
    <div class="fs-5 fw-semibold text-teal mt-1"><?= money($p['price']) ?><?= $p['listing_type'] === 'rent' ? ' <small class="text-muted fw-normal">/ ' . e($p['price_period'] ?: 'month') . '</small>' : '' ?></div>
  </div>
  <div class="d-flex flex-wrap gap-2 align-self-start">
    <a href="<?= url('admin/property-edit.php?id=' . $id) ?>" class="btn btn-navy"><i class="bi bi-pencil me-1"></i>Edit</a>
    <?php if ($p['listing_type'] === 'rent'): ?><a href="<?= url('admin/lease-edit.php?property_id=' . $id) ?>" class="btn btn-outline-primary"><i class="bi bi-key me-1"></i>New lease</a><?php endif; ?>
    <a href="<?= url('admin/maintenance-edit.php?property_id=' . $id) ?>" class="btn btn-outline-primary"><i class="bi bi-tools me-1"></i>Log repair</a>
    <a href="<?= url('property.php?slug=' . urlencode($p['slug'])) ?>" target="_blank" class="btn btn-light"><i class="bi bi-box-arrow-up-right"></i></a>
  </div>
</div>

<ul class="nav nav-tabs record-tabs mb-4">
  <?php foreach ($tabs as $k => $label):
      $count = ['photos' => $photoCount, 'documents' => $docCount, 'tenancy' => $leaseCount, 'maintenance' => (int) $jobStats['total']][$k] ?? null; ?>
    <li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= url('admin/property-view.php?id=' . $id . '&tab=' . $k) ?>"><?= e($label) ?><?php if ($count): ?> <span class="badge rounded-pill text-bg-light"><?= $count ?></span><?php endif; ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-4">
  <div class="col-xl-8">
    <div class="card mb-4"><div class="card-body">
      <div class="row g-3 small">
        <?php
        $facts = [
            'Property type' => $p['property_type'], 'Unit type' => $p['unit_type'], 'Bedrooms' => $p['bedrooms'] ? $p['bedrooms'] : 'Studio',
            'Bathrooms' => $p['bathrooms'], 'Floor area' => $p['floor_area'] ? (float) $p['floor_area'] . ' sqm' : '—', 'Floor' => $p['floor_level'] ?: '—',
            'Parking' => $p['parking'], 'Furnishing' => $p['furnishing'], 'Website views' => $p['views'],
            'Added' => fmt_date($p['created_at']), 'Last updated' => fmt_date($p['updated_at']),
        ];
        foreach ($facts as $k => $val): ?>
          <div class="col-6 col-md-4"><div class="text-muted"><?= e($k) ?></div><div class="fw-semibold"><?= e($val) ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php if ($p['description']): ?><hr><div><?= nl2p($p['description']) ?></div><?php endif; ?>
      <?php if ($p['amenities']): ?><hr><div class="d-flex flex-wrap gap-2"><?php foreach (lines($p['amenities']) as $a): ?><span class="badge text-bg-light border"><i class="bi bi-check2 text-teal"></i> <?= e($a) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-xl-4">
    <?php if ($p['listing_type'] === 'rent'): ?>
    <div class="card mb-4">
      <div class="card-header">Current tenant</div>
      <div class="card-body">
        <?php if ($current): ?>
          <a href="<?= url('admin/tenant-view.php?id=' . $current['tenant_id']) ?>" class="fw-semibold"><?= e($current['first_name'] . ' ' . $current['last_name']) ?></a>
          <div class="small text-muted"><?= e($current['phone']) ?> <?= e($current['email']) ?></div>
          <div class="small mt-2">Lease: <?= fmt_date($current['start_date']) ?> – <?= fmt_date($current['end_date']) ?><br>Rent: <?= money($current['monthly_rent'], 2) ?>/mo</div>
          <a href="<?= url('admin/lease-view.php?id=' . $current['id']) ?>" class="btn btn-sm btn-light mt-2">Open lease</a>
        <?php else: ?>
          <p class="text-muted mb-2">Vacant.</p>
          <a href="<?= url('admin/lease-edit.php?property_id=' . $id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-key me-1"></i>Start a lease</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
    <div class="card mb-4">
      <div class="card-header">Repairs &amp; maintenance</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between"><span>Jobs recorded</span><strong><?= (int) $jobStats['total'] ?></strong></div>
        <div class="d-flex justify-content-between"><span>Open now</span><strong class="<?= $jobStats['open_jobs'] ? 'text-danger' : '' ?>"><?= (int) $jobStats['open_jobs'] ?></strong></div>
        <div class="d-flex justify-content-between"><span>Total spent</span><strong><?= money($jobStats['spent'], 2) ?></strong></div>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header">Owner</div>
      <div class="card-body small">
        <div class="fw-semibold"><?= e($p['owner_name'] ?: '—') ?></div>
        <div><?= e($p['owner_phone']) ?></div><div><?= e($p['owner_email']) ?></div>
        <?php if ($p['internal_notes']): ?><hr><div class="text-muted"><?= nl2br(e($p['internal_notes'])) ?></div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'photos'):
    $photos = q_all('SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_cover DESC, sort_order, id', [$id]); ?>
<div class="card mb-4">
  <div class="card-body">
    <form method="post" action="<?= url('admin/photo-action.php') ?>" enctype="multipart/form-data" class="upload-box row g-2 align-items-end">
      <?= csrf_field() ?><input type="hidden" name="action" value="upload"><input type="hidden" name="property_id" value="<?= $id ?>">
      <div class="col-md-9"><label class="form-label small">Add photos — select several at once (JPG, PNG, WebP; up to <?= (int) MAX_PHOTO_MB ?> MB each; large photos are resized automatically)</label><input type="file" name="photos[]" class="form-control" accept="image/*" multiple required id="photoInput"></div>
      <div class="col-md-3"><button class="btn btn-navy w-100"><i class="bi bi-upload me-1"></i>Upload photos</button></div>
      <div class="col-12"><div class="photo-preview" id="photoPreview"></div></div>
    </form>
  </div>
</div>
<?php if (!$photos): ?>
  <p class="text-muted">No photos yet. Listings with photos get far more inquiries.</p>
<?php else: ?>
  <form method="post" action="<?= url('admin/photo-action.php') ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_all"><input type="hidden" name="property_id" value="<?= $id ?>">
    <button type="submit" tabindex="-1" aria-hidden="true" style="position:absolute;left:-9999px">Save</button>
    <div class="row g-3">
      <?php foreach ($photos as $ph): ?>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="photo-card<?= $ph['is_cover'] ? ' is-cover' : '' ?>">
            <a href="<?= e(photo_url($ph['file'])) ?>" target="_blank"><img src="<?= e(photo_url($ph['file'])) ?>" alt=""></a>
            <?php if ($ph['is_cover']): ?><span class="cover-badge"><i class="bi bi-star-fill"></i> Cover</span><?php endif; ?>
            <div class="p-2">
              <input name="caption[<?= $ph['id'] ?>]" class="form-control form-control-sm mb-1" placeholder="Caption" value="<?= e($ph['caption']) ?>" maxlength="200">
              <div class="d-flex gap-1">
                <input type="number" name="sort[<?= $ph['id'] ?>]" class="form-control form-control-sm" value="<?= (int) $ph['sort_order'] ?>" title="Order" style="width:70px">
                <?php if (!$ph['is_cover']): ?><button name="cover" value="<?= $ph['id'] ?>" class="btn btn-sm btn-light" title="Make cover"><i class="bi bi-star"></i></button><?php endif; ?>
                <button name="delete" value="<?= $ph['id'] ?>" class="btn btn-sm btn-light text-danger ms-auto" title="Delete" onclick="return confirm('Delete this photo?')"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="sticky-save text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save captions &amp; order</button></div>
  </form>
<?php endif; ?>

<?php elseif ($tab === 'documents'): ?>
<div class="card"><div class="card-body">
  <p class="small text-muted">Title, deed, tax declarations, condo dues, floor plan, owner authority to list, etc. Documents are private — only logged-in admins can open them.</p>
  <?php documents_panel('property', $id, $self, ['Title / Deed', 'Tax declaration', 'Contract', 'Authority to sell / lease', 'Floor plan', 'Dues / Bills', 'Permit', 'Inspection report', 'Other']); ?>
</div></div>

<?php elseif ($tab === 'tenancy'):
    $leases = q_all('SELECT l.*, t.first_name, t.last_name, (SELECT COALESCE(SUM(amount),0) FROM payments pa WHERE pa.lease_id = l.id) AS paid
                     FROM leases l JOIN tenants t ON t.id = l.tenant_id WHERE l.property_id = ? ORDER BY l.start_date DESC', [$id]); ?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>All tenants who have leased this property</span>
    <a href="<?= url('admin/lease-edit.php?property_id=' . $id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> New lease</a>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Tenant</th><th>Period</th><th>Rent</th><th>Deposit</th><th>Total paid</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (!$leases): ?><tr><td colspan="7" class="text-muted text-center py-4">No leases recorded for this property.</td></tr><?php endif; ?>
    <?php foreach ($leases as $l): ?>
      <tr>
        <td><a href="<?= url('admin/tenant-view.php?id=' . $l['tenant_id']) ?>" class="fw-semibold"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></a></td>
        <td class="small"><?= fmt_date($l['start_date']) ?> – <?= $l['move_out_date'] ? fmt_date($l['move_out_date']) : fmt_date($l['end_date']) ?><?php if ($l['end_reason']): ?><div class="text-muted"><?= e($l['end_reason']) ?></div><?php endif; ?></td>
        <td><?= money($l['monthly_rent'], 2) ?></td>
        <td><?= money($l['deposit'], 2) ?></td>
        <td><?= money($l['paid'], 2) ?></td>
        <td><?= status_badge($l['status']) ?></td>
        <td class="text-end"><a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-folder2-open"></i></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab === 'maintenance'):
    $jobs = q_all('SELECT m.*, t.first_name, t.last_name, (SELECT COUNT(*) FROM documents d WHERE d.entity_type = \'maintenance\' AND d.entity_id = m.id) AS docs
                   FROM maintenance m LEFT JOIN tenants t ON t.id = m.tenant_id WHERE m.property_id = ? ORDER BY m.reported_on DESC, m.id DESC', [$id]); ?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span>Repairs &amp; maintenance log · total spent <strong><?= money($jobStats['spent'], 2) ?></strong></span>
    <a href="<?= url('admin/maintenance-edit.php?property_id=' . $id) ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Log repair / maintenance</a>
  </div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Reported</th><th>Job</th><th>Contractor</th><th>Cost</th><th>Status</th><th>Completed</th><th></th></tr></thead>
    <tbody>
    <?php if (!$jobs): ?><tr><td colspan="7" class="text-muted text-center py-4">No repairs or maintenance recorded.</td></tr><?php endif; ?>
    <?php foreach ($jobs as $j): ?>
      <tr>
        <td class="small text-nowrap"><?= fmt_date($j['reported_on']) ?></td>
        <td><strong><?= e($j['title']) ?></strong> <?php if ($j['docs']): ?><i class="bi bi-paperclip text-muted" title="<?= (int) $j['docs'] ?> file(s)"></i><?php endif; ?>
          <div class="small text-muted"><?= e(ucfirst($j['category'])) ?><?= $j['area'] ? ' · ' . e($j['area']) : '' ?><?= $j['first_name'] ? ' · reported by tenant ' . e($j['first_name'] . ' ' . $j['last_name']) : '' ?></div></td>
        <td class="small"><?= e($j['contractor'] ?: '—') ?></td>
        <td class="text-nowrap"><?= $j['cost'] > 0 ? money($j['cost'], 2) . '<div class="small text-muted">' . e($j['charged_to']) . '</div>' : '—' ?></td>
        <td><?= status_badge($j['status']) ?><?php if ($j['priority'] === 'urgent' || $j['priority'] === 'high'): ?> <?= status_badge($j['priority']) ?><?php endif; ?></td>
        <td class="small"><?= fmt_date($j['completed_on']) ?></td>
        <td class="text-end"><a href="<?= url('admin/maintenance-edit.php?id=' . $j['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab === 'history'): ?>
<div class="card"><div class="card-body">
  <?php history_note_form($id, 0, $self); ?>
  <?php history_timeline(history_query('h.property_id = ?', [$id], 500), false, true); ?>
</div></div>
<?php endif; ?>
<?php admin_footer();
