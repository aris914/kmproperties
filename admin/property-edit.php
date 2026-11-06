<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) (get('id') ?: post('id'));
$p = $id ? q_one('SELECT * FROM properties WHERE id = ?', [$id]) : null;
if ($id && !$p) {
    flash('error', 'Property not found.');
    redirect('admin/properties.php');
}

$statuses = ['available' => 'Available', 'reserved' => 'Reserved', 'rented' => 'Rented', 'sold' => 'Sold', 'inactive' => 'Inactive (hidden from website)'];

if (is_post()) {
    csrf_check();

    if (post('action') === 'delete' && $p) {
        foreach (q_all('SELECT file FROM property_photos WHERE property_id = ?', [$id]) as $ph) {
            delete_file_safe(PHOTO_DIR . '/' . $ph['file']);
        }
        $docs = q_all("SELECT id, file FROM documents WHERE (entity_type = 'property' AND entity_id = ?)
                       OR (entity_type = 'lease' AND entity_id IN (SELECT id FROM leases WHERE property_id = ?))
                       OR (entity_type = 'maintenance' AND entity_id IN (SELECT id FROM maintenance WHERE property_id = ?))", [$id, $id, $id]);
        foreach ($docs as $d) {
            delete_file_safe(DOC_DIR . '/' . $d['file']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        }
        q('DELETE FROM history WHERE property_id = ? AND tenant_id IS NULL', [$id]);
        q('DELETE FROM properties WHERE id = ?', [$id]);
        audit('delete', 'properties', $p['title'], trim($p['code'] . ' ' . $p['building']));
        flash('success', 'Property “' . $p['title'] . '” and its photos, leases, repair records and documents were deleted.');
        redirect('admin/properties.php');
    }

    $data = [
        'code' => nn(post('code')),
        'title' => post('title'),
        'listing_type' => post('listing_type') === 'sale' ? 'sale' : 'rent',
        'property_type' => in_array(post('property_type'), property_types(), true) ? post('property_type') : 'Condominium',
        'unit_type' => nn(post('unit_type')),
        'location_id' => (int) post('location_id') ?: null,
        'building' => nn(post('building')),
        'unit_no' => nn(post('unit_no')),
        'address' => nn(post('address')),
        'price' => num(post('price')),
        'price_period' => post('listing_type') === 'sale' ? null : (nn(post('price_period')) ?: 'month'),
        'bedrooms' => max(0, min(20, (int) post('bedrooms'))),
        'bathrooms' => max(0, min(20, (int) post('bathrooms'))),
        'floor_area' => num(post('floor_area')) ?: null,
        'floor_level' => nn(post('floor_level')),
        'parking' => max(0, min(20, (int) post('parking'))),
        'furnishing' => in_array(post('furnishing'), ['Unfurnished', 'Semi-furnished', 'Fully furnished'], true) ? post('furnishing') : 'Unfurnished',
        'description' => nn(post('description')),
        'amenities' => nn(post('amenities')),
        'status' => isset($statuses[post('status')]) ? post('status') : 'available',
        'is_featured' => post('is_featured') ? 1 : 0,
        'owner_name' => nn(post('owner_name')),
        'owner_phone' => nn(post('owner_phone')),
        'owner_email' => nn(post('owner_email')),
        'internal_notes' => nn(post('internal_notes')),
    ];

    if ($data['title'] === '') {
        flash('error', 'Please enter a title.');
        $p = array_merge($p ?: [], $data);
    } else {
        $data['slug'] = unique_slug('properties', post('slug') ?: $data['title'], $id);
        if ($p) {
            // Describe what changed for the property history
            $labels = ['price' => 'Price', 'status' => 'Status', 'listing_type' => 'Listing type', 'furnishing' => 'Furnishing', 'owner_name' => 'Owner', 'title' => 'Title'];
            $changes = [];
            foreach ($labels as $k => $label) {
                if ((string) $p[$k] !== (string) $data[$k] && !($k === 'price' && (float) $p[$k] === (float) $data[$k])) {
                    $old = $k === 'price' ? money($p[$k]) : ($p[$k] ?: '—');
                    $new = $k === 'price' ? money($data[$k]) : ($data[$k] ?: '—');
                    $changes[] = "$label: $old → $new";
                }
            }
            db_update('properties', $data, $id);
            if ($p['status'] !== $data['status']) {
                log_history($id, null, 'status', 'Status changed to ' . ucfirst($data['status']), implode("\n", $changes));
            } elseif ($changes) {
                log_history($id, null, 'updated', 'Listing details updated', implode("\n", $changes));
            }
            audit('update', 'properties', $data['title'], $changes);
            flash('success', 'Property saved.');
        } else {
            $id = db_insert('properties', $data);
            log_history($id, null, 'created', 'Property added (' . ($data['listing_type'] === 'rent' ? 'for rent' : 'for sale') . ')', 'Price: ' . money($data['price']));
            audit('insert', 'properties', $data['title'], ($data['listing_type'] === 'rent' ? 'For rent' : 'For sale') . ', ' . money($data['price']));
            flash('success', 'Property created. Now add photos and documents.');
        }

        // Photos uploaded together with the form
        $files = files_list('photos');
        if ($files) {
            $ok = 0;
            $hasCover = (bool) q_val('SELECT COUNT(*) FROM property_photos WHERE property_id = ? AND is_cover = 1', [$id]);
            $sort = (int) q_val('SELECT COALESCE(MAX(sort_order),0) FROM property_photos WHERE property_id = ?', [$id]);
            foreach ($files as $file) {
                try {
                    $name = save_image_upload($file, PHOTO_DIR);
                    db_insert('property_photos', ['property_id' => $id, 'file' => $name, 'is_cover' => $hasCover ? 0 : 1, 'sort_order' => ++$sort]);
                    $hasCover = true;
                    $ok++;
                } catch (RuntimeException $ex) {
                    flash('error', $ex->getMessage());
                }
            }
            if ($ok) {
                log_history($id, null, 'photo', $ok . ' photo' . ($ok > 1 ? 's' : '') . ' added');
            }
        }
        redirect('admin/property-view.php?id=' . $id);
    }
}

$locations = q_all('SELECT id, name FROM locations ORDER BY sort_order, name');
$v = function ($k, $d = '') use ($p) { return isset($p[$k]) && $p[$k] !== null ? $p[$k] : $d; };

admin_header($p && !empty($p['id']) ? 'Edit property' : 'Add property', 'properties');
?>
<a href="<?= $p && !empty($p['id']) ? url('admin/property-view.php?id=' . $p['id']) : url('admin/properties.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> Back</a>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4">
        <div class="card-header">Listing</div>
        <div class="card-body row g-3">
          <div class="col-md-8"><label class="form-label">Title *</label><input name="title" class="form-control" required maxlength="200" value="<?= e($v('title')) ?>" placeholder="e.g. Fully Furnished 1BR near Ayala Avenue"></div>
          <div class="col-md-4"><label class="form-label">Listing code</label><input name="code" class="form-control" maxlength="30" value="<?= e($v('code')) ?>" placeholder="KM-R001"></div>
          <div class="col-md-4"><label class="form-label">Listing type</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="listing_type" id="ltRent" value="rent" <?= $v('listing_type', 'rent') === 'rent' ? 'checked' : '' ?>><label class="btn btn-outline-primary" for="ltRent">For Rent</label>
              <input type="radio" class="btn-check" name="listing_type" id="ltSale" value="sale" <?= $v('listing_type') === 'sale' ? 'checked' : '' ?>><label class="btn btn-outline-primary" for="ltSale">For Sale</label>
            </div>
          </div>
          <div class="col-md-4"><label class="form-label">Price (<?= e(setting('currency', '₱')) ?>)</label><input name="price" class="form-control" inputmode="decimal" value="<?= $v('price') !== '' ? e((float) $v('price')) : '' ?>"></div>
          <div class="col-md-4 rent-only"><label class="form-label">Per</label><select name="price_period" class="form-select"><?= select_options(['month' => 'Month', 'year' => 'Year', 'night' => 'Night', 'week' => 'Week'], $v('price_period', 'month'), true) ?></select></div>
          <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?= select_options($statuses, $v('status', 'available'), true) ?></select></div>
          <div class="col-md-4"><label class="form-label">Property type</label><select name="property_type" class="form-select"><?= select_options(property_types(), $v('property_type', 'Condominium')) ?></select></div>
          <div class="col-md-4"><label class="form-label">Unit type</label><select name="unit_type" class="form-select"><option value="">—</option><?= select_options(unit_types(), $v('unit_type')) ?></select></div>
          <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_featured" value="1" id="feat" <?= $v('is_featured') ? 'checked' : '' ?>><label class="form-check-label" for="feat">Featured — show first on the homepage</label></div></div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Location</div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label">City / area</label><select name="location_id" class="form-select"><option value="">—</option><?php foreach ($locations as $l): ?><option value="<?= $l['id'] ?>"<?= (int) $v('location_id') === (int) $l['id'] ? ' selected' : '' ?>><?= e($l['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-5"><label class="form-label">Building / project</label><input name="building" class="form-control" maxlength="150" value="<?= e($v('building')) ?>"></div>
          <div class="col-md-3"><label class="form-label">Unit no. <small class="text-muted">(private)</small></label><input name="unit_no" class="form-control" maxlength="40" value="<?= e($v('unit_no')) ?>"></div>
          <div class="col-12"><label class="form-label">Address shown on website</label><input name="address" class="form-control" maxlength="255" value="<?= e($v('address')) ?>"></div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">Details</div>
        <div class="card-body row g-3">
          <div class="col-6 col-md-2"><label class="form-label">Bedrooms</label><input type="number" min="0" name="bedrooms" class="form-control" value="<?= (int) $v('bedrooms', 0) ?>"><div class="form-text">0 = studio</div></div>
          <div class="col-6 col-md-2"><label class="form-label">Bathrooms</label><input type="number" min="0" name="bathrooms" class="form-control" value="<?= (int) $v('bathrooms', 1) ?>"></div>
          <div class="col-6 col-md-2"><label class="form-label">Parking</label><input type="number" min="0" name="parking" class="form-control" value="<?= (int) $v('parking', 0) ?>"></div>
          <div class="col-6 col-md-2"><label class="form-label">Area (sqm)</label><input name="floor_area" class="form-control" inputmode="decimal" value="<?= $v('floor_area') ? e((float) $v('floor_area')) : '' ?>"></div>
          <div class="col-6 col-md-2"><label class="form-label">Floor</label><input name="floor_level" class="form-control" maxlength="20" value="<?= e($v('floor_level')) ?>"></div>
          <div class="col-6 col-md-2"><label class="form-label">Furnishing</label><select name="furnishing" class="form-select"><?= select_options(['Unfurnished', 'Semi-furnished', 'Fully furnished'], $v('furnishing', 'Unfurnished')) ?></select></div>
          <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="6"><?= e($v('description')) ?></textarea></div>
          <div class="col-12"><label class="form-label">Amenities &amp; features <small class="text-muted">(one per line)</small></label><textarea name="amenities" class="form-control" rows="5" placeholder="Swimming pool&#10;Gym&#10;24/7 security"><?= e($v('amenities')) ?></textarea></div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <?php if (!$p || empty($p['id'])): ?>
      <div class="card mb-4">
        <div class="card-header">Photos</div>
        <div class="card-body">
          <input type="file" name="photos[]" class="form-control" accept="image/*" multiple id="photoInput">
          <div class="form-text">Select several at once. The first becomes the cover. JPG/PNG/WebP, up to <?= (int) MAX_PHOTO_MB ?> MB each.</div>
          <div class="photo-preview mt-2" id="photoPreview"></div>
        </div>
      </div>
      <?php endif; ?>
      <div class="card mb-4">
        <div class="card-header">Owner <small class="text-muted">(private)</small></div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label">Owner name</label><input name="owner_name" class="form-control" value="<?= e($v('owner_name')) ?>"></div>
          <div class="col-12"><label class="form-label">Phone</label><input name="owner_phone" class="form-control" value="<?= e($v('owner_phone')) ?>"></div>
          <div class="col-12"><label class="form-label">Email</label><input type="email" name="owner_email" class="form-control" value="<?= e($v('owner_email')) ?>"></div>
          <div class="col-12"><label class="form-label">Internal notes</label><textarea name="internal_notes" class="form-control" rows="4"><?= e($v('internal_notes')) ?></textarea></div>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header">Web address</div>
        <div class="card-body"><label class="form-label small">URL name (optional)</label><input name="slug" class="form-control" value="<?= e($v('slug')) ?>" placeholder="auto from title"></div>
      </div>
    </div>
  </div>
  <div class="sticky-save d-flex justify-content-between">
    <?php if ($p && !empty($p['id'])): ?>
      <button type="submit" form="deleteForm" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete property</button>
    <?php else: ?><span></span><?php endif; ?>
    <button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>Save property</button>
  </div>
</form>
<?php if ($p && !empty($p['id'])): ?>
<form method="post" id="deleteForm" onsubmit="return confirm('Delete this property together with ALL its photos, leases, payments, repair records and documents? This cannot be undone. (Tip: set the status to Inactive to just hide it.)')">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="action" value="delete">
</form>
<?php endif; ?>
<?php admin_footer();
