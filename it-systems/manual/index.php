<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/manuals/it-systems-manual.php';

auth_require_module_read('it-systems-manual');

$pageTitle = 'IT & Ecommerce User Manual | NutraAxis Operations';
$pageDescription = 'How to use IT & Ecommerce system links: Production vs UAT consoles.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner mkt-manual">
      <?php portal_manual_render(it_systems_manual_definition()); ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
