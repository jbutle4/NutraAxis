<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-performance');

$id = (int) ($_GET['id'] ?? 0);
$digest = mkt_digest_get($id);
if ($digest === null) {
    marketing_redirect('/marketing/performance/', ['tab' => 'digests', 'error' => 'That digest was not found.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'regenerate') {
    marketing_require_update();
    $result = process_execute('engagement-digest', ['period_end' => $digest['PeriodEndText'], 'force' => 1], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
    if (!empty($result['ok']) && !empty($result['digest_id'])) {
        marketing_redirect('/marketing/performance/digest.php', ['id' => (int) $result['digest_id'], 'notice' => (string) ($result['message'] ?? 'Regenerated.')]);
    }
    marketing_redirect('/marketing/performance/digest.php', ['id' => $id, 'error' => (string) ($result['error'] ?? $result['message'] ?? 'The digest could not be regenerated.')]);
}

$canUpdate = marketing_can_update();
$facts = $digest['facts'];
$recs = $digest['recommendations'];
$tasks = mkt_digest_tasks($id);
$period = marketing_format_date($digest['PeriodStartText']) . ' – ' . marketing_format_date($digest['PeriodEndText']);

$findFact = static function (string $list, string $refId) use ($facts): ?array {
    foreach ($facts[$list] ?? [] as $row) {
        if ((string) ($row['id'] ?? '') === $refId) {
            return $row;
        }
    }
    return null;
};
$evidence = static function (array $ref, bool $withMeta = true) use ($findFact): string {
    $type = (string) ($ref['type'] ?? '');
    $refId = (string) ($ref['id'] ?? '');
    [$list, $nameKey, $href] = match ($type) {
        'asset'    => ['assets', 'title', '/marketing/campaigns/asset.php?id=' . rawurlencode($refId)],
        'campaign' => ['campaigns', 'name', '/marketing/campaigns/campaign.php?id=' . rawurlencode($refId)],
        'topic'    => ['topics', 'title', '/marketing/topics/view.php?id=' . rawurlencode($refId)],
        'interest' => ['interests', 'name', '/marketing/interests/edit.php?id=' . rawurlencode($refId)],
        'content'  => ['content', 'title', '/marketing/content/view.php?id=' . rawurlencode($refId)],
        'page'     => ['pages_with_issues', 'path', '/marketing/pages/view.php?id=' . rawurlencode($refId)],
        'query'    => ['unmapped_queries', 'id', '/marketing/performance/?tab=search'],
        default    => ['', '', '/marketing/performance/'],
    };
    $row = $list !== '' ? $findFact($list, $refId) : null;
    $label = ucfirst($type) . ' ' . ($type === 'query' ? '"' . $refId . '"' : '#' . $refId);
    if ($row !== null && $type !== 'query' && trim((string) ($row[$nameKey] ?? '')) !== '') {
        $label .= ' ' . mb_strimwidth((string) $row[$nameKey], 0, 60, '…');
    }
    $meta = [];
    if ($row !== null && isset($row['score']) && $row['score'] !== null) {
        $meta[] = 'score ' . number_format((float) $row['score'], 0);
    }
    if ($row !== null && !empty($row['confidence'])) {
        $meta[] = strtolower(MKT_SCORE_CONFIDENCE[$row['confidence']] ?? (string) $row['confidence']);
    }
    if ($type === 'query' && $row !== null) {
        $meta[] = number_format((float) $row['impressions']) . ' impressions';
    }

    return '<a href="' . htmlspecialchars($href) . '">' . htmlspecialchars($label) . '</a>'
        . ($withMeta && $meta ? ' <span class="form-hint">(' . htmlspecialchars(implode(', ', $meta)) . ')</span>' : '');
};
$recTypes = [
    'double_down'      => 'Double down',
    'follow_up_series' => 'Follow-up series',
    'new_campaign'     => 'New campaign',
    'retire_interest'  => 'Retire interest',
    'add_source'       => 'Add source',
    'fix_page'         => 'Fix page',
    'map_keyword'      => 'Map keyword',
    'reply_backlog'    => 'Reply backlog',
    'other'            => 'Other',
];

$pageTitle = 'Digest ' . $period . ' | NutraAxis Operations';
$pageDescription = 'Weekly engagement digest and recommended actions.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/performance/?tab=digests',
          'back_label' => 'Back to Weekly digests',
          'category'   => 'Marketing & Research',
          'title'      => 'Digest: ' . $period,
          'lead'       => $digest['Status'] === 'sparse'
              ? 'Not enough data this week for recommendations.'
              : 'What happened last week and what to do next. Every recommendation cites scored items from the facts below; each one is a task in the Task queue.',
          'permission' => auth_module_permission_label('marketing-performance'),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

      <div class="admin-form-card">
        <div class="mkt-article"><?= mkt_markdown_html((string) $digest['Narrative']) ?></div>
        <p class="form-hint">
          Created <?= htmlspecialchars(mkt_cal_format($digest['CreatedAtText'])) ?><?= $digest['CreatedByName'] ? ' by ' . htmlspecialchars((string) $digest['CreatedByName']) : ' (scheduled)' ?>
          <?= $digest['Model'] ? ' · ' . htmlspecialchars((string) $digest['Model']) . ' (prompt v' . (int) $digest['PromptVersion'] . ')' : '' ?>
          <?= $digest['CostUsd'] !== null ? ' · $' . number_format((float) $digest['CostUsd'], 3) : '' ?>
        </p>
        <?php if ($canUpdate): ?>
        <form method="post" action="/marketing/performance/digest.php?id=<?= $id ?>" onsubmit="return confirm('Regenerate this digest? Its open tasks are cancelled and replaced.');">
          <input type="hidden" name="action" value="regenerate" />
          <button type="submit" class="btn-secondary">Regenerate</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if ($recs !== []): ?>
      <h2 class="hub-section-title">Recommendations</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Action</th><th>Recommendation</th><th>Evidence</th><th>Task</th></tr></thead>
          <tbody>
            <?php foreach ($recs as $index => $rec): ?>
            <?php $task = $tasks[$index] ?? null; ?>
            <tr>
              <td><?= htmlspecialchars($recTypes[$rec['type']] ?? (string) $rec['type']) ?><br /><span class="form-hint"><?= htmlspecialchars(MKT_TASK_ROLES[$rec['role']] ?? (string) $rec['role']) ?></span></td>
              <td><strong><?= htmlspecialchars((string) $rec['title']) ?></strong><?php if (!empty($rec['detail'])): ?><br /><?= htmlspecialchars((string) $rec['detail']) ?><?php endif; ?></td>
              <td><?= implode('<br />', array_map($evidence, $rec['refs'] ?? [])) ?></td>
              <td>
                <?php if ($task !== null): ?>
                <a href="/marketing/tasks/?tab=<?= $task['Status'] === 'open' ? 'open' : 'done' ?>">#<?= (int) $task['TaskID'] ?></a>
                <?= htmlspecialchars(MKT_TASK_STATUSES[$task['Status']] ?? (string) $task['Status']) ?>
                <?php if ($task['Status'] === 'open' && $task['DueDate']): ?><br /><span class="form-hint">due <?= htmlspecialchars(marketing_format_date($task['DueDate'])) ?></span><?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($digest['dropped'] !== []): ?>
      <details style="margin-top:1rem">
        <summary><?= count($digest['dropped']) ?> suggestion<?= count($digest['dropped']) === 1 ? '' : 's' ?> left out</summary>
        <ul>
          <?php foreach ($digest['dropped'] as $rec): ?>
          <li><?= htmlspecialchars((string) ($rec['title'] ?? '(untitled)')) ?> <span class="form-hint">— <?= htmlspecialchars((string) ($rec['reason'] ?? '')) ?></span></li>
          <?php endforeach; ?>
        </ul>
      </details>
      <?php endif; ?>

      <?php if ($facts !== []): ?>
      <details style="margin-top:1rem">
        <summary>Facts the digest was based on</summary>
        <?php
        $search = $facts['search'] ?? [];
        $visits = $facts['site_visits'] ?? [];
        $resp = $facts['responses'] ?? [];
        ?>
        <dl class="detail-list detail-list-inline">
          <dt>Search</dt><dd><?= number_format((float) ($search['clicks'] ?? 0)) ?> clicks, <?= number_format((float) ($search['impressions'] ?? 0)) ?> impressions (prior week <?= number_format((float) ($search['prior_clicks'] ?? 0)) ?> / <?= number_format((float) ($search['prior_impressions'] ?? 0)) ?>)</dd>
          <dt>Site visits</dt><dd><?= number_format((float) ($visits['sessions'] ?? 0)) ?> sessions (prior week <?= number_format((float) ($visits['prior_sessions'] ?? 0)) ?>), <?= number_format((float) ($visits['key_events'] ?? 0)) ?> key events, <?= number_format((float) ($visits['purchases'] ?? 0)) ?> purchases</dd>
          <dt>Responses</dt><dd><?= (int) ($resp['received'] ?? 0) ?> received, <?= (int) ($resp['awaiting_reply'] ?? 0) ?> awaiting reply, <?= (int) ($resp['escalated_open'] ?? 0) ?> with compliance</dd>
          <dt>Scored</dt><dd><?= count($facts['assets'] ?? []) ?> assets, <?= count($facts['campaigns'] ?? []) ?> campaigns, <?= count($facts['content'] ?? []) ?> content pieces, <?= count($facts['topics'] ?? []) ?> topics, <?= count($facts['interests'] ?? []) ?> interests</dd>
          <dt>Pages with issues</dt><dd><?= count($facts['pages_with_issues'] ?? []) ?> · Unmapped queries: <?= count($facts['unmapped_queries'] ?? []) ?></dd>
        </dl>
        <?php if (!empty($facts['assets'])): ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Top assets</th><th>Score</th><th>Confidence</th><th>Sessions</th><th>Interactions</th><th>Responses</th></tr></thead>
            <tbody>
              <?php foreach ($facts['assets'] as $row): ?>
              <tr>
                <td><?= $evidence(['type' => 'asset', 'id' => (string) $row['id']], false) ?></td>
                <td><?= number_format((float) $row['score'], 0) ?></td>
                <td><?= htmlspecialchars(MKT_SCORE_CONFIDENCE[$row['confidence']] ?? (string) $row['confidence']) ?></td>
                <td><?= number_format((float) $row['sessions']) ?></td>
                <td><?= number_format((float) $row['interactions']) ?></td>
                <td><?= number_format((float) $row['responses']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </details>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
