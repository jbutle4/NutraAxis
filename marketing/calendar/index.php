<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-calendar.php';

auth_require_module_read('marketing-calendar');

$activeSlug = 'marketing-calendar';
$baseHref = '/marketing/calendar/';
$tabs = [
    'month'    => 'Month',
    'upcoming' => 'Upcoming',
    'ready'    => 'Ready to schedule',
    'todo'     => 'Coordinator to-do',
    'posted'   => 'Posted',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'month';
$now = mkt_cal_now_local();
$channels = mkt_channels();
$error = null;

if (($_GET['export'] ?? '') === 'csv') {
    $from = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['from'] ?? ''), mkt_cal_tz()) ?: $now->setTime(0, 0);
    $to = DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_GET['to'] ?? ''), mkt_cal_tz()) ?: $from->modify('+13 days');
    $rows = mkt_cal_export_rows(
        $from->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        $to->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
    );
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="publishing-calendar-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['asset_id', 'date', 'time_central', 'channel', 'campaign', 'part', 'subject', 'preview_text', 'text', 'tracked_link', 'ghl_id'], ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_values($row), ',', '"', '');
    }
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $assetId = (int) ($_POST['asset_id'] ?? 0);
    $returnTab = array_key_exists((string) ($_POST['tab'] ?? ''), $tabs) ? (string) $_POST['tab'] : 'month';
    $query = ['tab' => $returnTab] + array_filter(['month' => (string) ($_POST['month'] ?? ''), 'campaign_id' => (string) ($_POST['campaign_id_filter'] ?? '')]);
    $result = ['ok' => false, 'error' => 'Unknown action.'];
    $notice = '';

    if ($action === 'schedule') {
        $result = mkt_asset_schedule($assetId, (string) ($_POST['at'] ?? ''));
        $notice = 'Scheduled.' . (!empty($result['past']) ? ' That time has already passed — mark it posted once it is live.' : '')
            . (!empty($result['reload_ghl']) ? ' It was already in GoHighLevel — move it there too.' : '');
    } elseif ($action === 'schedule_campaign') {
        $result = mkt_campaign_schedule((int) ($_POST['campaign_id'] ?? 0), (string) ($_POST['start_date'] ?? ''), (string) ($_POST['time'] ?? ''));
        $notice = ($result['scheduled'] ?? 0) . ' asset(s) scheduled.' . (!empty($result['not_ready']) ? ' ' . $result['not_ready'] . ' part(s) are still in drafting or review and were left off.' : '');
    } elseif ($action === 'unschedule') {
        $result = mkt_asset_unschedule($assetId);
        $notice = 'Taken off the calendar.' . (!empty($result['ghl_id']) ? ' Delete GoHighLevel item ' . $result['ghl_id'] . ' too.' : '');
    } elseif ($action === 'loaded') {
        $result = mkt_asset_record_loaded($assetId, (string) ($_POST['ghl_id'] ?? ''));
        $notice = 'GoHighLevel ID recorded.';
    } elseif ($action === 'posted') {
        $result = mkt_asset_mark_posted($assetId, (string) ($_POST['posted_at'] ?? ''), (string) ($_POST['url'] ?? ''), (string) ($_POST['ghl_id'] ?? ''));
        $notice = 'Marked posted.';
    }
    marketing_redirect($baseHref, $result['ok'] ? $query + ['notice' => $notice] : $query + ['error' => (string) $result['error']]);
}

$canUpdate = marketing_can_update();
$counts = mkt_cal_counts();
$conflicts = mkt_cal_conflicts();

$pageTitle = 'Publishing Calendar | NutraAxis Operations';
$pageDescription = 'Approved social and email assets by scheduled date; record the GoHighLevel post or campaign ID once loaded.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$tabLabels = $tabs;
$tabLabels['ready'] .= ' (' . ($counts['Ready'] ?? 0) . ')';
$tabLabels['todo'] .= ' (' . ($counts['Todo'] ?? 0) . ')';

$channelLabel = static fn(string $key): string => $channels[$key]['label'] ?? $key;
$assetLabel = static fn(array $a): string => (string) ($a['Subject'] ?: $a['Title'] ?: 'Asset #' . $a['AssetID']);
$copyId = 0;
$copyButton = static function (array $asset) use (&$copyId): string {
    $copyId++;
    $text = mkt_asset_copy_text($asset, ['Slug' => $asset['Slug'], 'CtaUrl' => $asset['CtaUrl']]);
    if (!empty($asset['Subject'])) {
        $text = 'Subject: ' . $asset['Subject'] . "\n" . (!empty($asset['PreviewText']) ? 'Preview: ' . $asset['PreviewText'] . "\n" : '') . "\n" . $text;
    }

    return '<textarea id="mkt-copy-' . $copyId . '" hidden>' . htmlspecialchars($text) . '</textarea>'
        . '<button type="button" class="btn-text" data-copy="mkt-copy-' . $copyId . '">Copy text</button>';
};
$warningsFor = static function (int $assetId) use ($conflicts): string {
    return isset($conflicts[$assetId]) ? '<div class="form-hint" style="color:var(--danger)">⚠ ' . htmlspecialchars(implode(' ', $conflicts[$assetId])) . '</div>' : '';
};
$hidden = static fn(string $tabKey, int $assetId = 0): string => '<input type="hidden" name="tab" value="' . htmlspecialchars($tabKey) . '" />'
    . ($assetId > 0 ? '<input type="hidden" name="asset_id" value="' . $assetId . '" />' : '');
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Publishing Calendar',
          'lead'       => 'Approved social posts and emails on one schedule (Central time). The coordinator loads each asset into GoHighLevel, records its post or campaign ID, and marks it posted once live. Nothing posts automatically.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? $error);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Schedule</strong>
          <p>
            <?= (int) ($counts['Ready'] ?? 0) ?> approved and ready to schedule ·
            <?= (int) ($counts['Next7'] ?? 0) ?> going out in the next 7 days ·
            <?= (int) ($counts['NeedsLoading'] ?? 0) ?> not yet in GoHighLevel ·
            <?= (int) ($counts['PastDue'] ?? 0) ?> past due, not confirmed posted ·
            <?= (int) ($counts['Posted30'] ?? 0) ?> posted in the last 30 days
          </p>
        </div>
        <div>
          <form method="get" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
            <input type="hidden" name="export" value="csv" />
            <label for="exp_from" class="form-hint">Export</label>
            <input class="form-input" type="date" id="exp_from" name="from" value="<?= $now->format('Y-m-d') ?>" />
            <input class="form-input" type="date" name="to" aria-label="Export to" value="<?= $now->modify('+13 days')->format('Y-m-d') ?>" />
            <button type="submit" class="btn-secondary" title="Scheduled assets with copy-ready text and tracked links, for loading into GoHighLevel">CSV</button>
          </form>
        </div>
      </div>

      <?php if ($tab === 'month'): ?>
        <?php
        $monthStart = DateTimeImmutable::createFromFormat('!Y-m', (string) ($_GET['month'] ?? ''), mkt_cal_tz()) ?: $now->modify('first day of this month')->setTime(0, 0);
        $gridStart = $monthStart->modify('-' . (int) $monthStart->format('w') . ' days');
        $monthEnd = $monthStart->modify('first day of next month');
        $gridEnd = $monthEnd->modify('+' . ((7 - (int) $monthEnd->format('w')) % 7) . ' days');
        $byDay = [];
        foreach (mkt_cal_assets_between($gridStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $gridEnd->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')) as $asset) {
            $byDay[mkt_cal_format($asset['PostedAt'] ?? $asset['ScheduledAt'], 'Y-m-d')][] = $asset;
        }
        $nowTs = time();
        ?>
        <div class="mkt-cal-nav">
          <a class="btn-secondary" href="?tab=month&amp;month=<?= $monthStart->modify('-1 month')->format('Y-m') ?>">‹ Prev</a>
          <h2><?= $monthStart->format('F Y') ?></h2>
          <a class="btn-secondary" href="?tab=month&amp;month=<?= $monthStart->modify('+1 month')->format('Y-m') ?>">Next ›</a>
          <a class="btn-text" href="?tab=month">Today</a>
        </div>
        <div class="admin-table-wrap">
          <table class="mkt-cal">
            <thead><tr><?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?><th><?= $d ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php for ($day = $gridStart; $day < $gridEnd; $day = $day->modify('+1 day')): ?>
                <?php if ($day->format('w') === '0'): ?><tr><?php endif; ?>
                <?php $key = $day->format('Y-m-d'); $classes = trim(($day->format('m') !== $monthStart->format('m') ? 'is-other-month ' : '') . ($key === $now->format('Y-m-d') ? 'is-today' : '')); ?>
                <td class="<?= $classes ?>">
                  <div class="mkt-cal-day"><?= $day->format('j') ?></div>
                  <?php foreach ($byDay[$key] ?? [] as $asset): ?>
                    <?php
                    $posted = $asset['Status'] === 'posted';
                    $pastDue = !$posted && strtotime($asset['ScheduledAt'] . ' UTC') < $nowTs;
                    $class = $posted ? 'is-posted' : ($pastDue ? 'is-past-due' : (empty($asset['ExternalPostID']) ? 'is-unloaded' : ''));
                    $title = $channelLabel((string) $asset['Channel']) . ' · ' . $asset['CampaignName'] . ' · part ' . $asset['SequenceNo'] . ' — ' . $assetLabel($asset)
                        . ($posted ? ' (posted)' : ($pastDue ? ' (past due — confirm posted)' : (empty($asset['ExternalPostID']) ? ' (not yet in GoHighLevel)' : ' (in GoHighLevel: ' . $asset['ExternalPostID'] . ')')))
                        . (isset($conflicts[(int) $asset['AssetID']]) ? ' ⚠ ' . implode(' ', $conflicts[(int) $asset['AssetID']]) : '');
                    ?>
                    <a class="mkt-cal-chip <?= $class ?>" href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>#publishing" title="<?= htmlspecialchars($title) ?>">
                      <?= isset($conflicts[(int) $asset['AssetID']]) ? '⚠ ' : '' ?><?= htmlspecialchars(mkt_cal_format($asset['PostedAt'] ?? $asset['ScheduledAt'], 'g:ia')) ?>
                      <strong><?= htmlspecialchars($channelLabel((string) $asset['Channel'])) ?></strong>
                      <?= htmlspecialchars(mb_strimwidth((string) $asset['CampaignName'], 0, 40, '…')) ?><?= (int) $asset['PartCount'] > 1 ? ' (' . (int) $asset['SequenceNo'] . ')' : '' ?>
                    </a>
                  <?php endforeach; ?>
                </td>
                <?php if ($day->format('w') === '6'): ?></tr><?php endif; ?>
              <?php endfor; ?>
            </tbody>
          </table>
        </div>
        <div class="mkt-cal-legend">
          <span><span class="mkt-cal-chip">In GoHighLevel</span></span>
          <span><span class="mkt-cal-chip is-unloaded">Not yet loaded</span></span>
          <span><span class="mkt-cal-chip is-past-due">Past due — confirm posted</span></span>
          <span><span class="mkt-cal-chip is-posted">Posted</span></span>
          <span>⚠ spacing or series-order warning</span>
        </div>

      <?php elseif ($tab === 'upcoming'): ?>
        <?php
        $days = max(1, min(90, (int) ($_GET['days'] ?? 30)));
        $upcoming = array_filter(
            mkt_cal_assets_between($now->modify('-1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $now->modify('+' . $days . ' days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')),
            static fn(array $a): bool => $a['Status'] === 'scheduled'
        );
        ?>
        <p class="form-hint">Scheduled assets for the next <?= $days ?> days (<a href="?tab=upcoming&amp;days=7">7</a> · <a href="?tab=upcoming&amp;days=30">30</a> · <a href="?tab=upcoming&amp;days=90">90</a>). Open an asset to reschedule or take it off the calendar.</p>
        <?php if ($canUpdate) { marketing_render_field_guide_link('calendar-todo'); } ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>When (Central)</th><th>Channel</th><th>Asset</th><th>GoHighLevel</th><th>Copy</th></tr></thead>
            <tbody>
              <?php if ($upcoming === []): ?>
              <tr><td colspan="5">Nothing scheduled. Approved assets wait on the Ready to schedule tab.</td></tr>
              <?php endif; ?>
              <?php foreach ($upcoming as $asset): ?>
              <tr>
                <td><?= htmlspecialchars(mkt_cal_format($asset['ScheduledAt'])) ?></td>
                <td><?= htmlspecialchars($channelLabel((string) $asset['Channel'])) ?></td>
                <td>
                  <a href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>#publishing"><strong><?= htmlspecialchars($assetLabel($asset)) ?></strong></a>
                  <div class="form-hint"><?= htmlspecialchars((string) $asset['CampaignName']) ?><?= (int) $asset['PartCount'] > 1 ? ' · part ' . (int) $asset['SequenceNo'] . ' of ' . (int) $asset['PartCount'] : '' ?></div>
                  <?= $warningsFor((int) $asset['AssetID']) ?>
                </td>
                <td>
                  <?php if (!empty($asset['ExternalPostID'])): ?>
                  <?= htmlspecialchars((string) $asset['ExternalPostID']) ?>
                  <?php elseif ($canUpdate): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
                    <?= $hidden('upcoming', (int) $asset['AssetID']) ?><input type="hidden" name="action" value="loaded" />
                    <input class="form-input" name="ghl_id" required maxlength="200" placeholder="Post / campaign ID" aria-label="GoHighLevel ID" />
                    <button type="submit" class="btn-secondary">Save</button>
                  </form>
                  <?php else: ?>Not loaded<?php endif; ?>
                </td>
                <td><?= $copyButton($asset) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php elseif ($tab === 'ready'): ?>
        <?php
        $filterId = (int) ($_GET['campaign_id'] ?? 0);
        $ready = mkt_cal_ready($filterId ?: null);
        $groups = [];
        foreach ($ready as $asset) {
            $groups[(int) $asset['CampaignID']][] = $asset;
        }
        $defaults = mkt_cal_default_times();
        $tomorrow = $now->modify('+1 day');
        ?>
        <p class="form-hint">
          Approved assets not yet on the calendar. Lay out a whole campaign — part N goes on the start date plus the campaign's cadence, at each channel's default time (<?= htmlspecialchars(implode(', ', array_map(static fn(string $c, string $t): string => $c . ' ' . $t, array_keys($defaults), $defaults))) ?>; change in Admin settings) — or schedule assets one at a time.
          <?php if ($filterId): ?><a href="?tab=ready">Show all campaigns</a><?php endif; ?>
        </p>
        <?php if ($groups === []): ?>
        <p>No approved assets waiting. Assets appear here once compliance (when required) and editorial approve them in <a href="/marketing/campaigns/?tab=review">Campaign Studio</a>.</p>
        <?php endif; ?>
        <?php if ($groups !== [] && $canUpdate) { marketing_render_field_guide_link('calendar-schedule'); } ?>
        <?php foreach ($groups as $campaignId => $assets): ?>
          <?php $first = $assets[0]; ?>
          <h2 class="hub-section-title"><a href="/marketing/campaigns/campaign.php?id=<?= $campaignId ?>"><?= htmlspecialchars((string) $first['CampaignName']) ?></a></h2>
          <p class="form-hint"><?= htmlspecialchars(MKT_CAMPAIGN_FORMATS[(string) $first['Format']] ?? '') ?><?= (int) $first['PartCount'] > 1 ? ' · ' . (int) $first['PartCount'] . ' parts, ' . (int) $first['CadenceDays'] . ' day(s) apart' : '' ?> · <?= count($assets) ?> approved asset(s) waiting</p>
          <?php if ($canUpdate): ?>
          <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form" style="margin-bottom:0.75rem">
            <?= $hidden('ready') ?><input type="hidden" name="action" value="schedule_campaign" /><input type="hidden" name="campaign_id" value="<?= $campaignId ?>" />
            <input type="hidden" name="campaign_id_filter" value="<?= $filterId ?: '' ?>" />
            <label for="start_<?= $campaignId ?>">Start</label>
            <input class="form-input" type="date" id="start_<?= $campaignId ?>" name="start_date" required value="<?= $tomorrow->format('Y-m-d') ?>" />
            <label for="time_<?= $campaignId ?>">Time</label>
            <input class="form-input" type="time" id="time_<?= $campaignId ?>" name="time" title="Leave blank to use each channel's default time" />
            <button type="submit" class="btn-primary">Lay out campaign</button>
          </form>
          <?php endif; ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Part</th><th>Channel</th><th>Asset</th><th>Approved</th><?php if ($canUpdate): ?><th>Schedule (Central)</th><?php endif; ?></tr></thead>
              <tbody>
                <?php foreach ($assets as $asset): ?>
                <?php $suggest = $tomorrow->modify('+' . (((int) $asset['SequenceNo'] - 1) * max(1, (int) $asset['CadenceDays'])) . ' days')->format('Y-m-d') . 'T' . ($defaults[(string) $asset['Channel']] ?? '09:00'); ?>
                <tr>
                  <td><?= (int) $asset['SequenceNo'] ?></td>
                  <td><?= htmlspecialchars($channelLabel((string) $asset['Channel'])) ?></td>
                  <td>
                    <a href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>"><strong><?= htmlspecialchars($assetLabel($asset)) ?></strong></a>
                    <div class="form-hint"><?= htmlspecialchars(mb_strimwidth(preg_replace('/\s+/', ' ', (string) $asset['Body']) ?? '', 0, 140, '…')) ?></div>
                  </td>
                  <td><?= htmlspecialchars(marketing_format_datetime($asset['EditorialAt'] ?? null)) ?></td>
                  <?php if ($canUpdate): ?>
                  <td>
                    <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
                      <?= $hidden('ready', (int) $asset['AssetID']) ?><input type="hidden" name="action" value="schedule" />
                      <input type="hidden" name="campaign_id_filter" value="<?= $filterId ?: '' ?>" />
                      <input class="form-input" type="datetime-local" name="at" required value="<?= htmlspecialchars($suggest) ?>" aria-label="Scheduled time" />
                      <button type="submit" class="btn-secondary">Schedule</button>
                    </form>
                  </td>
                  <?php endif; ?>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>

      <?php elseif ($tab === 'todo'): ?>
        <?php $loading = mkt_cal_needs_loading(); $pastDue = mkt_cal_past_due(); ?>
        <h2 class="hub-section-title">Load into GoHighLevel (<?= count($loading) ?>)</h2>
        <p class="form-hint">Create each post in GoHighLevel Social Planner (or the email campaign) at the scheduled time using the copy-ready text — it already contains the tracked link. Then record the GoHighLevel ID here. Keep Social Planner RSS auto-post off.</p>
        <?php if ($canUpdate) { marketing_render_field_guide_link('calendar-todo'); } ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>When (Central)</th><th>Channel</th><th>Asset</th><th>Copy</th><?php if ($canUpdate): ?><th>GoHighLevel ID</th><?php endif; ?></tr></thead>
            <tbody>
              <?php if ($loading === []): ?>
              <tr><td colspan="<?= $canUpdate ? 5 : 4 ?>">Everything scheduled is loaded.</td></tr>
              <?php endif; ?>
              <?php foreach ($loading as $asset): ?>
              <tr>
                <td><?= htmlspecialchars(mkt_cal_format($asset['ScheduledAt'])) ?></td>
                <td><?= htmlspecialchars($channelLabel((string) $asset['Channel'])) ?></td>
                <td>
                  <a href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>#publishing"><strong><?= htmlspecialchars($assetLabel($asset)) ?></strong></a>
                  <div class="form-hint"><?= htmlspecialchars((string) $asset['CampaignName']) ?></div>
                  <?= $warningsFor((int) $asset['AssetID']) ?>
                </td>
                <td><?= $copyButton($asset) ?></td>
                <?php if ($canUpdate): ?>
                <td>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
                    <?= $hidden('todo', (int) $asset['AssetID']) ?><input type="hidden" name="action" value="loaded" />
                    <input class="form-input" name="ghl_id" required maxlength="200" placeholder="Post / campaign ID" aria-label="GoHighLevel ID" />
                    <button type="submit" class="btn-secondary">Save</button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <h2 class="hub-section-title">Past due — confirm posted (<?= count($pastDue) ?>)</h2>
        <p class="form-hint">The scheduled time has passed. Check the post is live, then mark it posted with its public URL (email has none). If it didn't go out, open the asset and reschedule it.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Scheduled (Central)</th><th>Channel</th><th>Asset</th><th>GoHighLevel</th><?php if ($canUpdate): ?><th>Mark posted</th><?php endif; ?></tr></thead>
            <tbody>
              <?php if ($pastDue === []): ?>
              <tr><td colspan="<?= $canUpdate ? 5 : 4 ?>">Nothing past due.</td></tr>
              <?php endif; ?>
              <?php foreach ($pastDue as $asset): ?>
              <tr>
                <td><?= htmlspecialchars(mkt_cal_format($asset['ScheduledAt'])) ?></td>
                <td><?= htmlspecialchars($channelLabel((string) $asset['Channel'])) ?></td>
                <td>
                  <a href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>#publishing"><strong><?= htmlspecialchars($assetLabel($asset)) ?></strong></a>
                  <div class="form-hint"><?= htmlspecialchars((string) $asset['CampaignName']) ?></div>
                </td>
                <td><?= htmlspecialchars((string) ($asset['ExternalPostID'] ?? 'Not recorded')) ?></td>
                <?php if ($canUpdate): ?>
                <td>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form">
                    <?= $hidden('todo', (int) $asset['AssetID']) ?><input type="hidden" name="action" value="posted" />
                    <input class="form-input" type="url" name="url" maxlength="1000" placeholder="Public post URL" aria-label="Public post URL" />
                    <?php if (empty($asset['ExternalPostID'])): ?><input class="form-input" name="ghl_id" maxlength="200" placeholder="GoHighLevel ID" aria-label="GoHighLevel ID" /><?php endif; ?>
                    <button type="submit" class="btn-primary">Posted</button>
                  </form>
                </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      <?php else: ?>
        <?php $posted = mkt_cal_posted(); ?>
        <p class="form-hint">The 100 most recent posts. Site visits and key events from GA4 are matched to each asset by its tracked link (<code>utm_content</code> = asset number) and reported on Engagement &amp; Performance.</p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Posted (Central)</th><th>Channel</th><th>Asset</th><th>Live post</th><th>GoHighLevel ID</th></tr></thead>
            <tbody>
              <?php if ($posted === []): ?>
              <tr><td colspan="5">Nothing posted yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($posted as $asset): ?>
              <tr>
                <td><?= htmlspecialchars(mkt_cal_format($asset['PostedAt'])) ?></td>
                <td><?= htmlspecialchars($channelLabel((string) $asset['Channel'])) ?></td>
                <td>
                  <a href="/marketing/campaigns/asset.php?id=<?= (int) $asset['AssetID'] ?>#publishing"><strong><?= htmlspecialchars($assetLabel($asset)) ?></strong></a>
                  <div class="form-hint"><?= htmlspecialchars((string) $asset['CampaignName']) ?> · asset #<?= (int) $asset['AssetID'] ?></div>
                </td>
                <td><?= !empty($asset['ExternalPostUrl']) ? '<a href="' . htmlspecialchars((string) $asset['ExternalPostUrl']) . '" target="_blank" rel="noopener noreferrer">View</a>' : '—' ?></td>
                <td><?= htmlspecialchars((string) ($asset['ExternalPostID'] ?? '—')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <script>
    document.addEventListener('click', (event) => {
      const button = event.target.closest('[data-copy]');
      if (!button) return;
      navigator.clipboard.writeText(document.getElementById(button.dataset.copy).value).then(() => {
        button.textContent = 'Copied';
        setTimeout(() => { button.textContent = 'Copy text'; }, 2000);
      });
    });
  </script>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
