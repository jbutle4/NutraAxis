<?php
/** @var array $module */
/** @var string $activeSlug */
?>
  <main class="page-main">
    <div class="container page-inner">
      <a class="breadcrumb" href="/">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 18l-6-6 6-6"/>
        </svg>
        Back to Operations Home
      </a>

      <div class="page-hero">
        <div class="module-icon"><?= icon_svg($module['icon'], 28) ?></div>
        <div class="section-label"><?= htmlspecialchars($module['label']) ?></div>
        <h1><?= htmlspecialchars($module['headline']) ?></h1>
        <p class="page-lead"><?= htmlspecialchars($module['lead']) ?></p>
        <p class="permission-note">Your access: <?= htmlspecialchars(auth_module_permission_label($module['slug'])) ?></p>
      </div>

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
