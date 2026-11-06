<?php
require __DIR__ . '/includes/init.php';
require_admin();

$id = (int) get('id');
$t = q_one('SELECT * FROM tenants WHERE id = ?', [$id]);
if (!$t) {
    flash('error', 'Tenant not found.');
    redirect('admin/tenants.php');
}
$tabs = ['overview' => 'Overview', 'leases' => 'Lease history', 'payments' => 'Payments', 'maintenance' => 'Repair requests', 'documents' => 'Documents', 'history' => 'History'];
$tab = isset($tabs[get('tab')]) ? get('tab') : 'overview';
$self = url('admin/tenant-view.php?id=' . $id . '&tab=' . $tab);

$leases = q_all('SELECT l.*, p.title, p.code, p.unit_no, (SELECT COALESCE(SUM(amount),0) FROM payments pa WHERE pa.lease_id = l.id) AS paid
                 FROM leases l JOIN properties p ON p.id = l.property_id WHERE l.tenant_id = ? ORDER BY l.start_date DESC', [$id]);
$active = null;
foreach ($leases as $l) {
    if ($l['status'] === 'active') { $active = $l; break; }
}
$totalPaid = (float) q_val('SELECT COALESCE(SUM(pa.amount),0) FROM payments pa JOIN leases l ON l.id = pa.lease_id WHERE l.tenant_id = ?', [$id]);
$docCount = (int) q_val("SELECT COUNT(*) FROM documents WHERE entity_type = 'tenant' AND entity_id = ?", [$id]);
$jobCount = (int) q_val('SELECT COUNT(*) FROM maintenance WHERE tenant_id = ?', [$id]);
$payCount = (int) q_val('SELECT COUNT(*) FROM payments pa JOIN leases l ON l.id = pa.lease_id WHERE l.tenant_id = ?', [$id]);

admin_header(tenant_name($t), 'tenants');
?>
<div class="record-head">
  <span class="avatar-lg"><?= e(mb_strtoupper(mb_substr($t['first_name'], 0, 1) . mb_substr($t['last_name'], 0, 1))) ?></span>
  <div class="flex-fill">
    <div class="mb-1"><?= status_badge($t['status']) ?></div>
    <h2 class="h4 mb-1"><?= e(tenant_name($t)) ?></h2>
    <div class="small text-muted">
      <?php if ($t['phone']): ?><i class="bi bi-telephone"></i> <a href="tel:<?= e($t['phone']) ?>"><?= e($t['phone']) ?></a> &nbsp;<?php endif; ?>
      <?php if ($t['email']): ?><i class="bi bi-envelope"></i> <a href="mailto:<?= e($t['email']) ?>"><?= e($t['email']) ?></a><?php endif; ?>
    </div>
    <?php if ($active): ?><div class="small mt-1"><i class="bi bi-house-door text-teal"></i> Currently renting <a href="<?= url('admin/property-view.php?id=' . $active['property_id']) ?>"><?= e($active['title']) ?></a></div><?php endif; ?>
  </div>
  <div class="d-flex flex-wrap gap-2 align-self-start">
    <a href="<?= url('admin/tenant-edit.php?id=' . $id) ?>" class="btn btn-navy"><i class="bi bi-pencil me-1"></i>Edit</a>
    <a href="<?= url('admin/lease-edit.php?tenant_id=' . $id) ?>" class="btn btn-outline-primary"><i class="bi bi-key me-1"></i>New lease</a>
    <?php if ($active): ?><a href="<?= url('admin/maintenance-edit.php?property_id=' . $active['property_id'] . '&tenant_id=' . $id) ?>" class="btn btn-outline-primary"><i class="bi bi-tools me-1"></i>Log repair request</a><?php endif; ?>
  </div>
</div>

<ul class="nav nav-tabs record-tabs mb-4">
  <?php foreach ($tabs as $k => $label): $count = ['leases' => count($leases), 'payments' => $payCount, 'maintenance' => $jobCount, 'documents' => $docCount][$k] ?? null; ?>
    <li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= url('admin/tenant-view.php?id=' . $id . '&tab=' . $k) ?>"><?= e($label) ?><?php if ($count): ?> <span class="badge rounded-pill text-bg-light"><?= $count ?></span><?php endif; ?></a></li>
  <?php endforeach; ?>
</ul>

<?php if ($tab === 'overview'): ?>
<div class="row g-4">
  <div class="col-xl-8">
    <div class="card"><div class="card-body">
      <div class="row g-3 small">
        <?php
        $facts = [
            'Birth date' => fmt_date($t['birth_date']), 'Occupation' => $t['occupation'] ?: '—', 'Employer' => $t['employer'] ?: '—',
            'Valid ID' => trim($t['id_type'] . ' ' . $t['id_number']) ?: '—', 'ID expiry' => fmt_date($t['id_expiry'] ?? null), 'Address' => $t['address'] ?: '—',
            'Emergency contact' => $t['emergency_name'] ? $t['emergency_name'] . ($t['emergency_relation'] ? ' (' . $t['emergency_relation'] . ')' : '') . ' ' . $t['emergency_phone'] : '—',
            'Tenant since' => fmt_date($t['created_at']),
        ];
        foreach ($facts as $k => $val): ?>
          <div class="col-md-6"><div class="text-muted"><?= e($k) ?></div><div class="fw-semibold"><?= e($val) ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php if ($t['notes']): ?><hr><div class="small"><?= nl2br(e($t['notes'])) ?></div><?php endif; ?>
    </div></div>
  </div>
  <div class="col-xl-4">
    <?php $tdocs = tenant_docs($id); ?>
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-person-vcard me-1"></i>Valid ID</span><?= id_expiry_badge($t['id_expiry'] ?? null) ?></div>
      <div class="card-body">
        <div class="small mb-2"><b><?= e($t['id_type'] ?: 'ID type not set') ?></b><?= $t['id_number'] ? ' · ' . e($t['id_number']) : '' ?></div>
        <div class="row g-2">
          <?php foreach (['front' => 'Front', 'back' => 'Back'] as $side => $label): ?>
            <div class="col-6"><?= $tdocs[$side] ? doc_thumb($tdocs[$side]) : '<div class="id-thumb empty"><i class="bi bi-person-vcard"></i>No ' . strtolower($label) . '</div>' ?><div class="hint text-center mt-1"><?= $label ?></div></div>
          <?php endforeach; ?>
        </div>
        <a href="<?= url('admin/tenant-edit.php?id=' . $id) ?>#validId" class="btn btn-sm btn-light mt-2"><i class="bi bi-camera me-1"></i><?= $tdocs['front'] ? 'Update ID photos' : 'Add ID photos' ?></a>
        <?php if ($tdocs['other']): ?><a href="<?= url('admin/tenant-view.php?id=' . $id . '&tab=documents') ?>" class="btn btn-sm btn-light mt-2"><i class="bi bi-folder2-open me-1"></i><?= count($tdocs['other']) ?> other document<?= count($tdocs['other']) === 1 ? '' : 's' ?></a><?php endif; ?>
      </div>
    </div>
    <div class="card mb-4"><div class="card-body small">
      <div class="d-flex justify-content-between"><span>Leases</span><strong><?= count($leases) ?></strong></div>
      <div class="d-flex justify-content-between"><span>Total paid (all time)</span><strong><?= money($totalPaid, 2) ?></strong></div>
      <div class="d-flex justify-content-between"><span>Repair requests</span><strong><?= $jobCount ?></strong></div>
      <div class="d-flex justify-content-between"><span>Documents</span><strong><?= $docCount ?></strong></div>
    </div></div>
    <?php if ($active): ?>
    <div class="card"><div class="card-header">Active lease</div><div class="card-body small">
      <div class="fw-semibold"><?= e($active['title']) ?></div>
      <div><?= fmt_date($active['start_date']) ?> – <?= fmt_date($active['end_date']) ?></div>
      <div>Rent <?= money($active['monthly_rent'], 2) ?>/mo, due every <?= (int) $active['due_day'] ?><?= ordinal_suffix($active['due_day']) ?></div>
      <a href="<?= url('admin/lease-view.php?id=' . $active['id']) ?>" class="btn btn-sm btn-light mt-2">Open lease &amp; payments</a>
    </div></div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($tab === 'leases'): ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Property</th><th>Period</th><th>Rent</th><th>Deposit</th><th>Paid</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$leases): ?><tr><td colspan="7" class="text-muted text-center py-4">No leases yet.</td></tr><?php endif; ?>
  <?php foreach ($leases as $l): ?>
    <tr>
      <td><a href="<?= url('admin/property-view.php?id=' . $l['property_id']) ?>" class="fw-semibold"><?= e($l['title']) ?></a><div class="small text-muted"><?= e(trim($l['code'] . ($l['unit_no'] ? ' · Unit ' . $l['unit_no'] : ''), ' ·')) ?></div></td>
      <td class="small"><?= fmt_date($l['start_date']) ?> – <?= $l['move_out_date'] ? fmt_date($l['move_out_date']) : fmt_date($l['end_date']) ?><?php if ($l['end_reason']): ?><div class="text-muted"><?= e($l['end_reason']) ?></div><?php endif; ?></td>
      <td><?= money($l['monthly_rent'], 2) ?></td>
      <td class="small"><?= money($l['deposit'], 2) ?><?php if ($l['deposit_refunded'] !== null): ?><div class="text-muted">refunded <?= money($l['deposit_refunded'], 2) ?></div><?php endif; ?></td>
      <td><?= money($l['paid'], 2) ?></td>
      <td><?= status_badge($l['status']) ?></td>
      <td class="text-end"><a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-folder2-open"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<?php elseif ($tab === 'payments'):
    $pays = q_all('SELECT pa.*, p.title, l.id AS lease_id FROM payments pa JOIN leases l ON l.id = pa.lease_id JOIN properties p ON p.id = l.property_id WHERE l.tenant_id = ? ORDER BY pa.paid_on DESC, pa.id DESC', [$id]); ?>
<div class="card">
  <div class="card-header">All payments · total <strong><?= money($totalPaid, 2) ?></strong></div>
  <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
    <thead><tr><th>Date</th><th>Property</th><th>For</th><th>Method / ref.</th><th class="text-end">Amount</th></tr></thead>
    <tbody>
    <?php if (!$pays): ?><tr><td colspan="5" class="text-muted text-center py-4">No payments recorded.</td></tr><?php endif; ?>
    <?php foreach ($pays as $pa): ?>
      <tr>
        <td class="small"><?= fmt_date($pa['paid_on']) ?></td>
        <td class="small"><a href="<?= url('admin/lease-view.php?id=' . $pa['lease_id']) ?>"><?= e($pa['title']) ?></a></td>
        <td class="small"><?= e(ucfirst($pa['payment_type'])) ?><?= $pa['period_label'] ? ' · ' . e($pa['period_label']) : '' ?></td>
        <td class="small"><?= e($pa['method']) ?> <span class="text-muted"><?= e($pa['reference']) ?></span></td>
        <td class="text-end"><?= money($pa['amount'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php elseif ($tab === 'maintenance'):
    $jobs = q_all('SELECT m.*, p.title AS property_title FROM maintenance m JOIN properties p ON p.id = m.property_id WHERE m.tenant_id = ? ORDER BY m.reported_on DESC', [$id]); ?>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
  <thead><tr><th>Reported</th><th>Job</th><th>Property</th><th>Cost</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php if (!$jobs): ?><tr><td colspan="6" class="text-muted text-center py-4">No repair requests linked to this tenant.</td></tr><?php endif; ?>
  <?php foreach ($jobs as $j): ?>
    <tr>
      <td class="small"><?= fmt_date($j['reported_on']) ?></td>
      <td><strong><?= e($j['title']) ?></strong><div class="small text-muted"><?= e(ucfirst($j['category'])) ?></div></td>
      <td class="small"><?= e($j['property_title']) ?></td>
      <td class="small"><?= $j['cost'] > 0 ? money($j['cost'], 2) . ' <span class="text-muted">(' . e($j['charged_to']) . ')</span>' : '—' ?></td>
      <td><?= status_badge($j['status']) ?></td>
      <td class="text-end"><a href="<?= url('admin/maintenance-edit.php?id=' . $j['id']) ?>" class="btn btn-sm btn-light"><i class="bi bi-pencil"></i></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<?php elseif ($tab === 'documents'): ?>
<div class="card"><div class="card-body">
  <p class="mb-2"><a href="<?= url('admin/tenant-edit.php?id=' . $id) ?>#validId" class="btn btn-sm btn-navy"><i class="bi bi-camera me-1"></i>Upload ID photos (front / back)</a></p>
  <p class="small text-muted">Valid IDs, proof of income, application form, NBI clearance, etc. Lease contracts can also be uploaded on each lease.</p>
  <?php documents_panel('tenant', $id, $self, ['Valid ID', 'Proof of income', 'Application form', 'Clearance', 'Contract', 'Receipt', 'Other']); ?>
</div></div>

<?php elseif ($tab === 'history'): ?>
<div class="card"><div class="card-body">
  <?php history_note_form(0, $id, $self); ?>
  <?php history_timeline(history_query('h.tenant_id = ?', [$id], 500), true, false); ?>
</div></div>
<?php endif; ?>
<?php admin_footer();
