<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';

po_require_read();

$poId = (int) ($_GET['id'] ?? 0);
$order = po_get_order($poId);

if ($order === null) {
    http_response_code(404);
    $pageTitle = 'PO Not Found';
    $bodyClass = 'po-print-page';
    require dirname(__DIR__) . '/includes/head.php';
    echo '<main class="po-print-shell"><p>Purchase order not found.</p><a href="/po-management/">Back to Purchase Orders</a></main></body></html>';
    exit;
}

$lines = po_get_lines($poId);
$pageTitle = $order['PONumber'] . ' | Print';
$pageDescription = 'Printable purchase order.';
$bodyClass = 'po-print-page';

require dirname(__DIR__) . '/includes/head.php';
?>
  <div class="po-print-toolbar no-print">
    <a class="btn-secondary btn-small" href="/po-management/view.php?id=<?= $poId ?>">Back to PO</a>
    <button type="button" class="btn-primary btn-small" onclick="window.print()">Print</button>
  </div>

  <main class="po-print-shell">
    <?php require dirname(__DIR__) . '/includes/po-print.php'; ?>
  </main>
</body>
</html>
