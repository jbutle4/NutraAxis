<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-intake.php';

auth_require_module_read('research-interests');

$activeSlug = 'research-interests';
$id = (int) ($_GET['id'] ?? 0) ?: null;
$existing = $id !== null ? mkt_source_get($id) : null;
if ($id !== null && $existing === null) {
    marketing_redirect('/marketing/interests/', ['tab' => 'sources', 'error' => 'Source not found.']);
}
$canSave = $id === null ? marketing_can_create() : marketing_can_update();
$config = json_decode((string) ($existing['ConfigJson'] ?? ''), true) ?: [];
$error = null;

$form = [
    'name'         => (string) ($existing['Name'] ?? ($_GET['name'] ?? '')),
    'source_type'  => (string) ($existing['SourceType'] ?? ($_GET['type'] ?? 'rss')),
    'url'          => (string) ($existing['Url'] ?? ($_GET['url'] ?? '')),
    'query'        => (string) ($existing['Query'] ?? ''),
    'link_pattern' => (string) ($config['link_pattern'] ?? ''),
    'max_pages'    => (int) ($config['max_pages'] ?? 10),
    'schedule'     => (string) ($existing['Schedule'] ?? 'daily'),
    'status'       => (string) ($existing['Status'] ?? 'active'),
    'tos_notes'    => (string) ($existing['TosNotes'] ?? ''),
    'interest_ids' => $existing['interest_ids'] ?? [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canSave) {
        auth_render_access_denied('You do not have permission to save sources.');
    }
    $form = array_merge($form, $_POST, ['interest_ids' => array_map('intval', (array) ($_POST['interest_ids'] ?? []))]);
    $result = mkt_source_save($_POST, $id);
    if ($result['ok']) {
        marketing_redirect('/marketing/interests/', ['tab' => 'sources', 'notice' => $id === null ? 'Source created — it will run on the next hourly harvest.' : 'Source updated.']);
    }
    $error = $result['error'];
}

$interests = mkt_interests_list();
$runs = $id !== null ? mkt_harvest_runs(10, $id) : [];
$title = $id === null ? 'New source' : 'Edit source';
$pageTitle = $title . ' | NutraAxis Operations';
$pageDescription = 'Configure a Content Harvester source.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php render_list_page_header([
          'back_href'  => '/marketing/interests/?tab=sources',
          'back_label' => 'Back to Sources',
          'category'   => 'Marketing & Research',
          'title'      => $title,
          'lead'       => 'Feeds and searches are fetched by plain code on schedule — no AI. Sources auto-pause after repeated failures.',
      ]); ?>
      <?php marketing_render_notice(null, $error); ?>

      <form class="admin-form" method="post">
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="name">Name</label>
            <input class="form-input" id="name" name="name" required maxlength="150" value="<?= htmlspecialchars((string) $form['name']) ?>" placeholder="e.g. FDA press releases" />
          </div>
          <div class="form-group">
            <label for="source_type">Type</label>
            <select class="form-input" id="source_type" name="source_type" required>
              <?php foreach (MKT_SOURCE_TYPES as $key => $meta): ?>
              <option value="<?= htmlspecialchars($key) ?>" data-field="<?= $meta['field'] ?>" data-hint="<?= htmlspecialchars($meta['hint']) ?>" <?= $form['source_type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="schedule">Schedule</label>
            <select class="form-input" id="schedule" name="schedule">
              <?php foreach (MKT_SCHEDULES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $form['schedule'] === $key ? 'selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full" id="field-url">
            <label for="url">URL</label>
            <div>
              <input class="form-input" type="url" id="url" name="url" maxlength="2000" value="<?= htmlspecialchars((string) $form['url']) ?>" />
              <p class="form-hint source-type-hint"></p>
            </div>
          </div>
          <div class="form-group form-grid-full" id="field-query">
            <label for="query">Search query</label>
            <div>
              <input class="form-input" id="query" name="query" maxlength="1000" value="<?= htmlspecialchars((string) $form['query']) ?>" />
              <p class="form-hint source-type-hint"></p>
            </div>
          </div>
          <div class="form-group" id="field-link-pattern">
            <label for="link_pattern">Article link pattern</label>
            <div>
              <input class="form-input" id="link_pattern" name="link_pattern" value="<?= htmlspecialchars((string) $form['link_pattern']) ?>" placeholder="optional regex, e.g. /news/\d{4}/" />
              <p class="form-hint">Blank = links nested under the listing page path.</p>
            </div>
          </div>
          <div class="form-group" id="field-max-pages">
            <label for="max_pages">Max new pages per run</label>
            <input class="form-input" type="number" min="1" max="25" id="max_pages" name="max_pages" value="<?= (int) $form['max_pages'] ?>" />
          </div>
          <div class="form-group">
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <?php foreach (MKT_SOURCE_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $form['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group form-grid-full">
            <label for="tos_notes">Terms-of-use notes</label>
            <textarea class="form-input" id="tos_notes" name="tos_notes" rows="2" placeholder="Usage limits, attribution requirements, or restrictions"><?= htmlspecialchars((string) $form['tos_notes']) ?></textarea>
          </div>
          <div class="form-group form-grid-full form-group--stacked">
            <label>Interests</label>
            <?php if ($interests === []): ?>
            <p class="form-hint">No interests yet.</p>
            <?php else: ?>
            <div class="capability-grid capability-grid--six">
              <?php foreach ($interests as $interest): ?>
              <label><input type="checkbox" name="interest_ids[]" value="<?= (int) $interest['InterestID'] ?>" <?= in_array((int) $interest['InterestID'], $form['interest_ids'], true) ? 'checked' : '' ?> /> <?= htmlspecialchars((string) $interest['Name']) ?></label>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($canSave): ?>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><?= $id === null ? 'Create source' : 'Save changes' ?></button>
          <a class="btn-secondary" href="/marketing/interests/?tab=sources">Cancel</a>
        </div>
        <?php endif; ?>
      </form>

      <?php if ($existing !== null): ?>
      <h2 class="hub-section-title">Health</h2>
      <dl class="detail-list detail-list-inline detail-list-4col">
        <dt>Status</dt><dd><?= mkt_render_badge((string) $existing['Status'], MKT_SOURCE_STATUSES) ?></dd>
        <dt>Last run</dt><dd><?= htmlspecialchars(marketing_format_datetime($existing['LastRunAt'] ?? null)) ?></dd>
        <dt>Last success</dt><dd><?= htmlspecialchars(marketing_format_datetime($existing['LastSuccessAt'] ?? null)) ?></dd>
        <dt>Next run</dt><dd><?= htmlspecialchars($existing['NextRunAt'] ? marketing_format_datetime($existing['NextRunAt']) : 'Next hourly harvest') ?></dd>
        <dt>Consecutive failures</dt><dd><?= (int) $existing['ConsecutiveFailures'] ?></dd>
        <dt>Items total</dt><dd><?= (int) $existing['ItemsTotal'] ?></dd>
        <dt class="is-wide">Last error</dt><dd><?= htmlspecialchars((string) ($existing['LastError'] ?? '—')) ?></dd>
      </dl>
      <?php if ($runs !== []): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Started</th><th>Status</th><th>Fetched</th><th>New</th><th>Duplicates</th><th>Error</th></tr></thead>
          <tbody>
            <?php foreach ($runs as $run): ?>
            <tr>
              <td><?= htmlspecialchars(marketing_format_datetime($run['StartedAt'] ?? null)) ?></td>
              <td><?= mkt_render_badge((string) $run['Status']) ?></td>
              <td><?= (int) $run['Fetched'] ?></td>
              <td><?= (int) $run['Inserted'] ?></td>
              <td><?= (int) $run['Duplicates'] ?></td>
              <td><?= htmlspecialchars((string) ($run['ErrorMessage'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<script>
(function () {
  var type = document.getElementById('source_type');
  function sync() {
    var option = type.options[type.selectedIndex];
    var field = option.getAttribute('data-field');
    var isCrawl = type.value === 'crawl';
    document.getElementById('field-url').hidden = field !== 'url';
    document.getElementById('field-query').hidden = field !== 'query';
    document.getElementById('field-link-pattern').hidden = !isCrawl;
    document.getElementById('field-max-pages').hidden = !isCrawl;
    document.getElementById('url').required = field === 'url';
    document.getElementById('query').required = field === 'query';
    document.querySelectorAll('.source-type-hint').forEach(function (el) { el.textContent = option.getAttribute('data-hint'); });
  }
  type.addEventListener('change', sync);
  sync();
})();
</script>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
