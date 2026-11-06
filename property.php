<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$slug = get('slug');
$p = $slug ? q_one(listing_select() . ' WHERE p.slug = ?', [$slug]) : null;
// Hidden, pending or inactive listings: only signed-in admins may preview them
$isPublic = $p && $p['approval'] === 'approved' && (int) $p['is_visible'] === 1 && $p['status'] !== 'inactive';
$adminPreview = $p && !$isPublic && current_user();
if ($p && !$isPublic && !$adminPreview) {
    $p = null;
}
if (!$p) {
    http_response_code(404);
    $pageTitle = 'Property not found';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section text-center"><div class="container"><h1 class="h3">Property not found</h1><p class="text-muted">This listing may have been removed.</p><a class="btn btn-gold" href="' . url('properties.php') . '">Browse properties</a></div></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// Count one view per session per property (not for admin previews)
if (!$adminPreview && empty($_SESSION['viewed'][$p['id']])) {
    $_SESSION['viewed'][$p['id']] = 1;
    q('UPDATE properties SET views = views + 1 WHERE id = ?', [$p['id']]);
}

$photos = q_all('SELECT * FROM property_photos WHERE property_id = ? ORDER BY is_cover DESC, sort_order, id', [$p['id']]);
$amenities = lines($p['amenities']);
$similar = q_all(listing_select() . " WHERE p.listing_type = ? AND p.id <> ? AND p.status IN ('available','reserved') AND " . public_sql() . "
                 ORDER BY (p.location_id <=> ?) DESC, ABS(p.price - ?) LIMIT 3", [$p['listing_type'], $p['id'], $p['location_id'], $p['price']]);

$activeNav = $p['listing_type'];
$pageTitle = $p['title'];
$metaDescription = mb_substr(preg_replace('/\s+/', ' ', strip_tags($p['description'] ?: $p['title'])), 0, 160);
$ogImage = photo_url($photos ? $photos[0]['file'] : null);
$listLabel = $p['listing_type'] === 'rent' ? 'Condos for Rent' : 'Condos for Sale';
$hasMap = $p['latitude'] !== null && $p['longitude'] !== null && (int) $p['show_map'] === 1;
if ($hasMap) {
    list($extraHead, $extraScripts) = map_assets();
}

require __DIR__ . '/includes/header.php';
?>
<?php if ($adminPreview): ?>
<div class="preview-bar"><div class="container-xl d-flex flex-wrap align-items-center gap-2">
  <i class="bi bi-eye-slash"></i>
  <span><b>Admin preview</b> — visitors cannot see this listing (<?= e($p['approval'] !== 'approved' ? ($p['approval'] === 'pending' ? 'waiting for approval' : 'rejected') : (!(int) $p['is_visible'] ? 'hidden from the website' : 'status: inactive')) ?>).</span>
  <a class="ms-auto btn btn-sm btn-dark" href="<?= url('admin/property-view.php?id=' . $p['id']) ?>">Open in admin</a>
</div></div>
<?php endif; ?>
<section class="page-banner page-banner-sm">
  <div class="container-xl">
    <nav aria-label="breadcrumb"><ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Home</a></li>
      <li class="breadcrumb-item"><a href="<?= url('properties.php?type=' . $p['listing_type']) ?>"><?= $listLabel ?></a></li>
      <?php if ($p['location_name']): ?><li class="breadcrumb-item"><a href="<?= url('properties.php?type=' . $p['listing_type'] . '&location=' . urlencode($p['location_slug'])) ?>"><?= e($p['location_name']) ?></a></li><?php endif; ?>
      <li class="breadcrumb-item active"><?= e($p['title']) ?></li>
    </ol></nav>
  </div>
</section>

<section class="section pt-4">
  <div class="container-xl">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
      <div>
        <span class="prop-badge static prop-badge-<?= e($p['listing_type']) ?>"><?= $p['listing_type'] === 'rent' ? 'For Rent' : 'For Sale' ?></span>
        <?php if ($p['status'] !== 'available'): ?><span class="badge bg-secondary ms-1"><?= e(ucfirst($p['status'])) ?></span><?php endif; ?>
        <h1 class="detail-title mt-2"><?= e($p['title']) ?></h1>
        <div class="text-muted"><i class="bi bi-geo-alt text-gold"></i> <?= e(implode(', ', array_filter([$p['building'], $p['address'] ?: $p['location_name']]))) ?></div>
      </div>
      <div class="detail-price"><?= property_price_label($p) ?></div>
    </div>

    <!-- Gallery -->
    <div class="gallery mb-4">
      <?php if ($photos): ?>
        <div class="gallery-main" data-bs-toggle="modal" data-bs-target="#galleryModal" data-index="0">
          <img src="<?= e(photo_url($photos[0]['file'])) ?>" alt="<?= e($photos[0]['caption'] ?: $p['title']) ?>" id="galleryMainImg">
          <span class="gallery-count"><i class="bi bi-images me-1"></i><?= count($photos) ?> photo<?= count($photos) > 1 ? 's' : '' ?></span>
        </div>
        <?php if (count($photos) > 1): ?>
          <div class="gallery-thumbs">
            <?php foreach ($photos as $i => $ph): ?>
              <button type="button" class="gallery-thumb<?= $i === 0 ? ' active' : '' ?>" data-src="<?= e(photo_url($ph['file'])) ?>" data-index="<?= $i ?>"><img src="<?= e(photo_url($ph['file'])) ?>" alt="<?= e($ph['caption']) ?>" loading="lazy"></button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="gallery-main"><img src="<?= e(photo_url(null)) ?>" alt="<?= e($p['title']) ?>"></div>
      <?php endif; ?>
    </div>

    <div class="row g-4">
      <div class="col-lg-8">
        <div class="detail-specs">
          <div><i class="bi bi-door-closed"></i><strong><?= $p['bedrooms'] ? (int) $p['bedrooms'] : 'Studio' ?></strong><small>Bedroom<?= $p['bedrooms'] == 1 ? '' : 's' ?></small></div>
          <div><i class="bi bi-droplet"></i><strong><?= (int) $p['bathrooms'] ?></strong><small>Bathroom<?= $p['bathrooms'] == 1 ? '' : 's' ?></small></div>
          <div><i class="bi bi-arrows-fullscreen"></i><strong><?= $p['floor_area'] ? e(rtrim(rtrim($p['floor_area'], '0'), '.')) : '—' ?></strong><small>sqm</small></div>
          <div><i class="bi bi-car-front"></i><strong><?= (int) $p['parking'] ?></strong><small>Parking</small></div>
          <div><i class="bi bi-lamp"></i><strong><?= e(str_replace('furnished', '', $p['furnishing']) ?: '—') ?></strong><small>Furnished</small></div>
        </div>

        <div class="detail-block">
          <h2>Description</h2>
          <?= nl2p($p['description']) ?: '<p class="text-muted">Contact us for more details about this property.</p>' ?>
        </div>

        <div class="detail-block">
          <h2>Property Details</h2>
          <div class="row">
            <?php
            $facts = [
                'Listing ID' => $p['code'], 'Property type' => $p['property_type'], 'Unit type' => $p['unit_type'],
                'Building' => $p['building'], 'Floor' => $p['floor_level'], 'Location' => $p['location_name'],
                'Furnishing' => $p['furnishing'], 'Floor area' => $p['floor_area'] ? rtrim(rtrim($p['floor_area'], '0'), '.') . ' sqm' : '',
            ];
            foreach ($facts as $k => $v): if ($v): ?>
              <div class="col-sm-6"><div class="fact"><span><?= e($k) ?></span><strong><?= e($v) ?></strong></div></div>
            <?php endif; endforeach; ?>
          </div>
        </div>

        <?php if ($amenities): ?>
          <div class="detail-block">
            <h2>Amenities &amp; Features</h2>
            <ul class="amenities">
              <?php foreach ($amenities as $a): ?><li><i class="bi bi-check2-circle"></i><?= e($a) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <?php if ($hasMap): ?>
          <div class="detail-block">
            <h2>Location</h2>
            <div class="prop-map" data-map-view data-lat="<?= e($p['latitude']) ?>" data-lng="<?= e($p['longitude']) ?>"></div>
            <a class="small d-inline-block mt-2" target="_blank" rel="noopener" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e($p['latitude'] . ',' . $p['longitude']) ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Open in Google Maps</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="col-lg-4">
        <div class="form-card sticky-lg-top" style="top:110px">
          <h3 class="h5 mb-1">Interested in this unit?</h3>
          <p class="small text-muted mb-3">Send us a message to ask questions or book a viewing.</p>
          <?= inquiry_form('property', $p['id'], true) ?>
          <?php if (setting('phone')): ?>
            <hr><a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>" class="btn btn-outline-navy w-100"><i class="bi bi-telephone me-2"></i><?= e(setting('phone')) ?></a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($similar): ?>
      <div class="mt-5 pt-3">
        <h2 class="h4 mb-4">Similar Properties</h2>
        <div class="row g-4">
          <?php foreach ($similar as $sp): ?><div class="col-sm-6 col-lg-4"><?= property_card($sp) ?></div><?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($photos): ?>
<div class="modal fade" id="galleryModal" tabindex="-1" aria-label="Photo gallery" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content bg-dark border-0">
      <div class="modal-header border-0"><button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body p-0">
        <div id="galleryCarousel" class="carousel slide">
          <div class="carousel-inner">
            <?php foreach ($photos as $i => $ph): ?>
              <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
                <img src="<?= e(photo_url($ph['file'])) ?>" class="d-block w-100" alt="<?= e($ph['caption']) ?>">
                <?php if ($ph['caption']): ?><div class="carousel-caption"><p><?= e($ph['caption']) ?></p></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php if (count($photos) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#galleryCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span><span class="visually-hidden">Previous</span></button>
            <button class="carousel-control-next" type="button" data-bs-target="#galleryCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span><span class="visually-hidden">Next</span></button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
