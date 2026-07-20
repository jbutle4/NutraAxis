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
    provider_signup_render_policy_page_open();
    echo '<div class="signup-not-found">';
    echo '<div class="section-label">Provider Application</div>';
    echo '<h2 class="section-heading">Application link not found</h2>';
    echo '<p class="section-sub">Start a new application from the <a href="/provider-signup/">For Providers</a> page.</p>';
    provider_signup_render_support_link('provider-support-link provider-support-link--not-found');
    echo '</div>';
    provider_signup_render_apply_page_close();
    echo '</main>';
    require dirname(__DIR__) . '/includes/marketing-footer.php';
    exit;
}

$error = null;
$notice = null;

if (($_GET['notice'] ?? '') === 'started') {
    $notice = 'Your application has been started. Review and acknowledge the policy below to continue.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'acknowledge_policy') {
        if (empty($_POST['policy_acknowledged'])) {
            $error = 'Please check the acknowledgement box to continue.';
        } else {
            $result = provider_signup_acknowledge_policy($token);
            if ($result['ok']) {
                header(
                    'Location: /provider-signup/apply.php?token=' . rawurlencode($token) . '&notice=policy_acknowledged',
                    true,
                    302
                );
                exit;
            }
            $error = $result['error'] ?? 'Unable to record policy acknowledgement.';
        }
    } else {
        $error = 'Unknown action.';
    }

    $application = provider_signup_get_by_token($token) ?? $application;
}

$pageTitle = 'Practitioner Reseller Policy | NutraAxis';
$pageDescription = 'Review and acknowledge the NutraAxis Practitioner Reseller and Advertising Policy.';

require dirname(__DIR__) . '/includes/marketing-head.php';
echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
    . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
require dirname(__DIR__) . '/includes/marketing-header.php';
?>
  <main>
    <?php provider_signup_render_policy_page_open(); ?>
    <?php provider_signup_render_policy_page($application, $error, $notice); ?>
    <?php provider_signup_render_apply_page_close(); ?>
  </main>
<?php
require dirname(__DIR__) . '/includes/marketing-footer.php';
