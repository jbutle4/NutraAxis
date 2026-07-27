<?php
/**
 * Shown after the start form — provider must confirm via emailed link.
 */
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup-landing.php';
require dirname(__DIR__) . '/includes/provider-signup.php';

$email = provider_signup_normalize_email((string) ($_GET['email'] ?? ''));

$pageTitle = 'Check Your Email | NutraAxis';
$pageDescription = 'Confirm your email to continue your NutraAxis provider application.';

require dirname(__DIR__) . '/includes/marketing-head.php';
echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
    . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
require dirname(__DIR__) . '/includes/marketing-header.php';
?>
  <main>
    <?php provider_signup_render_check_email_page($email); ?>
  </main>
<?php
require dirname(__DIR__) . '/includes/marketing-footer.php';
