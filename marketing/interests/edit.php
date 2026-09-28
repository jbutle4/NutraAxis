<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-intake.php';

auth_require_module_read('research-interests');

$activeSlug = 'research-interests';
$id = (int) ($_GET['id'] ?? 0) ?: null;
$existing = $id !== null ? mkt_interest_get($id) : null;
if ($id !== null && $existing === null) {
    marketing_redirect('/marketing/interests/', ['error' => 'Interest not found.']);
}
$canSave = $id === null ? marketing_can_create() : marketing_can_update();
$error = null;

$form = [
    'name'             => (string) ($existing['Name'] ?? ''),
    'description'      => (string) ($existing['Description'] ?? ''),
    'therapeutic_area' => (string) ($existing['TherapeuticArea'] ?? ''),
    'product_line'     => (string) ($existing['ProductLine'] ?? ''),
    'audience'         => (string) ($existing['Audience'] ?? ''),
    'priority'         => (int) ($existing['Priority'] ?? 3),
    'status'           => (string) ($existing['Status'] ?? 'active'),
    'agent_enabled'    => $existing === null ? 1 : (int) $existing['AgentEnabled'],
    'source_ids'       => $existing['source_ids'] ?? [],
];
foreach (array_keys(MKT_TERM_TYPES) as $type) {
    $form['terms_' . $type] = implode("\n", $existing['terms'][$type] ?? []);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canSave) {
        auth_render_access_denied('You do not have permission to save interests.');
    }
    $form = array_merge($form, $_POST, ['source_ids' => array_map('intval', (array) ($_POST['source_ids'] ?? []))]);
    $result = mkt_interest_save($_POST, $id);
    if ($result['ok']) {
        marketing_redirect('/marketing/interests/', ['notice' => $id === null ? 'Interest created.' : 'Interest updated.']);
    }
    $error = $result['error'];
}

$sources = mkt_sources_list();
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');
$productLines = marketing_setting_lines('taxonomy.product_lines');
$audiences = [];
foreach (marketing_setting_lines('brand.audiences') as $line) {
    [$key, $label] = array_pad(explode('|', $line, 2), 2, null);
    $audiences[trim($key)] = trim((string) ($label ?? $key));
}

$title = $id === null ? 'New interest' : 'Edit interest';
$pageTitle = $title . ' | NutraAxis Operations';
$pageDescription = 'Define a watched topic for the Content Harvester.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$select = static function (string $name, array $options, string $current, string $empty = '—'): void {
    echo '<select class="form-input" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '">';
    echo '<option value="">' . htmlspecialchars($empty) . '</option>';
    $known = false;
    foreach ($options as $value => $label) {
        $value = (string) (is_int($value) ? $label : $value);
        $known = $known || $value === $current;
        echo '<option value="' . htmlspecialchars($value) . '"' . ($value === $current ? ' selected' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    if (!$known && $current !== '') {
        echo '<option value="' . htmlspecialchars($current) . '" selected>' . htmlspecialchars($current) . '</option>';
    }
    echo '</select>';
};
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/marketing/interests/',
          'back_label' => 'Back to Interests & Sources',
          'category'   => 'Marketing & Research',
          'title'      => $title,
          'lead'       => 'Include terms are added to the Keyword Universe automatically. Exclude terms and queries steer relevance scoring and the weekly AI research agent.',
      ]); ?>
      <?php marketing_render_notice(null, $error); ?>

      <form class="admin-form" method="post">
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="name">Name</label>
            <input class="form-input" id="name" name="name" required maxlength="150" value="<?= htmlspecialchars((string) $form['name']) ?>" placeholder="e.g. Berberine evidence" />
          </div>
          <div class="form-group form-grid-full">
            <label for="description">Description</label>
            <textarea class="form-input" id="description" name="description" rows="2" placeholder="What we want to learn and why it matters to NutraAxis"><?= htmlspecialchars((string) $form['description']) ?></textarea>
          </div>
          <div class="form-group">
            <label for="therapeutic_area">Therapeutic area</label>
            <?php $select('therapeutic_area', $areas, (string) $form['therapeutic_area']); ?>
          </div>
          <div class="form-group">
            <label for="product_line">Product line</label>
            <?php $select('product_line', $productLines, (string) $form['product_line']); ?>
          </div>
          <div class="form-group">
            <label for="audience">Audience</label>
            <?php $select('audience', $audiences, (string) $form['audience']); ?>
          </div>
          <div class="form-group">
            <label for="priority">Priority (1–5)</label>
            <input class="form-input" type="number" min="1" max="5" id="priority" name="priority" value="<?= (int) $form['priority'] ?>" />
          </div>
          <div class="form-group">
            <label for="status">Status</label>
            <?php $select('status', MKT_RECORD_STATUSES, (string) $form['status'], 'Active'); ?>
          </div>
          <div class="form-group form-group--stacked">
            <label><input type="checkbox" name="agent_enabled" value="1" <?= !empty($form['agent_enabled']) ? 'checked' : '' ?> /> Run the weekly AI research agent for this interest</label>
          </div>
          <?php foreach (MKT_TERM_TYPES as $type => $label): ?>
          <div class="form-group">
            <label for="terms_<?= $type ?>"><?= htmlspecialchars($label) ?></label>
            <textarea class="form-input" id="terms_<?= $type ?>" name="terms_<?= $type ?>" rows="5" placeholder="One per line"><?= htmlspecialchars((string) $form['terms_' . $type]) ?></textarea>
          </div>
          <?php endforeach; ?>
          <div class="form-group form-grid-full form-group--stacked">
            <label>Sources</label>
            <?php if ($sources === []): ?>
            <p class="form-hint">No sources yet — <a href="/marketing/interests/source.php">add a source</a>, then link it here.</p>
            <?php else: ?>
            <div class="capability-grid capability-grid--six">
              <?php foreach ($sources as $source): ?>
              <label><input type="checkbox" name="source_ids[]" value="<?= (int) $source['SourceID'] ?>" <?= in_array((int) $source['SourceID'], $form['source_ids'], true) ? 'checked' : '' ?> />
                <?= htmlspecialchars((string) $source['Name']) ?> <span class="form-hint">(<?= htmlspecialchars(MKT_SOURCE_TYPES[(string) $source['SourceType']]['label'] ?? '') ?>)</span></label>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($canSave): ?>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $id === null ? 'Create interest' : 'Save changes' ?></button>
          <a class="btn-secondary" href="/marketing/interests/">Cancel</a>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
