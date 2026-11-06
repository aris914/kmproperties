<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) (get('id') ?: post('id'));
$m = $id ? q_one('SELECT * FROM maintenance WHERE id = ?', [$id]) : null;
if ($id && !$m) {
    flash('error', 'Job not found.');
    redirect('admin/maintenance.php');
}
$categories = ['repair' => 'Repair', 'maintenance' => 'Preventive maintenance', 'inspection' => 'Inspection', 'renovation' => 'Renovation / improvement', 'cleaning' => 'Cleaning', 'other' => 'Other'];
$statuses = ['open' => 'Open', 'scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

if (is_post()) {
    csrf_check();
    if (post('action') === 'delete' && $m) {
        foreach (q_all("SELECT id, file FROM documents WHERE entity_type = 'maintenance' AND entity_id = ?", [$id]) as $d) {
            delete_file_safe(DOC_DIR . '/' . $d['file']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        }
        q('DELETE FROM maintenance WHERE id = ?', [$id]);
        log_history($m['property_id'], $m['tenant_id'], 'maintenance', 'Repair record deleted: ' . $m['title']);
        audit('delete', 'maintenance', $m['title'], 'Property #' . $m['property_id']);
        flash('success', 'Job deleted.');
        redirect('admin/property-view.php?id=' . $m['property_id'] . '&tab=maintenance');
    }

    $data = [
        'property_id' => (int) post('property_id'),
        'tenant_id' => (int) post('tenant_id') ?: null,
        'title' => post('title'),
        'category' => isset($categories[post('category')]) ? post('category') : 'repair',
        'area' => nn(post('area')),
        'priority' => in_array(post('priority'), ['low', 'normal', 'high', 'urgent'], true) ? post('priority') : 'normal',
        'status' => isset($statuses[post('status')]) ? post('status') : 'open',
        'reported_by' => nn(post('reported_by')),
        'reported_on' => valid_date(post('reported_on')) ?: date('Y-m-d'),
        'scheduled_on' => valid_date(post('scheduled_on')),
        'completed_on' => valid_date(post('completed_on')),
        'contractor' => nn(post('contractor')),
        'contractor_phone' => nn(post('contractor_phone')),
        'cost' => num(post('cost')),
        'charged_to' => in_array(post('charged_to'), ['owner', 'tenant', 'company', 'warranty'], true) ? post('charged_to') : 'owner',
        'description' => nn(post('description')),
        'resolution' => nn(post('resolution')),
    ];
    if ($data['status'] === 'completed' && !$data['completed_on']) {
        $data['completed_on'] = date('Y-m-d');
    }
    $prop = q_one('SELECT id, title FROM properties WHERE id = ?', [$data['property_id']]);
    if (!$prop || $data['title'] === '') {
        flash('error', 'Choose the property and describe the job.');
        $m = array_merge($m ?: [], $data);
    } else {
        $catLabel = $categories[$data['category']];
        if ($m && !empty($m['id'])) {
            db_update('maintenance', $data, $id);
            if ($m['status'] !== $data['status']) {
                if ($data['status'] === 'completed') {
                    log_history($data['property_id'], $data['tenant_id'], 'maintenance_done', 'Completed: ' . $data['title'],
                        trim(($data['contractor'] ? 'By ' . $data['contractor'] . '. ' : '') . ($data['cost'] > 0 ? 'Cost ' . money($data['cost'], 2) . ' (charged to ' . $data['charged_to'] . '). ' : '') . (string) $data['resolution']),
                        $data['completed_on']);
                } else {
                    log_history($data['property_id'], $data['tenant_id'], 'maintenance', $catLabel . ' “' . $data['title'] . '” is now ' . strtolower($statuses[$data['status']]),
                        $data['status'] === 'scheduled' && $data['scheduled_on'] ? 'Scheduled for ' . fmt_date($data['scheduled_on']) . ($data['contractor'] ? ' with ' . $data['contractor'] : '') : null);
                }
            } elseif ((float) $m['cost'] !== (float) $data['cost']) {
                log_history($data['property_id'], $data['tenant_id'], 'maintenance', 'Cost updated for “' . $data['title'] . '”: ' . money($m['cost'], 2) . ' → ' . money($data['cost'], 2));
            }
            audit('update', 'maintenance', $data['title'] . ' — ' . $prop['title'], audit_diff($m, $data, ['title' => 'Job', 'status' => 'Status', 'priority' => 'Priority', 'cost' => 'Cost', 'contractor' => 'Contractor', 'completed_on' => 'Completed', 'charged_to' => 'Charged to']));
            flash('success', 'Job saved.');
        } else {
            $id = db_insert('maintenance', $data);
            log_history($data['property_id'], $data['tenant_id'], $data['status'] === 'completed' ? 'maintenance_done' : 'maintenance',
                $catLabel . ' logged: ' . $data['title'],
                trim(($data['area'] ? 'Area: ' . $data['area'] . "\n" : '') . (string) $data['description']), $data['reported_on']);
            audit('insert', 'maintenance', $data['title'] . ' — ' . $prop['title'], $catLabel . ', ' . $data['status']);
            flash('success', 'Job logged. You can attach quotes, receipts and photos below.');
        }
        redirect('admin/maintenance-edit.php?id=' . $id);
    }
}

$isEdit = $m && !empty($m['id']);
$v = function ($k, $d = '') use ($m) { return isset($m[$k]) && $m[$k] !== null ? $m[$k] : $d; };
$presetProp = (int) $v('property_id', (int) get('property_id'));
$presetTenant = (int) $v('tenant_id', (int) get('tenant_id'));
if (!$isEdit && $presetProp && !$presetTenant) {
    // Pre-select the current tenant of the property
    $presetTenant = (int) q_val("SELECT tenant_id FROM leases WHERE property_id = ? AND status = 'active' LIMIT 1", [$presetProp]);
}

admin_header($isEdit ? 'Repair / maintenance job' : 'Log repair / maintenance', 'maintenance');
?>
<a href="<?= $presetProp ? url('admin/property-view.php?id=' . $presetProp . '&tab=maintenance') : url('admin/maintenance.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> Back</a>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4">
        <div class="card-header">Job</div>
        <div class="card-body row g-3">
          <div class="col-md-7"><label class="form-label">Property *</label><select name="property_id" class="form-select" required><option value="">Choose…</option><?= property_options($presetProp) ?></select></div>
          <div class="col-md-5"><label class="form-label">Tenant (if reported by / affects a tenant)</label><select name="tenant_id" class="form-select"><option value="">— none —</option><?= tenant_options($presetTenant) ?></select></div>
          <div class="col-md-8"><label class="form-label">What needs to be done? *</label><input name="title" class="form-control" required maxlength="200" value="<?= e($v('title')) ?>" placeholder="e.g. Aircon not cooling — master bedroom"></div>
          <div class="col-md-4"><label class="form-label">Type</label><select name="category" class="form-select"><?= select_options($categories, $v('category', 'repair'), true) ?></select></div>
          <div class="col-md-4"><label class="form-label">Area / item</label><input name="area" class="form-control" maxlength="80" list="areas" value="<?= e($v('area')) ?>">
            <datalist id="areas"><option>Aircon</option><option>Plumbing</option><option>Electrical</option><option>Kitchen</option><option>Bathroom</option><option>Bedroom</option><option>Living room</option><option>Appliances</option><option>Doors / locks</option><option>Windows</option><option>Walls / paint</option><option>Flooring</option><option>Pest control</option><option>Whole unit</option></datalist>
          </div>
          <div class="col-md-4"><label class="form-label">Priority</label><select name="priority" class="form-select"><?= select_options(['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'], $v('priority', 'normal'), true) ?></select></div>
          <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?= select_options($statuses, $v('status', 'open'), true) ?></select></div>
          <div class="col-12"><label class="form-label">Problem / work description</label><textarea name="description" class="form-control" rows="4"><?= e($v('description')) ?></textarea></div>
          <div class="col-12"><label class="form-label">Work done / resolution</label><textarea name="resolution" class="form-control" rows="3"><?= e($v('resolution')) ?></textarea></div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card mb-4">
        <div class="card-header">Dates</div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label">Reported by</label><input name="reported_by" class="form-control" value="<?= e($v('reported_by')) ?>" placeholder="Tenant, owner, admin, inspection…"></div>
          <div class="col-6"><label class="form-label">Reported on</label><input type="date" name="reported_on" class="form-control" value="<?= e($v('reported_on', date('Y-m-d'))) ?>"></div>
          <div class="col-6"><label class="form-label">Scheduled</label><input type="date" name="scheduled_on" class="form-control" value="<?= e($v('scheduled_on')) ?>"></div>
          <div class="col-6"><label class="form-label">Completed</label><input type="date" name="completed_on" class="form-control" value="<?= e($v('completed_on')) ?>"></div>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header">Contractor &amp; cost</div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label">Contractor / technician</label><input name="contractor" class="form-control" value="<?= e($v('contractor')) ?>"></div>
          <div class="col-12"><label class="form-label">Contractor phone</label><input name="contractor_phone" class="form-control" value="<?= e($v('contractor_phone')) ?>"></div>
          <div class="col-6"><label class="form-label">Cost</label><input name="cost" class="form-control" inputmode="decimal" value="<?= $v('cost') !== '' && (float) $v('cost') ? e((float) $v('cost')) : '' ?>"></div>
          <div class="col-6"><label class="form-label">Charged to</label><select name="charged_to" class="form-select"><?= select_options(['owner' => 'Owner', 'tenant' => 'Tenant', 'company' => 'K&M', 'warranty' => 'Warranty / developer'], $v('charged_to', 'owner'), true) ?></select></div>
        </div>
      </div>
    </div>
  </div>
  <div class="sticky-save d-flex justify-content-between mb-4">
    <?php if ($isEdit): ?><button type="submit" form="delForm" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete</button><?php else: ?><span></span><?php endif; ?>
    <button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>Save job</button>
  </div>
</form>

<?php if ($isEdit): ?>
<div class="card mb-4">
  <div class="card-header">Quotes, receipts &amp; before/after photos</div>
  <div class="card-body"><?php documents_panel('maintenance', $id, url('admin/maintenance-edit.php?id=' . $id), ['Quotation', 'Receipt / invoice', 'Before photo', 'After photo', 'Report', 'Warranty', 'Other']); ?></div>
</div>
<form method="post" id="delForm" onsubmit="return confirm('Delete this job and its files?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"></form>
<?php endif; ?>
<?php admin_footer();
