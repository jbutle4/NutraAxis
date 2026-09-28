<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/app.php';
require dirname(__DIR__) . '/includes/hub-cards.php';
require dirname(__DIR__) . '/includes/list-page-header.php';

auth_require_module_read('marketing');

$hub = get_module('marketing');
if ($hub === null) {
    http_response_code(404);
    exit('Module hub not found.');
}

$areas = auth_filter_hub_submodules(app_hub_submodules('marketing'));

usort(
    $areas,
    static fn(array $a, array $b): int => ((int) ($a['sort'] ?? 0)) <=> ((int) ($b['sort'] ?? 0))
);

$sections = [
    'engine'  => ['title' => 'Content Engine', 'items' => []],
    'library' => ['title' => 'Supporting Libraries & Governance', 'items' => []],
    'seo'     => ['title' => 'SEO & Site', 'items' => []],
    'other'   => ['title' => 'Other', 'items' => []],
];
foreach ($areas as $item) {
    $key = (string) ($item['section'] ?? 'other');
    $sections[isset($sections[$key]) ? $key : 'other']['items'][] = $item;
}

$activeSlug = 'marketing';
$pageTitle = ($hub['title'] ?? 'Marketing & Research Hub') . ' | NutraAxis Operations';
$pageDescription = (string) ($hub['desc'] ?? '');

require dirname(__DIR__) . '/includes/head.php';
require dirname(__DIR__) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/',
          'back_label' => 'Back to Operations Home',
          'category'   => (string) ($hub['label'] ?? 'Marketing & Research'),
          'title'      => (string) ($hub['headline'] ?? $hub['title'] ?? 'Marketing & Research Hub'),
          'lead'       => (string) ($hub['lead'] ?? $hub['desc'] ?? ''),
      ]); ?>

<?php if ($areas === []): ?>
      <div class="status-banner">
        <div>
          <strong>No applications assigned</strong>
          <p>Your role does not include Marketing &amp; Research access. Contact a site administrator.</p>
        </div>
      </div>
<?php else: ?>
<?php foreach ($sections as $section): ?>
<?php if ($section['items'] !== []): ?>
      <h2 class="hub-section-title"><?= htmlspecialchars($section['title']) ?></h2>
      <?php hub_render_card_grid($section['items'], 'capability-card capability-card-link', 'capability-grid capability-grid--six'); ?>
<?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__) . '/includes/footer.php';
