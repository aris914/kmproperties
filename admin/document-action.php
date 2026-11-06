<?php
require __DIR__ . '/includes/init.php';
require_admin();
if (!is_post()) {
    redirect('admin/documents.php');
}
csrf_check();
$back = admin_return_url('admin/documents.php');

/** Which property / tenant timeline a document belongs to. */
function doc_owner_ids($type, $id)
{
    switch ($type) {
        case 'property': return [$id, null];
        case 'tenant': return [null, $id];
        case 'lease':
            $l = q_one('SELECT property_id, tenant_id FROM leases WHERE id = ?', [$id]);
            return $l ? [$l['property_id'], $l['tenant_id']] : [null, null];
        case 'maintenance':
            $m = q_one('SELECT property_id, tenant_id FROM maintenance WHERE id = ?', [$id]);
            return $m ? [$m['property_id'], $m['tenant_id']] : [null, null];
    }
    return [null, null];
}

$tables = ['property' => 'properties', 'tenant' => 'tenants', 'lease' => 'leases', 'maintenance' => 'maintenance'];

if (post('action') === 'upload') {
    $type = post('entity_type');
    $eid = (int) post('entity_id');
    if (!isset($tables[$type]) || !q_val('SELECT id FROM `' . $tables[$type] . '` WHERE id = ?', [$eid])) {
        flash('error', 'The record for this document was not found.');
        header('Location: ' . $back);
        exit;
    }
    $files = files_list('files');
    if (!$files) {
        flash('error', 'Choose a file to upload. If you did, it may be larger than the server allows (' . ini_get('upload_max_filesize') . ').');
        header('Location: ' . $back);
        exit;
    }
    $u = current_user();
    $saved = [];
    foreach ($files as $file) {
        try {
            list($stored, $mime) = save_document_upload($file);
            $title = post('title');
            if ($title === '' || count($files) > 1) {
                $title = ($title !== '' ? $title . ' — ' : '') . pathinfo($file['name'], PATHINFO_FILENAME);
            }
            db_insert('documents', [
                'entity_type' => $type,
                'entity_id' => $eid,
                'title' => mb_substr($title, 0, 200),
                'category' => nn(post('category')),
                'file' => $stored,
                'original_name' => mb_substr(basename($file['name']), 0, 255),
                'mime' => $mime,
                'size' => (int) $file['size'],
                'uploaded_by' => $u['id'],
            ]);
            $saved[] = $title;
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
    }
    if ($saved) {
        list($pid, $tid) = doc_owner_ids($type, $eid);
        $label = ['property' => 'property', 'tenant' => 'tenant', 'lease' => 'lease', 'maintenance' => 'repair job'][$type];
        log_history($pid, $tid, 'document', count($saved) . ' document' . (count($saved) > 1 ? 's' : '') . ' uploaded to ' . $label, implode("\n", $saved));
        audit('insert', 'documents', $label . ' #' . $eid, implode("\n", $saved));
        flash('success', count($saved) . ' document' . (count($saved) > 1 ? 's' : '') . ' uploaded.');
    }
}

if (post('action') === 'delete') {
    $d = q_one('SELECT * FROM documents WHERE id = ?', [(int) post('id')]);
    if ($d) {
        delete_file_safe(DOC_DIR . '/' . $d['file']);
        q('DELETE FROM documents WHERE id = ?', [$d['id']]);
        list($pid, $tid) = doc_owner_ids($d['entity_type'], $d['entity_id']);
        log_history($pid, $tid, 'document', 'Document deleted: ' . $d['title']);
        audit('delete', 'documents', $d['title'], $d['entity_type'] . ' #' . $d['entity_id'] . ' · ' . $d['original_name']);
        flash('success', 'Document deleted.');
    }
}

header('Location: ' . $back);
exit;
