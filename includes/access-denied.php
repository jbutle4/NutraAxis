  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Home',
          'category'   => 'Access Denied',
          'title'      => 'Permission required',
          'lead'       => $accessDeniedMessage ?? 'You do not have permission to view this page.',
      ]); ?>

      <?php
      ob_start();
      ?>
      <a class="btn-secondary" href="/">Back to Home</a>
      <?php if (auth_is_logged_in()): ?>
      <a class="btn-primary" href="/my-account/">My Account</a>
      <?php else: ?>
      <a class="btn-primary" href="/login/">Log In</a>
      <?php endif; ?>
      <?php
      $pageToolbar = trim(ob_get_clean());
      render_list_page_toolbar($pageToolbar !== '' ? $pageToolbar : null);
      ?>
    </div>
  </main>
