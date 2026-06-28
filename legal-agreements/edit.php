<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal.php';
require dirname(__DIR__) . '/includes/legal-attachments.php';

legal_require_update();

$contractId = (int) ($_GET['id'] ?? 0);
$contract = $contractId > 0 ? legal_get_contract($contractId) : null;

if ($contract === null) {
    header('Location: /legal-agreements/', true, 302);
    exit;
}

$activeSlug = 'legal-agreements';
$error = null;
$form = legal_contract_to_form($contract);
$userOptions = legal_user_options();
$attachments = legal_list_attachments($contractId);
$notice = $_GET['notice'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = array_merge($form, legal_contract_from_input($_POST));
    $form['contract_id'] = $contractId;
    $result = legal_save_contract($_POST, $contractId);

    if ($result['ok']) {
        header('Location: /legal-agreements/view.php?id=' . $contractId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'Edit ' . $contract['ContractNumber'] . ' | Legal Agreements';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/legal-agreements/view.php?id=' . $contractId,
          'back_label' => 'Back to ' . (string) $contract['ContractNumber'],
          'category'   => 'Legal',
          'title'      => 'Edit ' . (string) $contract['ContractNumber'],
          'lead'       => 'Update contract register details for ' . (string) $contract['ContractName'] . '.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($notice === 'attachment'): ?>
      <div class="admin-notice is-success" role="status">Attachment uploaded successfully.</div>
      <?php endif; ?>

      <?php
        $isEdit = true;
        $formAction = '/legal-agreements/edit.php?id=' . $contractId;
        require dirname(__DIR__) . '/includes/legal-contract-form.php';
      ?>

      <?php
        $showUploadForm = legal_can_update();
        require dirname(__DIR__) . '/includes/legal-attachments-section.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
