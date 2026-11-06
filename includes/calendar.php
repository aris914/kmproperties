<?php
/* =====================================================================
   Calendar (1.6.0): everything with a date, in one place.
     event   — meetings, viewings, inspections, signings, move-ins … (events table, added by admins)
     rent    — each lease's monthly due date, coloured by paid / upcoming / due / overdue (from includes/rent.php)
     lease   — lease start and end dates
     repair  — repairs and maintenance scheduled for a day
   ===================================================================== */

function event_types()
{
    return [
        'viewing' => ['Viewing', 'bi-eye', 'Show a unit to a client'], 'meeting' => ['Meeting', 'bi-people', 'With a tenant, owner or buyer'],
        'inspection' => ['Inspection', 'bi-clipboard-check', 'Unit check'], 'signing' => ['Contract signing', 'bi-pen', 'Lease or sale documents'],
        'move_in' => ['Move-in', 'bi-box-arrow-in-right', 'Turnover to a tenant'], 'move_out' => ['Move-out', 'bi-box-arrow-right', 'Unit handback'],
        'collection' => ['Collection', 'bi-cash-coin', 'Collect rent or documents'], 'other' => ['Other', 'bi-calendar-event', ''],
    ];
}

function event_with_types()
{
    return ['tenant' => 'Tenant', 'owner' => 'Property owner', 'buyer' => 'Buyer / prospect', 'other' => 'Other'];
}

/**
 * All calendar items from $from to $to (Y-m-d, inclusive), sorted by date and time.
 * Each: date, time (H:i or ''), end_time, title, kind, sub, cls, url, status, icon, meta.
 */
function calendar_items($from, $to, array $kinds = ['event', 'rent', 'lease', 'repair'])
{
    $items = [];
    $today = date('Y-m-d');

    if (in_array('event', $kinds, true)) {
        try {
            $rows = q_all("SELECT e.*, p.title AS property_title, CONCAT(t.first_name, ' ', t.last_name) AS tenant_name
                           FROM events e LEFT JOIN properties p ON p.id = e.property_id LEFT JOIN tenants t ON t.id = e.tenant_id
                           WHERE e.start_at BETWEEN ? AND ? ORDER BY e.start_at", [$from . ' 00:00:00', $to . ' 23:59:59']);
        } catch (Exception $ex) {
            $rows = [];
        }
        $types = event_types();
        foreach ($rows as $e) {
            $who = $e['tenant_name'] ?: $e['contact_name'];
            $items[] = [
                'date' => substr($e['start_at'], 0, 10), 'time' => $e['all_day'] ? '' : substr($e['start_at'], 11, 5),
                'end_time' => $e['end_at'] && !$e['all_day'] ? substr($e['end_at'], 11, 5) : '',
                'title' => $e['title'], 'kind' => 'event', 'sub' => $e['event_type'], 'id' => (int) $e['id'],
                'cls' => 'ev-' . $e['event_type'] . ($e['status'] !== 'scheduled' ? ' ev-' . $e['status'] : ''),
                'url' => url('admin/calendar.php?event=' . $e['id'] . '&m=' . substr($e['start_at'], 0, 7)), 'status' => $e['status'],
                'icon' => $types[$e['event_type']][1] ?? 'bi-calendar-event',
                'meta' => trim(implode(' · ', array_filter([$types[$e['event_type']][0] ?? '', $who, $e['property_title'], $e['location']]))),
                'row' => $e,
            ];
        }
    }

    if (in_array('rent', $kinds, true)) {
        for ($m = month_first($from); $m <= month_first($to); $m = month_add($m, 1)) {
            $leases = leases_for_month($m);
            $paid = rent_paid_map(array_column($leases, 'id'));
            foreach ($leases as $l) {
                foreach (rent_schedule($l, $paid[$l['id']] ?? [], $m) as $r) {
                    if ($r['period'] !== $m || $r['due_date'] < $from || $r['due_date'] > $to) {
                        continue;
                    }
                    $name = $l['first_name'] . ' ' . $l['last_name'];
                    $items[] = [
                        'date' => $r['due_date'], 'time' => '', 'end_time' => '', 'kind' => 'rent', 'sub' => $r['status'], 'status' => $r['status'],
                        'title' => 'Rent ' . money($r['balance'] > 0 ? $r['balance'] : $r['due']) . ' — ' . $name,
                        'cls' => 'rent-' . $r['status'], 'icon' => $r['status'] === 'paid' ? 'bi-check-circle' : ($r['status'] === 'overdue' ? 'bi-exclamation-circle' : 'bi-cash'),
                        'url' => url('admin/lease-view.php?id=' . $l['id'] . ($r['balance'] > 0 ? '&pay=' . substr($m, 0, 7) . '#pay' : '')),
                        'meta' => $l['title'] . ' · ' . ($r['status'] === 'paid' ? 'paid' : ($r['status'] === 'overdue' ? $r['days_late'] . ' day(s) overdue' : ($r['paid'] > 0 ? 'part paid' : $r['status']))),
                    ];
                }
            }
        }
    }

    if (in_array('lease', $kinds, true)) {
        $ls = q_all("SELECT l.id, l.start_date, l.end_date, l.status, p.title, t.first_name, t.last_name FROM leases l
                     JOIN properties p ON p.id = l.property_id JOIN tenants t ON t.id = l.tenant_id
                     WHERE l.status IN ('active','upcoming') AND ((l.start_date BETWEEN ? AND ?) OR (l.end_date BETWEEN ? AND ?))", [$from, $to, $from, $to]);
        foreach ($ls as $l) {
            $name = $l['first_name'] . ' ' . $l['last_name'];
            if ($l['start_date'] >= $from && $l['start_date'] <= $to) {
                $items[] = ['date' => $l['start_date'], 'time' => '', 'end_time' => '', 'kind' => 'lease', 'sub' => 'start', 'status' => $l['status'], 'cls' => 'lease-start', 'icon' => 'bi-key',
                    'title' => 'Lease starts — ' . $name, 'meta' => $l['title'], 'url' => url('admin/lease-view.php?id=' . $l['id'])];
            }
            if ($l['end_date'] && $l['end_date'] >= $from && $l['end_date'] <= $to) {
                $items[] = ['date' => $l['end_date'], 'time' => '', 'end_time' => '', 'kind' => 'lease', 'sub' => 'end', 'status' => $l['status'], 'cls' => 'lease-end', 'icon' => 'bi-door-open',
                    'title' => 'Lease ends — ' . $name, 'meta' => $l['title'], 'url' => url('admin/lease-view.php?id=' . $l['id'])];
            }
        }
    }

    if (in_array('repair', $kinds, true)) {
        $js = q_all("SELECT m.id, m.title, m.scheduled_on, m.status, m.priority, m.contractor, p.title AS property_title FROM maintenance m JOIN properties p ON p.id = m.property_id
                     WHERE m.scheduled_on BETWEEN ? AND ? AND m.status IN ('open','scheduled','in_progress')", [$from, $to]);
        foreach ($js as $j) {
            $items[] = ['date' => $j['scheduled_on'], 'time' => '', 'end_time' => '', 'kind' => 'repair', 'sub' => $j['status'], 'status' => $j['status'],
                'cls' => 'repair' . ($j['scheduled_on'] < $today ? ' repair-late' : ''), 'icon' => 'bi-tools', 'title' => $j['title'],
                'meta' => trim($j['property_title'] . ($j['contractor'] ? ' · ' . $j['contractor'] : '')), 'url' => url('admin/maintenance-edit.php?id=' . $j['id'])];
        }
    }

    usort($items, function ($a, $b) {
        $ka = [$a['date'], $a['time'] === '' ? '99' : $a['time'], $a['kind'] === 'event' ? 0 : 1];
        $kb = [$b['date'], $b['time'] === '' ? '99' : $b['time'], $b['kind'] === 'event' ? 0 : 1];
        return $ka <=> $kb;
    });
    return $items;
}

/** One event as an iCalendar (.ics) VEVENT, for Google / Outlook / phone calendars. */
function event_ics(array $e)
{
    $esc = function ($s) { return str_replace(["\\", ';', ',', "\r\n", "\n"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], (string) $s); };
    $tz = date_default_timezone_get();
    $start = strtotime($e['start_at']);
    $end = $e['end_at'] ? strtotime($e['end_at']) : $start + 3600;
    $lines = ['BEGIN:VEVENT', 'UID:kmup-event-' . (int) $e['id'] . '@' . preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['HTTP_HOST'] ?? 'kmproperties'),
        'DTSTAMP:' . gmdate('Ymd\THis\Z')];
    if (!empty($e['all_day'])) {
        $lines[] = 'DTSTART;VALUE=DATE:' . date('Ymd', $start);
        $lines[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime('+1 day', $start));
    } else {
        $lines[] = 'DTSTART;TZID=' . $tz . ':' . date('Ymd\THis', $start);
        $lines[] = 'DTEND;TZID=' . $tz . ':' . date('Ymd\THis', $end);
    }
    $desc = array_filter([
        isset(event_types()[$e['event_type']]) ? event_types()[$e['event_type']][0] : '',
        $e['contact_name'] ? 'With: ' . $e['contact_name'] . ($e['contact_phone'] ? ' (' . $e['contact_phone'] . ')' : '') : '',
        $e['notes'],
    ]);
    $lines[] = 'SUMMARY:' . $esc($e['title']);
    if ($e['location']) {
        $lines[] = 'LOCATION:' . $esc($e['location']);
    }
    if ($desc) {
        $lines[] = 'DESCRIPTION:' . $esc(implode("\n", $desc));
    }
    $lines[] = 'STATUS:' . ($e['status'] === 'cancelled' ? 'CANCELLED' : 'CONFIRMED');
    $lines[] = 'END:VEVENT';
    return implode("\r\n", $lines);
}

function ics_wrap(array $vevents)
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//K&M Urban Properties//Calendar//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\n"
        . implode("\r\n", $vevents) . "\r\nEND:VCALENDAR\r\n";
}

/** Upcoming events for a property or a tenant (for their pages). */
function upcoming_events($field, $id, $limit = 5)
{
    if (!in_array($field, ['property_id', 'tenant_id'], true)) {
        return [];
    }
    try {
        return q_all("SELECT * FROM events WHERE $field = ? AND status = 'scheduled' AND start_at >= ? ORDER BY start_at LIMIT " . (int) $limit, [(int) $id, date('Y-m-d 00:00:00')]);
    } catch (Exception $ex) {
        return [];
    }
}
