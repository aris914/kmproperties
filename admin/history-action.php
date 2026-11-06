<?php
/** Adds a manual note to a property and/or tenant history. */
require __DIR__ . '/includes/init.php';
require_admin();
if (!is_post()) {
    redirect('admin/dashboard.php');
}
csrf_check();
$back = admin_return_url('admin/dashboard.php');

$pid = (int) post('property_id') ?: null;
$tid = (int) post('tenant_id') ?: null;
$summary = post('summary');
if ($summary !== '' && ($pid || $tid)) {
    log_history($pid, $tid, 'note', $summary, post('details'), valid_date(post('event_date')) ?: date('Y-m-d'));
    audit('insert', 'history', $summary, post('details'));
    flash('success', 'Note added to the history.');
} else {
    flash('error', 'Write a short note first.');
}
header('Location: ' . $back);
exit;
