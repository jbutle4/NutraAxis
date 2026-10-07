<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-intake.php';

auth_require_module_read('research-interests');

$activeSlug = 'research-interests';
$baseHref = '/marketing/interests/';
$tabs = ['interests' => 'Interests', 'sources' => 'Sources', 'taxonomy' => 'Taxonomy'];
$tab = (string) ($_GET['tab'] ?? 'interests');
if (!isset($tabs[$tab])) {
    $tab = 'interests';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'source_status') {
        $ok = mkt_source_set_status((int) ($_POST['source_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        marketing_redirect($baseHref, ['tab' => 'sources'] + ($ok ? ['notice' => 'Source status updated.'] : ['error' => 'Could not update the source.']));
    }
    if ($action === 'save_taxonomy') {
        $changed = marketing_settings_save([
            'taxonomy.therapeutic_areas' => (string) ($_POST['therapeutic_areas'] ?? ''),
            'taxonomy.product_lines'     => (string) ($_POST['product_lines'] ?? ''),
            'brand.audiences'            => (string) ($_POST['audiences'] ?? ''),
        ]);
        marketing_redirect($baseHref, ['tab' => 'taxonomy', 'notice' => $changed . ' list(s) updated.']);
    }
}

$pageTitle = 'Interests & Sources | NutraAxis Operations';
$pageDescription = 'Watched topics, terms, and the sources the Content Harvester fetches.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Interests & Sources',
          'lead'       => 'Stage 1 of the Content Engine: what we watch (interests and terms) and where we look (feeds, searches, sites).',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_tabs($baseHref, $tabs, $tab);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

<?php if ($tab === 'interests'): ?>
      <?php $interests = mkt_interests_list((string) ($_GET['status'] ?? '')); ?>
      <?php if (marketing_can_create()): ?>
      <p><a class="btn-primary" href="/marketing/interests/edit.php">New interest</a></p>
      <?php endif; ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Interest</th><th>Area / audience</th><th>Priority</th><th>Performance</th><th>Include terms</th><th>Sources</th><th>Items (7 days)</th><th>AI agent</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php if ($interests === []): ?>
            <tr><td colspan="9">No interests yet. Create one to start harvesting — each interest needs include terms or search queries. Linking sources is optional; every harvested item is scored against every active interest.</td></tr>
            <?php endif; ?>
            <?php foreach ($interests as $row): ?>
            <tr>
              <td>
                <a class="table-name-link" href="/marketing/interests/edit.php?id=<?= (int) $row['InterestID'] ?>"><?= htmlspecialchars((string) $row['Name']) ?></a>
                <?php if (!empty($row['Description'])): ?><br><span class="form-hint"><?= htmlspecialchars(mb_strimwidth((string) $row['Description'], 0, 120, '…')) ?></span><?php endif; ?>
              </td>
              <td><?= htmlspecialchars(implode(' · ', array_filter([(string) ($row['TherapeuticArea'] ?? ''), (string) ($row['Audience'] ?? '')]))) ?: '—' ?></td>
              <td><?= (int) $row['Priority'] ?><?= $row['SuggestedPriority'] !== null && (int) $row['SuggestedPriority'] !== (int) $row['Priority'] ? ' <span class="form-hint">→ ' . (int) $row['SuggestedPriority'] . ' suggested</span>' : '' ?></td>
              <td>
                <?php if ($row['PerformanceScore'] === null): ?>—<?php else: ?>
                <?= number_format((float) $row['PerformanceScore'], 0) ?><?= (float) $row['RelevanceWeight'] !== 1.0 ? ' <span class="form-hint">(weight ' . number_format((float) $row['RelevanceWeight'], 2) . '×)</span>' : '' ?>
                <?php endif; ?>
              </td>
              <td><?= (int) $row['IncludeCount'] ?></td>
              <td><?= (int) $row['SourceCount'] ?></td>
              <td><a href="/marketing/content-harvester/?interest_id=<?= (int) $row['InterestID'] ?>"><?= (int) $row['Items7d'] ?></a></td>
              <td><?= !empty($row['AgentEnabled']) ? 'Weekly' : 'Off' ?><?= !empty($row['LastAgentRunAt']) ? '<br><span class="form-hint">' . htmlspecialchars(marketing_format_datetime($row['LastAgentRunAt'])) . '</span>' : '' ?></td>
              <td><?= mkt_render_badge((string) $row['Status'], MKT_RECORD_STATUSES) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php elseif ($tab === 'sources'): ?>
      <?php
      $typeFilter = (string) ($_GET['type'] ?? '');
      $statusFilter = (string) ($_GET['status'] ?? '');
      $sources = mkt_sources_list(['type' => $typeFilter, 'status' => $statusFilter]);
      ?>
      <?php if (marketing_can_create()): ?>
      <p><a class="btn-primary" href="/marketing/interests/source.php">New source</a></p>
      <?php endif; ?>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="tab" value="sources" />
        <div class="audit-filter-grid">
          <div>
            <label for="type">Type</label>
            <select class="form-input" id="type" name="type">
              <option value="">All types</option>
              <?php foreach (MKT_SOURCE_TYPES as $key => $meta): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $typeFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach (MKT_SOURCE_STATUSES as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=sources">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Source</th><th>Type</th><th>Interests</th><th>Schedule</th><th>Last success</th><th>Items (7 days / total)</th><th>Health</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($sources === []): ?>
            <tr><td colspan="8">No sources match.</td></tr>
            <?php endif; ?>
            <?php foreach ($sources as $row): ?>
            <?php $status = (string) $row['Status']; ?>
            <tr>
              <td>
                <a class="table-name-link" href="/marketing/interests/source.php?id=<?= (int) $row['SourceID'] ?>"><?= htmlspecialchars((string) $row['Name']) ?></a>
                <br><span class="form-hint"><?= htmlspecialchars(mb_strimwidth(mkt_source_display_target($row), 0, 80, '…')) ?></span>
              </td>
              <td><?= htmlspecialchars(MKT_SOURCE_TYPES[(string) $row['SourceType']]['label'] ?? (string) $row['SourceType']) ?></td>
              <td><?= htmlspecialchars((string) ($row['InterestNames'] ?? '')) ?: '—' ?></td>
              <td><?= htmlspecialchars(MKT_SCHEDULES[(string) $row['Schedule']] ?? (string) $row['Schedule']) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($row['LastSuccessAt'] ?? null)) ?></td>
              <td><a href="/marketing/content-harvester/?source_id=<?= (int) $row['SourceID'] ?>"><?= (int) $row['Items7d'] ?></a> / <?= (int) $row['ItemsTotal'] ?></td>
              <td>
                <?= mkt_render_badge($status, MKT_SOURCE_STATUSES) ?>
                <?php if ((int) $row['ConsecutiveFailures'] > 0): ?>
                <br><span class="form-hint"><?= (int) $row['ConsecutiveFailures'] ?> failure(s): <?= htmlspecialchars(mb_strimwidth((string) ($row['LastError'] ?? ''), 0, 120, '…')) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (marketing_can_update()): ?>
                <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="table-action-form">
                  <input type="hidden" name="action" value="source_status" />
                  <input type="hidden" name="source_id" value="<?= (int) $row['SourceID'] ?>" />
                  <input type="hidden" name="status" value="<?= $status === 'active' ? 'paused' : 'active' ?>" />
                  <button type="submit" class="btn-secondary"><?= $status === 'active' ? 'Pause' : 'Resume' ?></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php else: ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="action" value="save_taxonomy" />
        <?php marketing_render_field_guide_link('taxonomy'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="therapeutic_areas">Therapeutic areas</label>
            <textarea class="form-input" id="therapeutic_areas" name="therapeutic_areas" rows="6" <?= marketing_can_update() ? '' : 'readonly' ?>><?= htmlspecialchars((string) marketing_setting('taxonomy.therapeutic_areas', '')) ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="product_lines">Product lines</label>
            <textarea class="form-input" id="product_lines" name="product_lines" rows="6" <?= marketing_can_update() ? '' : 'readonly' ?>><?= htmlspecialchars((string) marketing_setting('taxonomy.product_lines', '')) ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="audiences">Audiences (key|description)</label>
            <textarea class="form-input" id="audiences" name="audiences" rows="4" <?= marketing_can_update() ? '' : 'readonly' ?>><?= htmlspecialchars((string) marketing_setting('brand.audiences', '')) ?></textarea>
          </div>
        </div>
        <p class="form-hint">One entry per line. These lists feed the interest form and generation prompts.</p>
        <?php if (marketing_can_update()): ?>
        <div class="form-actions"><button type="submit" class="btn-primary">Save taxonomy</button></div>
        <?php endif; ?>
      </form>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
