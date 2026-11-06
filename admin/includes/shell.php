<?php
/** Admin page shell (1.1.0, RBJocsing-style): header bar, menu (layout from Admin › Theme & layout), page card, footer, idle sign-out. */

function admin_nav()
{
    $u = current_user();
    $isAdmin = $u && $u['role'] === 'admin';
    $newInq = (int) q_val("SELECT COUNT(*) FROM inquiries WHERE status = 'new'");
    $openJobs = (int) q_val("SELECT COUNT(*) FROM maintenance WHERE status IN ('open','scheduled','in_progress')");
    try {
        $pendingSubs = (int) q_val("SELECT COUNT(*) FROM properties WHERE approval = 'pending'");
    } catch (Exception $ex) {
        $pendingSubs = 0; // columns not added yet
    }
    $nav = [
        'Overview' => [
            ['dashboard', 'dashboard.php', 'bi-speedometer2', 'Dashboard', 0],
        ],
        'Management' => [
            ['properties', 'properties.php', 'bi-buildings', 'Properties', 0],
            ['tenants', 'tenants.php', 'bi-people', 'Tenants', 0],
            ['leases', 'leases.php', 'bi-file-earmark-text', 'Leases & Payments', 0],
            ['maintenance', 'maintenance.php', 'bi-tools', 'Repairs & Maintenance', $openJobs],
            ['documents', 'documents.php', 'bi-folder2-open', 'Documents', 0],
        ],
        'Website' => [
            ['submissions', 'submissions.php', 'bi-house-add', 'Owner Submissions', $pendingSubs],
            ['sections', 'sections.php', 'bi-layout-text-window', 'Landing Page', 0],
            ['locations', 'locations.php', 'bi-geo-alt', 'Locations', 0],
            ['inquiries', 'inquiries.php', 'bi-envelope', 'Inquiries', $newInq],
        ],
    ];
    if ($isAdmin) {
        $nav['Admin'] = [
            ['settings', 'settings.php', 'bi-gear', 'Site Settings', 0],
            ['theme', 'theme.php', 'bi-palette', 'Theme & Layout', 0],
            ['users', 'users.php', 'bi-person-gear', 'Users', 0],
            ['security', 'security.php', 'bi-shield-lock', 'Security', 0],
            ['audit', 'audit.php', 'bi-journal-text', 'Audit Log', 0],
        ];
    }
    return $nav;
}

function admin_header($title, $active = '')
{
    $u = current_user();
    $t = theme_cfg();
    $initials = '';
    foreach (array_slice(preg_split('/\s+/', trim($u['name'])), 0, 2) as $w) {
        $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    $navl = in_array($t['layout'], ['panel', 'dark', 'float', 'rail'], true);
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Admin · <?= e(setting('site_name', 'K&M Urban Properties')) ?></title>
<link rel="icon" href="<?= url('assets/img/favicon.png') ?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="<?= url('assets/css/admin.css?v=' . APP_VERSION) ?>" rel="stylesheet">
<?= theme_head() ?>
</head>
<body class="admin lay-<?= e($t['layout']) ?><?= $navl ? ' navl' : '' ?>"
      data-layout="<?= e($t['layout']) ?>"
      data-idle-min="<?= (int) security_cfg()['idle'] ?>"
      data-ping="<?= e(url('admin/ping.php')) ?>"
      data-csrf="<?= e(csrf_token()) ?>">
<script>(function(){var b=document.body,l=b.dataset.layout,v=null;try{v=localStorage.getItem("kmup_nav_"+l)}catch(e){}if(b.classList.contains("navl")&&(v===null?l==="rail":v==="1"))b.classList.add("navc")})();</script>
<header class="adm-head">
  <button class="navtog" type="button" onclick="navToggle()" aria-label="Menu"><i class="bi bi-list"></i></button>
  <a class="adm-logo" href="<?= url('admin/dashboard.php') ?>" title="Dashboard"><img src="<?= e(setting('logo_dark') ? site_image_url(setting('logo_dark')) : url('assets/img/logo-dark.png')) ?>" alt="<?= e(setting('site_name', 'K&M Urban Properties')) ?>"></a>
  <span class="hright">
    <span class="clk d-none d-md-inline" id="clk"></span>
    <button type="button" class="hbtn" id="modeBtn" onclick="cycleMode()" title="Light / dark mode"><i class="bi bi-circle-half"></i></button>
    <a class="hbtn d-none d-sm-inline-flex" href="<?= url('index.php') ?>" target="_blank" title="View website"><i class="bi bi-box-arrow-up-right"></i></a>
    <form method="post" action="<?= url('admin/logout.php') ?>" class="d-inline"><?= csrf_field() ?><button class="hbtn hout">← LOGOUT</button></form>
  </span>
</header>
<main class="adm-main">
  <div class="adm-card">
    <div class="lay">
      <nav class="lnav" aria-label="Admin menu">
        <a class="who" href="<?= url('admin/profile.php') ?>" title="My account">
          <span class="av"><?= e($initials ?: '?') ?></span>
          <span class="who-t"><b><?= e($u['name']) ?></b><small><?= $u['role'] === 'admin' ? 'Administrator' : 'Staff' ?></small></span>
        </a>
        <div class="tabs">
          <?php foreach (admin_nav() as $group => $items): ?>
            <div class="sg"><?= e($group) ?></div>
            <?php foreach ($items as $n): ?>
              <a href="<?= url('admin/' . $n[1]) ?>" class="tab<?= $active === $n[0] ? ' on' : '' ?>" title="<?= e($n[3]) ?>"<?= $active === $n[0] ? ' aria-current="page"' : '' ?>>
                <span class="ti"><i class="bi <?= $n[2] ?>"></i></span><span class="tl"><?= e($n[3]) ?></span><?php if ($n[4]): ?><span class="bdg"><?= $n[4] ?></span><?php endif; ?>
              </a>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </nav>
      <div class="lmain">
        <h1 class="ptitle"><?= e($title) ?></h1>
        <?= flashes_html() ?>
<?php
}

function admin_footer()
{
    ?>
      </div>
    </div>
  </div>
</main>
<footer class="adm-foot">© <?= date('Y') ?> – <?= e(setting('site_name', 'K&M Urban Properties')) ?> · v<?= e(APP_VERSION) ?></footer>
<div class="sidebar-backdrop" onclick="document.body.classList.remove('navo')"></div>

<div class="modal fade" id="idleModal" tabindex="-1" data-bs-backdrop="static" aria-labelledby="idleTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">
    <div class="modal-body text-center p-4">
      <i class="bi bi-hourglass-split fs-1 text-warning"></i>
      <h2 class="h5 mt-2" id="idleTitle">Still there?</h2>
      <p class="mb-3">For your security you will be signed out in <b id="idleLeft">60</b> seconds because there was no activity.</p>
      <button type="button" class="btn btn-primary w-100" id="idleStay">Stay signed in</button>
    </div>
  </div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= url('assets/js/admin.js?v=' . APP_VERSION) ?>"></script>
</body>
</html>
<?php
}
