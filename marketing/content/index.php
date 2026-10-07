<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-content');

$activeSlug = 'marketing-content';
$baseHref = '/marketing/content/';
$tabs = [
    'board'    => 'Board',
    'list'     => 'All content',
    'review'   => 'Review queue',
    'new'      => 'New piece',
    'archived' => 'Archived',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'board';
$error = null;
$types = mkt_content_types();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    marketing_require_create();
    $result = mkt_content_create($_POST);
    if ($result['ok']) {
        mkt_tasks_sync();
        if (!empty($_POST['brief_now'])) {
            $run = process_execute('content-brief', ['content_id' => $result['id']], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            mkt_tasks_sync();
            marketing_redirect('/marketing/content/view.php', !empty($run['ok'])
                ? ['id' => $result['id'], 'notice' => 'Piece created. ' . (string) ($run['message'] ?? 'Brief written.')]
                : ['id' => $result['id'], 'error' => 'Piece created, but the brief failed: ' . (string) ($run['error'] ?? 'unknown error')]);
        }
        marketing_redirect('/marketing/content/view.php', ['id' => $result['id'], 'notice' => 'Piece created.']);
    }
    $error = $result['error'];
    $tab = 'new';
}

$canCreate = marketing_can_create();
$counts = mkt_content_stage_counts();
$isCompliance = mkt_can_compliance_review();
$isEditor = mkt_can_editorial_review();
$minScore = (float) marketing_setting('claims.min_score', '7');

$pageTitle = 'Content Pipeline | NutraAxis Operations';
$pageDescription = 'Long-form web content from brief to published URL, claims-checked and cleared by compliance and editorial review.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$tabLabels = $tabs;
$tabLabels['review'] .= ' (' . ($counts['compliance_review'] + $counts['editorial']) . ')';
if (!$canCreate) {
    unset($tabLabels['new']);
}
$flagged = static fn(array $row): bool => $row['Stage'] === 'draft' && ($row['ComplianceStatus'] === 'changes_requested' || $row['EditorialStatus'] === 'changes_requested');
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Content Pipeline',
          'lead'       => 'Long-form pages for the site: AI brief, AI or manual draft, claims check, compliance review, editorial approval, then a manual publish with the live URL recorded. Every edit is kept as a version.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Pipeline</strong>
          <p>
            <?= $counts['idea'] + $counts['brief'] ?> in planning ·
            <?= $counts['draft'] ?> drafting ·
            <?= $counts['compliance_review'] ?> awaiting compliance ·
            <?= $counts['editorial'] ?> awaiting editorial ·
            <?= $counts['approved'] ?> ready to publish ·
            <?= $counts['published'] + $counts['monitoring'] ?> live
          </p>
          <p class="form-hint">Submitting needs a claims score of at least <?= htmlspecialchars(number_format($minScore, 1)) ?>. Nothing is published automatically — the coordinator puts approved copy live and records the URL.</p>
        </div>
      </div>

      <?php if ($tab === 'board'): ?>
        <?php
        $rows = mkt_content_list(['stage' => 'active']);
        $columns = array_fill_keys(MKT_CONTENT_BOARD, []);
        foreach ($rows as $row) {
            $col = $row['Stage'] === 'monitoring' ? 'published' : (string) $row['Stage'];
            if (isset($columns[$col])) {
                $columns[$col][] = $row;
            }
        }
        ?>
        <?php if ($rows === []): ?>
        <p>No content yet. <?= $canCreate ? 'Start a piece on the <a href="?tab=new">New piece</a> tab, or from an accepted topic on the <a href="/marketing/topics/">Topic Board</a>.' : '' ?></p>
        <?php else: ?>
        <div class="mkt-board">
          <?php foreach ($columns as $stage => $items): ?>
          <div class="mkt-board-col">
            <h3><span><?= htmlspecialchars($stage === 'published' ? 'Live' : MKT_CONTENT_STAGES[$stage]) ?></span><span class="form-hint"><?= count($items) ?></span></h3>
            <?php foreach ($items as $row): ?>
            <a class="mkt-board-card<?= $flagged($row) ? ' is-flagged' : '' ?>" href="/marketing/content/view.php?id=<?= (int) $row['ContentID'] ?>">
              <strong><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 90, '…')) ?></strong>
              <span class="form-hint">
                <?= htmlspecialchars(mkt_content_type_label((string) $row['ContentType'])) ?>
                <?= $row['CurrentVersionNo'] ? ' · v' . (int) $row['CurrentVersionNo'] : '' ?>
                <?= $row['ClaimsScore'] !== null ? ' · ' . htmlspecialchars(number_format((float) $row['ClaimsScore'], 1)) : '' ?>
                <?= $row['OwnerName'] ? ' · ' . htmlspecialchars((string) $row['OwnerName']) : '' ?>
                <?= $flagged($row) ? '<br>Changes requested' : '' ?>
                <?= $row['DueDate'] ? '<br>Due ' . htmlspecialchars(marketing_format_date((string) $row['DueDate'])) : '' ?>
              </span>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      <?php elseif ($tab === 'list' || $tab === 'archived'): ?>
        <?php
        $filters = [
            'stage' => $tab === 'archived' ? 'archived' : (string) ($_GET['stage'] ?? 'active'),
            'type'  => (string) ($_GET['type'] ?? ''),
            'q'     => (string) ($_GET['q'] ?? ''),
        ];
        $rows = mkt_content_list($filters);
        ?>
        <?php if ($tab === 'list'): ?>
        <form class="mkt-inline-form" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="tab" value="list" />
          <select class="form-input" name="stage">
            <option value="active" <?= $filters['stage'] === 'active' ? 'selected' : '' ?>>All active stages</option>
            <?php foreach (MKT_CONTENT_STAGES as $key => $label): ?>
            <?php if ($key !== 'archived'): ?>
            <option value="<?= $key ?>" <?= $filters['stage'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endif; ?>
            <?php endforeach; ?>
          </select>
          <select class="form-input" name="type">
            <option value="">All types</option>
            <?php foreach ($types as $key => $type): ?>
            <option value="<?= htmlspecialchars($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($type['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <input class="form-input" type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Title or keyword" />
          <button type="submit" class="btn-secondary">Filter</button>
        </form>
        <?php endif; ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Title</th><th>Type</th><th>Keyword</th><th>Stage</th><th>Version</th><th>Claims score</th><th>Owner</th><th>Due</th><th>Updated</th></tr></thead>
            <tbody>
              <?php if ($rows === []): ?>
              <tr><td colspan="9"><?= $tab === 'archived' ? 'No archived content.' : 'No content matches.' ?></td></tr>
              <?php endif; ?>
              <?php foreach ($rows as $row): ?>
              <tr>
                <td>
                  <a href="/marketing/content/view.php?id=<?= (int) $row['ContentID'] ?>"><strong><?= htmlspecialchars((string) $row['Title']) ?></strong></a>
                  <?php if (!empty($row['TopicTitle'])): ?><div class="form-hint">Topic: <?= htmlspecialchars(mb_strimwidth((string) $row['TopicTitle'], 0, 100, '…')) ?></div><?php endif; ?>
                  <?php if (!empty($row['ProductName'])): ?><div class="form-hint">Product: <?= htmlspecialchars((string) $row['ProductName']) ?></div><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(mkt_content_type_label((string) $row['ContentType'])) ?></td>
                <td><?= htmlspecialchars((string) ($row['PrimaryKeyword'] ?? '—')) ?></td>
                <td><?= mkt_content_badge((string) $row['Stage']) ?><?= $flagged($row) ? '<div class="form-hint">Changes requested</div>' : '' ?></td>
                <td><?= $row['CurrentVersionNo'] ? 'v' . (int) $row['CurrentVersionNo'] . ' · ' . number_format((int) $row['WordCount']) . ' words' : '—' ?></td>
                <td><?= $row['CurrentVersionNo'] ? mkt_content_score_badge($row) : '—' ?></td>
                <td><?= htmlspecialchars((string) ($row['OwnerName'] ?? '—')) ?></td>
                <td><?= $row['DueDate'] ? htmlspecialchars(marketing_format_date((string) $row['DueDate'])) : '—' ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime($row['UpdatedAt'] ?? null)) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'review'): ?>
        <?php
        $queue = array_merge(mkt_content_list(['stage' => 'compliance_review']), mkt_content_list(['stage' => 'editorial']));
        usort($queue, static fn(array $a, array $b): int => strcmp((string) $a['SubmittedAt'], (string) $b['SubmittedAt']));
        $versions = [];
        foreach ($queue as $row) {
            $versions[(int) $row['ContentID']] = $row['SubmittedVersionID'] ? mkt_content_version_get((int) $row['SubmittedVersionID']) : null;
        }
        $names = mkt_user_names(array_column($queue, 'SubmittedBy'));
        $me = marketing_user_id();
        ?>
        <p class="form-hint">
          Compliance clears first, then editorial. You can't review a version you wrote or submitted.
          <?= $isCompliance ? 'Your role grants compliance review.' : '' ?>
          <?= $isEditor ? 'You can give editorial approval.' : '' ?>
        </p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Title</th><th>Type</th><th>Version</th><th>Claims score</th><th>Submitted</th><th>Waiting on</th></tr></thead>
            <tbody>
              <?php if ($queue === []): ?>
              <tr><td colspan="6">Nothing is waiting for review.</td></tr>
              <?php endif; ?>
              <?php foreach ($queue as $row): ?>
              <?php
              $gate = mkt_content_pending_gate($row);
              $version = $versions[(int) $row['ContentID']];
              $ownWork = $me !== null && in_array($me, [(int) ($version['CreatedBy'] ?? 0), (int) $row['SubmittedBy']], true);
              $waiting = $gate === 'compliance' ? 'Compliance' : 'Editorial';
              $mine = !$ownWork && ($gate === 'compliance' ? $isCompliance : $isEditor);
              ?>
              <tr>
                <td><a href="/marketing/content/view.php?id=<?= (int) $row['ContentID'] ?>"><strong><?= htmlspecialchars((string) $row['Title']) ?></strong></a></td>
                <td><?= htmlspecialchars(mkt_content_type_label((string) $row['ContentType'])) ?></td>
                <td><?= $version ? 'v' . (int) $version['VersionNo'] . ' · ' . number_format((int) $version['WordCount']) . ' words' : '—' ?></td>
                <td><?= mkt_content_score_badge($version) ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime($row['SubmittedAt'] ?? null)) ?><?= isset($names[(int) $row['SubmittedBy']]) ? '<div class="form-hint">' . htmlspecialchars((string) $names[(int) $row['SubmittedBy']]) . '</div>' : '' ?></td>
                <td><?= $mine ? '<a class="btn-secondary" href="/marketing/content/view.php?id=' . (int) $row['ContentID'] . '#review">Review (' . $waiting . ')</a>' : htmlspecialchars($waiting) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'new' && $canCreate): ?>
        <?php
        $accepted = mkt_topics_list(['statuses' => ['accepted'], 'order' => 'recent'], 200);
        $products = mkt_products_list();
        $keywords = mkt_keywords_list();
        $owners = mkt_content_owner_options();
        $form = [
            'title'              => (string) ($_POST['title'] ?? ''),
            'content_type'       => (string) ($_POST['content_type'] ?? $_GET['type'] ?? 'article'),
            'topic_id'           => (string) ($_POST['topic_id'] ?? $_GET['topic_id'] ?? ''),
            'product_id'         => (string) ($_POST['product_id'] ?? $_GET['product_id'] ?? ''),
            'keyword_id'         => (string) ($_POST['keyword_id'] ?? ''),
            'primary_keyword'    => (string) ($_POST['primary_keyword'] ?? ''),
            'secondary_keywords' => (string) ($_POST['secondary_keywords'] ?? ''),
            'audience'           => (string) ($_POST['audience'] ?? ''),
            'target_words'       => (string) ($_POST['target_words'] ?? ''),
            'target_url'         => (string) ($_POST['target_url'] ?? ''),
            'owner_user_id'      => (string) ($_POST['owner_user_id'] ?? (string) marketing_user_id()),
            'due_date'           => (string) ($_POST['due_date'] ?? ''),
            'notes'              => (string) ($_POST['notes'] ?? ''),
        ];
        ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>?tab=new">
          <input type="hidden" name="action" value="create" />
          <?php marketing_render_field_guide_link('content-new'); ?>
          <div class="form-grid">
            <div class="form-group form-grid-full"><label for="title">Working title</label><input class="form-input" id="title" name="title" maxlength="300" value="<?= htmlspecialchars($form['title']) ?>" placeholder="Defaults to the topic title" /></div>
            <div class="form-group">
              <label for="content_type">Type</label>
              <select class="form-input" id="content_type" name="content_type">
                <?php foreach ($types as $key => $type): ?>
                <option value="<?= htmlspecialchars($key) ?>" <?= $form['content_type'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($type['label']) ?> (~<?= number_format($type['words']) ?> words)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="audience">Audience</label>
              <select class="form-input" id="audience" name="audience">
                <option value="">Topic's audience</option>
                <?php foreach (MKT_CLAIM_AUDIENCES as $key => $label): ?>
                <option value="<?= $key ?>" <?= $form['audience'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group form-grid-full">
              <label for="topic_id">Topic</label>
              <select class="form-input" id="topic_id" name="topic_id">
                <option value="">None</option>
                <?php foreach ($accepted as $row): ?>
                <option value="<?= (int) $row['TopicID'] ?>" <?= $form['topic_id'] === (string) $row['TopicID'] ? 'selected' : '' ?>>#<?= (int) $row['TopicID'] ?> <?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 140, '…')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="product_id">Product</label>
              <select class="form-input" id="product_id" name="product_id">
                <option value="">None</option>
                <?php foreach ($products as $row): ?>
                <option value="<?= (int) $row['ProductID'] ?>" <?= $form['product_id'] === (string) $row['ProductID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['Name']) ?> (<?= (int) $row['ApprovedClaims'] ?> approved claims)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="keyword_id">Keyword list</label>
              <select class="form-input" id="keyword_id" name="keyword_id">
                <option value="">None</option>
                <?php foreach ($keywords as $row): ?>
                <option value="<?= (int) $row['KeywordID'] ?>" <?= $form['keyword_id'] === (string) $row['KeywordID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['Keyword']) ?><?= $row['Cluster'] ? ' — ' . htmlspecialchars((string) $row['Cluster']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <p class="form-hint form-grid-full">Link an accepted topic (its evidence and linked claims feed the brief), a product (all its approved claims become usable), or both. A product page needs a product.</p>
            <div class="form-group"><label for="primary_keyword">Primary keyword</label><input class="form-input" id="primary_keyword" name="primary_keyword" maxlength="200" value="<?= htmlspecialchars($form['primary_keyword']) ?>" placeholder="Defaults to the chosen keyword" /></div>
            <div class="form-group"><label for="secondary_keywords">Secondary keywords</label><input class="form-input" id="secondary_keywords" name="secondary_keywords" maxlength="1000" value="<?= htmlspecialchars($form['secondary_keywords']) ?>" placeholder="Comma separated" /></div>
            <div class="form-group"><label for="target_words">Target words</label><input class="form-input" type="number" id="target_words" name="target_words" min="200" max="5000" value="<?= htmlspecialchars($form['target_words']) ?>" placeholder="Type default" /></div>
            <div class="form-group"><label for="due_date">Due</label><input class="form-input" type="date" id="due_date" name="due_date" value="<?= htmlspecialchars($form['due_date']) ?>" /></div>
            <div class="form-group">
              <label for="owner_user_id">Owner</label>
              <select class="form-input" id="owner_user_id" name="owner_user_id">
                <?php foreach ($owners as $row): ?>
                <option value="<?= (int) $row['UserID'] ?>" <?= $form['owner_user_id'] === (string) $row['UserID'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $row['UserName']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group"><label for="target_url">Planned URL</label><input class="form-input" type="url" id="target_url" name="target_url" maxlength="1000" value="<?= htmlspecialchars($form['target_url']) ?>" placeholder="Optional, e.g. <?= htmlspecialchars(rtrim((string) marketing_setting('brand.site_url', ''), '/')) ?>/learn/…" /></div>
            <div class="form-group form-grid-full"><label for="notes">Notes for the brief</label><textarea class="form-input" id="notes" name="notes" rows="3" maxlength="2000" placeholder="Optional — a point to lead with, a question practitioners keep asking, pages to link to"><?= htmlspecialchars($form['notes']) ?></textarea></div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary" name="brief_now" value="1">Create and write the brief</button>
            <button type="submit" class="btn-secondary">Create only</button>
          </div>
          <p class="form-hint">The AI brief takes under a minute (usually a few cents). You can edit it before approving.</p>
        </form>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
