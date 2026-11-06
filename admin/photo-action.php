<?php
require __DIR__ . '/includes/init.php';
require_admin();
if (!is_post()) {
    redirect('admin/properties.php');
}
csrf_check();

$pid = (int) post('property_id');
$ptitle = q_val('SELECT title FROM properties WHERE id = ?', [$pid]);
if (!$ptitle) {
    flash('error', 'Property not found.');
    redirect('admin/properties.php');
}
$back = 'admin/property-view.php?id=' . $pid . '&tab=photos';

function ensure_cover($pid)
{
    if (!q_val('SELECT COUNT(*) FROM property_photos WHERE property_id = ? AND is_cover = 1', [$pid])) {
        $first = q_val('SELECT id FROM property_photos WHERE property_id = ? ORDER BY sort_order, id LIMIT 1', [$pid]);
        if ($first) {
            q('UPDATE property_photos SET is_cover = 1 WHERE id = ?', [$first]);
        }
    }
}

if (post('action') === 'upload') {
    $files = files_list('photos');
    if (!$files) {
        flash('error', 'Choose at least one photo. If you did, the files may be larger than the server allows (' . ini_get('upload_max_filesize') . ').');
        redirect($back);
    }
    $sort = (int) q_val('SELECT COALESCE(MAX(sort_order),0) FROM property_photos WHERE property_id = ?', [$pid]);
    $ok = 0;
    foreach ($files as $file) {
        try {
            $name = save_image_upload($file, PHOTO_DIR);
            db_insert('property_photos', ['property_id' => $pid, 'file' => $name, 'sort_order' => ++$sort]);
            $ok++;
        } catch (RuntimeException $ex) {
            flash('error', $ex->getMessage());
        }
    }
    ensure_cover($pid);
    if ($ok) {
        log_history($pid, null, 'photo', $ok . ' photo' . ($ok > 1 ? 's' : '') . ' added');
        audit('insert', 'photos', $ptitle, $ok . ' photo(s) uploaded');
        flash('success', $ok . ' photo' . ($ok > 1 ? 's' : '') . ' uploaded.');
    }
    redirect($back);
}

if (post('action') === 'save_all') {
    if (post('delete')) {
        $ph = q_one('SELECT * FROM property_photos WHERE id = ? AND property_id = ?', [(int) post('delete'), $pid]);
        if ($ph) {
            delete_file_safe(PHOTO_DIR . '/' . $ph['file']);
            q('DELETE FROM property_photos WHERE id = ?', [$ph['id']]);
            ensure_cover($pid);
            log_history($pid, null, 'photo', 'Photo removed');
            audit('delete', 'photos', $ptitle, $ph['file']);
            flash('success', 'Photo deleted.');
        }
        redirect($back);
    }
    foreach ((array) ($_POST['caption'] ?? []) as $phId => $cap) {
        q('UPDATE property_photos SET caption = ?, sort_order = ? WHERE id = ? AND property_id = ?', [nn(mb_substr((string) $cap, 0, 200)), (int) ($_POST['sort'][$phId] ?? 0), (int) $phId, $pid]);
    }
    if (post('cover')) {
        q('UPDATE property_photos SET is_cover = (id = ?) WHERE property_id = ?', [(int) post('cover'), $pid]);
        audit('update', 'photos', $ptitle, 'Cover photo changed');
        flash('success', 'Cover photo changed.');
    } else {
        audit('update', 'photos', $ptitle, 'Captions / order saved');
        flash('success', 'Photos saved.');
    }
    redirect($back);
}

redirect($back);
