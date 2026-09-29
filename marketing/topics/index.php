<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-topics.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('research-topics');

$activeSlug = 'research-topics';
$baseHref = '/marketing/topics/';
$tabs = [
    'board'    => 'Board',
    'accepted' => 'Accepted',
    'emerging' => 'Emerging interests',
    'parked'   => 'Parked & rejected',
    'items'    => 'Scored items',
    'runs'     => 'Scoring runs',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'board';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $returnTab = array_key_exists((string) ($_POST['tab'] ?? ''), $tabs) ? (string) $_POST['tab'] : 'board';

    if ($action === 'run_score' || $action === 'run_cluster') {
        $code = $action === 'run_score' ? 'research-score-batch' : 'research-cluster-topics';
        $result = process_execute($code, ['force' => 1], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
        marketing_redirect($baseHref, !empty($result['ok'])
            ? ['tab' => $returnTab, 'notice' => (string) ($result['message'] ?? 'Job completed.')]
            : ['tab' => $returnTab, 'error' => (string) ($result['error'] ?? 'Job failed.')]);
    }
    if (in_array($action, ['park', 'reject', 'reopen'], true)) {
        $decision = ['park' => 'parked', 'reject' => 'rejected', 'reopen' => 'proposed'][$action];
        $result = mkt_topic_decide((int) ($_POST['topic_id'] ?? 0), $decision);
        marketing_redirect($baseHref, $result['ok']
            ? ['tab' => $returnTab, 'notice' => 'Topic ' . strtolower(MKT_TOPIC_STATUSES[$decision]) . '.']
            : ['tab' => $returnTab, 'error' => $result['error']]);
    }
}

$interests = mkt_interests_list();
$interestNames = array_column($interests, 'Name', 'InterestID');
$areas = marketing_setting_lines('taxonomy.therapeutic_areas');
$filters = [
    'interest_id' => (string) ($_GET['interest_id'] ?? ''),
    'area'        => (string) ($_GET['area'] ?? ''),
    'evidence'    => (string) ($_GET['evidence'] ?? ''),
    'status'      => (string) ($_GET['status'] ?? ''),
    'q'           => trim((string) ($_GET['q'] ?? '')),
];
$topicTabs = [
    'board'    => ['statuses' => ['proposed']],
    'accepted' => ['statuses' => ['accepted'], 'order' => 'recent'],
    'emerging' => ['statuses' => ['proposed', 'parked'], 'emerging' => 1],
    'parked'   => ['statuses' => ['parked', 'rejected'], 'order' => 'recent'],
];
$topics = isset($topicTabs[$tab]) ? mkt_topics_list($topicTabs[$tab] + $filters) : [];
$counts = mkt_topic_status_counts();
$pipeline = mkt_topic_pipeline_counts();
$canUpdate = marketing_can_update();

$pageTitle = 'Topic Synthesis | NutraAxis Operations';
$pageDescription = 'AI-scored research grouped into candidate topics; accept a topic and set its angle.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$select = static function (string $name, array $options, string $current, string $id = ''): void {
    echo '<select class="form-input" id="' . htmlspecialchars($id ?: $name) . '" name="' . htmlspecialchars($name) . '">';
    foreach ($options as $key => $label) {
        echo '<option value="' . htmlspecialchars((string) $key) . '"' . ((string) $key === $current ? ' selected' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    echo '</select>';
};
$tabLabels = $tabs;
$tabLabels['board'] .= ' (' . $counts['proposed'] . ')';
$tabLabels['accepted'] .= ' (' . $counts['accepted'] . ')';
$tabLabels['emerging'] .= ' (' . $counts['emerging'] . ')';
$tabLabels['parked'] .= ' (' . ($counts['parked'] + $counts['rejected']) . ')';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Topic Synthesis',
          'lead'       => 'Harvested items are AI-scored against each interest, then grouped into candidate topics. Accept a topic by writing its angle — that brief is what Campaign Studio writes from.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Pipeline</strong>
          <p>
            <?= number_format($pipeline['Waiting'] ?? 0) ?> waiting to score ·
            <?= number_format($pipeline['InBatch'] ?? 0) ?> in a scoring batch ·
            <?= number_format($pipeline['Scored'] ?? 0) ?> relevant, not yet in a topic ·
            <?= number_format($pipeline['InTopics'] ?? 0) ?> in topics ·
            <?= number_format($pipeline['Discarded'] ?? 0) ?> below the relevance threshold (<?= htmlspecialchars((string) marketing_setting('research.relevance_threshold', '0.6')) ?>)
          </p>
        </div>
        <?php if ($canUpdate): ?>
        <div>
          <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
            <input type="hidden" name="action" value="run_score" /><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
            <button type="submit" class="btn-secondary" title="Collect finished batches and submit every waiting item now">Score waiting items</button>
          </form>
          <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
            <input type="hidden" name="action" value="run_cluster" /><input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
            <button type="submit" class="btn-secondary" title="Group relevant items into topics now (Sonnet, a few cents)">Cluster topics now</button>
          </form>
        </div>
        <?php endif; ?>
      </div>

      <?php if (isset($topicTabs[$tab])): ?>
        <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
          <div class="audit-filter-grid">
            <div><label for="f_interest">Interest</label><?php $select('interest_id', ['' => 'All'] + $interestNames, $filters['interest_id'], 'f_interest'); ?></div>
            <div><label for="f_area">Therapeutic area</label><?php $select('area', ['' => 'All'] + array_combine($areas, $areas), $filters['area'], 'f_area'); ?></div>
            <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Title, summary, or angle" /></div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=<?= htmlspecialchars($tab) ?>">Clear</a>
          </div>
        </form>

        <?php if ($tab === 'emerging'): ?>
        <p class="form-hint">Themes the clustering run found that no watched interest covers. Create an interest to start harvesting and researching it, or park the topic.</p>
        <?php elseif ($tab === 'board'): ?>
        <p class="form-hint">Sorted by trend score: recent volume ×2, week-over-week growth, distinct sources ×1.5, and peer-reviewed or regulatory items.</p>
        <?php endif; ?>

        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Topic</th><th>Interest / area</th><th>Items (7d / prior 7d)</th><th>Sources</th><th>Evidence</th><th>Trend</th><th>Last item</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
              <?php if ($topics === []): ?>
              <tr><td colspan="9"><?= $tab === 'board' ? 'No proposed topics. Topics appear after items are scored and the daily clustering run groups them.' : 'No topics here.' ?></td></tr>
              <?php endif; ?>
              <?php foreach ($topics as $row): ?>
              <tr>
                <td>
                  <a href="/marketing/topics/view.php?id=<?= (int) $row['TopicID'] ?>"><strong><?= htmlspecialchars((string) $row['Title']) ?></strong></a>
                  <?php if (!empty($row['IsEmerging'])): ?> <span class="status-badge status-submitted">Emerging</span><?php endif; ?>
                  <?php if ($tab === 'accepted' && !empty($row['Angle'])): ?>
                  <div class="form-hint"><strong>Angle:</strong> <?= htmlspecialchars(mb_strimwidth((string) $row['Angle'], 0, 220, '…')) ?></div>
                  <?php elseif ($tab === 'emerging' && !empty($row['SuggestedInterestName'])): ?>
                  <div class="form-hint"><strong>Suggested interest:</strong> <?= htmlspecialchars((string) $row['SuggestedInterestName']) ?><?php
                      $terms = json_decode((string) ($row['SuggestedTermsJson'] ?? ''), true);
                      echo is_array($terms) && $terms !== [] ? ' — ' . htmlspecialchars(implode(', ', $terms)) : '';
                  ?></div>
                  <?php elseif (!empty($row['Summary'])): ?>
                  <div class="form-hint"><?= htmlspecialchars(mb_strimwidth((string) $row['Summary'], 0, 220, '…')) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($row['InterestName'] ?? '—')) ?><?php if (!empty($row['TherapeuticArea'])): ?><div class="form-hint"><?= htmlspecialchars((string) $row['TherapeuticArea']) ?></div><?php endif; ?></td>
                <td><?= (int) $row['ItemCount'] ?> (<?= (int) $row['Items7d'] ?> / <?= (int) $row['ItemsPrior7d'] ?>)</td>
                <td><?= (int) $row['SourceDiversity'] ?></td>
                <td><?= (int) $row['EvidenceCount'] ?></td>
                <td><?= htmlspecialchars(number_format((float) $row['TrendScore'], 1)) ?></td>
                <td><?= htmlspecialchars(marketing_format_date($row['LastItemAt'] ?? null)) ?></td>
                <td><?= mkt_render_badge((string) $row['Status'] === 'accepted' ? 'active' : (string) $row['Status'], ['active' => 'Accepted'] + MKT_TOPIC_STATUSES) ?></td>
                <td>
                  <a href="/marketing/topics/view.php?id=<?= (int) $row['TopicID'] ?>"><?= $canUpdate ? 'Review' : 'View' ?></a>
                  <?php if ($canUpdate): ?>
                    <?php if ($tab === 'emerging' && marketing_can_create()): ?>
                    · <a href="/marketing/interests/edit.php?from_topic=<?= (int) $row['TopicID'] ?>">Create interest</a>
                    <?php endif; ?>
                    <?php foreach (($row['Status'] === 'proposed' ? ['park' => 'Park', 'reject' => 'Reject'] : ($row['Status'] === 'accepted' ? [] : ['reopen' => 'Reopen'])) as $act => $label): ?>
                    <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                      <input type="hidden" name="action" value="<?= $act ?>" />
                      <input type="hidden" name="topic_id" value="<?= (int) $row['TopicID'] ?>" />
                      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
                      · <button type="submit" class="btn-text"><?= $label ?></button>
                    </form>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'items'): ?>
        <?php $items = mkt_scored_items($filters); ?>
        <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="tab" value="items" />
          <div class="audit-filter-grid">
            <div><label for="f_status">Outcome</label><?php $select('status', ['' => 'All', 'scored' => 'Relevant (not in a topic)', 'clustered' => 'In a topic', 'discarded' => 'Below threshold'], $filters['status'], 'f_status'); ?></div>
            <div><label for="f_interest">Interest (≥ 0.5)</label><?php $select('interest_id', ['' => 'All'] + $interestNames, $filters['interest_id'], 'f_interest'); ?></div>
            <div><label for="f_evidence">Evidence</label><?php $select('evidence', ['' => 'All'] + MKT_ITEM_EVIDENCE_TYPES, $filters['evidence'], 'f_evidence'); ?></div>
            <div><label for="f_area">Therapeutic area</label><?php $select('area', ['' => 'All'] + array_combine($areas, $areas), $filters['area'], 'f_area'); ?></div>
            <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Title, AI summary, product or keyword" /></div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?tab=items">Clear</a>
          </div>
        </form>
        <p class="form-hint"><?= count($items) ?> most recently scored item(s). Relevance is the highest score across interests (0–1).</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Item</th><th>Relevance</th><th>Top interest</th><th>Evidence</th><th>Products / keywords</th><th>Published</th><th>Outcome</th></tr></thead>
            <tbody>
              <?php if ($items === []): ?>
              <tr><td colspan="7">No scored items match.</td></tr>
              <?php endif; ?>
              <?php foreach ($items as $row): ?>
              <?php $tags = mkt_item_tags($row['TagsJson'] ?? null); ?>
              <tr>
                <td>
                  <a href="<?= htmlspecialchars((string) $row['Url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 140, '…')) ?></a>
                  <div class="form-hint"><?= htmlspecialchars((string) ($row['Domain'] ?? '')) ?><?= !empty($row['AiSummary']) ? ' — ' . htmlspecialchars((string) $row['AiSummary']) : '' ?></div>
                </td>
                <td><?= htmlspecialchars(number_format((float) $row['RelevanceMax'], 2)) ?></td>
                <td><?= htmlspecialchars((string) ($row['PrimaryInterestName'] ?? '—')) ?></td>
                <td><?= htmlspecialchars(MKT_ITEM_EVIDENCE_TYPES[(string) ($row['EvidenceType'] ?? '')] ?? '—') ?><?= !empty($row['StudyJson']) ? ' <span class="form-hint" title="Study facts extracted">(study)</span>' : '' ?></td>
                <td><?= htmlspecialchars(implode(', ', array_merge($tags['products'], $tags['keywords']))) ?></td>
                <td><?= htmlspecialchars(marketing_format_date($row['PublishedAt'] ?? null)) ?></td>
                <td>
                  <?php if (!empty($row['TopicID'])): ?><a href="/marketing/topics/view.php?id=<?= (int) $row['TopicID'] ?>">In topic #<?= (int) $row['TopicID'] ?></a>
                  <?php else: ?><?= mkt_render_badge((string) $row['Status'], MKT_ITEM_STATUSES) ?><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php else: ?>
        <?php $batches = mkt_ai_batches(); ?>
        <h2 class="hub-section-title">Scoring batches</h2>
        <p class="form-hint">Items are scored through the Anthropic Message Batches API at half the realtime price; batches usually finish within the hour and are collected by the next hourly run.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Submitted</th><th>Model</th><th>Items</th><th>Scored</th><th>Returned to queue</th><th>Cost</th><th>Status</th><th>Collected</th><th>Note</th></tr></thead>
            <tbody>
              <?php if ($batches === []): ?>
              <tr><td colspan="10">No scoring batches yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($batches as $row): ?>
              <tr>
                <td><?= (int) $row['BatchID'] ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime($row['SubmittedAt'] ?? null)) ?></td>
                <td><?= htmlspecialchars((string) ($row['Model'] ?? '')) ?></td>
                <td><?= (int) $row['ItemCount'] ?></td>
                <td><?= $row['SucceededCount'] !== null ? (int) $row['SucceededCount'] : '—' ?></td>
                <td><?= $row['ErroredCount'] !== null ? (int) $row['ErroredCount'] : '—' ?></td>
                <td><?= $row['CostUsd'] !== null ? htmlspecialchars(marketing_format_usd((float) $row['CostUsd'], 3)) : '—' ?></td>
                <td><?= mkt_render_badge((string) $row['Status'] === 'collected' ? 'success' : ((string) $row['Status'] === 'submitted' ? 'running' : 'failed'), ['success' => 'Collected', 'running' => 'Processing (' . ($row['ExternalStatus'] ?? 'submitted') . ')', 'failed' => 'Failed']) ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime($row['CollectedAt'] ?? null)) ?></td>
                <td><?= htmlspecialchars((string) ($row['ErrorMessage'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php $runs = array_merge(marketing_jobs_recent(15, 'research-score-batch'), marketing_jobs_recent(10, 'research-cluster-topics'));
        usort($runs, static fn(array $a, array $b): int => (int) $b['ProcessExecutionLogID'] <=> (int) $a['ProcessExecutionLogID']); ?>
        <h2 class="hub-section-title">Recent job runs</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Started</th><th>Job</th><th>Trigger</th><th>Status</th><th>Result</th></tr></thead>
            <tbody>
              <?php if ($runs === []): ?>
              <tr><td colspan="5">No runs yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($runs as $row): ?>
              <tr>
                <td><?= htmlspecialchars(marketing_format_datetime($row['StartedAt'] ?? null)) ?></td>
                <td><?= htmlspecialchars((string) $row['ProcessName']) ?></td>
                <td><?= htmlspecialchars((string) ($row['TriggerType'] ?? '')) ?><?= !empty($row['TriggeredByUserName']) ? ' — ' . htmlspecialchars((string) $row['TriggeredByUserName']) : '' ?></td>
                <td><?= mkt_render_badge(strtolower((string) $row['Status'])) ?></td>
                <td><?= htmlspecialchars((string) ($row['ResultMessage'] ?: ($row['ErrorMessage'] ?? ''))) ?></td>
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
