<?php
/* Admin › Rent Collection (1.5.0): who has paid this month's rent and who has not.
   Pick a month: every lease that owes rent then, with its due date, amount due, paid, balance and status,
   plus unpaid balances from earlier months. Record payments and send reminders from here. */
require __DIR__ . '/includes/init.php';
require_admin();

$ym = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') . '-01' : month_first(date('Y-m-d'));
$filter = in_array(get('f'), ['unpaid', 'paid', 'upcoming'], true) ? get('f') : '';
$today = date('Y-m-d');

$leases = leases_for_month($ym);
$paid = rent_paid_map(array_column($leases, 'id'));
$rows = [];
$sum = ['due' => 0, 'paid' => 0, 'balance' => 0, 'overdue' => 0, 'n' => ['paid' => 0, 'due' => 0, 'overdue' => 0, 'upcoming' => 0]];
foreach ($leases as $l) {
    $sched = rent_schedule($l, $paid[$l['id']] ?? [], $ym);
    $row = null;
    $arrears = 0;
    foreach ($sched as $r) {
        if ($r['period'] === $ym) {
            $row = $r;
        } elseif ($r['period'] < $ym && $r['due_date'] <= $today) {
            $arrears += $r['balance'];
        }
    }
    if (!$row) {
        continue; // moved out before this month's due date
    }
    $sum['due'] += $row['due'];
    $sum['paid'] += $row['paid'];
    $sum['balance'] += $row['balance'];
    if ($row['status'] === 'overdue') {
        $sum['overdue'] += $row['balance'];
    }
    $sum['n'][$row['status']]++;
    $isUnpaid = in_array($row['status'], ['due', 'overdue'], true);
    if ($filter === 'unpaid' && !$isUnpaid) { continue; }
    if ($filter === 'paid' && $row['status'] !== 'paid') { continue; }
    if ($filter === 'upcoming' && $row['status'] !== 'upcoming') { continue; }
    $rows[] = ['l' => $l, 'r' => $row, 'arrears' => $arrears];
}
// unpaid first, then by due date
usort($rows, function ($a, $b) {
    $o = ['overdue' => 0, 'due' => 1, 'upcoming' => 2, 'paid' => 3];
    return [$o[$a['r']['status']], $a['r']['due_date']] <=> [$o[$b['r']['status']], $b['r']['due_date']];
});
$rate = $sum['due'] > 0 ? round($sum['paid'] / $sum['due'] * 100) : 0;

if (get('csv')) {
    audit('download', 'payments', 'rent-collection-' . substr($ym, 0, 7) . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rent-collection-' . substr($ym, 0, 7) . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Month', 'Tenant', 'Phone', 'Property', 'Due date', 'Rent', 'Paid', 'Balance', 'Status', 'Earlier unpaid']);
    foreach ($rows as $x) {
        fputcsv($out, [month_label($ym), $x['l']['first_name'] . ' ' . $x['l']['last_name'], $x['l']['phone'], $x['l']['title'], $x['r']['due_date'], $x['r']['due'], $x['r']['paid'], $x['r']['balance'], $x['r']['status'], $x['arrears']]);
    }
    fclose($out);
    exit;
}

$prev = substr(month_add($ym, -1), 0, 7);
$next = substr(month_add($ym, 1), 0, 7);
$link = function ($p) use ($ym, $filter) { return url('admin/collection.php?' . http_build_query(array_filter(array_merge(['m' => substr($ym, 0, 7), 'f' => $filter], $p)))); };

admin_header('Rent Collection', 'collection');
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="btn-group">
    <a class="btn btn-light" href="<?= $link(['m' => $prev]) ?>" title="Previous month"><i class="bi bi-chevron-left"></i></a>
    <form method="get" class="d-flex"><input type="month" name="m" value="<?= e(substr($ym, 0, 7)) ?>" class="form-control rounded-0" onchange="this.form.submit()"><?php if ($filter): ?><input type="hidden" name="f" value="<?= e($filter) ?>"><?php endif; ?></form>
    <a class="btn btn-light" href="<?= $link(['m' => $next]) ?>" title="Next month"><i class="bi bi-chevron-right"></i></a>
  </div>
  <?php if (substr($ym, 0, 7) !== date('Y-m')): ?><a class="btn btn-sm btn-link" href="<?= url('admin/collection.php') ?>">This month</a><?php endif; ?>
  <div class="ms-auto d-flex gap-2">
    <a class="btn btn-light btn-sm" href="<?= $link(['csv' => 1]) ?>"><i class="bi bi-download me-1"></i>CSV</a>
    <button class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-6 col-lg-3"><div class="stat-card stat-navy"><i class="bi bi-receipt"></i><div><div class="stat-value fs-5"><?= money($sum['due']) ?></div><div class="stat-label">Rent due in <?= e(month_label($ym)) ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card stat-teal"><i class="bi bi-cash-coin"></i><div><div class="stat-value fs-5"><?= money($sum['paid']) ?></div><div class="stat-label">Collected · <?= $rate ?>%</div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card <?= $sum['balance'] > 0 ? 'stat-danger' : 'stat-teal' ?>"><i class="bi bi-hourglass-split"></i><div><div class="stat-value fs-5"><?= money($sum['balance']) ?></div><div class="stat-label">Still to collect<?= $sum['overdue'] > 0 ? ' · ' . money($sum['overdue']) . ' overdue' : '' ?></div></div></div></div>
  <div class="col-6 col-lg-3"><div class="stat-card stat-gold"><i class="bi bi-people"></i><div><div class="stat-value fs-5"><?= $sum['n']['paid'] ?> / <?= array_sum($sum['n']) ?></div><div class="stat-label">Paid · <?= $sum['n']['overdue'] ?> overdue · <?= $sum['n']['due'] ?> due · <?= $sum['n']['upcoming'] ?> upcoming</div></div></div></div>
</div>
<div class="progress mb-4" style="height:10px" role="progressbar" aria-label="Collected" aria-valuenow="<?= $rate ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= $rate ?>%;background:var(--accent)"></div></div>

<ul class="nav nav-tabs record-tabs mb-3">
  <?php foreach (['' => 'All', 'unpaid' => 'Unpaid (due & overdue)', 'paid' => 'Paid', 'upcoming' => 'Upcoming'] as $k => $lab): ?>
    <li class="nav-item"><a class="nav-link<?= $filter === $k ? ' active' : '' ?>" href="<?= $link(['f' => $k]) ?>"><?= $lab ?></a></li>
  <?php endforeach; ?>
</ul>

<div class="card mb-4"><div class="table-responsive"><table class="table table-hover align-middle">
  <thead><tr><th>Tenant / property</th><th>Due date</th><th class="text-end">Rent</th><th class="text-end">Paid</th><th class="text-end">Balance</th><th>Status</th><th class="text-end no-print"></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No leases<?= $filter ? ' in this list' : '' ?> for <?= e(month_label($ym)) ?>.</td></tr><?php endif; ?>
  <?php foreach ($rows as $x): $l = $x['l']; $r = $x['r'];
      $msg = rent_reminder_text($l, $r, $x['arrears']);
      $phone = preg_replace('/[^0-9+]/', '', (string) $l['phone']); ?>
    <tr class="<?= $r['status'] === 'overdue' ? 'table-danger' : '' ?>">
      <td><a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>" class="fw-semibold text-decoration-none"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></a>
        <div class="small text-muted"><?= e($l['title']) ?><?= $l['unit_no'] ? ' · Unit ' . e($l['unit_no']) : '' ?></div>
        <?php if ($x['arrears'] > 0): ?><div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> <?= money($x['arrears'], 2) ?> unpaid from earlier months</div><?php endif; ?></td>
      <td class="small text-nowrap"><?= fmt_date($r['due_date']) ?><?php if ($r['status'] === 'overdue'): ?><div class="text-danger"><?= $r['days_late'] ?> day(s) late</div><?php endif; ?></td>
      <td class="text-end"><?= money($r['due'], 2) ?></td>
      <td class="text-end"><?= $r['paid'] > 0 ? money($r['paid'], 2) : '—' ?></td>
      <td class="text-end fw-semibold"><?= $r['balance'] > 0 ? money($r['balance'], 2) : '—' ?></td>
      <td><?= rent_status_badge($r['status'], $r['paid']) ?></td>
      <td class="text-end text-nowrap no-print">
        <?php if ($r['balance'] > 0): ?>
          <a href="<?= url('admin/lease-view.php?id=' . $l['id'] . '&pay=' . substr($ym, 0, 7)) ?>#pay" class="btn btn-sm btn-primary"><i class="bi bi-cash me-1"></i>Record payment</a>
          <div class="btn-group">
            <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" title="Send a reminder"><i class="bi bi-bell"></i></button>
            <ul class="dropdown-menu dropdown-menu-end">
              <?php if ($phone): ?><li><a class="dropdown-item" href="sms:<?= e($phone) ?>?body=<?= rawurlencode($msg) ?>"><i class="bi bi-chat-text me-2"></i>Text message</a></li>
              <li><a class="dropdown-item" href="viber://forward?text=<?= rawurlencode($msg) ?>"><i class="bi bi-telephone-inbound me-2"></i>Viber</a></li><?php endif; ?>
              <?php if ($l['email']): ?><li><a class="dropdown-item" href="mailto:<?= e($l['email']) ?>?subject=<?= rawurlencode('Rent reminder — ' . month_label($ym)) ?>&body=<?= rawurlencode($msg) ?>"><i class="bi bi-envelope me-2"></i>Email</a></li><?php endif; ?>
              <li><button type="button" class="dropdown-item copy-msg" data-msg="<?= e($msg) ?>"><i class="bi bi-clipboard me-2"></i>Copy message</button></li>
            </ul>
          </div>
        <?php else: ?>
          <a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>" class="btn btn-sm btn-light">Open</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div>

<?php $arrearsAll = rent_arrears(); if ($arrearsAll): ?>
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between"><span><i class="bi bi-exclamation-triangle me-1 text-danger"></i>All unpaid rent as of today</span><a class="small" href="<?= url('admin/reports.php?r=arrears') ?>">Aging report</a></div>
  <div class="table-responsive"><table class="table table-sm align-middle">
    <thead><tr><th>Tenant / property</th><th>Unpaid months</th><th class="text-end">Total unpaid</th><th class="no-print"></th></tr></thead>
    <tbody>
    <?php foreach ($arrearsAll as $a): $l = $a['lease']; $months = array_filter($a['rows'], function ($r) { return $r['balance'] > 0 && $r['due_date'] <= date('Y-m-d'); }); ?>
      <tr>
        <td><a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>"><?= e($l['first_name'] . ' ' . $l['last_name']) ?></a><div class="small text-muted"><?= e($l['title']) ?><?= $l['status'] !== 'active' ? ' · lease ' . e($l['status']) : '' ?></div></td>
        <td class="small"><?= e(implode(', ', array_map(function ($r) { return $r['label']; }, $months))) ?></td>
        <td class="text-end fw-semibold text-danger"><?= money($a['totals']['balance'], 2) ?></td>
        <td class="text-end no-print"><a href="<?= url('admin/lease-view.php?id=' . $l['id']) ?>#pay" class="btn btn-sm btn-light">Record payment</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<p class="hint">A month is <b>due</b> on the lease’s due date and turns <b>overdue</b> after its grace days. Advance rent and deposit applied to rent count as paid for the month they cover. Change a lease’s due day on its Edit / renew page.</p>
<script>
document.querySelectorAll('.copy-msg').forEach(function (b) {
  b.addEventListener('click', function () { navigator.clipboard && navigator.clipboard.writeText(b.dataset.msg).then(function () { b.innerHTML = '<i class="bi bi-check2 me-2"></i>Copied'; }); });
});
</script>
<?php admin_footer();
