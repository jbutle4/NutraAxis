<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_read();

$activeSlug = 'po-payments';
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'type'   => $typeFilter !== '' ? $typeFilter : null,
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(PO_PAYMENT_LIST_SORT_COLUMNS, 'payment_date', 'desc', $_GET);
$payments = po_payment_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'PO Payments | Inventory Management';
$pageDescription = 'Track payments made against purchase orders.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      $listToolbar = po_payment_can_create() ? '<a class="btn-primary" href="/po-payments/new.php">Record Payment</a>' : '';
      render_list_page_header([
          'back_href'  => '/inventory-management/',
          'back_label' => 'Back to Inventory Management',
          'category'   => 'Inventory',
          'title'      => 'Supplier Payments',
          'lead'       => 'Record and review payments applied to purchase orders or supplier invoices without a PO.',
          'permission' => permission_label(po_permission_value()),
      ]);
      ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Payment recorded successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Payment updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Payment deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/po-payments/">
        <?php table_sort_hidden_inputs($listFilters, 'payment_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="type">Payment type</label>
            <select class="form-input" id="type" name="type">
              <option value="">All types</option>
              <?php foreach (PO_PAYMENT_TYPES as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>" <?= $typeFilter === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="status">Payment status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (PO_PAYMENT_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="PO number, invoice #, supplier, confirmation #, or payer" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/po-payments/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                PO_PAYMENT_LIST_SORT_COLUMNS,
                '/po-payments',
                $listFilters,
                ['type', 'status', 'q'],
                PO_PAYMENT_LIST_SORT_NUMERIC,
                'payment_date',
                'desc',
                'payment_date',
                table_actions_header(po_payment_can_update() ? ['View', 'Edit'] : ['View'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($payments === []): ?>
            <tr><td colspan="9">No payments match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($payments as $payment): ?>
            <tr>
              <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
              <td>
                <?php $referenceHref = po_payment_reference_href($payment); ?>
                <?php if ($referenceHref !== null): ?>
                <a class="btn-text" href="<?= htmlspecialchars($referenceHref) ?>"><?= htmlspecialchars(po_payment_reference_label($payment)) ?></a>
                <?php else: ?>
                <?= htmlspecialchars(po_payment_reference_label($payment)) ?>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars($payment['SupplierName']) ?></td>
              <td><?= htmlspecialchars(po_format_money($payment['PaymentAmount'])) ?></td>
              <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
              <td><span class="status-badge <?= po_payment_status_class((string) ($payment['PaymentStatus'] ?? '')) ?>"><?= htmlspecialchars(po_payment_format_status($payment['PaymentStatus'] ?? null)) ?></span></td>
              <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
              <td><?= htmlspecialchars($payment['PaymentMadeBy'] ?? '—') ?></td>
              <td>
                <?php $attachmentCount = (int) ($payment['AttachmentCount'] ?? 0); ?>
                <?php if ($attachmentCount > 0 && po_payment_can_update()): ?>
                <a class="btn-text" href="/po-payments/edit.php?id=<?= (int) $payment['PaymentID'] ?>"><?= $attachmentCount === 1 ? '1 file' : $attachmentCount . ' files' ?></a>
                <?php else: ?>
                <?= $attachmentCount > 0 ? ($attachmentCount === 1 ? '1 file' : $attachmentCount . ' files') : '—' ?>
                <?php endif; ?>
              </td>
              <?php
              $paymentActions = [];
              $referenceHref = po_payment_reference_href($payment);
              if ($referenceHref !== null) {
                  $paymentActions[] = ['href' => $referenceHref, 'label' => 'View PO'];
              }
              if (po_payment_can_update()) {
                  $paymentActions[] = ['href' => '/po-payments/edit.php?id=' . (int) $payment['PaymentID'], 'label' => 'Edit'];
              }
              table_actions_cell($paymentActions);
              ?>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
