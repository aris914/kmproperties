<?php
/* Admin › Reports (1.5.0): rent collection, unpaid rent aging, income & expenses, payments register,
   deposits held, occupancy & rent roll, lease expiry and repair costs. Each report: filters, totals,
   CSV download and a print view. */
require __DIR__ . '/includes/init.php';
require_admin();

$REPORTS = [
    'collection' => ['Rent collection by month', 'bi-bar-chart', 'Rent due vs collected for each month, with the collection rate.'],
    'arrears' => ['Unpaid rent (aging)', 'bi-exclamation-triangle', 'Every tenant who owes rent today, split by how late it is.'],
    'income' => ['Income & expenses by property', 'bi-cash-stack', 'Rent and other income received, repair costs and net per property.'],
    'payments' => ['Payments register', 'bi-receipt', 'Every payment received or refunded in a period, with totals by type and method.'],
    'deposits' => ['Deposits & advances', 'bi-safe', 'Security deposits and advance rent required, received, applied, refunded and held.'],
    'occupancy' => ['Occupancy & rent roll', 'bi-building-check', 'Rental units, who is renting, monthly rent roll and vacancy.'],
    'expiring' => ['Lease expiry', 'bi-calendar-x', 'Leases ending soon and recently ended.'],
    'maintenance' => ['Repairs & maintenance costs', 'bi-tools', 'Jobs and costs per property, and who paid for them.'],
];
$r = isset($REPORTS[get('r')]) ? get('r') : 'collection';
$today = date('Y-m-d');
$from = valid_date(get('from')) ?: date('Y-01-01');
$to = valid_date(get('to')) ?: $today;
if ($from > $to) {
    list($from, $to) = [$to, $from];
}
$fromM = preg_match('/^\d{4}-\d{2}$/', get('fm')) ? get('fm') . '-01' : month_add(month_first($today), -11);
$toM = preg_match('/^\d{4}-\d{2}$/', get('tm')) ? get('tm') . '-01' : month_first($today);
if ($fromM > $toM) {
    list($fromM, $toM) = [$toM, $fromM];
}
if ((int) ((strtotime($toM) - strtotime($fromM)) / 86400 / 28) > 36) {
    $fromM = month_add($toM, -35); // keep it to 3 years
}

$rep = ['head' => [], 'rows' => [], 'foot' => null, 'money' => [], 'cards' => [], 'chart' => '', 'filters' => 'dates', 'note' => ''];

switch ($r) {
    case 'collection':
        $rep['filters'] = 'months';
        $rep['head'] = ['Month', 'Leases', 'Rent due', 'Collected', 'Unpaid', 'Collection rate', 'Rent received (by payment date)'];
        $rep['money'] = [2, 3, 4, 6];
        $T = [0, 0, 0, 0];
        $bars = [];
        for ($m = $fromM; $m <= $toM; $m = month_add($m, 1)) {
            $ls = leases_for_month($m);
            $pm = rent_paid_map(array_column($ls, 'id'));
            $due = $paid = $bal = 0;
            $n = 0;
            foreach ($ls as $l) {
                foreach (rent_schedule($l, $pm[$l['id']] ?? [], $m) as $row) {
                    if ($row['period'] === $m) {
                        $due += $row['due'];
                        $paid += $row['paid'];
                        $bal += $row['balance'];
                        $n++;
                    }
                }
            }
            $cash = (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_type IN ('rent','advance') AND paid_on BETWEEN ? AND ?", [$m, date('Y-m-t', strtotime($m))]);
            $rep['rows'][] = [month_label($m, true), $n, $due, $paid, $bal, $due > 0 ? round($paid / $due * 100) . '%' : '—', $cash];
            $T[0] += $due; $T[1] += $paid; $T[2] += $bal; $T[3] += $cash;
            $bars[] = [month_label($m, true), $due, $paid];
        }
        $rep['foot'] = ['Total', '', $T[0], $T[1], $T[2], $T[0] > 0 ? round($T[1] / $T[0] * 100) . '%' : '—', $T[3]];
        $rep['cards'] = [['Rent due', money($T[0])], ['Collected', money($T[1])], ['Unpaid', money($T[2])], ['Collection rate', $T[0] > 0 ? round($T[1] / $T[0] * 100) . '%' : '—']];
        $max = max(1, max(array_map(function ($b) { return $b[1]; }, $bars ?: [[0, 1, 0]])));
        $rep['chart'] = '<div class="chart-bars">' . implode('', array_map(function ($b) use ($max) {
            return '<div class="cb" title="' . e($b[0] . ': due ' . money($b[1]) . ', collected ' . money($b[2])) . '"><div class="cb-due" style="height:' . round($b[1] / $max * 100, 1) . '%"><div class="cb-paid" style="height:' . ($b[1] > 0 ? round(min(1, $b[2] / $b[1]) * 100, 1) : 0) . '%"></div></div></div>';
        }, $bars)) . '</div><div class="chart-labels">' . implode('', array_map(function ($b) { return '<span>' . e(substr($b[0], 0, 3)) . '</span>'; }, $bars)) . '</div>'
            . '<div class="small text-muted mt-1"><span class="sw" style="background:var(--accent)"></span>Collected <span class="sw ms-3" style="background:color-mix(in srgb, var(--neutral) 30%, transparent)"></span>Rent due</div>';
        break;

    case 'arrears':
        $rep['filters'] = '';
        $rep['head'] = ['Tenant', 'Phone', 'Property', 'Lease', 'Oldest unpaid', '0–30 days', '31–60 days', '61–90 days', 'Over 90 days', 'Total unpaid'];
        $rep['money'] = [5, 6, 7, 8, 9];
        $T = [0, 0, 0, 0, 0];
        foreach (rent_arrears() as $a) {
            $b = [0, 0, 0, 0];
            $oldest = null;
            foreach ($a['rows'] as $row) {
                if ($row['balance'] > 0 && $row['due_date'] <= $today) {
                    $d = $row['days_late'];
                    $b[$d <= 30 ? 0 : ($d <= 60 ? 1 : ($d <= 90 ? 2 : 3))] += $row['balance'];
                    $oldest = $oldest ?: $row['label'];
                }
            }
            $l = $a['lease'];
            $rep['rows'][] = [$l['first_name'] . ' ' . $l['last_name'], $l['phone'], $l['title'], ucfirst($l['status']), $oldest, $b[0], $b[1], $b[2], $b[3], array_sum($b)];
            foreach ($b as $k => $v) { $T[$k] += $v; }
            $T[4] += array_sum($b);
        }
        $rep['foot'] = ['Total', '', '', '', '', $T[0], $T[1], $T[2], $T[3], $T[4]];
        $rep['cards'] = [['Tenants owing', count($rep['rows'])], ['Total unpaid', money($T[4])], ['Over 60 days', money($T[2] + $T[3])], ['Over 90 days', money($T[3])]];
        $rep['note'] = 'Days are counted from each month’s due date. Includes ended leases that still owe rent.';
        break;

    case 'income':
        $rep['head'] = ['Property', 'Rent & advance received', 'Deposit applied to rent', 'Other income', 'Total income', 'Repairs (owner)', 'Repairs (K&M)', 'Net'];
        $rep['money'] = [1, 2, 3, 4, 5, 6, 7];
        $inc = q_all("SELECT l.property_id,
                SUM(CASE WHEN pa.payment_type IN ('rent','advance') THEN pa.amount ELSE 0 END) AS rent,
                SUM(CASE WHEN pa.payment_type = 'deposit_applied' THEN pa.amount ELSE 0 END) AS dep,
                SUM(CASE WHEN pa.payment_type IN ('utilities','penalty','other') THEN pa.amount ELSE 0 END) AS other
            FROM payments pa JOIN leases l ON l.id = pa.lease_id WHERE pa.paid_on BETWEEN ? AND ? GROUP BY l.property_id", [$from, $to]);
        $exp = q_all("SELECT property_id, SUM(CASE WHEN charged_to = 'owner' THEN cost ELSE 0 END) AS owner, SUM(CASE WHEN charged_to = 'company' THEN cost ELSE 0 END) AS company
            FROM maintenance WHERE status = 'completed' AND completed_on BETWEEN ? AND ? GROUP BY property_id", [$from, $to]);
        $by = [];
        foreach ($inc as $x) { $by[$x['property_id']] = ['rent' => (float) $x['rent'], 'dep' => (float) $x['dep'], 'other' => (float) $x['other'], 'owner' => 0, 'company' => 0]; }
        foreach ($exp as $x) {
            if (!isset($by[$x['property_id']])) { $by[$x['property_id']] = ['rent' => 0, 'dep' => 0, 'other' => 0, 'owner' => 0, 'company' => 0]; }
            $by[$x['property_id']]['owner'] = (float) $x['owner'];
            $by[$x['property_id']]['company'] = (float) $x['company'];
        }
        $titles = $by ? array_column(q_all('SELECT id, title FROM properties WHERE id IN (' . implode(',', array_map('intval', array_keys($by))) . ')'), 'title', 'id') : [];
        $T = array_fill(0, 7, 0);
        foreach ($by as $pid => $x) {
            $income = $x['rent'] + $x['dep'] + $x['other'];
            $row = [$titles[$pid] ?? '#' . $pid, $x['rent'], $x['dep'], $x['other'], $income, $x['owner'], $x['company'], $income - $x['owner'] - $x['company']];
            $rep['rows'][] = $row;
            for ($k = 1; $k <= 7; $k++) { $T[$k - 1] += $row[$k]; }
        }
        usort($rep['rows'], function ($a, $b) { return $b[4] <=> $a[4]; });
        $rep['foot'] = array_merge(['Total'], $T);
        $rep['cards'] = [['Total income', money($T[3])], ['Repair costs', money($T[4] + $T[5])], ['Net', money($T[6])], ['Properties', count($rep['rows'])]];
        $rep['note'] = 'Income by payment date; repairs by completion date. Repairs charged to tenants or covered by warranty are not counted as expenses. Deposits held are not income.';
        break;

    case 'payments':
        $rep['filters'] = 'dates+payments';
        $types = payment_types();
        $ptype = isset($types[get('type')]) ? get('type') : '';
        $method = mb_substr(get('method'), 0, 40);
        $w = ['pa.paid_on BETWEEN ? AND ?'];
        $pr = [$from, $to];
        if ($ptype) { $w[] = 'pa.payment_type = ?'; $pr[] = $ptype; }
        if ($method !== '') { $w[] = 'pa.method = ?'; $pr[] = $method; }
        $rows = q_all('SELECT pa.*, t.first_name, t.last_name, p.title FROM payments pa JOIN leases l ON l.id = pa.lease_id JOIN tenants t ON t.id = l.tenant_id JOIN properties p ON p.id = l.property_id
                       WHERE ' . implode(' AND ', $w) . ' ORDER BY pa.paid_on, pa.id', $pr);
        $rep['head'] = ['Date', 'Tenant', 'Property', 'Type', 'Month', 'Method', 'Reference', 'Amount'];
        $rep['money'] = [7];
        $byType = [];
        $byMethod = [];
        $total = 0;
        foreach ($rows as $pa) {
            $amt = $pa['payment_type'] === 'deposit_refund' ? -$pa['amount'] : (float) $pa['amount'];
            $rep['rows'][] = [$pa['paid_on'], $pa['first_name'] . ' ' . $pa['last_name'], $pa['title'], $types[$pa['payment_type']], $pa['period_month'] ? month_label($pa['period_month'], true) : '', $pa['method'], $pa['reference'], $amt];
            $byType[$types[$pa['payment_type']]] = ($byType[$types[$pa['payment_type']]] ?? 0) + $amt;
            $m = $pa['method'] ?: '(none)';
            $byMethod[$m] = ($byMethod[$m] ?? 0) + $amt;
            $total += $amt;
        }
        $rep['foot'] = ['Total', count($rows) . ' payment(s)', '', '', '', '', '', $total];
        arsort($byType);
        $rep['cards'] = array_map(function ($k, $v) { return [$k, money($v)]; }, array_keys($byType), $byType);
        $rep['extra'] = $byMethod;
        $rep['methods'] = array_column(q_all('SELECT DISTINCT method FROM payments WHERE method IS NOT NULL ORDER BY method'), 'method');
        $rep['ptype'] = $ptype;
        $rep['method'] = $method;
        break;

    case 'deposits':
        $rep['filters'] = '';
        $rep['head'] = ['Tenant', 'Property', 'Lease', 'Deposit required', 'Received', 'Applied to rent', 'Refunded', 'Held now', 'Advance required', 'Advance received'];
        $rep['money'] = [3, 4, 5, 6, 7, 8, 9];
        $T = array_fill(0, 7, 0);
        foreach (q_all("SELECT l.*, t.first_name, t.last_name, p.title FROM leases l JOIN tenants t ON t.id = l.tenant_id JOIN properties p ON p.id = l.property_id ORDER BY l.status = 'active' DESC, t.last_name") as $l) {
            $m = lease_money($l);
            if ($l['status'] !== 'active' && $l['status'] !== 'upcoming' && $m['deposit_held'] <= 0.009) {
                continue; // closed and settled
            }
            $row = [$l['first_name'] . ' ' . $l['last_name'], $l['title'], ucfirst($l['status']), $m['deposit_required'], $m['deposit_received'], $m['deposit_applied'], $m['deposit_refunded'], $m['deposit_held'], $m['advance_required'], $m['advance_received']];
            $rep['rows'][] = $row;
            for ($k = 3; $k <= 9; $k++) { $T[$k - 3] += $row[$k]; }
        }
        $rep['foot'] = array_merge(['Total', '', ''], $T);
        $rep['cards'] = [['Deposits held', money($T[4])], ['Deposits not yet received', money(max(0, $T[0] - $T[1]))], ['Advance not yet received', money(max(0, $T[5] - $T[6]))], ['Leases', count($rep['rows'])]];
        $rep['note'] = 'Active and upcoming leases, plus ended leases that still hold a deposit (refund due).';
        break;

    case 'occupancy':
        $rep['filters'] = '';
        $rep['head'] = ['Property', 'Status', 'Tenant', 'Listing rent', 'Lease rent', 'Lease ends', 'Vacant since'];
        $rep['money'] = [3, 4];
        $units = q_all("SELECT p.id, p.title, p.status, p.price, p.created_at,
                (SELECT CONCAT(t.first_name, ' ', t.last_name) FROM leases l JOIN tenants t ON t.id = l.tenant_id WHERE l.property_id = p.id AND l.status = 'active' LIMIT 1) AS tenant,
                (SELECT monthly_rent FROM leases l WHERE l.property_id = p.id AND l.status = 'active' LIMIT 1) AS lease_rent,
                (SELECT end_date FROM leases l WHERE l.property_id = p.id AND l.status = 'active' LIMIT 1) AS lease_end,
                (SELECT MAX(COALESCE(move_out_date, end_date)) FROM leases l WHERE l.property_id = p.id AND l.status IN ('ended','terminated')) AS last_out
            FROM properties p WHERE p.listing_type = 'rent' AND p.approval = 'approved' AND p.status <> 'inactive' ORDER BY p.status = 'rented' DESC, p.title");
        $n = count($units);
        $rented = 0;
        $roll = 0;
        $potential = 0;
        foreach ($units as $u) {
            $isRented = $u['status'] === 'rented';
            $rented += $isRented ? 1 : 0;
            $roll += (float) $u['lease_rent'];
            $potential += (float) $u['price'];
            $vacant = $isRented || $u['status'] === 'reserved' ? '' : fmt_date($u['last_out'] ?: substr($u['created_at'], 0, 10));
            $rep['rows'][] = [$u['title'], ucfirst($u['status']), $u['tenant'] ?: '—', (float) $u['price'], $u['lease_rent'] !== null ? (float) $u['lease_rent'] : '', $u['lease_end'] ? fmt_date($u['lease_end']) : ($isRented ? 'open-ended' : ''), $vacant];
        }
        $rep['foot'] = ['Total', $rented . ' of ' . $n . ' rented', '', $potential, $roll, '', ''];
        $rep['cards'] = [['Rental units', $n], ['Occupancy', $n ? round($rented / $n * 100) . '%' : '—'], ['Monthly rent roll', money($roll)], ['Vacancy (listing rent)', money(max(0, $potential - array_sum(array_map(function ($u) { return $u['status'] === 'rented' ? (float) $u['price'] : 0; }, $units))))]];
        break;

    case 'expiring':
        $rep['filters'] = 'days';
        $days = in_array((int) get('d'), [30, 60, 90, 180, 365], true) ? (int) get('d') : 90;
        $rep['days'] = $days;
        $rep['head'] = ['Tenant', 'Phone', 'Property', 'Ends', 'Days left', 'Monthly rent', 'Status'];
        $rep['money'] = [5];
        $rows = q_all("SELECT l.*, t.first_name, t.last_name, t.phone, p.title FROM leases l JOIN tenants t ON t.id = l.tenant_id JOIN properties p ON p.id = l.property_id
                       WHERE (l.status = 'active' AND l.end_date IS NOT NULL AND l.end_date <= ?) OR (l.status IN ('ended','terminated') AND COALESCE(l.move_out_date, l.end_date) >= ?)
                       ORDER BY l.end_date", [date('Y-m-d', strtotime('+' . $days . ' days')), date('Y-m-d', strtotime('-30 days'))]);
        $soon = 0;
        foreach ($rows as $l) {
            $left = (int) floor((strtotime($l['end_date']) - strtotime($today)) / 86400);
            $soon += $l['status'] === 'active' ? 1 : 0;
            $rep['rows'][] = [$l['first_name'] . ' ' . $l['last_name'], $l['phone'], $l['title'], $l['end_date'], $l['status'] === 'active' ? $left : 'moved out ' . fmt_date($l['move_out_date'] ?: $l['end_date']), (float) $l['monthly_rent'], ucfirst($l['status'])];
        }
        $rep['cards'] = [['Ending within ' . $days . ' days', $soon], ['Ended in the last 30 days', count($rows) - $soon], ['Monthly rent at risk', money(array_sum(array_map(function ($l) { return $l['status'] === 'active' ? (float) $l['monthly_rent'] : 0; }, $rows)))]];
        break;

    case 'maintenance':
        $rep['head'] = ['Property', 'Jobs', 'Open', 'Completed', 'Avg. days to complete', 'Owner', 'Tenant', 'K&M', 'Warranty', 'Total cost'];
        $rep['money'] = [5, 6, 7, 8, 9];
        $rows = q_all("SELECT p.title, COUNT(*) AS jobs, SUM(m.status IN ('open','scheduled','in_progress')) AS open_jobs, SUM(m.status = 'completed') AS done,
                AVG(CASE WHEN m.status = 'completed' AND m.completed_on IS NOT NULL THEN DATEDIFF(m.completed_on, m.reported_on) END) AS avg_days,
                SUM(CASE WHEN m.charged_to = 'owner' THEN m.cost ELSE 0 END) AS owner, SUM(CASE WHEN m.charged_to = 'tenant' THEN m.cost ELSE 0 END) AS tenant,
                SUM(CASE WHEN m.charged_to = 'company' THEN m.cost ELSE 0 END) AS company, SUM(CASE WHEN m.charged_to = 'warranty' THEN m.cost ELSE 0 END) AS warranty, SUM(m.cost) AS total
            FROM maintenance m JOIN properties p ON p.id = m.property_id WHERE m.reported_on BETWEEN ? AND ? GROUP BY p.id, p.title ORDER BY total DESC", [$from, $to]);
        $T = array_fill(0, 9, 0);
        foreach ($rows as $x) {
            $row = [$x['title'], (int) $x['jobs'], (int) $x['open_jobs'], (int) $x['done'], $x['avg_days'] !== null ? round($x['avg_days'], 1) : '—', (float) $x['owner'], (float) $x['tenant'], (float) $x['company'], (float) $x['warranty'], (float) $x['total']];
            $rep['rows'][] = $row;
            foreach ([1, 2, 3, 5, 6, 7, 8, 9] as $k) { $T[$k - 1] += $row[$k]; }
        }
        $rep['foot'] = ['Total', $T[0], $T[1], $T[2], '', $T[4], $T[5], $T[6], $T[7], $T[8]];
        $cats = q_all("SELECT category, COUNT(*) AS n, SUM(cost) AS c FROM maintenance WHERE reported_on BETWEEN ? AND ? GROUP BY category ORDER BY c DESC", [$from, $to]);
        $rep['cards'] = array_merge([['Jobs', $T[0]], ['Total cost', money($T[8])]], array_map(function ($c) { return [ucfirst($c['category']) . ' (' . $c['n'] . ')', money($c['c'])]; }, array_slice($cats, 0, 4)));
        $rep['note'] = 'Jobs reported in the period.';
        break;
}

$fmt = function ($v, $k) use ($rep) {
    if (in_array($k, $rep['money'], true) && is_numeric($v)) {
        return ($v < 0 ? '− ' : '') . money(abs($v), 2);
    }
    return e($v);
};

if (get('csv')) {
    $file = 'report-' . $r . '-' . date('Y-m-d') . '.csv';
    audit('download', 'reports', $file, $REPORTS[$r][0]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [$REPORTS[$r][0] . ' — ' . setting('site_name', 'K&M Urban Properties') . ' — ' . date('Y-m-d H:i')]);
    fputcsv($out, $rep['head']);
    foreach ($rep['rows'] as $row) {
        fputcsv($out, $row);
    }
    if ($rep['foot']) {
        fputcsv($out, $rep['foot']);
    }
    fclose($out);
    exit;
}

$qs = function ($p = []) use ($r) { return url('admin/reports.php?' . http_build_query(array_filter(array_merge($_GET, ['r' => $r], $p), function ($v) { return $v !== null && $v !== ''; }))); };
$period = $rep['filters'] === 'months' ? month_label($fromM, true) . ' – ' . month_label($toM, true) : (strpos($rep['filters'], 'dates') === 0 ? fmt_date($from) . ' – ' . fmt_date($to) : 'as of ' . fmt_date($today));

admin_header('Reports', 'reports');
?>
<div class="row g-4">
  <div class="col-xl-3 no-print">
    <div class="list-group report-nav">
      <?php foreach ($REPORTS as $k => $x): ?>
        <a href="<?= url('admin/reports.php?r=' . $k) ?>" class="list-group-item list-group-item-action<?= $r === $k ? ' active' : '' ?>"><i class="bi <?= $x[1] ?> me-2"></i><?= e($x[0]) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="col-xl-9">
    <div class="print-title mb-2"><b><?= e(setting('site_name', 'K&M Urban Properties')) ?></b> · printed <?= date('M j, Y g:i A') ?></div>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
      <div><h2 class="h5 mb-0"><i class="bi <?= $REPORTS[$r][1] ?> me-1"></i><?= e($REPORTS[$r][0]) ?></h2><div class="hint"><?= e($REPORTS[$r][2]) ?> · <b><?= e($period) ?></b></div></div>
      <div class="d-flex gap-2 no-print">
        <a class="btn btn-sm btn-light" href="<?= $qs(['csv' => 1]) ?>"><i class="bi bi-download me-1"></i>CSV</a>
        <button class="btn btn-sm btn-light" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
      </div>
    </div>

    <?php if ($rep['filters']): ?>
      <form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3 no-print">
        <input type="hidden" name="r" value="<?= e($r) ?>">
        <?php if ($rep['filters'] === 'months'): ?>
          <div><label class="form-label small">From month</label><input type="month" name="fm" value="<?= e(substr($fromM, 0, 7)) ?>" class="form-control form-control-sm"></div>
          <div><label class="form-label small">To month</label><input type="month" name="tm" value="<?= e(substr($toM, 0, 7)) ?>" class="form-control form-control-sm"></div>
        <?php elseif ($rep['filters'] === 'days'): ?>
          <div><label class="form-label small">Ending within</label><select name="d" class="form-select form-select-sm" onchange="this.form.submit()"><?php foreach ([30, 60, 90, 180, 365] as $d): ?><option value="<?= $d ?>"<?= $rep['days'] === $d ? ' selected' : '' ?>><?= $d ?> days</option><?php endforeach; ?></select></div>
        <?php else: ?>
          <div><label class="form-label small">From</label><input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm"></div>
          <div><label class="form-label small">To</label><input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm"></div>
          <div class="btn-group btn-group-sm">
            <a class="btn btn-light" href="<?= $qs(['from' => date('Y-m-01'), 'to' => $today]) ?>">This month</a>
            <a class="btn btn-light" href="<?= $qs(['from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-t', strtotime('last day of last month'))]) ?>">Last month</a>
            <a class="btn btn-light" href="<?= $qs(['from' => date('Y-01-01'), 'to' => $today]) ?>">This year</a>
          </div>
          <?php if ($rep['filters'] === 'dates+payments'): ?>
            <div><label class="form-label small">Type</label><select name="type" class="form-select form-select-sm"><option value="">All types</option><?= select_options(payment_types(), $rep['ptype'], true) ?></select></div>
            <div><label class="form-label small">Method</label><select name="method" class="form-select form-select-sm"><option value="">All methods</option><?= select_options($rep['methods'], $rep['method']) ?></select></div>
          <?php endif; ?>
        <?php endif; ?>
        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Show</button>
      </form>
    <?php endif; ?>

    <?php if ($rep['cards']): ?>
      <div class="row g-2 mb-3">
        <?php foreach ($rep['cards'] as $c): ?>
          <div class="col-6 col-md-3"><div class="bkbox mb-0 py-2"><div class="hint"><?= e($c[0]) ?></div><div class="fw-semibold fs-6"><?= e($c[1]) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($rep['chart']): ?><div class="bkbox"><?= $rep['chart'] ?></div><?php endif; ?>

    <div class="card mb-3"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
      <thead><tr><?php foreach ($rep['head'] as $k => $h): ?><th class="<?= in_array($k, $rep['money'], true) ? 'text-end' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead>
      <tbody>
      <?php if (!$rep['rows']): ?><tr><td colspan="<?= count($rep['head']) ?>" class="text-center text-muted py-4">Nothing to show for this period.</td></tr><?php endif; ?>
      <?php foreach ($rep['rows'] as $row): ?>
        <tr><?php foreach ($row as $k => $v): ?><td class="<?= in_array($k, $rep['money'], true) ? 'text-end text-nowrap' : 'small' ?>"><?= $fmt($v, $k) ?></td><?php endforeach; ?></tr>
      <?php endforeach; ?>
      </tbody>
      <?php if ($rep['foot'] && $rep['rows']): ?>
        <tfoot><tr class="fw-semibold"><?php foreach ($rep['foot'] as $k => $v): ?><td class="<?= in_array($k, $rep['money'], true) ? 'text-end text-nowrap' : '' ?>"><?= $fmt($v, $k) ?></td><?php endforeach; ?></tr></tfoot>
      <?php endif; ?>
    </table></div></div>

    <?php if (!empty($rep['extra'])): ?>
      <div class="bkbox" style="max-width:420px"><h2>By method</h2>
        <table class="table table-sm mb-0"><tbody><?php foreach ($rep['extra'] as $k => $v): ?><tr><td><?= e($k) ?></td><td class="text-end"><?= money($v, 2) ?></td></tr><?php endforeach; ?></tbody></table>
      </div>
    <?php endif; ?>
    <?php if ($rep['note']): ?><p class="hint"><?= e($rep['note']) ?></p><?php endif; ?>
  </div>
</div>
<?php admin_footer();
