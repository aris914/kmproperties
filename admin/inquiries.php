<?php
require __DIR__ . '/includes/init.php';
require_admin();

if (is_post()) {
    csrf_check();
    $inq = q_one('SELECT * FROM inquiries WHERE id = ?', [(int) post('id')]);
    if ($inq) {
        $action = post('action');
        if ($action === 'update') {
            q('UPDATE inquiries SET status = ?, admin_notes = ? WHERE id = ?', [in_array(post('status'), ['new', 'contacted', 'closed'], true) ? post('status') : 'new', nn(post('admin_notes')), $inq['id']]);
            audit('update', 'inquiries', $inq['name'], 'Status: ' . post('status'));
            flash('success', 'Inquiry updated.');
            redirect('admin/inquiries.php?view=' . $inq['id']);
        } elseif ($action === 'to_tenant') {
            $parts = preg_split('/\s+/', trim($inq['name']));
            $last = count($parts) > 1 ? array_pop($parts) : '';
            $tid = db_insert('tenants', [
                'first_name' => implode(' ', $parts) ?: $inq['name'], 'last_name' => $last ?: '-',
                'email' => $inq['email'], 'phone' => $inq['phone'], 'status' => 'prospect',
                'notes' => 'From website inquiry on ' . fmt_date($inq['created_at']) . ($inq['message'] ? ":\n" . $inq['message'] : ''),
            ]);
            log_history($inq['property_id'], $tid, 'created', 'Prospect tenant created from website inquiry');
            q("UPDATE inquiries SET status = 'contacted' WHERE id = ? AND status = 'new'", [$inq['id']]);
            audit('insert', 'tenants', $inq['name'], 'Created from website inquiry #' . $inq['id']);
            flash('success', 'Tenant record created — check the name split and complete the details.');
            redirect('admin/tenant-edit.php?id=' . $tid);
        } elseif ($action === 'delete') {
            q('DELETE FROM inquiries WHERE id = ?', [$inq['id']]);
            audit('delete', 'inquiries', $inq['name'], $inq['inquiry_type'] . ' inquiry of ' . $inq['created_at']);
            flash('success', 'Inquiry deleted.');
        }
    }
    redirect('admin/inquiries.php');
}

$view = get('view') ? q_one('SELECT i.*, p.title, p.slug FROM inquiries i LEFT JOIN properties p ON p.id = i.property_id WHERE i.id = ?', [(int) get('view')]) : null;
$status = get('status');
$type = get('type');
$where = ['1=1'];
$params = [];
if ($status) { $where[] = 'i.status = ?'; $params[] = $status; }
if ($type) { $where[] = 'i.inquiry_type = ?'; $params[] = $type; }
$rows = q_all('SELECT i.*, p.title FROM inquiries i LEFT JOIN properties p ON p.id = i.property_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.created_at DESC LIMIT 300', $params);
$typeLabels = ['property' => 'Property inquiry', 'listing' => 'List my property', 'contact' => 'Contact form'];

admin_header('Inquiries', 'inquiries');
?>
<div class="row g-4">
  <div class="<?= $view ? 'col-xl-6' : 'col-12' ?>">
    <form class="d-flex flex-wrap gap-2 mb-3" method="get">
      <select name="status" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">Any status</option><?= select_options(['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'], $status, true) ?></select>
      <select name="type" class="form-select form-select-sm w-auto" onchange="this.form.submit()"><option value="">All types</option><?= select_options($typeLabels, $type, true) ?></select>
    </form>
    <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>From</th><th>About</th><th>Received</th><th>Status</th></tr></thead>
      <tbody>
      <?php if (!$rows): ?><tr><td colspan="4" class="text-center text-muted py-4">No inquiries.</td></tr><?php endif; ?>
      <?php foreach ($rows as $i): ?>
        <tr class="clickable<?= $view && (int) $view['id'] === (int) $i['id'] ? ' table-active' : '' ?><?= $i['status'] === 'new' ? ' fw-semibold' : '' ?>" onclick="location.href='<?= url('admin/inquiries.php?view=' . $i['id'] . ($status ? '&status=' . urlencode($status) : '') . ($type ? '&type=' . urlencode($type) : '')) ?>'">
          <td><?= e($i['name']) ?><div class="small text-muted fw-normal"><?= e($i['phone']) ?> <?= e($i['email']) ?></div></td>
          <td class="small fw-normal"><span class="badge text-bg-light"><?= e($typeLabels[$i['inquiry_type']]) ?></span><div class="text-truncate" style="max-width:280px"><?= e($i['title'] ?: mb_substr((string) $i['message'], 0, 80)) ?></div></td>
          <td class="small fw-normal text-nowrap"><?= fmt_date($i['created_at'], 'M j, g:i A') ?></td>
          <td><?= status_badge($i['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div></div>
  </div>

  <?php if ($view): ?>
  <div class="col-xl-6">
    <div class="card sticky-xl-top" style="top:80px">
      <div class="card-header d-flex justify-content-between"><span><?= e($typeLabels[$view['inquiry_type']]) ?></span><a href="<?= url('admin/inquiries.php') ?>" class="btn-close"></a></div>
      <div class="card-body">
        <h3 class="h5 mb-1"><?= e($view['name']) ?></h3>
        <div class="mb-3 small">
          <?php if ($view['phone']): ?><a href="tel:<?= e($view['phone']) ?>" class="me-3"><i class="bi bi-telephone"></i> <?= e($view['phone']) ?></a><?php endif; ?>
          <?php if ($view['email']): ?><a href="mailto:<?= e($view['email']) ?>"><i class="bi bi-envelope"></i> <?= e($view['email']) ?></a><?php endif; ?>
          <div class="text-muted"><?= fmt_date($view['created_at'], 'l, M j, Y g:i A') ?></div>
        </div>
        <?php if ($view['title']): ?><div class="alert alert-light border small mb-3"><i class="bi bi-building"></i> <a href="<?= url('admin/property-view.php?id=' . $view['property_id']) ?>"><?= e($view['title']) ?></a> · <a href="<?= url('property.php?slug=' . urlencode($view['slug'])) ?>" target="_blank">view listing</a></div><?php endif; ?>
        <?php if ($view['extra']): ?><div class="bg-light rounded p-3 small mb-3"><?= nl2br(e($view['extra'])) ?></div><?php endif; ?>
        <div class="mb-3"><?= $view['message'] ? nl2br(e($view['message'])) : '<span class="text-muted">(no message)</span>' ?></div>
        <form method="post" class="row g-2">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= $view['id'] ?>"><input type="hidden" name="action" value="update">
          <div class="col-md-4"><label class="form-label small">Status</label><select name="status" class="form-select"><?= select_options(['new' => 'New', 'contacted' => 'Contacted', 'closed' => 'Closed'], $view['status'], true) ?></select></div>
          <div class="col-md-8"><label class="form-label small">Internal notes</label><textarea name="admin_notes" class="form-control" rows="2"><?= e($view['admin_notes']) ?></textarea></div>
          <div class="col-12 d-flex flex-wrap gap-2 justify-content-between mt-2">
            <div class="d-flex gap-2">
              <button type="submit" form="toTenant" class="btn btn-sm btn-outline-primary"><i class="bi bi-person-plus me-1"></i>Create tenant</button>
              <button type="submit" form="delInq" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </div>
            <button class="btn btn-primary">Save</button>
          </div>
        </form>
        <form method="post" id="toTenant"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $view['id'] ?>"><input type="hidden" name="action" value="to_tenant"></form>
        <form method="post" id="delInq" onsubmit="return confirm('Delete this inquiry?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $view['id'] ?>"><input type="hidden" name="action" value="delete"></form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php admin_footer();
