<?php
require __DIR__ . '/includes/init.php';

auth_require_login();

$activeSlug = 'planner';
$plannerUrl = 'https://planner.cloud.microsoft/webui/premiumplan/c2a63499-8702-4dee-8431-1efef82b8879/org/0b986e71-e763-f011-8ee3-7ced8d213739?tid=60392fb7-51ea-497a-8a08-0ec0265a97c7';

$pageTitle = 'MS Planner | NutraAxis Operations';
$pageDescription = 'Open the NutraAxis Microsoft Planner premium plan.';

require __DIR__ . '/includes/head.php';
require __DIR__ . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'Operations',
          'title'      => 'MS Planner',
          'lead'       => 'NutraAxis premium plan in Microsoft Planner.',
      ]); ?>

      <div class="status-banner planner-launch-banner">
        <div>
          <strong>Microsoft Planner opens in a new tab</strong>
          <p>Microsoft 365 blocks Planner from loading inside other websites, so this page launches your plan in a separate browser tab using your existing Microsoft sign-in.</p>
        </div>
        <a class="btn-primary" href="<?= htmlspecialchars($plannerUrl) ?>" target="_blank" rel="noopener noreferrer">Open MS Planner</a>
      </div>

      <p class="planner-launch-note">If Planner does not open automatically, use the button above or update the <strong>MS Planner</strong> link in Links Index to point here.</p>
    </div>
  </main>
  <script>
    (function () {
      var url = <?= json_encode($plannerUrl, JSON_THROW_ON_ERROR) ?>;
      var opened = false;
      try {
        opened = window.open(url, '_blank', 'noopener,noreferrer') !== null;
      } catch (e) {
        opened = false;
      }
      if (!opened) {
        window.location.replace(url);
      }
    })();
  </script>
<?php
require __DIR__ . '/includes/footer.php';
