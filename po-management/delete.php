<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';

po_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-management/', true, 302);
    exit;
}

$poId = (int) ($_POST['po_id'] ?? 0);
$result = po_delete_order($poId);

if ($result['ok']) {
    header('Location: /po-management/?notice=deleted', true, 302);
    exit;
}

$activeSlug = 'po-management';
$pageTitle = 'Delete PO | PO Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <div class="admin-notice is-error" role="alert"><?= htmlspecialchars($result['error']) ?></div>
      <div class="module-actions">
        <a class="btn-secondary" href="/po-management/">Back to Purchase Orders</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
