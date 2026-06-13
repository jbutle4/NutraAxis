<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-receiving.php';

por_require_update();

$porId = (int) ($_GET['id'] ?? 0);
$receipt = $porId > 0 ? por_get($porId) : null;

if ($receipt === null) {
    header('Location: /po-receiving/', true, 302);
    exit;
}

if (!por_can_edit($receipt)) {
    header('Location: /po-receiving/view.php?id=' . $porId . '&error=' . rawurlencode('This receipt has been transmitted and can no longer be edited.'), true, 302);
    exit;
}

$activeSlug = 'po-receiving';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = por_save($_POST, $porId);
    if ($result['ok']) {
        header('Location: /po-receiving/view.php?id=' . $porId . '&notice=updated', true, 302);
        exit;
    }
    $error = $result['error'];
    $form = por_form_from_post($_POST, $porId);
    $form['por_id'] = $porId;
} else {
    $form = por_to_form($receipt, por_get_lines($porId));
}

$pageTitle = 'Edit Receipt | PO Receiving';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/po-receiving/view.php?id=<?= $porId ?>">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Receipt
      </a>

      <div class="page-hero">
        <div class="section-label">Supply Chain</div>
        <h1>Edit PO Receipt</h1>
        <p class="page-lead"><?= htmlspecialchars($receipt['PONumber']) ?> · <?= htmlspecialchars($receipt['SupplierName']) ?></p>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/po-receiving/edit.php?id=' . $porId;
        require dirname(__DIR__) . '/includes/po-receiving-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
