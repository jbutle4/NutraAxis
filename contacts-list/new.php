<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/contacts.php';

contacts_require_create();

$activeSlug = 'contacts-list';
$error = null;
$form = contacts_from_input([]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form = contacts_from_input($_POST);
    $result = contacts_save($_POST);

    if ($result['ok']) {
        header('Location: /contacts-list/view.php?id=' . $result['id'] . '&notice=created', true, 302);
        exit;
    }

    $error = $result['error'];
}

$pageTitle = 'New Contact | Contacts List';

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/contacts-list/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Contacts List
      </a>

      <div class="admin-header">
        <div>
          <div class="section-label">Operations</div>
          <h1>New Contact</h1>
          <p class="page-lead">Add a contact to the operations directory.</p>
        </div>
      </div>

      <?php if ($error !== null): ?>
      <div class="admin-notice is-error is-detail" role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        $formAction = '/contacts-list/new.php';
        $isEdit = false;
        require dirname(__DIR__) . '/includes/contact-form.php';
      ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
