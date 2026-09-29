<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-performance.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-performance');

$activeSlug = 'marketing-performance';
$baseHref = '/marketing/performance/';
$tabs = [
    'overview' => 'Overview',
    'assets'   => 'Campaign assets',
    'search'   => 'Search queries',
    'landing'  => 'Landing pages',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'overview';
$days = mkt_perf_days($_GET['days'] ?? 28);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    if ((string) ($_POST['action'] ?? '') === 'refresh') {
        $messages = [];
        $errors = [];
        foreach (['seo-gsc-ingest' => 'Search Console', 'seo-ga4-ingest' => 'GA4'] as $code => $label) {
            $result = process_execute($code, [], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            if (!empty($result['ok'])) {
                $messages[] = $label . ': ' . (string) ($result['message'] ?? 'done');
            } else {
                $errors[] = $label . ': ' . (string) ($result['error'] ?? 'failed');
            }
        }
        marketing_redirect($baseHref, array_filter([
            'tab'    => $tab,
            'days'   => $days,
            'notice' => implode(' · ', $messages),
            'error'  => implode(' · ', $errors),
        ]));
    }
}

$windows = mkt_perf_windows($days);
$status = mkt_analytics_status();
$canUpdate = marketing_can_update();
$channels = mkt_channels();

$pageTitle = 'Engagement & Performance | NutraAxis Operations';
$pageDescription = 'Search Console and GA4 results for nutraaxislabs.com and the traffic each campaign asset drove.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$range = static fn(?array $w): string => $w === null ? 'no data yet' : marketing_format_date($w[0]) . ' – ' . marketing_format_date($w[1]);
$lastRun = static function (?array $run): string {
    if ($run === null) {
        return 'not run yet';
    }
    return marketing_format_datetime((string) $run['StartedAt']) . ' (' . strtolower((string) $run['Status']) . ')';
};
$num = static fn(mixed $v): string => number_format((float) $v);
$delta = static function (float $current, float $prior, bool $lowerIsBetter = false): string {
    $label = mkt_perf_change($current, $prior);
    if ($label === '') {
        return '';
    }
    $better = $lowerIsBetter ? $current < $prior : $current > $prior;
    $class = $label === 'new' || $current === $prior ? '' : ($better ? ' is-up' : ' is-down');
    return '<span class="mkt-delta' . $class . '">' . htmlspecialchars($label) . '</span>';
};
$kpi = static function (string $label, string $value, string $prior, string $deltaHtml = ''): void {
    echo '<div class="mkt-kpi"><span class="mkt-kpi-label">' . htmlspecialchars($label) . '</span>'
        . '<span class="mkt-kpi-value">' . htmlspecialchars($value) . ' ' . $deltaHtml . '</span>'
        . '<span class="mkt-kpi-prior">prior: ' . htmlspecialchars($prior) . '</span></div>';
};
$pageLink = static function (?int $pageId, ?string $path, string $fallback): string {
    if ($pageId) {
        return '<a href="/marketing/pages/view.php?id=' . $pageId . '">' . htmlspecialchars((string) $path) . '</a>';
    }
    return htmlspecialchars($fallback);
};
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Engagement & Performance',
          'lead'       => 'How nutraaxislabs.com performs in Google search and GA4, and the site traffic each campaign asset\'s tagged link drove. Data refreshes nightly from Search Console and GA4.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      marketing_render_tabs($baseHref, $tabs, $tab, ['days' => $days]);
      ?>

      <div class="status-banner">
        <div>
          <strong><?= htmlspecialchars(MKT_PERF_PERIODS[$days]) ?></strong>
          <p>
            Search Console <?= htmlspecialchars($range($windows['gsc'])) ?> (last pull <?= htmlspecialchars($lastRun($status['seo-gsc-ingest']['run'])) ?>) ·
            GA4 <?= htmlspecialchars($range($windows['ga4'])) ?> (last pull <?= htmlspecialchars($lastRun($status['seo-ga4-ingest']['run'])) ?>).
          </p>
          <p class="form-hint">Search Console data lags about 3 days and GA4 about 1 day, so each window ends on that source's latest day. "Prior" is the same length window just before it.</p>
        </div>
        <div>
          <form method="get" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form" style="display:inline-flex">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
            <select class="form-input" id="f_days" name="days" aria-label="Period" onchange="this.form.submit()">
              <?php foreach (MKT_PERF_PERIODS as $value => $label): ?>
              <option value="<?= (int) $value ?>"<?= $value === $days ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="btn-secondary">Go</button></noscript>
          </form>
          <?php if ($canUpdate): ?>
          <form method="post" action="<?= htmlspecialchars($baseHref . '?' . http_build_query(['tab' => $tab, 'days' => $days])) ?>" style="display:inline">
            <input type="hidden" name="action" value="refresh" />
            <button type="submit" class="btn-secondary" title="Pull the latest Search Console and GA4 data now (under a minute)">Refresh data now</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($tab === 'overview'): ?>
        <?php
        $search = mkt_perf_search_totals($windows['gsc']);
        $searchPrior = mkt_perf_search_totals($windows['gsc_prior']);
        $ga4 = mkt_perf_ga4_totals($windows['ga4']);
        $ga4Prior = mkt_perf_ga4_totals($windows['ga4_prior']);
        $ctr = static fn(array $r): float => (float) $r['Impressions'] > 0 ? (float) $r['Clicks'] / (float) $r['Impressions'] * 100 : 0.0;
        $engaged = static fn(array $r): float => (float) $r['Sessions'] > 0 ? (float) $r['EngagedSessions'] / (float) $r['Sessions'] * 100 : 0.0;
        $pos = static fn(array $r): string => $r['Position'] === null ? '—' : (string) $r['Position'];
        ?>
        <h2>Google search</h2>
        <div class="mkt-kpis">
          <?php
          $kpi('Clicks', $num($search['Clicks']), $num($searchPrior['Clicks']), $delta((float) $search['Clicks'], (float) $searchPrior['Clicks']));
          $kpi('Impressions', $num($search['Impressions']), $num($searchPrior['Impressions']), $delta((float) $search['Impressions'], (float) $searchPrior['Impressions']));
          $kpi('Click-through rate', round($ctr($search), 1) . '%', round($ctr($searchPrior), 1) . '%');
          $kpi('Average position', $pos($search), $pos($searchPrior), $search['Position'] !== null && $searchPrior['Position'] !== null ? $delta((float) $search['Position'], (float) $searchPrior['Position'], true) : '');
          ?>
        </div>

        <h2>Site visits (GA4)</h2>
        <div class="mkt-kpis">
          <?php
          $kpi('Sessions', $num($ga4['Sessions']), $num($ga4Prior['Sessions']), $delta((float) $ga4['Sessions'], (float) $ga4Prior['Sessions']));
          $pts = (int) round($engaged($ga4)) - (int) round($engaged($ga4Prior));
          $kpi('Engaged sessions', round($engaged($ga4)) . '%', round($engaged($ga4Prior)) . '%', (float) $ga4Prior['Sessions'] > 0 && $pts !== 0
              ? '<span class="mkt-delta ' . ($pts > 0 ? 'is-up' : 'is-down') . '">' . ($pts > 0 ? '+' : '−') . abs($pts) . ' pts</span>'
              : '');
          $kpi('Key events', $num($ga4['KeyEvents']), $num($ga4Prior['KeyEvents']), $delta((float) $ga4['KeyEvents'], (float) $ga4Prior['KeyEvents']));
          $kpi('Purchases', $num($ga4['Transactions']), $num($ga4Prior['Transactions']), $delta((float) $ga4['Transactions'], (float) $ga4Prior['Transactions']));
          $kpi('Revenue', marketing_format_usd((float) $ga4['Revenue']), marketing_format_usd((float) $ga4Prior['Revenue']), $delta((float) $ga4['Revenue'], (float) $ga4Prior['Revenue']));
          ?>
        </div>

        <h2>Sessions by channel</h2>
        <?php $channelRows = mkt_perf_channels($windows); ?>
        <?php if ($channelRows === []): ?>
        <p class="form-hint">No GA4 data yet. It loads nightly, or use "Refresh data now".</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Channel</th><th>Sessions</th><th>Share</th><th>Engaged</th><th>Key events</th><th>Prior sessions</th><th>Change</th></tr></thead>
            <tbody>
              <?php foreach ($channelRows as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) $row['Channel']) ?></td>
                <td><?= $num($row['Sessions']) ?></td>
                <td><?= mkt_perf_rate((float) $row['Sessions'], (float) $ga4['Sessions']) ?></td>
                <td><?= mkt_perf_rate((float) $row['EngagedSessions'], (float) $row['Sessions']) ?></td>
                <td><?= $num($row['KeyEvents']) ?></td>
                <td><?= $num($row['PriorSessions']) ?></td>
                <td><?= $delta((float) $row['Sessions'], (float) $row['PriorSessions']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <h2>Top search queries</h2>
        <?php $queries = array_slice(mkt_perf_queries($windows, 10), 0, 10); ?>
        <?php if ($queries === []): ?>
        <p class="form-hint">No Search Console data in this window.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Query</th><th>Clicks</th><th>Impressions</th><th>Avg position</th><th>Top page</th></tr></thead>
            <tbody>
              <?php foreach ($queries as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) $row['Query']) ?></td>
                <td><?= $num($row['Clicks']) ?></td>
                <td><?= $num($row['Impressions']) ?></td>
                <td><?= htmlspecialchars((string) ($row['Position'] ?? '—')) ?></td>
                <td><?= $pageLink($row['PageID'] !== null ? (int) $row['PageID'] : null, $row['Path'], (string) $row['Page']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p><a href="<?= htmlspecialchars($baseHref . '?' . http_build_query(['tab' => 'search', 'days' => $days])) ?>">All search queries →</a></p>
        <?php endif; ?>

      <?php elseif ($tab === 'assets'): ?>
        <?php $assets = mkt_perf_assets($windows['ga4']); ?>
        <p class="form-hint">
          Every campaign asset's call-to-action link carries <code>utm_campaign</code> = the campaign slug and <code>utm_content</code> = the asset ID.
          GA4 sessions arriving with those tags are credited to the asset. Scheduled and posted assets are listed even before their first visit.
        </p>
        <?php if ($assets === []): ?>
        <p class="form-hint">No scheduled or posted assets yet, and no tagged visits matched an asset.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Asset</th><th>Campaign</th><th>Channel</th><th>Status</th><th>Posted</th><th>Sessions</th><th>Engaged</th><th>Key events</th><th>Purchases</th><th>All-time sessions</th><th>Last visit</th></tr></thead>
            <tbody>
              <?php foreach ($assets as $row): ?>
              <?php $title = trim((string) ($row['Title'] ?: $row['Subject'])) ?: 'Part ' . (int) $row['SequenceNo']; ?>
              <tr>
                <td><a href="/marketing/campaigns/asset.php?id=<?= (int) $row['AssetID'] ?>">#<?= (int) $row['AssetID'] ?> <?= htmlspecialchars(mb_strimwidth($title, 0, 60, '…')) ?></a></td>
                <td><a href="/marketing/campaigns/campaign.php?id=<?= (int) $row['CampaignID'] ?>"><?= htmlspecialchars((string) $row['CampaignName']) ?></a></td>
                <td><?= htmlspecialchars($channels[$row['Channel']]['label'] ?? (string) $row['Channel']) ?></td>
                <td><?= mkt_render_badge((string) $row['Status'], MKT_ASSET_STATUSES) ?></td>
                <td>
                  <?= htmlspecialchars(marketing_format_date($row['PostedAt'])) ?>
                  <?php if (!empty($row['ExternalPostUrl'])): ?><br /><a href="<?= htmlspecialchars((string) $row['ExternalPostUrl']) ?>" target="_blank" rel="noopener">View post</a><?php endif; ?>
                </td>
                <td><?= $num($row['Sessions']) ?></td>
                <td><?= mkt_perf_rate((float) $row['EngagedSessions'], (float) $row['Sessions']) ?></td>
                <td><?= $num($row['KeyEvents']) ?></td>
                <td><?= $num($row['Transactions']) ?><?= (float) $row['Revenue'] > 0 ? ' (' . marketing_format_usd((float) $row['Revenue']) . ')' : '' ?></td>
                <td><?= $num($row['AllSessions']) ?></td>
                <td><?= htmlspecialchars(marketing_format_date($row['LastSeen'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <h2>Other tagged traffic</h2>
        <?php $other = mkt_perf_other_tagged($windows['ga4']); ?>
        <p class="form-hint">Visits with UTM tags that did not match a campaign asset — links from bios, older posts, partners, or hand-built URLs.</p>
        <?php if ($other === []): ?>
        <p class="form-hint">None in this window.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Source</th><th>Medium</th><th>Campaign</th><th>Content</th><th>Sessions</th><th>Engaged</th><th>Key events</th></tr></thead>
            <tbody>
              <?php foreach ($other as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) $row['Source']) ?></td>
                <td><?= htmlspecialchars((string) $row['Medium']) ?></td>
                <td><?= htmlspecialchars((string) $row['Campaign']) ?></td>
                <td><?= htmlspecialchars((string) $row['Content']) ?></td>
                <td><?= $num($row['Sessions']) ?></td>
                <td><?= mkt_perf_rate((float) $row['EngagedSessions'], (float) $row['Sessions']) ?></td>
                <td><?= $num($row['KeyEvents']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      <?php elseif ($tab === 'search'): ?>
        <?php $queries = mkt_perf_queries($windows, 200); ?>
        <p class="form-hint">Queries where nutraaxislabs.com appeared in Google, with the page that earned the most clicks for each. "Not mapped" means no page targets the query yet — map it from the page's detail screen.</p>
        <?php if ($queries === []): ?>
        <p class="form-hint">No Search Console data in this window.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Query</th><th>Clicks</th><th>Prior clicks</th><th>Impressions</th><th>CTR</th><th>Avg position</th><th>Top page</th><th>Pages shown</th><th>Keyword map</th></tr></thead>
            <tbody>
              <?php foreach ($queries as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) $row['Query']) ?></td>
                <td><?= $num($row['Clicks']) ?> <?= $delta((float) $row['Clicks'], (float) $row['PriorClicks']) ?></td>
                <td><?= $num($row['PriorClicks']) ?></td>
                <td><?= $num($row['Impressions']) ?></td>
                <td><?= mkt_perf_rate((float) $row['Clicks'], (float) $row['Impressions']) ?></td>
                <td><?= htmlspecialchars((string) ($row['Position'] ?? '—')) ?></td>
                <td><?= $pageLink($row['PageID'] !== null ? (int) $row['PageID'] : null, $row['Path'], (string) $row['Page']) ?></td>
                <td><?= (int) $row['PageCount'] ?></td>
                <td><?= (int) $row['Mapped'] ? 'Mapped' : '<span class="form-hint">Not mapped</span>' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      <?php else: ?>
        <?php $landing = mkt_perf_landing($windows, 200); ?>
        <p class="form-hint">Pages where GA4 sessions started, with search clicks to the same page. "(not set)" is GA4's label for sessions without a recorded landing page.</p>
        <?php if ($landing === []): ?>
        <p class="form-hint">No GA4 data in this window.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Landing page</th><th>Type</th><th>Sessions</th><th>Engaged</th><th>Avg engagement</th><th>Key events</th><th>Purchases</th><th>Search clicks</th><th>Issues</th></tr></thead>
            <tbody>
              <?php foreach ($landing as $row): ?>
              <tr>
                <td><?= $pageLink($row['PageID'] !== null ? (int) $row['PageID'] : null, $row['Path'], (string) $row['LandingPage']) ?></td>
                <td><?= htmlspecialchars($row['PageType'] !== null ? (MKT_PAGE_TYPES[$row['PageType']] ?? (string) $row['PageType']) : '—') ?></td>
                <td><?= $num($row['Sessions']) ?></td>
                <td><?= mkt_perf_rate((float) $row['EngagedSessions'], (float) $row['Sessions']) ?></td>
                <td><?= (float) $row['Sessions'] > 0 ? (int) round((float) $row['EngagementSeconds'] / (float) $row['Sessions']) . 's' : '—' ?></td>
                <td><?= $num($row['KeyEvents']) ?></td>
                <td><?= $num($row['Transactions']) ?></td>
                <td><?= $row['PageID'] !== null ? $num($row['SearchClicks']) : '—' ?></td>
                <td><?= $row['PageID'] !== null ? mkt_page_issue_badges((string) ($row['Issues'] ?? '')) : '' ?></td>
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
