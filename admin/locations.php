<?php
require __DIR__ . '/includes/init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $action = post('action');
    try {
        if ($action === 'save') {
            $id = (int) post('id');
            $name = post('name');
            if ($name === '') {
                throw new RuntimeException('Enter a location name.');
            }
            $data = [
                'name' => $name,
                'slug' => unique_slug('locations', post('slug') ?: $name, $id),
                'description' => nn(post('description')),
                'is_active' => post('is_active') ? 1 : 0,
                'sort_order' => (int) post('sort_order'),
            ];
            $existing = $id ? q_one('SELECT * FROM locations WHERE id = ?', [$id]) : null;
            $files = files_list('image');
            if ($files) {
                $data['image'] = save_image_upload($files[0], SITE_UPLOAD_DIR, 1000);
                if ($existing && $existing['image']) {
                    delete_file_safe(SITE_UPLOAD_DIR . '/' . $existing['image']);
                }
            }
            if ($existing) {
                db_update('locations', $data, $id);
            } else {
                db_insert('locations', $data);
            }
            flash('success', 'Location saved.');
        } elseif ($action === 'delete') {
            $loc = q_one('SELECT * FROM locations WHERE id = ?', [(int) post('id')]);
            if ($loc) {
                if ($loc['image']) {
                    delete_file_safe(SITE_UPLOAD_DIR . '/' . $loc['image']);
                }
                q('DELETE FROM locations WHERE id = ?', [$loc['id']]);
                flash('success', 'Location deleted. Its properties now have no location.');
            }
        }
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin/locations.php');
}

$rows = q_all('SELECT l.*, (SELECT COUNT(*) FROM properties p WHERE p.location_id = l.id) AS cnt FROM locations l ORDER BY sort_order, name');
$edit = get('edit') ? q_one('SELECT * FROM locations WHERE id = ?', [(int) get('edit')]) : null;
admin_header('Locations', 'locations');
?>
<div class="row g-4">
  <div class="col-xl-7">
    <div class="card">
      <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th></th><th>Location</th><th>Listings</th><th>Order</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><img src="<?= e($r['image'] ? site_image_url($r['image']) : url('assets/img/hero.jpg')) ?>" class="thumb-sm" alt=""></td>
            <td><strong><?= e($r['name']) ?></strong><div class="small text-muted"><?= e($r['description']) ?></div></td>
            <td><?= (int) $r['cnt'] ?></td>
            <td><?= (int) $r['sort_order'] ?></td>
            <td><?= $r['is_active'] ? '<span class="badge text-bg-success">Shown</span>' : '<span class="badge text-bg-secondary">Hidden</span>' ?></td>
            <td class="text-end text-nowrap">
              <a href="<?= url('admin/locations.php?edit=' . $r['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a>
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this location?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button class="btn btn-sm btn-light text-danger"><i class="bi bi-trash"></i></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
  <div class="col-xl-5">
    <form method="post" enctype="multipart/form-data" class="card">
      <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? $edit['id'] : 0 ?>">
      <div class="card-header"><?= $edit ? 'Edit location' : 'Add location' ?></div>
      <div class="card-body row g-3">
        <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?= e($edit['name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Order</label><input type="number" name="sort_order" class="form-control" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></div>
        <div class="col-12"><label class="form-label">Short description</label><input name="description" class="form-control" maxlength="255" value="<?= e($edit['description'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">URL name (optional)</label><input name="slug" class="form-control" value="<?= e($edit['slug'] ?? '') ?>" placeholder="auto from name"></div>
        <div class="col-12"><label class="form-label">Tile picture</label><input type="file" name="image" class="form-control" accept="image/*"></div>
        <div class="col-12 form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="locActive" <?= !$edit || $edit['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="locActive">Show on website</label></div>
      </div>
      <div class="card-footer d-flex justify-content-between">
        <?php if ($edit): ?><a href="<?= url('admin/locations.php') ?>" class="btn btn-light">Cancel</a><?php else: ?><span></span><?php endif; ?>
        <button class="btn btn-primary">Save location</button>
      </div>
    </form>
  </div>
</div>
<?php admin_footer();
