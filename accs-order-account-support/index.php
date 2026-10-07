<?php
require dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/hub-cards.php';
require_once dirname(__DIR__) . '/includes/list-page-header.php';
require_once dirname(__DIR__) . '/includes/accs-order-account-support.php';

auth_require_module_read('accs-order-account-support');

$activeSlug = 'accs-order-account-support';
$processes = accs_order_account_support_processes();

$pageTitle = 'ACCS Order and Account Support | NutraAxis Operations';
$pageDescription = 'Support processes for ACCS orders, clinic accounts, and related ecommerce follow-ups.';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => 'IT & Ecommerce',
          'title'      => 'ACCS Order and Account Support',
          'lead'       => 'Guided processes for ACCS order and clinic account support. Open a process below when available.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      ?>

<?php if ($processes === []): ?>
      <div class="status-banner">
        <div>
          <strong>Support processes coming next</strong>
          <p>This hub will list ACCS order and account support workflows as they are defined. Check back after the first processes are published.</p>
        </div>
      </div>
<?php else: ?>
      <?php hub_render_card_grid($processes, 'capability-card capability-card-link', 'capability-grid capability-grid--six'); ?>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
