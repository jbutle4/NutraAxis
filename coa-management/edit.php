<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/coa.php';

coa_require_update();

$activeSlug = 'coa-management';
$id = (int) ($_GET['id'] ?? 0);
$existing = coa_get($id);

if ($existing === null) {
    http_response_code(404);
    exit('COA document not found.');
}

$error = null;
$form = coa_row_to_form($existing);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = coa_from_input($_POST);
    $result = coa_save($_POST, $_FILES['coa_pdf'] ?? null);

    if ($result['ok']) {
        header('Location: /coa-management/view.php?id=' . $result['id'] . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit COA | COA Management';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/coa-management/view.php?id=' . $id,
          'back_label' => 'Back to COA Detail',
          'category'   => 'Quality',
          'title'      => 'Edit COA',
          'lead'       => htmlspecialchars((string) $existing['ProductName']) . ' · Lot ' . htmlspecialchars((string) $existing['LotNumber']),
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/coa-management/edit.php?id=' . $id;
        require dirname(__DIR__) . '/includes/coa-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
