<?php

function list_page_header_back_icon(): string
{
    return <<<'SVG'
<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
  <path d="M15 18l-6-6 6-6"/>
</svg>
SVG;
}

function render_list_page_header(array $header): void
{
    $backHref = (string) ($header['back_href'] ?? '/');
    $backLabel = (string) ($header['back_label'] ?? 'Back');
    $category = (string) ($header['category'] ?? '');
    $title = (string) ($header['title'] ?? '');
    $lead = (string) ($header['lead'] ?? '');
    $leadHtml = !empty($header['lead_html']);
    $permission = $header['permission'] ?? null;

    if ($permission !== null && $permission !== '' && !str_starts_with((string) $permission, 'Your access:')) {
        $permission = 'Your access: ' . $permission;
    }
    ?>
    <div class="page-list-header">
      <div class="page-list-header-row page-list-header-row--meta">
        <a class="page-list-back breadcrumb" href="<?= htmlspecialchars($backHref) ?>">
          <?= list_page_header_back_icon() ?>
          <?= htmlspecialchars($backLabel) ?>
        </a>
        <?php if ($category !== ''): ?>
        <span class="page-list-category section-label"><?= htmlspecialchars($category) ?></span>
        <?php endif; ?>
      </div>
      <div class="page-list-header-row page-list-header-row--title">
        <h1 class="page-list-title"><?= htmlspecialchars($title) ?></h1>
        <div class="page-list-summary">
          <?php if ($lead !== ''): ?>
          <p class="page-list-lead page-lead"><?= $leadHtml ? $lead : htmlspecialchars($lead) ?></p>
          <?php endif; ?>
          <?php if ($permission !== null && $permission !== ''): ?>
          <p class="page-list-permission permission-note"><?= htmlspecialchars((string) $permission) ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
}

function render_list_page_toolbar(?string $html): void
{
    if ($html === null || trim($html) === '') {
        return;
    }
    ?>
    <div class="page-list-header-row page-list-header-row--toolbar page-list-toolbar admin-actions">
      <?= $html ?>
    </div>
    <?php
}
