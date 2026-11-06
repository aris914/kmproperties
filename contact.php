<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$activeNav = 'contact';
$pageTitle = 'Contact Us';
$map = setting('map_embed');
$mapOk = $map && preg_match('~^https://(www\.)?google\.com/maps/embed\?~', $map);

require __DIR__ . '/includes/header.php';
?>
<section class="page-banner">
  <div class="container-xl">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Home</a></li><li class="breadcrumb-item active">Contact Us</li></ol></nav>
    <h1>Contact <span class="script">Us</span></h1>
    <p>Looking for a unit, or want to list yours? We would love to hear from you.</p>
  </div>
</section>

<section class="section">
  <div class="container-xl">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="contact-card h-100">
          <?php if (setting('phone')): ?><div class="ci"><i class="bi bi-telephone"></i><div><small>Call or text</small><a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a><?php if (setting('phone_alt')): ?><br><?= e(setting('phone_alt')) ?><?php endif; ?></div></div><?php endif; ?>
          <?php if (setting('email')): ?><div class="ci"><i class="bi bi-envelope"></i><div><small>Email</small><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></div></div><?php endif; ?>
          <?php if (setting('address')): ?><div class="ci"><i class="bi bi-geo-alt"></i><div><small>Office</small><?= e(setting('address')) ?></div></div><?php endif; ?>
          <?php if (setting('office_hours')): ?><div class="ci"><i class="bi bi-clock"></i><div><small>Office hours</small><?= e(setting('office_hours')) ?></div></div><?php endif; ?>
        </div>
      </div>
      <div class="col-lg-7">
        <div class="form-card"><h2 class="h5 mb-3">Send us a message</h2><?= inquiry_form('contact') ?></div>
      </div>
    </div>
    <?php if ($mapOk): ?>
      <div class="map-wrap mt-5"><iframe src="<?= e($map) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map"></iframe></div>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
