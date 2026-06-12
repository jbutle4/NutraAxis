<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-approval.php';

po_require_read();

if (po_can_read_approval_queue() && !po_can_create() && !isset($_GET['skip_approver_redirect'])) {
    header('Location: /po-management/approvals.php', true, 302);
    exit;
}

$activeSlug = 'po-management';
$activePoSection = 'list';
$canCreate = po_can_create();
$canUpdate = po_can_update();
$canDelete = po_can_delete();
$canApprove = po_can_read_approval_queue();
$pendingApprovalCount = $canApprove ? po_count_pending_approvals() : 0;
$statusFilter = $_GET['status'] ?? '';
$orders = po_list_orders($statusFilter !== '' ? $statusFilter : null);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'PO Management | NutraAxis Operations';
$pageDescription = 'Create, track, and manage purchase orders across suppliers.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <div class="admin-header">
        <div>
          <div class="section-label">Procurement</div>
          <h1>Purchase Orders</h1>
          <p class="page-lead">Create and track supplier purchase orders from creation through approval and payment.</p>
          <p class="permission-note">Your access: <?= htmlspecialchars(permission_label(po_permission_value())) ?></p>
        </div>
        <?php if ($canCreate): ?>
        <a class="btn-primary" href="/po-management/new.php">New Purchase Order</a>
        <a class="btn-secondary" href="/po-management/import.php">Import from Excel</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'created'): ?>
      <div class="admin-notice is-success" role="status">Purchase order created successfully.</div>
      <?php elseif ($notice === 'updated'): ?>
      <div class="admin-notice is-success" role="status">Purchase order updated successfully.</div>
      <?php elseif ($notice === 'deleted'): ?>
      <div class="admin-notice is-success" role="status">Purchase order deleted successfully.</div>
      <?php elseif ($notice === 'submitted'): ?>
      <div class="admin-notice is-success" role="status">Purchase order submitted for approval.</div>
      <?php endif; ?>

      <?php if ($canApprove && $pendingApprovalCount > 0): ?>
      <div class="status-banner status-banner-approval">
        <div>
          <strong><?= $pendingApprovalCount === 1 ? '1 purchase order is' : $pendingApprovalCount . ' purchase orders are' ?> waiting for approval</strong>
          <p>Review submitted POs and take approval action from the approval queue.</p>
        </div>
        <a class="btn-primary" href="/po-management/approvals.php">Open Approval Queue</a>
      </div>
      <?php endif; ?>

      <form class="po-filter" method="get" action="/po-management/">
        <label for="status">Filter by status</label>
        <select class="form-input" id="status" name="status" onchange="this.form.submit()">
          <option value="">All statuses</option>
          <?php foreach (PO_STATUSES as $status): ?>
          <option value="<?= htmlspecialchars($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status) ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>PO Number</th>
              <th>Supplier</th>
              <th>Status</th>
              <th>Order Date</th>
              <th>Expected Delivery</th>
              <th>Total</th>
              <th>Created By</th>
              <th>View | Edit</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($orders === []): ?>
            <tr>
              <td colspan="8" class="empty-cell">No purchase orders found.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td><a class="btn-text" href="/po-management/view.php?id=<?= (int) $order['POID'] ?>"><?= htmlspecialchars($order['PONumber']) ?></a></td>
              <td><?= htmlspecialchars($order['SupplierName']) ?></td>
              <td><span class="status-badge <?= po_status_class($order['POStatus']) ?>"><?= htmlspecialchars($order['POStatus']) ?></span></td>
              <td><?= htmlspecialchars(po_format_date($order['OrderDate'])) ?></td>
              <td><?= htmlspecialchars(po_format_date($order['ExpectedDeliveryDate'])) ?></td>
              <td><?= htmlspecialchars(po_format_money($order['Subtotal'])) ?></td>
              <td><?= htmlspecialchars($order['CreatedByName']) ?></td>
              <?php
              $poActions = [
                  ['href' => '/po-management/view.php?id=' . (int) $order['POID'], 'label' => 'View'],
              ];
              if ($canApprove && $order['POStatus'] === PO_STATUS_SUBMITTED) {
                  $poActions[] = ['href' => '/po-management/approve.php?id=' . (int) $order['POID'], 'label' => 'Review'];
              }
              if (po_can_edit_order($order)) {
                  $poActions[] = ['href' => '/po-management/edit.php?id=' . (int) $order['POID'], 'label' => 'Edit'];
              }
              if ($canDelete && $order['POStatus'] === 'Created') {
                  $poActions[] = [
                      'html' => table_action_delete_form(
                          '/po-management/delete.php',
                          ['po_id' => (int) $order['POID']],
                          'Delete this purchase order?'
                      ),
                  ];
              }
              table_actions_cell($poActions);
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
