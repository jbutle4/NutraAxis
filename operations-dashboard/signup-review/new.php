<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/provider-signup.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

provider_signup_require_update();

$activeSlug = 'signup-review';
$error = null;
$form = provider_signup_default_form();
$form['accs_environment'] = 'production';
$markApproved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'create') {
    $form = provider_signup_form_from_post($_POST);
    $markApproved = !empty($_POST['mark_approved']);
    $result = provider_signup_ops_create_clinic($form, $markApproved);

    if ($result['ok'] && !empty($result['application_id'])) {
        $notice = $markApproved ? 'created_approved' : 'created';
        header(
            'Location: /operations-dashboard/signup-review/view.php?id='
            . (int) $result['application_id']
            . '&notice=' . rawurlencode($notice),
            true,
            302
        );
        exit;
    }

    $error = htmlspecialchars((string) ($result['error'] ?? 'Unable to create clinic application.'));
    if (!empty($result['application_id'])) {
        $error .= ' <a href="/operations-dashboard/signup-review/view.php?id='
            . (int) $result['application_id'] . '">Open application #'
            . (int) $result['application_id'] . '</a>';
    }
}

$pageTitle = 'Create New Clinic | NutraAxis Operations';
$pageDescription = 'Create a provider clinic application from Operations without the public signup flow.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/operations-dashboard/signup-review/',
          'back_label' => 'Back to Provider Signup Queue',
          'category'   => 'Operations',
          'title'      => 'Create New Clinic',
          'lead'       => 'Enter company, admin, and compliance details to create a clinic application from Operations. Use Create Clinic Store on the application page when ready to provision ACCS.',
          'permission' => permission_label(provider_signup_permission_value()),
      ]);
      ?>

      <div class="admin-notice" role="status">
        This creates an Operations-managed application record (same fields as a provider signup).
        It does <strong>not</strong> email the provider a continue link. After create, open the application to approve (if needed) and run <strong>Create Clinic Store</strong>.
      </div>

      <?php
      $application = [
          'ApplicationID'             => 0,
          'ProviderEmail'             => $form['provider_email'],
          'TaxIdEncrypted'            => '',
          'AchAccountNumberEncrypted' => '',
      ];
      $opsFormIsCreate = true;
      $opsFormMarkApproved = $markApproved;
      $opsFormAction = '/operations-dashboard/signup-review/new.php';
      $opsFormCancelHref = '/operations-dashboard/signup-review/';
      $opsFormErrorHtml = $error;
      require dirname(__DIR__, 2) . '/includes/provider-signup-ops-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
