<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-tasks.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-performance');

$baseHref = '/marketing/performance/inbox.php';
$tabs = [
    'action'    => 'Needs action',
    'escalated' => 'With compliance',
    'done'      => 'Replied & closed',
    'all'       => 'All',
    'add'       => 'Add a response',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'action';
$canUpdate = marketing_can_update();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'add') {
    marketing_require_update();
    $created = mkt_response_create($_POST);
    if (!$created['ok']) {
        $_GET['error'] = $created['error'];
        $tab = 'add';
    } else {
        $id = (int) $created['id'];
        $triage = process_execute('engagement-triage', ['response_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
        mkt_response_notify_escalations();
        $row = mkt_response_get($id);
        $notice = 'Response saved' . ($created['redacted'] ? ' (' . $created['redacted'] . ' handle/email/phone removed)' : '') . '. ';
        if ($row !== null && $row['Triage'] !== null) {
            $notice .= 'Triage: ' . mkt_response_triage_info((string) $row['Triage'])['label']
                . ($row['Status'] === 'escalated' ? ' — sent to compliance.' : '.');
        } else {
            $notice .= 'Triage did not finish (' . ($triage['error'] ?? ($row['TriageError'] ?? 'unknown')) . ') — label it by hand.';
        }
        marketing_redirect('/marketing/performance/response.php', ['id' => $id, 'notice' => $notice]);
    }
}

if ($canUpdate) {
    mkt_response_notify_escalations();
}
$filters = [
    'tab'      => $tab,
    'triage'   => (string) ($_GET['triage'] ?? ''),
    'asset_id' => (int) ($_GET['asset_id'] ?? 0),
    'q'        => trim((string) ($_GET['q'] ?? '')),
];
$counts = mkt_response_counts();
$channels = mkt_response_channels();
$back = ['href' => '/marketing/performance/', 'label' => 'Back to Engagement & Performance'];

$pageTitle = 'Response Inbox | NutraAxis Operations';
$pageDescription = 'Comments, replies, DMs and email replies to campaign posts, triaged with compliance escalation.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$tabLabels = $tabs;
$tabLabels['action'] .= ' (' . $counts['Action'] . ')';
$tabLabels['escalated'] .= ' (' . $counts['Escalated'] . ')';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Response Inbox',
          'lead'       => 'Comments, replies, DMs and email replies to our posts and emails. Each one is triaged by AI (with keyword rules for conditions, drugs and reactions); claims-risk and possible adverse events go to compliance with a ' . mkt_response_escalation_hours() . '-hour clock. People write every reply.',
          'permission' => auth_module_permission_label('marketing-performance'),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Inbox</strong>
          <p>
            <?= number_format($counts['Action']) ?> need action ·
            <?= number_format($counts['AwaitingReply']) ?> waiting for a reply ·
            <?= number_format($counts['Escalated']) ?> with compliance<?= $counts['Overdue'] ? ' (<strong style="color:var(--danger)">' . $counts['Overdue'] . ' overdue</strong>)' : '' ?> ·
            <?= number_format($counts['Untriaged']) ?> waiting for triage.
          </p>
          <p class="form-hint">GoHighLevel is not connected yet, so responses are added by hand. Store the text only — @handles, emails and phone numbers are stripped before saving and before any AI call, but names are not, so leave them out; the link goes back to the original.</p>
        </div>
      </div>

      <?php if ($tab === 'add'): ?>
        <?php if (!$canUpdate): ?>
        <p class="form-hint">You need Marketing update access to add responses.</p>
        <?php else: ?>
        <?php $assets = mkt_engagement_assets(); ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref . '?tab=add') ?>">
          <input type="hidden" name="action" value="add" />
          <?php marketing_render_field_guide_link('response-add'); ?>
          <div class="form-grid">
          <div class="form-group">
            <label for="asset_id">In response to</label>
            <select class="form-input" id="asset_id" name="asset_id">
              <option value="">— not linked to a post —</option>
              <?php foreach ($assets as $assetId => $label): ?>
              <option value="<?= (int) $assetId ?>"<?= (int) ($_POST['asset_id'] ?? $_GET['asset_id'] ?? 0) === $assetId ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="channel">Channel</label>
            <select class="form-input" id="channel" name="channel" required>
              <?php foreach ($channels as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>"<?= (string) ($_POST['channel'] ?? '') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="kind">Kind</label>
            <select class="form-input" id="kind" name="kind" required>
              <?php foreach (MKT_RESPONSE_KINDS as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>"<?= (string) ($_POST['kind'] ?? 'comment') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="received_at">Received (Central)</label>
            <input class="form-input" type="datetime-local" id="received_at" name="received_at" value="<?= htmlspecialchars((string) ($_POST['received_at'] ?? mkt_cal_now_local()->format('Y-m-d\TH:i'))) ?>" />
          </div>
          <div class="form-group">
            <label for="url">Link to it</label>
            <input class="form-input" type="url" id="url" name="url" maxlength="1000" value="<?= htmlspecialchars((string) ($_POST['url'] ?? '')) ?>" placeholder="https://… (optional)" />
          </div>
          <div class="form-group form-grid-full">
            <label for="text">Text</label>
            <textarea class="form-input" id="text" name="text" rows="5" maxlength="4000" required placeholder="Paste only what they wrote — leave out their name."><?= htmlspecialchars((string) ($_POST['text'] ?? '')) ?></textarea>
          </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary">Save and triage</button>
          </div>
        </form>
        <?php endif; ?>

      <?php else: ?>
        <?php $rows = mkt_responses_list($filters); ?>
        <form class="po-filter audit-filter page-list-filters" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
          <div class="audit-filter-grid">
            <div>
              <label for="f_triage">Label</label>
              <select class="form-input" id="f_triage" name="triage">
                <option value="">Any</option>
                <?php foreach (MKT_RESPONSE_TRIAGE as $key => $info): ?>
                <option value="<?= htmlspecialchars($key) ?>"<?= $filters['triage'] === $key ? ' selected' : '' ?>><?= htmlspecialchars($info[0]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Words in the response" /></div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($baseHref . '?tab=' . $tab) ?>">Clear</a>
          </div>
        </form>

        <?php if ($rows === []): ?>
        <p class="form-hint"><?= $counts['Total'] === 0 ? 'No responses yet. When a post gets a comment, reply, DM or email reply, add it with "Add a response".' : 'Nothing here.' ?></p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>#</th><th>Received</th><th>Channel</th><th>In response to</th><th>Text</th><th>Label</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
              <tr>
                <td><a href="/marketing/performance/response.php?id=<?= (int) $row['ResponseID'] ?>">#<?= (int) $row['ResponseID'] ?></a></td>
                <td><?= htmlspecialchars(mkt_cal_format($row['ReceivedAt'], 'M j, g:i A')) ?></td>
                <td><?= htmlspecialchars($channels[$row['Channel']] ?? (string) $row['Channel']) ?><br /><span class="form-hint"><?= htmlspecialchars(MKT_RESPONSE_KINDS[$row['Kind']] ?? (string) $row['Kind']) ?></span></td>
                <td><?= $row['AssetID'] ? '<a href="/marketing/campaigns/asset.php?id=' . (int) $row['AssetID'] . '">Asset #' . (int) $row['AssetID'] . '</a><br /><span class="form-hint">' . htmlspecialchars(mb_strimwidth((string) $row['CampaignName'], 0, 50, '…')) . '</span>' : '—' ?></td>
                <td><a href="/marketing/performance/response.php?id=<?= (int) $row['ResponseID'] ?>"><?= htmlspecialchars(mb_strimwidth((string) $row['BodyText'], 0, 160, '…')) ?></a></td>
                <td><?= mkt_response_triage_badge($row['Triage']) ?><?= $row['TriageSource'] === 'rule' ? '<br /><span class="form-hint">keyword rule</span>' : '' ?></td>
                <td>
                  <?= htmlspecialchars(MKT_RESPONSE_STATUSES[$row['Status']] ?? (string) $row['Status']) ?>
                  <?php if ($row['Status'] === 'escalated'): ?>
                  <br /><span class="form-hint"<?= (int) $row['Overdue'] ? ' style="color:var(--danger);font-weight:600"' : '' ?>><?= (int) $row['Overdue'] ? 'Overdue — was due ' : 'Due ' ?><?= htmlspecialchars(mkt_cal_format($row['EscalationDueAt'], 'M j, g:i A')) ?></span>
                  <?php elseif ($row['Status'] === 'new' && $row['TriageError']): ?>
                  <br /><span class="form-hint" style="color:var(--danger)">Triage failed<?= (int) $row['TriageAttempts'] >= 3 ? ' — label by hand' : ', will retry' ?></span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
