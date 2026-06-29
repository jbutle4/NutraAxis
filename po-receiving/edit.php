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
      <?php
      render_list_page_header([
          'back_href'  => '/po-receiving/view.php?id=' . $porId,
          'back_label' => 'Back to Receipt',
          'category'   => 'Inbound',
          'title'      => 'Edit PO Receipt',
          'lead'       => $receipt['PONumber'] . ' · ' . $receipt['SupplierName'],
      ]);
      ?>

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
