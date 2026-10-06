<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/manuals/supply-chain-manual.php';

auth_require_module_read('supply-chain-manual');

$pageTitle = 'Supply Chain User Manual | NutraAxis Operations';
$pageDescription = 'How to use Supply Chain: roles, workflows, and a guide to every module.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner mkt-manual">
      <?php portal_manual_render(supply_chain_manual_definition()); ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
