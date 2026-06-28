<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-import.php';
require dirname(__DIR__) . '/includes/po-attachments.php';

po_require_create();

$activeSlug = 'po-management';
$activePoSection = 'import';
$error = null;
$warning = null;
$step = $_GET['step'] ?? 'upload';

if (isset($_GET['cancel'])) {
    po_import_clear_pending();
    header('Location: /po-management/import.php', true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $importAction = $_POST['import_action'] ?? 'upload';

    if ($importAction === 'create_supplier') {
        require dirname(__DIR__) . '/includes/supplier.php';

        $pending = po_import_pending_get();
        if ($pending === null) {
            $error = 'Import session expired. Upload the file again.';
            $step = 'upload';
        } else {
            $supplierResult = supplier_save($_POST);
            if (!$supplierResult['ok']) {
                $error = $supplierResult['error'];
                $step = 'supplier';
            } else {
                $importResult = po_import_finish($pending, (int) $supplierResult['id']);
                if ($importResult['ok']) {
                    $params = ['step' => 'complete', 'po_id' => (int) $importResult['id']];
                    if (!empty($importResult['warning'])) {
                        $params['warning'] = $importResult['warning'];
                    }
                    header('Location: /po-management/import.php?' . http_build_query($params), true, 302);
                    exit;
                }
                $error = $importResult['error'];
                $step = 'supplier';
            }
        }
    } else {
        $parsed = po_import_parse_upload($_FILES['import_file'] ?? []);
        if (!empty($parsed['supplier_missing'])) {
            $staged = po_import_stage_upload($_FILES['import_file']);
            if (!$staged['ok']) {
                $error = $staged['error'];
                $step = 'upload';
            } else {
                po_import_pending_set([
                    'header'            => $parsed['header'],
                    'lines'             => $parsed['lines'],
                    'staging_id'        => $staged['staging_id'],
                    'staging_path'      => $staged['path'],
                    'staging_filename'  => $staged['filename'],
                ]);
                header('Location: /po-management/import.php?step=supplier', true, 302);
                exit;
            }
        } elseif (!$parsed['ok']) {
            $error = $parsed['error'] ?? 'Unable to import file.';
            $step = 'upload';
        } else {
            $orderData = $parsed['data'];
            $lines = $orderData['lines'];
            $header = $orderData;
            unset($header['lines']);

            $pending = [
                'header'           => $header,
                'lines'            => $lines,
                'staging_filename' => (string) ($_FILES['import_file']['name'] ?? 'import'),
            ];
            $staged = po_import_stage_upload($_FILES['import_file']);
            if ($staged['ok']) {
                $pending['staging_path'] = $staged['path'];
                $pending['staging_id'] = $staged['staging_id'];
            }

            $supplierId = (int) ($orderData['supplier_id'] ?? 0);
            $importResult = po_import_finish($pending, $supplierId);
            if ($importResult['ok']) {
                $params = ['step' => 'complete', 'po_id' => (int) $importResult['id']];
                if (!empty($importResult['warning'])) {
                    $params['warning'] = $importResult['warning'];
                }
                header('Location: /po-management/import.php?' . http_build_query($params), true, 302);
                exit;
            }
            po_import_clear_pending();
            $error = $importResult['error'];
            $step = 'upload';
        }
    }
}

$completePo = null;
if ($step === 'complete') {
    $poId = (int) ($_GET['po_id'] ?? 0);
    $completePo = $poId > 0 ? po_get_order($poId) : null;
    if ($completePo === null) {
        $step = 'upload';
        $error = 'Imported purchase order could not be found.';
    } else {
        $warning = $_GET['warning'] ?? null;
    }
}

$supplierForm = null;
$importSupplierName = '';
if ($step === 'supplier') {
    $pending = po_import_pending_get();
    if ($pending === null) {
        $step = 'upload';
        $error = 'Import session expired. Upload the file again.';
    } else {
        require_once dirname(__DIR__) . '/includes/supplier.php';
        $supplierForm = po_import_supplier_form_from_header($pending['header']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['import_action'] ?? '') === 'create_supplier') {
            $supplierForm = supplier_from_input($_POST);
        }
        $importSupplierName = (string) ($pending['header']['supplier_name'] ?? '');
    }
}

$pageTitle = 'Import Purchase Order | PO Management';
$pageDescription = 'Import a purchase order from the Excel or CSV template.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      if ($step === 'complete' && $completePo !== null) {
          $importTitle = 'Import complete';
          $importLead = 'Purchase order <strong>' . htmlspecialchars($completePo['PONumber']) . '</strong> was created from your import file.';
          $importLeadHtml = true;
      } elseif ($step === 'supplier' && $supplierForm !== null) {
          $importTitle = 'Create supplier for import';
          $importLead = 'The supplier in your import file is not in the system yet. Review the details below, then create the supplier and finish the import.';
          $importLeadHtml = false;
      } else {
          $importTitle = 'Import Purchase Order';
          $importLead = 'Upload a completed Excel or CSV template to create a draft PO.';
          $importLeadHtml = false;
      }
      render_list_page_header([
          'back_href'  => '/po-management/',
          'back_label' => 'Back to Purchase Orders',
          'category'   => 'Procurement',
          'title'      => $importTitle,
          'lead'       => $importLead,
          'lead_html'  => $importLeadHtml,
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php if ($step === 'complete' && $completePo !== null): ?>

      <?php if ($warning !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($warning) ?></div>
      <?php endif; ?>

      <div class="admin-notice is-success" role="status">
        The draft PO for <?= htmlspecialchars($completePo['SupplierName']) ?> is ready for review.
      </div>

      <?php render_list_page_toolbar(
          '<a class="btn-primary" href="/po-management/view.php?id=' . (int) $completePo['POID'] . '">View Imported PO</a>'
          . '<a class="btn-secondary" href="/po-management/import.php">Import new PO</a>'
          . '<a class="btn-secondary" href="/po-management/">Back to PO list</a>'
      ); ?>

      <?php elseif ($step === 'supplier' && $supplierForm !== null): ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $form = $supplierForm;
        require dirname(__DIR__) . '/includes/po-import-supplier-step.php';
      ?>

      <?php else: ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="account-card" style="margin-bottom: 20px;">
        <h2>How to use the template</h2>
        <ol class="import-steps">
          <li>Download the blank <strong>Excel</strong> or <strong>CSV</strong> template below.</li>
          <li>Drop your signed PO PDF into ChatGPT or Claude and ask it to fill the template fields from the PDF.</li>
          <li>Review the populated Header and Lines sheets (or CSV sections).</li>
          <li>Upload the completed file here to create a draft purchase order.</li>
          <li>If the supplier does not exist yet, you can create it during import before the PO is saved.</li>
        </ol>
        <div class="module-actions">
          <a class="btn-secondary" href="/po-management/template.php?type=xlsx">Download Excel template</a>
          <a class="btn-secondary" href="/po-management/template.php?type=csv">Download CSV template</a>
        </div>
      </div>

      <form class="admin-form" method="post" enctype="multipart/form-data" action="/po-management/import.php">
        <input type="hidden" name="import_action" value="upload" />
        <div class="form-group">
          <label for="import_file">Import file (.xlsx or .csv)</label>
          <input class="form-input" type="file" id="import_file" name="import_file" accept=".xlsx,.xls,.csv" required />
        </div>
        <div class="module-actions">
          <button class="btn-primary" type="submit">Import and Create PO</button>
          <a class="btn-secondary" href="/po-management/">Cancel</a>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
