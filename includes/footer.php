<?php
$siteName = setting('site_name', 'K&M Urban Properties');
$logoDark = setting('logo_dark') ? site_image_url(setting('logo_dark')) : url('assets/img/logo-dark.png');
$socials = [
    'facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'tiktok' => 'bi-tiktok',
];
$footLocations = q_all('SELECT name, slug FROM locations WHERE is_active = 1 ORDER BY sort_order, name LIMIT 6');
?>
</main>
<footer class="site-footer">
  <div class="footer-wave" aria-hidden="true"></div>
  <div class="container-xl">
    <div class="row g-4 py-5">
      <div class="col-lg-4">
        <img src="<?= e($logoDark) ?>" alt="<?= e($siteName) ?>" class="footer-logo mb-3">
        <p class="text-white-50"><?= e(setting('footer_about')) ?></p>
        <div class="d-flex gap-2">
          <?php foreach ($socials as $key => $icon): if (setting($key)): ?>
            <a class="social-btn" href="<?= e(safe_link(setting($key))) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($key)) ?>"><i class="bi <?= $icon ?>"></i></a>
          <?php endif; endforeach; ?>
          <?php if (setting('viber')): ?><a class="social-btn" href="viber://chat?number=<?= e(rawurlencode(setting('viber'))) ?>" aria-label="Viber"><i class="bi bi-telephone-inbound"></i></a><?php endif; ?>
          <?php if (setting('whatsapp')): ?><a class="social-btn" href="https://wa.me/<?= e(preg_replace('~\D~', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="footer-title">Explore</h6>
        <ul class="list-unstyled footer-links">
          <li><a href="<?= url('properties.php?type=rent') ?>">Condos for Rent</a></li>
          <li><a href="<?= url('properties.php?type=sale') ?>">Condos for Sale</a></li>
          <li><a href="<?= url('list-property.php') ?>">List Your Property</a></li>
          <li><a href="<?= url('about.php') ?>">About Us</a></li>
          <li><a href="<?= url('contact.php') ?>">Contact Us</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="footer-title">Locations</h6>
        <ul class="list-unstyled footer-links">
          <?php foreach ($footLocations as $loc): ?>
            <li><a href="<?= url('properties.php?location=' . urlencode($loc['slug'])) ?>"><?= e($loc['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="col-lg-4">
        <h6 class="footer-title">Contact</h6>
        <ul class="list-unstyled footer-contact">
          <?php if (setting('address')): ?><li><i class="bi bi-geo-alt"></i><?= e(setting('address')) ?></li><?php endif; ?>
          <?php if (setting('phone')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
          <?php if (setting('phone_alt')): ?><li><i class="bi bi-phone"></i><?= e(setting('phone_alt')) ?></li><?php endif; ?>
          <?php if (setting('email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
          <?php if (setting('office_hours')): ?><li><i class="bi bi-clock"></i><?= e(setting('office_hours')) ?></li><?php endif; ?>
        </ul>
      </div>
    </div>
    <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between gap-2 py-3">
      <span>&copy; <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
      <span>Condominium Rentals &bull; Property Sales &bull; Metro Manila, Philippines</span>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/main.js?v=' . APP_VERSION) ?>"></script>
</body>
</html>
