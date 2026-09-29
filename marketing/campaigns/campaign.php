<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-calendar.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-campaigns');

$activeSlug = 'marketing-campaigns';
$listHref = '/marketing/campaigns/';
$id = (int) ($_GET['id'] ?? 0);
$campaign = $id > 0 ? mkt_campaign_get($id) : null;
if ($campaign === null) {
    marketing_redirect($listHref, ['error' => 'Campaign not found.']);
}
$selfHref = '/marketing/campaigns/campaign.php?id=' . $id;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $back = static fn(array $result, string $notice) => marketing_redirect('/marketing/campaigns/campaign.php', !empty($result['ok'])
        ? ['id' => $id, 'notice' => (string) ($result['message'] ?? $notice)]
        : ['id' => $id, 'error' => (string) ($result['error'] ?? 'Action failed.')]);

    if ($action === 'generate') {
        $back(process_execute('campaign-generate', ['campaign_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Content generated.');
    } elseif ($action === 'check') {
        $back(process_execute('campaign-claims-check', ['campaign_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Claims check complete.');
    } elseif ($action === 'submit_passing') {
        $submitted = 0;
        $skipped = 0;
        foreach (mkt_campaign_assets($id) as $asset) {
            if (!in_array($asset['Status'], MKT_ASSET_EDITABLE, true)) {
                continue;
            }
            mkt_asset_submit((int) $asset['AssetID'])['ok'] ? $submitted++ : $skipped++;
        }
        $back(['ok' => true], $submitted . ' asset(s) submitted for review' . ($skipped > 0 ? '; ' . $skipped . ' not ready (stale check, low score or missing [LINK]).' : '.'));
    } elseif ($action === 'save') {
        $result = mkt_campaign_update($id, $_POST);
        if ($result['ok']) {
            $back($result, 'Campaign saved.');
        }
        $error = $result['error'];
    } elseif ($action === 'archive' || $action === 'restore') {
        $back(mkt_campaign_update($id, ['status' => $action === 'archive' ? 'archived' : 'active'] + [
            'name' => $campaign['Name'], 'cta_url' => $campaign['CtaUrl'], 'cta_text' => $campaign['CtaText'], 'brief' => $campaign['Brief'],
        ]), $action === 'archive' ? 'Campaign archived.' : 'Campaign restored.');
    }
}

$assets = mkt_campaign_assets($id);
$channels = mkt_channels();
$canEdit = marketing_can_update() && $campaign['Status'] !== 'archived';
$allEditable = $assets === [] || array_reduce($assets, static fn(bool $ok, array $a): bool => $ok && in_array($a['Status'], MKT_ASSET_EDITABLE, true), true);
$staleCount = count(array_filter($assets, static fn(array $a): bool => in_array($a['Status'], MKT_ASSET_EDITABLE, true) && !mkt_asset_check_current($a)));
$readyCount = count(array_filter($assets, static fn(array $a): bool => in_array($a['Status'], MKT_ASSET_EDITABLE, true) && mkt_asset_passes($a)));
$approvedCount = count(array_filter($assets, static fn(array $a): bool => $a['Status'] === 'approved'));
$claims = !empty($campaign['TopicID']) ? array_filter(mkt_topic_claims((int) $campaign['TopicID']), static fn(array $c): bool => $c['Status'] === 'approved') : [];
$names = mkt_user_names([$campaign['CreatedBy'] ?? 0]);

$pageTitle = $campaign['Name'] . ' | Campaign Studio | NutraAxis Operations';
$pageDescription = 'Campaign detail: brief, generated assets, claims scores and review status.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$form = [
    'name'     => (string) ($_POST['name'] ?? $campaign['Name']),
    'cta_url'  => (string) ($_POST['cta_url'] ?? $campaign['CtaUrl']),
    'cta_text' => (string) ($_POST['cta_text'] ?? $campaign['CtaText'] ?? ''),
    'brief'    => (string) ($_POST['brief'] ?? $campaign['Brief'] ?? ''),
];
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $listHref . ($campaign['Status'] === 'archived' ? '?tab=archived' : ''),
          'back_label' => 'Back to Campaign Studio',
          'category'   => 'Marketing & Research',
          'title'      => (string) $campaign['Name'],
          'lead'       => (string) ($campaign['TopicAngle'] ?? ''),
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      ?>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Status</dt><dd><?= mkt_render_badge((string) $campaign['Status'], MKT_CAMPAIGN_STATUSES) ?></dd>
          <dt>Topic</dt><dd><?php if (!empty($campaign['TopicID'])): ?><a href="/marketing/topics/view.php?id=<?= (int) $campaign['TopicID'] ?>"><?= htmlspecialchars((string) $campaign['TopicTitle']) ?></a><?= $campaign['TopicStatus'] !== 'accepted' ? ' <span class="status-badge status-cancelled">No longer accepted</span>' : '' ?><?php else: ?>—<?php endif; ?></dd>
          <dt>Format</dt><dd><?= htmlspecialchars(MKT_CAMPAIGN_FORMATS[(string) $campaign['Format']] ?? '') ?><?= (int) $campaign['PartCount'] > 1 ? ' — ' . (int) $campaign['PartCount'] . ' parts, ' . (int) $campaign['CadenceDays'] . ' day(s) apart' : '' ?></dd>
          <dt>Channels</dt><dd><?= htmlspecialchars(implode(', ', array_map(static fn(string $k): string => $channels[$k]['label'] ?? $k, explode(',', (string) $campaign['Channels'])))) ?></dd>
          <dt>Audience</dt><dd><?= htmlspecialchars(MKT_CLAIM_AUDIENCES[(string) $campaign['Audience']] ?? '') ?></dd>
          <dt>UTM campaign</dt><dd><code><?= htmlspecialchars((string) $campaign['Slug']) ?></code></dd>
          <dt class="is-wide">Avoid</dt><dd><?= htmlspecialchars((string) ($campaign['TopicAvoid'] ?? '—')) ?></dd>
          <dt class="is-wide">Approved claims</dt><dd><?= $claims === [] ? 'None linked to the topic — content may not make product or ingredient benefit claims.' : count($claims) . ' linked on the topic' ?></dd>
          <dt>Generated</dt><dd><?= !empty($campaign['GeneratedAt']) ? htmlspecialchars(marketing_format_datetime($campaign['GeneratedAt'])) . ' (prompt v' . (int) $campaign['PromptVersion'] . ')' : 'Not yet' ?></dd>
          <dt>Created</dt><dd><?= htmlspecialchars(marketing_format_datetime($campaign['CreatedAt'] ?? null)) ?><?= isset($names[(int) $campaign['CreatedBy']]) ? ' by ' . htmlspecialchars((string) $names[(int) $campaign['CreatedBy']]) : '' ?></dd>
        </dl>
      </div>

      <?php if ($canEdit): ?>
      <div class="status-banner">
        <div>
          <strong><?= count($assets) ?> asset(s)</strong>
          <p><?= $staleCount ?> need a claims check · <?= $readyCount ?> ready to submit (score ≥ <?= htmlspecialchars((string) marketing_setting('claims.min_score', '7')) ?>)</p>
        </div>
        <div>
          <?php if ($allEditable): ?>
          <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline" <?= $assets !== [] ? 'onsubmit="return confirm(\'Regenerate replaces every draft asset in this campaign. Continue?\');"' : '' ?>>
            <button type="submit" class="btn-primary" name="action" value="generate" title="Sonnet, usually a few cents; takes a minute or two"><?= $assets === [] ? 'Generate content' : 'Regenerate all' ?></button>
          </form>
          <?php endif; ?>
          <?php if ($staleCount > 0): ?>
          <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
            <button type="submit" class="btn-secondary" name="action" value="check">Check claims (<?= $staleCount ?>)</button>
          </form>
          <?php endif; ?>
          <?php if ($readyCount > 0): ?>
          <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
            <button type="submit" class="btn-secondary" name="action" value="submit_passing">Submit <?= $readyCount ?> for review</button>
          </form>
          <?php endif; ?>
          <?php if ($approvedCount > 0): ?>
          <a class="btn-primary" href="/marketing/calendar/?tab=ready&amp;campaign_id=<?= $id ?>">Schedule <?= $approvedCount ?> approved</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <h2 class="hub-section-title">Assets</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Part</th><th>Channel</th><th>Asset</th><th>Length</th><th>Claims score</th><th>Compliance</th><th>Editorial</th><th>Status</th><th>Scheduled (Central)</th></tr></thead>
          <tbody>
            <?php if ($assets === []): ?>
            <tr><td colspan="9">No assets yet. <?= $canEdit ? 'Use Generate content to draft them from the topic.' : '' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($assets as $asset): ?>
            <?php
            $check = mkt_asset_check($asset);
            $channel = $channels[(string) $asset['Channel']] ?? null;
            $chars = mb_strlen(mkt_asset_copy_text($asset, $campaign));
            ?>
            <tr>
              <td><?= (int) $asset['SequenceNo'] ?></td>
              <td><?= htmlspecialchars($channel['label'] ?? (string) $asset['Channel']) ?></td>
              <td>
                <a href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>"><strong><?= htmlspecialchars((string) ($asset['Subject'] ?: $asset['Title'] ?: 'Asset #' . $asset['AssetID'])) ?></strong></a>
                <div class="form-hint"><?= htmlspecialchars(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $asset['Body']) ?? '', 0, 180, '…')) ?></div>
                <?php if ($check !== null && mkt_asset_check_current($asset) && ($check['issues'] !== [] || $check['flag_terms'] !== [])): ?>
                <div class="form-hint"><strong><?= count($check['issues']) + count($check['flag_terms']) ?> finding(s)</strong><?= !empty($check['summary']) ? ' — ' . htmlspecialchars((string) $check['summary']) : '' ?></div>
                <?php endif; ?>
              </td>
              <td><?= number_format($chars) ?><?= $channel !== null && $channel['max'] > 0 ? ' / ' . number_format($channel['max']) : '' ?></td>
              <td><?= mkt_score_badge($asset) ?><?= !empty($asset['NeedsCompliance']) && mkt_asset_check_current($asset) ? '<div class="form-hint">Needs compliance</div>' : '' ?></td>
              <td><?= htmlspecialchars(MKT_GATE_STATUSES[(string) ($asset['ComplianceStatus'] ?? '')] ?? '—') ?></td>
              <td><?= htmlspecialchars(MKT_GATE_STATUSES[(string) ($asset['EditorialStatus'] ?? '')] ?? '—') ?></td>
              <td><?= mkt_asset_badge($asset) ?></td>
              <td><?= htmlspecialchars(mkt_cal_format($asset['PostedAt'] ?? $asset['ScheduledAt'])) ?><?= !empty($asset['ExternalPostID']) && $asset['Status'] === 'scheduled' ? '<div class="form-hint">In GoHighLevel</div>' : '' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h2 class="hub-section-title">Campaign settings</h2>
      <?php if ($canEdit): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <div class="form-grid">
          <div class="form-group form-grid-full"><label for="name">Name</label><input class="form-input" id="name" name="name" required maxlength="200" value="<?= htmlspecialchars($form['name']) ?>" /></div>
          <div class="form-group form-grid-full"><label for="cta_url">Call-to-action URL</label><input class="form-input" type="url" id="cta_url" name="cta_url" required maxlength="1000" value="<?= htmlspecialchars($form['cta_url']) ?>" /></div>
          <div class="form-group form-grid-full"><label for="cta_text">Call-to-action text</label><input class="form-input" id="cta_text" name="cta_text" maxlength="200" value="<?= htmlspecialchars($form['cta_text']) ?>" /></div>
          <div class="form-group form-grid-full"><label for="brief">Extra brief</label><textarea class="form-input" id="brief" name="brief" rows="3" maxlength="2000"><?= htmlspecialchars($form['brief']) ?></textarea></div>
        </div>
        <p class="form-hint">The URL feeds every asset's tracked link. Brief changes apply the next time content is generated.</p>
        <div class="form-actions">
          <button type="submit" class="btn-primary" name="action" value="save">Save</button>
          <button type="submit" class="btn-secondary" name="action" value="archive" formnovalidate onclick="return confirm('Archive this campaign?');">Archive campaign</button>
        </div>
      </form>
      <?php else: ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Call to action</dt><dd><?= htmlspecialchars((string) ($campaign['CtaText'] ?? '')) ?> — <a href="<?= htmlspecialchars((string) $campaign['CtaUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars((string) $campaign['CtaUrl']) ?></a></dd>
          <dt>Extra brief</dt><dd><?= nl2br(htmlspecialchars((string) ($campaign['Brief'] ?? '—'))) ?></dd>
        </dl>
      </div>
      <?php if (marketing_can_update() && $campaign['Status'] === 'archived'): ?>
      <form method="post" action="<?= htmlspecialchars($selfHref) ?>"><button type="submit" class="btn-secondary" name="action" value="restore">Restore campaign</button></form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
