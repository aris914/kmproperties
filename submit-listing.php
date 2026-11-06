<?php
/* Receives List Your Property: creates a property waiting for approval (hidden from the website),
   saves its photos and map pin, and records an inquiry so the owner can be contacted. */
require __DIR__ . '/includes/bootstrap.php';

$back = url('list-property.php');
if (!is_post()) {
    redirect($back);
}
// Photos larger than the server's post_max_size arrive as an empty request
if (empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'The photos were too large to send together. Please choose fewer or smaller photos and try again.');
    redirect($back);
}
csrf_check();

function listing_fail($msg)
{
    $old = $_POST;
    unset($old['csrf'], $old['website']);
    $_SESSION['listing_old'] = $old;
    flash('error', $msg);
    redirect(url('list-property.php'));
}

// Honeypot: bots fill the hidden "website" field
if (post('website') !== '') {
    $_SESSION['listing_sent'] = '';
    redirect($back);
}

// Rate limit: 3 submissions per hour per browser and per IP address
$now = time();
$_SESSION['listing_times'] = array_values(array_filter($_SESSION['listing_times'] ?? [], function ($t) use ($now) { return $t > $now - 3600; }));
$ip = client_ip();
$recentIp = (int) q_val("SELECT COUNT(*) FROM properties WHERE source = 'owner' AND submitter_ip = ? AND submitted_at > NOW() - INTERVAL 1 HOUR", [$ip]);
if (count($_SESSION['listing_times']) >= 3 || $recentIp >= 3) {
    listing_fail('You have already submitted several properties. Please wait an hour or contact us directly.');
}

$name = mb_substr(post('name'), 0, 150);
$phone = mb_substr(post('phone'), 0, 60);
$email = mb_substr(post('email'), 0, 190);
$building = mb_substr(post('building'), 0, 150);
$price = num(post('price'));
$offer = in_array(post('offer'), ['rent', 'sale', 'both', 'manage'], true) ? post('offer') : 'rent';
$locId = (int) post('location_id');
$loc = $locId ? q_one('SELECT id, name FROM locations WHERE id = ?', [$locId]) : null;

if ($name === '' || $phone === '') {
    listing_fail('Please enter your name and mobile number.');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    listing_fail('Please enter a valid email address, or leave it empty.');
}
if ($building === '' || $price <= 0) {
    listing_fail('Please enter the building / project and the asking price.');
}
if (!post('consent')) {
    listing_fail('Please confirm that you are the owner or authorised to list the property.');
}

$lat = post('latitude') !== '' ? (float) post('latitude') : null;
$lng = post('longitude') !== '' ? (float) post('longitude') : null;
if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    $lat = $lng = null;
}

$unit = in_array(post('unit_type'), unit_types(), true) ? post('unit_type') : null;
$amen = array_values(array_intersect(isset($_POST['amen']) && is_array($_POST['amen']) ? $_POST['amen'] : [], amenity_choices()));
$listing = $offer === 'sale' ? 'sale' : 'rent';
$title = trim(($unit ?: 'Unit') . ' in ' . $building . ($loc ? ', ' . $loc['name'] : ''));
$offerText = ['rent' => 'Rent out', 'sale' => 'Sell', 'both' => 'Rent out or sell', 'manage' => 'Have it managed'][$offer];

$pid = db_insert('properties', [
    'title' => mb_substr($title, 0, 200),
    'slug' => unique_slug('properties', $title),
    'listing_type' => $listing,
    'property_type' => in_array(post('property_type'), property_types(), true) ? post('property_type') : 'Condominium',
    'unit_type' => $unit,
    'location_id' => $loc ? $loc['id'] : null,
    'building' => $building,
    'unit_no' => nn(mb_substr(post('unit_no'), 0, 40)),
    'address' => nn(mb_substr(post('address'), 0, 255)),
    'latitude' => $lat,
    'longitude' => $lng,
    'show_map' => 1,
    'price' => $price,
    'price_period' => $listing === 'rent' ? 'month' : null,
    'bedrooms' => max(0, min(20, (int) post('bedrooms'))),
    'bathrooms' => max(0, min(20, (int) post('bathrooms'))),
    'floor_area' => num(post('floor_area')) ?: null,
    'floor_level' => nn(mb_substr(post('floor_level'), 0, 20)),
    'parking' => max(0, min(20, (int) post('parking'))),
    'furnishing' => in_array(post('furnishing'), ['Unfurnished', 'Semi-furnished', 'Fully furnished'], true) ? post('furnishing') : 'Unfurnished',
    'description' => nn(mb_substr(post('description'), 0, 3000)),
    'amenities' => $amen ? implode("\n", $amen) : null,
    'status' => 'available',
    'approval' => 'pending',
    'is_visible' => 0,
    'is_featured' => 0,
    'source' => 'owner',
    'submitted_at' => date('Y-m-d H:i:s'),
    'submitter_ip' => $ip,
    'owner_name' => $name,
    'owner_phone' => $phone,
    'owner_email' => nn($email),
    'internal_notes' => 'Submitted by the owner on the website. Wants to: ' . $offerText . '.' . ($locId === 0 ? ' Location: other (not in the list).' : ''),
]);

// Photos (the browser already made them smaller; the server checks and resizes again)
$saved = 0;
$errors = [];
foreach (array_slice(files_list('photos'), 0, 12) as $i => $file) {
    try {
        $fileName = save_image_upload($file, PHOTO_DIR);
        db_insert('property_photos', ['property_id' => $pid, 'file' => $fileName, 'is_cover' => $saved === 0 ? 1 : 0, 'sort_order' => $saved + 1]);
        $saved++;
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    }
}

db_insert('inquiries', [
    'inquiry_type' => 'listing',
    'property_id' => $pid,
    'name' => $name,
    'email' => nn($email),
    'phone' => $phone,
    'message' => nn(mb_substr(post('description'), 0, 3000)),
    'extra' => implode("\n", array_filter([
        'Offer: ' . $offerText, 'Building: ' . $building, $loc ? 'Location: ' . $loc['name'] : null,
        $unit ? 'Unit type: ' . $unit : null, 'Asking price: ' . money($price), $saved . ' photo(s)',
        $lat !== null ? 'Map pin: ' . $lat . ', ' . $lng : 'No map pin',
    ])),
    'ip' => $ip,
]);
log_history($pid, null, 'created', 'Submitted by the owner on the website — waiting for approval',
    $name . ' · ' . $phone . ($email ? ' · ' . $email : '') . "\n" . $saved . ' photo(s)' . ($errors ? ' (' . count($errors) . ' rejected)' : ''));
audit('insert', 'properties', $title, 'Owner submission from the website by ' . $name . ' (' . $phone . '), ' . $saved . ' photo(s) — pending approval');
$_SESSION['listing_times'][] = $now;

$to = setting('notify_email');
if ($to && filter_var($to, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
    @mail($to, 'New property submitted: ' . $title, "A property was submitted on the website and is waiting for approval.\n\n"
        . "$title\nOwner: $name, $phone $email\nAsking price: " . money($price) . "\nPhotos: $saved\n\nReview it in Admin > Owner Submissions.",
        'Content-Type: text/plain; charset=utf-8');
}

if ($errors) {
    flash('warning', 'Some photos could not be used: ' . implode('; ', array_slice($errors, 0, 3)));
}
$_SESSION['listing_sent'] = $name;
redirect($back);
