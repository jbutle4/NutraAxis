<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-ranks.php';

auth_require_module_read('marketing-ranks');

$activeSlug = 'marketing-ranks';
$baseHref = '/marketing/ranks/';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'import') {
        marketing_require_update();
        [$text, $error] = mkt_import_text($_FILES['file'] ?? null, (string) ($_POST['paste'] ?? ''));
        if ($error === null) {
            $result = mkt_rank_import($text, (string) ($_POST['result_date'] ?? ''));
            if ($result['ok']) {
                marketing_redirect($baseHref, ['tab' => 'import', 'notice' => $result['message']]);
            }
            $error = $result['error'];
        }
    }
    if ($action === 'delete_import') {
        if (!marketing_can_delete()) {
            http_response_code(403);
            exit('Forbidden');
        }
        $deleted = mkt_rank_import_delete((int) ($_POST['import_id'] ?? 0));
        marketing_redirect($baseHref, ['tab' => 'import', 'notice' => $deleted ? 'Import deleted with its results.' : 'Import not found.']);
    }
}

$data = mkt_rank_keywords();
$all = mkt_rank_sort($data['rows']);
if (($_GET['export'] ?? '') === 'csv') {
    mkt_rank_export_csv($all);
}

$summary = mkt_rank_summary($all);
$tabs = ['keywords' => 'Keywords (' . $summary['total'] . ')', 'movers' => 'Movers', 'competitors' => 'Competitors', 'import' => 'Import'];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'keywords';
if ($error !== null) {
    $tab = 'import';
}
$filters = [
    'band'    => array_key_exists((string) ($_GET['band'] ?? ''), MKT_RANK_BANDS) ? (string) $_GET['band'] : '',
    'cluster' => (string) ($_GET['cluster'] ?? ''),
    'q'       => trim((string) ($_GET['q'] ?? '')),
];
$clusters = array_values(array_unique(array_filter(array_map(static fn(array $r): string => (string) ($r['Cluster'] ?? ''), $all))));
sort($clusters);
$imports = mkt_rank_imports();
$gscWindow = mkt_rank_gsc_window();
$dropPlaces = max(1, (int) marketing_setting('rank.drop_places', '5'));
$siteUrl = rtrim((string) marketing_setting('pages.site_url', 'https://www.' . mkt_our_host()), '/');

$pageTitle = 'Rank Tracker | NutraAxis Operations';
$pageDescription = 'Keyword positions and competitor movement.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$spark = static function (array $points, bool $inverted = true): string {
    $points = array_values($points);
    if (count($points) < 2) {
        return '<span class="form-hint">—</span>';
    }
    $w = 96;
    $h = 24;
    $min = min($points);
    $max = max($points);
    $span = max(1, $max - $min);
    $coords = [];
    foreach ($points as $i => $p) {
        $x = round($i * ($w - 4) / (count($points) - 1) + 2, 1);
        $norm = ($p - $min) / $span;
        $y = round(($inverted ? $norm : 1 - $norm) * ($h - 6) + 3, 1);
        $coords[] = "$x,$y";
    }
    $better = $inverted ? end($points) <= $points[0] : end($points) >= $points[0];

    return '<svg class="mkt-spark" width="' . $w . '" height="' . $h . '" viewBox="0 0 ' . $w . ' ' . $h . '" aria-hidden="true"><polyline fill="none" stroke="' . ($better ? '#2f7d5b' : '#b4532a') . '" stroke-width="1.8" points="' . implode(' ', $coords) . '"/></svg>';
};
$change = static function (?float $delta): string {
    if ($delta === null) {
        return '<span class="form-hint">—</span>';
    }
    if (abs($delta) < 0.5) {
        return '<span class="mkt-rank-flat">±0</span>';
    }

    return $delta > 0
        ? '<span class="mkt-rank-up">▲ ' . number_format($delta, 1) . '</span>'
        : '<span class="mkt-rank-down">▼ ' . number_format(abs($delta), 1) . '</span>';
};
$position = static function (array $r): string {
    if ($r['Cur'] !== null) {
        return '<strong>' . number_format($r['Cur'], 1) . '</strong><br><span class="form-hint">' . ($r['CurGsc'] ? 'Search Console' : 'Import') . '</span>';
    }
    if ($r['LastPos'] !== null) {
        return '<span class="form-hint">' . number_format($r['LastPos'], 1) . ' on ' . htmlspecialchars(marketing_format_date($r['LastDate'])) . '</span>';
    }

    return '<span class="form-hint">' . ($r['Checked'] ? 'Not found' : 'No data yet') . '</span>';
};
$pageLink = static fn(?string $path): string => $path === null || $path === ''
    ? '<span class="form-hint">—</span>'
    : '<a href="' . htmlspecialchars($siteUrl . $path) . '" target="_blank" rel="noopener">' . htmlspecialchars($path) . '</a>';
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Rank Tracker',
          'lead'       => 'Where our pages rank in Google for the keywords we track, how that is moving, and which competitors share the first page. Our positions come from Search Console each night; competitor positions come from OpenRush or Semrush results you import.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $error);
      marketing_render_tabs($baseHref, $tabs, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong><?= number_format($summary['total']) ?> keyword<?= $summary['total'] === 1 ? '' : 's' ?> tracked</strong>
          <p><?= $summary['top3'] + $summary['page1'] ?> on page 1 (<?= $summary['top3'] ?> in the top 3) · <?= $summary['p2_3'] ?> on positions 11–30 · <?= $summary['low'] ?> lower · <?= $summary['missing'] ?> not found or no data in the last 7 days.</p>
          <p class="form-hint">
            <?= $gscWindow !== null ? 'Search Console through ' . htmlspecialchars(marketing_format_date($gscWindow[1])) . ' (it runs about 2 days behind).' : 'No Search Console data yet.' ?>
            <?= $imports !== [] ? 'Last import: ' . htmlspecialchars(marketing_format_date($imports[0]['ResultDateIso'])) . '.' : 'No results imported yet.' ?>
            Tick “Track rank” on a keyword in the Keyword Universe to add it here.
          </p>
        </div>
        <div>
          <a class="btn-secondary" href="/marketing/keywords/">Choose keywords</a>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>?export=csv">Export CSV</a>
        </div>
      </div>

<?php if ($tab === 'keywords'): ?>
      <?php $rows = mkt_rank_filter($all, $filters); ?>
      <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($baseHref) ?>">
        <div class="audit-filter-grid">
          <div>
            <label for="f_band">Position</label>
            <select class="form-input" id="f_band" name="band">
              <option value="">All</option>
              <?php foreach (MKT_RANK_BANDS as $key => $label): ?>
              <option value="<?= $key ?>" <?= $filters['band'] === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="f_cluster">Cluster</label>
            <select class="form-input" id="f_cluster" name="cluster">
              <option value="">All</option>
              <?php foreach ($clusters as $cluster): ?>
              <option value="<?= htmlspecialchars($cluster) ?>" <?= $filters['cluster'] === $cluster ? 'selected' : '' ?>><?= htmlspecialchars($cluster) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Keyword or page" /></div>
        </div>
        <div class="audit-filter-actions">
          <button type="submit" class="btn-primary">Apply Filters</button>
          <a class="btn-secondary" href="<?= htmlspecialchars($baseHref) ?>">Clear</a>
        </div>
      </form>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Keyword</th><th>Cluster</th><th>Position</th><th>7 days</th><th>28 days</th><th>90-day trend</th><th>Ranking page</th><th>Impressions (28d)</th><th>Clicks (28d)</th><th>Competitors on page 1</th></tr></thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="10"><?= $all === [] ? 'No keywords are tracked yet.' : 'No keywords match these filters.' ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars((string) $r['Keyword']) ?></td>
              <td><?= htmlspecialchars((string) ($r['Cluster'] ?? '')) ?></td>
              <td><?= $position($r) ?></td>
              <td><?= $change($r['Change7']) ?></td>
              <td><?= $change($r['Change28']) ?></td>
              <td><?= $spark($r['Trend']) ?></td>
              <td><?= $pageLink($r['Page']) ?></td>
              <td><?= number_format($r['Impr28']) ?></td>
              <td><?= number_format($r['Clicks28']) ?></td>
              <td><?= $r['CompPage1'] === null ? '<span class="form-hint">—</span>' : (int) $r['CompPage1'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">Position is the average over the last 7 days of data (lower is better; ▲ means it moved up), from Search Console where the exact keyword was searched, otherwise from imported results. Changes compare with the 7 days one week and four weeks earlier. A grey figure is the last position seen, more than 7 days ago. Competitors on page 1 counts the competitors in Settings found in the top 10 of the latest import.</p>

<?php elseif ($tab === 'movers'): ?>
      <?php $movers = mkt_rank_movers($all); ?>
      <div class="mkt-rank-cols">
        <?php foreach (['gains' => 'Biggest gains (28 days)', 'drops' => 'Biggest drops (28 days)'] as $key => $heading): ?>
        <div>
          <h2 class="hub-section-title"><?= $heading ?></h2>
          <div class="admin-table-wrap"><table class="admin-table">
            <thead><tr><th>Keyword</th><th>Now</th><th>Was</th><th>Change</th><th>Page</th></tr></thead>
            <tbody>
              <?php if ($movers[$key] === []): ?>
              <tr><td colspan="5">None yet — changes appear once there are four weeks of positions.</td></tr>
              <?php endif; ?>
              <?php foreach ($movers[$key] as $r): ?>
              <tr>
                <td><?= htmlspecialchars((string) $r['Keyword']) ?><?= $key === 'drops' && $r['Prev28'] <= 10 && $r['Cur'] > 10 ? ' <span class="status-badge status-cancelled">Left page 1</span>' : '' ?></td>
                <td><strong><?= number_format($r['Cur'], 1) ?></strong></td>
                <td><?= number_format($r['Prev28'], 1) ?></td>
                <td><?= $change($r['Change28']) ?></td>
                <td><?= $pageLink($r['Page']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        </div>
        <?php endforeach; ?>
      </div>
      <h2 class="hub-section-title">Page 1 changes</h2>
      <?php if ($movers['reached'] === [] && $movers['left'] === []): ?>
      <p class="form-hint">No tracked keyword reached or left page 1 against four weeks earlier.</p>
      <?php else: ?>
      <ul class="mkt-rank-events">
        <?php foreach ($movers['reached'] as $r): ?>
        <li><span class="mkt-rank-up">▲</span> <strong><?= htmlspecialchars((string) $r['Keyword']) ?></strong> reached page 1 (position <?= number_format($r['Cur'], 1) ?>, was <?= number_format($r['Prev28'], 1) ?>).</li>
        <?php endforeach; ?>
        <?php foreach ($movers['left'] as $r): ?>
        <li><span class="mkt-rank-down">▼</span> <strong><?= htmlspecialchars((string) $r['Keyword']) ?></strong> left page 1 (position <?= number_format($r['Cur'], 1) ?>, was <?= number_format($r['Prev28'], 1) ?>).</li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <p class="form-hint">A tracked keyword that leaves page 1, or drops <?= $dropPlaces ?> or more places against four weeks earlier (Settings: rank.drop_places), opens a rank drop alert in Audit &amp; Issues → Alerts when the alert job runs.</p>

<?php elseif ($tab === 'competitors'): ?>
      <?php $competitors = mkt_rank_competitors($all); ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Competitor</th><th>Domain</th><th>Tracked keywords on page 1</th><th>Keywords seen</th><th>Average position</th><th>Keywords where they outrank us</th><th>Trend (page 1 count)</th></tr></thead>
          <tbody>
            <?php if ($competitors === []): ?>
            <tr><td colspan="7">No competitor domains in Settings (seo.competitor_domains).</td></tr>
            <?php endif; ?>
            <?php foreach ($competitors as $c): ?>
            <tr>
              <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
              <td><?= htmlspecialchars(implode(', ', $c['domains'])) ?></td>
              <td><?= $c['keywords'] > 0 ? $c['page1'] . ' of ' . $summary['total'] : '<span class="form-hint">—</span>' ?></td>
              <td><?= $c['keywords'] ?></td>
              <td><?= $c['avg'] !== null ? number_format($c['avg'], 1) : '<span class="form-hint">—</span>' ?></td>
              <td><?= $c['keywords'] > 0 ? $c['outrank'] : '<span class="form-hint">—</span>' ?></td>
              <td><?= $spark($c['trend'], false) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">From imported results only (each keyword’s latest result per competitor). Competitors are the domains in Admin &amp; Jobs → Settings (seo.competitor_domains); only their names, domains and positions are kept. “Outrank us” counts keywords where they are higher than our current or last position, or where we have none.</p>

<?php else: ?>
      <?php if (marketing_can_update()): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="import" />
        <?php marketing_render_field_guide_link('rank-import'); ?>
        <div class="form-grid">
          <div class="form-group form-grid-full">
            <label for="i_paste">Paste results</label>
            <div>
              <textarea class="form-input" id="i_paste" name="paste" rows="6" placeholder="Paste an OpenRush search visibility or search results (SERP) response, or CSV text"><?= htmlspecialchars((string) ($_POST['paste'] ?? '')) ?></textarea>
              <p class="form-hint">OpenRush: inspect_search_visibility for <?= htmlspecialchars(mkt_our_host()) ?> or a competitor over the tracked keywords, or inspect_serp for one keyword (paste one response, or a JSON list of them). CSV: a Semrush Organic Positions export, or any sheet with keyword, position and url (or domain) columns.</p>
            </div>
          </div>
          <div class="form-group"><label for="i_file">Or file</label><input class="form-input" type="file" id="i_file" name="file" accept=".csv,.json,.txt,text/csv,application/json" /></div>
          <div class="form-group">
            <label for="i_date">Results date</label>
            <div>
              <input class="form-input" type="date" id="i_date" name="result_date" value="<?= htmlspecialchars((string) ($_POST['result_date'] ?? '')) ?>" />
              <p class="form-hint">Leave blank to use the date in the results, or today.</p>
            </div>
          </div>
        </div>
        <p class="form-hint">Only tracked keywords are kept, and only positions for <?= htmlspecialchars(mkt_our_host()) ?> and the competitor domains in Settings; other sites are ignored.</p>
        <div class="form-actions"><button type="submit" class="btn-primary">Import results</button></div>
      </form>
      <?php endif; ?>
      <h2 class="hub-section-title">Import history</h2>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>Imported</th><th>Source</th><th>Results date</th><th>Keywords</th><th>Our positions</th><th>Competitor positions</th><th>Skipped</th><th>By</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if ($imports === []): ?>
          <tr><td colspan="9">No imports yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($imports as $i): ?>
          <tr>
            <td><?= htmlspecialchars(marketing_format_datetime($i['CreatedAt'])) ?></td>
            <td><?= $i['Source'] === 'openrush' ? 'OpenRush' : 'CSV' ?></td>
            <td><?= htmlspecialchars(marketing_format_date($i['ResultDateIso'])) ?></td>
            <td><?= (int) $i['Keywords'] ?></td>
            <td><?= (int) $i['OursRanked'] ?></td>
            <td><?= (int) $i['CompetitorRows'] ?></td>
            <td><?= (int) $i['Skipped'] ?></td>
            <td><?= htmlspecialchars((string) ($i['CreatedByName'] ?? '')) ?></td>
            <td>
              <?php if (marketing_can_delete()): ?>
              <form method="post" action="<?= htmlspecialchars($baseHref) ?>" onsubmit="return confirm('Delete this import and its results?');">
                <input type="hidden" name="action" value="delete_import" /><input type="hidden" name="import_id" value="<?= (int) $i['ImportID'] ?>" />
                <button type="submit" class="btn-text">Delete</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
  </main>
<?php require dirname(__DIR__, 2) . '/includes/footer.php'; ?>
