<?php
/* =====================================================================
   Rent collection engine (1.5.0)
   Every lease has a monthly schedule from its start month to its end (or move-out) month.
   Each month is due on the lease's due day (capped at the month's last day; the first month is
   never due before the start date). A month is paid by payments of type rent, advance or
   deposit_applied whose period_month is that month. Status of a month:
     paid      — fully paid
     upcoming  — due date still ahead
     due       — due today or within the grace days
     overdue   — past the due date + grace days and not fully paid (partly paid shows the amount)
   Deposit held = deposit received − deposit applied to rent − deposit refunded.
   ===================================================================== */

function payment_types()
{
    return [
        'rent' => 'Rent', 'advance' => 'Advance rent', 'deposit' => 'Security deposit', 'deposit_applied' => 'Deposit applied to rent',
        'deposit_refund' => 'Deposit refund', 'utilities' => 'Utilities', 'penalty' => 'Penalty / late fee', 'other' => 'Other',
    ];
}

/** Payment types that settle a month's rent. */
function rent_settling_types()
{
    return ['rent', 'advance', 'deposit_applied'];
}

function rent_status_badge($status, $paid = 0)
{
    $map = ['paid' => ['success', 'Paid'], 'upcoming' => ['light', 'Upcoming'], 'due' => ['warning', 'Due'], 'overdue' => ['danger', 'Overdue'], 'partial' => ['warning', 'Partly paid']];
    $s = isset($map[$status]) ? $map[$status] : ['secondary', ucfirst($status)];
    $label = $s[1];
    if ($status === 'overdue' && $paid > 0) {
        $label = 'Overdue (part paid)';
    }
    return '<span class="badge text-bg-' . $s[0] . '">' . $label . '</span>';
}

function month_first($date)
{
    return date('Y-m-01', strtotime($date));
}

function month_add($ym1, $n)
{
    return date('Y-m-01', strtotime($ym1 . ' ' . ($n >= 0 ? '+' : '') . (int) $n . ' months'));
}

function month_label($ym1, $short = false)
{
    return date($short ? 'M Y' : 'F Y', strtotime($ym1));
}

/** Due date of a month for a due day (31 in February = 28/29). */
function rent_due_date($ym1, $dueDay)
{
    $last = (int) date('t', strtotime($ym1));
    return date('Y-m-', strtotime($ym1)) . str_pad((string) max(1, min((int) $dueDay, $last)), 2, '0', STR_PAD_LEFT);
}

/** Last day rent is owed: move-out date, else end date, else null (open-ended). */
function lease_last_date(array $l)
{
    if (!empty($l['move_out_date'])) {
        return $l['move_out_date'];
    }
    return !empty($l['end_date']) ? $l['end_date'] : null;
}

/** [lease_id => [Y-m-01 => amount paid]] for the given leases. */
function rent_paid_map(array $leaseIds)
{
    $map = [];
    $ids = array_values(array_filter(array_map('intval', $leaseIds)));
    if (!$ids) {
        return $map;
    }
    $rows = q_all("SELECT lease_id, period_month, SUM(amount) AS paid FROM payments
                   WHERE lease_id IN (" . implode(',', $ids) . ") AND period_month IS NOT NULL AND payment_type IN ('rent','advance','deposit_applied')
                   GROUP BY lease_id, period_month");
    foreach ($rows as $r) {
        $map[(int) $r['lease_id']][$r['period_month']] = (float) $r['paid'];
    }
    return $map;
}

/**
 * Monthly schedule of a lease up to $until (Y-m-01; default: next month).
 * Each row: period, label, due_date, due, paid, balance, status, days_late.
 */
function rent_schedule(array $l, array $paidByPeriod, $until = null, $today = null)
{
    $today = $today ?: date('Y-m-d');
    $rows = [];
    if (empty($l['start_date']) || (float) $l['monthly_rent'] <= 0) {
        return $rows;
    }
    $start = month_first($l['start_date']);
    $until = $until ?: month_add(month_first($today), 1);
    $lastDate = lease_last_date($l);
    $last = $lastDate ? min($until, month_first($lastDate)) : $until;
    $grace = isset($l['grace_days']) ? (int) $l['grace_days'] : 3;
    $rent = (float) $l['monthly_rent'];
    for ($p = $start; $p <= $last; $p = month_add($p, 1)) {
        $due = rent_due_date($p, $l['due_day'] ?: 1);
        if ($p === $start && $due < $l['start_date']) {
            $due = $l['start_date'];
        }
        if ($lastDate && $due > $lastDate) {
            continue; // moved out before this month's due date
        }
        $paid = isset($paidByPeriod[$p]) ? $paidByPeriod[$p] : 0.0;
        $bal = max(0, round($rent - $paid, 2));
        if ($bal <= 0.009) {
            $status = 'paid';
        } elseif ($today < $due) {
            $status = 'upcoming';
        } elseif ($today <= date('Y-m-d', strtotime($due . ' +' . $grace . ' days'))) {
            $status = 'due';
        } else {
            $status = 'overdue';
        }
        $rows[] = [
            'period' => $p, 'label' => month_label($p, true), 'due_date' => $due, 'due' => $rent, 'paid' => $paid, 'balance' => $bal,
            'status' => $status, 'days_late' => $bal > 0 && $today > $due ? (int) floor((strtotime($today) - strtotime($due)) / 86400) : 0,
        ];
    }
    return $rows;
}

/** Totals of a schedule, counting only months already due. */
function rent_totals(array $rows, $today = null)
{
    $today = $today ?: date('Y-m-d');
    $t = ['due' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'overdue' => 0.0, 'overdue_months' => 0, 'paid_ahead' => 0.0, 'next' => null];
    foreach ($rows as $r) {
        if ($r['due_date'] <= $today) {
            $t['due'] += $r['due'];
            $t['paid'] += $r['paid'];
            $t['balance'] += $r['balance'];
            if ($r['status'] === 'overdue') {
                $t['overdue'] += $r['balance'];
                $t['overdue_months']++;
            }
        } else {
            $t['paid_ahead'] += $r['paid'];
            if (!$t['next'] && $r['balance'] > 0) {
                $t['next'] = $r;
            }
        }
    }
    return $t;
}

/** Oldest month with a balance (for the payment form), or the next month. */
function rent_next_period(array $rows)
{
    foreach ($rows as $r) {
        if ($r['balance'] > 0) {
            return $r;
        }
    }
    return $rows ? end($rows) : null;
}

/** Deposit and advance summary of a lease. */
function lease_money(array $l)
{
    $sums = [];
    foreach (q_all('SELECT payment_type, SUM(amount) AS s FROM payments WHERE lease_id = ? GROUP BY payment_type', [(int) $l['id']]) as $r) {
        $sums[$r['payment_type']] = (float) $r['s'];
    }
    $g = function ($k) use ($sums) { return isset($sums[$k]) ? $sums[$k] : 0.0; };
    return [
        'deposit_required' => (float) $l['deposit'], 'deposit_received' => $g('deposit'), 'deposit_applied' => $g('deposit_applied'),
        'deposit_refunded' => $g('deposit_refund'), 'deposit_held' => round($g('deposit') - $g('deposit_applied') - $g('deposit_refund'), 2),
        'advance_required' => (float) $l['advance'], 'advance_received' => $g('advance'),
        'rent_received' => $g('rent'), 'other_received' => $g('utilities') + $g('penalty') + $g('other'), 'sums' => $sums,
    ];
}

/** Leases that owe rent in a month (active, or ended / terminated after it started). */
function leases_for_month($ym1)
{
    $end = date('Y-m-t', strtotime($ym1));
    return q_all("SELECT l.*, p.title, p.code, p.unit_no, p.building, t.first_name, t.last_name, t.phone, t.email
                  FROM leases l JOIN properties p ON p.id = l.property_id JOIN tenants t ON t.id = l.tenant_id
                  WHERE l.start_date <= ? AND l.status IN ('active','ended','terminated')
                    AND (COALESCE(l.move_out_date, l.end_date) IS NULL OR COALESCE(l.move_out_date, l.end_date) >= ?)
                  ORDER BY l.due_day, t.last_name", [$end, $ym1]);
}

/** Every lease with unpaid months (arrears) as of today: [lease + totals + rows]. */
function rent_arrears()
{
    $leases = q_all("SELECT l.*, p.title, t.first_name, t.last_name, t.phone, t.email FROM leases l
                     JOIN properties p ON p.id = l.property_id JOIN tenants t ON t.id = l.tenant_id
                     WHERE l.status IN ('active','ended','terminated')");
    $paid = rent_paid_map(array_column($leases, 'id'));
    $out = [];
    foreach ($leases as $l) {
        $rows = rent_schedule($l, isset($paid[$l['id']]) ? $paid[$l['id']] : [], month_first(date('Y-m-d')));
        $tot = rent_totals($rows);
        if ($tot['balance'] > 0.009) {
            $out[] = ['lease' => $l, 'rows' => $rows, 'totals' => $tot];
        }
    }
    usort($out, function ($a, $b) { return $b['totals']['balance'] <=> $a['totals']['balance']; });
    return $out;
}

/** Text for an SMS / email rent reminder. */
function rent_reminder_text(array $l, array $row, $arrears = 0)
{
    $name = trim($l['first_name']);
    $msg = 'Hi ' . $name . ', this is a friendly reminder from ' . setting('site_name', 'K&M Urban Properties') . ': your rent of '
        . money($row['balance'], 2) . ' for ' . month_label($row['period']) . ' (' . $l['title'] . ') ' . ($row['due_date'] < date('Y-m-d') ? 'was due on ' : 'is due on ') . fmt_date($row['due_date']) . '.';
    if ($arrears > 0) {
        $msg .= ' Previous unpaid balance: ' . money($arrears, 2) . '.';
    }
    return $msg . ' Thank you!';
}
