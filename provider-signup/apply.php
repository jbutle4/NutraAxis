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
    echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
        . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
    require dirname(__DIR__) . '/includes/marketing-header.php';
    echo '<main>';
    provider_signup_render_apply_page_open();
    echo '<div class="signup-not-found">';
    echo '<div class="section-label">Provider Application</div>';
    echo '<h2 class="section-heading">Application link not found</h2>';
    echo '<p class="section-sub">Start a new application from the <a href="/provider-signup/">For Providers</a> page.</p>';
    echo '</div>';
    provider_signup_render_apply_page_close();
    echo '</main>';
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
    $notice = 'Your Clinic Store activation request has been received.';
} elseif (($_GET['notice'] ?? '') === 'activated') {
    $notice = 'Your Clinic Store has been activated.';
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
            $submitForm = provider_signup_provider_can_submit($application)
                ? provider_signup_form_from_row($application)
                : provider_signup_form_from_post($_POST);
            $result = provider_signup_submit($token, $submitForm);
            if ($result['ok']) {
                $query = provider_signup_provider_can_submit($application) ? 'notice=activated' : 'notice=submitted';
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

require dirname(__DIR__) . '/includes/marketing-head.php';
echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
    . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
require dirname(__DIR__) . '/includes/marketing-header.php';
?>
  <main>
    <?php provider_signup_render_apply_page_open(); ?>
    <?php
    $editable = provider_signup_provider_can_edit($application);
    $canSubmit = provider_signup_provider_can_submit($application);
    $attachments = provider_signup_list_attachments((int) $application['ApplicationID']);
    $checklist = provider_signup_submit_checklist($form, (int) $application['ApplicationID']);
    require dirname(__DIR__) . '/includes/provider-signup-form.php';
    provider_signup_render_apply_page_close();
    ?>
  </main>
<?php
require dirname(__DIR__) . '/includes/marketing-footer.php';
