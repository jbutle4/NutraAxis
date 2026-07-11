<?php

/**
 * @param array{
 *   title: string,
 *   lead: string,
 *   category: string,
 *   hostname?: string
 * } $page
 */
function subdomain_site_render_landing(array $page): void
{
    $hostname = trim((string) ($page['hostname'] ?? ''));
    ?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => $page['category'],
          'title'      => $page['title'],
          'lead'       => $page['lead'],
      ]); ?>

      <?php if ($hostname !== ''): ?>
      <div class="admin-notice" role="status">
        This section is served at <strong><?= htmlspecialchars($hostname) ?></strong> and maps to this portal path.
      </div>
      <?php endif; ?>

      <div class="status-banner">
        <div>
          <strong>Section in development</strong>
          <p>Content and workflows for <?= htmlspecialchars($page['title']) ?> will be connected here in a future release.</p>
        </div>
      </div>
    </div>
  </main>
    <?php
}
