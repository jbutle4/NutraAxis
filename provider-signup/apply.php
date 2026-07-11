<?php
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/marketing-site.php';
require dirname(__DIR__) . '/includes/provider-signup-landing.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

$token = trim((string) ($_GET['token'] ?? $_POST['access_token'] ?? ''));
$application = provider_signup_get_by_token($token);

if ($application === null) {
    http_response_code(404);
    $pageTitle = 'Application Not Found | NutraAxis';
    require dirname(__DIR__) . '/includes/marketing-head.php';
    require dirname(__DIR__) . '/includes/marketing-header.php';
    echo '<main class="marketing-main"><section class="marketing-signup-stub"><div class="marketing-signup-stub__inner">';
    echo '<h2 class="marketing-signup-stub__title">Application link not found</h2>';
    echo '<p class="marketing-signup-stub__lead">Start a new application from the <a href="/provider-signup/">provider signup page</a>.</p>';
    echo '</div></section></main>';
    require dirname(__DIR__) . '/includes/marketing-footer.php';
    exit;
}

$form = provider_signup_form_from_row($application);
$error = null;
$notice = null;
$warn = null;

if (($_GET['notice'] ?? '') === 'started') {
    $notice = 'Your application has been started. We emailed you a link to return and finish later.';
} elseif (($_GET['notice'] ?? '') === 'draft_saved') {
    $notice = 'Draft saved successfully.';
} elseif (($_GET['notice'] ?? '') === 'submitted') {
    $notice = 'Application submitted for operations review.';
} elseif (($_GET['notice'] ?? '') === 'certificate_uploaded') {
    $notice = 'Reseller certificate uploaded successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'save_draft');

    if ($action === 'upload_certificate') {
        $upload = provider_signup_save_attachment($token, $_FILES['reseller_certificate'] ?? []);
        if ($upload['ok']) {
            header('Location: /provider-signup/apply.php?token=' . rawurlencode($token) . '&notice=certificate_uploaded', true, 302);
            exit;
        }
        $error = $upload['error'];
    } else {
        $form = provider_signup_form_from_post($_POST);

        if ($action === 'submit_application') {
            $result = provider_signup_submit($token, $form);
            if ($result['ok']) {
                $query = 'notice=submitted';
                if (!empty($result['warn'])) {
                    $query .= '&warn=' . rawurlencode((string) $result['warn']);
                }
                header('Location: /provider-signup/apply.php?token=' . rawurlencode($token) . '&' . $query, true, 302);
                exit;
            }
            $error = $result['error'];
        } else {
            $result = provider_signup_save_draft($token, $form);
            if ($result['ok']) {
                header('Location: /provider-signup/apply.php?token=' . rawurlencode($token) . '&notice=draft_saved', true, 302);
                exit;
            }
            $error = $result['error'];
        }
    }

    $application = provider_signup_get_by_token($token) ?? $application;
}

if (!empty($_GET['warn'])) {
    $warn = (string) $_GET['warn'];
}

$pageTitle = 'Provider Application | NutraAxis';
$pageDescription = 'Complete your NutraAxis provider signup application.';
$bodyClass = 'marketing-site appear';

require dirname(__DIR__) . '/includes/marketing-head.php';
echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
    . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
echo '<link rel="stylesheet" href="/assets/css/provider-signup.css?v='
    . htmlspecialchars(marketing_site_css_version()) . '" />' . "\n";
require dirname(__DIR__) . '/includes/marketing-header.php';
?>
  <main>
    <div class="na-providers">
      <section class="apply-section apply-section--form">
        <div class="container">
    <?php
    $editable = provider_signup_provider_can_edit($application);
    $attachments = provider_signup_list_attachments((int) $application['ApplicationID']);
    $checklist = provider_signup_submit_checklist($form, (int) $application['ApplicationID']);
    require dirname(__DIR__) . '/includes/provider-signup-form.php';
    ?>
        </div>
      </section>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/marketing-footer.php';
