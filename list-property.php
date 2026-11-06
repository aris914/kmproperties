<?php
/* List Your Property (1.2.0): owners submit their unit with photos and a map pin.
   The submission becomes a property waiting for approval (hidden from the website) and an inquiry.
   An admin reviews it under Admin › Owner Submissions; once approved it appears in the listings. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$activeNav = 'list';
$pageTitle = 'List Your Property';
$metaDescription = 'Own a condo in Metro Manila? List it with ' . setting('site_name') . ' — add photos and pin the location, and we will find qualified tenants or buyers.';
$cta = q_one("SELECT * FROM landing_sections WHERE section_key = 'cta'");
$locations = q_all('SELECT id, name FROM locations WHERE is_active = 1 ORDER BY sort_order, name');
$sent = !empty($_SESSION['listing_sent']);
$sentName = $sent ? $_SESSION['listing_sent'] : '';
unset($_SESSION['listing_sent']);
$old = isset($_SESSION['listing_old']) && is_array($_SESSION['listing_old']) ? $_SESSION['listing_old'] : [];
unset($_SESSION['listing_old']);
$o = function ($k, $d = '') use ($old) { return isset($old[$k]) && is_string($old[$k]) ? $old[$k] : $d; };
$oldAmen = isset($old['amen']) && is_array($old['amen']) ? $old['amen'] : [];
$maxPhotos = 12;
list($mapCss, $mapJs) = map_assets();
$extraHead = $mapCss;
$extraScripts = $mapJs;

require __DIR__ . '/includes/header.php';
?>
<section class="page-banner">
  <div class="container-xl">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Home</a></li><li class="breadcrumb-item active">List Your Property</li></ol></nav>
    <h1>List Your Property <span class="script">with us</span></h1>
    <p><?= e($cta['body'] ?? 'Let us find qualified tenants or buyers for your unit.') ?></p>
  </div>
</section>

<section class="section pt-5">
  <div class="container-xl">
    <?php if ($sent): ?>
      <div class="form-card text-center mx-auto" style="max-width:640px">
        <i class="bi bi-check-circle-fill display-4 text-success"></i>
        <h2 class="h4 mt-3">Thank you<?= $sentName ? ', ' . e($sentName) : '' ?>!</h2>
        <p class="text-muted">We received your property with its photos and location. Our team will review it and contact you, usually within one business day. Once approved, it will appear in our listings.</p>
        <a href="<?= url('index.php') ?>" class="btn btn-gold">Back to home</a>
        <a href="<?= url('list-property.php') ?>" class="btn btn-outline-navy ms-2">List another unit</a>
      </div>
    <?php else: ?>
    <div class="row g-5">
      <div class="col-lg-4">
        <h2 class="h4">How it works</h2>
        <ol class="steps-list">
          <li><b>Tell us about your unit</b><span>Details, asking price, photos and a pin on the map.</span></li>
          <li><b>We review it</b><span>Our team checks the details and contacts you to confirm.</span></li>
          <li><b>It goes live</b><span>Once approved, your unit appears in our listings and we start finding tenants or buyers.</span></li>
        </ol>
        <h2 class="h5 mt-4">Why list with K&amp;M?</h2>
        <ul class="why-list">
          <li><i class="bi bi-megaphone"></i><div><strong>Wide marketing</strong><span>Your listing on our website and social channels.</span></div></li>
          <li><i class="bi bi-person-check"></i><div><strong>Screened tenants &amp; buyers</strong><span>We verify IDs and capacity to pay before you meet them.</span></div></li>
          <li><i class="bi bi-file-earmark-check"></i><div><strong>Paperwork handled</strong><span>Contracts, deeds and turnover documents prepared for you.</span></div></li>
          <li><i class="bi bi-tools"></i><div><strong>Property management</strong><span>Rent collection, repairs and maintenance — ideal for owners abroad.</span></div></li>
        </ul>
      </div>
      <div class="col-lg-8">
        <form method="post" action="<?= url('submit-listing.php') ?>" enctype="multipart/form-data" class="form-card listing-form" id="listingForm">
          <?= csrf_field() ?>
          <div class="hp-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

          <h2 class="lf-step"><span>1</span> Your details</h2>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Your name *</label><input name="name" class="form-control" required maxlength="150" value="<?= e($o('name')) ?>" autocomplete="name"></div>
            <div class="col-md-6"><label class="form-label">Mobile number *</label><input name="phone" class="form-control" required maxlength="60" value="<?= e($o('phone')) ?>" autocomplete="tel"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="190" value="<?= e($o('email')) ?>" autocomplete="email"></div>
            <div class="col-md-6"><label class="form-label">I want to *</label><select name="offer" class="form-select"><?= select_options(['rent' => 'Rent out my unit', 'sale' => 'Sell my unit', 'both' => 'Rent out or sell', 'manage' => 'Have my unit managed (rent)'], $o('offer', 'rent'), true) ?></select></div>
          </div>

          <h2 class="lf-step"><span>2</span> The property</h2>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Building / project *</label><input name="building" class="form-control" required maxlength="150" value="<?= e($o('building')) ?>" placeholder="e.g. One Serendra"></div>
            <div class="col-md-3"><label class="form-label">Unit no. <small class="text-muted">(private)</small></label><input name="unit_no" class="form-control" maxlength="40" value="<?= e($o('unit_no')) ?>"></div>
            <div class="col-md-3"><label class="form-label">City / area *</label><select name="location_id" class="form-select" required><option value="">Select…</option><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>"<?= $o('location_id') === (string) $l['id'] ? ' selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?><option value="0"<?= $o('location_id') === '0' ? ' selected' : '' ?>>Other</option></select></div>
            <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" maxlength="255" value="<?= e($o('address')) ?>" placeholder="Street, barangay, city"></div>
            <div class="col-md-4"><label class="form-label">Property type</label><select name="property_type" class="form-select"><?= select_options(property_types(), $o('property_type', 'Condominium')) ?></select></div>
            <div class="col-md-4"><label class="form-label">Unit type</label><select name="unit_type" class="form-select"><?= select_options(unit_types(), $o('unit_type', '1 Bedroom')) ?></select></div>
            <div class="col-md-4"><label class="form-label">Asking price (₱) *</label><input name="price" class="form-control" required inputmode="decimal" maxlength="20" value="<?= e($o('price')) ?>" placeholder="Monthly rent or selling price"></div>
            <div class="col-6 col-md-2"><label class="form-label">Bedrooms</label><input type="number" min="0" max="20" name="bedrooms" class="form-control" value="<?= e($o('bedrooms', '1')) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Bathrooms</label><input type="number" min="0" max="20" name="bathrooms" class="form-control" value="<?= e($o('bathrooms', '1')) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Area (sqm)</label><input name="floor_area" class="form-control" inputmode="decimal" maxlength="10" value="<?= e($o('floor_area')) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Floor</label><input name="floor_level" class="form-control" maxlength="20" value="<?= e($o('floor_level')) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Parking</label><input type="number" min="0" max="20" name="parking" class="form-control" value="<?= e($o('parking', '0')) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label">Furnishing</label><select name="furnishing" class="form-select"><?= select_options(['Unfurnished', 'Semi-furnished', 'Fully furnished'], $o('furnishing', 'Unfurnished')) ?></select></div>
            <div class="col-12"><label class="form-label">Amenities &amp; features</label>
              <div class="amen-grid">
                <?php foreach (amenity_choices() as $a): ?>
                  <label class="form-check"><input type="checkbox" class="form-check-input" name="amen[]" value="<?= e($a) ?>"<?= in_array($a, $oldAmen, true) ? ' checked' : '' ?>> <?= e($a) ?></label>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="4" maxlength="3000" placeholder="View, condition, availability date, inclusions, terms…"><?= e($o('description')) ?></textarea></div>
          </div>

          <h2 class="lf-step"><span>3</span> Pin the location</h2>
          <p class="small text-muted">Search for the building, use your current location, or click the map. Drag the pin to adjust.</p>
          <div class="map-tools">
            <div class="input-group"><input class="form-control" data-map-search placeholder="Search building or address" maxlength="150"><button type="button" class="btn btn-navy" data-map-go><i class="bi bi-search"></i></button></div>
            <button type="button" class="btn btn-outline-navy" data-map-me><i class="bi bi-crosshair me-1"></i>My location</button>
            <button type="button" class="btn btn-light" data-map-clear>Clear pin</button>
          </div>
          <div class="map-box" data-map-picker data-lat="#lat" data-lng="#lng"></div>
          <div class="small text-muted mt-1" data-map-status></div>
          <input type="hidden" name="latitude" id="lat" value="<?= e($o('latitude')) ?>">
          <input type="hidden" name="longitude" id="lng" value="<?= e($o('longitude')) ?>">

          <h2 class="lf-step"><span>4</span> Photos</h2>
          <p class="small text-muted">Up to <?= $maxPhotos ?> photos (JPG, PNG or WebP). The first photo is the cover. Large photos are made smaller automatically before sending.</p>
          <label class="photo-drop">
            <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple data-photo-picker data-max="<?= $maxPhotos ?>" data-preview="#ppPreview" data-count="#ppCount">
            <i class="bi bi-images"></i><span>Click to choose photos</span><small id="ppCount"></small>
          </label>
          <div class="pp-grid" id="ppPreview"></div>

          <div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required><label class="form-check-label small" for="consent">I am the owner of this property or authorised by the owner to list it, and I agree that <?= e(setting('site_name')) ?> may contact me about it and publish the details and photos once approved.</label></div>
          <button class="btn btn-gold btn-lg mt-3 px-4" id="lfSubmit"><i class="bi bi-send me-2"></i>Submit Property</button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var f = document.getElementById('listingForm');
  if (f) f.addEventListener('submit', function () { var b = document.getElementById('lfSubmit'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending…'; });
});
</script>
<?php require __DIR__ . '/includes/footer.php';
