<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';

auth_require_module_read('accs-order-account-support');

$pageTitle = 'Recreate order as Stage cart | NutraAxis Operations';
$pageDescription = 'Phase 2 — open Stage cart from a Production order.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/accs-order-account-support/',
          'back_label' => 'Back to ACCS Order and Account Support',
          'category'   => 'IT & Ecommerce',
          'title'      => 'Recreate order as Stage cart',
          'lead'       => 'Phase 2 — build an open Stage cart from a Production order after the clinic is cloned.',
          'permission' => auth_module_permission_label('accs-order-account-support'),
      ]);
      ?>

      <div class="status-banner">
        <div>
          <strong>Coming next (Phase 2)</strong>
          <p>Clone a Production clinic to Stage first. Order → open cart recreation will land in the next phase so you can finish checkout yourself on Stage to observe the issue.</p>
        </div>
        <a class="btn-primary" href="/accs-order-account-support/clone/">Clone account to Stage</a>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
