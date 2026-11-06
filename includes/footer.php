<?php
$siteName = setting('site_name', 'K&M Urban Properties');
$logoDark = setting('logo_dark') ? site_image_url(setting('logo_dark')) : url('assets/img/logo-dark.png?v=' . APP_VERSION);
$socials = [
    'facebook' => 'bi-facebook', 'instagram' => 'bi-instagram', 'tiktok' => 'bi-tiktok',
];
$fc = site_footer_cfg();
$footLocations = $fc['show_locations'] ? q_all('SELECT name, slug FROM locations WHERE is_active = 1 ORDER BY sort_order, name LIMIT ' . (int) $fc['loc_count']) : [];
?>
</main>
<footer class="site-footer">
  <div class="footer-wave" aria-hidden="true"></div>
  <div class="container-xl">
    <div class="row g-4 py-5">
      <div class="col-lg-4">
        <?php if ($fc['show_logo']): ?><img src="<?= e($logoDark) ?>" alt="<?= e($siteName) ?>" class="footer-logo mb-3"><?php endif; ?>
        <?php if (setting('footer_about')): ?><p class="text-white-50"><?= nl2br(e(setting('footer_about'))) ?></p><?php endif; ?>
        <?php if ($fc['show_social']): ?>
        <div class="d-flex gap-2">
          <?php foreach ($socials as $key => $icon): if (setting($key)): ?>
            <a class="social-btn" href="<?= e(safe_link(setting($key))) ?>" target="_blank" rel="noopener" aria-label="<?= e(ucfirst($key)) ?>"><i class="bi <?= $icon ?>"></i></a>
          <?php endif; endforeach; ?>
          <?php if (setting('viber')): ?><a class="social-btn" href="viber://chat?number=<?= e(rawurlencode(setting('viber'))) ?>" aria-label="Viber"><i class="bi bi-telephone-inbound"></i></a><?php endif; ?>
          <?php if (setting('whatsapp')): ?><a class="social-btn" href="https://wa.me/<?= e(preg_replace('~\D~', '', setting('whatsapp'))) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="bi bi-whatsapp"></i></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($fc['links']): ?>
      <div class="col-6 col-lg-2">
        <h6 class="footer-title"><?= e($fc['col1']) ?></h6>
        <ul class="list-unstyled footer-links">
          <?php foreach ($fc['links'] as $lk): ?><li><a href="<?= e(safe_link($lk['url'])) ?>"><?= e($lk['label']) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($footLocations): ?>
      <div class="col-6 col-lg-2">
        <h6 class="footer-title"><?= e($fc['col2']) ?></h6>
        <ul class="list-unstyled footer-links">
          <?php foreach ($footLocations as $loc): ?>
            <li><a href="<?= url('properties.php?location=' . urlencode($loc['slug'])) ?>"><?= e($loc['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($fc['show_contact']): ?>
      <div class="col-lg-4">
        <h6 class="footer-title"><?= e($fc['col3']) ?></h6>
        <ul class="list-unstyled footer-contact">
          <?php if (setting('address')): ?><li><i class="bi bi-geo-alt"></i><?= e(setting('address')) ?></li><?php endif; ?>
          <?php if (setting('phone')): ?><li><i class="bi bi-telephone"></i><a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></li><?php endif; ?>
          <?php if (setting('phone_alt')): ?><li><i class="bi bi-phone"></i><?= e(setting('phone_alt')) ?></li><?php endif; ?>
          <?php if (setting('email')): ?><li><i class="bi bi-envelope"></i><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></li><?php endif; ?>
          <?php if (setting('office_hours')): ?><li><i class="bi bi-clock"></i><?= e(setting('office_hours')) ?></li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between gap-2 py-3">
      <span><?= e(footer_text($fc['copyright'])) ?><?php if ($fc['admin_link']): ?> · <a href="<?= url('admin/index.php') ?>" class="text-reset">Admin</a><?php endif; ?></span>
      <?php if ($fc['tagline'] !== ''): ?><span><?= e(footer_text($fc['tagline'])) ?></span><?php endif; ?>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/main.js?v=' . APP_VERSION) ?>"></script>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
