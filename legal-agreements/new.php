<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal.php';

legal_require_create();

$activeSlug = 'legal-agreements';
$error = null;
$form = legal_contract_from_input([
    'contract_status' => 'Draft',
    'contract_type'   => 'Other',
    'auto_renewal'    => '0',
]);
$userOptions = legal_user_options();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = legal_contract_from_input($_POST);
    $result = legal_save_contract($_POST);

    if ($result['ok']) {
        header('Location: /legal-agreements/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Contract | Legal Agreements';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/legal-agreements/',
          'back_label' => 'Back to Contract Register',
          'category'   => 'Legal',
          'title'      => 'New Contract',
          'lead'       => 'Add a legal agreement or contract to the register.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $isEdit = false;
        $formAction = '/legal-agreements/new.php';
        require dirname(__DIR__) . '/includes/legal-contract-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
