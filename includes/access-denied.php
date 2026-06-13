  <main class="page-main">
    <div class="container page-inner">
      <div class="page-hero">
        <div class="section-label">Access Denied</div>
        <h1>Permission required</h1>
        <p class="page-lead"><?= htmlspecialchars($accessDeniedMessage ?? 'You do not have permission to view this page.') ?></p>
        <div class="module-actions">
          <a class="btn-secondary" href="/">Back to Home</a>
          <?php if (auth_is_logged_in()): ?>
          <a class="btn-primary" href="/my-account/">My Account</a>
          <?php else: ?>
          <a class="btn-primary" href="/login/">Log In</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
