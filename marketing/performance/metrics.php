<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-engagement.php';

auth_require_module_read('marketing-performance');

$baseHref = '/marketing/performance/metrics.php';
$tabs = ['enter' => 'Enter metrics', 'import' => 'Import CSV', 'recent' => 'Recent entries'];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'enter';
$assetId = (int) ($_GET['asset_id'] ?? $_POST['asset_id'] ?? 0);
$importErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save') {
        $result = mkt_asset_metric_save($assetId, (string) ($_POST['as_of'] ?? ''), $_POST['m'] ?? [], (string) ($_POST['note'] ?? ''));
        if ($result['ok']) {
            marketing_redirect($baseHref, ['asset_id' => $assetId, 'notice' => 'Metrics saved for asset #' . $assetId . '. Scores refresh nightly.']);
        }
        $_GET['error'] = $result['error'];
    } elseif ($action === 'delete') {
        mkt_metric_delete((int) ($_POST['metric_id'] ?? 0));
        marketing_redirect($baseHref, ['asset_id' => $assetId, 'notice' => 'Entry deleted.']);
    } elseif ($action === 'import') {
        $result = mkt_metrics_import_csv((string) ($_POST['csv'] ?? ''));
        if ($result['imported'] > 0 && $result['errors'] === []) {
            marketing_redirect($baseHref, ['tab' => 'recent', 'notice' => $result['imported'] . ' rows imported.']);
        }
        $tab = 'import';
        $importErrors = $result['errors'];
        $_GET[$result['imported'] > 0 ? 'notice' : 'error'] = $result['imported'] > 0
            ? $result['imported'] . ' rows imported; ' . count($result['errors']) . ' skipped (below).'
            : 'Nothing imported.';
    }
}

$canUpdate = marketing_can_update();
$assets = mkt_engagement_assets();
$channels = mkt_response_channels();
$mediumByChannel = array_map(static fn(array $c): string => $c['medium'], mkt_channels());

$pageTitle = 'Asset Metrics | NutraAxis Operations';
$pageDescription = 'Record per-post social and email numbers until GoHighLevel is connected.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$fieldInput = static function (string $column, $value): void {
    [$label] = MKT_METRIC_FIELDS[$column];
    echo '<div class="form-group"><label for="m_' . $column . '">' . htmlspecialchars($label) . '</label>'
        . '<input class="form-input" type="text" inputmode="numeric" pattern="[0-9,]*" id="m_' . $column . '" name="m[' . $column . ']" value="'
        . htmlspecialchars((string) $value) . '" /></div>';
};
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/performance/',
          'back_label' => 'Back to Engagement & Performance',
          'category'   => 'Marketing & Research',
          'title'      => 'Asset Metrics',
          'lead'       => 'Per-post likes, comments, shares and reach, and per-email sends, opens and clicks. GoHighLevel is not connected yet, so these are copied in by hand or imported from a CSV. Enter lifetime totals as of a date; the latest entry for each asset counts toward its score. Site visits come from GA4 automatically.',
          'permission' => auth_module_permission_label('marketing-performance'),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      marketing_render_tabs($baseHref, $tabs, $tab);
      ?>

      <?php if ($tab === 'enter'): ?>
        <?php if ($assets === []): ?>
        <p class="form-hint">No scheduled or posted assets yet. Metrics can be entered once a campaign asset is on the calendar.</p>
        <?php else: ?>
        <form method="get" action="<?= htmlspecialchars($baseHref) ?>" class="mkt-inline-form" style="margin-bottom:1rem">
          <label for="pick">Asset</label>
          <select class="form-input" id="pick" name="asset_id" onchange="this.form.submit()">
            <option value="">— choose —</option>
            <?php foreach ($assets as $id => $label): ?>
            <option value="<?= (int) $id ?>"<?= $id === $assetId ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
          <noscript><button type="submit" class="btn-secondary">Open</button></noscript>
        </form>

        <?php if (isset($assets[$assetId])): ?>
          <?php
          $history = mkt_asset_metrics($assetId);
          $latest = $history[0] ?? [];
          $asset = db()->prepare('SELECT Channel FROM dbo.MktAsset WHERE AssetID = :id');
          $asset->execute(['id' => $assetId]);
          $medium = $mediumByChannel[(string) $asset->fetchColumn()] ?? 'social';
          $posted = $_POST['m'] ?? [];
          ?>
          <?php if ($canUpdate): ?>
          <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref . '?asset_id=' . $assetId) ?>">
            <input type="hidden" name="action" value="save" />
            <input type="hidden" name="asset_id" value="<?= $assetId ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label for="as_of">As of</label>
                <input class="form-input" type="date" id="as_of" name="as_of" required max="<?= mkt_cal_now_local()->format('Y-m-d') ?>" value="<?= htmlspecialchars((string) ($_POST['as_of'] ?? mkt_cal_now_local()->format('Y-m-d'))) ?>" />
              </div>
              <?php foreach (MKT_METRIC_FIELDS as $column => [$label, $fieldMedium]): ?>
                <?php if ($fieldMedium === 'both' || $fieldMedium === $medium): ?>
                  <?php $fieldInput($column, $posted[$column] ?? ''); ?>
                <?php endif; ?>
              <?php endforeach; ?>
              <div class="form-group form-grid-full">
                <label for="note">Note</label>
                <input class="form-input" id="note" name="note" maxlength="500" value="<?= htmlspecialchars((string) ($_POST['note'] ?? '')) ?>" placeholder="Optional (e.g. copied from LinkedIn analytics)" />
              </div>
            </div>
            <p class="form-hint">Lifetime totals shown on the platform today. Saving again for the same date replaces that day's entry.<?= $latest ? ' Last entry: ' . htmlspecialchars(marketing_format_date($latest['AsOf'])) . '.' : '' ?></p>
            <div class="form-actions"><button type="submit" class="btn-primary">Save metrics</button></div>
          </form>
          <?php endif; ?>

          <?php if ($history !== []): ?>
          <h2 class="hub-section-title">History for asset #<?= $assetId ?></h2>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>As of</th><th>Source</th>
                  <?php foreach (MKT_METRIC_FIELDS as $column => [$label, $fieldMedium]): ?><?php if ($fieldMedium === 'both' || $fieldMedium === $medium): ?><th><?= htmlspecialchars($label) ?></th><?php endif; ?><?php endforeach; ?>
                  <th>Entered</th><th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $row): ?>
                <tr>
                  <td><?= htmlspecialchars(marketing_format_date($row['AsOf'])) ?></td>
                  <td><?= htmlspecialchars(ucfirst((string) $row['Source'])) ?></td>
                  <?php foreach (MKT_METRIC_FIELDS as $column => [$label, $fieldMedium]): ?><?php if ($fieldMedium === 'both' || $fieldMedium === $medium): ?><td><?= $row[$column] === null ? '—' : number_format((int) $row[$column]) ?></td><?php endif; ?><?php endforeach; ?>
                  <td><?= htmlspecialchars((string) ($row['EnteredByName'] ?? '')) ?><?= $row['Note'] ? '<br /><span class="form-hint">' . htmlspecialchars((string) $row['Note']) . '</span>' : '' ?></td>
                  <td>
                    <?php if ($canUpdate): ?>
                    <form method="post" action="<?= htmlspecialchars($baseHref . '?asset_id=' . $assetId) ?>" onsubmit="return confirm('Delete this entry?');">
                      <input type="hidden" name="action" value="delete" />
                      <input type="hidden" name="asset_id" value="<?= $assetId ?>" />
                      <input type="hidden" name="metric_id" value="<?= (int) $row['MetricID'] ?>" />
                      <button type="submit" class="btn-text">Delete</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>

      <?php elseif ($tab === 'import'): ?>
        <?php if (!$canUpdate): ?>
        <p class="form-hint">You need Marketing update access to import metrics.</p>
        <?php else: ?>
        <p class="form-hint">
          Paste CSV with a header row. Required: <code>asset_id</code> (or <code>external_post_id</code>, the GoHighLevel ID recorded on the calendar) and <code>as_of</code> (YYYY-MM-DD or M/D/YYYY).
          Metric columns (any subset): <code>impressions, reach, likes, comments, shares, saves, link_clicks, video_views, sent, delivered, opens, email_clicks, replies, unsubscribes, bounces, leads</code>.
        </p>
        <?php if ($importErrors !== []): ?>
        <div class="admin-notice is-error" role="alert"><?= implode('<br />', array_map('htmlspecialchars', $importErrors)) ?></div>
        <?php endif; ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref . '?tab=import') ?>">
          <input type="hidden" name="action" value="import" />
          <div class="form-grid">
            <div class="form-group form-grid-full form-group--stacked">
              <label for="csv">CSV</label>
              <textarea class="form-input" id="csv" name="csv" rows="10" required placeholder="asset_id,as_of,impressions,likes,comments,shares&#10;12,2026-10-05,840,31,4,2"><?= htmlspecialchars((string) ($_POST['csv'] ?? '')) ?></textarea>
            </div>
          </div>
          <div class="form-actions"><button type="submit" class="btn-primary">Import</button></div>
        </form>
        <?php endif; ?>

      <?php else: ?>
        <?php $recent = mkt_metrics_recent(); ?>
        <?php if ($recent === []): ?>
        <p class="form-hint">No metrics entered yet.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Asset</th><th>Campaign</th><th>As of</th><th>Source</th><th>Impressions / sent</th><th>Interactions / opens</th><th>Clicks</th><th>Leads</th><th>Entered</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $row): ?>
              <?php
              $isEmail = ($mediumByChannel[(string) $row['Channel']] ?? 'social') === 'email';
              $interactions = (int) $row['Likes'] + (int) $row['Comments'] + (int) $row['Shares'] + (int) $row['Saves'];
              ?>
              <tr>
                <td><a href="<?= htmlspecialchars($baseHref . '?asset_id=' . (int) $row['AssetID']) ?>">#<?= (int) $row['AssetID'] ?> <?= htmlspecialchars($channels[$row['Channel']] ?? (string) $row['Channel']) ?></a></td>
                <td><?= htmlspecialchars(mb_strimwidth((string) $row['CampaignName'], 0, 50, '…')) ?></td>
                <td><?= htmlspecialchars(marketing_format_date($row['AsOf'])) ?></td>
                <td><?= htmlspecialchars(ucfirst((string) $row['Source'])) ?></td>
                <td><?= number_format((int) ($isEmail ? $row['EmailSent'] : ($row['Impressions'] ?? $row['Reach']))) ?></td>
                <td><?= number_format($isEmail ? (int) $row['EmailOpens'] : $interactions) ?></td>
                <td><?= number_format((int) ($isEmail ? $row['EmailClicks'] : $row['LinkClicks'])) ?></td>
                <td><?= $row['Leads'] === null ? '—' : number_format((int) $row['Leads']) ?></td>
                <td><?= htmlspecialchars((string) ($row['EnteredByName'] ?? '')) ?></td>
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
