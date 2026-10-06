<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/manuals/operations-manual.php';

auth_require_module_read('operations-manual');

$pageTitle = 'Operations User Manual | NutraAxis Operations';
$pageDescription = 'How to use the Operations home section: portal tools and team shortcuts.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner mkt-manual">
      <?php portal_manual_render(operations_manual_definition()); ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
