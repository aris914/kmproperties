<?php
/** Receives every public form (property inquiry, contact, list-your-property). */
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
csrf_check();

$return = post('return');
if (!is_string($return) || $return === '' || $return[0] !== '/' || strpos($return, '//') === 0) {
    $return = url('index.php');
}

// Honeypot: bots fill the hidden "website" field
if (post('website') !== '') {
    flash('success', 'Thank you! We received your message.');
    redirect($return);
}

// Simple rate limit: 5 submissions per 10 minutes per session
$now = time();
$_SESSION['inq_times'] = array_filter($_SESSION['inq_times'] ?? [], function ($t) use ($now) { return $t > $now - 600; });
if (count($_SESSION['inq_times']) >= 5) {
    flash('error', 'You have sent several messages already. Please wait a few minutes or call us directly.');
    redirect($return);
}

$type = in_array(post('inquiry_type'), ['property', 'listing', 'contact'], true) ? post('inquiry_type') : 'contact';
$name = mb_substr(post('name'), 0, 150);
$phone = mb_substr(post('phone'), 0, 60);
$email = mb_substr(post('email'), 0, 190);
$message = mb_substr(post('message'), 0, 3000);
$propertyId = (int) post('property_id') ?: null;

if ($name === '' || ($phone === '' && $email === '')) {
    flash('error', 'Please enter your name and a phone number or email so we can reach you.');
    redirect($return);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Please enter a valid email address.');
    redirect($return);
}
if ($propertyId && !q_val('SELECT id FROM properties WHERE id = ?', [$propertyId])) {
    $propertyId = null;
}

// Extra fields from the "List your property" form
$extra = null;
if ($type === 'listing') {
    $fields = ['offer' => 'Offer', 'unit_type' => 'Unit type', 'building' => 'Building', 'location' => 'Location', 'floor_area' => 'Floor area (sqm)', 'furnishing' => 'Furnishing', 'asking_price' => 'Asking price'];
    $rows = [];
    foreach ($fields as $k => $label) {
        $v = mb_substr(post($k), 0, 200);
        if ($v !== '') {
            $rows[] = $label . ': ' . $v;
        }
    }
    $extra = implode("\n", $rows);
}

db_insert('inquiries', [
    'inquiry_type' => $type,
    'property_id' => $propertyId,
    'name' => $name,
    'email' => nn($email),
    'phone' => nn($phone),
    'message' => nn($message),
    'extra' => nn($extra),
    'ip' => mb_substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
]);
$_SESSION['inq_times'][] = $now;

if ($propertyId) {
    log_history($propertyId, null, 'inquiry', 'Inquiry received from ' . $name);
}

// Optional email notification (works only if the host allows mail())
$to = setting('notify_email');
if ($to && filter_var($to, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
    $subject = 'New ' . $type . ' inquiry from ' . $name;
    $body = "Name: $name\nPhone: $phone\nEmail: $email\n\n" . ($extra ? $extra . "\n\n" : '') . $message;
    @mail($to, $subject, $body, 'Content-Type: text/plain; charset=utf-8');
}

flash('success', 'Thank you, ' . $name . '! We received your message and will get back to you shortly.');
redirect($return);
