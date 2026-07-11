<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

provider_signup_require_read();

$applicationId = (int) ($_GET['id'] ?? 0);
$application = provider_signup_get($applicationId);

if ($application === null) {
    http_response_code(404);
    $pageTitle = 'Application Not Found | NutraAxis Operations';
    require dirname(__DIR__, 2) . '/includes/head.php';
    require dirname(__DIR__, 2) . '/includes/header.php';
    echo '<main class="page-main"><div class="container page-inner"><div class="page-hero"><h1>Application not found</h1><div class="module-actions"><a class="btn-secondary" href="/operations-dashboard/signup-review/">Back to queue</a></div></div></div></main>';
    require dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

$activeSlug = 'signup-review';
$canUpdate = provider_signup_can_update();
$canEdit = provider_signup_ops_can_edit($application);
$error = null;
$form = provider_signup_form_from_row($application);
$checklist = provider_signup_submit_checklist($form, $applicationId);

if (!$canUpdate || !$canEdit) {
    auth_render_access_denied('You do not have permission to edit this provider application.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'save') {
    $form = provider_signup_form_from_post($_POST);
    $form['provider_email'] = (string) ($application['ProviderEmail'] ?? '');
    $result = provider_signup_ops_update($applicationId, $form, (string) ($_POST['edit_note'] ?? ''));

    if ($result['ok']) {
        header('Location: /operations-dashboard/signup-review/view.php?id=' . $applicationId . '&notice=updated', true, 302);
        exit;
    }

    $error = $result['error'] ?? 'Unable to save application.';
    $application = provider_signup_get($applicationId) ?? $application;
    $checklist = provider_signup_submit_checklist($form, $applicationId);
}

$pageTitle = 'Edit Application #' . $applicationId . ' | NutraAxis Operations';
$pageDescription = 'Edit provider signup application data.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/operations-dashboard/signup-review/view.php?id=' . $applicationId,
          'back_label' => 'Back to Application #' . $applicationId,
          'category'   => 'Operations',
          'title'      => 'Edit Application #' . $applicationId,
          'lead'       => trim((string) ($application['CompanyName'] ?? 'Provider application')) . ' · ' . (string) ($application['Status'] ?? ''),
      ]);
      ?>

      <?php if (!$checklist['complete']): ?>
      <div class="admin-notice" role="status">
        <strong>Approval checklist</strong> — still needed: <?= htmlspecialchars(implode(', ', $checklist['missing'])) ?>.
      </div>
      <?php endif; ?>

      <?php
      $opsFormAction = '/operations-dashboard/signup-review/application-form.php?id=' . $applicationId;
      $opsFormCancelHref = '/operations-dashboard/signup-review/view.php?id=' . $applicationId;
      require dirname(__DIR__, 2) . '/includes/provider-signup-ops-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
