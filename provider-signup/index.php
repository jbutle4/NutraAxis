<?php
/**
 * Public marketing landing — matches nutraaxislabs.com/for-providers.
 * Internal ops review lives at /operations-dashboard/provider-enrollment/ (Operations Portal).
 */
require dirname(__DIR__) . '/includes/marketing-init.php';
require dirname(__DIR__) . '/includes/provider-signup-landing.php';

$pageTitle = 'For Providers | NutraAxis';
$pageDescription = 'Launch your co-branded NutraAxis Clinic Store with provider-set pricing, zero inventory, and fulfillment handled for you.';

require dirname(__DIR__) . '/includes/marketing-head.php';
echo '<link rel="stylesheet" href="/assets/css/provider-signup-landing.css?v='
    . htmlspecialchars(provider_signup_landing_css_version()) . '" />' . "\n";
require dirname(__DIR__) . '/includes/marketing-header.php';
?>
  <main>
    <?php provider_signup_render_landing_page(); ?>
  </main>
<?php
require dirname(__DIR__) . '/includes/marketing-footer.php';
