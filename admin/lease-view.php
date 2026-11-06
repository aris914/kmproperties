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
$types = payment_types();

/** Applies deposit to unpaid months (oldest first). Returns the amount applied. */
function apply_deposit_to_arrears(array $l, $max, $on)
{
    $rows = rent_schedule($l, rent_paid_map([$l['id']])[$l['id']] ?? [], month_first($on));
    $left = $max;
    $done = [];
    foreach ($rows as $r) {
        if ($left <= 0.009) {
            break;
        }
        if ($r['balance'] > 0 && $r['due_date'] <= $on) {
            $amt = min($left, $r['balance']);
            db_insert('payments', ['lease_id' => $l['id'], 'amount' => $amt, 'paid_on' => $on, 'period_month' => $r['period'], 'period_label' => $r['label'], 'payment_type' => 'deposit_applied', 'notes' => 'Deposit applied to unpaid rent']);
            $left -= $amt;
            $done[] = $r['label'] . ' ' . money($amt, 2);
        }
    }
    return [round($max - $left, 2), $done];
}

if (is_post()) {
    csrf_check();
    unset($_SESSION['overdue_cache']);
    $action = post('action');

    if ($action === 'payment_add') {
        $amount = round(num(post('amount')), 2);
        $date = valid_date(post('paid_on'));
        $ptype = isset($types[post('payment_type')]) ? post('payment_type') : 'rent';
        $period = preg_match('/^\d{4}-\d{2}$/', post('period')) ? post('period') . '-01' : null;
        $months = in_array($ptype, ['rent', 'advance'], true) ? max(1, min(12, (int) post('months'))) : 1;
        $money = lease_money($l);
        $err = '';
        if ($amount <= 0 || !$date) {
            $err = 'Enter the amount and payment date.';
        } elseif (in_array($ptype, rent_settling_types(), true) && !$period) {
            $err = 'Choose the month this payment is for.';
        } elseif ($ptype === 'deposit_applied' && $amount > $money['deposit_held'] + 0.009) {
            $err = 'Only ' . money($money['deposit_held'], 2) . ' of the deposit is still held.';
        } elseif ($ptype === 'deposit_refund' && $amount > $money['deposit_held'] + 0.009) {
            $err = 'The refund is more than the deposit held (' . money($money['deposit_held'], 2) . ').';
        }
        if ($err) {
            flash('error', $err);
            redirect($self . ($period ? '&pay=' . substr($period, 0, 7) : '') . '#pay');
        }
        $per = round($amount / $months, 2);
        $labels = [];
        for ($k = 0; $k < $months; $k++) {
            $pm = $period ? month_add($period, $k) : null;
            db_insert('payments', [
                'lease_id' => $id, 'amount' => $k === $months - 1 ? round($amount - $per * ($months - 1), 2) : $per, 'paid_on' => $date,
                'period_month' => in_array($ptype, ['deposit', 'deposit_refund'], true) ? null : $pm, 'period_label' => $pm ? month_label($pm, true) : null,
                'payment_type' => $ptype, 'method' => nn(post('method')), 'reference' => nn(post('reference')), 'notes' => nn(post('notes')),
            ]);
            if ($pm) {
                $labels[] = month_label($pm, true);
            }
        }
        if ($ptype === 'deposit_refund') {
            q('UPDATE leases SET deposit_refunded = COALESCE(deposit_refunded, 0) + ? WHERE id = ?', [$amount, $id]);
        }
        $what = $types[$ptype] . ($labels ? ', ' . implode(' – ', array_unique([reset($labels), end($labels)])) : '');
        log_history($l['property_id'], $l['tenant_id'], 'payment', ($ptype === 'deposit_refund' ? 'Deposit refunded: ' : 'Payment received: ') . money($amount, 2) . ' (' . $what . ') ' . ($ptype === 'deposit_refund' ? 'to ' : 'from ') . $tName, trim(post('method') . ' ' . post('reference')), $date);
        audit('insert', 'payments', $tName . ' — ' . $l['title'], money($amount, 2) . ' ' . $what . ' on ' . $date . ' ' . trim(post('method') . ' ' . post('reference')));
        flash('success', ($ptype === 'deposit_refund' ? 'Refund' : 'Payment') . ' of ' . money($amount, 2) . ' recorded (' . $what . ').');
    } elseif ($action === 'payment_delete') {
        $pay = q_one('SELECT * FROM payments WHERE id = ? AND lease_id = ?', [(int) post('payment_id'), $id]);
        if ($pay) {
            q('DELETE FROM payments WHERE id = ?', [$pay['id']]);
            if ($pay['payment_type'] === 'deposit_refund') {
                q('UPDATE leases SET deposit_refunded = GREATEST(0, COALESCE(deposit_refunded, 0) - ?) WHERE id = ?', [$pay['amount'], $id]);
            }
            log_history($l['property_id'], $l['tenant_id'], 'payment', $types[$pay['payment_type']] . ' of ' . money($pay['amount'], 2) . ' dated ' . fmt_date($pay['paid_on']) . ' was deleted');
            audit('delete', 'payments', $tName . ' — ' . $l['title'], $types[$pay['payment_type']] . ' ' . money($pay['amount'], 2) . ' dated ' . $pay['paid_on'] . ($pay['period_label'] ? ' for ' . $pay['period_label'] : ''));
            flash('success', 'Payment deleted.');
        }
    } elseif ($action === 'end' && in_array($l['status'], ['active', 'upcoming'], true)) {
        $moveOut = valid_date(post('move_out_date')) ?: date('Y-m-d');
        $newStatus = post('end_status') === 'terminated' ? 'terminated' : 'ended';
        $money = lease_money($l);
        $applied = 0;
        $appliedList = [];
        $l2 = array_merge($l, ['move_out_date' => $moveOut]);
        if (post('apply_deposit') && $money['deposit_held'] > 0) {
            list($applied, $appliedList) = apply_deposit_to_arrears($l2, $money['deposit_held'], $moveOut);
        }
        $refund = post('deposit_refunded') === '' ? null : min(num(post('deposit_refunded')), max(0, $money['deposit_held'] - $applied));
        if ($refund !== null && $refund > 0) {
            db_insert('payments', ['lease_id' => $id, 'amount' => $refund, 'paid_on' => $moveOut, 'payment_type' => 'deposit_refund', 'method' => nn(post('refund_method')), 'notes' => 'Refund at move-out']);
        }
        db_update('leases', ['status' => $newStatus, 'move_out_date' => $moveOut, 'end_reason' => nn(post('end_reason')), 'deposit_refunded' => $refund], $id);
        release_after_lease($l['property_id'], $l['tenant_id']);
        $details = 'Move-out: ' . fmt_date($moveOut) . (post('end_reason') ? "\nReason: " . post('end_reason') : '')
            . ($applied > 0 ? "\nDeposit applied to unpaid rent: " . money($applied, 2) . ' (' . implode(', ', $appliedList) . ')' : '')
            . ($refund !== null ? "\nDeposit refunded: " . money($refund, 2) . ' of ' . money($l['deposit'], 2) : '');
        log_history($l['property_id'], $l['tenant_id'], 'lease_end', ($newStatus === 'terminated' ? 'Lease terminated' : 'Lease ended') . ': ' . $tName . ' moved out of ' . $l['title'], $details, $moveOut);
        audit('update', 'leases', $tName . ' — ' . $l['title'], ($newStatus === 'terminated' ? 'Terminated' : 'Ended') . "\n" . $details);
        flash('success', 'Lease closed. The property is available again.');
    } elseif ($action === 'delete') {
        foreach (q_all("SELECT id, file FROM documents WHERE entity_type = 'lease' AND entity_id = ?", [$id]) as $d) {
            delete_file_safe(DOC_DIR . '/' . $d['file']);
            q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        }
        q('DELETE FROM leases WHERE id = ?', [$id]);
        release_after_lease($l['property_id'], $l['tenant_id']);
        log_history($l['property_id'], $l['tenant_id'], 'lease_update', 'Lease record deleted (' . fmt_date($l['start_date']) . ' – ' . fmt_date($l['end_date']) . ')');
        audit('delete', 'leases', $tName . ' — ' . $l['title'], fmt_date($l['start_date']) . ' – ' . fmt_date($l['end_date']));
        flash('success', 'Lease deleted.');
        redirect('admin/leases.php');
    }
    redirect($self);
}

$payments = q_all('SELECT * FROM payments WHERE lease_id = ? ORDER BY paid_on DESC, id DESC', [$id]);
$paidMap = rent_paid_map([$id]);
$rows = rent_schedule($l, $paidMap[$id] ?? []);
$tot = rent_totals($rows);
$money = lease_money($l);
$open = in_array($l['status'], ['active', 'upcoming'], true);

// payment form defaults: ?pay=YYYY-MM from the Collection page, else the oldest unpaid month
$payRow = null;
if (preg_match('/^\d{4}-\d{2}$/', get('pay'))) {
    foreach ($rows as $r) {
        if (substr($r['period'], 0, 7) === get('pay')) {
            $payRow = $r;
        }
    }
}
$payRow = $payRow ?: rent_next_period($rows);
$payAmount = $payRow ? ($payRow['balance'] > 0 ? $payRow['balance'] : $payRow['due']) : (float) $l['monthly_rent'];

admin_header('Lease — ' . $tName, 'leases');
?>
<div class="record-head">
  <span class="avatar-lg"><i class="bi bi-file-earmark-text"></i></span>
  <div class="flex-fill">
    <div class="mb-1"><?= status_badge($l['status']) ?> <?php if ($tot['overdue'] > 0): ?><span class="badge text-bg-danger"><?= money($tot['overdue'], 2) ?> overdue</span><?php elseif ($tot['balance'] <= 0 && $rows): ?><span class="badge text-bg-success">Rent up to date</span><?php endif; ?></div>
    <h2 class="h4 mb-1"><a href="<?= url('admin/tenant-view.php?id=' . $l['tenant_id']) ?>"><?= e($tName) ?></a> <span class="text-muted fw-normal">·</span> <a href="<?= url('admin/property-view.php?id=' . $l['property_id']) ?>"><?= e($l['title']) ?></a></h2>
    <div class="small text-muted"><?= fmt_date($l['start_date']) ?> – <?= fmt_date($l['end_date']) ?><?= $l['move_out_date'] ? ' · moved out ' . fmt_date($l['move_out_date']) : '' ?> · rent <?= money($l['monthly_rent'], 2) ?> due every <?= (int) $l['due_day'] ?><?= ordinal_suffix($l['due_day']) ?></div>
  </div>
  <div class="d-flex gap-2 align-self-start">
    <a href="<?= url('admin/lease-edit.php?id=' . $id) ?>" class="btn btn-navy"><i class="bi bi-pencil me-1"></i>Edit / renew</a>
    <?php if ($open): ?><button class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#endLease"><i class="bi bi-door-open me-1"></i>End lease</button><?php endif; ?>
  </div>
</div>

<?php if ($open): ?>
<div class="collapse mb-4" id="endLease">
  <form method="post" class="card card-body border-danger">
    <?= csrf_field() ?><input type="hidden" name="action" value="end">
    <h3 class="h6">End this lease / move-out</h3>
    <div class="row g-3">
      <div class="col-md-3"><label class="form-label small">Move-out date</label><input type="date" name="move_out_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-3"><label class="form-label small">Type</label><select name="end_status" class="form-select"><option value="ended">Ended normally</option><option value="terminated">Terminated early</option></select></div>
      <div class="col-md-6"><label class="form-label small">Reason / remarks</label><input name="end_reason" class="form-control" maxlength="255"></div>
      <div class="col-12"><div class="upload-box">
        <div class="small mb-2">Deposit held: <b><?= money($money['deposit_held'], 2) ?></b><?= $tot['balance'] > 0 ? ' · unpaid rent: <b class="text-danger">' . money($tot['balance'], 2) . '</b>' : '' ?></div>
        <?php if ($tot['balance'] > 0 && $money['deposit_held'] > 0): ?><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="apply_deposit" value="1" id="applyDep" checked><label class="form-check-label" for="applyDep">Use the deposit to pay the unpaid rent first (oldest months first)</label></div><?php endif; ?>
        <div class="row g-2">
          <div class="col-md-4"><label class="form-label small">Deposit refunded to tenant</label><input name="deposit_refunded" class="form-control" inputmode="decimal" placeholder="leave blank if not yet"></div>
          <div class="col-md-4"><label class="form-label small">Refund method</label><select name="refund_method" class="form-select"><?= select_options(['Bank transfer', 'GCash', 'Maya', 'Cash', 'Check'], 'Bank transfer') ?></select></div>
        </div>
      </div></div>
      <div class="col-12"><button class="btn btn-danger" onclick="return confirm('Close this lease? The property will become available.')">Close lease</button></div>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3"><div class="stat-card stat-navy"><i class="bi bi-receipt"></i><div><div class="stat-value fs-5"><?= money($tot['due'], 2) ?></div><div class="stat-label">Rent due so far</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card stat-teal"><i class="bi bi-cash-coin"></i><div><div class="stat-value fs-5"><?= money($tot['paid'], 2) ?></div><div class="stat-label">Paid<?= $tot['paid_ahead'] > 0 ? ' (+' . money($tot['paid_ahead'], 2) . ' ahead)' : '' ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card <?= $tot['balance'] > 0 ? 'stat-danger' : 'stat-teal' ?>"><i class="bi bi-exclamation-diamond"></i><div><div class="stat-value fs-5"><?= money($tot['balance'], 2) ?></div><div class="stat-label">Balance<?= $tot['overdue_months'] ? ' · ' . $tot['overdue_months'] . ' month(s) overdue' : '' ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card stat-gold"><i class="bi bi-calendar-event"></i><div><div class="stat-value fs-5"><?= $tot['next'] ? fmt_date($tot['next']['due_date'], 'M j') : '—' ?></div><div class="stat-label"><?= $tot['next'] ? 'Next due · ' . money($tot['next']['balance'], 2) : 'Next due' ?></div></div></div></div>
</div>

<div class="row g-4">
  <div class="col-xl-4">
    <div class="card mb-4">
      <div class="card-header">Terms</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between"><span>Monthly rent</span><strong><?= money($l['monthly_rent'], 2) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Due</span><strong>every <?= (int) $l['due_day'] ?><?= ordinal_suffix($l['due_day']) ?> of the month</strong></div>
        <div class="d-flex justify-content-between"><span>Grace period</span><strong><?= (int) $l['grace_days'] ?> day(s)</strong></div>
        <?php if ($l['end_reason']): ?><div class="mt-2 text-muted">End reason: <?= e($l['end_reason']) ?></div><?php endif; ?>
        <?php if ($l['notes']): ?><hr><div><?= nl2br(e($l['notes'])) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-safe me-1"></i>Deposit &amp; advance</div>
      <div class="card-body small">
        <div class="d-flex justify-content-between"><span>Deposit required</span><strong><?= money($money['deposit_required'], 2) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Deposit received</span><strong class="<?= $money['deposit_received'] + 0.009 < $money['deposit_required'] ? 'text-danger' : '' ?>"><?= money($money['deposit_received'], 2) ?></strong></div>
        <?php if ($money['deposit_applied'] > 0): ?><div class="d-flex justify-content-between"><span>Applied to rent</span><strong>− <?= money($money['deposit_applied'], 2) ?></strong></div><?php endif; ?>
        <?php if ($money['deposit_refunded'] > 0): ?><div class="d-flex justify-content-between"><span>Refunded</span><strong>− <?= money($money['deposit_refunded'], 2) ?></strong></div><?php endif; ?>
        <div class="d-flex justify-content-between border-top mt-1 pt-1"><span>Deposit held now</span><strong class="text-teal"><?= money($money['deposit_held'], 2) ?></strong></div>
        <hr class="my-2">
        <div class="d-flex justify-content-between"><span>Advance required</span><strong><?= money($money['advance_required'], 2) ?></strong></div>
        <div class="d-flex justify-content-between"><span>Advance received</span><strong class="<?= $money['advance_received'] + 0.009 < $money['advance_required'] ? 'text-danger' : '' ?>"><?= money($money['advance_received'], 2) ?></strong></div>
        <div class="hint mt-1">The advance pays the <?= $l['advance_applies'] === 'last' ? 'last' : 'first' ?> month(s) of the lease.</div>
        <?php if ($money['deposit_received'] + 0.009 < $money['deposit_required'] || $money['advance_received'] + 0.009 < $money['advance_required']): ?>
          <div class="alert alert-warning py-1 px-2 small mt-2 mb-0">Not fully received yet — record it with the payment form.</div>
        <?php endif; ?>
      </div>
    </div>
    <div class="card mb-4">
      <div class="card-header">Tenant</div>
      <div class="card-body small"><a href="<?= url('admin/tenant-view.php?id=' . $l['tenant_id']) ?>" class="fw-semibold"><?= e($tName) ?></a><div><?= e($l['phone']) ?></div><div><?= e($l['email']) ?></div></div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card mb-4" id="pay">
      <div class="card-header">Record a payment</div>
      <div class="card-body">
        <form method="post" class="row g-2 align-items-end" id="payForm">
          <?= csrf_field() ?><input type="hidden" name="action" value="payment_add">
          <div class="col-md-3"><label class="form-label small">For</label><select name="payment_type" id="payType" class="form-select"><?= select_options($types, 'rent', true) ?></select></div>
          <div class="col-md-3 pay-period"><label class="form-label small">Month</label><input type="month" name="period" class="form-control" value="<?= e($payRow ? substr($payRow['period'], 0, 7) : date('Y-m')) ?>"></div>
          <div class="col-md-2 pay-months"><label class="form-label small">Months</label><select name="months" id="payMonths" class="form-select"><?php for ($m = 1; $m <= 12; $m++): ?><option value="<?= $m ?>"><?= $m ?></option><?php endfor; ?></select></div>
          <div class="col-md-2"><label class="form-label small">Amount</label><input name="amount" id="payAmount" class="form-control" inputmode="decimal" required value="<?= e(round($payAmount, 2)) ?>"></div>
          <div class="col-md-2"><label class="form-label small">Date paid</label><input type="date" name="paid_on" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
          <div class="col-md-3"><label class="form-label small">Method</label><select name="method" class="form-select"><?= select_options(['Bank transfer', 'GCash', 'Maya', 'Cash', 'Check', 'PDC', 'Other'], 'Bank transfer') ?></select></div>
          <div class="col-md-3"><label class="form-label small">Reference</label><input name="reference" class="form-control" maxlength="100"></div>
          <div class="col-md-4"><label class="form-label small">Notes</label><input name="notes" class="form-control" maxlength="255"></div>
          <div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Add</button></div>
          <div class="col-12 hint" id="payHint"></div>
        </form>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between"><span><i class="bi bi-calendar3 me-1"></i>Rent schedule</span><span class="small text-muted">due every <?= (int) $l['due_day'] ?><?= ordinal_suffix($l['due_day']) ?> · <?= (int) $l['grace_days'] ?> day(s) grace</span></div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead><tr><th>Month</th><th>Due date</th><th class="text-end">Rent</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-muted text-center py-3">No months due yet.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
          <tr class="<?= $r['status'] === 'overdue' ? 'table-danger' : '' ?>">
            <td><?= e($r['label']) ?></td>
            <td class="small"><?= fmt_date($r['due_date']) ?><?php if ($r['days_late'] && $r['status'] === 'overdue'): ?><div class="text-danger"><?= $r['days_late'] ?> day(s) late</div><?php endif; ?></td>
            <td class="text-end"><?= money($r['due'], 2) ?></td>
            <td class="text-end"><?= $r['paid'] > 0 ? money($r['paid'], 2) : '—' ?></td>
            <td class="text-end fw-semibold"><?= $r['balance'] > 0 ? money($r['balance'], 2) : '—' ?></td>
            <td><?= rent_status_badge($r['status'], $r['paid']) ?></td>
            <td class="text-end"><?php if ($r['balance'] > 0): ?><a href="<?= url($self . '&pay=' . substr($r['period'], 0, 7)) ?>#pay" class="btn btn-sm btn-light">Pay</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <div class="card mb-4">
      <div class="card-header">Payment history · received <?= money($money['rent_received'] + $money['advance_received'] + $money['deposit_received'] + $money['other_received'], 2) ?></div>
      <div class="table-responsive"><table class="table table-sm table-hover align-middle">
        <thead><tr><th>Date</th><th>For</th><th>Month</th><th>Method</th><th class="text-end">Amount</th><th></th></tr></thead>
        <tbody>
        <?php if (!$payments): ?><tr><td colspan="6" class="text-muted text-center py-3">No payments yet.</td></tr><?php endif; ?>
        <?php foreach ($payments as $pa): ?>
          <tr>
            <td class="small"><?= fmt_date($pa['paid_on']) ?></td>
            <td class="small"><?= e($types[$pa['payment_type']] ?? $pa['payment_type']) ?><?php if ($pa['notes']): ?><div class="text-muted"><?= e($pa['notes']) ?></div><?php endif; ?></td>
            <td class="small"><?= e($pa['period_month'] ? month_label($pa['period_month'], true) : ($pa['period_label'] ?: '—')) ?></td>
            <td class="small"><?= e($pa['method']) ?> <span class="text-muted"><?= e($pa['reference']) ?></span></td>
            <td class="text-end <?= $pa['payment_type'] === 'deposit_refund' ? 'text-danger' : '' ?>"><?= $pa['payment_type'] === 'deposit_refund' ? '− ' : '' ?><?= money($pa['amount'], 2) ?></td>
            <td class="text-end"><form method="post" onsubmit="return confirm('Delete this payment?')"><?= csrf_field() ?><input type="hidden" name="action" value="payment_delete"><input type="hidden" name="payment_id" value="<?= $pa['id'] ?>"><button class="btn btn-sm btn-link text-danger p-0" title="Delete"><i class="bi bi-x-circle"></i></button></form></td>
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
<script>
(function () {
  var t = document.getElementById('payType'), amt = document.getElementById('payAmount'), mo = document.getElementById('payMonths'), hint = document.getElementById('payHint');
  var rent = <?= json_encode((float) $l['monthly_rent']) ?>, held = <?= json_encode($money['deposit_held']) ?>;
  var depLeft = <?= json_encode(max(0, $money['deposit_required'] - $money['deposit_received'])) ?>, advLeft = <?= json_encode(max(0, $money['advance_required'] - $money['advance_received'])) ?>;
  var text = {
    rent: 'Rent for the chosen month. Choose more months to pay several at once — the amount is split evenly.',
    advance: 'Advance rent counts as payment for the chosen month(s).',
    deposit: 'Security deposit — held until move-out (no month).',
    deposit_applied: 'Uses part of the deposit held (' + held.toLocaleString() + ') to pay the chosen month.',
    deposit_refund: 'Money returned to the tenant from the deposit held (' + held.toLocaleString() + ').',
    utilities: 'Electricity, water, association dues and similar (not rent).', penalty: 'Late fee or penalty (not rent).', other: 'Any other payment (not rent).'
  };
  function sync(setAmount) {
    var v = t.value, monthly = ['rent', 'advance', 'deposit_applied', 'utilities', 'penalty', 'other'].indexOf(v) >= 0;
    document.querySelector('.pay-period').style.display = ['deposit', 'deposit_refund'].indexOf(v) >= 0 ? 'none' : '';
    document.querySelector('.pay-months').style.display = ['rent', 'advance'].indexOf(v) >= 0 ? '' : 'none';
    if (setAmount) {
      if (v === 'rent' || v === 'advance') amt.value = (v === 'advance' && advLeft ? advLeft : rent * parseInt(mo.value, 10)).toFixed(2);
      if (v === 'deposit') amt.value = (depLeft || rent * 2).toFixed(2);
      if (v === 'deposit_applied' || v === 'deposit_refund') amt.value = Math.min(held, v === 'deposit_applied' ? rent : held).toFixed(2);
    }
    hint.textContent = text[v] || '';
  }
  t.addEventListener('change', function () { sync(true); });
  mo.addEventListener('change', function () { if (t.value === 'rent') amt.value = (rent * parseInt(mo.value, 10)).toFixed(2); });
  sync(false);
})();
</script>
<?php admin_footer();
