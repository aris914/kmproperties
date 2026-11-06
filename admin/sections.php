<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/sections-config.php';
require_admin();

if (is_post()) {
    csrf_check();
    if (post('action') === 'order') {
        foreach ((array) ($_POST['sort'] ?? []) as $id => $sort) {
            q('UPDATE landing_sections SET sort_order = ? WHERE id = ?', [(int) $sort, (int) $id]);
        }
        flash('success', 'Section order saved.');
    } elseif (post('action') === 'toggle') {
        q('UPDATE landing_sections SET is_active = 1 - is_active WHERE id = ?', [(int) post('id')]);
        flash('success', 'Section visibility updated.');
    }
    redirect('admin/sections.php');
}

$sections = q_all('SELECT s.*, (SELECT COUNT(*) FROM section_items i WHERE i.section_id = s.id) AS item_count FROM landing_sections s ORDER BY sort_order, id');
admin_header('Landing Page', 'sections');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <p class="text-muted mb-0">Edit the content of each homepage section, hide the ones you don’t need, and change the order (lower number = higher on the page).</p>
  <a href="<?= url('index.php') ?>" target="_blank" class="btn btn-outline-secondary"><i class="bi bi-eye me-1"></i>Preview homepage</a>
</div>

<form method="post" class="card">
  <?= csrf_field() ?><input type="hidden" name="action" value="order">
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th style="width:90px">Order</th><th>Section</th><th>Content</th><th>Visible</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($sections as $s): $cfg = section_config($s['section_key']); ?>
      <tr class="<?= $s['is_active'] ? '' : 'table-light text-muted' ?>">
        <td><input type="number" name="sort[<?= $s['id'] ?>]" value="<?= (int) $s['sort_order'] ?>" class="form-control form-control-sm" style="width:75px"></td>
        <td><strong><?= e($s['label']) ?></strong><div class="small text-muted"><?= e($cfg['help']) ?></div></td>
        <td class="small"><?= e($s['title'] ?: '—') ?><?php if ($cfg['items'] !== null): ?><br><span class="text-muted"><?= (int) $s['item_count'] ?> item(s)</span><?php endif; ?></td>
        <td>
          <button type="submit" form="toggle<?= $s['id'] ?>" class="btn btn-sm <?= $s['is_active'] ? 'btn-success' : 'btn-outline-secondary' ?>"><?= $s['is_active'] ? '<i class="bi bi-eye"></i> Shown' : '<i class="bi bi-eye-slash"></i> Hidden' ?></button>
        </td>
        <td class="text-end"><a href="<?= url('admin/section-edit.php?id=' . $s['id']) ?>" class="btn btn-sm btn-navy"><i class="bi bi-pencil me-1"></i>Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="card-footer text-end"><button class="btn btn-primary"><i class="bi bi-sort-numeric-down me-1"></i>Save order</button></div>
</form>
<?php foreach ($sections as $s): ?>
  <form method="post" id="toggle<?= $s['id'] ?>" class="d-none"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $s['id'] ?>"></form>
<?php endforeach; ?>
<?php admin_footer();
