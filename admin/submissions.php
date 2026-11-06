<?php
/* Admin › Owner Submissions (1.2.0): properties sent through List Your Property, waiting for approval. */
require __DIR__ . '/includes/init.php';
require_admin();

$tab = in_array(get('tab'), ['pending', 'approved', 'rejected'], true) ? get('tab') : 'pending';
$counts = [];
foreach (q_all("SELECT approval, COUNT(*) AS n FROM properties WHERE source = 'owner' GROUP BY approval") as $r) {
    $counts[$r['approval']] = (int) $r['n'];
}
$rows = q_all("SELECT p.*, l.name AS location_name, u.name AS reviewer,
        (SELECT file FROM property_photos ph WHERE ph.property_id = p.id ORDER BY ph.is_cover DESC, ph.sort_order, ph.id LIMIT 1) AS cover,
        (SELECT COUNT(*) FROM property_photos ph WHERE ph.property_id = p.id) AS photo_count
    FROM properties p LEFT JOIN locations l ON l.id = p.location_id LEFT JOIN users u ON u.id = p.reviewed_by
    WHERE p.source = 'owner' AND p.approval = ? ORDER BY " . ($tab === 'pending' ? 'p.submitted_at ASC' : 'p.reviewed_at DESC, p.submitted_at DESC') . ' LIMIT 200', [$tab]);
$self = url('admin/submissions.php?tab=' . $tab);

admin_header('Owner Submissions', 'submissions');
?>
<p class="hint">Properties that owners send through <a href="<?= url('list-property.php') ?>" target="_blank">List Your Property</a>. They stay hidden until you approve them. Review the details and photos, edit anything that needs fixing, then <b>Approve &amp; publish</b>.</p>

<ul class="nav nav-tabs record-tabs mb-4">
  <?php foreach (['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $k => $l): ?>
    <li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= url('admin/submissions.php?tab=' . $k) ?>"><?= $l ?><?php if (!empty($counts[$k])): ?> <span class="badge rounded-pill <?= $k === 'pending' ? 'text-bg-danger' : 'text-bg-light' ?>"><?= $counts[$k] ?></span><?php endif; ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if (!$rows): ?>
  <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i><?= $tab === 'pending' ? 'No submissions waiting for approval.' : 'Nothing here yet.' ?></div>
<?php endif; ?>

<div class="row g-3">
<?php foreach ($rows as $p): ?>
  <div class="col-12">
    <div class="card sub-card">
      <div class="row g-0">
        <div class="col-md-3"><a href="<?= url('admin/property-view.php?id=' . $p['id']) ?>"><img src="<?= e(photo_url($p['cover'])) ?>" class="sub-img" alt=""></a></div>
        <div class="col-md-9"><div class="card-body">
          <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
              <span class="badge <?= $p['listing_type'] === 'rent' ? 'bg-teal' : 'bg-gold' ?>"><?= $p['listing_type'] === 'rent' ? 'For Rent' : 'For Sale' ?></span>
              <a href="<?= url('admin/property-view.php?id=' . $p['id']) ?>" class="fw-semibold ms-1 text-decoration-none"><?= e($p['title']) ?></a>
              <div class="small text-muted mt-1"><i class="bi bi-geo-alt"></i> <?= e(implode(', ', array_filter([$p['building'], $p['unit_no'] ? 'Unit ' . $p['unit_no'] : '', $p['location_name'] ?: 'Other location']))) ?></div>
            </div>
            <div class="text-end"><div class="fs-5 fw-semibold text-teal"><?= money($p['price']) ?><?= $p['listing_type'] === 'rent' ? '<small class="text-muted fw-normal">/mo</small>' : '' ?></div><div class="small text-muted">sent <?= fmt_date($p['submitted_at'], 'M j, Y g:i A') ?></div></div>
          </div>
          <div class="d-flex flex-wrap gap-3 small mt-2">
            <span><i class="bi bi-person"></i> <?= e($p['owner_name']) ?></span>
            <?php if ($p['owner_phone']): ?><a href="tel:<?= e($p['owner_phone']) ?>"><i class="bi bi-telephone"></i> <?= e($p['owner_phone']) ?></a><?php endif; ?>
            <?php if ($p['owner_email']): ?><a href="mailto:<?= e($p['owner_email']) ?>"><i class="bi bi-envelope"></i> <?= e($p['owner_email']) ?></a><?php endif; ?>
            <span><i class="bi bi-images"></i> <?= (int) $p['photo_count'] ?> photo<?= $p['photo_count'] == 1 ? '' : 's' ?></span>
            <span><?= $p['latitude'] !== null ? '<i class="bi bi-pin-map-fill text-success"></i> Map pin' : '<i class="bi bi-pin-map text-muted"></i> No map pin' ?></span>
            <span><i class="bi bi-door-closed"></i> <?= $p['bedrooms'] ? (int) $p['bedrooms'] . ' BR' : 'Studio' ?> · <?= (int) $p['bathrooms'] ?> bath<?= $p['floor_area'] ? ' · ' . (float) $p['floor_area'] . ' sqm' : '' ?></span>
          </div>
          <?php if ($p['review_note'] || $p['reviewer']): ?><div class="small mt-2 text-muted"><i class="bi bi-chat-left-text"></i> <?= $p['reviewer'] ? e($p['reviewer']) . ', ' . fmt_date($p['reviewed_at']) : '' ?><?= $p['review_note'] ? ': ' . e($p['review_note']) : '' ?></div><?php endif; ?>
          <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?= url('admin/property-view.php?id=' . $p['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-folder2-open me-1"></i>Review</a>
            <a href="<?= url('admin/property-edit.php?id=' . $p['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil me-1"></i>Edit</a>
            <a href="<?= url('property.php?slug=' . urlencode($p['slug'])) ?>" target="_blank" class="btn btn-sm btn-light"><i class="bi bi-eye me-1"></i>Preview</a>
            <?php review_buttons($p, $self); ?>
          </div>
        </div></div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php review_modal(); ?>
<?php admin_footer();
