<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-receiving.php';

por_require_create();

$activeSlug = 'po-receiving';
$error = null;
$preselectedPo = (int) ($_GET['po_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = por_save($_POST);
    if ($result['ok']) {
        header('Location: /po-receiving/view.php?id=' . (int) $result['id'] . '&notice=created', true, 302);
        exit;
    }
    $error = $result['error'];
    $form = por_form_from_post($_POST);
} elseif ($preselectedPo > 0) {
    $form = por_default_header_from_po($preselectedPo) ?? por_from_input(['po_id' => (string) $preselectedPo]);
} else {
    $form = por_from_input([]);
}

$pageTitle = 'New Receipt | PO Receiving';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-receiving/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to PO Receiving
      </a>

      <div class="page-hero">
        <div class="section-label">Supply Chain</div>
        <h1>New PO Receipt</h1>
        <p class="page-lead">Create an advanced shipping notice and receiving record for a purchase order.</p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/po-receiving/new.php';
        require dirname(__DIR__) . '/includes/po-receiving-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
