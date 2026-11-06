<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/sections-config.php';
require_admin();

$id = (int) get('id');
$s = q_one('SELECT * FROM landing_sections WHERE id = ?', [$id]);
if (!$s) {
    flash('error', 'Section not found.');
    redirect('admin/sections.php');
}
$cfg = section_config($s['section_key']);
$f = $cfg['fields'];
$self = 'admin/section-edit.php?id=' . $id;

if (is_post()) {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'save') {
            $data = ['is_active' => post('is_active') ? 1 : 0];
            foreach (['title', 'highlight', 'subtitle', 'body'] as $k) {
                if (isset($f[$k])) {
                    $data[$k] = nn(post($k));
                }
            }
            if (isset($f['button'])) {
                $data['button_text'] = nn(post('button_text'));
                $data['button_link'] = nn(post('button_link'));
            }
            if (isset($f['limit'])) {
                $data['item_limit'] = max(1, min(24, (int) post('item_limit')));
            }
            if (isset($f['image'])) {
                $files = files_list('image');
                if ($files) {
                    $data['image'] = save_image_upload($files[0], SITE_UPLOAD_DIR, 2400);
                    if ($s['image']) {
                        delete_file_safe(SITE_UPLOAD_DIR . '/' . $s['image']);
                    }
                } elseif (post('remove_image') && $s['image']) {
                    delete_file_safe(SITE_UPLOAD_DIR . '/' . $s['image']);
                    $data['image'] = null;
                }
            }
            db_update('landing_sections', $data, $id);
            flash('success', 'Section saved.');
        } elseif ($action === 'item_save' && $cfg['items'] !== null) {
            $itemId = (int) post('item_id');
            $data = [
                'icon' => nn(post('icon')),
                'title' => nn(post('title')),
                'subtitle' => nn(post('subtitle')),
                'text' => nn(post('text')),
                'link' => nn(post('link')),
                'sort_order' => (int) post('sort_order'),
            ];
            $existing = $itemId ? q_one('SELECT * FROM section_items WHERE id = ? AND section_id = ?', [$itemId, $id]) : null;
            if (isset($cfg['items']['image'])) {
                $files = files_list('item_image');
                if ($files) {
                    $data['image'] = save_image_upload($files[0], SITE_UPLOAD_DIR, 600);
                    if ($existing && $existing['image']) {
                        delete_file_safe(SITE_UPLOAD_DIR . '/' . $existing['image']);
                    }
                }
            }
            if ($existing) {
                db_update('section_items', $data, $itemId);
                flash('success', 'Item updated.');
            } else {
                $data['section_id'] = $id;
                if (!$data['sort_order']) {
                    $data['sort_order'] = (int) q_val('SELECT COALESCE(MAX(sort_order),0) + 1 FROM section_items WHERE section_id = ?', [$id]);
                }
                db_insert('section_items', $data);
                flash('success', 'Item added.');
            }
        } elseif ($action === 'item_delete') {
            $item = q_one('SELECT * FROM section_items WHERE id = ? AND section_id = ?', [(int) post('item_id'), $id]);
            if ($item) {
                if ($item['image']) {
                    delete_file_safe(SITE_UPLOAD_DIR . '/' . $item['image']);
                }
                q('DELETE FROM section_items WHERE id = ?', [$item['id']]);
                flash('success', 'Item removed.');
            }
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect($self);
}

$items = q_all('SELECT * FROM section_items WHERE section_id = ? ORDER BY sort_order, id', [$id]);
$editItem = get('item') ? q_one('SELECT * FROM section_items WHERE id = ? AND section_id = ?', [(int) get('item'), $id]) : null;

admin_header('Edit: ' . $s['label'], 'sections');
?>
<a href="<?= url('admin/sections.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> All sections</a>
<?php if ($cfg['help']): ?><div class="alert alert-info py-2"><i class="bi bi-info-circle me-1"></i><?= e($cfg['help']) ?></div><?php endif; ?>

<div class="row g-4">
  <div class="<?= $cfg['items'] !== null ? 'col-xl-5' : 'col-xl-8' ?>">
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="card-header">Section content</div>
      <div class="card-body row g-3">
        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= $s['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="isActive">Show this section on the homepage</label></div>
        <?php if (isset($f['title'])): ?><div class="col-12"><label class="form-label"><?= e($f['title']) ?></label><input name="title" class="form-control" value="<?= e($s['title']) ?>" maxlength="200"></div><?php endif; ?>
        <?php if (isset($f['highlight'])): ?><div class="col-12"><label class="form-label"><?= e($f['highlight']) ?></label><input name="highlight" class="form-control" value="<?= e($s['highlight']) ?>" maxlength="200"></div><?php endif; ?>
        <?php if (isset($f['subtitle'])): ?><div class="col-12"><label class="form-label"><?= e($f['subtitle']) ?></label><input name="subtitle" class="form-control" value="<?= e($s['subtitle']) ?>" maxlength="255"></div><?php endif; ?>
        <?php if (isset($f['body'])): ?><div class="col-12"><label class="form-label"><?= e($f['body']) ?></label><textarea name="body" class="form-control" rows="5"><?= e($s['body']) ?></textarea></div><?php endif; ?>
        <?php if (isset($f['limit'])): ?><div class="col-md-4"><label class="form-label"><?= e($f['limit']) ?></label><input type="number" name="item_limit" class="form-control" min="1" max="24" value="<?= (int) $s['item_limit'] ?>"></div><?php endif; ?>
        <?php if (isset($f['button'])): ?>
          <div class="col-md-6"><label class="form-label"><?= e($f['button']) ?> — text</label><input name="button_text" class="form-control" value="<?= e($s['button_text']) ?>" maxlength="80"></div>
          <div class="col-md-6"><label class="form-label">Button link</label><input name="button_link" class="form-control" value="<?= e($s['button_link']) ?>" placeholder="properties.php?type=rent" maxlength="255"></div>
        <?php endif; ?>
        <?php if (isset($f['image'])): ?>
          <div class="col-12">
            <label class="form-label"><?= e($f['image']) ?></label>
            <div class="d-flex gap-3 align-items-start">
              <img src="<?= e(site_image_url($s['image'])) ?>" class="img-preview" alt="">
              <div class="flex-fill">
                <input type="file" name="image" class="form-control" accept="image/*">
                <?php if ($s['image']): ?><div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmImg"><label class="form-check-label small" for="rmImg">Remove and use the default picture</label></div><?php else: ?><div class="small text-muted mt-1">Using the default picture.</div><?php endif; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
        <?php if (!$f): ?><p class="text-muted mb-0">This section has no heading — edit its items on the right.</p><?php endif; ?>
      </div>
      <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save section</button></div>
    </form>
  </div>

  <?php if ($cfg['items'] !== null): $il = $cfg['items']; ?>
  <div class="col-xl-7">
    <div class="card mb-4">
      <div class="card-header">Items</div>
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>#</th><?php if (isset($il['icon'])): ?><th>Icon</th><?php endif; ?><th><?= e($il['title'] ?? 'Title') ?></th><th></th></tr></thead>
        <tbody>
          <?php if (!$items): ?><tr><td colspan="4" class="text-muted">No items yet — add one below.</td></tr><?php endif; ?>
          <?php foreach ($items as $it): ?>
            <tr>
              <td class="text-muted"><?= (int) $it['sort_order'] ?></td>
              <?php if (isset($il['icon'])): ?><td><i class="bi <?= e($it['icon']) ?> fs-4 text-teal"></i></td><?php endif; ?>
              <td><strong><?= e($it['title']) ?></strong> <?= e($it['subtitle']) ?><div class="small text-muted text-truncate" style="max-width:380px"><?= e($it['text']) ?></div></td>
              <td class="text-end text-nowrap">
                <a href="<?= url($self . '&item=' . $it['id']) ?>#itemForm" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Remove this item?')"><?= csrf_field() ?><input type="hidden" name="action" value="item_delete"><input type="hidden" name="item_id" value="<?= $it['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <form method="post" enctype="multipart/form-data" class="card" id="itemForm">
      <?= csrf_field() ?><input type="hidden" name="action" value="item_save"><input type="hidden" name="item_id" value="<?= $editItem ? $editItem['id'] : 0 ?>">
      <div class="card-header"><?= $editItem ? 'Edit item' : 'Add item' ?></div>
      <div class="card-body row g-3">
        <?php if (isset($il['icon'])): ?>
          <div class="col-12">
            <label class="form-label">Icon</label>
            <input type="hidden" name="icon" id="iconInput" value="<?= e($editItem['icon'] ?? 'bi-star') ?>">
            <div class="icon-picker">
              <?php foreach (icon_choices() as $ic): ?>
                <button type="button" class="icon-opt<?= ($editItem['icon'] ?? 'bi-star') === $ic ? ' active' : '' ?>" data-icon="<?= $ic ?>" title="<?= $ic ?>"><i class="bi <?= $ic ?>"></i></button>
              <?php endforeach; ?>
            </div>
            <div class="form-text">More icons: any name from icons.getbootstrap.com, typed as <code>bi-name</code> — <a href="#" onclick="var v=prompt('Bootstrap icon name (e.g. bi-house)');if(v){document.getElementById('iconInput').value=v;}return false;">enter custom</a></div>
          </div>
        <?php endif; ?>
        <?php if (isset($il['title'])): ?><div class="col-md-6"><label class="form-label"><?= e($il['title']) ?></label><input name="title" class="form-control" value="<?= e($editItem['title'] ?? '') ?>" maxlength="200" required></div><?php endif; ?>
        <?php if (isset($il['subtitle'])): ?><div class="col-md-6"><label class="form-label"><?= e($il['subtitle']) ?></label><input name="subtitle" class="form-control" value="<?= e($editItem['subtitle'] ?? '') ?>" maxlength="200"></div><?php endif; ?>
        <?php if (isset($il['text'])): ?><div class="col-12"><label class="form-label"><?= e($il['text']) ?></label><textarea name="text" class="form-control" rows="3"><?= e($editItem['text'] ?? '') ?></textarea></div><?php endif; ?>
        <?php if (isset($il['link'])): ?><div class="col-md-8"><label class="form-label"><?= e($il['link']) ?></label><input name="link" class="form-control" value="<?= e($editItem['link'] ?? '') ?>" maxlength="255"></div><?php endif; ?>
        <?php if (isset($il['image'])): ?><div class="col-md-8"><label class="form-label"><?= e($il['image']) ?></label><input type="file" name="item_image" class="form-control" accept="image/*"></div><?php endif; ?>
        <div class="col-md-4"><label class="form-label">Order</label><input type="number" name="sort_order" class="form-control" value="<?= (int) ($editItem['sort_order'] ?? 0) ?>"></div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <?php if ($editItem): ?><a href="<?= url($self) ?>" class="btn btn-light">Cancel</a><?php else: ?><span></span><?php endif; ?>
        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i><?= $editItem ? 'Update item' : 'Add item' ?></button>
      </div>
    </form>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer();
