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
      <?php
      render_list_page_header([
          'back_href'  => '/po-receiving/',
          'back_label' => 'Back to PO Receiving',
          'category'   => 'Supply Chain',
          'title'      => 'New PO Receipt',
          'lead'       => 'Create an advanced shipping notice and receiving record for a purchase order.',
      ]);
      ?>

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
