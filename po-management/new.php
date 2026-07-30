<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-attachments.php';
require dirname(__DIR__) . '/includes/po-rework.php';

po_require_create();

$activeSlug = 'po-management';
$activePoSection = 'new';
$error = null;
$form = po_default_header();
$lines = [['sku' => '', 'quote_number' => '', 'description' => '', 'quantity' => '', 'unit_price' => '', 'expiration_date' => '']];
$copiedFrom = null;
$reworkFromTransfer = null;
$sourceTransferId = 0;
$copyFromId = (int) ($_GET['copy_from'] ?? 0);
$reworkFromTransferId = (int) ($_GET['rework_from_transfer'] ?? 0);
$suppliers = po_list_suppliers();

if (isset($_GET['ledger_profile']) && $copyFromId <= 0) {
    $form['ledger_profile'] = po_normalize_ledger_profile((string) $_GET['ledger_profile']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, $_POST);
    $sourceTransferId = (int) ($_POST['source_transfer_id'] ?? 0);
    $result = po_save_order($_POST);

    if ($result['ok']) {
        if (!empty($_FILES['source_pdf']['name'])) {
            po_save_attachment($result['id'], $_FILES['source_pdf'], 'SourcePDF');
        }
        if ($sourceTransferId > 0) {
            po_rework_link_return_po($sourceTransferId, (int) $result['id']);
        }
        header('Location: /po-management/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
    $lines = $_POST['lines'] ?? $lines;
} elseif ($reworkFromTransferId > 0) {
    $rework = po_rework_form_from_transfer($reworkFromTransferId);
    if (!$rework['ok']) {
        $error = $rework['error'];
    } else {
        $form = $rework['form'];
        $lines = $rework['lines'];
        $sourceTransferId = (int) $rework['source_transfer_id'];
        $reworkFromTransfer = [
            'id'    => $sourceTransferId,
            'label' => (string) $rework['source_transfer_label'],
        ];
    }
} elseif ($copyFromId > 0) {
    $duplicate = po_duplicate_form_from_order($copyFromId);
    if (!$duplicate['ok']) {
        $error = $duplicate['error'];
    } else {
        $form = $duplicate['form'];
        $lines = $duplicate['lines'];
        $copiedFrom = [
            'id'     => (int) $duplicate['source_po_id'],
            'number' => (string) $duplicate['source_po_number'],
        ];
    }
}

$pageTitle = $reworkFromTransfer !== null
    ? 'CMO Rework Return PO | PO Management'
    : ($copiedFrom !== null
    ? 'Duplicate ' . $copiedFrom['number'] . ' | PO Management'
    : 'New Purchase Order | PO Management');
$pageDescription = 'Create a new supplier purchase order.';

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

      <div class="page-hero">
        <div class="section-label">Procurement</div>
        <h1><?= $reworkFromTransfer !== null ? 'CMO Rework Return PO' : ($copiedFrom !== null ? 'Duplicate Purchase Order' : 'New Purchase Order') ?></h1>
        <p class="page-lead">
          <?php if ($reworkFromTransfer !== null): ?>
          Rework return for <a href="/inventory-transfers/view.php?id=<?= (int) $reworkFromTransfer['id'] ?>"><?= htmlspecialchars($reworkFromTransfer['label']) ?></a>.
          Save this zero-dollar PO, then receive at CART via PO Receiving and ship the linked CMO → CART return transfer.
          <?php elseif ($copiedFrom !== null): ?>
          Copied from <a href="/po-management/view.php?id=<?= (int) $copiedFrom['id'] ?>"><?= htmlspecialchars($copiedFrom['number']) ?></a>.
          Review the PO number, dates, and line items before saving.
          <?php else: ?>
          Enter NutraSeal-style header fields, line items, and optionally attach the source PDF.
          <?php endif; ?>
        </p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $sourceTransferId = $sourceTransferId ?? 0;
        require dirname(__DIR__) . '/includes/po-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
