<?php
/* Admin › Theme & layout (1.1.0): colours, menu layout, page width and light/dark mode for everyone.
   Changes preview instantly on this page; Save applies them for all admin users. */
require __DIR__ . '/includes/init.php';
require_admin('admin');

if (is_post()) {
    csrf_check();
    $old = theme_cfg();
    $new = post('reset') ? [] : [
        'layout' => post('layout'), 'width' => post('width'), 'mode' => post('mode'),
    ];
    if (!post('reset')) {
        foreach (array_keys(theme_defaults()) as $k) {
            $new[$k] = post('c_' . $k);
        }
    }
    $clean = theme_clean($new);
    save_setting('admin_theme', json_encode($clean));
    $labels = ['layout' => 'Layout', 'width' => 'Page width', 'mode' => 'Appearance'];
    foreach (theme_labels() as $k => $l) {
        $labels[$k] = $l[0];
    }
    audit('update', 'theme', 'Theme & layout', post('reset') ? 'Reset to default' : audit_diff($old, $clean, $labels));
    flash('success', post('reset') ? 'Theme reset to the K&M default.' : 'Theme saved for everyone.');
    redirect('admin/theme.php');
}

$t = theme_cfg();
$defs = theme_defaults();
admin_header('Theme & Layout', 'theme');
?>
<p class="hint">Changes preview instantly on this page. Click <b>Save theme</b> to apply them for everyone. The <i class="bi bi-circle-half"></i> button in the header lets each person switch light / dark mode for their own browser.</p>
<form method="post" id="themeForm">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="bkbox">
        <h2>Colours</h2>
        <div class="presets">
          <?php foreach (theme_presets() as $name => $p): $p = array_merge($defs, $p); ?>
            <button type="button" class="btn btn-sm btn-light preset" data-p='<?= e(json_encode($p)) ?>'>
              <?php foreach (['primary', 'accent', 'highlight', 'bg2'] as $k): ?><span class="sw" style="background:<?= e($p[$k]) ?>"></span><?php endforeach; ?><?= e($name) ?>
            </button>
          <?php endforeach; ?>
        </div>
        <?php foreach (theme_labels() as $k => $l): ?>
          <div class="crow">
            <input type="color" name="c_<?= $k ?>" id="c_<?= $k ?>" value="<?= e($t[$k]) ?>" data-k="<?= $k ?>" aria-label="<?= e($l[0]) ?>">
            <div><b><?= e($l[0]) ?></b><div class="hint"><?= e($l[1]) ?></div></div>
            <code id="cv_<?= $k ?>"><?= e($t[$k]) ?></code>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="bkbox">
        <h2>Layout</h2>
        <p class="hint">How the admin menu and pages are arranged for everyone.</p>
        <?php foreach (theme_layouts() as $k => $l): ?>
          <label class="lay-opt"><input type="radio" class="form-check-input" name="layout" value="<?= $k ?>" <?= $t['layout'] === $k ? 'checked' : '' ?>>
            <span><b><?= e($l[0]) ?></b><?= $k === 'panel' ? ' <span class="hint">(default)</span>' : '' ?><br><span class="hint"><?= e($l[1]) ?></span></span></label>
        <?php endforeach; ?>
      </div>
      <div class="bkbox">
        <h2>Page width</h2>
        <p class="hint">How wide the content area is on large screens. Wider shows more table columns without scrolling.</p>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach (theme_widths() as $k => $l): ?>
            <label class="form-check"><input type="radio" class="form-check-input" name="width" value="<?= e($k) ?>" <?= $t['width'] === (string) $k ? 'checked' : '' ?>> <?= e($l) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="bkbox">
        <h2>Appearance</h2>
        <p class="hint">The default for everyone. Each person can still switch with the header button.</p>
        <div class="d-flex flex-wrap gap-3">
          <?php foreach (['auto' => 'Follow device', 'light' => 'Light', 'dark' => 'Dark'] as $k => $l): ?>
            <label class="form-check"><input type="radio" class="form-check-input" name="mode" value="<?= $k ?>" <?= $t['mode'] === $k ? 'checked' : '' ?>> <?= e($l) ?></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="sticky-save d-flex flex-wrap gap-2">
    <button type="submit" name="reset" value="1" class="btn btn-light" onclick="return confirm('Reset colours and layout to the K&M default?')">Reset to default</button>
    <button class="btn btn-primary ms-auto"><i class="bi bi-check2 me-1"></i>Save theme</button>
  </div>
</form>

<script>
(function () {
  var root = document.documentElement.style, body = document.body;
  var VARS = { primary: '--primary', accent: '--accent', highlight: '--hl', info: '--info', danger: '--danger', neutral: '--neutral', bg1: '--bg1', bg2: '--bg2' };
  var ON = { primary: '--onp', accent: '--ona', highlight: '--onhl' };
  function on(hex) { var n = parseInt(hex.slice(1), 16); return (0.299 * (n >> 16 & 255) + 0.587 * (n >> 8 & 255) + 0.114 * (n & 255)) / 255 > 0.6 ? '#14202b' : '#ffffff'; }
  function setC(k, v) {
    root.setProperty(VARS[k], v); if (ON[k]) root.setProperty(ON[k], on(v));
    var c = document.getElementById('cv_' + k); if (c) c.textContent = v;
  }
  document.querySelectorAll('input[type=color]').forEach(function (i) { i.addEventListener('input', function () { setC(i.dataset.k, i.value); }); });
  document.querySelectorAll('.preset').forEach(function (b) {
    b.addEventListener('click', function () {
      var p = JSON.parse(b.dataset.p);
      Object.keys(VARS).forEach(function (k) { var i = document.getElementById('c_' + k); i.value = p[k]; setC(k, p[k]); });
    });
  });
  document.querySelectorAll('input[name=layout]').forEach(function (r) {
    r.addEventListener('change', function () {
      var l = r.value, iconL = ['panel', 'dark', 'float', 'rail'].indexOf(l) >= 0;
      body.className = body.className.replace(/\blay-\w+\b/g, '').replace(/\bnav[lco]\b/g, '').trim() + ' lay-' + l + (iconL ? ' navl' : '');
      body.dataset.layout = l;
      if (iconL && l === 'rail') body.classList.add('navc');
    });
  });
  document.querySelectorAll('input[name=width]').forEach(function (r) {
    r.addEventListener('change', function () { root.setProperty('--mainw', r.value === 'full' ? '100%' : r.value + 'px'); });
  });
  document.querySelectorAll('input[name=mode]').forEach(function (r) {
    r.addEventListener('change', function () { try { localStorage.removeItem('kmup_mode'); } catch (e) {} applyMode(r.value); });
  });
})();
</script>
<?php admin_footer();
