<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$activeNav = 'list';
$pageTitle = 'List Your Property';
$metaDescription = 'Own a condo in Metro Manila? List it with ' . setting('site_name') . ' and we will find qualified tenants or buyers.';
$cta = q_one("SELECT * FROM landing_sections WHERE section_key = 'cta'");
$locations = q_all('SELECT name FROM locations WHERE is_active = 1 ORDER BY sort_order, name');

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
    <div class="row g-5">
      <div class="col-lg-5">
        <h2 class="h4">Why list with K&amp;M?</h2>
        <ul class="why-list">
          <li><i class="bi bi-megaphone"></i><div><strong>Wide marketing</strong><span>Professional photos and listing on our website and social channels.</span></div></li>
          <li><i class="bi bi-person-check"></i><div><strong>Screened tenants &amp; buyers</strong><span>We verify IDs, employment and capacity to pay before you meet them.</span></div></li>
          <li><i class="bi bi-file-earmark-check"></i><div><strong>Paperwork handled</strong><span>Lease contracts, deeds of sale and turnover documents prepared for you.</span></div></li>
          <li><i class="bi bi-tools"></i><div><strong>Property management</strong><span>Rent collection, repairs and maintenance — ideal for owners abroad.</span></div></li>
        </ul>
      </div>
      <div class="col-lg-7">
        <div class="form-card">
          <h2 class="h5 mb-3">Tell us about your unit</h2>
          <form method="post" action="<?= url('inquire.php') ?>" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="inquiry_type" value="listing">
            <input type="hidden" name="return" value="<?= e(url('list-property.php')) ?>">
            <div class="hp-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="col-md-6"><label class="form-label">Your name *</label><input name="name" class="form-control" required maxlength="150"></div>
            <div class="col-md-6"><label class="form-label">Mobile number *</label><input name="phone" class="form-control" required maxlength="60"></div>
            <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="190"></div>
            <div class="col-md-6"><label class="form-label">I want to</label><select name="offer" class="form-select"><option>Rent out my unit</option><option>Sell my unit</option><option>Rent out or sell</option><option>Have my unit managed</option></select></div>
            <div class="col-md-6"><label class="form-label">Building / project</label><input name="building" class="form-control" maxlength="200"></div>
            <div class="col-md-6"><label class="form-label">Location</label><select name="location" class="form-select"><option value="">Select…</option><?php foreach ($locations as $l): ?><option><?= e($l['name']) ?></option><?php endforeach; ?><option>Other</option></select></div>
            <div class="col-md-4"><label class="form-label">Unit type</label><select name="unit_type" class="form-select"><?= select_options(unit_types(), '') ?></select></div>
            <div class="col-md-4"><label class="form-label">Floor area (sqm)</label><input name="floor_area" class="form-control" inputmode="decimal" maxlength="20"></div>
            <div class="col-md-4"><label class="form-label">Furnishing</label><select name="furnishing" class="form-select"><option>Unfurnished</option><option>Semi-furnished</option><option>Fully furnished</option></select></div>
            <div class="col-md-6"><label class="form-label">Asking price / monthly rent</label><input name="asking_price" class="form-control" maxlength="60"></div>
            <div class="col-12"><label class="form-label">Other details</label><textarea name="message" class="form-control" rows="4" maxlength="3000" placeholder="Parking, view, availability date, etc."></textarea></div>
            <div class="col-12"><button class="btn btn-gold px-4 py-2"><i class="bi bi-send me-2"></i>Submit Property</button></div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
