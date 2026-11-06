<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$activeNav = 'about';
$pageTitle = 'About Us';
$byKey = [];
foreach (q_all("SELECT * FROM landing_sections WHERE section_key IN ('about','services','stats','testimonials')") as $s) {
    $byKey[$s['section_key']] = $s;
}
$about = $byKey['about'] ?? ['title' => 'About Us', 'highlight' => '', 'subtitle' => '', 'body' => '', 'image' => ''];

require __DIR__ . '/includes/header.php';
?>
<section class="page-banner">
  <div class="container-xl">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Home</a></li><li class="breadcrumb-item active">About Us</li></ol></nav>
    <h1>About <span class="script"><?= e(setting('site_name')) ?></span></h1>
    <p><?= e(setting('tagline')) ?></p>
  </div>
</section>

<section class="section">
  <div class="container-xl">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6"><div class="about-img"><img src="<?= e(site_image_url($about['image'])) ?>" alt=""></div></div>
      <div class="col-lg-6">
        <?php if ($about['subtitle']): ?><span class="eyebrow"><?= e($about['subtitle']) ?></span><?php endif; ?>
        <h2 class="display-title"><?= e($about['title']) ?></h2>
        <span class="title-swoosh start"></span>
        <div class="mt-3"><?= nl2p($about['body']) ?></div>
      </div>
    </div>
  </div>
</section>

<?php if (!empty($byKey['stats'])): $items = section_items($byKey['stats']['id']); if ($items): ?>
<section class="stats-band"><div class="container-xl"><div class="row g-4 text-center">
  <?php foreach ($items as $it): ?><div class="col-6 col-lg-3"><div class="stat-num"><?= e($it['title']) ?></div><div class="stat-label"><?= e($it['text']) ?></div></div><?php endforeach; ?>
</div></div></section>
<?php endif; endif; ?>

<?php if (!empty($byKey['services'])): $items = section_items($byKey['services']['id']); if ($items): ?>
<section class="section">
  <div class="container-xl">
    <?= section_heading($byKey['services']) ?>
    <div class="row g-4 justify-content-center">
      <?php foreach ($items as $it): ?>
        <div class="col-md-6 col-lg-4"><div class="service-card h-100">
          <span class="service-icon"><i class="bi <?= e($it['icon'] ?: 'bi-check2-circle') ?>"></i></span>
          <h3><?= e($it['title']) ?></h3><p><?= e($it['text']) ?></p>
        </div></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; endif; ?>

<?php if (!empty($byKey['testimonials'])): $items = section_items($byKey['testimonials']['id']); if ($items): ?>
<section class="section section-alt">
  <div class="container-xl">
    <?= section_heading($byKey['testimonials']) ?>
    <div class="row g-4">
      <?php foreach ($items as $it): ?>
        <div class="col-md-6 col-lg-4"><figure class="testimonial h-100">
          <i class="bi bi-quote quote-mark"></i><blockquote><?= e($it['text']) ?></blockquote>
          <figcaption><span class="avatar"><?= e(mb_strtoupper(mb_substr($it['title'], 0, 1))) ?></span><span><strong><?= e($it['title']) ?></strong><small><?= e($it['subtitle']) ?></small></span></figcaption>
        </figure></div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; endif; ?>
<?php require __DIR__ . '/includes/footer.php';
