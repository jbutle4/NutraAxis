<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-production.php';
require dirname(__DIR__) . '/includes/po-production-import.php';

po_require_update();

$activeSlug = 'po-management';
$activePoSection = 'production-import';
$error = null;
$step = $_GET['step'] ?? 'upload';
$result = null;

if (isset($_GET['cancel'])) {
    po_production_import_clear_pending();
    header('Location: /po-management/import-production-status.php', true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['import_action'] ?? 'upload';

    if ($action === 'apply') {
        $pending = po_production_import_pending_get();
        if ($pending === null || empty($pending['rows'])) {
            $error = 'Import session expired. Upload the file again.';
            $step = 'upload';
        } else {
            $applyResult = po_production_import_apply($pending['rows'], auth_user()['UserID'] ?? null);
            if (!$applyResult['ok']) {
                $error = $applyResult['error'];
                $step = 'preview';
                $previewRows = po_production_import_resolve_rows($pending['rows']);
            } else {
                po_production_import_clear_pending();
                $result = $applyResult;
                $step = 'complete';
            }
        }
    } else {
        $parsed = po_production_import_parse_upload($_FILES['import_file'] ?? []);
        if (!$parsed['ok']) {
            $error = $parsed['error'] ?? 'Unable to import file.';
            $step = 'upload';
        } else {
            $staged = po_import_stage_upload($_FILES['import_file']);
            po_production_import_pending_set([
                'rows'             => $parsed['rows'],
                'staging_path'     => $staged['ok'] ? $staged['path'] : null,
                'staging_filename' => (string) ($_FILES['import_file']['name'] ?? 'import'),
            ]);
            header('Location: /po-management/import-production-status.php?step=preview', true, 302);
            exit;
        }
    }
}

$previewRows = null;
$pendingFilename = null;
if ($step === 'preview') {
    $pending = po_production_import_pending_get();
    if ($pending === null || empty($pending['rows'])) {
        $step = 'upload';
        $error = $error ?? 'Import session expired. Upload the file again.';
    } else {
        $previewRows = po_production_import_resolve_rows($pending['rows']);
        $pendingFilename = (string) ($pending['staging_filename'] ?? 'import');
    }
}

$pageTitle = 'Import Production Status | PO Management';
$pageDescription = 'Update PO production status from a Wells open-order report or similar spreadsheet.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-management/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Purchase Orders
      </a>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php if ($step === 'complete' && $result !== null): ?>
      <div class="page-hero">
        <div class="section-label">Procurement</div>
        <h1>Production status import complete</h1>
        <p class="page-lead">Updated <?= (int) $result['updated'] ?> PO line(s)<?php if ((int) $result['skipped'] > 0): ?>, skipped <?= (int) $result['skipped'] ?><?php endif; ?>.</p>
      </div>

      <?php if (!empty($result['errors'])): ?>
      <div class="admin-notice is-error is-detail" role="alert">
        <ul>
          <?php foreach ($result['errors'] as $rowError): ?>
          <li><?= htmlspecialchars($rowError) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <div class="admin-notice is-success" role="status">Production status records were saved successfully.</div>

      <div class="module-actions">
        <a class="btn-primary" href="/po-management/">Back to PO list</a>
        <a class="btn-secondary" href="/po-management/import-production-status.php">Import another file</a>
      </div>

      <?php elseif ($step === 'preview' && $previewRows !== null): ?>
      <?php
        $readyCount = count(array_filter($previewRows, fn(array $row): bool => !empty($row['match']['found']) && !empty($row['match']['editable'])));
        $skipCount = count($previewRows) - $readyCount;
      ?>
      <div class="page-hero">
        <div class="section-label">Procurement</div>
        <h1>Review production status import</h1>
        <p class="page-lead">
          File: <strong><?= htmlspecialchars($pendingFilename) ?></strong>
          · <?= count($previewRows) ?> row(s)
          · <?= $readyCount ?> ready to update
          <?php if ($skipCount > 0): ?> · <?= $skipCount ?> will be skipped<?php endif; ?>
        </p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="account-card production-status-card">
        <p class="account-card-lead">Rows are matched by <strong>PO number</strong> and <strong>SKU number</strong> (ItemSKU on the PO line). Status values from the spreadsheet are normalized to the system’s production status options.</p>

        <div class="admin-table-wrap production-status-table-wrap">
          <table class="admin-table production-status-table">
            <thead>
              <tr>
                <th>Row</th>
                <th>PO / SKU</th>
                <th>Match</th>
                <th>MFG</th>
                <th>Bottle / pkg</th>
                <th>Bulk test</th>
                <th>Bottle test</th>
                <th>Target ship</th>
                <th>Pallets</th>
                <th>Est. lbs</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($previewRows as $row): ?>
              <?php $mapped = $row['mapped']; ?>
              <tr>
                <td><?= (int) $row['row_number'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($row['po_number']) ?></strong><br />
                  <span class="text-muted"><?= htmlspecialchars($row['sku']) ?></span>
                  <?php if ($row['product'] !== ''): ?>
                  <br /><span class="text-muted"><?= htmlspecialchars($row['product']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($row['match']['found']) && !empty($row['match']['editable'])): ?>
                  <span class="status-badge is-success">Line <?= (int) $row['match']['line_number'] ?></span>
                  <?php elseif (!empty($row['match']['found'])): ?>
                  <span class="status-badge is-warning" title="<?= htmlspecialchars((string) ($row['match']['error'] ?? '')) ?>">Not editable</span>
                  <?php else: ?>
                  <span class="status-badge is-error" title="<?= htmlspecialchars((string) ($row['match']['error'] ?? '')) ?>">Not found</span>
                  <?php endif; ?>
                  <?php if (!empty($row['warnings'])): ?>
                  <ul class="import-warning-list">
                    <?php foreach ($row['warnings'] as $warning): ?>
                    <li><?= htmlspecialchars($warning) ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($mapped['mfg_status']) ?></td>
                <td><?= htmlspecialchars($mapped['bottle_packaging_status']) ?></td>
                <td><?= htmlspecialchars($mapped['bulk_test_status']) ?></td>
                <td><?= htmlspecialchars($mapped['bottle_test_status']) ?></td>
                <td><?= htmlspecialchars(po_format_date($mapped['target_ship_date'])) ?></td>
                <td><?= $mapped['pallet_count'] !== null ? htmlspecialchars((string) $mapped['pallet_count']) : '—' ?></td>
                <td><?= $mapped['est_weight_lbs'] !== null ? htmlspecialchars((string) $mapped['est_weight_lbs']) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <form class="admin-form" method="post" action="/po-management/import-production-status.php?step=preview">
          <input type="hidden" name="import_action" value="apply" />
          <div class="module-actions">
            <?php if ($readyCount > 0): ?>
            <button class="btn-primary" type="submit">Apply <?= $readyCount ?> update(s)</button>
            <?php endif; ?>
            <a class="btn-secondary" href="/po-management/import-production-status.php?cancel=1">Cancel</a>
          </div>
        </form>
      </div>

      <?php else: ?>
      <div class="page-hero">
        <div class="section-label">Procurement</div>
        <h1>Import Production Status</h1>
        <p class="page-lead">Upload a Wells open-order report or similar spreadsheet to update production status on existing PO lines.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="account-card" style="margin-bottom: 20px;">
        <h2>Expected file format</h2>
        <p class="account-card-lead">The import looks for a header row containing at least <strong>PO#</strong> and <strong>SKUNumber</strong>, then updates matching PO lines.</p>
        <ul class="import-steps">
          <li><strong>PO#</strong> — matched to <code>PONumber</code> on the purchase order</li>
          <li><strong>SKUNumber</strong> — matched to <code>ItemSKU</code> on the PO line</li>
          <li><strong>MFG Status</strong>, <strong>Bottle Status</strong>, <strong>Bulk Test Status</strong>, <strong>Bottle Test Status</strong> — mapped to production status fields</li>
          <li><strong>Label Status</strong> — stored in line comments (the system tracks label progress in comments)</li>
          <li><strong>Target Ship Date</strong>, <strong>Pallet count</strong>, <strong>Est weight</strong>, <strong>Comments</strong> — imported when present</li>
          <li>Only approved, paid, or submitted-for-payment POs can be updated.</li>
        </ul>
      </div>

      <form class="admin-form" method="post" enctype="multipart/form-data" action="/po-management/import-production-status.php">
        <input type="hidden" name="import_action" value="upload" />
        <div class="form-group">
          <label for="import_file">Spreadsheet (.xlsx or .csv)</label>
          <input class="form-input" type="file" id="import_file" name="import_file" accept=".xlsx,.xls,.csv" required />
        </div>
        <div class="module-actions">
          <button class="btn-primary" type="submit">Upload and preview</button>
          <a class="btn-secondary" href="/po-management/">Cancel</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
