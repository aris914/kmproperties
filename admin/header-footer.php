<?php
/* Admin › Header & Footer (1.6.0): website menu, Call button, announcement bar and footer. */
require __DIR__ . '/includes/init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $navIn = isset($_POST['nav']) && is_array($_POST['nav']) ? $_POST['nav'] : [];
    $nav = [];
    foreach (array_keys(header_nav_defaults()) as $k) {
        $x = isset($navIn[$k]) && is_array($navIn[$k]) ? $navIn[$k] : [];
        $nav[$k] = ['label' => (string) ($x['label'] ?? ''), 'show' => !empty($x['show']), 'order' => (int) ($x['order'] ?? 0)];
    }
    $rows = function ($name, $withNew = false) {
        $out = [];
        $in = isset($_POST[$name]) && is_array($_POST[$name]) ? $_POST[$name] : [];
        foreach ($in as $l) {
            if (!is_array($l)) {
                continue;
            }
            $out[] = ['label' => (string) ($l['label'] ?? ''), 'url' => (string) ($l['url'] ?? '')] + ($withNew ? ['new' => !empty($l['new'])] : []);
        }
        return $out;
    };
    $header = site_header_clean([
        'nav' => $nav, 'links' => $rows('hlinks', true),
        'call_show' => post('call_show') ? 1 : 0, 'call_label' => post('call_label'), 'call_number' => post('call_number'),
        'sticky' => post('sticky') ? 1 : 0,
        'ann' => ['show' => post('ann_show') ? 1 : 0, 'text' => post('ann_text'), 'link' => post('ann_link'), 'link_text' => post('ann_link_text'), 'style' => post('ann_style')],
    ]);
    $footer = site_footer_clean([
        'show_logo' => post('show_logo') ? 1 : 0, 'show_social' => post('show_social') ? 1 : 0,
        'col1' => post('col1'), 'links' => $rows('flinks'),
        'show_locations' => post('show_locations') ? 1 : 0, 'col2' => post('col2'), 'loc_count' => post('loc_count'),
        'show_contact' => post('show_contact') ? 1 : 0, 'col3' => post('col3'),
        'copyright' => post('copyright'), 'tagline' => post('tagline'), 'admin_link' => post('admin_link') ? 1 : 0,
    ]);
    save_setting('site_header', json_encode($header));
    save_setting('site_footer', json_encode($footer));
    save_setting('footer_about', mb_substr(post('footer_about'), 0, 1000));
    audit('update', 'settings', 'Header & footer', 'Menu items shown: ' . count(array_filter($header['nav'], function ($n) { return $n['show']; })) . ', custom links: ' . count($header['links'])
        . ', announcement ' . ($header['ann']['show'] ? 'on' : 'off') . ', footer links: ' . count($footer['links']));
    flash('success', 'Header and footer saved. Open the website to see them.');
    redirect('admin/header-footer.php');
}

$h = site_header_cfg();
$f = site_footer_cfg();
admin_header('Header & Footer', 'headerfooter');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <p class="hint mb-0">The website’s menu, Call button, announcement bar and footer. Logo, phone, email, address and social links are in <a href="<?= url('admin/settings.php') ?>">Site Settings</a>; colours in <a href="<?= url('admin/theme.php') ?>">Theme &amp; Layout</a>.</p>
  <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-light btn-sm"><i class="bi bi-box-arrow-up-right me-1"></i>Open the website</a>
</div>
<form method="post">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-6">
      <div class="bkbox">
        <h2><i class="bi bi-menu-button-wide me-1"></i>Header menu</h2>
        <p class="hint">Rename, hide or reorder the menu items (lower number = further left). Rentals, Sales and Locations keep their drop-down lists.</p>
        <div class="table-responsive"><table class="table table-sm align-middle mb-2">
          <thead><tr><th style="width:70px">Show</th><th>Label</th><th style="width:90px">Order</th></tr></thead>
          <tbody>
          <?php foreach ($h['nav'] as $k => $n): ?>
            <tr>
              <td><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="nav[<?= $k ?>][show]" value="1" <?= $n['show'] ? 'checked' : '' ?> aria-label="Show <?= e($n['label']) ?>"></div></td>
              <td><input name="nav[<?= $k ?>][label]" class="form-control form-control-sm" value="<?= e($n['label']) ?>" maxlength="40" placeholder="<?= e(header_nav_defaults()[$k]) ?>"></td>
              <td><input type="number" name="nav[<?= $k ?>][order]" class="form-control form-control-sm" value="<?= (int) $n['order'] ?>"></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <h3 class="h6 mt-3">Extra menu links <span class="hint">(up to 6, shown after the items above)</span></h3>
        <div id="hlinks">
          <?php foreach (array_pad($h['links'], max(2, count($h['links']) + 1), ['label' => '', 'url' => '', 'new' => false]) as $i => $lk): ?>
            <div class="row g-2 mb-2 link-row">
              <div class="col-5"><input name="hlinks[<?= $i ?>][label]" class="form-control form-control-sm" placeholder="Label" value="<?= e($lk['label']) ?>" maxlength="40"></div>
              <div class="col-5"><input name="hlinks[<?= $i ?>][url]" class="form-control form-control-sm" placeholder="https://… or page.php" value="<?= e($lk['url']) ?>" maxlength="255"></div>
              <div class="col-2 d-flex align-items-center"><label class="form-check small mb-0" title="Open in a new tab"><input type="checkbox" class="form-check-input" name="hlinks[<?= $i ?>][new]" value="1" <?= !empty($lk['new']) ? 'checked' : '' ?>> new tab</label></div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-light" onclick="addRow('hlinks', 6)"><i class="bi bi-plus"></i> Add link</button>
        <p class="hint mt-2 mb-0">Leave a row empty to remove it.</p>
      </div>

      <div class="bkbox">
        <h2><i class="bi bi-telephone me-1"></i>Call button &amp; header</h2>
        <div class="form-check form-switch my-2"><input class="form-check-input" type="checkbox" name="call_show" value="1" id="callShow" <?= $h['call_show'] ? 'checked' : '' ?>><label class="form-check-label" for="callShow">Show the Call button</label></div>
        <div class="row g-2">
          <div class="col-md-6"><label class="form-label">Button text</label><input name="call_label" class="form-control" value="<?= e($h['call_label']) ?>" maxlength="30"></div>
          <div class="col-md-6"><label class="form-label">Number to call</label><input name="call_number" class="form-control" value="<?= e($h['call_number']) ?>" maxlength="40" placeholder="<?= e(setting('phone') ?: 'main phone in Site Settings') ?>"></div>
        </div>
        <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="sticky" value="1" id="sticky" <?= $h['sticky'] ? 'checked' : '' ?>><label class="form-check-label" for="sticky">Keep the header at the top while scrolling</label></div>
      </div>

      <div class="bkbox">
        <h2><i class="bi bi-megaphone me-1"></i>Announcement bar</h2>
        <p class="hint">A thin bar above the header, e.g. “New units in BGC — move in this month!”</p>
        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="ann_show" value="1" id="annShow" <?= $h['ann']['show'] ? 'checked' : '' ?>><label class="form-check-label" for="annShow">Show the announcement bar</label></div>
        <div class="row g-2">
          <div class="col-12"><label class="form-label">Text</label><input name="ann_text" class="form-control" value="<?= e($h['ann']['text']) ?>" maxlength="200"></div>
          <div class="col-md-5"><label class="form-label">Link (optional)</label><input name="ann_link" class="form-control" value="<?= e($h['ann']['link']) ?>" maxlength="255" placeholder="properties.php?type=rent"></div>
          <div class="col-md-4"><label class="form-label">Link text</label><input name="ann_link_text" class="form-control" value="<?= e($h['ann']['link_text']) ?>" maxlength="40" placeholder="Learn more"></div>
          <div class="col-md-3"><label class="form-label">Colour</label><select name="ann_style" class="form-select"><?= select_options(['gold' => 'Highlight (gold)', 'teal' => 'Accent (teal)', 'navy' => 'Dark', 'red' => 'Red (urgent)'], $h['ann']['style'], true) ?></select></div>
        </div>
      </div>
    </div>

    <div class="col-xl-6">
      <div class="bkbox">
        <h2><i class="bi bi-layout-text-window-reverse me-1"></i>Footer — about</h2>
        <div class="form-check form-switch my-2"><input class="form-check-input" type="checkbox" name="show_logo" value="1" id="showLogo" <?= $f['show_logo'] ? 'checked' : '' ?>><label class="form-check-label" for="showLogo">Show the logo</label></div>
        <label class="form-label">About text</label><textarea name="footer_about" class="form-control" rows="3" maxlength="1000"><?= e(setting('footer_about')) ?></textarea>
        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="show_social" value="1" id="showSocial" <?= $f['show_social'] ? 'checked' : '' ?>><label class="form-check-label" for="showSocial">Show social media buttons <span class="hint">(links in Site Settings)</span></label></div>
      </div>

      <div class="bkbox">
        <h2><i class="bi bi-link-45deg me-1"></i>Footer — quick links</h2>
        <label class="form-label">Column title</label><input name="col1" class="form-control mb-2" value="<?= e($f['col1']) ?>" maxlength="40">
        <div id="flinks">
          <?php foreach (array_pad($f['links'], max(2, count($f['links']) + 1), ['label' => '', 'url' => '']) as $i => $lk): ?>
            <div class="row g-2 mb-2 link-row">
              <div class="col-5"><input name="flinks[<?= $i ?>][label]" class="form-control form-control-sm" placeholder="Label" value="<?= e($lk['label']) ?>" maxlength="40"></div>
              <div class="col-7"><input name="flinks[<?= $i ?>][url]" class="form-control form-control-sm" placeholder="page.php or https://…" value="<?= e($lk['url']) ?>" maxlength="255"></div>
            </div>
          <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-light" onclick="addRow('flinks', 10)"><i class="bi bi-plus"></i> Add link</button>
        <span class="hint ms-2">Remove all links to hide this column.</span>
      </div>

      <div class="bkbox">
        <h2><i class="bi bi-columns-gap me-1"></i>Footer — locations &amp; contact</h2>
        <div class="row g-2 align-items-end">
          <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_locations" value="1" id="showLoc" <?= $f['show_locations'] ? 'checked' : '' ?>><label class="form-check-label" for="showLoc">Show locations</label></div></div>
          <div class="col-md-5"><label class="form-label">Column title</label><input name="col2" class="form-control" value="<?= e($f['col2']) ?>" maxlength="40"></div>
          <div class="col-md-3"><label class="form-label">How many</label><input type="number" name="loc_count" min="1" max="20" class="form-control" value="<?= (int) $f['loc_count'] ?>"></div>
          <div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_contact" value="1" id="showContact" <?= $f['show_contact'] ? 'checked' : '' ?>><label class="form-check-label" for="showContact">Show contact details</label></div></div>
          <div class="col-md-8"><label class="form-label">Column title</label><input name="col3" class="form-control" value="<?= e($f['col3']) ?>" maxlength="40"></div>
        </div>
      </div>

      <div class="bkbox">
        <h2><i class="bi bi-c-circle me-1"></i>Footer — bottom bar</h2>
        <label class="form-label">Copyright line</label><input name="copyright" class="form-control mb-1" value="<?= e($f['copyright']) ?>" maxlength="200">
        <div class="hint mb-2"><code>{year}</code> = current year, <code>{site}</code> = business name. Shows: <?= e(footer_text($f['copyright'])) ?></div>
        <label class="form-label">Right-hand text <span class="hint">(leave empty to hide)</span></label><input name="tagline" class="form-control" value="<?= e($f['tagline']) ?>" maxlength="200">
        <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="admin_link" value="1" id="adminLink" <?= $f['admin_link'] ? 'checked' : '' ?>><label class="form-check-label" for="adminLink">Show a small “Admin” sign-in link</label></div>
      </div>
    </div>
  </div>
  <div class="sticky-save text-end"><button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>Save header &amp; footer</button></div>
</form>
<script>
function addRow(id, max) {
  var box = document.getElementById(id), rows = box.querySelectorAll('.link-row');
  if (rows.length >= max) { alert('Up to ' + max + ' links.'); return; }
  var clone = rows[rows.length - 1].cloneNode(true), n = rows.length;
  clone.querySelectorAll('input').forEach(function (i) {
    i.name = i.name.replace(/\[\d+\]/, '[' + n + ']');
    if (i.type === 'checkbox') i.checked = false; else i.value = '';
  });
  box.appendChild(clone);
}
</script>
<?php admin_footer();
