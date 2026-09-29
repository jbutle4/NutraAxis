<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-calendar.php';
require dirname(__DIR__, 2) . '/includes/marketing-performance.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-campaigns');

$activeSlug = 'marketing-campaigns';
$id = (int) ($_GET['id'] ?? 0);
$asset = $id > 0 ? mkt_asset_get($id) : null;
if ($asset === null) {
    marketing_redirect('/marketing/campaigns/', ['error' => 'Asset not found.']);
}
$campaign = mkt_campaign_get((int) $asset['CampaignID']);
$selfHref = '/marketing/campaigns/asset.php?id=' . $id;
$campaignHref = '/marketing/campaigns/campaign.php?id=' . (int) $asset['CampaignID'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $back = static fn(array $result, string $notice) => marketing_redirect('/marketing/campaigns/asset.php', !empty($result['ok'])
        ? ['id' => $id, 'notice' => (string) ($result['message'] ?? $notice)]
        : ['id' => $id, 'error' => (string) ($result['error'] ?? 'Action failed.')]);

    if ($action === 'save') {
        $result = mkt_asset_save($id, $_POST);
        if ($result['ok'] && !empty($result['changed'])) {
            $check = process_execute('campaign-claims-check', ['asset_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            $notice = ($result['reset'] ? 'Saved — the asset went back to draft and both reviews were cleared. ' : 'Saved. ')
                . (!empty($check['ok']) ? 'Claims check re-run.' : 'Claims check failed: ' . (string) ($check['error'] ?? 'unknown error'));
            $back(['ok' => true], $notice);
        }
        if ($result['ok']) {
            $back($result, 'No changes.');
        }
        $error = $result['error'];
    } elseif ($action === 'check') {
        $back(process_execute('campaign-claims-check', ['asset_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Claims check complete.');
    } elseif ($action === 'revise') {
        $back(process_execute('campaign-revise-asset', ['asset_id' => $id, 'instruction' => (string) ($_POST['instruction'] ?? '')], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'AI revision saved and re-checked.');
    } elseif ($action === 'submit') {
        $result = mkt_asset_submit($id);
        $back($result, !empty($result['compliance']) ? 'Submitted — waiting on compliance review.' : 'Submitted — no claims found, so it goes straight to editorial review.');
    } elseif ($action === 'review') {
        $result = mkt_asset_review($id, (string) ($_POST['gate'] ?? ''), (string) ($_POST['decision'] ?? ''), (string) ($_POST['note'] ?? ''));
        $back($result, match ($result['status'] ?? '') {
            'approved'          => 'Approved — the asset is ready to schedule.',
            'changes_requested' => 'Changes requested — the asset went back to the author.',
            default             => 'Compliance cleared — waiting on editorial review.',
        });
    } elseif ($action === 'schedule') {
        $result = mkt_asset_schedule($id, (string) ($_POST['at'] ?? ''));
        $back($result, 'Scheduled.' . (!empty($result['past']) ? ' That time has already passed — mark it posted once it is live.' : '')
            . (!empty($result['reload_ghl']) ? ' It was already in GoHighLevel — move it there too.' : ''));
    } elseif ($action === 'unschedule') {
        $result = mkt_asset_unschedule($id);
        $back($result, 'Taken off the calendar.' . (!empty($result['ghl_id']) ? ' Delete GoHighLevel item ' . $result['ghl_id'] . ' too.' : ''));
    } elseif ($action === 'loaded') {
        $back(mkt_asset_record_loaded($id, (string) ($_POST['ghl_id'] ?? '')), 'GoHighLevel ID recorded.');
    } elseif ($action === 'posted') {
        $back(mkt_asset_mark_posted($id, (string) ($_POST['posted_at'] ?? ''), (string) ($_POST['url'] ?? ''), (string) ($_POST['ghl_id'] ?? '')), 'Marked posted.');
    } elseif ($action === 'update_posted') {
        $back(mkt_asset_update_posted($id, (string) ($_POST['url'] ?? ''), (string) ($_POST['ghl_id'] ?? '')), 'Posting details updated.');
    } elseif ($action === 'archive') {
        $result = mkt_asset_archive($id);
        if ($result['ok']) {
            marketing_redirect('/marketing/campaigns/campaign.php', ['id' => (int) $asset['CampaignID'], 'notice' => 'Asset archived.']);
        }
        $error = $result['error'];
    }
}

$channels = mkt_channels();
$channel = $channels[(string) $asset['Channel']] ?? ['label' => $asset['Channel'], 'medium' => 'social', 'max' => 0, 'guidance' => ''];
$isEmail = $channel['medium'] === 'email';
$status = (string) $asset['Status'];
$editable = in_array($status, MKT_ASSET_EDITABLE, true);
$canUpdate = marketing_can_update();
$canEditContent = $canUpdate && in_array($status, ['draft', 'changes_requested', 'in_review', 'approved'], true);
$check = mkt_asset_check($asset);
$checkCurrent = mkt_asset_check_current($asset);
$reviews = mkt_asset_reviews($id);
$names = mkt_user_names([
    $asset['UpdatedBy'] ?? 0, $asset['SubmittedBy'] ?? 0, $asset['ComplianceBy'] ?? 0, $asset['EditorialBy'] ?? 0,
    $asset['ScheduledBy'] ?? 0, $asset['LoadedBy'] ?? 0, $asset['PostedBy'] ?? 0,
]);
$byName = static fn(string $col): string => isset($names[(int) ($asset[$col] ?? 0)]) ? ' by ' . htmlspecialchars((string) $names[(int) $asset[$col]]) : '';
$warnings = $status === 'scheduled' ? (mkt_cal_conflicts()[$id] ?? []) : [];
$claimIds = json_decode((string) ($asset['ClaimIdsJson'] ?? ''), true);
$usedClaims = [];
if (is_array($claimIds) && $claimIds !== [] && !empty($campaign['TopicID'])) {
    foreach (mkt_topic_claims((int) $campaign['TopicID']) as $claim) {
        if (in_array((int) $claim['ClaimID'], array_map('intval', $claimIds), true)) {
            $usedClaims[] = $claim;
        }
    }
}
$trackedUrl = mkt_asset_tracked_url($asset, $campaign);
$copyText = mkt_asset_copy_text($asset, $campaign);
$variants = json_decode((string) ($asset['SubjectVariantsJson'] ?? ''), true);
$me = marketing_user_id();
$ownWork = $me !== null && in_array($me, [(int) ($asset['UpdatedBy'] ?? 0), (int) ($asset['SubmittedBy'] ?? 0)], true);
$gate = null;
if ($status === 'in_review') {
    if ($asset['ComplianceStatus'] === 'pending') {
        $gate = 'compliance';
    } elseif ($asset['EditorialStatus'] === 'pending') {
        $gate = 'editorial';
    }
}
$canReview = $gate !== null && !$ownWork && ($gate === 'compliance' ? mkt_can_compliance_review() : mkt_can_editorial_review());

$form = [];
foreach (MKT_ASSET_CONTENT_FIELDS as $key => $col) {
    $form[$key] = (string) ($_POST[$key] ?? $asset[$col] ?? '');
}
$title = (string) ($asset['Subject'] ?: $asset['Title'] ?: 'Asset #' . $id);
$pageTitle = $title . ' | Campaign Studio | NutraAxis Operations';
$pageDescription = 'Asset editor, claims check, compliance and editorial review.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$classLabels = ['approved' => 'Approved claim', 'evidence' => 'Cited finding', 'unapproved' => 'Unapproved claim', 'disease' => 'Disease claim'];
$gateLabels = ['submit' => 'Submitted', 'compliance' => 'Compliance', 'editorial' => 'Editorial', 'system' => 'System'];
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $campaignHref,
          'back_label' => 'Back to ' . mb_strimwidth((string) $campaign['Name'], 0, 60, '…'),
          'category'   => 'Marketing & Research',
          'title'      => $title,
          'lead'       => $channel['label'] . ' · part ' . (int) $asset['SequenceNo'] . ' of ' . (int) $campaign['PartCount'] . ' · version ' . (int) $asset['ContentVersion'],
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      ?>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Status</dt><dd><?= mkt_asset_badge($asset) ?></dd>
          <dt>Claims score</dt><dd><?= mkt_score_badge($asset) ?> <span class="form-hint">minimum <?= htmlspecialchars((string) marketing_setting('claims.min_score', '7')) ?> to submit</span></dd>
          <dt>Compliance</dt><dd><?= htmlspecialchars(MKT_GATE_STATUSES[(string) ($asset['ComplianceStatus'] ?? '')] ?? '—') ?><?= !empty($asset['ComplianceBy']) ? ' — ' . htmlspecialchars((string) ($names[(int) $asset['ComplianceBy']] ?? '')) . ', ' . htmlspecialchars(marketing_format_datetime($asset['ComplianceAt'])) : '' ?></dd>
          <dt>Editorial</dt><dd><?= htmlspecialchars(MKT_GATE_STATUSES[(string) ($asset['EditorialStatus'] ?? '')] ?? '—') ?><?= !empty($asset['EditorialBy']) ? ' — ' . htmlspecialchars((string) ($names[(int) $asset['EditorialBy']] ?? '')) . ', ' . htmlspecialchars(marketing_format_datetime($asset['EditorialAt'])) : '' ?></dd>
          <dt>Last edited</dt><dd><?= htmlspecialchars(marketing_format_datetime($asset['UpdatedAt'] ?? null)) ?><?= isset($names[(int) ($asset['UpdatedBy'] ?? 0)]) ? ' by ' . htmlspecialchars((string) $names[(int) $asset['UpdatedBy']]) : ' (AI generation)' ?></dd>
          <?php if (!empty($asset['SubmittedAt'])): ?>
          <dt>Submitted</dt><dd><?= htmlspecialchars(marketing_format_datetime($asset['SubmittedAt'])) ?><?= isset($names[(int) $asset['SubmittedBy']]) ? ' by ' . htmlspecialchars((string) $names[(int) $asset['SubmittedBy']]) : '' ?></dd>
          <?php endif; ?>
          <dt>Tracked link</dt><dd><code style="word-break:break-all"><?= htmlspecialchars($trackedUrl) ?></code></dd>
          <?php if (!empty($asset['MediaNotes'])): ?><dt>Media</dt><dd><?= htmlspecialchars((string) $asset['MediaNotes']) ?></dd><?php endif; ?>
        </dl>
      </div>

      <?php if (in_array($status, ['approved', 'scheduled', 'posted'], true)): ?>
      <h2 class="hub-section-title" id="publishing">Publishing</h2>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <?php if ($status === 'approved'): ?>
          <dt>Calendar</dt><dd>Approved, not scheduled yet.</dd>
          <?php else: ?>
          <dt>Scheduled</dt><dd><?= htmlspecialchars(mkt_cal_format($asset['ScheduledAt'], 'D M j, Y g:i A T')) ?><?= $byName('ScheduledBy') ?></dd>
          <dt>GoHighLevel</dt><dd><?= !empty($asset['ExternalPostID']) ? htmlspecialchars((string) $asset['ExternalPostID']) . (!empty($asset['LoadedAt']) ? ' — loaded ' . htmlspecialchars(marketing_format_datetime($asset['LoadedAt'])) . $byName('LoadedBy') : '') : 'Not loaded yet' ?></dd>
          <?php endif; ?>
          <?php if ($status === 'posted'): ?>
          <dt>Posted</dt><dd><?= htmlspecialchars(mkt_cal_format($asset['PostedAt'], 'D M j, Y g:i A T')) ?><?= $byName('PostedBy') ?></dd>
          <dt>Live post</dt><dd><?= !empty($asset['ExternalPostUrl']) ? '<a href="' . htmlspecialchars((string) $asset['ExternalPostUrl']) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars((string) $asset['ExternalPostUrl']) . '</a>' : '—' ?></dd>
          <?php
          $traffic = mkt_perf_asset_daily((int) $asset['AssetID'], 365);
          $trafficSessions = array_sum(array_column($traffic, 'Sessions'));
          ?>
          <dt>Site traffic</dt>
          <dd>
            <?php if ($traffic === []): ?>
            No GA4 sessions from the tracked link yet <span class="form-hint">(GA4 loads nightly and lags about a day)</span>
            <?php else: ?>
            <?= number_format($trafficSessions) ?> sessions ·
            <?= mkt_perf_rate((float) array_sum(array_column($traffic, 'EngagedSessions')), (float) $trafficSessions) ?> engaged ·
            <?= number_format(array_sum(array_column($traffic, 'KeyEvents'))) ?> key events ·
            <?= number_format(array_sum(array_column($traffic, 'Transactions'))) ?> purchases
            <span class="form-hint">— last visit <?= htmlspecialchars(marketing_format_date($traffic[0]['MetricDate'])) ?></span>
            <?php endif; ?>
            <a class="btn-text" href="/marketing/performance/?tab=assets">All assets</a>
          </dd>
          <?php endif; ?>
          <?php if ($warnings !== []): ?><dt>Warnings</dt><dd style="color:var(--danger)">⚠ <?= htmlspecialchars(implode(' ', $warnings)) ?></dd><?php endif; ?>
        </dl>
      </div>
      <?php if ($canUpdate): ?>
        <?php if ($status === 'approved' || $status === 'scheduled'): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="margin-bottom:0.75rem">
          <input type="hidden" name="action" value="schedule" />
          <label for="at"><?= $status === 'approved' ? 'Schedule for' : 'Move to' ?> (Central)</label>
          <input class="form-input" type="datetime-local" id="at" name="at" required value="<?= htmlspecialchars(mkt_cal_input_value($asset['ScheduledAt']) ?: mkt_cal_now_local()->modify('+1 day')->format('Y-m-d') . 'T' . (mkt_cal_default_times()[(string) $asset['Channel']] ?? '09:00')) ?>" />
          <button type="submit" class="btn-<?= $status === 'approved' ? 'primary' : 'secondary' ?>"><?= $status === 'approved' ? 'Schedule' : 'Reschedule' ?></button>
          <?php if ($status === 'approved'): ?><a class="btn-text" href="/marketing/calendar/?tab=ready&amp;campaign_id=<?= (int) $asset['CampaignID'] ?>">Lay out the whole campaign</a><?php endif; ?>
        </form>
        <?php endif; ?>
        <?php if ($status === 'scheduled'): ?>
        <?php if (empty($asset['ExternalPostID'])): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="margin-bottom:0.75rem">
          <input type="hidden" name="action" value="loaded" />
          <label for="ghl_id">Loaded into GoHighLevel as</label>
          <input class="form-input" id="ghl_id" name="ghl_id" required maxlength="200" placeholder="Post / campaign ID" />
          <button type="submit" class="btn-secondary">Save ID</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form" style="margin-bottom:0.75rem">
          <input type="hidden" name="action" value="posted" />
          <label for="url">Live at</label>
          <input class="form-input" type="url" id="url" name="url" maxlength="1000" placeholder="Public post URL (optional for email)" />
          <label for="posted_at">Posted</label>
          <input class="form-input" type="datetime-local" id="posted_at" name="posted_at" title="Leave blank to use the scheduled time" />
          <button type="submit" class="btn-primary">Mark posted</button>
        </form>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" onsubmit="return confirm('Take this asset off the calendar?<?= !empty($asset['ExternalPostID']) ? ' Delete it in GoHighLevel too.' : '' ?>');">
          <input type="hidden" name="action" value="unschedule" />
          <button type="submit" class="btn-text">Take off the calendar</button>
          <span class="form-hint">— needed before the content can be edited.</span>
        </form>
        <?php elseif ($status === 'posted'): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" class="mkt-inline-form">
          <input type="hidden" name="action" value="update_posted" />
          <input class="form-input" type="url" name="url" maxlength="1000" value="<?= htmlspecialchars((string) ($asset['ExternalPostUrl'] ?? '')) ?>" placeholder="Public post URL" aria-label="Public post URL" />
          <input class="form-input" name="ghl_id" maxlength="200" value="<?= htmlspecialchars((string) ($asset['ExternalPostID'] ?? '')) ?>" placeholder="GoHighLevel ID" aria-label="GoHighLevel ID" />
          <button type="submit" class="btn-secondary">Update</button>
        </form>
        <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($canReview): ?>
      <h2 class="hub-section-title" id="review"><?= $gate === 'compliance' ? 'Compliance review' : 'Editorial review' ?></h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="review" />
        <input type="hidden" name="gate" value="<?= $gate ?>" />
        <p class="form-hint">
          <?= $gate === 'compliance'
              ? 'Check every statement against the approved claims and the rules below: no disease claims, no guarantees, findings attributed to their source, and the disclaimer where a ‡ claim is used.'
              : 'Voice, accuracy, channel fit and the call to action. Compliance has ' . ($asset['ComplianceStatus'] === 'not_required' ? 'not been required (the check found no claims).' : 'cleared this asset.') ?>
        </p>
        <div class="form-group form-grid-full"><label for="note">Note</label><textarea class="form-input" id="note" name="note" rows="3" maxlength="2000" placeholder="Required when requesting changes"></textarea></div>
        <div class="form-actions">
          <button type="submit" class="btn-primary" name="decision" value="approved"><?= $gate === 'compliance' ? 'Clear compliance' : 'Approve' ?></button>
          <button type="submit" class="btn-secondary" name="decision" value="changes_requested">Request changes</button>
        </div>
      </form>
      <?php elseif ($gate !== null): ?>
      <p class="form-hint" id="review">
        Waiting on <?= $gate === 'compliance' ? 'compliance review by a Marketing Compliance Reviewer' : 'editorial review by a Marketing admin' ?>.
        <?= $ownWork ? 'You edited or submitted this asset, so someone else must review it.' : '' ?>
      </p>
      <?php endif; ?>

      <h2 class="hub-section-title">Copy-ready preview</h2>
      <div class="detail-card">
        <?php if ($isEmail): ?>
        <dl class="detail-list detail-list-inline">
          <dt>Subject</dt><dd><?= htmlspecialchars((string) ($asset['Subject'] ?? '')) ?></dd>
          <?php if (is_array($variants) && count($variants) > 1): ?><dt>Variants</dt><dd><?= htmlspecialchars(implode(' · ', $variants)) ?></dd><?php endif; ?>
          <dt>Preview text</dt><dd><?= htmlspecialchars((string) ($asset['PreviewText'] ?? '')) ?></dd>
        </dl>
        <?php endif; ?>
        <pre id="copy-text" style="white-space:pre-wrap;font-family:inherit;margin:0"><?= htmlspecialchars($copyText) ?></pre>
        <p class="form-hint">
          <?= number_format(mb_strlen($copyText)) ?> characters<?= $channel['max'] > 0 ? ' of ' . number_format($channel['max']) : '' ?>.
          <?= in_array($status, ['approved', 'scheduled', 'posted'], true) ? '' : '<strong>Not approved — do not post.</strong>' ?>
          <button type="button" class="btn-text" onclick="navigator.clipboard.writeText(document.getElementById('copy-text').innerText).then(() => { this.textContent = 'Copied'; });">Copy</button>
        </p>
      </div>

      <h2 class="hub-section-title">Claims check</h2>
      <?php if ($check === null): ?>
      <p class="form-hint">Not checked yet.</p>
      <?php else: ?>
      <?php if (!$checkCurrent): ?>
      <p class="admin-notice is-error">This check is for version <?= (int) $asset['ClaimsCheckedVersion'] ?>; the content is now version <?= (int) $asset['ContentVersion'] ?>. Re-run the check before submitting.</p>
      <?php endif; ?>
      <div class="detail-card">
        <dl class="detail-list detail-list-inline">
          <dt>Summary</dt><dd><?= htmlspecialchars((string) ($check['summary'] ?? '')) ?></dd>
          <dt>Score</dt><dd><?= htmlspecialchars(number_format((float) $check['score'], 1)) ?> (AI <?= htmlspecialchars(number_format((float) ($check['ai_score'] ?? $check['score']), 1)) ?><?= $check['flag_terms'] !== [] ? ', minus ' . count($check['flag_terms']) . ' for flag terms' : '' ?>)</dd>
          <?php if ($check['flag_terms'] !== []): ?><dt>Flag terms</dt><dd><?= htmlspecialchars(implode(', ', $check['flag_terms'])) ?></dd><?php endif; ?>
          <dt>References</dt><dd><?= !empty($check['references_efficacy']) ? 'Efficacy · ' : '' ?><?= !empty($check['references_condition']) ? 'Health condition · ' : '' ?>Disclaimer <?= !empty($check['disclaimer_ok']) ? 'OK' : '<strong>missing or wrong</strong>' ?></dd>
          <dt>Compliance</dt><dd><?= !empty($asset['NeedsCompliance']) ? 'Required' : 'Not required' ?><?= marketing_setting('review.compliance_mode', 'claims') === 'all' ? ' (every asset is reviewed)' : '' ?></dd>
          <?php if ($usedClaims !== []): ?>
          <dt>Claims used</dt><dd><?= implode('<br>', array_map(static fn(array $c): string => htmlspecialchars(($c['ProductName'] ?? 'General') . ': ' . $c['ClaimText']), $usedClaims)) ?></dd>
          <?php endif; ?>
        </dl>
        <?php if ($check['issues'] !== []): ?>
        <h3>Issues</h3>
        <ul><?php foreach ($check['issues'] as $issue): ?><li><?= htmlspecialchars((string) $issue) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
      <?php if ($check['statements'] !== []): ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Statement</th><th>Class</th><th>Note</th></tr></thead>
          <tbody>
            <?php foreach ($check['statements'] as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) $row['text']) ?></td>
              <td><?= mkt_render_badge(in_array($row['class'], ['unapproved', 'disease'], true) ? 'failed' : 'active', ['failed' => $classLabels[$row['class']] ?? $row['class'], 'active' => $classLabels[$row['class']] ?? $row['class']]) ?></td>
              <td><?= htmlspecialchars((string) ($row['note'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($canUpdate && $editable): ?>
      <div class="form-actions">
        <?php if (!$checkCurrent): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline"><button type="submit" class="btn-secondary" name="action" value="check">Run claims check</button></form>
        <?php endif; ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <button type="submit" class="btn-primary" name="action" value="submit" <?= mkt_asset_passes($asset) ? '' : 'disabled title="Needs a current claims check at or above the minimum score"' ?>>Submit for review</button>
        </form>
      </div>

      <h2 class="hub-section-title">AI revise</h2>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="revise" />
        <div class="form-group form-grid-full"><label for="instruction">Instruction</label><textarea class="form-input" id="instruction" name="instruction" rows="2" maxlength="2000" placeholder="Optional — blank fixes every claims-check finding. e.g. 'Shorter, lead with the study finding, drop the children angle.'"></textarea></div>
        <p class="form-hint">Rewrites this asset (Sonnet, about a cent), saves it as a new version and re-runs the claims check.</p>
        <div class="form-actions"><button type="submit" class="btn-secondary">Revise with AI</button></div>
      </form>
      <?php endif; ?>

      <?php if ($canEditContent): ?>
      <h2 class="hub-section-title">Edit</h2>
      <?php if (!$editable): ?>
      <p class="admin-notice is-error">This asset is <?= htmlspecialchars(strtolower(MKT_ASSET_STATUSES[$status])) ?>. Saving a change sends it back to draft and clears both reviews.</p>
      <?php endif; ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="save" />
        <div class="form-grid">
          <?php if ($isEmail): ?>
          <div class="form-group form-grid-full"><label for="subject">Subject</label><input class="form-input" id="subject" name="subject" maxlength="300" value="<?= htmlspecialchars($form['subject']) ?>" /></div>
          <div class="form-group form-grid-full"><label for="preview_text">Preview text</label><input class="form-input" id="preview_text" name="preview_text" maxlength="300" value="<?= htmlspecialchars($form['preview_text']) ?>" /></div>
          <?php else: ?>
          <div class="form-group form-grid-full"><label for="title">Internal title</label><input class="form-input" id="title" name="title" maxlength="300" value="<?= htmlspecialchars($form['title']) ?>" /></div>
          <?php endif; ?>
          <div class="form-group form-grid-full form-group--stacked">
            <label for="body">Body</label>
            <textarea class="form-input" id="body" name="body" rows="14" required><?= htmlspecialchars($form['body']) ?></textarea>
            <p class="form-hint">Put <code>[LINK]</code> where the tracked link goes. <?= htmlspecialchars((string) $channel['guidance']) ?></p>
          </div>
          <?php if (!$isEmail): ?>
          <div class="form-group form-grid-full"><label for="hashtags">Hashtags</label><input class="form-input" id="hashtags" name="hashtags" maxlength="500" value="<?= htmlspecialchars($form['hashtags']) ?>" /></div>
          <?php endif; ?>
          <div class="form-group form-grid-full"><label for="cta_text">Call-to-action text</label><input class="form-input" id="cta_text" name="cta_text" maxlength="200" value="<?= htmlspecialchars($form['cta_text']) ?>" /></div>
          <div class="form-group form-grid-full"><label for="media_notes">Media notes</label><input class="form-input" id="media_notes" name="media_notes" maxlength="1000" value="<?= htmlspecialchars($form['media_notes']) ?>" placeholder="Image or video direction for the designer" /></div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary">Save and re-check</button>
          <?php if ($status !== 'posted'): ?>
          <button type="submit" class="btn-secondary" name="action" value="archive" formnovalidate onclick="return confirm('Archive this asset?');">Archive asset</button>
          <?php endif; ?>
        </div>
      </form>
      <?php endif; ?>

      <h2 class="hub-section-title">Review history</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>When</th><th>Step</th><th>Decision</th><th>By</th><th>Version</th><th>Score</th><th>Note</th></tr></thead>
          <tbody>
            <?php if ($reviews === []): ?>
            <tr><td colspan="7">No review activity yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($reviews as $row): ?>
            <tr>
              <td><?= htmlspecialchars(marketing_format_datetime($row['CreatedAt'] ?? null)) ?></td>
              <td><?= htmlspecialchars($gateLabels[(string) $row['Gate']] ?? (string) $row['Gate']) ?></td>
              <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string) $row['Decision']))) ?></td>
              <td><?= htmlspecialchars((string) ($row['UserName'] ?? '—')) ?></td>
              <td><?= $row['ContentVersion'] !== null ? (int) $row['ContentVersion'] : '—' ?></td>
              <td><?= $row['ClaimsScore'] !== null ? htmlspecialchars(number_format((float) $row['ClaimsScore'], 1)) : '—' ?></td>
              <td><?= nl2br(htmlspecialchars((string) ($row['Note'] ?? ''))) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
