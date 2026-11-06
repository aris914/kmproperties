<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) (get('id') ?: post('id'));
$l = q_one('SELECT l.*, p.title, p.code, p.unit_no, p.building, t.first_name, t.last_name, t.phone, t.email
            FROM leases l JOIN properties p ON p.id = l.property_id JOIN tenants t ON t.id = l.tenant_id WHERE l.id = ?', [$id]);
if (!$l) {
    flash('error', 'Lease not found.');
    redirect('admin/leases.php');
}
$self = 'admin/lease-view.php?id=' . $id;
$tName = $l['first_name'] . ' ' . $l['last_name'];

if (is_post()) {
    csrf_check();
    $action = post('action');

    if ($action === 'payment_add') {
        $amount = num(post('amount'));
        $date = valid_date(post('paid_on'));
        $ptype = in_array(post('payment_type'), ['rent', 'deposit', 'advance', 'utilities', 'penalty', 'other'], true) ? post('payment_type') : 'rent';
        if ($amount <= 0 || !$date) {
            flash('error', 'Enter the amount and payment date.');
        } else {
            db_insert('payments', [
                'lease_id' => $id, 'amount' => $amount, 'paid_on' => $date, 'payment_type' => $ptype,
                'period_label' => nn(post('period_label')), 'method' => nn(post('method')),
                'reference' => nn(post('reference')), 'notes' => nn(post('notes')),
            ]);
            log_history($l['property_id'], $l['tenant_id'], 'payment',
                'Payment received: ' . money($amount, 2) . ' (' . $ptype . (post('period_label') ? ', ' . post('period_label') : '') . ') from ' . $tName,
                trim(post('method') . ' ' . post('reference')), $date);
            flash('success', 'Payment recorded.');
        }
    } elseif ($action === 'payment_delete') {
        $pay = q_one('SELECT * FROM payments WHERE id = ? AND lease_id = ?', [(int) post('payment_id'), $id]);
        if ($pay) {
            q('DELETE FROM payments WHERE id = ?', [$pay['id']]);
            log_history($l['property_id'], $l['tenant_id'], 'payment', 'Payment of ' . money($pay['amount'], 2) . ' dated ' . fmt_date($pay['paid_on']) . ' was deleted');
            flash('success', 'Payment deleted.');
        }
    } elseif ($action === 'end' && in_array($l['status'], ['active', 'upcoming'], true)) {
        $moveOut = valid_date(post('move_out_date')) ?: date('Y-m-d');
        $newStatus = post('end_status') === 'terminated' ? 'terminated' : 'ended';
        $refund = post('deposit_refunded') === '' ? null : num(post('deposit_refunded'));
        db_update('leases', [
            'status' => $newStatus, 'move_out_date' => $moveOut, 'end_reason' => nn(post('end_reason')), 'deposit_refunded' => $refund,
        ], $id);
        release_after_lease($l['property_id'], $l['tenant_id']);
        $details = 'Move-out: ' . fmt_date($moveOut) . (post('end_reason') ? "\nReason: " . post('end_reason') : '')
            . ($refund !== null ? "\nDeposit refunded: " . money($refund, 2) . ' of ' . money($l['deposit'], 2) : '');
        log_history($l['property_id'], $l['tenant_id'], 'lease_end', ($newStatus === 'terminated' ? 'Lease terminated' : 'Lease ended') . ': ' . $tName . ' moved out of ' . $l['title'], $details, $moveOut);
        flash('success', 'Lease closed. The property is available again.');
    } elseif ($action === 'delete') {
        foreach (q_all("SELECT id, file FROM documents WHERE entity_type = 'lease' AND entity_id = ?", [$id]) as $d) {
            delete_file_safe(DOC_DIR . '/' . $d['file']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        }
        q('DELETE FROM leases WHERE id = ?', [$id]);
        release_after_lease($l['property_id'], $l['tenant_id']);
        log_history($l['property_id'], $l['tenant_id'], 'lease_update', 'Lease record deleted (' . fmt_date($l['start_date']) . ' – ' . fmt_date($l['end_date']) . ')');
        flash('success', 'Lease deleted.');
        redirect('admin/leases.php');
    }
    redirect($self);
}

$payments = q_all('SELECT * FROM payments WHERE lease_id = ? ORDER BY paid_on DESC, id DESC', [$id]);
$byType = [];
foreach ($payments as $pa) {
    $byType[$pa['payment_type']] = ($byType[$pa['payment_type']] ?? 0) + $pa['amount'];
}
$rentPaid = $byType['rent'] ?? 0;

// Rough rent statement: months elapsed since start × rent, vs rent paid (+ advance)
$endForCalc = $l['move_out_date'] ?: ($l['status'] === 'active' ? date('Y-m-d') : ($l['end_date'] ?: date('Y-m-d')));
$months = 0;
if ($l['start_date'] <= $endForCalc) {
    $a = new DateTime($l['start_date']);
    $b = new DateTime($endForCalc);
    $diff = $a->diff($b);
    $months = $diff->y * 12 + $diff->m + 1;
}
$rentDue = $months * (float) $l['monthly_rent'];
$balance = $rentDue - $rentPaid - ($byType['advance'] ?? 0);

admin_header('Lease — ' . $tName, 'leases');
?>
<div class="record-head">
  <span class="avatar-lg"><i class="bi bi-file-earmark-text"></i></span>
  <div class="flex-fill">
    <div class="mb-1"><?= status_badge($l['status']) ?></div>
    <h2 class="h4 mb-1"><a href="<?= url('admin/tenant-view.php?id=' . $l['tenant_id']) ?>"><?= e($tName) ?></a> <span class="text-muted fw-normal">·</span> <a href="<?= url('admin/property-view.php?id=' . $l['property_id']) ?>"><?= e($l['title']) ?></a></h2>
    <div class="small text-muted"><?= fmt_date($l['start_date']) ?> – <?= fmt_date($l['end_date']) ?><?= $l['move_out_date'] ? ' · moved out ' . fmt_date($l['move_out_date']) : '' ?></div>
  </div>
  <div class="d-flex gap-2 align-self-start">
    <a href="<?= url('admin/lease-edit.php?id=' . $id) ?>" class="btn btn-navy"><i class="bi bi-pencil me-1"></i>Edit / renew</a>
    <?php if (in_array($l['status'], ['active', 'upcoming'], true)): ?><button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#endLease"><i class="bi bi-door-open me-1"></i>End lease</button><?php endif; ?>
  </div>
</div>

<?php if (in_array($l['status'], ['active', 'upcoming'], true)): ?>
<div class="collapse mb-4" id="endLease">
  <form method="post" class="card card-body border-danger">
    <?= csrf_field() ?><input type="hidden" name="action" value="end">
    <h3 class="h6">End this lease / move-out</h3>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label small">Move-out date</label><input type="date" name="move_out_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Type</label><select name="end_status" class="form-select"><option value="ended">Ended normally</option><option value="terminated">Terminated early</option></select></div>
      <div class="col-md-3"><label class="form-label small">Deposit refunded (of <?= money($l['deposit'], 2) ?>)</label><input name="deposit_refunded" class="form-control" inputmode="decimal" placeholder="leave blank if not yet"></div>
      <div class="col-md-3"><label class="form-label small">Reason / remarks</label><input name="end_reason" class="form-control" maxlength="255"></div>
      <div class="col-12"><button class="btn btn-danger" onclick="return confirm('Close this lease? The property will become available.')">Close lease</button></div>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-xl-4">
    <div class="card mb-4">
      <div class="card-header">Terms</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between"><span>Monthly rent</span><strong><?= money($l['monthly_rent'], 2) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Due day</span><strong><?= (int) $l['due_day'] ?><?= ordinal_suffix($l['due_day']) ?> of the month</strong></div>
        <div class="d-flex justify-content-between"><span>Security deposit</span><strong><?= money($l['deposit'], 2) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Advance</span><strong><?= money($l['advance'], 2) ?></strong></div>
        <?php if ($l['deposit_refunded'] !== null): ?><div class="d-flex justify-content-between"><span>Deposit refunded</span><strong><?= money($l['deposit_refunded'], 2) ?></strong></div><?php endif; ?>
        <?php if ($l['end_reason']): ?><div class="mt-2 text-muted">End reason: <?= e($l['end_reason']) ?></div><?php endif; ?>
        <?php if ($l['notes']): ?><hr><div><?= nl2br(e($l['notes'])) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header">Rent statement</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between"><span><?= $months ?> month(s) × <?= money($l['monthly_rent'], 2) ?></span><strong><?= money($rentDue, 2) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Rent paid</span><strong><?= money($rentPaid, 2) ?></strong></div>
        <?php if (!empty($byType['advance'])): ?><div class="d-flex justify-content-between"><span>Advance paid</span><strong><?= money($byType['advance'], 2) ?></strong></div><?php endif; ?>
        <hr class="my-2">
        <div class="d-flex justify-content-between fs-6"><span><?= $balance > 0 ? 'Balance due' : 'Paid ahead' ?></span><strong class="<?= $balance > 0 ? 'text-danger' : 'text-success' ?>"><?= money(abs($balance), 2) ?></strong></div>
        <div class="text-muted mt-2">Estimate: counts every started month up to <?= $l['status'] === 'active' ? 'today' : 'move-out' ?>.</div>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header">Tenant</div>
      <div class="card-body small"><a href="<?= url('admin/tenant-view.php?id=' . $l['tenant_id']) ?>" class="fw-semibold"><?= e($tName) ?></a><div><?= e($l['phone']) ?></div><div><?= e($l['email']) ?></div></div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card mb-4">
      <div class="card-header">Record a payment</div>
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end">
          <?= csrf_field() ?><input type="hidden" name="action" value="payment_add">
          <div class="col-md-2"><label class="form-label small">Amount</label><input name="amount" class="form-control" inputmode="decimal" required value="<?= e((float) $l['monthly_rent']) ?>"></div>
          <div class="col-md-2"><label class="form-label small">Date paid</label><input type="date" name="paid_on" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-2"><label class="form-label small">For</label><select name="payment_type" class="form-select"><?= select_options(['rent' => 'Rent', 'deposit' => 'Deposit', 'advance' => 'Advance', 'utilities' => 'Utilities', 'penalty' => 'Penalty', 'other' => 'Other'], 'rent', true) ?></select></div>
          <div class="col-md-2"><label class="form-label small">Period</label><input name="period_label" class="form-control" value="<?= date('M Y') ?>"></div>
          <div class="col-md-2"><label class="form-label small">Method</label><select name="method" class="form-select"><?= select_options(['Cash', 'Bank transfer', 'GCash', 'Maya', 'Check', 'PDC', 'Other'], 'Bank transfer') ?></select></div>
          <div class="col-md-2"><label class="form-label small">Reference</label><input name="reference" class="form-control" maxlength="100"></div>
          <div class="col-md-10"><input name="notes" class="form-control form-control-sm" placeholder="Notes (optional)" maxlength="255"></div>
          <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
        </form>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header">Payment history · total <?= money(array_sum($byType), 2) ?></div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
        <thead><tr><th>Date</th><th>For</th><th>Method</th><th>Reference</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="6" class="text-muted text-center py-3">No payments yet.</td></tr><?php endif; ?>
        <?php foreach ($payments as $pa): ?>
          <tr>
            <td class="small"><?= fmt_date($pa['paid_on']) ?></td>
            <td class="small"><?= e(ucfirst($pa['payment_type'])) ?> <?= e($pa['period_label']) ?><?php if ($pa['notes']): ?><div class="text-muted"><?= e($pa['notes']) ?></div><?php endif; ?></td>
            <td class="small"><?= e($pa['method']) ?></td>
            <td class="small"><?= e($pa['reference']) ?></td>
            <td class="text-end"><?= money($pa['amount'], 2) ?></td>
            <td class="text-end"><form method="post" onsubmit="return confirm('Delete this payment?')"><?= csrf_field() ?><input type="hidden" name="action" value="payment_delete"><input type="hidden" name="payment_id" value="<?= $pa['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-circle"></i></button></form></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <div class="card mb-4">
      <div class="card-header">Lease documents</div>
      <div class="card-body"><?php documents_panel('lease', $id, url($self), ['Signed contract', 'Move-in inspection', 'Move-out inspection', 'Receipt', 'PDC copies', 'Addendum', 'Other']); ?></div>
    </div>

    <form method="post" class="text-end" onsubmit="return confirm('Delete this lease record with its payments and documents? Use only for mistakes — to close a lease, use End lease.')">
      <?= csrf_field() ?><input type="hidden" name="action" value="delete">
      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Delete lease record</button>
    </form>
  </div>
</div>
<?php admin_footer();
