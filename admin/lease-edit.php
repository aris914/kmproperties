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
        'advance_applies' => post('advance_applies') === 'last' ? 'last' : 'first',
        'due_day' => max(1, min(31, (int) post('due_day'))),
        'grace_days' => max(0, min(31, (int) post('grace_days'))),
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
    } elseif ($data['monthly_rent'] <= 0) {
        $error = 'Enter the monthly rent.';
    } elseif ($data['advance_applies'] === 'last' && !$data['end_date']) {
        $error = 'An advance for the last month needs an end date. Choose “first month” or add the end date.';
    } elseif ($data['status'] === 'active' && q_val("SELECT id FROM leases WHERE property_id = ? AND status = 'active' AND id <> ?", [$data['property_id'], $id])) {
        $error = 'This property already has an active lease. End that lease first.';
    }

    if ($error) {
        flash('error', $error);
        $l = array_merge($l ?: [], $data);
    } else {
        $tName = tenant_name($tenant);
        $period = fmt_date($data['start_date']) . ' – ' . ($data['end_date'] ? fmt_date($data['end_date']) : 'open-ended');
        $details = "Period: $period\nMonthly rent: " . money($data['monthly_rent'], 2) . ', due every ' . $data['due_day'] . ordinal_suffix($data['due_day'])
            . "\nDeposit: " . money($data['deposit'], 2) . "\nAdvance: " . money($data['advance'], 2) . ' (' . ($data['advance_applies'] === 'last' ? 'last' : 'first') . ' month)';

        if ($l && !empty($l['id'])) {
            $changes = audit_diff($l, $data, [
                'monthly_rent' => 'Rent', 'start_date' => 'Start date', 'end_date' => 'End date', 'due_day' => 'Due day', 'grace_days' => 'Grace days',
                'deposit' => 'Deposit', 'advance' => 'Advance', 'advance_applies' => 'Advance covers', 'status' => 'Status',
            ]);
            db_update('leases', $data, $id);
            // keep the advance payment on the month it covers
            if ($l['advance_applies'] !== $data['advance_applies'] || $l['start_date'] !== $data['start_date'] || (string) $l['end_date'] !== (string) $data['end_date']) {
                $ap = $data['advance_applies'] === 'last' && $data['end_date'] ? month_first($data['end_date']) : month_first($data['start_date']);
                q("UPDATE payments SET period_month = ?, period_label = ? WHERE lease_id = ? AND payment_type = 'advance'", [$ap, month_label($ap, true), $id]);
            }
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

            // Deposit and advance received at signing
            if (post('record_initial')) {
                $on = valid_date(post('received_on')) ?: date('Y-m-d');
                $method = mb_substr(post('initial_method'), 0, 40) ?: null;
                $rec = [];
                if ($data['deposit'] > 0) {
                    db_insert('payments', ['lease_id' => $id, 'amount' => $data['deposit'], 'paid_on' => $on, 'payment_type' => 'deposit', 'method' => $method, 'notes' => 'Received at signing']);
                    $rec[] = 'deposit ' . money($data['deposit'], 2);
                }
                if ($data['advance'] > 0) {
                    // the advance pays one or more months: the first months, or the last ones
                    $months = max(1, (int) round($data['advance'] / $data['monthly_rent']));
                    $per = round($data['advance'] / $months, 2);
                    $first = $data['advance_applies'] === 'last' ? month_add(month_first($data['end_date']), -($months - 1)) : month_first($data['start_date']);
                    for ($k = 0; $k < $months; $k++) {
                        $pm = month_add($first, $k);
                        db_insert('payments', ['lease_id' => $id, 'amount' => $k === $months - 1 ? round($data['advance'] - $per * ($months - 1), 2) : $per,
                            'paid_on' => $on, 'period_month' => $pm, 'period_label' => month_label($pm, true), 'payment_type' => 'advance', 'method' => $method, 'notes' => 'Advance received at signing']);
                    }
                    $rec[] = 'advance ' . money($data['advance'], 2) . ' (' . ($months > 1 ? $months . ' months' : month_label($first, true)) . ')';
                }
                if ($rec) {
                    log_history($data['property_id'], $data['tenant_id'], 'payment', 'Payment received: ' . implode(' and ', $rec) . ' from ' . $tName, $method, $on);
                    audit('insert', 'payments', $tName . ' — ' . $prop['title'], 'Received at signing: ' . implode(', ', $rec));
                }
            }
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
$props = q_all("SELECT id, title, code, unit_no, price, listing_type, deposit_months, advance_months, due_day FROM properties
                WHERE approval = 'approved' AND (listing_type = 'rent' OR id = ?) ORDER BY title", [$presetProp]);
$pp = null;
foreach ($props as $p) {
    if ((int) $p['id'] === $presetProp) {
        $pp = $p;
    }
}
// defaults for a new lease come from the property's terms
$defRent = $pp ? (float) $pp['price'] : '';
$defDep = $pp ? round($pp['price'] * $pp['deposit_months'], 2) : '';
$defAdv = $pp ? round($pp['price'] * $pp['advance_months'], 2) : '';
$defDue = $pp && $pp['due_day'] ? (int) $pp['due_day'] : (int) date('j');

admin_header($isEdit ? 'Edit lease' : 'New lease', 'leases');
?>
<a href="<?= $isEdit ? url('admin/lease-view.php?id=' . $l['id']) : url('admin/leases.php') ?>" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> Back</a>
<form method="post" style="max-width:980px">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $v('id', 0) ?>">
  <div class="card mb-4">
    <div class="card-header">Lease</div>
    <div class="card-body row g-3">
      <div class="col-md-6"><label class="form-label">Property *</label>
        <select name="property_id" id="propSel" class="form-select" required><option value="">Choose…</option>
          <?php foreach ($props as $p): ?>
            <option value="<?= $p['id'] ?>" data-rent="<?= e((float) $p['price']) ?>" data-dep="<?= e((float) $p['deposit_months']) ?>" data-adv="<?= e((float) $p['advance_months']) ?>" data-due="<?= e((int) $p['due_day']) ?>"<?= $presetProp === (int) $p['id'] ? ' selected' : '' ?>><?= e(($p['code'] ? $p['code'] . ' — ' : '') . $p['title'] . ($p['unit_no'] ? ' (Unit ' . $p['unit_no'] . ')' : '')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6"><label class="form-label">Tenant * <a href="<?= url('admin/tenant-edit.php') ?>" class="small ms-2" target="_blank">+ add new tenant</a></label><select name="tenant_id" class="form-select" required><option value="">Choose…</option><?= tenant_options($presetTenant) ?></select></div>
      <div class="col-md-4"><label class="form-label">Start date *</label><input type="date" name="start_date" class="form-control" required value="<?= e($v('start_date', date('Y-m-d'))) ?>"></div>
      <div class="col-md-4"><label class="form-label">End date</label><input type="date" name="end_date" class="form-control" value="<?= e($v('end_date')) ?>"><div class="form-text">Leave blank for month-to-month.</div></div>
      <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><?= select_options($isEdit ? ['active' => 'Active', 'upcoming' => 'Upcoming (signed, not started)', 'ended' => 'Ended', 'terminated' => 'Terminated'] : ['active' => 'Active', 'upcoming' => 'Upcoming (signed, not started)'], $v('status', 'active'), true) ?></select></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><i class="bi bi-calendar-check me-1"></i>Rent &amp; due date</div>
    <div class="card-body row g-3">
      <div class="col-md-4"><label class="form-label">Monthly rent *</label><input name="monthly_rent" id="fRent" class="form-control" inputmode="decimal" required value="<?= e($v('monthly_rent', $defRent)) ?>"></div>
      <div class="col-md-4"><label class="form-label">Rent is due every</label>
        <div class="input-group"><input type="number" min="1" max="31" name="due_day" id="fDue" class="form-control" value="<?= (int) $v('due_day', $defDue) ?>"><span class="input-group-text">of the month</span></div>
        <div class="form-text">29–31 = last day of shorter months.</div></div>
      <div class="col-md-4"><label class="form-label">Grace period</label>
        <div class="input-group"><input type="number" min="0" max="31" name="grace_days" class="form-control" value="<?= (int) $v('grace_days', 3) ?>"><span class="input-group-text">days</span></div>
        <div class="form-text">Rent counts as overdue after this.</div></div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header"><i class="bi bi-safe me-1"></i>Deposit &amp; advance</div>
    <div class="card-body row g-3">
      <div class="col-md-4"><label class="form-label">Security deposit</label><input name="deposit" id="fDep" class="form-control" inputmode="decimal" value="<?= e($v('deposit', $defDep)) ?>"><div class="form-text" id="depHint"><?= $pp ? e((float) $pp['deposit_months']) . ' month(s) per the property terms' : '' ?></div></div>
      <div class="col-md-4"><label class="form-label">Advance rent</label><input name="advance" id="fAdv" class="form-control" inputmode="decimal" value="<?= e($v('advance', $defAdv)) ?>"><div class="form-text" id="advHint"><?= $pp ? e((float) $pp['advance_months']) . ' month(s) per the property terms' : '' ?></div></div>
      <div class="col-md-4"><label class="form-label">Advance covers</label><select name="advance_applies" class="form-select"><?= select_options(['first' => 'The first month(s)', 'last' => 'The last month(s)'], $v('advance_applies', 'first'), true) ?></select></div>
      <?php if (!$isEdit): ?>
        <div class="col-12"><div class="upload-box">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="record_initial" value="1" id="recInit" checked><label class="form-check-label fw-semibold" for="recInit">Deposit and advance were received</label></div>
          <div class="row g-2 mt-1">
            <div class="col-md-4"><label class="form-label">Received on</label><input type="date" name="received_on" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
            <div class="col-md-4"><label class="form-label">Method</label><select name="initial_method" class="form-select form-select-sm"><?= select_options(['Bank transfer', 'GCash', 'Maya', 'Cash', 'Check', 'PDC', 'Other'], 'Bank transfer') ?></select></div>
          </div>
          <div class="hint mt-1">Records the deposit (held until move-out) and the advance (paid against the month(s) it covers) as payments.</div>
        </div></div>
      <?php else: ?>
        <div class="col-12 hint">Record deposit, advance, refunds and deposit applied to rent on the lease page.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header">Notes / special terms</div>
    <div class="card-body"><textarea name="notes" class="form-control" rows="3"><?= e($v('notes')) ?></textarea>
      <?php if (!$isEdit): ?><div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> An active lease marks the property as <strong>Rented</strong> and the tenant as <strong>Active</strong>, and is added to both histories.</div><?php endif; ?></div>
  </div>
  <div class="sticky-save text-end"><button class="btn btn-primary btn-lg"><i class="bi bi-check2 me-1"></i>Save lease</button></div>
</form>
<?php if (!$isEdit): ?>
<script>
// Fill rent, deposit, advance and due day from the chosen property's terms
document.getElementById('propSel').addEventListener('change', function () {
  var o = this.selectedOptions[0]; if (!o || !o.dataset.rent) return;
  var rent = parseFloat(o.dataset.rent) || 0, dep = parseFloat(o.dataset.dep) || 0, adv = parseFloat(o.dataset.adv) || 0;
  document.getElementById('fRent').value = rent || '';
  document.getElementById('fDep').value = rent ? Math.round(rent * dep * 100) / 100 : '';
  document.getElementById('fAdv').value = rent ? Math.round(rent * adv * 100) / 100 : '';
  if (parseInt(o.dataset.due, 10)) document.getElementById('fDue').value = o.dataset.due;
  document.getElementById('depHint').textContent = dep + ' month(s) per the property terms';
  document.getElementById('advHint').textContent = adv + ' month(s) per the property terms';
});
</script>
<?php endif; ?>
<?php admin_footer();
