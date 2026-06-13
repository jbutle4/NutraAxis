<?php
/** @var array $supplierPurchaseOrders */
/** @var bool $canViewPos */
require_once __DIR__ . '/po.php';

$canViewPos = $canViewPos ?? po_can_access_po_pages();
?>
      <section class="detail-card supplier-po-report">
        <h2>Related purchase orders</h2>
        <p class="page-lead"><?= count($supplierPurchaseOrders) === 1 ? '1 purchase order' : count($supplierPurchaseOrders) . ' purchase orders' ?> linked to this supplier.</p>

        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>PO number</th>
                <th>Status</th>
                <th>Order date</th>
                <th>Expected delivery</th>
                <th>Subtotal</th>
                <th>Created by</th>
                <?php if ($canViewPos): ?>
                <th>View</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if ($supplierPurchaseOrders === []): ?>
              <tr><td colspan="<?= $canViewPos ? 7 : 6 ?>">No purchase orders for this supplier.</td></tr>
              <?php else: ?>
              <?php foreach ($supplierPurchaseOrders as $order): ?>
              <tr>
                <td><?= htmlspecialchars($order['PONumber']) ?></td>
                <td><span class="status-badge <?= po_status_class($order['POStatus']) ?>"><?= htmlspecialchars($order['POStatus']) ?></span></td>
                <td><?= htmlspecialchars(po_format_date($order['OrderDate'])) ?></td>
                <td><?= htmlspecialchars(po_format_date($order['ExpectedDeliveryDate'])) ?></td>
                <td><?= htmlspecialchars(po_format_money($order['Subtotal'])) ?></td>
                <td><?= htmlspecialchars($order['CreatedByName']) ?></td>
                <?php if ($canViewPos): ?>
                <?php table_actions_cell([
                    ['href' => '/po-management/view.php?id=' . (int) $order['POID'], 'label' => 'View'],
                ]); ?>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
