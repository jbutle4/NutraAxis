<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-campaigns.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-campaigns');

$activeSlug = 'marketing-campaigns';
$baseHref = '/marketing/campaigns/';
$tabs = [
    'campaigns' => 'Campaigns',
    'review'    => 'Review queue',
    'new'       => 'New campaign',
    'archived'  => 'Archived',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'campaigns';
$error = null;
$channels = mkt_channels();
$socialChannels = array_filter($channels, static fn(array $c): bool => $c['medium'] === 'social');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    marketing_require_create();
    $result = mkt_campaign_create($_POST);
    if ($result['ok']) {
        $notice = 'Campaign created.';
        if (!empty($_POST['generate_now'])) {
            $run = process_execute('campaign-generate', ['campaign_id' => $result['id']], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            marketing_redirect('/marketing/campaigns/campaign.php', !empty($run['ok'])
                ? ['id' => $result['id'], 'notice' => 'Campaign created. ' . (string) ($run['message'] ?? 'Content generated.')]
                : ['id' => $result['id'], 'error' => 'Campaign created, but generation failed: ' . (string) ($run['error'] ?? 'unknown error')]);
        }
        marketing_redirect('/marketing/campaigns/campaign.php', ['id' => $result['id'], 'notice' => $notice]);
    }
    $error = $result['error'];
    $tab = 'new';
}

$canCreate = marketing_can_create();
$counts = mkt_asset_status_counts();
$isCompliance = mkt_can_compliance_review();
$isEditor = mkt_can_editorial_review();
$reviewers = mkt_compliance_reviewers();

$pageTitle = 'Campaign Studio | NutraAxis Operations';
$pageDescription = 'Posts, series and emails generated from accepted topics, claims-checked and cleared by compliance and editorial review.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$tabLabels = $tabs;
$tabLabels['review'] .= ' (' . $counts['in_review'] . ')';
if (!$canCreate) {
    unset($tabLabels['new']);
}
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Campaign Studio',
          'lead'       => 'Generate a post, series or email from an accepted topic. Every asset is claims-checked; anything that makes or implies a claim goes to the compliance officer before editorial approval.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Assets</strong>
          <p>
            <?= $counts['draft'] + $counts['changes_requested'] ?> in drafting (<?= $counts['changes_requested'] ?> with changes requested) ·
            <?= $counts['compliance_pending'] ?> awaiting compliance ·
            <?= $counts['editorial_ready'] ?> awaiting editorial ·
            <?= $counts['approved'] ?> approved ·
            <?= $counts['scheduled'] ?> scheduled ·
            <?= $counts['posted'] ?> posted
          </p>
          <p class="form-hint">
            Compliance reviewers:
            <?php if ($reviewers === []): ?>
            <strong>none yet</strong> — in <a href="/site-admin/users/">Site Admin → Users</a>, assign the compliance officer the <strong>Marketing Compliance Reviewer</strong> role (or grant Marketing Compliance Review update on their role in <a href="/site-admin/roles/">Roles</a>).
            <?php else: ?>
            <?= htmlspecialchars(implode(', ', array_map(static fn(array $r): string => (string) $r['UserName'] . ' (' . $r['RoleName'] . ')' . (str_contains((string) ($r['Marketing'] ?? ''), 'U') ? '' : ' — role lacks Marketing update'), $reviewers))) ?>.
            <?php endif; ?>
          </p>
        </div>
      </div>

      <?php if ($tab === 'campaigns' || $tab === 'archived'): ?>
        <?php $campaigns = mkt_campaigns_list($tab === 'archived' ? ['status' => 'archived'] : []); ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Campaign</th><th>Format</th><th>Channels</th><th>Audience</th><th>Assets</th><th>Drafting</th><th>In review</th><th>Approved</th><th>Updated</th><th>Status</th></tr></thead>
            <tbody>
              <?php if ($campaigns === []): ?>
              <tr><td colspan="10"><?= $tab === 'archived' ? 'No archived campaigns.' : 'No campaigns yet. Start one from an accepted topic on the New campaign tab.' ?></td></tr>
              <?php endif; ?>
              <?php foreach ($campaigns as $row): ?>
              <tr>
                <td>
                  <a href="/marketing/campaigns/campaign.php?id=<?= (int) $row['CampaignID'] ?>"><strong><?= htmlspecialchars((string) $row['Name']) ?></strong></a>
                  <?php if (!empty($row['TopicTitle'])): ?><div class="form-hint">Topic: <a href="/marketing/topics/view.php?id=<?= (int) $row['TopicID'] ?>"><?= htmlspecialchars(mb_strimwidth((string) $row['TopicTitle'], 0, 120, '…')) ?></a></div><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(MKT_CAMPAIGN_FORMATS[(string) $row['Format']] ?? (string) $row['Format']) ?><?= (int) $row['PartCount'] > 1 ? ' (' . (int) $row['PartCount'] . ' parts)' : '' ?></td>
                <td><?= htmlspecialchars(implode(', ', array_map(static fn(string $k): string => $channels[$k]['label'] ?? $k, explode(',', (string) $row['Channels'])))) ?></td>
                <td><?= htmlspecialchars(MKT_CLAIM_AUDIENCES[(string) $row['Audience']] ?? '') ?></td>
                <td><?= (int) $row['AssetCount'] ?></td>
                <td><?= (int) $row['DraftCount'] ?></td>
                <td><?= (int) $row['ReviewCount'] ?></td>
                <td><?= (int) $row['ApprovedCount'] ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime($row['UpdatedAt'] ?? null)) ?></td>
                <td><?= mkt_render_badge((string) $row['Status'], MKT_CAMPAIGN_STATUSES) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'review'): ?>
        <?php $queue = mkt_review_queue(); $me = marketing_user_id(); ?>
        <p class="form-hint">
          Compliance clears first, then editorial. You can't review an asset you last edited or submitted.
          <?= $isCompliance ? 'Your role grants compliance review.' : '' ?>
          <?= $isEditor ? 'You can give editorial approval.' : '' ?>
        </p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Asset</th><th>Campaign</th><th>Claims score</th><th>Compliance</th><th>Editorial</th><th>Submitted</th><th>Waiting on</th></tr></thead>
            <tbody>
              <?php if ($queue === []): ?>
              <tr><td colspan="7">Nothing is waiting for review.</td></tr>
              <?php endif; ?>
              <?php foreach ($queue as $row): ?>
              <?php
              $compliancePending = $row['ComplianceStatus'] === 'pending';
              $ownWork = $me !== null && in_array($me, [(int) $row['UpdatedBy'], (int) $row['SubmittedBy']], true);
              $waiting = $compliancePending ? 'Compliance' : 'Editorial';
              $mine = !$ownWork && ($compliancePending ? $isCompliance : $isEditor);
              ?>
              <tr>
                <td>
                  <a href="/marketing/campaigns/asset.php?id=<?= (int) $row['AssetID'] ?>"><strong><?= htmlspecialchars((string) ($row['Title'] ?: 'Asset #' . $row['AssetID'])) ?></strong></a>
                  <div class="form-hint"><?= htmlspecialchars($channels[(string) $row['Channel']]['label'] ?? (string) $row['Channel']) ?> · part <?= (int) $row['SequenceNo'] ?></div>
                </td>
                <td><a href="/marketing/campaigns/campaign.php?id=<?= (int) $row['CampaignID'] ?>"><?= htmlspecialchars((string) $row['CampaignName']) ?></a></td>
                <td><?= $row['ClaimsScore'] !== null ? htmlspecialchars(number_format((float) $row['ClaimsScore'], 1)) : '—' ?></td>
                <td><?= htmlspecialchars(MKT_GATE_STATUSES[(string) $row['ComplianceStatus']] ?? '—') ?></td>
                <td><?= htmlspecialchars(MKT_GATE_STATUSES[(string) $row['EditorialStatus']] ?? '—') ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime($row['SubmittedAt'] ?? null)) ?><?= !empty($row['SubmittedByName']) ? '<div class="form-hint">' . htmlspecialchars((string) $row['SubmittedByName']) . '</div>' : '' ?></td>
                <td><?= $mine ? '<a class="btn-secondary" href="/marketing/campaigns/asset.php?id=' . (int) $row['AssetID'] . '#review">Review (' . $waiting . ')</a>' : htmlspecialchars($waiting) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'new' && $canCreate): ?>
        <?php
        $accepted = mkt_topics_list(['statuses' => ['accepted'], 'order' => 'recent'], 200);
        $form = [
            'topic_id'     => (string) ($_POST['topic_id'] ?? $_GET['topic_id'] ?? ''),
            'format'       => (string) ($_POST['format'] ?? 'post'),
            'channels'     => (array) ($_POST['channels'] ?? array_keys(array_intersect_key($socialChannels, ['linkedin' => 1, 'facebook' => 1]))),
            'part_count'   => (string) ($_POST['part_count'] ?? '3'),
            'cadence_days' => (string) ($_POST['cadence_days'] ?? '2'),
            'audience'     => (string) ($_POST['audience'] ?? ''),
            'name'         => (string) ($_POST['name'] ?? ''),
            'cta_url'      => (string) ($_POST['cta_url'] ?? marketing_setting('campaign.default_cta_url', '')),
            'cta_text'     => (string) ($_POST['cta_text'] ?? ''),
            'brief'        => (string) ($_POST['brief'] ?? ''),
        ];
        ?>
        <?php if ($accepted === []): ?>
        <p>No accepted topics. Accept a topic and write its angle on the <a href="/marketing/topics/">Topic Board</a> first.</p>
        <?php else: ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>?tab=new">
          <input type="hidden" name="action" value="create" />
          <div class="form-grid">
            <div class="form-group form-grid-full">
              <label for="topic_id">Topic</label>
              <select class="form-input" id="topic_id" name="topic_id" required>
                <option value="">Choose an accepted topic…</option>
                <?php foreach ($accepted as $row): ?>
                <option value="<?= (int) $row['TopicID'] ?>" <?= $form['topic_id'] === (string) $row['TopicID'] ? 'selected' : '' ?>>#<?= (int) $row['TopicID'] ?> <?= htmlspecialchars(mb_strimwidth((string) $row['Title'], 0, 140, '…')) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label for="format">Format</label>
              <select class="form-input" id="format" name="format">
                <?php foreach (MKT_CAMPAIGN_FORMATS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $form['format'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
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
            <div class="form-group form-grid-full form-group--stacked">
              <label>Social channels</label>
              <div>
                <?php foreach ($socialChannels as $key => $channel): ?>
                <label style="margin-right:1.25rem"><input type="checkbox" name="channels[]" value="<?= htmlspecialchars($key) ?>" <?= in_array($key, $form['channels'], true) ? 'checked' : '' ?> /> <?= htmlspecialchars($channel['label']) ?><?= $channel['max'] > 0 ? ' <span class="form-hint">(' . number_format($channel['max']) . ')</span>' : '' ?></label>
                <?php endforeach; ?>
              </div>
              <p class="form-hint">Posts and series get one asset per channel per part. Email campaigns ignore this and use the email channel.</p>
            </div>
            <div class="form-group">
              <label for="part_count">Parts</label>
              <input class="form-input" type="number" id="part_count" name="part_count" min="1" max="<?= MKT_SERIES_MAX_PARTS ?>" value="<?= htmlspecialchars($form['part_count']) ?>" />
            </div>
            <div class="form-group">
              <label for="cadence_days">Days between parts</label>
              <input class="form-input" type="number" id="cadence_days" name="cadence_days" min="1" max="30" value="<?= htmlspecialchars($form['cadence_days']) ?>" />
            </div>
            <p class="form-hint form-grid-full">Parts apply to a series (2–<?= MKT_SERIES_MAX_PARTS ?>) or an email sequence (1–<?= MKT_EMAIL_MAX_PARTS ?>); a single post is always one part.</p>
            <div class="form-group form-grid-full"><label for="name">Name</label><input class="form-input" id="name" name="name" maxlength="200" value="<?= htmlspecialchars($form['name']) ?>" placeholder="Defaults to the topic title and format" /></div>
            <div class="form-group form-grid-full"><label for="cta_url">Call-to-action URL</label><input class="form-input" type="url" id="cta_url" name="cta_url" required maxlength="1000" value="<?= htmlspecialchars($form['cta_url']) ?>" /></div>
            <div class="form-group form-grid-full"><label for="cta_text">Call-to-action text</label><input class="form-input" id="cta_text" name="cta_text" maxlength="200" value="<?= htmlspecialchars($form['cta_text']) ?>" placeholder="e.g. Read the practitioner summary" /></div>
            <div class="form-group form-grid-full"><label for="brief">Extra brief</label><textarea class="form-input" id="brief" name="brief" rows="3" maxlength="2000" placeholder="Optional — anything beyond the topic's angle, e.g. an event, an offer, or a point to lead with"><?= htmlspecialchars($form['brief']) ?></textarea></div>
          </div>
          <p class="form-hint">UTM tags are added automatically: source = channel, medium = social or email, campaign = the campaign slug, content = the asset number.</p>
          <div class="form-actions">
            <button type="submit" class="btn-primary" name="generate_now" value="1">Create and generate</button>
            <button type="submit" class="btn-secondary">Create only</button>
          </div>
          <p class="form-hint">Generating takes a minute or two (Sonnet, usually a few cents) and runs the claims check on every asset.</p>
        </form>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
