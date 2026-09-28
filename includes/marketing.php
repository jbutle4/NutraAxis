<?php

require_once __DIR__ . '/app.php';

/**
 * Placeholder leaf page for Marketing hub modules not yet built.
 *
 * @param array{slug: string, title: string, lead: string, phase: string} $config
 */
function marketing_render_placeholder(array $config): void
{
    $slug = (string) ($config['slug'] ?? '');
    $title = (string) ($config['title'] ?? 'Marketing');
    $lead = (string) ($config['lead'] ?? '');
    $phase = (string) ($config['phase'] ?? 'Coming soon');

    auth_require_module_read($slug);

    $activeSlug = $slug;
    $pageTitle = $title . ' | NutraAxis Operations';
    $pageDescription = $lead !== '' ? $lead : $title;

    $back = app_module_hub_back_link($slug);

    require __DIR__ . '/head.php';
    require __DIR__ . '/header.php';
    ?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => $title,
          'lead'       => $lead,
          'permission' => auth_module_permission_label($slug),
      ]); ?>

      <div class="status-banner">
        <div>
          <strong>Placeholder — <?= htmlspecialchars($phase) ?></strong>
          <p>This module is registered in the Marketing &amp; Research Hub for roadmap visibility. Functionality will land in the SEO Operations and Research Application build phases (see docs/SEO_OPS_BUILD_SPEC.md).</p>
        </div>
      </div>
    </div>
  </main>
    <?php
    require __DIR__ . '/footer.php';
}
