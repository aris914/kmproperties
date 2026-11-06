<?php
/* Approve / reject owner submissions, and show / hide any property on the website. */
require __DIR__ . '/includes/init.php';
$me = require_admin();
if (!is_post()) {
    redirect('admin/properties.php');
}
csrf_check();
$back = admin_return_url('admin/properties.php');

$p = q_one('SELECT * FROM properties WHERE id = ?', [(int) post('id')]);
if (!$p) {
    flash('error', 'Property not found.');
    header('Location: ' . $back);
    exit;
}
$id = (int) $p['id'];
$action = post('action');

if ($action === 'approve') {
    $publish = post('publish') === '1';
    db_update('properties', [
        'approval' => 'approved', 'is_visible' => $publish ? 1 : 0,
        'reviewed_at' => date('Y-m-d H:i:s'), 'reviewed_by' => $me['id'], 'review_note' => nn(mb_substr(post('note'), 0, 255)),
    ], $id);
    log_history($id, null, 'status', $publish ? 'Approved and published on the website' : 'Approved (still hidden from the website)', post('note'));
    audit('update', 'properties', $p['title'], $publish ? 'Owner submission approved and published' : 'Owner submission approved, kept hidden');
    q("UPDATE inquiries SET status = 'contacted' WHERE property_id = ? AND inquiry_type = 'listing' AND status = 'new'", [$id]);
    flash('success', $publish ? '“' . $p['title'] . '” is approved and now shows in the listings.' : '“' . $p['title'] . '” is approved. It stays hidden until you show it.');
} elseif ($action === 'reject') {
    $note = mb_substr(post('note'), 0, 255);
    db_update('properties', [
        'approval' => 'rejected', 'is_visible' => 0,
        'reviewed_at' => date('Y-m-d H:i:s'), 'reviewed_by' => $me['id'], 'review_note' => nn($note),
    ], $id);
    log_history($id, null, 'status', 'Submission rejected', $note);
    audit('update', 'properties', $p['title'], 'Owner submission rejected' . ($note !== '' ? ': ' . $note : ''));
    flash('success', '“' . $p['title'] . '” was rejected. It stays saved under Owner Submissions › Rejected (delete it from its edit page if not needed).');
} elseif ($action === 'pending') {
    db_update('properties', ['approval' => 'pending', 'is_visible' => 0], $id);
    log_history($id, null, 'status', 'Moved back to waiting for approval');
    audit('update', 'properties', $p['title'], 'Moved back to pending');
    flash('success', 'Moved back to waiting for approval.');
} elseif ($action === 'show' || $action === 'hide') {
    if ($action === 'show' && $p['approval'] !== 'approved') {
        flash('error', 'Approve this submission first; then it can be shown on the website.');
    } else {
        $vis = $action === 'show' ? 1 : 0;
        db_update('properties', ['is_visible' => $vis], $id);
        log_history($id, null, 'status', $vis ? 'Shown on the website' : 'Hidden from the website');
        audit('update', 'properties', $p['title'], $vis ? 'Shown on the website' : 'Hidden from the website');
        flash('success', '“' . $p['title'] . '” is now ' . ($vis ? 'shown on' : 'hidden from') . ' the website.'
            . ($vis && $p['status'] === 'inactive' ? ' Note: its status is Inactive, so visitors still cannot see it — change the status to make it appear.' : ''));
    }
}
header('Location: ' . $back);
exit;
