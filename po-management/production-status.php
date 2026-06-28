<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/po.php';
require dirname(__DIR__) . '/includes/po-production.php';

po_require_read();

$poId = (int) ($_GET['id'] ?? $_POST['po_id'] ?? 0);
if ($poId <= 0) {
    header('Location: /po-management/', true, 302);
    exit;
}

$order = po_get_order($poId);
if ($order === null) {
    http_response_code(404);
    $pageTitle = 'PO Not Found';
    require dirname(__DIR__) . '/includes/head.php';
    require dirname(__DIR__) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Purchase order not found</h1><div class="module-actions"><a class="btn-secondary" href="/po-management/">Back to Purchase Orders</a></div></div></div></main>';
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

if (!po_can_edit_production_status($order)) {
    auth_render_access_denied('Production status cannot be updated for this purchase order.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    po_require_update();

    $result = po_save_production_statuses($poId, $_POST);

    if ($result['ok']) {
        header('Location: /po-management/view.php?id=' . $poId . '&notice=production_updated', true, 302);
        exit;
    }

    $error = $result['error'];
} else {
    $error = null;
}

$activeSlug = 'po-management';
$activePoSection = 'list';
$lines = po_get_lines($poId);
$productionByLine = po_get_production_status_map($poId);

$pageTitle = $order['PONumber'] . ' | Production Status';
$pageDescription = 'Update production status for purchase order lines.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/po-management/view.php?id=' . $poId,
          'back_label' => 'Back to ' . $order['PONumber'],
          'category'   => 'Procurement',
          'title'      => 'Production status',
          'lead'       => '<span class="status-badge ' . po_status_class($order['POStatus']) . '">' . htmlspecialchars($order['POStatus']) . '</span> · ' . htmlspecialchars($order['PONumber']) . ' · ' . htmlspecialchars($order['SupplierName']),
          'lead_html'  => true,
      ]);
      ?>

      <?php require dirname(__DIR__) . '/includes/po-nav.php'; ?>

      <?php require dirname(__DIR__) . '/includes/po-production-form.php'; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
