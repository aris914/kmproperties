<?php
require __DIR__ . '/includes/init.php';
require_admin('admin');

$fields = [
    'General' => [
        'site_name' => ['Business name', 'text'],
        'tagline' => ['Tagline', 'text'],
        'meta_description' => ['Search engine description (about 155 characters)', 'textarea'],
        'footer_about' => ['Footer “about” text', 'textarea'],
        'currency' => ['Currency symbol', 'text'],
    ],
    'Contact' => [
        'phone' => ['Main phone (used for the “Call Us” button)', 'text'],
        'phone_alt' => ['Other phone', 'text'],
        'email' => ['Public email', 'email'],
        'address' => ['Office address', 'text'],
        'office_hours' => ['Office hours', 'text'],
        'map_embed' => ['Google Maps embed link (the src="https://www.google.com/maps/embed?..." part)', 'text'],
        'notify_email' => ['Send new-inquiry alerts to (optional; needs mail() on your host)', 'email'],
    ],
    'Social media' => [
        'facebook' => ['Facebook page URL', 'url'],
        'instagram' => ['Instagram URL', 'url'],
        'tiktok' => ['TikTok URL', 'url'],
        'viber' => ['Viber number', 'text'],
        'whatsapp' => ['WhatsApp number', 'text'],
    ],
];

if (is_post()) {
    csrf_check();
    $changed = [];
    foreach ($fields as $group) {
        foreach ($group as $key => $meta) {
            $val = post($key);
            if ((string) setting($key) !== (string) $val) {
                $changed[] = $meta[0];
            }
            if ($key === 'map_embed' && $val !== '') {
                if (preg_match('~src="([^"]+)"~', $val, $m)) {
                    $val = html_entity_decode($m[1]);
                }
                if (!preg_match('~^https://(www\.)?google\.com/maps/embed\?~', $val)) {
                    flash('warning', 'The map link was not saved — it must start with https://www.google.com/maps/embed?');
                    continue;
                }
            }
            save_setting($key, $val);
        }
    }
    try {
        foreach (['logo_dark' => 'Header/footer logo'] as $key => $label) {
            $files = files_list($key);
            if ($files) {
                $old = setting($key);
                save_setting($key, save_image_upload($files[0], SITE_UPLOAD_DIR, 800));
                if ($old) {
                    delete_file_safe(SITE_UPLOAD_DIR . '/' . $old);
                }
            } elseif (post('reset_' . $key)) {
                if (setting($key)) {
                    delete_file_safe(SITE_UPLOAD_DIR . '/' . setting($key));
                }
                save_setting($key, '');
            }
        }
        audit('update', 'settings', 'Site settings', $changed);
        flash('success', 'Settings saved.');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/settings.php');
}

admin_header('Site Settings', 'settings');
?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="row g-4">
    <?php foreach ($fields as $group => $items): ?>
      <div class="col-xl-6">
        <div class="card h-100">
          <div class="card-header"><?= e($group) ?></div>
          <div class="card-body row g-3">
            <?php foreach ($items as $key => $meta): ?>
              <div class="col-12">
                <label class="form-label"><?= e($meta[0]) ?></label>
                <?php if ($meta[1] === 'textarea'): ?>
                  <textarea name="<?= $key ?>" class="form-control" rows="3"><?= e(setting($key)) ?></textarea>
                <?php else: ?>
                  <input type="<?= $meta[1] ?>" name="<?= $key ?>" class="form-control" value="<?= e(setting($key)) ?>">
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="col-xl-6">
      <div class="card h-100">
        <div class="card-header">Logo</div>
        <div class="card-body">
          <p class="small text-muted">Shown on the dark-blue header and footer. Use a PNG with a dark or transparent background, about 480 × 200 px.</p>
          <div class="logo-preview mb-3"><img src="<?= e(setting('logo_dark') ? site_image_url(setting('logo_dark')) : url('assets/img/logo-dark.png?v=' . APP_VERSION)) ?>" alt=""></div>
          <input type="file" name="logo_dark" class="form-control" accept="image/png,image/webp,image/jpeg">
          <?php if (setting('logo_dark')): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="reset_logo_dark" value="1" id="rstLogo"><label class="form-check-label small" for="rstLogo">Go back to the original logo</label></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <div class="sticky-save"><button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>Save settings</button></div>
</form>
<?php admin_footer();
