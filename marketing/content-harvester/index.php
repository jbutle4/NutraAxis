<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-intake.php';

auth_require_module_read('research-harvester');

$activeSlug = 'research-harvester';
$baseHref = '/marketing/content-harvester/';
$tabs = ['queue' => 'Item queue', 'runs' => 'Harvest runs', 'add' => 'Add item', 'suggested' => 'Suggested sources'];
$tab = (string) ($_GET['tab'] ?? 'queue');
if (!isset($tabs[$tab])) {
    $tab = 'queue';
}
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'item_status') {
        marketing_require_update();
        $ok = mkt_item_set_status((int) ($_POST['item_id'] ?? 0), (string) ($_POST['status'] ?? ''));
        $return = (string) ($_POST['return'] ?? $baseHref);
        marketing_redirect(str_starts_with($return, $baseHref) ? $return : $baseHref, $ok ? [] : ['error' => 'Could not update the item.']);
    }
    if ($action === 'add_item') {
        marketing_require_create();
        $result = mkt_item_add_manual($_POST);
        if ($result['ok']) {
            marketing_redirect($baseHref, ['notice' => 'Item added to the queue.']);
        }
        $tab = 'add';
        $error = $result['error'];
    }
}

$pageTitle = 'Content Harvester | NutraAxis Operations';
$pageDescription = 'Scheduled harvest runs, the deduplicated item queue, and source suggestions.';
$back = app_module_hub_back_link($activeSlug);
$interests = mkt_interests_list();

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
          'title'      => 'Content Harvester',
          'lead'       => 'Stage 2: sources are fetched hourly by schedule and deduplicated; the weekly AI research agent adds verified finds. Items flow to Topic Synthesis.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_tabs($baseHref, $tabs, $tab);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      ?>

<?php if ($tab === 'queue'): ?>
      <?php
      $filters = [
          'status'      => (string) ($_GET['status'] ?? 'open'),
          'source_id'   => (int) ($_GET['source_id'] ?? 0),
          'source_type' => (string) ($_GET['source_type'] ?? ''),
          'interest_id' => (int) ($_GET['interest_id'] ?? 0),
          'q'           => trim((string) ($_GET['q'] ?? '')),
      ];
      $items = mkt_items_list($filters, 200);
      $counts = mkt_item_status_counts();
      $returnUrl = $baseHref . '?' . http_build_query(array_filter($filters));
      ?>
      <p class="form-hint">
        <?php foreach (MKT_ITEM_STATUSES as $key => $label): ?>
        <?= htmlspecialchars($label) ?>: <strong><?= (int) ($counts[$key] ?? 0) ?></strong> &nbsp;
        <?php endforeach; ?>
      </p>
      <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <div class="audit-filter-grid">
          <div>
            <label for="status">Status</label>
            <select class="form-input" id="status" name="status">
              <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>Open (new → promoted)</option>
              <option value="" <?= $filters['status'] === '' ? 'selected' : '' ?>>All</option>
              <?php foreach (MKT_ITEM_STATUSES as $key => $label): ?>
              <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="interest_id">Interest</label>
            <select class="form-input" id="interest_id" name="interest_id">
              <option value="">All interests</option>
              <?php foreach ($interests as $interest): ?>
              <option value="<?= (int) $interest['InterestID'] ?>" <?= $filters['interest_id'] === (int) $interest['InterestID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $interest['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="source_type">Source type</label>
            <select class="form-input" id="source_type" name="source_type">
              <option value="">All types</option>
              <?php foreach (MKT_ITEM_SOURCE_TYPES as $key => $meta): ?>
              <option value="<?= $key ?>" <?= $filters['source_type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide">
            <label for="q">Search</label>
            <input class="form-input" type="search" id="q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Title, summary, or domain" />
          </div>
        </div>
        <?php if ($filters['source_id'] > 0): ?><input type="hidden" name="source_id" value="<?= $filters['source_id'] ?>" /><?php endif; ?>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Item</th><th>Source</th><th>Published</th><th>Fetched</th><th>Status</th><th>Actions</th></tr>
          </thead>
          <tbody>
            <?php if ($items === []): ?>
            <tr><td colspan="6">No items match. New sources are fetched on the next hourly harvest.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
            <?php $status = (string) $item['Status']; ?>
            <tr>
              <td>
                <a href="<?= htmlspecialchars((string) $item['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $item['Title']) ?></a>
                <br><span class="form-hint"><?= htmlspecialchars(mb_strimwidth(trim((string) ($item['Summary'] ?? '')), 0, 220, '…')) ?></span>
                <?php if (!empty($item['VerificationNote'])): ?><br><span class="form-hint">Verification: <?= htmlspecialchars((string) $item['VerificationNote']) ?></span><?php endif; ?>
              </td>
              <td>
                <?= htmlspecialchars((string) ($item['SourceName'] ?? (MKT_ITEM_SOURCE_TYPES[(string) $item['SourceType']]['label'] ?? $item['SourceType']))) ?>
                <br><span class="form-hint"><?= htmlspecialchars((string) ($item['Domain'] ?? '')) ?><?= !empty($item['InterestName']) ? ' · ' . htmlspecialchars((string) $item['InterestName']) : '' ?></span>
              </td>
              <td><?= htmlspecialchars(marketing_format_date($item['PublishedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($item['FetchedAt'] ?? null)) ?></td>
              <td><?= mkt_render_badge($status, MKT_ITEM_STATUSES) ?></td>
              <td>
                <?php if (marketing_can_update() && in_array($status, ['new', 'scored', 'ignored'], true)): ?>
                <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="table-action-form">
                  <input type="hidden" name="action" value="item_status" />
                  <input type="hidden" name="item_id" value="<?= (int) $item['ItemID'] ?>" />
                  <input type="hidden" name="return" value="<?= htmlspecialchars($returnUrl) ?>" />
                  <input type="hidden" name="status" value="<?= $status === 'ignored' ? 'new' : 'ignored' ?>" />
                  <button type="submit" class="btn-secondary"><?= $status === 'ignored' ? 'Restore' : 'Ignore' ?></button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php elseif ($tab === 'runs'): ?>
      <?php $runs = mkt_harvest_runs(150); ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr><th>Started</th><th>Source / interest</th><th>Type</th><th>Status</th><th>Fetched</th><th>New</th><th>Duplicates</th><th>Rejected</th><th>Error</th></tr>
          </thead>
          <tbody>
            <?php if ($runs === []): ?>
            <tr><td colspan="9">No harvest runs yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($runs as $run): ?>
            <tr>
              <td><?= htmlspecialchars(marketing_format_datetime($run['StartedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars((string) ($run['SourceName'] ?? $run['InterestName'] ?? '—')) ?></td>
              <td><?= htmlspecialchars(MKT_ITEM_SOURCE_TYPES[(string) $run['RunType']]['label'] ?? (string) $run['RunType']) ?></td>
              <td><?= mkt_render_badge((string) $run['Status']) ?></td>
              <td><?= (int) $run['Fetched'] ?></td>
              <td><?= (int) $run['Inserted'] ?></td>
              <td><?= (int) $run['Duplicates'] ?></td>
              <td><?= (int) $run['Rejected'] ?></td>
              <td><?= htmlspecialchars(mb_strimwidth((string) ($run['ErrorMessage'] ?? ''), 0, 160, '…')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

<?php elseif ($tab === 'add'): ?>
      <?php if (!marketing_can_create()): ?>
      <p>You need create access to add items.</p>
      <?php else: ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>?tab=add">
        <input type="hidden" name="action" value="add_item" />
        <?php marketing_render_field_guide_link('item-add'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="url">URL</label>
            <input class="form-input" type="url" id="url" name="url" required value="<?= htmlspecialchars((string) ($_POST['url'] ?? '')) ?>" placeholder="Article, study, or post URL" />
          </div>
          <div class="form-group form-grid-full">
            <label for="title">Title</label>
            <input class="form-input" id="title" name="title" maxlength="500" value="<?= htmlspecialchars((string) ($_POST['title'] ?? '')) ?>" placeholder="Leave blank to read it from the page" />
          </div>
          <div class="form-group form-grid-full">
            <label for="summary">Summary / why it matters</label>
            <textarea class="form-input" id="summary" name="summary" rows="3"><?= htmlspecialchars((string) ($_POST['summary'] ?? '')) ?></textarea>
          </div>
          <div class="form-group form-grid-full">
            <label for="body">Pasted text (optional)</label>
            <textarea class="form-input" id="body" name="body" rows="6" placeholder="Paste text from closed groups, PDFs, or newsletters"><?= htmlspecialchars((string) ($_POST['body'] ?? '')) ?></textarea>
          </div>
          <div class="form-group">
            <label for="interest_id">Interest</label>
            <select class="form-input" id="interest_id" name="interest_id">
              <option value="">—</option>
              <?php foreach ($interests as $interest): ?>
              <option value="<?= (int) $interest['InterestID'] ?>"><?= htmlspecialchars((string) $interest['Name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn-primary">Add to queue</button></div>
      </form>
      <?php endif; ?>

<?php else: ?>
      <?php $suggested = mkt_suggested_source_domains(); ?>
      <p class="form-hint">Domains the weekly AI research agent cited at least twice (not counting citations it rejected as unreachable or not matching) that do not appear in any source's URL. Paused sources count as registered, so their domains are not suggested.</p>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Domain</th><th>Cited items</th><th>Last seen</th><th>Sample</th><th>Actions</th></tr></thead>
          <tbody>
            <?php if ($suggested === []): ?>
            <tr><td colspan="5">No suggestions yet — they appear after the research agent has run.</td></tr>
            <?php endif; ?>
            <?php foreach ($suggested as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['Domain']) ?></td>
              <td><?= (int) $row['Items'] ?></td>
              <td><?= htmlspecialchars(marketing_format_datetime($row['LastSeen'] ?? null)) ?></td>
              <td><a href="<?= htmlspecialchars((string) $row['SampleUrl']) ?>" target="_blank" rel="noopener noreferrer">Open</a></td>
              <td>
                <?php if (marketing_can_create()): ?>
                <a class="btn-secondary" href="/marketing/interests/source.php?<?= htmlspecialchars(http_build_query(['type' => 'crawl', 'name' => (string) $row['Domain'], 'url' => 'https://' . $row['Domain'] . '/'])) ?>">Add as source</a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
<?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
