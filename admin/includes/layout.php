<?php
require __DIR__ . '/shell.php';

/** Renders the timeline list for history rows. */
function history_timeline(array $rows, $showProperty = false, $showTenant = false)
{
    if (!$rows) {
        echo '<p class="text-muted mb-0">No history recorded yet.</p>';
        return;
    }
    echo '<ul class="timeline">';
    foreach ($rows as $h) {
        echo '<li class="tl-' . e($h['event_type']) . '"><span class="tl-icon"><i class="bi ' . history_icon($h['event_type']) . '"></i></span><div class="tl-body">';
        echo '<div class="d-flex flex-wrap justify-content-between gap-2"><strong>' . e($h['summary']) . '</strong><small class="text-muted">' . fmt_date($h['event_date']) . '</small></div>';
        $meta = [];
        if ($showProperty && !empty($h['property_title'])) {
            $meta[] = '<a href="' . url('admin/property-view.php?id=' . (int) $h['property_id']) . '"><i class="bi bi-building"></i> ' . e($h['property_title']) . '</a>';
        }
        if ($showTenant && !empty($h['tenant_first'])) {
            $meta[] = '<a href="' . url('admin/tenant-view.php?id=' . (int) $h['tenant_id']) . '"><i class="bi bi-person"></i> ' . e($h['tenant_first'] . ' ' . $h['tenant_last']) . '</a>';
        }
        if (!empty($h['user_name'])) {
            $meta[] = '<span><i class="bi bi-person-badge"></i> ' . e($h['user_name']) . '</span>';
        }
        if ($meta) {
            echo '<div class="tl-meta">' . implode(' · ', $meta) . '</div>';
        }
        if ($h['details']) {
            echo '<div class="tl-details">' . nl2br(e($h['details'])) . '</div>';
        }
        echo '</div></li>';
    }
    echo '</ul>';
}

function history_query($where, array $params, $limit = 200)
{
    return q_all("SELECT h.*, p.title AS property_title, t.first_name AS tenant_first, t.last_name AS tenant_last, u.name AS user_name
                  FROM history h
                  LEFT JOIN properties p ON p.id = h.property_id
                  LEFT JOIN tenants t ON t.id = h.tenant_id
                  LEFT JOIN users u ON u.id = h.user_id
                  WHERE $where ORDER BY h.event_date DESC, h.id DESC LIMIT " . (int) $limit, $params);
}

/** Form to add a manual note to a property or tenant timeline. */
function history_note_form($propertyId, $tenantId, $return)
{
    ?>
    <form method="post" action="<?= url('admin/history-action.php') ?>" class="row g-2 mb-4 align-items-end">
      <?= csrf_field() ?>
      <input type="hidden" name="property_id" value="<?= (int) $propertyId ?>">
      <input type="hidden" name="tenant_id" value="<?= (int) $tenantId ?>">
      <input type="hidden" name="return" value="<?= e($return) ?>">
      <div class="col-md-5"><label class="form-label small">Add a note to the history</label><input name="summary" class="form-control" required maxlength="255" placeholder="e.g. Unit inspected before turnover"></div>
      <div class="col-md-4"><label class="form-label small">Details (optional)</label><input name="details" class="form-control"></div>
      <div class="col-md-2"><label class="form-label small">Date</label><input type="date" name="event_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
      <div class="col-md-1"><button class="btn btn-navy w-100" title="Add note"><i class="bi bi-plus-lg"></i></button></div>
    </form>
    <?php
}

/** Document list + upload form for any record. */
function documents_panel($entityType, $entityId, $return, array $categories = [])
{
    $docs = q_all('SELECT d.*, u.name AS uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.entity_type = ? AND d.entity_id = ? ORDER BY d.created_at DESC', [$entityType, $entityId]);
    if (!$categories) {
        $categories = ['Contract', 'ID', 'Receipt', 'Title / Deed', 'Permit', 'Photo', 'Report', 'Other'];
    }
    ?>
    <form method="post" action="<?= url('admin/document-action.php') ?>" enctype="multipart/form-data" class="upload-box row g-2 align-items-end mb-3">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="upload">
      <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
      <input type="hidden" name="entity_id" value="<?= (int) $entityId ?>">
      <input type="hidden" name="return" value="<?= e($return) ?>">
      <div class="col-md-4"><label class="form-label small">File(s) — PDF, Word, Excel, images · max <?= (int) (defined('MAX_DOC_MB') ? MAX_DOC_MB : 10) ?> MB each</label><input type="file" name="files[]" class="form-control" multiple required accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.txt,.csv"></div>
      <div class="col-md-4"><label class="form-label small">Title (blank = file name)</label><input name="title" class="form-control" maxlength="200"></div>
      <div class="col-md-2"><label class="form-label small">Category</label><select name="category" class="form-select"><?= select_options($categories, '') ?></select></div>
      <div class="col-md-2"><button class="btn btn-navy w-100"><i class="bi bi-upload me-1"></i>Upload</button></div>
    </form>
    <?php if (!$docs): ?>
      <p class="text-muted mb-0">No documents uploaded yet.</p>
    <?php else: ?>
      <div class="table-responsive"><table class="table table-sm align-middle mb-0">
        <thead><tr><th>Document</th><th>Category</th><th>Size</th><th>Uploaded</th><th class="text-end"></th></tr></thead>
        <tbody>
        <?php foreach ($docs as $d): ?>
          <tr>
            <td><i class="bi <?= doc_icon($d['original_name']) ?> me-1 text-teal"></i><a href="<?= url('admin/download.php?id=' . $d['id']) ?>" target="_blank"><?= e($d['title']) ?></a><div class="small text-muted"><?= e($d['original_name']) ?></div></td>
            <td><?= e($d['category']) ?></td>
            <td class="small"><?= human_size($d['size']) ?></td>
            <td class="small"><?= fmt_date($d['created_at']) ?><br><span class="text-muted"><?= e($d['uploader']) ?></span></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-light" href="<?= url('admin/download.php?id=' . $d['id'] . '&dl=1') ?>" title="Download"><i class="bi bi-download"></i></a>
              <form method="post" action="<?= url('admin/document-action.php') ?>" class="d-inline" onsubmit="return confirm('Delete this document permanently?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $d['id'] ?>"><input type="hidden" name="return" value="<?= e($return) ?>">
                <button class="btn btn-sm btn-light text-danger" title="Delete"><i class="bi bi-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif;
}

function doc_icon($name)
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $map = ['pdf' => 'bi-file-earmark-pdf', 'doc' => 'bi-file-earmark-word', 'docx' => 'bi-file-earmark-word', 'xls' => 'bi-file-earmark-excel', 'xlsx' => 'bi-file-earmark-excel', 'jpg' => 'bi-file-earmark-image', 'jpeg' => 'bi-file-earmark-image', 'png' => 'bi-file-earmark-image', 'webp' => 'bi-file-earmark-image'];
    return isset($map[$ext]) ? $map[$ext] : 'bi-file-earmark';
}

function admin_return_url($fallback)
{
    $r = post('return');
    if (is_string($r) && $r !== '' && $r[0] === '/' && strpos($r, '//') !== 0) {
        return $r;
    }
    return url($fallback);
}

function property_options($selected = null, $onlyRent = false)
{
    $rows = q_all('SELECT id, title, code, building, unit_no FROM properties' . ($onlyRent ? " WHERE listing_type = 'rent'" : '') . ' ORDER BY title');
    $html = '';
    foreach ($rows as $r) {
        $label = ($r['code'] ? $r['code'] . ' — ' : '') . $r['title'] . ($r['unit_no'] ? ' (Unit ' . $r['unit_no'] . ')' : '');
        $html .= '<option value="' . $r['id'] . '"' . ((int) $selected === (int) $r['id'] ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

function tenant_options($selected = null)
{
    $rows = q_all("SELECT id, first_name, last_name, status FROM tenants WHERE status <> 'blacklisted' OR id = ? ORDER BY last_name, first_name", [(int) $selected]);
    $html = '';
    foreach ($rows as $r) {
        $html .= '<option value="' . $r['id'] . '"' . ((int) $selected === (int) $r['id'] ? ' selected' : '') . '>' . e($r['last_name'] . ', ' . $r['first_name']) . ' (' . e($r['status']) . ')</option>';
    }
    return $html;
}

/* ---------- Owner submissions & website visibility (1.2.0) ---------- */

/** Approve / reject buttons for a property, depending on its approval state. */
function review_buttons(array $p, $return)
{
    $f = function ($action, $label, $cls, $extra = '') use ($p, $return) {
        return '<form method="post" action="' . url('admin/property-action.php') . '" class="d-inline">' . csrf_field()
            . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><input type="hidden" name="action" value="' . $action . '">'
            . '<input type="hidden" name="return" value="' . e($return) . '">' . $extra
            . '<button class="btn btn-sm ' . $cls . '">' . $label . '</button></form>';
    };
    if ($p['approval'] === 'pending') {
        echo $f('approve', '<i class="bi bi-check2-circle me-1"></i>Approve &amp; publish', 'btn-primary', '<input type="hidden" name="publish" value="1">');
        echo $f('approve', 'Approve, keep hidden', 'btn-outline-primary', '<input type="hidden" name="publish" value="0">');
        echo '<button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="' . (int) $p['id']
            . '" data-title="' . e($p['title']) . '" data-return="' . e($return) . '"><i class="bi bi-x-circle me-1"></i>Reject</button>';
    } elseif ($p['approval'] === 'rejected') {
        echo $f('approve', '<i class="bi bi-check2-circle me-1"></i>Approve &amp; publish', 'btn-primary', '<input type="hidden" name="publish" value="1">');
        echo $f('pending', 'Back to waiting', 'btn-light');
    } else {
        visibility_button($p, $return);
    }
}

/** Show on website / Hide from website toggle. */
function visibility_button(array $p, $return, $small = true)
{
    if ($p['approval'] !== 'approved') {
        echo '<span class="badge text-bg-warning">' . ($p['approval'] === 'pending' ? 'Waiting for approval' : 'Rejected') . '</span>';
        return;
    }
    $vis = (int) $p['is_visible'] === 1;
    echo '<form method="post" action="' . url('admin/property-action.php') . '" class="d-inline">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int) $p['id'] . '"><input type="hidden" name="action" value="' . ($vis ? 'hide' : 'show') . '">'
        . '<input type="hidden" name="return" value="' . e($return) . '">'
        . '<button class="btn ' . ($small ? 'btn-sm ' : '') . ($vis ? 'btn-success' : 'btn-outline-secondary') . '" title="' . ($vis ? 'Shown on the website — click to hide' : 'Hidden from the website — click to show') . '">'
        . '<i class="bi ' . ($vis ? 'bi-eye' : 'bi-eye-slash') . ' me-1"></i>' . ($vis ? 'Shown' : 'Hidden') . '</button></form>';
}

/** The dialog that asks for a reason when rejecting. Include once per page. */
function review_modal()
{
    ?>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><form method="post" action="<?= url('admin/property-action.php') ?>" class="modal-content">
    <?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" id="rjId"><input type="hidden" name="return" id="rjReturn">
    <div class="modal-header"><h2 class="modal-title h5" id="rejectTitle">Reject submission</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p class="mb-2" id="rjName"></p>
      <label class="form-label" for="rjNote">Reason (kept in the history; not sent to the owner)</label>
      <textarea name="note" id="rjNote" class="form-control" rows="3" maxlength="255" placeholder="e.g. Duplicate listing, incomplete details, could not verify owner"></textarea>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Reject</button></div>
  </form></div>
</div>
<script>
document.getElementById('rejectModal').addEventListener('show.bs.modal', function (ev) {
  var b = ev.relatedTarget;
  document.getElementById('rjId').value = b.dataset.id;
  document.getElementById('rjReturn').value = b.dataset.return;
  document.getElementById('rjName').textContent = b.dataset.title;
});
</script>
    <?php
}

/* ---------- Tenant ID photos & documents (1.4.0) ---------- */

/** A tenant's documents split into the ID front, ID back and everything else. */
function tenant_docs($tenantId)
{
    $out = ['front' => null, 'back' => null, 'other' => []];
    foreach (q_all("SELECT * FROM documents WHERE entity_type = 'tenant' AND entity_id = ? ORDER BY created_at DESC, id DESC", [(int) $tenantId]) as $d) {
        $side = null;
        if ($d['category'] === 'Valid ID') {
            if (substr($d['title'], -7) === '(front)') {
                $side = 'front';
            } elseif (substr($d['title'], -6) === '(back)') {
                $side = 'back';
            }
        }
        if ($side && !$out[$side]) {
            $out[$side] = $d;
        } else {
            $out['other'][] = $d;
        }
    }
    return $out;
}

/** Thumbnail (images) or file icon (PDF etc.) linking to the private file. */
function doc_thumb(array $d, $cls = 'id-thumb')
{
    $href = url('admin/download.php?id=' . (int) $d['id']);
    if (strpos((string) $d['mime'], 'image/') === 0) {
        return '<a href="' . $href . '" target="_blank" class="' . $cls . '" title="' . e($d['title']) . '"><img src="' . $href . '" alt="' . e($d['title']) . '" loading="lazy"></a>';
    }
    return '<a href="' . $href . '" target="_blank" class="' . $cls . ' is-file" title="' . e($d['title']) . '"><i class="bi ' . doc_icon($d['original_name']) . '"></i><span>' . e($d['original_name']) . '</span></a>';
}

/** Expiry badge for an ID date. */
function id_expiry_badge($date)
{
    if (!$date) {
        return '';
    }
    $days = (int) floor((strtotime($date) - strtotime(date('Y-m-d'))) / 86400);
    if ($days < 0) {
        return '<span class="badge text-bg-danger">ID expired ' . fmt_date($date) . '</span>';
    }
    if ($days <= 60) {
        return '<span class="badge text-bg-warning">ID expires ' . fmt_date($date) . '</span>';
    }
    return '<span class="badge text-bg-light">Valid until ' . fmt_date($date) . '</span>';
}
