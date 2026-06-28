<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';

links_require_create();

$activeSlug = 'links-index';
$error = null;
$form = links_from_input([
    'link_status'                => 'active',
    'link_category'              => 'Web application',
    'user_registration_required' => '0',
]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = links_from_input($_POST);
    $result = links_save($_POST);

    if ($result['ok']) {
        header('Location: /links-index/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Link | Links Index';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/links-index/',
          'back_label' => 'Back to Links Index',
          'category'   => 'Resources',
          'title'      => 'New Link',
          'lead'       => 'Add a shortcut to the Links Index.',
      ]);
      ?>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $formAction = '/links-index/new.php';
        $isEdit = false;
        require dirname(__DIR__) . '/includes/link-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
