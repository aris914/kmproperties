<?php
/** Public site header. Set $pageTitle, $metaDescription, $activeNav before including. */
$siteName = setting('site_name', 'K&M Urban Properties');
$pageTitle = isset($pageTitle) && $pageTitle ? $pageTitle . ' | ' . $siteName : $siteName . ' — ' . setting('tagline', 'Your City. Your Space. Your Home.');
$metaDescription = isset($metaDescription) ? $metaDescription : setting('meta_description');
$activeNav = isset($activeNav) ? $activeNav : '';
$navLocations = q_all('SELECT name, slug FROM locations WHERE is_active = 1 ORDER BY sort_order, name');
$logoDark = setting('logo_dark') ? site_image_url(setting('logo_dark')) : url('assets/img/logo-dark.png');
$phone = setting('phone');
$ogImage = isset($ogImage) ? $ogImage : url('assets/img/hero.jpg');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<link rel="icon" href="<?= url('assets/img/favicon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Yellowtail&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/style.css?v=' . APP_VERSION) ?>" rel="stylesheet">
<?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>
<header class="site-header sticky-top">
  <nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-xl">
      <a class="navbar-brand" href="<?= url('index.php') ?>"><img src="<?= e($logoDark) ?>" alt="<?= e($siteName) ?>" class="brand-logo"></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menu">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNav">
        <ul class="navbar-nav mx-auto align-items-lg-center">
          <li class="nav-item"><a class="nav-link<?= $activeNav === 'home' ? ' active' : '' ?>" href="<?= url('index.php') ?>">Home</a></li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle<?= $activeNav === 'rent' ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Condos for Rent</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('properties.php?type=rent') ?>">All rentals</a></li>
              <li><hr class="dropdown-divider"></li>
              <?php foreach (unit_types() as $ut): ?>
                <li><a class="dropdown-item" href="<?= url('properties.php?type=rent&unit=' . urlencode($ut)) ?>"><?= e($ut) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle<?= $activeNav === 'sale' ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Condos for Sale</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="<?= url('properties.php?type=sale') ?>">All for sale</a></li>
              <li><hr class="dropdown-divider"></li>
              <?php foreach (unit_types() as $ut): ?>
                <li><a class="dropdown-item" href="<?= url('properties.php?type=sale&unit=' . urlencode($ut)) ?>"><?= e($ut) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle<?= $activeNav === 'locations' ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Locations</a>
            <ul class="dropdown-menu">
              <?php foreach ($navLocations as $loc): ?>
                <li><a class="dropdown-item" href="<?= url('properties.php?location=' . urlencode($loc['slug'])) ?>"><?= e($loc['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </li>
          <li class="nav-item"><a class="nav-link<?= $activeNav === 'list' ? ' active' : '' ?>" href="<?= url('list-property.php') ?>">List Your Property</a></li>
          <li class="nav-item"><a class="nav-link<?= $activeNav === 'about' ? ' active' : '' ?>" href="<?= url('about.php') ?>">About Us</a></li>
          <li class="nav-item"><a class="nav-link<?= $activeNav === 'contact' ? ' active' : '' ?>" href="<?= url('contact.php') ?>">Contact Us</a></li>
        </ul>
        <?php if ($phone): ?>
          <a class="btn btn-gold btn-call" href="tel:<?= e(preg_replace('~[^0-9+]~', '', $phone)) ?>"><i class="bi bi-telephone-fill me-2"></i>Call Us</a>
        <?php endif; ?>
      </div>
    </div>
  </nav>
</header>
<main>
<?php $fl = flashes_html(); if ($fl): ?><div class="container-xl pt-3"><?= $fl ?></div><?php endif; ?>
