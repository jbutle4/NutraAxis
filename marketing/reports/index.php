<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-reports.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-reports');

$activeSlug = 'marketing-reports';
$baseHref = '/marketing/reports/';
$viewHref = '/marketing/reports/view.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $period = mkt_report_period((string) ($_POST['month'] ?? ''));
    $back = static fn(array $result, string $ok): never => marketing_redirect($baseHref, ['month' => (string) ($_POST['month'] ?? '')] + (!empty($result['ok'])
        ? ['notice' => (string) ($result['message'] ?? $ok)]
        : ['error' => (string) ($result['error'] ?? 'Something went wrong.')]));
    if ($period === null || $period['partial']) {
        $back(['ok' => false, 'error' => 'Only a closed month can be frozen, reviewed or given highlights.'], '');
    }
    switch ((string) ($_POST['action'] ?? '')) {
        case 'review':
            $back(mkt_report_mark_reviewed($period['ym'], (string) ($_POST['note'] ?? '')), 'Marked reviewed. The review task closes by itself.');
        case 'freeze':
            mkt_report_freeze($period, marketing_user_id(), mkt_report_get($period['ym']) !== null);
            // no break — a fresh snapshot gets fresh highlights
        case 'highlights':
            $result = process_execute('report-highlights', ['month' => $period['ym'], 'force' => 1], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            $back($result, 'Highlights written.');
        default:
            $back(['ok' => false, 'error' => 'Unknown action.'], '');
    }
}

mkt_report_freeze_due();
$saved = mkt_reports_saved();
$months = [];
foreach (mkt_report_months() as $ym => $label) {
    $period = mkt_report_period($ym);
    if ($period === null) {
        continue;
    }
    $window = [$period['start'], $period['end']];
    $months[$ym] = ['period' => $period, 'saved' => $saved[$ym] ?? null, 'gsc' => mkt_perf_search_totals($window), 'ga4' => mkt_perf_ga4_totals($window)];
}
$selected = isset($months[(string) ($_GET['month'] ?? '')]) ? (string) $_GET['month'] : mkt_report_default_month();
$sel = $months[$selected] ?? null;
$digests = mkt_digests_list(8);
$canUpdate = marketing_can_update();

$pageTitle = 'Reports | NutraAxis Operations';
$pageDescription = 'Monthly marketing reports for Word and PDF, and the weekly digests.';
$hubBack = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$statusText = static function (array $m): string {
    $p = $m['period'];
    if ($m['saved'] !== null) {
        return 'Frozen ' . marketing_format_datetime($m['saved']['FrozenAt']);
    }

    return $p['partial'] ? 'Month to date, through ' . mkt_report_short_date($p['end']) : 'Live — freezes once analytics data is complete';
};
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $hubBack['href'],
          'back_label' => $hubBack['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Reports',
          'lead'       => 'A monthly marketing report — search, traffic, rankings, backlinks, site health, content, research and AI spend — to print, save as PDF or download for Word. The weekly digests are listed below.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

      <?php if ($sel !== null): $p = $sel['period']; $s = $sel['saved']; ?>
      <div class="status-banner">
        <div>
          <strong><?= htmlspecialchars($p['label']) ?> report</strong>
          <p><?= htmlspecialchars($statusText($sel)) ?>.
            <?php if ($s !== null): ?>
              <?= (int) $s['HasHighlights'] === 1 ? 'AI highlights written.' : ($s['HighlightsError'] ? 'Highlights failed: ' . htmlspecialchars((string) $s['HighlightsError']) : 'Highlights are written by the next scheduled run.') ?>
              <?= $s['ReviewedAt'] !== null ? 'Reviewed by ' . htmlspecialchars((string) ($s['ReviewedByName'] ?? 'someone')) . ' ' . htmlspecialchars(marketing_format_datetime($s['ReviewedAt'])) . ($s['ReviewNote'] ? ' — ' . htmlspecialchars((string) $s['ReviewNote']) : '') . '.' : 'Not reviewed yet.' ?>
            <?php endif; ?>
          </p>
          <p class="form-hint">A closed month is frozen from the 2nd of the next month, once Search Console and Analytics have data through its last day (by the <?= (int) marketing_setting('reports.freeze_after_days', '5') ?>th at the latest). Until then, and for the current month, the report is built from live data each time it is opened.</p>
          <?php if ($canUpdate && !$p['partial']): ?>
          <div style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-top:.5rem">
            <?php if ($s !== null && $s['ReviewedAt'] === null): ?>
            <form method="post" action="<?= htmlspecialchars($baseHref) ?>" class="admin-form" style="display:flex;gap:.5rem;align-items:center;margin:0">
              <input type="hidden" name="action" value="review" /><input type="hidden" name="month" value="<?= htmlspecialchars($p['ym']) ?>" />
              <input class="form-input" type="text" name="note" maxlength="1000" placeholder="Note (optional)" aria-label="Review note" style="min-width:16rem" />
              <button type="submit" class="btn-primary">Mark reviewed</button>
            </form>
            <?php endif; ?>
            <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="margin:0">
              <input type="hidden" name="action" value="freeze" /><input type="hidden" name="month" value="<?= htmlspecialchars($p['ym']) ?>" />
              <button type="submit" class="btn-text" <?= $s !== null ? 'onclick="return confirm(\'Replace the saved figures with current data and rewrite the highlights?\')"' : '' ?>><?= $s !== null ? 'Refresh snapshot' : 'Freeze now' ?></button>
            </form>
            <?php if ($s !== null): ?>
            <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="margin:0">
              <input type="hidden" name="action" value="highlights" /><input type="hidden" name="month" value="<?= htmlspecialchars($p['ym']) ?>" />
              <button type="submit" class="btn-text"><?= (int) $s['HasHighlights'] === 1 ? 'Rewrite highlights' : 'Write highlights now' ?></button>
            </form>
            <?php endif; ?>
            <?php marketing_render_field_guide_link('report-review'); ?>
          </div>
          <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;gap:.5rem;align-items:flex-start">
          <a class="btn-primary" href="<?= htmlspecialchars($viewHref . '?month=' . $p['ym']) ?>" target="_blank" rel="noopener">Open report</a>
          <a class="btn-text" href="<?= htmlspecialchars($viewHref . '?month=' . $p['ym'] . '&format=docx') ?>">Download Word</a>
        </div>
      </div>
      <?php endif; ?>

      <h2 class="section-title" style="margin-top:1.5rem">Monthly reports</h2>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Month</th><th>Status</th><th>Reviewed</th><th>Search clicks</th><th>Impressions</th><th>Sessions</th><th>Key events</th><th>Report</th></tr></thead>
          <tbody>
          <?php foreach ($months as $ym => $m): $s = $m['saved']; ?>
            <tr<?= $ym === $selected ? ' class="is-selected"' : '' ?>>
              <td><a href="<?= htmlspecialchars($baseHref . '?month=' . $ym) ?>"><strong><?= htmlspecialchars($m['period']['label']) ?></strong></a></td>
              <td><?= htmlspecialchars($statusText($m)) ?></td>
              <td><?= $s !== null && $s['ReviewedAt'] !== null ? htmlspecialchars((string) ($s['ReviewedByName'] ?? '') . ', ' . marketing_format_date(substr((string) $s['ReviewedAt'], 0, 10))) : '—' ?></td>
              <td><?= number_format((float) $m['gsc']['Clicks']) ?></td>
              <td><?= number_format((float) $m['gsc']['Impressions']) ?></td>
              <td><?= number_format((float) $m['ga4']['Sessions']) ?></td>
              <td><?= number_format((float) $m['ga4']['KeyEvents']) ?></td>
              <td>
                <a href="<?= htmlspecialchars($viewHref . '?month=' . $ym) ?>" target="_blank" rel="noopener">Open</a> ·
                <a href="<?= htmlspecialchars($viewHref . '?month=' . $ym . '&format=docx') ?>">Word</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="form-hint">The columns show live figures; a frozen report keeps the figures it was saved with.</p>

      <h2 class="section-title" style="margin-top:1.5rem">Weekly digests</h2>
      <p class="form-hint">Each Monday the digest summarizes the week before and recommends actions; they live in <a href="/marketing/performance/?tab=digests">Engagement &amp; Performance</a>.</p>
      <?php if ($digests === []): ?>
        <p class="form-hint">No digests yet. The first appears the Monday after there is a full week of engagement data.</p>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Week</th><th>Status</th><th>Tasks</th></tr></thead>
          <tbody>
          <?php foreach ($digests as $row): ?>
            <tr>
              <td><a href="/marketing/performance/digest.php?id=<?= (int) $row['DigestID'] ?>"><?= htmlspecialchars(marketing_format_date($row['PeriodStart']) . ' – ' . marketing_format_date($row['PeriodEnd'])) ?></a></td>
              <td><?= $row['Status'] === 'sparse' ? 'Not enough data' : 'Ready' ?></td>
              <td><?= (int) $row['TaskCount'] ?></td>
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
