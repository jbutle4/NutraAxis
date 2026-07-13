<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-payment.php';

po_payment_require_read();

$activeSlug = 'po-payments';
$hubBack = app_module_hub_back_link('po-payments');
$typeFilter = $_GET['type'] ?? '';
$search = trim($_GET['q'] ?? '');
$listFilters = [
    'type' => $typeFilter !== '' ? $typeFilter : null,
    'q'    => $search !== '' ? $search : null,
] + table_sort_state(PO_PAYMENT_LIST_SORT_COLUMNS, 'payment_date', 'desc', $_GET);
$payments = po_payment_list($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'Supplier Payments | Procurement';
$pageDescription = 'Track payments made against purchase orders.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="<?= htmlspecialchars($hubBack['href']) ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        <?= htmlspecialchars($hubBack['label']) ?>
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Inventory</div>
          <h1>PO Payments</h1>
          <p class="page-lead">Record and review payments applied to purchase orders.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(po_permission_value())) ?></p>
        </div>
        <?php if (po_payment_can_create()): ?>
        <a class="btn-primary" href="/po-payments/new.php">Record Payment</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Payment recorded successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Payment updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Payment deleted successfully.</div>
      <?php endif; ?>

      <form class="po-filter audit-filter" method="get" action="/po-payments/">
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
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="PO number, supplier, confirmation #, or payer" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="/po-payments/">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                PO_PAYMENT_LIST_SORT_COLUMNS,
                '/po-payments',
                $listFilters,
                ['type', 'q'],
                PO_PAYMENT_LIST_SORT_NUMERIC,
                'payment_date',
                'desc',
                'payment_date',
                table_actions_header(po_payment_can_update() ? ['View PO', 'Edit'] : ['View PO'])
            ); ?>
          </thead>
          <tbody>
            <?php if ($payments === []): ?>
            <tr><td colspan="8">No payments match your filters.</td></tr>
            <?php else: ?>
            <?php foreach ($payments as $payment): ?>
            <tr>
              <td><?= htmlspecialchars(po_payment_format_datetime($payment['PaymentDate'])) ?></td>
              <td><a class="btn-text" href="/po-management/view.php?id=<?= (int) $payment['POID'] ?>"><?= htmlspecialchars($payment['PONumber']) ?></a></td>
              <td><?= htmlspecialchars($payment['SupplierName']) ?></td>
              <td><?= htmlspecialchars(po_format_money($payment['PaymentAmount'])) ?></td>
              <td><?= htmlspecialchars($payment['PaymentType']) ?></td>
              <td><?= htmlspecialchars($payment['PaymentConfNumber'] ?? '—') ?></td>
              <td><?= htmlspecialchars($payment['PaymentMadeBy'] ?? '—') ?></td>
              <?php
              $paymentActions = [
                  ['href' => '/po-management/view.php?id=' . (int) $payment['POID'], 'label' => 'View PO'],
              ];
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
