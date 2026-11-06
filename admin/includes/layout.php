<?php
require __DIR__ . '/theme.php';
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
