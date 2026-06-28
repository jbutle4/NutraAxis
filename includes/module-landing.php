<?php
/** @var array $module */
/** @var string $activeSlug */
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => $module['label'],
          'title'      => $module['headline'],
          'lead'       => $module['lead'],
          'permission' => auth_module_permission_label($module['slug']),
      ]); ?>

      <div class="capability-grid">
        <?php foreach ($module['capabilities'] as $cap): ?>
        <div class="capability-card">
          <h3><?= htmlspecialchars($cap['title']) ?></h3>
          <p><?= htmlspecialchars($cap['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="module-actions">
        <span class="btn-primary">Launch Application</span>
        <a class="btn-secondary" href="/">All Applications</a>
      </div>

      <div class="status-banner">
        <div>
          <strong>Module in development</strong>
          <p>This landing page is ready. Application functionality for <?= htmlspecialchars($module['title']) ?> will be connected in a future release.</p>
        </div>
      </div>
    </div>
  </main>
