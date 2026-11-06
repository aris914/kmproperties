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
$docCategories = ['Proof of income', 'Certificate of employment', 'Payslip', 'Bank statement', 'NBI / police clearance', 'Barangay clearance', 'Application form', 'Signed contract', 'Valid ID', 'Other'];

/** Saves the ID photos (front / back replace the previous ones) and other documents sent with the form. */
function tenant_save_uploads($tid, array $data)
{
    $u = current_user();
    $saved = [];
    $errors = [];
    $idName = $data['id_type'] ?: 'ID';
    foreach (['front' => 'Front', 'back' => 'Back'] as $side => $label) {
        // "Take photo" (camera) and "Upload" are two inputs for the same slot; use whichever was filled
        $files = files_list('id_' . $side) ?: files_list('id_' . $side . '_file');
        if (!$files) {
            continue;
        }
        try {
            list($stored, $mime) = save_document_upload($files[0]);
            $old = tenant_docs($tid)[$side];
            if ($old) { // replace the previous photo of this side
                delete_file_safe(DOC_DIR . '/' . $old['file']);
                q('DELETE FROM documents WHERE id = ?', [$old['id']]);
            }
            db_insert('documents', [
                'entity_type' => 'tenant', 'entity_id' => $tid, 'title' => 'Valid ID — ' . $idName . ' (' . $side . ')', 'category' => 'Valid ID',
                'file' => $stored, 'original_name' => mb_substr(basename($files[0]['name']), 0, 255), 'mime' => $mime,
                'size' => (int) $files[0]['size'], 'uploaded_by' => $u['id'],
            ]);
            $saved[] = $idName . ' (' . $side . ')' . ($old ? ', replaced' : '');
        } catch (RuntimeException $ex) {
            $errors[] = $label . ' of ID: ' . $ex->getMessage();
        }
    }
    $cat = post('doc_category');
    $title = mb_substr(post('doc_title'), 0, 150);
    $docs = array_slice(files_list('docs'), 0, 15);
    foreach ($docs as $file) {
        try {
            list($stored, $mime) = save_document_upload($file);
            $base = pathinfo($file['name'], PATHINFO_FILENAME);
            $docTitle = $title !== '' ? $title . (count($docs) > 1 ? ' — ' . $base : '') : $base;
            db_insert('documents', [
                'entity_type' => 'tenant', 'entity_id' => $tid, 'title' => mb_substr($docTitle, 0, 200),
                'category' => $cat !== '' ? mb_substr($cat, 0, 60) : 'Other', 'file' => $stored,
                'original_name' => mb_substr(basename($file['name']), 0, 255), 'mime' => $mime, 'size' => (int) $file['size'], 'uploaded_by' => $u['id'],
            ]);
            $saved[] = $docTitle . ' (' . ($cat ?: 'Other') . ')';
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }
    if ($saved) {
        log_history(null, $tid, 'document', count($saved) . ' document' . (count($saved) > 1 ? 's' : '') . ' uploaded', implode("\n", $saved));
        audit('insert', 'documents', tenant_name($data), implode("\n", $saved));
        flash('success', count($saved) . ' file' . (count($saved) > 1 ? 's' : '') . ' uploaded: ' . implode(', ', $saved) . '.');
    }
    if ($errors) {
        flash('error', 'Not uploaded — ' . implode('; ', $errors));
    }
}

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
        'id_expiry' => valid_date(post('id_expiry')),
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
        tenant_save_uploads($id, $data);
        redirect('admin/tenant-view.php?id=' . $id);
    }
}

$v = function ($k, $d = '') use ($t) { return isset($t[$k]) && $t[$k] !== null ? $t[$k] : $d; };
$isEdit = $t && !empty($t['id']);
$tdocs = $isEdit ? tenant_docs($t['id']) : ['front' => null, 'back' => null, 'other' => []];
admin_header($isEdit ? 'Edit tenant' : 'Add tenant', 'tenants');
?>
<a href="<?= $isEdit ? url('admin/tenant-view.php?id=' . $t['id']) : url('admin/tenants.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> Back</a>
<form method="post" enctype="multipart/form-data">
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
        </div>
      </div>

      <div class="card mb-4" id="validId">
        <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-person-vcard me-1"></i>Valid ID</span><?= id_expiry_badge($v('id_expiry')) ?></div>
        <div class="card-body">
          <div class="row g-3 mb-3">
            <div class="col-md-5"><label class="form-label">ID type</label><select name="id_type" class="form-select"><option value="">—</option><?= select_options(['Passport', "Driver's License", 'UMID', 'PhilSys National ID', 'SSS ID', 'PRC ID', 'Postal ID', 'Company ID', 'Other'], $v('id_type')) ?></select></div>
            <div class="col-md-4"><label class="form-label">ID number</label><input name="id_number" class="form-control" value="<?= e($v('id_number')) ?>"></div>
            <div class="col-md-3"><label class="form-label">Expiry date</label><input type="date" name="id_expiry" class="form-control" value="<?= e($v('id_expiry')) ?>"></div>
          </div>
          <div class="id-slots">
            <?php foreach (['front' => 'Front of ID', 'back' => 'Back of ID'] as $side => $label): $d = $tdocs[$side]; ?>
              <div class="id-slot">
                <h3><?= $label ?><?php if ($d): ?><button type="submit" form="docDel<?= $d['id'] ?>" class="btn btn-sm btn-link text-danger p-0">Remove</button><?php endif; ?></h3>
                <div id="pv_id_<?= $side ?>"><?= $d ? doc_thumb($d) : '<div class="id-thumb empty"><i class="bi bi-person-vcard"></i>No photo yet</div>' ?></div>
                <div class="id-pick">
                  <label class="btn btn-sm btn-navy"><i class="bi bi-camera me-1"></i>Take photo<input type="file" name="id_<?= $side ?>" accept="image/*" capture="environment" data-resize data-preview="#pv_id_<?= $side ?>" data-pair="id_<?= $side ?>_file"></label>
                  <label class="btn btn-sm btn-light"><i class="bi bi-upload me-1"></i><?= $d ? 'Replace' : 'Upload' ?><input type="file" name="id_<?= $side ?>_file" accept="image/jpeg,image/png,image/webp,application/pdf" data-resize data-preview="#pv_id_<?= $side ?>" data-pair="id_<?= $side ?>"></label>
                </div>
                <?php if ($d): ?><div class="hint mt-1"><?= e($d['original_name']) ?> · <?= fmt_date($d['created_at']) ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <p class="hint mt-2 mb-0">JPG, PNG, WebP or PDF. On a phone, “Take photo” opens the camera. Photos are made smaller before upload and kept private — only signed-in admins can open them. A new photo replaces the old one when you click Save tenant.</p>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header"><i class="bi bi-folder2-open me-1"></i>Other documents</div>
        <div class="card-body">
          <?php if ($tdocs['other']): ?>
            <div class="mb-3">
              <?php foreach ($tdocs['other'] as $d): ?>
                <div class="doc-mini">
                  <?= doc_thumb($d) ?>
                  <div class="flex-fill"><a href="<?= url('admin/download.php?id=' . $d['id']) ?>" target="_blank"><?= e($d['title']) ?></a><div class="hint"><?= e($d['category']) ?> · <?= human_size($d['size']) ?> · <?= fmt_date($d['created_at']) ?></div></div>
                  <a class="btn btn-sm btn-light" href="<?= url('admin/download.php?id=' . $d['id'] . '&dl=1') ?>" title="Download"><i class="bi bi-download"></i></a>
                  <button type="submit" form="docDel<?= $d['id'] ?>" class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="row g-2 align-items-end upload-box">
            <div class="col-md-5"><label class="form-label">Add files (several at once)</label><input type="file" name="docs[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.csv" data-resize></div>
            <div class="col-md-4"><label class="form-label">Category</label><select name="doc_category" class="form-select"><?= select_options($docCategories, 'Proof of income') ?></select></div>
            <div class="col-md-3"><label class="form-label">Title (optional)</label><input name="doc_title" class="form-control" maxlength="150" placeholder="file name"></div>
          </div>
          <p class="hint mt-2 mb-0">Uploaded when you click Save tenant. PDF, Word, Excel or images, up to <?= (int) (defined('MAX_DOC_MB') ? MAX_DOC_MB : 10) ?> MB each.</p>
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
<?php if ($isEdit): foreach (array_merge(array_filter([$tdocs['front'], $tdocs['back']]), $tdocs['other']) as $d): ?>
<form method="post" action="<?= url('admin/document-action.php') ?>" id="docDel<?= $d['id'] ?>" class="d-none" onsubmit="return confirm('Delete this file permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="return" value="<?= e(url('admin/tenant-edit.php?id=' . $t['id'])) ?>"></form>
<?php endforeach; endif; ?>
<?php if ($isEdit): ?>
<form method="post" id="delForm" onsubmit="return confirm('Delete this tenant with all their leases, payments and documents? This cannot be undone. (Tip: set the status to Former to keep the history.)')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $t['id'] ?>"><input type="hidden" name="action" value="delete"></form>
<?php endif; ?>
<?php admin_footer();
