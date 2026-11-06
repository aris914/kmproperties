<?php
/* Admin › Test Data (1.3.0): add made-up records for a full workflow, or clear them. Administrators only. */
require __DIR__ . '/includes/init.php';
require APP_ROOT . '/includes/testdata.php';
$me = require_admin('admin');

$ready = true;
try {
    q_val('SELECT is_test FROM properties LIMIT 1');
} catch (Exception $ex) {
    $ready = false;
}

if (is_post() && $ready) {
    csrf_check();
    if (post('action') === 'add') {
        $opt = ['rent' => post('rent'), 'sale' => post('sale'), 'tenants' => post('tenants'), 'owner' => post('owner'), 'photos' => post('photos'), 'docs' => post('docs')];
        if (!(int) $opt['rent'] && !(int) $opt['sale'] && !(int) $opt['tenants'] && !(int) $opt['owner']) {
            flash('error', 'Enter how many records to add.');
        } else {
            try {
                $r = td_populate($opt);
                $msg = "Added {$r['properties']} properties ({$r['submissions']} owner submissions), {$r['tenants']} tenants, {$r['leases']} leases, {$r['payments']} payments, {$r['jobs']} repair jobs, {$r['photos']} photos, {$r['documents']} documents and {$r['inquiries']} inquiries.";
                audit('insert', 'test_data', 'Test data added', $msg);
                flash('success', $msg);
            } catch (Exception $ex) {
                flash('error', 'Could not add all test data: ' . $ex->getMessage() . ' (anything added so far is marked as test data and can be cleared).');
            }
        }
    } elseif (post('action') === 'clear') {
        $scope = post('scope') === 'all' ? 'all' : 'test';
        if ($scope === 'all' && post('confirm') !== 'DELETE') {
            flash('error', 'To clear ALL records, type DELETE in the box.');
        } else {
            $r = td_clear($scope);
            $msg = ($scope === 'all' ? 'Cleared ALL data: ' : 'Cleared test data: ') . "{$r['properties']} properties, {$r['tenants']} tenants, {$r['leases']} leases, {$r['jobs']} repair jobs, {$r['photos']} photos, {$r['documents']} documents, {$r['inquiries']} inquiries and {$r['history']} history entries.";
            audit('delete', 'test_data', $scope === 'all' ? 'ALL records cleared' : 'Test data cleared', $msg);
            flash('success', $msg);
        }
    }
    redirect('admin/test-data.php');
}

$counts = $ready ? td_counts() : [];
admin_header('Test Data', 'testdata');
?>
<p class="hint">For trying the system out and training staff. Only administrators see this page. Users, settings, locations, the landing page and the audit log are never changed here.</p>
<?php if (!$ready): ?><div class="alert alert-warning">The database update for test data has not run yet. Reload this page in a moment.</div><?php endif; ?>

<div class="row g-4">
  <div class="col-xl-5">
    <div class="bkbox">
      <h2><i class="bi bi-bar-chart me-1"></i>What is in the database</h2>
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Records</th><th class="text-end">Total</th><th class="text-end">Test</th></tr></thead>
        <tbody>
        <?php foreach ($counts as $c): ?>
          <tr><td><?= e($c[0]) ?></td><td class="text-end"><?= number_format($c[1]) ?></td><td class="text-end"><?= $c[2] ? '<span class="pill">' . number_format($c[2]) . '</span>' : '<span class="text-muted">0</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="col-xl-7">
    <form method="post" class="bkbox">
      <?= csrf_field() ?><input type="hidden" name="action" value="add">
      <h2><i class="bi bi-plus-circle me-1"></i>Add test data</h2>
      <p class="hint">Made-up Filipino names, example.com emails and fictional buildings in your locations, marked as test data so they can be cleared later without touching real records. Listing codes start with <code>TEST-</code>.</p>
      <div class="row g-3">
        <div class="col-6 col-md-3"><label class="form-label">For rent <span class="hint">(0–30)</span></label><input type="number" name="rent" min="0" max="30" value="8" class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label">For sale <span class="hint">(0–20)</span></label><input type="number" name="sale" min="0" max="20" value="4" class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label">Tenants <span class="hint">(0–40)</span></label><input type="number" name="tenants" min="0" max="40" value="9" class="form-control"></div>
        <div class="col-6 col-md-3"><label class="form-label">Owner submissions <span class="hint">(0–10)</span></label><input type="number" name="owner" min="0" max="10" value="3" class="form-control"></div>
        <div class="col-12 d-flex flex-wrap gap-4">
          <label class="form-check"><input type="checkbox" class="form-check-input" name="photos" value="1" checked> Generated photos (3–4 per property)</label>
          <label class="form-check"><input type="checkbox" class="form-check-input" name="docs" value="1" checked> Sample PDF documents</label>
        </div>
      </div>
      <details class="mt-3 small">
        <summary class="text-muted">What the full workflow includes</summary>
        <ul class="mt-2 mb-0">
          <li><b>Re-let unit:</b> a former tenant who moved out (deposit partly refunded, repaint job) and a new tenant since last month — see its Tenancy history.</li>
          <li><b>Lease ending in 20 days</b> (shows on the dashboard), <b>lease starting in 15 days</b> (unit reserved, move-in inspection scheduled).</li>
          <li><b>Active leases</b> with deposit, advance and monthly rent payments; some are behind on rent, so the rent statement shows a balance due.</li>
          <li><b>Hidden unit</b> under renovation (repair job in progress), and vacant units with completed aircon cleaning.</li>
          <li><b>Repairs</b>: open, urgent, scheduled, in progress and completed with costs and receipts.</li>
          <li><b>For sale</b>: available, reserved and sold, with a sample title document.</li>
          <li><b>Owner submissions</b> waiting for approval, and one rejected.</li>
          <li><b>Inquiries</b> about listings and from the contact form, map pins, and history on every record.</li>
        </ul>
      </details>
      <button class="btn btn-primary mt-3" <?= $ready ? '' : 'disabled' ?> onclick="this.disabled=true;this.innerHTML='<span class=&quot;spinner-border spinner-border-sm me-2&quot;></span>Adding…';this.form.submit()"><i class="bi bi-plus-lg me-1"></i>Add test data</button>
      <span class="hint ms-2">Takes a few seconds with photos.</span>
    </form>

    <form method="post" class="bkbox" style="border-color:color-mix(in srgb, var(--danger) 45%, transparent)" onsubmit="return confirm(this.scope.value === 'all' ? 'Delete ALL properties, tenants, leases, payments, repairs, documents and inquiries? This cannot be undone.' : 'Delete all test data?')">
      <?= csrf_field() ?><input type="hidden" name="action" value="clear">
      <h2 class="text-danger"><i class="bi bi-trash3 me-1"></i>Clear data</h2>
      <p class="hint">Deletes properties with their photos, leases, payments, repairs, documents and history, plus tenants and inquiries. This cannot be undone.</p>
      <label class="form-check mb-1"><input type="radio" class="form-check-input" name="scope" value="test" checked onchange="document.getElementById('allBox').classList.add('d-none')"> Only test data (records made with Add test data)</label>
      <label class="form-check mb-2"><input type="radio" class="form-check-input" name="scope" value="all" onchange="document.getElementById('allBox').classList.remove('d-none')"> <b>ALL</b> properties, tenants and inquiries — real records too (start fresh)</label>
      <div id="allBox" class="d-none mb-2" style="max-width:320px"><label class="form-label">Type DELETE to confirm</label><input name="confirm" class="form-control" autocomplete="off"></div>
      <button class="btn btn-outline-danger" <?= $ready ? '' : 'disabled' ?>>Clear data…</button>
    </form>
  </div>
</div>
<?php admin_footer();
