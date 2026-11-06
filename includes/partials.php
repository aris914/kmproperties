<?php
/** Reusable public-site blocks. */

function property_card(array $p)
{
    $cover = isset($p['cover']) ? $p['cover'] : property_cover($p['id']);
    $href = url('property.php?slug=' . urlencode($p['slug']));
    $badge = $p['listing_type'] === 'rent' ? 'For Rent' : 'For Sale';
    ob_start(); ?>
    <article class="prop-card h-100">
      <a href="<?= $href ?>" class="prop-media">
        <img src="<?= e(photo_url($cover)) ?>" alt="<?= e($p['title']) ?>" loading="lazy">
        <span class="prop-badge prop-badge-<?= e($p['listing_type']) ?>"><?= $badge ?></span>
        <?php if ($p['status'] !== 'available'): ?><span class="prop-status"><?= e(ucfirst($p['status'])) ?></span><?php endif; ?>
      </a>
      <div class="prop-body">
        <div class="prop-price"><?= property_price_label($p) ?></div>
        <h3 class="prop-title"><a href="<?= $href ?>"><?= e($p['title']) ?></a></h3>
        <div class="prop-loc"><i class="bi bi-geo-alt"></i> <?= e(trim(($p['building'] ? $p['building'] . ', ' : '') . ($p['location_name'] ?? ''), ', ')) ?></div>
        <ul class="prop-specs">
          <li><i class="bi bi-door-closed"></i> <?= $p['bedrooms'] ? (int) $p['bedrooms'] . ' BR' : 'Studio' ?></li>
          <li><i class="bi bi-droplet"></i> <?= (int) $p['bathrooms'] ?> Bath</li>
          <?php if ($p['floor_area']): ?><li><i class="bi bi-arrows-fullscreen"></i> <?= e(rtrim(rtrim($p['floor_area'], '0'), '.')) ?> sqm</li><?php endif; ?>
          <?php if ($p['parking']): ?><li><i class="bi bi-car-front"></i> <?= (int) $p['parking'] ?></li><?php endif; ?>
        </ul>
      </div>
    </article>
    <?php return ob_get_clean();
}

/** Base SELECT for public listings, with location name and cover photo. */
function listing_select()
{
    return "SELECT p.*, l.name AS location_name, l.slug AS location_slug,
              (SELECT file FROM property_photos ph WHERE ph.property_id = p.id ORDER BY ph.is_cover DESC, ph.sort_order, ph.id LIMIT 1) AS cover
            FROM properties p LEFT JOIN locations l ON l.id = p.location_id";
}

function section_items($sectionId)
{
    return q_all('SELECT * FROM section_items WHERE section_id = ? ORDER BY sort_order, id', [$sectionId]);
}

function section_heading(array $s, $light = false)
{
    if (!$s['title'] && !$s['subtitle']) {
        return '';
    }
    $html = '<div class="section-head text-center' . ($light ? ' light' : '') . '">';
    if ($s['subtitle']) {
        $html .= '<span class="eyebrow">' . e($s['subtitle']) . '</span>';
    }
    if ($s['title']) {
        $html .= '<h2>' . e($s['title']) . ($s['highlight'] ? ' <span class="script">' . e($s['highlight']) . '</span>' : '') . '</h2>';
    }
    return $html . '<span class="title-swoosh"></span></div>';
}

function inquiry_form($type = 'contact', $propertyId = null, $compact = false)
{
    $return = $_SERVER['REQUEST_URI'] ?? url('index.php');
    ob_start(); ?>
    <form method="post" action="<?= url('inquire.php') ?>" class="inquiry-form row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="inquiry_type" value="<?= e($type) ?>">
      <input type="hidden" name="return" value="<?= e($return) ?>">
      <?php if ($propertyId): ?><input type="hidden" name="property_id" value="<?= (int) $propertyId ?>"><?php endif; ?>
      <div class="hp-field" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div class="<?= $compact ? 'col-12' : 'col-md-6' ?>"><input name="name" class="form-control" placeholder="Full name *" required maxlength="150"></div>
      <div class="<?= $compact ? 'col-12' : 'col-md-6' ?>"><input name="phone" class="form-control" placeholder="Mobile number *" required maxlength="60"></div>
      <div class="col-12"><input type="email" name="email" class="form-control" placeholder="Email address" maxlength="190"></div>
      <div class="col-12"><textarea name="message" class="form-control" rows="<?= $compact ? 3 : 4 ?>" placeholder="<?= $propertyId ? 'I would like to schedule a viewing…' : 'How can we help you?' ?>" maxlength="3000"></textarea></div>
      <div class="col-12"><button class="btn btn-gold w-100 py-2"><i class="bi bi-send me-2"></i><?= $propertyId ? 'Send Inquiry' : 'Send Message' ?></button></div>
    </form>
    <?php return ob_get_clean();
}
