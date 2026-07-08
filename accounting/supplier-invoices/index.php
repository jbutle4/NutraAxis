<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/accounting.php';
require dirname(__DIR__, 2) . '/includes/supplier-invoice.php';

if (isset($_GET['new'])) {
    $params = $_GET;
    unset($params['new']);
    $redirect = '/accounting/supplier-invoices/new.php';
    if ($params !== []) {
        $redirect .= '?' . http_build_query($params);
    }
    header('Location: ' . $redirect, true, 302);
    exit;
}

supplier_invoice_require_read();

$activeSlug = 'accounting';
$accountingSection = 'invoices';
$statusFilter = $_GET['status'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'status' => $statusFilter !== '' ? $statusFilter : null,
    'q'      => $search !== '' ? $search : null,
] + table_sort_state(SUPPLIER_INVOICE_LIST_SORT_COLUMNS, 'txn_date', 'desc', $_GET);
$invoices = supplier_invoice_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Supplier Invoices | Accounting';
$pageDescription = 'Create and manage supplier invoices for QuickBooks Bill sync.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner page-inner--wide">
      <?php
      $listToolbar = supplier_invoice_can_create() ? '<a class="btn-primary" href="/accounting/supplier-invoices/new.php">New Invoice</a>' : '';
      render_list_page_header([
          'back_href'  => '/accounting/',
          'back_label' => 'Back to Accounting',
          'category'   => 'Finance',
          'title'      => 'Supplier Invoices',
          'lead'       => 'Create vendor invoices, attach source documents, and prepare bills for QuickBooks sync.',
          'permission' => permission_label(accounting_permission_value()),
      ]);
      ?>

      <?php require dirname(__DIR__, 2) . '/includes/accounting-nav.php'; ?>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Invoice created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Invoice updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Invoice deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="/accounting/supplier-invoices/">
        <?php table_sort_hidden_inputs($listFilters, 'txn_date', 'desc'); ?>
        <div class="audit-filter-grid">
          <div>
            <label for="status">Sync status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (SUPPLIER_INVOICE_SYNC_STATUSES as $status): ?>
              <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Invoice #, supplier, PO, or notes" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/accounting/supplier-invoices/">Clear</a>
        </div>
      </form>

      <?php render_list_page_toolbar($listToolbar !== '' ? $listToolbar : null); ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                SUPPLIER_INVOICE_LIST_SORT_COLUMNS,
                '/accounting/supplier-invoices',
                $listFilters,
                ['status', 'q'],
                SUPPLIER_INVOICE_LIST_SORT_NUMERIC,
                'txn_date',
                'desc',
                'txn_date',
                table_actions_header(
                    supplier_invoice_can_update() ? ['View', 'Edit'] : ['View']
                )
            ); ?>
          </thead>
          <tbody>
            <?php if ($invoices === []): ?>
            <tr><td colspan="9">No supplier invoices match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($invoices as $invoice): ?>
            <tr>
              <td><?= htmlspecialchars(accounting_format_date($invoice['TxnDate'])) ?></td>
              <td><a class="btn-text" href="/accounting/supplier-invoices/view.php?id=<?= (int) $invoice['SupplierInvoiceID'] ?>"><?= htmlspecialchars(supplier_invoice_reference($invoice)) ?></a></td>
              <td><?= htmlspecialchars($invoice['SupplierName']) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($invoice['TotalAmt'])) ?></td>
              <td><?= htmlspecialchars(accounting_format_money($invoice['Balance'] ?? max(0, (float) $invoice['TotalAmt'] - (float) ($invoice['PaidAmt'] ?? 0)))) ?></td>
              <td><?= htmlspecialchars(accounting_format_date($invoice['DueDate'])) ?></td>
              <td><span class="status-badge <?= supplier_invoice_status_class((string) $invoice['SyncStatus']) ?>"><?= htmlspecialchars($invoice['SyncStatus']) ?></span></td>
              <td><?= !empty($invoice['PONumber']) ? htmlspecialchars($invoice['PONumber']) : '—' ?></td>
              <?php
              $actions = [['href' => '/accounting/supplier-invoices/view.php?id=' . (int) $invoice['SupplierInvoiceID'], 'label' => 'View']];
              if (supplier_invoice_can_update() && supplier_invoice_is_editable($invoice)) {
                  $actions[] = ['href' => '/accounting/supplier-invoices/edit.php?id=' . (int) $invoice['SupplierInvoiceID'], 'label' => 'Edit'];
              }
              table_actions_cell($actions);
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
require dirname(__DIR__, 2) . '/includes/footer.php';
