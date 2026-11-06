<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) (get('id') ?: post('id'));
$l = $id ? q_one('SELECT * FROM leases WHERE id = ?', [$id]) : null;
if ($id && !$l) {
    flash('error', 'Lease not found.');
    redirect('admin/leases.php');
}

if (is_post()) {
    csrf_check();
    $data = [
        'property_id' => (int) post('property_id'),
        'tenant_id' => (int) post('tenant_id'),
        'start_date' => valid_date(post('start_date')),
        'end_date' => valid_date(post('end_date')),
        'monthly_rent' => num(post('monthly_rent')),
        'deposit' => num(post('deposit')),
        'advance' => num(post('advance')),
        'due_day' => max(1, min(31, (int) post('due_day'))),
        'status' => in_array(post('status'), ['active', 'upcoming'], true) || ($l && in_array(post('status'), ['ended', 'terminated'], true)) ? post('status') : 'active',
        'notes' => nn(post('notes')),
    ];
    $prop = q_one('SELECT * FROM properties WHERE id = ?', [$data['property_id']]);
    $tenant = q_one('SELECT * FROM tenants WHERE id = ?', [$data['tenant_id']]);
    $error = '';
    if (!$prop || !$tenant) {
        $error = 'Choose a property and a tenant.';
    } elseif (!$data['start_date']) {
        $error = 'Enter the start date.';
    } elseif ($data['end_date'] && $data['end_date'] < $data['start_date']) {
        $error = 'The end date is before the start date.';
    } elseif ($data['status'] === 'active' && q_val("SELECT id FROM leases WHERE property_id = ? AND status = 'active' AND id <> ?", [$data['property_id'], $id])) {
        $error = 'This property already has an active lease. End that lease first.';
    }

    if ($error) {
        flash('error', $error);
        $l = array_merge($l ?: [], $data);
    } else {
        $tName = tenant_name($tenant);
        $period = fmt_date($data['start_date']) . ' – ' . ($data['end_date'] ? fmt_date($data['end_date']) : 'open-ended');
        $details = "Period: $period\nMonthly rent: " . money($data['monthly_rent'], 2) . "\nDeposit: " . money($data['deposit'], 2) . "\nAdvance: " . money($data['advance'], 2);

        if ($l && !empty($l['id'])) {
            $changes = [];
            if ((float) $l['monthly_rent'] !== (float) $data['monthly_rent']) { $changes[] = 'Rent: ' . money($l['monthly_rent'], 2) . ' → ' . money($data['monthly_rent'], 2); }
            if ((string) $l['end_date'] !== (string) $data['end_date']) { $changes[] = 'End date: ' . fmt_date($l['end_date']) . ' → ' . fmt_date($data['end_date']); }
            if ((string) $l['start_date'] !== (string) $data['start_date']) { $changes[] = 'Start date: ' . fmt_date($l['start_date']) . ' → ' . fmt_date($data['start_date']); }
            if ($l['status'] !== $data['status']) { $changes[] = 'Status: ' . $l['status'] . ' → ' . $data['status']; }
            db_update('leases', $data, $id);
            if ($changes) {
                $summary = $l['end_date'] !== $data['end_date'] && $data['end_date'] > (string) $l['end_date'] ? 'Lease renewed / extended' : 'Lease updated';
                log_history($data['property_id'], $data['tenant_id'], 'lease_update', $summary . ' — ' . $tName, implode("\n", $changes));
            }
            audit('update', 'leases', $tName . ' — ' . $prop['title'], $changes);
            flash('success', 'Lease saved.');
        } else {
            $id = db_insert('leases', $data);
            log_history($data['property_id'], $data['tenant_id'], 'lease_start',
                ($data['status'] === 'upcoming' ? 'Lease signed (starts ' . fmt_date($data['start_date']) . ')' : 'Lease started') . ': ' . $tName . ' — ' . $prop['title'],
                $details, $data['start_date'] <= date('Y-m-d') ? $data['start_date'] : date('Y-m-d'));
            audit('insert', 'leases', $tName . ' — ' . $prop['title'], $details);
            flash('success', 'Lease created. You can now upload the signed contract and record payments.');
        }

        // Keep property and tenant statuses in line with the lease
        if ($data['status'] === 'active') {
            if ($prop['status'] !== 'rented') {
                q("UPDATE properties SET status = 'rented' WHERE id = ?", [$prop['id']]);
            }
            if ($tenant['status'] !== 'active') {
                q("UPDATE tenants SET status = 'active' WHERE id = ?", [$tenant['id']]);
            }
        } elseif ($data['status'] === 'upcoming' && $prop['status'] === 'available') {
            q("UPDATE properties SET status = 'reserved' WHERE id = ?", [$prop['id']]);
        } elseif (in_array($data['status'], ['ended', 'terminated'], true)) {
            release_after_lease($prop['id'], $tenant['id']);
        }
        redirect('admin/lease-view.php?id=' . $id);
    }
}

$isEdit = $l && !empty($l['id']);
$v = function ($k, $d = '') use ($l) { return isset($l[$k]) && $l[$k] !== null ? $l[$k] : $d; };
$presetProp = (int) $v('property_id', (int) get('property_id'));
$presetTenant = (int) $v('tenant_id', (int) get('tenant_id'));
$defaultRent = '';
if (!$isEdit && $presetProp) {
    $defaultRent = (float) q_val("SELECT price FROM properties WHERE id = ? AND listing_type = 'rent'", [$presetProp]);
}

admin_header($isEdit ? 'Edit lease' : 'New lease', 'leases');
?>
<a href="<?= $isEdit ? url('admin/lease-view.php?id=' . $l['id']) : url('admin/leases.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> Back</a>
<form method="post" class="card" style="max-width:900px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">
  <div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">Property *</label><select name="property_id" class="form-select" required><option value="">Choose…</option><?= property_options($presetProp) ?></select></div>
    <div class="col-md-6"><label class="form-label">Tenant * <a href="<?= url('admin/tenant-edit.php') ?>" class="small ms-2" target="_blank">+ add new tenant</a></label><select name="tenant_id" class="form-select" required><option value="">Choose…</option><?= tenant_options($presetTenant) ?></select></div>
    <div class="col-md-4"><label class="form-label">Start date *</label><input type="date" name="start_date" class="form-control" required value="<?= e($v('start_date', date('Y-m-d'))) ?>"></div>
    <div class="col-md-4"><label class="form-label">End date</label><input type="date" name="end_date" class="form-control" value="<?= e($v('end_date')) ?>"><div class="form-text">Leave blank for month-to-month.</div></div>
    <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?= select_options($isEdit ? ['active' => 'Active', 'upcoming' => 'Upcoming (signed, not started)', 'ended' => 'Ended', 'terminated' => 'Terminated'] : ['active' => 'Active', 'upcoming' => 'Upcoming (signed, not started)'], $v('status', 'active'), true) ?></select></div>
    <div class="col-md-3"><label class="form-label">Monthly rent</label><input name="monthly_rent" class="form-control" inputmode="decimal" value="<?= e($v('monthly_rent', $defaultRent)) ?>"></div>
    <div class="col-md-3"><label class="form-label">Security deposit</label><input name="deposit" class="form-control" inputmode="decimal" value="<?= e($v('deposit')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Advance rent</label><input name="advance" class="form-control" inputmode="decimal" value="<?= e($v('advance')) ?>"></div>
    <div class="col-md-3"><label class="form-label">Rent due day</label><input type="number" min="1" max="31" name="due_day" class="form-control" value="<?= (int) $v('due_day', 1) ?>"></div>
    <div class="col-12"><label class="form-label">Notes / special terms</label><textarea name="notes" class="form-control" rows="4"><?= e($v('notes')) ?></textarea></div>
    <?php if (!$isEdit): ?><div class="col-12 small text-muted"><i class="bi bi-info-circle"></i> An active lease marks the property as <strong>Rented</strong> and the tenant as <strong>Active</strong>, and is added to both histories.</div><?php endif; ?>
  </div>
  <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save lease</button></div>
</form>
<?php admin_footer();
