<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) (get('id') ?: post('id'));
$t = $id ? q_one('SELECT * FROM tenants WHERE id = ?', [$id]) : null;
if ($id && !$t) {
    flash('error', 'Tenant not found.');
    redirect('admin/tenants.php');
}
$statuses = ['prospect' => 'Prospect / applicant', 'active' => 'Active', 'former' => 'Former', 'blacklisted' => 'Blacklisted'];

if (is_post()) {
    csrf_check();
    if (post('action') === 'delete' && $t) {
        if (q_val("SELECT COUNT(*) FROM leases WHERE tenant_id = ? AND status = 'active'", [$id])) {
            flash('error', 'End the tenant’s active lease before deleting them. (Tip: set the status to Former instead of deleting, to keep their history.)');
            redirect('admin/tenant-view.php?id=' . $id);
        }
        $docs = q_all("SELECT id, file FROM documents WHERE (entity_type = 'tenant' AND entity_id = ?) OR (entity_type = 'lease' AND entity_id IN (SELECT id FROM leases WHERE tenant_id = ?))", [$id, $id]);
        foreach ($docs as $d) {
            delete_file_safe(DOC_DIR . '/' . $d['file']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        }
        q('DELETE FROM history WHERE tenant_id = ? AND property_id IS NULL', [$id]);
        q('UPDATE history SET tenant_id = NULL WHERE tenant_id = ?', [$id]);
        q('DELETE FROM tenants WHERE id = ?', [$id]);
        audit('delete', 'tenants', tenant_name($t));
        flash('success', 'Tenant deleted.');
        redirect('admin/tenants.php');
    }

    $data = [
        'first_name' => post('first_name'),
        'last_name' => post('last_name'),
        'email' => nn(post('email')),
        'phone' => nn(post('phone')),
        'birth_date' => valid_date(post('birth_date')),
        'occupation' => nn(post('occupation')),
        'employer' => nn(post('employer')),
        'id_type' => nn(post('id_type')),
        'id_number' => nn(post('id_number')),
        'address' => nn(post('address')),
        'emergency_name' => nn(post('emergency_name')),
        'emergency_phone' => nn(post('emergency_phone')),
        'emergency_relation' => nn(post('emergency_relation')),
        'status' => isset($statuses[post('status')]) ? post('status') : 'prospect',
        'notes' => nn(post('notes')),
    ];
    if ($data['first_name'] === '' || $data['last_name'] === '') {
        flash('error', 'First and last name are required.');
        $t = array_merge($t ?: [], $data);
    } elseif ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        flash('error', 'That email address is not valid.');
        $t = array_merge($t ?: [], $data);
    } else {
        if ($t) {
            db_update('tenants', $data, $id);
            if ($t['status'] !== $data['status']) {
                log_history(null, $id, 'status', 'Tenant status changed to ' . $statuses[$data['status']]);
            } else {
                log_history(null, $id, 'updated', 'Tenant details updated');
            }
            audit('update', 'tenants', tenant_name($data), audit_diff($t, $data, ['first_name' => 'First name', 'last_name' => 'Last name', 'status' => 'Status', 'phone' => 'Phone', 'email' => 'Email', 'id_number' => 'ID number', 'address' => 'Address']));
            flash('success', 'Tenant saved.');
        } else {
            $id = db_insert('tenants', $data);
            log_history(null, $id, 'created', 'Tenant record created');
            audit('insert', 'tenants', tenant_name($data));
            flash('success', 'Tenant added.');
        }
        redirect('admin/tenant-view.php?id=' . $id);
    }
}

$v = function ($k, $d = '') use ($t) { return isset($t[$k]) && $t[$k] !== null ? $t[$k] : $d; };
$isEdit = $t && !empty($t['id']);
admin_header($isEdit ? 'Edit tenant' : 'Add tenant', 'tenants');
?>
<a href="<?= $isEdit ? url('admin/tenant-view.php?id=' . $t['id']) : url('admin/tenants.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> Back</a>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card mb-4">
        <div class="card-header">Personal details</div>
        <div class="card-body row g-3">
          <div class="col-md-5"><label class="form-label">First name *</label><input name="first_name" class="form-control" required value="<?= e($v('first_name')) ?>"></div>
          <div class="col-md-5"><label class="form-label">Last name *</label><input name="last_name" class="form-control" required value="<?= e($v('last_name')) ?>"></div>
          <div class="col-md-2"><label class="form-label">Status</label><select name="status" class="form-select"><?= select_options($statuses, $v('status', 'prospect'), true) ?></select></div>
          <div class="col-md-4"><label class="form-label">Mobile</label><input name="phone" class="form-control" value="<?= e($v('phone')) ?>"></div>
          <div class="col-md-5"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($v('email')) ?>"></div>
          <div class="col-md-3"><label class="form-label">Birth date</label><input type="date" name="birth_date" class="form-control" value="<?= e($v('birth_date')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Occupation</label><input name="occupation" class="form-control" value="<?= e($v('occupation')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Employer / company</label><input name="employer" class="form-control" value="<?= e($v('employer')) ?>"></div>
          <div class="col-12"><label class="form-label">Permanent / previous address</label><input name="address" class="form-control" value="<?= e($v('address')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Valid ID type</label><select name="id_type" class="form-select"><option value="">—</option><?= select_options(['Passport', "Driver's License", 'UMID', 'PhilSys National ID', 'SSS ID', 'PRC ID', 'Postal ID', 'Company ID', 'Other'], $v('id_type')) ?></select></div>
          <div class="col-md-6"><label class="form-label">ID number</label><input name="id_number" class="form-control" value="<?= e($v('id_number')) ?>"></div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card mb-4">
        <div class="card-header">Emergency contact</div>
        <div class="card-body row g-3">
          <div class="col-12"><label class="form-label">Name</label><input name="emergency_name" class="form-control" value="<?= e($v('emergency_name')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input name="emergency_phone" class="form-control" value="<?= e($v('emergency_phone')) ?>"></div>
          <div class="col-md-6"><label class="form-label">Relationship</label><input name="emergency_relation" class="form-control" value="<?= e($v('emergency_relation')) ?>"></div>
        </div>
      </div>
      <div class="card mb-4">
        <div class="card-header">Notes</div>
        <div class="card-body"><textarea name="notes" class="form-control" rows="6"><?= e($v('notes')) ?></textarea></div>
      </div>
    </div>
  </div>
  <div class="sticky-save d-flex justify-content-between">
    <?php if ($isEdit): ?><button type="submit" form="delForm" class="btn btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete tenant</button><?php else: ?><span></span><?php endif; ?>
    <button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>Save tenant</button>
  </div>
</form>
<?php if ($isEdit): ?>
<form method="post" id="delForm" onsubmit="return confirm('Delete this tenant with all their leases, payments and documents? This cannot be undone. (Tip: set the status to Former to keep the history.)')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="action" value="delete"></form>
<?php endif; ?>
<?php admin_footer();
