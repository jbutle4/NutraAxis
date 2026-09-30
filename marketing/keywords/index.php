<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-intake.php';

auth_require_module_read('marketing-keywords');

$activeSlug = 'marketing-keywords';
$baseHref = '/marketing/keywords/';
$error = null;
$editId = (int) ($_GET['edit'] ?? 0) ?: null;
$editing = $editId !== null ? mkt_keyword_get($editId) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $id = (int) ($_POST['keyword_id'] ?? 0) ?: null;
        $id === null ? marketing_require_create() : marketing_require_update();
        $result = mkt_keyword_save($_POST, $id);
        if ($result['ok']) {
            marketing_redirect($baseHref, ['notice' => $id === null ? 'Keyword added.' : 'Keyword updated.']);
        }
        $error = $result['error'];
        $editing = $id !== null ? array_merge(mkt_keyword_get($id) ?? [], ['KeywordID' => $id]) : null;
    }
    if ($action === 'import') {
        marketing_require_create();
        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Choose a CSV file to import.';
        } else {
            $result = mkt_keywords_import_csv((string) $file['tmp_name']);
            if ($result['ok']) {
                marketing_redirect($baseHref, ['notice' => sprintf('Import complete: %d created, %d updated, %d skipped.', $result['created'], $result['updated'], $result['skipped'])]);
            }
            $error = $result['error'];
        }
    }
}

$filters = [
    'purpose' => (string) ($_GET['purpose'] ?? ''),
    'status'  => (string) ($_GET['status'] ?? ''),
    'q'       => trim((string) ($_GET['q'] ?? '')),
];
$keywords = mkt_keywords_list($filters);

$pageTitle = 'Keyword Universe | NutraAxis Operations';
$pageDescription = 'SEO keywords and interest terms in one list.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$field = static fn(string $key, $default = '') => htmlspecialchars((string) ($editing[$key] ?? $default));
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Keyword Universe',
          'lead'       => 'One keyword list for SEO targets and interest terms. Include terms added on an interest appear here with purpose "Interest".',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      ?>

      <?php if (marketing_can_create() || ($editing !== null && marketing_can_update())): ?>
      <h2 class="hub-section-title"><?= $editing !== null ? 'Edit keyword' : 'Add keyword' ?></h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
        <input type="hidden" name="action" value="save" />
        <?php if ($editing !== null): ?><input type="hidden" name="keyword_id" value="<?= (int) $editing['KeywordID'] ?>" /><?php endif; ?>
        <?php marketing_render_field_guide_link('keyword'); ?>
        <div class="form-grid">
          <div class="form-group"><label for="keyword">Keyword</label><input class="form-input" id="keyword" name="keyword" required maxlength="200" value="<?= $field('Keyword') ?>" /></div>
          <div class="form-group">
            <label for="purpose">Purpose</label>
            <select class="form-input" id="purpose" name="purpose">
              <?php foreach (MKT_KEYWORD_PURPOSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= ($editing['Purpose'] ?? 'seo') === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label for="priority">Priority (1–5)</label><input class="form-input" type="number" min="1" max="5" id="priority" name="priority" value="<?= $field('Priority', 3) ?>" /></div>
          <div class="form-group"><label for="cluster">Cluster</label><input class="form-input" id="cluster" name="cluster" maxlength="100" value="<?= $field('Cluster') ?>" /></div>
          <div class="form-group"><label for="intent">Intent</label><input class="form-input" id="intent" name="intent" maxlength="20" value="<?= $field('Intent') ?>" placeholder="info / commercial / nav" /></div>
          <div class="form-group"><label for="volume">Monthly volume</label><input class="form-input" type="number" min="0" id="volume" name="volume" value="<?= $field('Volume') ?>" /></div>
          <div class="form-group"><label for="difficulty">Difficulty</label><input class="form-input" type="number" min="0" max="100" id="difficulty" name="difficulty" value="<?= $field('Difficulty') ?>" /></div>
          <div class="form-group">
            <label for="kw_status">Status</label>
            <select class="form-input" id="kw_status" name="status">
              <?php foreach (MKT_RECORD_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= ($editing['Status'] ?? 'active') === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full"><label for="notes">Notes</label><input class="form-input" id="notes" name="notes" maxlength="1000" value="<?= $field('Notes') ?>" /></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $editing !== null ? 'Save keyword' : 'Add keyword' ?></button>
          <?php if ($editing !== null): ?><a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Cancel</a><?php endif; ?>
        </div>
      </form>
      <?php endif; ?>

      <?php if (marketing_can_create()): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import" />
        <?php marketing_render_field_guide_link('keyword-import'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="csv">Import CSV</label>
            <div>
              <input class="form-input" type="file" id="csv" name="csv" accept=".csv,text/csv" />
              <p class="form-hint">Header row required. Columns: keyword, purpose (seo/interest/both), priority, cluster, intent, volume, difficulty, notes. Existing keywords are updated.</p>
            </div>
          </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn-secondary">Import</button></div>
      </form>
      <?php endif; ?>

      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <div class="audit-filter-grid">
          <div>
            <label for="f_purpose">Purpose</label>
            <select class="form-input" id="f_purpose" name="purpose">
              <option value="">All</option>
              <?php foreach (MKT_KEYWORD_PURPOSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $filters['purpose'] === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="f_status">Status</label>
            <select class="form-input" id="f_status" name="status">
              <option value="">All</option>
              <?php foreach (MKT_RECORD_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="f_q">Search</label>
            <input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Keyword, cluster, or notes" />
          </div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
        </div>
      </form>

      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Keyword</th><th>Purpose</th><th>Priority</th><th>Cluster</th><th>Intent</th><th>Volume</th><th>Difficulty</th><th>Interests</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($keywords === []): ?>
            <tr><td colspan="10">No keywords yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($keywords as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['Keyword']) ?></td>
              <td><?= htmlspecialchars(MKT_KEYWORD_PURPOSES[(string) $row['Purpose']] ?? (string) $row['Purpose']) ?></td>
              <td><?= (int) $row['Priority'] ?></td>
              <td><?= htmlspecialchars((string) ($row['Cluster'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['Intent'] ?? '')) ?></td>
              <td><?= $row['Volume'] !== null ? number_format((int) $row['Volume']) : '—' ?></td>
              <td><?= $row['Difficulty'] !== null ? (int) $row['Difficulty'] : '—' ?></td>
              <td><?= (int) $row['InterestLinks'] ?></td>
              <td><?= mkt_render_badge((string) $row['Status'], MKT_RECORD_STATUSES) ?></td>
              <td><?php if (marketing_can_update()): ?><a href="<?= htmlspecialchars($baseHref) ?>?edit=<?= (int) $row['KeywordID'] ?>">Edit</a><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
