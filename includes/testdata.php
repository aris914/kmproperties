<?php
/* =====================================================================
   Admin › Test Data (1.3.0): made-up records for trying the whole workflow, modelled on the
   RBJocsing portal's Test data page. Everything created here is marked is_test = 1 (properties,
   tenants, inquiries); leases, payments, repairs, photos, documents and history hang off those
   records, so "Clear test data" removes exactly what was added and never touches real records.
   Never changes users, settings, locations, landing page sections or the audit log.
   ===================================================================== */

const TD_FIRST = ['Andrea', 'Mark', 'Liza', 'Paolo', 'Bea', 'Carlo', 'Jasmine', 'Miguel', 'Patricia', 'Rafael', 'Camille', 'Joshua', 'Nicole', 'Gabriel', 'Kristine', 'Enzo', 'Angela', 'Daniel', 'Sofia', 'Luis', 'Bianca', 'Marco', 'Trisha', 'Nathan'];
const TD_LAST = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Villanueva', 'Ramos', 'Aquino', 'Castillo', 'Navarro', 'Dela Cruz', 'Fernandez', 'Gonzales', 'Lim', 'Tan', 'Sy', 'Rivera'];
const TD_BUILDINGS = ['Azure Heights', 'Greenfield Tower', 'Skyline Residences', 'Parkview Suites', 'Bayview Place', 'Metro Gardens', 'Emerald Towers', 'Sunrise Lofts', 'Harbor Point', 'Central Park Residences', 'Palm Grove Tower', 'Vista Verde', 'Crescent Bay', 'Maple Court'];
const TD_JOBS_EMP = ['BPO team lead', 'Software developer', 'Nurse', 'Accountant', 'Marketing officer', 'Engineer', 'Teacher', 'Sales manager', 'Graphic designer', 'Bank officer'];

function td_pick(array $a) { return $a[array_rand($a)]; }
function td_day($offset) { return date('Y-m-d', strtotime(($offset >= 0 ? '+' : '') . (int) $offset . ' days')); }
function td_month_add($date, $months) { return date('Y-m-d', strtotime($date . ' ' . ($months >= 0 ? '+' : '') . (int) $months . ' months')); }

/** Approximate centre of known Metro Manila areas, for map pins. */
function td_center($locName)
{
    $c = ['makati' => [14.5547, 121.0244], 'bonifacio' => [14.5509, 121.0503], 'bgc' => [14.5509, 121.0503], 'ortigas' => [14.5869, 121.0614],
          'quezon' => [14.6760, 121.0437], 'manila' => [14.5995, 120.9842], 'pasay' => [14.5378, 121.0014], 'para' => [14.4793, 121.0198],
          'mandaluyong' => [14.5794, 121.0359], 'taguig' => [14.5176, 121.0509], 'pasig' => [14.5764, 121.0851], 'san juan' => [14.6019, 121.0355]];
    $n = strtolower((string) $locName);
    foreach ($c as $k => $ll) {
        if (strpos($n, $k) !== false) {
            return $ll;
        }
    }
    return [14.5995, 120.9842];
}

/* ---------- Counts ---------- */

function td_counts()
{
    $one = function ($sql) { try { return (int) q_val($sql); } catch (Exception $ex) { return 0; } };
    $tp = 'SELECT id FROM properties WHERE is_test = 1';
    return [
        ['Properties', $one('SELECT COUNT(*) FROM properties'), $one('SELECT COUNT(*) FROM properties WHERE is_test = 1')],
        ['Owner submissions waiting', $one("SELECT COUNT(*) FROM properties WHERE approval = 'pending'"), $one("SELECT COUNT(*) FROM properties WHERE approval = 'pending' AND is_test = 1")],
        ['Property photos', $one('SELECT COUNT(*) FROM property_photos'), $one("SELECT COUNT(*) FROM property_photos WHERE property_id IN ($tp)")],
        ['Tenants', $one('SELECT COUNT(*) FROM tenants'), $one('SELECT COUNT(*) FROM tenants WHERE is_test = 1')],
        ['Leases', $one('SELECT COUNT(*) FROM leases'), $one("SELECT COUNT(*) FROM leases WHERE property_id IN ($tp)")],
        ['Payments', $one('SELECT COUNT(*) FROM payments'), $one("SELECT COUNT(*) FROM payments WHERE lease_id IN (SELECT id FROM leases WHERE property_id IN ($tp))")],
        ['Repairs & maintenance', $one('SELECT COUNT(*) FROM maintenance'), $one("SELECT COUNT(*) FROM maintenance WHERE property_id IN ($tp)")],
        ['Documents', $one('SELECT COUNT(*) FROM documents'), $one("SELECT COUNT(*) FROM documents WHERE (entity_type = 'property' AND entity_id IN ($tp))
            OR (entity_type = 'tenant' AND entity_id IN (SELECT id FROM tenants WHERE is_test = 1))
            OR (entity_type = 'lease' AND entity_id IN (SELECT id FROM leases WHERE property_id IN ($tp)))
            OR (entity_type = 'maintenance' AND entity_id IN (SELECT id FROM maintenance WHERE property_id IN ($tp)))")],
        ['Inquiries', $one('SELECT COUNT(*) FROM inquiries'), $one('SELECT COUNT(*) FROM inquiries WHERE is_test = 1')],
        ['History entries', $one('SELECT COUNT(*) FROM history'), $one("SELECT COUNT(*) FROM history WHERE property_id IN ($tp) OR tenant_id IN (SELECT id FROM tenants WHERE is_test = 1)")],
    ];
}

/* ---------- Files ---------- */

/** A made-up room photo (gradient sky, buildings, a label). Returns the file name, or null without GD. */
function td_photo($label, $seed)
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    mt_srand($seed);
    $w = 1200;
    $h = 800;
    $im = imagecreatetruecolor($w, $h);
    $top = [mt_rand(10, 60), mt_rand(60, 120), mt_rand(110, 170)];
    $bot = [mt_rand(200, 250), mt_rand(170, 220), mt_rand(120, 180)];
    for ($y = 0; $y < $h; $y++) {
        $f = $y / $h;
        $c = imagecolorallocate($im, (int) ($top[0] + ($bot[0] - $top[0]) * $f), (int) ($top[1] + ($bot[1] - $top[1]) * $f), (int) ($top[2] + ($bot[2] - $top[2]) * $f));
        imageline($im, 0, $y, $w, $y, $c);
    }
    $x = -20;
    while ($x < $w) { // skyline
        $bw = mt_rand(70, 160);
        $bh = mt_rand(200, 560);
        $shade = mt_rand(20, 60);
        $col = imagecolorallocate($im, $shade, $shade + 15, $shade + 35);
        imagefilledrectangle($im, $x, $h - $bh, $x + $bw, $h, $col);
        $win = imagecolorallocate($im, 255, mt_rand(200, 235), mt_rand(120, 170));
        for ($wy = $h - $bh + 18; $wy < $h - 20; $wy += 26) {
            for ($wx = $x + 10; $wx < $x + $bw - 14; $wx += 20) {
                if (mt_rand(0, 3)) {
                    imagefilledrectangle($im, $wx, $wy, $wx + 9, $wy + 12, $win);
                }
            }
        }
        $x += $bw + mt_rand(4, 18);
    }
    // label: drawn small, then enlarged so it is readable
    $text = 'TEST PHOTO - ' . strtoupper($label);
    $lw = strlen($text) * 9 + 24;
    $lab = imagecreatetruecolor($lw, 30);
    imagefilledrectangle($lab, 0, 0, $lw, 30, imagecolorallocate($lab, 0, 44, 76));
    imagestring($lab, 5, 12, 7, $text, imagecolorallocate($lab, 244, 194, 91));
    imagecopyresampled($im, $lab, 40, 40, 0, 0, $lw * 2, 60, $lw, 30);
    imagedestroy($lab);
    if (!is_dir(PHOTO_DIR)) {
        mkdir(PHOTO_DIR, 0755, true);
    }
    $name = 'test-' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.jpg';
    imagejpeg($im, PHOTO_DIR . '/' . $name, 80);
    imagedestroy($im);
    mt_srand();
    return $name;
}

/** A made-up ID card photo (front or back) saved as the tenant's Valid ID document. */
function td_id_card(array $t, $side)
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $w = 1000;
    $h = 630;
    $im = imagecreatetruecolor($w, $h);
    imagefilledrectangle($im, 0, 0, $w, $h, imagecolorallocate($im, 236, 242, 247));
    imagefilledrectangle($im, 0, 0, $w, 120, imagecolorallocate($im, 0, 56, 101));
    $txt = function ($str, $x, $y, $scale, $rgb) use ($im) {
        $lw = strlen($str) * 9 + 4;
        $lab = imagecreatetruecolor($lw, 18);
        $bg = imagecolorallocate($lab, 255, 0, 255);
        imagefill($lab, 0, 0, $bg);
        imagecolortransparent($lab, $bg);
        imagestring($lab, 5, 2, 1, $str, imagecolorallocate($lab, $rgb[0], $rgb[1], $rgb[2]));
        imagecopyresized($im, $lab, $x, $y, 0, 0, (int) ($lw * $scale), (int) (18 * $scale), $lw, 18);
        imagedestroy($lab);
    };
    $txt('REPUBLIC OF THE PHILIPPINES - SAMPLE', 30, 22, 2.2, [255, 255, 255]);
    $txt(strtoupper($t['id_type']) . ' - TEST DATA', 30, 70, 1.6, [244, 194, 91]);
    if ($side === 'front') {
        imagefilledrectangle($im, 40, 160, 300, 500, imagecolorallocate($im, 190, 205, 220));
        imagefilledellipse($im, 170, 270, 130, 150, imagecolorallocate($im, 120, 140, 160));
        imagefilledellipse($im, 170, 470, 230, 190, imagecolorallocate($im, 120, 140, 160));
        $txt('LAST NAME', 340, 170, 1.3, [90, 100, 110]);
        $txt(strtoupper($t['last_name']), 340, 200, 2.6, [20, 30, 40]);
        $txt('GIVEN NAME', 340, 270, 1.3, [90, 100, 110]);
        $txt(strtoupper($t['first_name']), 340, 300, 2.6, [20, 30, 40]);
        $txt('DATE OF BIRTH  ' . $t['birth_date'], 340, 380, 1.6, [20, 30, 40]);
        $txt('ID NO.  ' . $t['id_number'], 340, 430, 1.6, [20, 30, 40]);
        $txt('VALID UNTIL  ' . $t['id_expiry'], 340, 480, 1.6, [20, 30, 40]);
    } else {
        $txt('ADDRESS: ' . strtoupper($t['address']), 40, 170, 1.6, [20, 30, 40]);
        $txt('EMERGENCY: ' . strtoupper($t['emergency_name']), 40, 220, 1.6, [20, 30, 40]);
        for ($i = 0; $i < 60; $i++) { // barcode
            $bw = mt_rand(2, 7);
            imagefilledrectangle($im, 40 + $i * 14, 330, 40 + $i * 14 + $bw, 470, imagecolorallocate($im, 20, 30, 40));
        }
        $txt('NOT A REAL ID - MADE BY ADMIN > TEST DATA', 40, 520, 1.6, [192, 57, 43]);
    }
    $sub = date('Y/m');
    if (!is_dir(DOC_DIR . '/' . $sub)) {
        mkdir(DOC_DIR . '/' . $sub, 0755, true);
    }
    $file = $sub . '/test-' . bin2hex(random_bytes(10)) . '.jpg';
    imagejpeg($im, DOC_DIR . '/' . $file, 85);
    imagedestroy($im);
    $u = current_user();
    db_insert('documents', [
        'entity_type' => 'tenant', 'entity_id' => $t['id'], 'title' => 'Valid ID — ' . $t['id_type'] . ' (' . $side . ')', 'category' => 'Valid ID',
        'file' => $file, 'original_name' => 'id-' . $side . '-sample.jpg', 'mime' => 'image/jpeg',
        'size' => (int) filesize(DOC_DIR . '/' . $file), 'uploaded_by' => $u ? $u['id'] : null,
    ]);
    return true;
}

/** A small one-page PDF with a title and some lines. */
function td_pdf($title, array $lines)
{
    $clean = function ($s) {
        $s = strtr((string) $s, ['—' => '-', '–' => '-', '₱' => 'PHP ', '·' => '-', '’' => "'", 'ñ' => 'n', 'Ñ' => 'N']);
        $s = preg_replace('/[^\x20-\x7E]/', '', $s);
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    };
    $c = "BT /F1 9 Tf 50 810 Td (" . $clean(setting('site_name', 'K&M Urban Properties') . ' - SAMPLE DOCUMENT FOR TESTING') . ") Tj ET\n";
    $c .= "BT /F1 20 Tf 50 770 Td (" . $clean($title) . ") Tj ET\n";
    $y = 735;
    foreach ($lines as $l) {
        $c .= "BT /F1 12 Tf 50 $y Td (" . $clean($l) . ") Tj ET\n";
        $y -= 20;
    }
    $c .= "BT /F1 9 Tf 50 60 Td (Made-up test data - not a real document.) Tj ET\n";
    $objs = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        "<< /Length " . strlen($c) . " >>\nstream\n" . $c . "endstream",
    ];
    $pdf = "%PDF-1.4\n";
    $off = [];
    foreach ($objs as $i => $o) {
        $off[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $o . "\nendobj\n";
    }
    $x = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($off as $o) {
        $pdf .= sprintf("%010d 00000 n \n", $o);
    }
    $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$x\n%%EOF";
    return $pdf;
}

/** Saves a sample PDF as a document of a record. */
function td_doc($type, $id, $title, $category, array $lines)
{
    $sub = date('Y/m');
    if (!is_dir(DOC_DIR . '/' . $sub)) {
        mkdir(DOC_DIR . '/' . $sub, 0755, true);
    }
    $file = $sub . '/test-' . bin2hex(random_bytes(10)) . '.pdf';
    $pdf = td_pdf($title, $lines);
    file_put_contents(DOC_DIR . '/' . $file, $pdf);
    $u = current_user();
    db_insert('documents', [
        'entity_type' => $type, 'entity_id' => $id, 'title' => $title . ' (sample)', 'category' => $category,
        'file' => $file, 'original_name' => slugify($title) . '-sample.pdf', 'mime' => 'application/pdf',
        'size' => strlen($pdf), 'uploaded_by' => $u ? $u['id'] : null,
    ]);
}

/* ---------- Add ---------- */

function td_populate(array $opt)
{
    @set_time_limit(180);
    $nRent = max(0, min(30, (int) $opt['rent']));
    $nSale = max(0, min(20, (int) $opt['sale']));
    $nTen = max(0, min(40, (int) $opt['tenants']));
    $nOwn = max(0, min(10, (int) $opt['owner']));
    $withPhotos = !empty($opt['photos']);
    $withDocs = !empty($opt['docs']);
    $locs = q_all('SELECT id, name FROM locations WHERE is_active = 1 ORDER BY sort_order, name') ?: [['id' => null, 'name' => 'Metro Manila']];
    $out = ['properties' => 0, 'submissions' => 0, 'tenants' => 0, 'leases' => 0, 'payments' => 0, 'jobs' => 0, 'photos' => 0, 'documents' => 0, 'inquiries' => 0];
    $rooms = ['Living room', 'Bedroom', 'Kitchen', 'Bathroom', 'Balcony view', 'Building'];
    $methods = ['Bank transfer', 'GCash', 'Cash', 'Maya', 'Check'];

    $makeProperty = function ($i, $type, array $extra = []) use ($locs, $withPhotos, $rooms, &$out) {
        $loc = $locs[$i % count($locs)];
        $units = ['Studio', '1 Bedroom', '2 Bedroom', '3 Bedroom', '1 Bedroom', 'Loft'];
        $unit = $units[$i % count($units)];
        $beds = ['Studio' => 0, '1 Bedroom' => 1, '2 Bedroom' => 2, '3 Bedroom' => 3, 'Loft' => 1][$unit];
        $area = [0 => mt_rand(22, 30), 1 => mt_rand(32, 48), 2 => mt_rand(55, 75), 3 => mt_rand(85, 120)][$beds];
        $building = TD_BUILDINGS[($i + ($type === 'sale' ? 5 : 0)) % count(TD_BUILDINGS)];
        $price = $type === 'rent' ? (int) (round((12000 + $area * mt_rand(550, 900)) / 500) * 500) : (int) (round(($area * mt_rand(140, 230) * 1000) / 50000) * 50000);
        $furn = td_pick(['Unfurnished', 'Semi-furnished', 'Fully furnished']);
        $title = ($furn === 'Fully furnished' ? 'Furnished ' : '') . $unit . ' at ' . $building . ', ' . $loc['name'];
        list($clat, $clng) = td_center($loc['name']);
        $amen = array_slice(array_values(array_filter(amenity_choices(), function () { return mt_rand(0, 2) > 0; })), 0, 7);
        $data = array_merge([
            'code' => 'TEST-' . strtoupper(substr($type, 0, 1)) . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
            'title' => $title, 'slug' => unique_slug('properties', 'test ' . $title),
            'listing_type' => $type, 'property_type' => 'Condominium', 'unit_type' => $unit, 'location_id' => $loc['id'],
            'building' => $building, 'unit_no' => mt_rand(3, 40) . str_pad((string) mt_rand(1, 20), 2, '0', STR_PAD_LEFT),
            'address' => $building . ', ' . $loc['name'], 'latitude' => round($clat + mt_rand(-60, 60) / 10000, 7), 'longitude' => round($clng + mt_rand(-60, 60) / 10000, 7), 'show_map' => 1,
            'price' => $price, 'price_period' => $type === 'rent' ? 'month' : null,
            'bedrooms' => $beds, 'bathrooms' => max(1, $beds), 'floor_area' => $area, 'floor_level' => mt_rand(3, 45) . 'th', 'parking' => $beds >= 2 ? 1 : mt_rand(0, 1),
            'furnishing' => $furn,
            'description' => "Test listing for trying the system. Bright $unit with city view, near malls, schools and offices in {$loc['name']}.\n\nThis record was made by Admin > Test Data and can be cleared there.",
            'amenities' => implode("\n", $amen), 'status' => 'available', 'approval' => 'approved', 'is_visible' => 1,
            'is_featured' => $i < 2 ? 1 : 0, 'source' => 'admin', 'is_test' => 1,
            'owner_name' => td_pick(TD_FIRST) . ' ' . td_pick(TD_LAST), 'owner_phone' => '0917' . mt_rand(1000000, 9999999),
            'owner_email' => 'owner' . mt_rand(100, 999) . '@example.com', 'internal_notes' => 'TEST DATA',
        ], $extra);
        $pid = db_insert('properties', $data);
        log_history($pid, null, 'created', $data['source'] === 'owner' ? 'Submitted by the owner on the website — waiting for approval' : 'Property added (' . ($type === 'rent' ? 'for rent' : 'for sale') . ')', 'Test data · price ' . money($price), $data['source'] === 'owner' ? substr($data['submitted_at'], 0, 10) : td_day(-mt_rand(200, 420)));
        if ($withPhotos) {
            $n = mt_rand(3, 4);
            for ($k = 0; $k < $n; $k++) {
                $f = td_photo($rooms[$k % count($rooms)], $pid * 31 + $k);
                if ($f) {
                    db_insert('property_photos', ['property_id' => $pid, 'file' => $f, 'caption' => $rooms[$k % count($rooms)], 'is_cover' => $k === 0 ? 1 : 0, 'sort_order' => $k + 1]);
                    $out['photos']++;
                }
            }
        }
        $out['properties']++;
        return ['id' => $pid] + $data;
    };

    // Tenants
    $tenants = [];
    for ($i = 0; $i < $nTen; $i++) {
        $fn = TD_FIRST[$i % count(TD_FIRST)];
        $ln = TD_LAST[($i * 7) % count(TD_LAST)];
        $t = [
            'first_name' => $fn, 'last_name' => $ln, 'email' => strtolower($fn . '.' . str_replace(' ', '', $ln)) . mt_rand(1, 99) . '@example.com',
            'phone' => '09' . mt_rand(170000000, 199999999), 'birth_date' => td_day(-mt_rand(23, 50) * 365), 'occupation' => td_pick(TD_JOBS_EMP),
            'employer' => td_pick(['Accenture PH', 'BDO', 'Globe Telecom', 'St. Luke\'s', 'Ayala Land', 'Jollibee Foods', 'Concentrix', 'PLDT']),
            'id_type' => td_pick(['Passport', "Driver's License", 'UMID', 'PhilSys National ID']), 'id_number' => 'T' . mt_rand(10000000, 99999999), 'id_expiry' => td_day(mt_rand(-40, 1500)),
            'address' => td_pick(['Cebu City', 'Davao City', 'Iloilo City', 'Baguio City', 'Batangas City', 'Laguna']),
            'emergency_name' => td_pick(TD_FIRST) . ' ' . $ln, 'emergency_phone' => '09' . mt_rand(170000000, 199999999), 'emergency_relation' => td_pick(['Mother', 'Father', 'Sister', 'Brother', 'Spouse']),
            'status' => 'prospect', 'notes' => 'TEST DATA', 'is_test' => 1,
        ];
        $t['id'] = db_insert('tenants', $t);
        log_history(null, $t['id'], 'created', 'Tenant record created', 'Test data', td_day(-mt_rand(60, 400)));
        $tenants[] = $t;
        $out['tenants']++;
        if ($withDocs && $i % 3 !== 2) { // most test tenants get ID photos (front and back)
            foreach (['front', 'back'] as $side) {
                if (td_id_card($t, $side)) {
                    $out['documents']++;
                }
            }
            if ($i % 2 === 0) {
                td_doc('tenant', $t['id'], 'Certificate of employment - ' . $fn . ' ' . $ln, 'Certificate of employment', ['Employee: ' . $fn . ' ' . $ln, 'Position: ' . $t['occupation'], 'Employer: ' . $t['employer'], 'Monthly salary: PHP ' . number_format(mt_rand(35, 120) * 1000)]);
                $out['documents']++;
            }
        }
    }

    $tq = 0; // next free tenant
    $nextTenant = function () use (&$tq, $tenants) { return $tq < count($tenants) ? $tenants[$tq++] : null; };

    $addLease = function (array $p, array $t, $start, $end, $status, $paidMonths, array $extra = []) use (&$out, $methods, $withDocs) {
        $rent = (float) $p['price'];
        $data = array_merge([
            'property_id' => $p['id'], 'tenant_id' => $t['id'], 'start_date' => $start, 'end_date' => $end,
            'monthly_rent' => $rent, 'deposit' => $rent * 2, 'advance' => $rent, 'due_day' => min(28, (int) date('j', strtotime($start))),
            'status' => $status, 'notes' => 'Test lease', 'grace_days' => 3,
        ], $extra);
        $lid = db_insert('leases', $data);
        $out['leases']++;
        $name = $t['first_name'] . ' ' . $t['last_name'];
        log_history($p['id'], $t['id'], 'lease_start', ($status === 'upcoming' ? 'Lease signed (starts ' . fmt_date($start) . ')' : 'Lease started') . ': ' . $name . ' — ' . $p['title'],
            'Period: ' . fmt_date($start) . ' – ' . fmt_date($end) . "\nMonthly rent: " . money($rent, 2), $status === 'upcoming' ? td_day(-3) : $start);
        if ($status !== 'upcoming') {
            foreach ([['deposit', $rent * 2], ['advance', $rent]] as $pp) {
                db_insert('payments', ['lease_id' => $lid, 'amount' => $pp[1], 'paid_on' => $start, 'payment_type' => $pp[0], 'method' => 'Bank transfer', 'reference' => 'TD' . mt_rand(100000, 999999),
                    'period_month' => $pp[0] === 'advance' ? month_first($start) : null, 'period_label' => $pp[0] === 'advance' ? month_label(month_first($start), true) : null]);
                $out['payments']++;
            }
            log_history($p['id'], $t['id'], 'payment', 'Payment received: ' . money($rent * 3, 2) . ' (deposit and advance) from ' . $name, null, $start);
            for ($m = 1; $m <= $paidMonths; $m++) { // the advance covers the first month
                $on = td_month_add($start, $m);
                if ($on > date('Y-m-d')) {
                    break;
                }
                $method = td_pick($methods);
                db_insert('payments', ['lease_id' => $lid, 'amount' => $rent, 'paid_on' => $on, 'payment_type' => 'rent', 'period_month' => month_first($on), 'period_label' => date('M Y', strtotime($on)), 'method' => $method, 'reference' => $method === 'Cash' ? null : 'TD' . mt_rand(100000, 999999)]);
                $out['payments']++;
                log_history($p['id'], $t['id'], 'payment', 'Payment received: ' . money($rent, 2) . ' (rent, ' . date('M Y', strtotime($on)) . ') from ' . $name, $method, $on);
            }
        }
        if ($withDocs) {
            td_doc('lease', $lid, 'Signed lease contract - ' . $name, 'Signed contract', ['Property: ' . $p['title'], 'Tenant: ' . $name, 'Period: ' . $start . ' to ' . $end, 'Monthly rent: PHP ' . number_format($rent, 2), 'Security deposit: PHP ' . number_format($rent * 2, 2)]);
            $out['documents']++;
        }
        return $lid;
    };

    $addJob = function (array $p, $tenant, $title, $cat, $status, $priority, $reportedAgo, $cost = 0, $contractor = null, $area = null) use (&$out, $withDocs) {
        $rep = td_day(-$reportedAgo);
        $done = $status === 'completed' ? td_day(-max(0, $reportedAgo - mt_rand(1, 6))) : null;
        $jid = db_insert('maintenance', [
            'property_id' => $p['id'], 'tenant_id' => $tenant ? $tenant['id'] : null, 'title' => $title, 'category' => $cat, 'area' => $area,
            'priority' => $priority, 'status' => $status, 'reported_by' => $tenant ? 'Tenant' : 'Admin', 'reported_on' => $rep,
            'scheduled_on' => $status === 'scheduled' ? td_day(mt_rand(2, 9)) : null, 'completed_on' => $done,
            'contractor' => $contractor, 'contractor_phone' => $contractor ? '0918' . mt_rand(1000000, 9999999) : null,
            'cost' => $cost, 'charged_to' => $cost > 0 ? td_pick(['owner', 'owner', 'tenant', 'company']) : 'owner',
            'description' => 'Test job. ' . $title . '.', 'resolution' => $status === 'completed' ? 'Fixed and checked with the tenant/owner.' : null,
        ]);
        $out['jobs']++;
        log_history($p['id'], $tenant ? $tenant['id'] : null, 'maintenance', ucfirst($cat) . ' logged: ' . $title, $area ? 'Area: ' . $area : null, $rep);
        if ($status === 'completed') {
            log_history($p['id'], $tenant ? $tenant['id'] : null, 'maintenance_done', 'Completed: ' . $title, trim(($contractor ? 'By ' . $contractor . '. ' : '') . ($cost > 0 ? 'Cost ' . money($cost, 2) . '.' : '')), $done);
            if ($withDocs && $cost > 0) {
                td_doc('maintenance', $jid, 'Receipt - ' . $title, 'Receipt / invoice', ['Job: ' . $title, 'Contractor: ' . $contractor, 'Amount: PHP ' . number_format($cost, 2), 'Date: ' . $done]);
                $out['documents']++;
            }
        }
        return $jid;
    };

    // Properties for rent, each with a different part of the workflow
    for ($i = 0; $i < $nRent; $i++) {
        $role = $i < 4 ? ['relet', 'ending', 'upcoming', 'hidden'][$i] : ($i % 3 === 2 ? 'vacant' : 'active');
        $p = $makeProperty($i, 'rent', $role === 'hidden' ? ['is_visible' => 0, 'is_featured' => 0] : []);
        if ($role === 'hidden') {
            log_history($p['id'], null, 'status', 'Hidden from the website', 'Under renovation', td_day(-25));
            $addJob($p, null, 'Kitchen and bathroom renovation', 'renovation', 'in_progress', 'normal', 25, 85000, 'BuildRight Contractors', 'Kitchen');
            continue;
        }
        if ($role === 'relet') {
            $old = $nextTenant();
            $new = $nextTenant();
            if ($old) {
                $s = td_month_add(td_day(-15), -14);
                $e = td_month_add($s, 12);
                $lid = $addLease($p, $old, $s, date('Y-m-d', strtotime($e . ' -1 day')), 'ended', 11, ['move_out_date' => td_day(-45), 'end_reason' => 'Moved to a bigger unit', 'deposit_refunded' => $p['price'] * 2 - 3500]);
                db_insert('payments', ['lease_id' => $lid, 'amount' => $p['price'] * 2 - 3500, 'paid_on' => td_day(-40), 'payment_type' => 'deposit_refund', 'method' => 'Bank transfer', 'notes' => 'Refund at move-out (PHP 3,500 kept for wall repair)']);
                q("UPDATE tenants SET status = 'former' WHERE id = ?", [$old['id']]);
                log_history($p['id'], $old['id'], 'lease_end', 'Lease ended: ' . $old['first_name'] . ' ' . $old['last_name'] . ' moved out of ' . $p['title'], "Reason: Moved to a bigger unit\nDeposit refunded: " . money($p['price'] * 2 - 3500, 2) . ' (PHP 3,500 kept for wall repair)', td_day(-45));
                $addJob($p, $old, 'Repaint walls after move-out', 'maintenance', 'completed', 'normal', 42, 3500, 'Juan Painting Services', 'Walls / paint');
            }
            if ($new) {
                $s = td_day(-30);
                $addLease($p, $new, $s, date('Y-m-d', strtotime(td_month_add($s, 12) . ' -1 day')), 'active', 1);
                q("UPDATE tenants SET status = 'active' WHERE id = ?", [$new['id']]);
                q("UPDATE properties SET status = 'rented' WHERE id = ?", [$p['id']]);
            }
            continue;
        }
        if ($role === 'upcoming') {
            if ($t = $nextTenant()) {
                $s = td_day(15);
                $addLease($p, $t, $s, date('Y-m-d', strtotime(td_month_add($s, 12) . ' -1 day')), 'upcoming', 0);
                q("UPDATE properties SET status = 'reserved' WHERE id = ?", [$p['id']]);
                q("UPDATE tenants SET status = 'active' WHERE id = ?", [$t['id']]);
                $addJob($p, null, 'Move-in inspection and cleaning', 'inspection', 'scheduled', 'normal', 2, 0, 'K&M property team', 'Whole unit');
            }
            continue;
        }
        if ($role === 'vacant') {
            $addJob($p, null, 'Aircon cleaning (all units)', 'maintenance', 'completed', 'low', mt_rand(40, 120), 1800, 'CoolAir Services', 'Aircon');
            continue;
        }
        // active (including the one ending soon)
        if ($t = $nextTenant()) {
            if ($role === 'ending') {
                $end = td_day(20);
                $s = date('Y-m-d', strtotime(td_month_add($end, -12) . ' +1 day'));
                $paid = 10;
            } else {
                $s = td_month_add(td_day(-mt_rand(5, 25)), -mt_rand(2, 9));
                $end = date('Y-m-d', strtotime(td_month_add($s, 12) . ' -1 day'));
                $months = (int) floor((strtotime(date('Y-m-d')) - strtotime($s)) / (86400 * 30.4));
                $paid = $i % 4 === 1 ? max(0, $months - 2) : $months; // every few leases are behind on rent
            }
            $addLease($p, $t, $s, $end, 'active', $paid);
            q("UPDATE properties SET status = 'rented' WHERE id = ?", [$p['id']]);
            q("UPDATE tenants SET status = 'active' WHERE id = ?", [$t['id']]);
            $jobs = [
                ['Aircon not cooling', 'repair', 'open', 'high', 3, 0, null, 'Aircon'],
                ['Leaking kitchen faucet', 'repair', 'completed', 'normal', 50, 1200, 'Mario Plumbing', 'Plumbing'],
                ['Water leak under the bathroom sink', 'repair', 'open', 'urgent', 1, 0, null, 'Bathroom'],
                ['Door lock replacement', 'repair', 'in_progress', 'normal', 6, 2500, 'SecureLock PH', 'Doors / locks'],
                ['Annual electrical safety inspection', 'inspection', 'scheduled', 'low', 4, 0, 'Volt Electric', 'Electrical'],
            ];
            $j = $jobs[$i % count($jobs)];
            $addJob($p, $t, $j[0], $j[1], $j[2], $j[3], $j[4], $j[5], $j[6], $j[7]);
        }
    }

    // Properties for sale: available, reserved, sold
    for ($i = 0; $i < $nSale; $i++) {
        $status = $i === 1 ? 'reserved' : ($i === 2 ? 'sold' : 'available');
        $p = $makeProperty($i, 'sale', ['status' => $status]);
        if ($status !== 'available') {
            log_history($p['id'], null, 'status', 'Status changed to ' . ucfirst($status), 'Buyer: ' . td_pick(TD_FIRST) . ' ' . td_pick(TD_LAST), td_day(-mt_rand(3, 30)));
        }
        if ($withDocs) {
            td_doc('property', $p['id'], 'Condominium certificate of title - ' . $p['building'], 'Title / Deed', ['CCT No. T-' . mt_rand(100000, 999999), 'Unit ' . $p['unit_no'] . ', ' . $p['building'], 'Registered owner: ' . $p['owner_name'], 'Floor area: ' . $p['floor_area'] . ' sqm']);
            $out['documents']++;
        }
    }

    // Owner submissions from List Your Property: waiting, and one rejected
    for ($i = 0; $i < $nOwn; $i++) {
        $rejected = $nOwn >= 2 && $i === $nOwn - 1;
        $owner = TD_FIRST[($i + 9) % count(TD_FIRST)] . ' ' . TD_LAST[($i + 3) % count(TD_LAST)];
        $phone = '0917' . mt_rand(1000000, 9999999);
        $p = $makeProperty($i + 7, $i % 2 ? 'sale' : 'rent', [
            'approval' => $rejected ? 'rejected' : 'pending', 'is_visible' => 0, 'is_featured' => 0, 'source' => 'owner',
            'submitted_at' => date('Y-m-d H:i:s', strtotime('-' . mt_rand(2, 70) . ' hours')), 'submitter_ip' => '203.0.113.' . mt_rand(2, 250),
            'owner_name' => $owner, 'owner_phone' => $phone, 'owner_email' => 'owner.test' . mt_rand(10, 99) . '@example.com', 'code' => null,
            'reviewed_at' => $rejected ? date('Y-m-d H:i:s', strtotime('-1 hour')) : null, 'review_note' => $rejected ? 'Duplicate of an existing listing (test)' : null,
            'internal_notes' => 'TEST DATA — submitted by the owner on the website.',
        ]);
        if ($rejected) {
            log_history($p['id'], null, 'status', 'Submission rejected', 'Duplicate of an existing listing (test)');
        }
        db_insert('inquiries', [
            'inquiry_type' => 'listing', 'property_id' => $p['id'], 'name' => $owner, 'email' => $p['owner_email'], 'phone' => $phone,
            'message' => 'Hi, I would like to ' . ($p['listing_type'] === 'rent' ? 'rent out' : 'sell') . ' my unit. (test)',
            'extra' => 'Offer: ' . ($p['listing_type'] === 'rent' ? 'Rent out' : 'Sell') . "\nBuilding: " . $p['building'] . "\nAsking price: " . money($p['price']),
            'status' => $rejected ? 'closed' : 'new', 'ip' => $p['submitter_ip'], 'is_test' => 1,
        ]);
        $out['submissions']++;
        $out['inquiries']++;
    }

    // Website inquiries about test listings, and contact messages
    $pub = q_all("SELECT id, title FROM properties WHERE is_test = 1 AND approval = 'approved' AND is_visible = 1 AND status IN ('available','reserved') ORDER BY RAND() LIMIT 4");
    foreach ($pub as $k => $pp) {
        $n = td_pick(TD_FIRST) . ' ' . td_pick(TD_LAST);
        db_insert('inquiries', ['inquiry_type' => 'property', 'property_id' => $pp['id'], 'name' => $n, 'email' => 'buyer' . mt_rand(100, 999) . '@example.com', 'phone' => '0918' . mt_rand(1000000, 9999999),
            'message' => td_pick(['Is this still available? I would like to schedule a viewing this weekend.', 'Can the price be negotiated? Is parking included?', 'Is it pet-friendly? I have a small dog.', 'What are the move-in requirements?']) . ' (test)',
            'status' => $k === 0 ? 'contacted' : 'new', 'ip' => '198.51.100.' . mt_rand(2, 250), 'is_test' => 1]);
        log_history($pp['id'], null, 'inquiry', 'Inquiry received from ' . $n);
        $out['inquiries']++;
    }
    foreach (['Looking for a 2-bedroom near BGC, budget around PHP 50,000. (test)', 'Do you manage units for owners abroad? (test)'] as $msg) {
        db_insert('inquiries', ['inquiry_type' => 'contact', 'name' => td_pick(TD_FIRST) . ' ' . td_pick(TD_LAST), 'email' => 'visitor' . mt_rand(100, 999) . '@example.com', 'phone' => '0919' . mt_rand(1000000, 9999999), 'message' => $msg, 'status' => 'new', 'ip' => '198.51.100.' . mt_rand(2, 250), 'is_test' => 1]);
        $out['inquiries']++;
    }

    // a manual note on a couple of timelines
    foreach (array_slice($tenants, 0, 2) as $t) {
        log_history(null, $t['id'], 'note', 'Called to confirm documents', 'Test note: tenant will bring proof of income on Monday.', td_day(-mt_rand(5, 40)));
    }
    return $out;
}

/* ---------- Clear ---------- */

function td_in(array $ids)
{
    return $ids ? implode(',', array_map('intval', $ids)) : '0';
}

/** $scope 'test' = only records made by Add test data; 'all' = every property, tenant, lease, payment, repair, document, history entry and inquiry. */
function td_clear($scope)
{
    @set_time_limit(180);
    $all = $scope === 'all';
    $pids = array_column(q_all('SELECT id FROM properties' . ($all ? '' : ' WHERE is_test = 1')), 'id');
    $tids = array_column(q_all('SELECT id FROM tenants' . ($all ? '' : ' WHERE is_test = 1')), 'id');
    $P = td_in($pids);
    $T = td_in($tids);
    $lids = array_column(q_all("SELECT id FROM leases WHERE property_id IN ($P) OR tenant_id IN ($T)"), 'id');
    $mids = array_column(q_all("SELECT id FROM maintenance WHERE property_id IN ($P)"), 'id');
    $L = td_in($lids);
    $M = td_in($mids);
    $out = ['properties' => count($pids), 'tenants' => count($tids), 'leases' => count($lids), 'jobs' => count($mids), 'photos' => 0, 'documents' => 0, 'inquiries' => 0, 'history' => 0];

    foreach (q_all("SELECT file FROM property_photos WHERE property_id IN ($P)") as $ph) {
        delete_file_safe(PHOTO_DIR . '/' . $ph['file']);
        $out['photos']++;
    }
    $docWhere = $all ? '1=1' : "(entity_type = 'property' AND entity_id IN ($P)) OR (entity_type = 'tenant' AND entity_id IN ($T))
                 OR (entity_type = 'lease' AND entity_id IN ($L)) OR (entity_type = 'maintenance' AND entity_id IN ($M))";
    foreach (q_all("SELECT id, file FROM documents WHERE $docWhere") as $d) {
        delete_file_safe(DOC_DIR . '/' . $d['file']);
        $out['documents']++;
    }
    q("DELETE FROM documents WHERE $docWhere");
    $out['history'] = q($all ? 'DELETE FROM history' : "DELETE FROM history WHERE property_id IN ($P) OR tenant_id IN ($T)")->rowCount();
    $out['inquiries'] = q($all ? 'DELETE FROM inquiries' : 'DELETE FROM inquiries WHERE is_test = 1')->rowCount();
    q("DELETE FROM leases WHERE id IN ($L)");          // payments go with their lease
    q("DELETE FROM properties WHERE id IN ($P)");      // photos, repairs and remaining leases go with their property
    q("DELETE FROM tenants WHERE id IN ($T)");
    return $out;
}
