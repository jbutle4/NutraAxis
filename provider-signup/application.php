<?php
/**
 * Public application start — email capture before tokenized apply form.
 */
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup-landing.php';

$startError = trim((string) ($_GET['error'] ?? ''));

$pageTitle = 'Apply for Provider Access | NutraAxis';
$pageDescription = 'Start your NutraAxis provider application and launch your co-branded Clinic Store.';

require dirname(__DIR__) . '/includes/marketing-head.php';
echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
    . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
require dirname(__DIR__) . '/includes/marketing-header.php';
?>
  <main>
    <?php provider_signup_render_application_start_page($startError); ?>
  </main>
<?php
require dirname(__DIR__) . '/includes/marketing-footer.php';
