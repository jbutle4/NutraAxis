<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require_once dirname(__DIR__, 2) . '/includes/list-page-header.php';
require_once dirname(__DIR__, 2) . '/includes/manuals/administration-manual.php';

auth_require_module_read('administration-manual');

$pageTitle = 'Administration User Manual | NutraAxis Operations';
$pageDescription = 'How to use Administration: accounting, legal, support, and related tools.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner mkt-manual">
      <?php portal_manual_render(administration_manual_definition()); ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
