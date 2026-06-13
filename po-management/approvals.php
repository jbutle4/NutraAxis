<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-approval.php';

po_require_approval_read();

$activeSlug = 'po-management';
$activePoSection = 'approvals';
$listFilters = table_sort_state(PO_APPROVAL_LIST_SORT_COLUMNS, 'order_date', 'asc', $_GET);
$orders = po_list_pending_approvals($listFilters);
$notice = $_GET['notice'] ?? null;

$pageTitle = 'PO Approvals | PO Management';
$pageDescription = 'Review purchase orders submitted for approval.';

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

      <div class="admin-header">
        <div>
          <div class="section-label">Procurement</div>
          <h1>Approval Queue</h1>
          <p class="page-lead">Purchase orders waiting for your review. Open a PO to see details, attachments, and approval actions.</p>
          <p class="permission-note">Signed in as <?= htmlspecialchars(auth_user()['UserName'] ?? '') ?> · Approval access: <?= htmlspecialchars(permission_label(po_approval_permission_value())) ?></p>
        </div>
        <?php if (permission_can_read(po_permission_value())): ?>
        <a class="btn-secondary" href="/po-management/?skip_approver_redirect=1">View All POs</a>
        <?php endif; ?>
      </div>

      <?php if ($notice === 'actioned'): ?>
      <div class="admin-notice is-success" role="status">Approval action recorded successfully.</div>
      <?php endif; ?>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <?php table_sort_render_head_row(
                PO_APPROVAL_LIST_SORT_COLUMNS,
                '/po-management/approvals.php',
                $listFilters,
                [],
                PO_APPROVAL_LIST_SORT_NUMERIC,
                'order_date',
                'asc',
                '',
                'View | Review'
            ); ?>
          </thead>
          <tbody>
            <?php if ($orders === []): ?>
            <tr>
              <td colspan="6" class="empty-cell">No purchase orders are waiting for approval.</td>
            </tr>
            <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr>
              <td><?= htmlspecialchars($order['PONumber']) ?></td>
              <td><?= htmlspecialchars($order['SupplierName']) ?></td>
              <td><?= htmlspecialchars(po_format_date($order['OrderDate'])) ?></td>
              <td><?= htmlspecialchars(po_format_money($order['TotalDue'] ?? $order['Subtotal'])) ?></td>
              <td><?= htmlspecialchars($order['CreatedByName']) ?></td>
              <?php table_actions_cell([
                  ['href' => '/po-management/view.php?id=' . (int) $order['POID'], 'label' => 'View'],
                  ['href' => '/po-management/approve.php?id=' . (int) $order['POID'], 'label' => 'Review'],
              ]); ?>
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
