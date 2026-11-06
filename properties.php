<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/partials.php';

$type = in_array(get('type'), ['rent', 'sale'], true) ? get('type') : '';
$unit = in_array(get('unit'), unit_types(), true) ? get('unit') : '';
$locSlug = get('location');
$beds = get('beds');
$minPrice = num(get('min'));
$maxPrice = num(get('max'));
$keyword = mb_substr(get('q'), 0, 80);
$sort = get('sort', 'newest');
$page = max(1, (int) get('page', 1));
$perPage = 12;

$location = $locSlug ? q_one('SELECT * FROM locations WHERE slug = ? AND is_active = 1', [$locSlug]) : null;
$locations = q_all('SELECT name, slug FROM locations WHERE is_active = 1 ORDER BY sort_order, name');

$where = ["p.status IN ('available','reserved')"];
$params = [];
if ($type) { $where[] = 'p.listing_type = ?'; $params[] = $type; }
if ($unit) { $where[] = 'p.unit_type = ?'; $params[] = $unit; }
if ($location) { $where[] = 'p.location_id = ?'; $params[] = $location['id']; }
if ($beds !== '' && ctype_digit($beds)) { $where[] = 'p.bedrooms >= ?'; $params[] = (int) $beds; }
if ($minPrice > 0) { $where[] = 'p.price >= ?'; $params[] = $minPrice; }
if ($maxPrice > 0) { $where[] = 'p.price <= ?'; $params[] = $maxPrice; }
if ($keyword !== '') {
    $where[] = '(p.title LIKE ? OR p.building LIKE ? OR p.address LIKE ? OR p.code LIKE ?)';
    $kw = '%' . $keyword . '%';
    array_push($params, $kw, $kw, $kw, $kw);
}
$orders = [
    'newest' => 'p.is_featured DESC, p.created_at DESC',
    'price_asc' => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'size' => 'p.floor_area DESC',
];
$orderBy = isset($orders[$sort]) ? $orders[$sort] : $orders['newest'];
$whereSql = ' WHERE ' . implode(' AND ', $where);

$total = (int) q_val('SELECT COUNT(*) FROM properties p' . $whereSql, $params);
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$props = q_all(listing_select() . $whereSql . " ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);

$heading = $type === 'rent' ? 'Condos for Rent' : ($type === 'sale' ? 'Condos for Sale' : 'All Properties');
if ($unit) { $heading = $unit . ' ' . $heading; }
if ($location) { $heading .= ' in ' . $location['name']; }

$activeNav = $location && !$type ? 'locations' : $type;
$pageTitle = $heading;
$metaDescription = $heading . ' — browse verified listings with photos, prices and details from ' . setting('site_name') . '.';

function page_link($n)
{
    $qs = $_GET;
    $qs['page'] = $n;
    return url('properties.php?' . http_build_query($qs));
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-banner">
  <div class="container-xl">
    <nav aria-label="breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?= url('index.php') ?>">Home</a></li><li class="breadcrumb-item active"><?= e($heading) ?></li></ol></nav>
    <h1><?= e($heading) ?></h1>
    <p><?= $total ?> propert<?= $total === 1 ? 'y' : 'ies' ?> found</p>
  </div>
</section>

<section class="section pt-4">
  <div class="container-xl">
    <form class="filter-bar row g-2 align-items-end mb-4" method="get">
      <div class="col-6 col-md-2">
        <label class="form-label small">Looking to</label>
        <select name="type" class="form-select"><option value="">Rent or buy</option><?= select_options(['rent' => 'Rent', 'sale' => 'Buy'], $type, true) ?></select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Location</label>
        <select name="location" class="form-select"><option value="">All locations</option>
          <?php foreach ($locations as $l): ?><option value="<?= e($l['slug']) ?>"<?= $locSlug === $l['slug'] ? ' selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Unit type</label>
        <select name="unit" class="form-select"><option value="">Any</option><?= select_options(unit_types(), $unit) ?></select>
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label small">Min price</label>
        <input name="min" class="form-control" inputmode="numeric" value="<?= $minPrice ? e((int) $minPrice) : '' ?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label small">Max price</label>
        <input name="max" class="form-control" inputmode="numeric" value="<?= $maxPrice ? e((int) $maxPrice) : '' ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small">Keyword</label>
        <input name="q" class="form-control" placeholder="Building, area…" value="<?= e($keyword) ?>">
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-navy flex-fill"><i class="bi bi-search"></i> Filter</button>
        <a href="<?= url('properties.php' . ($type ? '?type=' . $type : '')) ?>" class="btn btn-outline-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
      </div>
      <input type="hidden" name="sort" value="<?= e($sort) ?>">
    </form>

    <div class="d-flex justify-content-end mb-3">
      <div class="dropdown">
        <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">Sort: <?= e(['newest' => 'Newest', 'price_asc' => 'Price (low to high)', 'price_desc' => 'Price (high to low)', 'size' => 'Largest'][$sort] ?? 'Newest') ?></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <?php foreach (['newest' => 'Newest', 'price_asc' => 'Price (low to high)', 'price_desc' => 'Price (high to low)', 'size' => 'Largest'] as $k => $v): $qs = $_GET; $qs['sort'] = $k; unset($qs['page']); ?>
            <li><a class="dropdown-item" href="<?= url('properties.php?' . e(http_build_query($qs))) ?>"><?= $v ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>

    <?php if (!$props): ?>
      <div class="empty-state text-center py-5">
        <i class="bi bi-buildings display-3 text-gold"></i>
        <h2 class="h4 mt-3">No properties match your search yet</h2>
        <p class="text-muted">Try fewer filters, or tell us what you need and we will find it for you.</p>
        <a href="<?= url('contact.php') ?>" class="btn btn-gold">Contact us</a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($props as $p): ?><div class="col-sm-6 col-lg-4"><?= property_card($p) ?></div><?php endforeach; ?>
      </div>
      <?php if ($pages > 1): ?>
        <nav class="mt-5"><ul class="pagination justify-content-center">
          <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(page_link($page - 1)) ?>">&laquo;</a></li>
          <?php for ($i = 1; $i <= $pages; $i++): ?>
            <li class="page-item<?= $i === $page ? ' active' : '' ?>"><a class="page-link" href="<?= e(page_link($i)) ?>"><?= $i ?></a></li>
          <?php endfor; ?>
          <li class="page-item<?= $page >= $pages ? ' disabled' : '' ?>"><a class="page-link" href="<?= e(page_link($page + 1)) ?>">&raquo;</a></li>
        </ul></nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php';
