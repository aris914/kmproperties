<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$activeNav = 'home';
$sections = q_all('SELECT * FROM landing_sections WHERE is_active = 1 ORDER BY sort_order, id');
$locations = q_all('SELECT * FROM locations WHERE is_active = 1 ORDER BY sort_order, name');

require __DIR__ . '/includes/header.php';

foreach ($sections as $s):
    switch ($s['section_key']):

    case 'hero': ?>
<section class="hero" style="--hero-img:url('<?= e(site_image_url($s['image'])) ?>')">
  <div class="container-xl hero-inner">
    <div class="hero-copy">
      <h1><?= e($s['title']) ?><?php if ($s['highlight']): ?><span class="script d-block"><?= e($s['highlight']) ?></span><?php endif; ?></h1>
      <?php if ($s['body']): ?><p class="lead"><?= e($s['body']) ?></p><?php endif; ?>
      <?php if ($s['button_text']): ?><a href="<?= e(safe_link($s['button_link'])) ?>" class="btn btn-gold btn-lg mt-2"><?= e($s['button_text']) ?></a><?php endif; ?>
    </div>
    <form class="hero-search" action="<?= url('properties.php') ?>" method="get">
      <div class="hs-field">
        <i class="bi bi-geo-alt-fill"></i>
        <label>Location
          <select name="location" class="form-select-plain">
            <option value="">Select a city</option>
            <?php foreach ($locations as $loc): ?><option value="<?= e($loc['slug']) ?>"><?= e($loc['name']) ?></option><?php endforeach; ?>
          </select>
        </label>
      </div>
      <div class="hs-field">
        <i class="bi bi-house-door-fill"></i>
        <label>Property Type
          <select name="unit" class="form-select-plain">
            <option value="">Condominium (any)</option>
            <?= select_options(unit_types(), '') ?>
          </select>
        </label>
      </div>
      <div class="hs-toggle" role="radiogroup" aria-label="Rent or buy">
        <input type="radio" name="type" id="hsRent" value="rent" checked><label for="hsRent">For Rent</label>
        <input type="radio" name="type" id="hsSale" value="sale"><label for="hsSale">For Sale</label>
      </div>
      <button class="btn btn-gold hs-btn"><i class="bi bi-search me-2"></i>Search</button>
    </form>
  </div>
</section>
<?php break;

    case 'features':
        $items = section_items($s['id']);
        if (!$items) break; ?>
<section class="features-bar">
  <div class="container-xl">
    <div class="row g-0">
      <?php foreach ($items as $it): ?>
        <div class="col-6 col-lg-3 feature-item">
          <span class="feature-icon"><i class="bi <?= e($it['icon'] ?: 'bi-star') ?>"></i></span>
          <span class="feature-text"><?= e($it['title']) ?><br><?= e($it['subtitle']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'featured_rent':
    case 'featured_sale':
        $type = $s['section_key'] === 'featured_rent' ? 'rent' : 'sale';
        $limit = max(1, min(24, (int) $s['item_limit']));
        $props = q_all(listing_select() . " WHERE p.listing_type = ? AND p.status IN ('available','reserved') ORDER BY p.is_featured DESC, p.created_at DESC LIMIT $limit", [$type]);
        if (!$props) break; ?>
<section class="section <?= $type === 'sale' ? 'section-alt' : '' ?>">
  <div class="container-xl">
    <?= section_heading($s) ?>
    <div class="row g-4">
      <?php foreach ($props as $p): ?><div class="col-sm-6 col-lg-4"><?= property_card($p) ?></div><?php endforeach; ?>
    </div>
    <?php if ($s['button_text']): ?>
      <div class="text-center mt-5"><a href="<?= e(safe_link($s['button_link'])) ?>" class="btn btn-outline-navy btn-lg"><?= e($s['button_text']) ?> <i class="bi bi-arrow-right ms-1"></i></a></div>
    <?php endif; ?>
  </div>
</section>
<?php break;

    case 'services':
        $items = section_items($s['id']);
        if (!$items) break; ?>
<section class="section">
  <div class="container-xl">
    <?= section_heading($s) ?>
    <div class="row g-4 justify-content-center">
      <?php foreach ($items as $it): ?>
        <div class="col-md-6 col-lg-4">
          <div class="service-card h-100">
            <span class="service-icon"><i class="bi <?= e($it['icon'] ?: 'bi-check2-circle') ?>"></i></span>
            <h3><?= e($it['title']) ?></h3>
            <p><?= e($it['text']) ?></p>
            <?php if ($it['link']): ?><a href="<?= e(safe_link($it['link'])) ?>" class="link-gold">Learn more <i class="bi bi-arrow-right"></i></a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'locations':
        $limit = max(1, (int) $s['item_limit']);
        $locs = q_all("SELECT l.*, (SELECT COUNT(*) FROM properties p WHERE p.location_id = l.id AND p.status IN ('available','reserved')) AS cnt
                       FROM locations l WHERE l.is_active = 1 ORDER BY l.sort_order, l.name LIMIT $limit");
        if (!$locs) break; ?>
<section class="section section-navy">
  <div class="container-xl">
    <?= section_heading($s, true) ?>
    <div class="row g-3">
      <?php foreach ($locs as $i => $loc): ?>
        <div class="col-6 col-md-4 col-lg-3">
          <a class="loc-tile" href="<?= url('properties.php?location=' . urlencode($loc['slug'])) ?>" style="background-image:url('<?= e($loc['image'] ? site_image_url($loc['image']) : url('assets/img/hero.jpg')) ?>')">
            <span class="loc-name"><?= e($loc['name']) ?></span>
            <span class="loc-count"><?= (int) $loc['cnt'] ?> listing<?= $loc['cnt'] == 1 ? '' : 's' ?></span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'about': ?>
<section class="section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6">
        <div class="about-img"><img src="<?= e(site_image_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy"></div>
      </div>
      <div class="col-lg-6">
        <?php if ($s['subtitle']): ?><span class="eyebrow"><?= e($s['subtitle']) ?></span><?php endif; ?>
        <h2 class="display-title"><?= e($s['title']) ?><?php if ($s['highlight']): ?> <span class="script"><?= e($s['highlight']) ?></span><?php endif; ?></h2>
        <span class="title-swoosh start"></span>
        <div class="text-muted-2 mt-3"><?= nl2p($s['body']) ?></div>
        <?php if ($s['button_text']): ?><a href="<?= e(safe_link($s['button_link'])) ?>" class="btn btn-navy mt-2"><?= e($s['button_text']) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php break;

    case 'stats':
        $items = section_items($s['id']);
        if (!$items) break; ?>
<section class="stats-band">
  <div class="container-xl">
    <div class="row g-4 text-center">
      <?php foreach ($items as $it): ?>
        <div class="col-6 col-lg-3"><div class="stat-num"><?= e($it['title']) ?></div><div class="stat-label"><?= e($it['text']) ?></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'testimonials':
        $items = section_items($s['id']);
        if (!$items) break; ?>
<section class="section section-alt">
  <div class="container-xl">
    <?= section_heading($s) ?>
    <div class="row g-4">
      <?php foreach ($items as $it): ?>
        <div class="col-md-6 col-lg-4">
          <figure class="testimonial h-100">
            <i class="bi bi-quote quote-mark"></i>
            <blockquote><?= e($it['text']) ?></blockquote>
            <figcaption>
              <?php if ($it['image']): ?><img src="<?= e(site_image_url($it['image'])) ?>" alt=""><?php else: ?><span class="avatar"><?= e(mb_strtoupper(mb_substr($it['title'], 0, 1))) ?></span><?php endif; ?>
              <span><strong><?= e($it['title']) ?></strong><small><?= e($it['subtitle']) ?></small></span>
            </figcaption>
          </figure>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php break;

    case 'cta': ?>
<section class="cta-band" style="--cta-img:url('<?= e(site_image_url($s['image'])) ?>')">
  <div class="container-xl">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <h2><?= e($s['title']) ?><?php if ($s['highlight']): ?> <span class="script"><?= e($s['highlight']) ?></span><?php endif; ?></h2>
        <p class="mb-0"><?= e($s['body']) ?></p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <?php if ($s['button_text']): ?><a href="<?= e(safe_link($s['button_link'])) ?>" class="btn btn-gold btn-lg"><?= e($s['button_text']) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php break;

    case 'contact': ?>
<section class="section" id="contact">
  <div class="container-xl">
    <?= section_heading($s) ?>
    <div class="row g-4 justify-content-center">
      <div class="col-lg-4">
        <div class="contact-card h-100">
          <?php if (setting('phone')): ?><div class="ci"><i class="bi bi-telephone"></i><div><small>Call us</small><a href="tel:<?= e(preg_replace('~[^0-9+]~', '', setting('phone'))) ?>"><?= e(setting('phone')) ?></a></div></div><?php endif; ?>
          <?php if (setting('email')): ?><div class="ci"><i class="bi bi-envelope"></i><div><small>Email</small><a href="mailto:<?= e(setting('email')) ?>"><?= e(setting('email')) ?></a></div></div><?php endif; ?>
          <?php if (setting('address')): ?><div class="ci"><i class="bi bi-geo-alt"></i><div><small>Office</small><?= e(setting('address')) ?></div></div><?php endif; ?>
          <?php if (setting('office_hours')): ?><div class="ci"><i class="bi bi-clock"></i><div><small>Hours</small><?= e(setting('office_hours')) ?></div></div><?php endif; ?>
        </div>
      </div>
      <div class="col-lg-6"><div class="form-card"><?= inquiry_form('contact') ?></div></div>
    </div>
  </div>
</section>
<?php break;

    endswitch;
endforeach;

require __DIR__ . '/includes/footer.php';
