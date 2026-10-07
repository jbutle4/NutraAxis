<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-issues.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-issues');

$activeSlug = 'marketing-issues';
$baseHref = '/marketing/issues/';
$tabs = [
    'open'     => 'Open',
    'fixed'    => 'Fixed',
    'resolved' => 'Verified',
    'ignored'  => 'Ignored',
    'audits'   => 'Audits',
    'alerts'   => 'Alerts',
];
$tab = array_key_exists((string) ($_GET['tab'] ?? ''), $tabs) ? (string) $_GET['tab'] : 'open';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $job = static function (string $code, array $params, string $returnTab) use ($baseHref): never {
        $result = process_execute($code, $params, PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
        marketing_redirect($baseHref, ['tab' => $returnTab] + (!empty($result['ok'])
            ? ['notice' => (string) ($result['message'] ?? 'Done.')]
            : ['error' => (string) ($result['error'] ?? 'The job failed.')]));
    };
    if ($action === 'crawl') {
        $job('seo-crawl', [], 'audits');
    }
    if ($action === 'openrush') {
        $json = trim((string) ($_POST['audit_json'] ?? ''));
        if ($json === '' || json_decode($json) === null) {
            marketing_redirect($baseHref, ['tab' => 'audits', 'error' => 'Paste the full JSON result of the OpenRush audit_site tool.']);
        }
        if (strlen($json) > 2_000_000) {
            marketing_redirect($baseHref, ['tab' => 'audits', 'error' => 'That audit is too large to import (2 MB limit).']);
        }
        $job('seo-openrush-import', ['audit_json' => $json], 'audits');
    }
    if ($action === 'check_alerts') {
        $job('seo-alerts', [], 'alerts');
    }
    if ($action === 'acknowledge') {
        $done = mkt_alert_acknowledge((int) ($_POST['alert_id'] ?? 0));
        marketing_redirect($baseHref, ['tab' => 'alerts'] + ($done['ok'] ? ['notice' => 'Alert acknowledged.'] : ['error' => $done['error']]));
    }
}

$filters = [
    'tab'      => $tab,
    'status'   => (string) ($_GET['status'] ?? ''),
    'severity' => (string) ($_GET['severity'] ?? ''),
    'owner'    => (string) ($_GET['owner'] ?? ''),
    'q'        => trim((string) ($_GET['q'] ?? '')),
];
$counts = mkt_issue_counts();
$canUpdate = marketing_can_update();
$lastCrawl = mkt_analytics_status()['seo-crawl']['run'] ?? null;

$pageTitle = 'Audit & Issues | NutraAxis Operations';
$pageDescription = 'Site audit findings grouped by check, with fix specs, verification and alerts.';
$back = app_module_hub_back_link($activeSlug);

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$select = static function (string $name, array $options, string $current, string $id): void {
    echo '<select class="form-input" id="' . htmlspecialchars($id) . '" name="' . htmlspecialchars($name) . '">';
    foreach ($options as $key => $label) {
        echo '<option value="' . htmlspecialchars((string) $key) . '"' . ((string) $key === $current ? ' selected' : '') . '>' . htmlspecialchars((string) $label) . '</option>';
    }
    echo '</select>';
};
$tabLabels = $tabs;
$tabLabels['open'] .= ' (' . ($counts['new'] + $counts['open']) . ')';
$tabLabels['fixed'] .= ' (' . $counts['fixed'] . ')';
$tabLabels['resolved'] .= ' (' . $counts['verified'] . ')';
$tabLabels['ignored'] .= ' (' . $counts['ignored'] . ')';
$tabLabels['alerts'] .= ' (' . $counts['alerts'] . ')';
$exportQuery = array_filter(['tab' => $tab, 'status' => $filters['status'], 'severity' => $filters['severity'], 'owner' => $filters['owner'], 'q' => $filters['q']]);
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => 'Marketing & Research',
          'title'      => 'Audit & Issues',
          'lead'       => 'Every check the site crawler (and imported OpenRush audits) found, grouped into one issue per problem with the pages it is on. Mark an issue fixed and the portal recrawls those pages to confirm; an issue that comes back is reopened.',
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      marketing_render_tabs($baseHref, $tabLabels, $tab);
      ?>

      <div class="status-banner">
        <div>
          <strong>Site health</strong>
          <p>
            <?= $counts['new'] + $counts['open'] ?> open issues (<?= $counts['new'] ?> new, <?= $counts['high_open'] ?> high severity) on <?= number_format($counts['open_urls']) ?> page URLs ·
            <?= $counts['fixed'] ?> being rechecked · <?= $counts['verified'] ?> verified · <?= $counts['ignored'] ?> ignored ·
            <?= $counts['alerts'] ?> open alert<?= $counts['alerts'] === 1 ? '' : 's' ?>.
          </p>
          <p class="form-hint">
            The crawler audits every active page each Monday (last run <?= $lastCrawl ? htmlspecialchars(marketing_format_datetime((string) $lastCrawl['StartedAt']) . ', ' . strtolower((string) $lastCrawl['Status'])) : 'not yet' ?>).
            It reads each page's HTML as delivered, before any browser scripts run.
          </p>
        </div>
        <div>
          <?php if (in_array($tab, ['open', 'fixed', 'resolved', 'ignored'], true)): ?>
          <a class="btn-secondary" href="/marketing/issues/export.php?<?= htmlspecialchars(http_build_query(['format' => 'csv'] + $exportQuery)) ?>">Export CSV</a>
          <a class="btn-secondary" href="/marketing/issues/export.php?<?= htmlspecialchars(http_build_query(['format' => 'md'] + $exportQuery)) ?>" title="Markdown hand-off with each issue's fix spec (or advice) and pages">Developer packet</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if (isset(MKT_ISSUE_TABS[$tab])): ?>
        <?php $issues = mkt_issues_list($filters); ?>
        <form class="po-filter audit-filter" method="get" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>" />
          <div class="audit-filter-grid">
            <?php if ($tab === 'open'): ?>
            <div><label for="f_status">Status</label><?php $select('status', ['' => 'New and open', 'new' => 'New only', 'open' => 'Open only'], $filters['status'], 'f_status'); ?></div>
            <?php endif; ?>
            <div><label for="f_sev">Severity</label><?php $select('severity', ['' => 'All'] + MKT_ISSUE_SEVERITIES, $filters['severity'], 'f_sev'); ?></div>
            <div><label for="f_owner">Owner</label><?php $select('owner', ['' => 'All'] + MKT_ISSUE_OWNERS, $filters['owner'], 'f_owner'); ?></div>
            <div class="audit-filter-wide"><label for="f_q">Search</label><input class="form-input" type="search" id="f_q" name="q" value="<?= htmlspecialchars($filters['q']) ?>" placeholder="Issue, code or URL" /></div>
          </div>
          <div class="audit-filter-actions">
            <button type="submit" class="btn-primary">Apply Filters</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($baseHref . '?tab=' . $tab) ?>">Clear</a>
          </div>
        </form>

        <?php if ($issues === []): ?>
        <p class="form-hint">
          <?= match ($tab) {
              'open'     => $counts['new'] + $counts['open'] + $counts['verified'] + $counts['fixed'] + $counts['ignored'] === 0
                  ? 'No audits yet — run "Crawl site now" on the Audits tab.'
                  : 'No open issues match.',
              'fixed'    => 'Nothing is waiting on a recheck. Issues marked fixed move to Verified once the recrawl confirms it.',
              'resolved' => 'No verified issues yet. An issue is verified when an audit no longer finds it on any page.',
              default    => 'Nothing ignored.',
          } ?>
        </p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>Issue</th><th>Severity</th><th>Owner</th><th>Status</th><th>Open pages</th><th>Found by</th><th>First seen</th><th>Last seen</th><th>Assignee</th><th>Fix spec</th></tr>
            </thead>
            <tbody>
              <?php foreach ($issues as $issue): ?>
              <tr>
                <td>
                  <a href="/marketing/issues/view.php?id=<?= (int) $issue['IssueID'] ?>"><?= htmlspecialchars((string) $issue['Title']) ?></a>
                  <br /><span class="form-hint"><code><?= htmlspecialchars((string) $issue['Code']) ?></code><?= (int) $issue['ReopenCount'] ? ' · reopened ' . (int) $issue['ReopenCount'] . '×' : '' ?></span>
                  <?php if ($tab === 'ignored' && $issue['IgnoreReason']): ?><br /><span class="form-hint"><?= htmlspecialchars((string) $issue['IgnoreReason']) ?></span><?php endif; ?>
                </td>
                <td><?= mkt_severity_badge((string) $issue['Severity']) ?></td>
                <td><?= htmlspecialchars(MKT_ISSUE_OWNERS[$issue['Owner']] ?? (string) $issue['Owner']) ?></td>
                <td><?= mkt_issue_badge((string) $issue['Status']) ?></td>
                <td><?= (int) $issue['OpenUrlCount'] ?><?= (int) $issue['TotalUrls'] > (int) $issue['OpenUrlCount'] ? ' <span class="form-hint">of ' . (int) $issue['TotalUrls'] . '</span>' : '' ?></td>
                <td><?= htmlspecialchars(implode(', ', array_map(static fn(string $s): string => MKT_AUDIT_SOURCES[$s] ?? $s, array_filter(explode(',', (string) $issue['Sources']))))) ?></td>
                <td><?= htmlspecialchars(marketing_format_date((string) $issue['FirstSeenAt'])) ?></td>
                <td><?= htmlspecialchars(marketing_format_date((string) $issue['LastSeenAt'])) ?></td>
                <td><?= htmlspecialchars((string) ($issue['AssigneeName'] ?? '—')) ?></td>
                <td><?= $issue['FixSpecAt'] ? 'Yes' : '<span class="form-hint">—</span>' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

      <?php elseif ($tab === 'audits'): ?>
        <?php $audits = mkt_audits_list(); ?>
        <?php if ($canUpdate): ?>
        <div class="status-banner">
          <div>
            <strong>Run the crawler</strong>
            <p>Reads the sitemaps and rechecks every active page (about a minute and a half). Findings merge into the existing issues; nothing is duplicated.</p>
          </div>
          <div>
            <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
              <input type="hidden" name="action" value="crawl" />
              <button type="submit" class="btn-secondary">Crawl site now</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($audits === []): ?>
        <p class="form-hint">No audits yet.</p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr><th>When</th><th>Source</th><th>Scope</th><th>Pages</th><th>Findings</th><th>New issues</th><th>URLs newly affected</th><th>URLs resolved</th><th>Verified</th><th>Reopened</th><th>Still present</th><th>Score</th><th>By</th></tr>
            </thead>
            <tbody>
              <?php foreach ($audits as $audit): ?>
              <tr>
                <td>
                  <?= htmlspecialchars(marketing_format_datetime((string) $audit['StartedAt'])) ?>
                  <?php if ($audit['Summary']): ?><br /><span class="form-hint"><?= htmlspecialchars((string) $audit['Summary']) ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(MKT_AUDIT_SOURCES[$audit['Source']] ?? (string) $audit['Source']) ?></td>
                <td>
                  <?= htmlspecialchars(MKT_AUDIT_SCOPES[$audit['Scope']] ?? (string) $audit['Scope']) ?>
                  <?php if ($audit['IssueID']): ?><br /><a class="form-hint" href="/marketing/issues/view.php?id=<?= (int) $audit['IssueID'] ?>"><?= htmlspecialchars((string) $audit['IssueTitle']) ?></a><?php endif; ?>
                </td>
                <td><?= (int) $audit['PagesAudited'] ?><?= (int) $audit['Skipped'] ? ' <span class="form-hint">(' . (int) $audit['Skipped'] . ' skipped)</span>' : '' ?></td>
                <td><?= (int) $audit['Findings'] ?></td>
                <td><?= (int) $audit['NewIssues'] ?></td>
                <td><?= (int) $audit['NewUrls'] ?></td>
                <td><?= (int) $audit['ResolvedUrls'] ?></td>
                <td><?= (int) $audit['VerifiedIssues'] ?></td>
                <td><?= (int) $audit['ReopenedIssues'] ?></td>
                <td><?= (int) $audit['StillOpenIssues'] ?></td>
                <td><?= $audit['Score'] !== null ? htmlspecialchars((string) (float) $audit['Score']) : '—' ?></td>
                <td><?= htmlspecialchars((string) ($audit['CreatedByName'] ?? ($audit['ProcessLogID'] ? 'Schedule' : '—'))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if ($canUpdate): ?>
        <h2 class="hub-section-title">Import an OpenRush audit</h2>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($baseHref) ?>">
          <input type="hidden" name="action" value="openrush" />
          <?php marketing_render_field_guide_link('issue-openrush-import'); ?>
          <div class="form-group form-group--stacked">
            <label for="audit_json">OpenRush audit_site result (JSON)</label>
            <textarea class="form-input" id="audit_json" name="audit_json" rows="8" required placeholder='{"schema_version":"ofe/1.0","domain":"site_audit","data":{"domain":"nutraaxislabs.com","pages":[…]}}'></textarea>
          </div>
          <p class="form-hint">
            In Cursor or Claude, ask OpenRush to run <code>audit_site</code> for nutraaxislabs.com and paste the whole JSON reply here.
            OpenRush samples up to 20 pages; only pages already active in the Page Inventory are counted, and only nutraaxislabs.com audits are accepted.
            Its own checks (such as low content) resolve when a later import no longer finds them.
          </p>
          <div class="form-actions"><button type="submit" class="btn-primary">Import audit</button></div>
        </form>
        <?php endif; ?>

      <?php else: ?>
        <?php
          $alertView = in_array($_GET['view'] ?? '', ['resolved', 'all'], true) ? (string) $_GET['view'] : 'active';
          $alerts = mkt_alerts_list($alertView);
          $rules = marketing_setting_lines('alerts.rules');
        ?>
        <div class="status-banner">
          <div>
            <strong>Alert rules</strong>
            <p>
              Checked daily at about 6 AM Central.
              On: <?= htmlspecialchars(implode(', ', array_map(static fn(string $r): string => MKT_ALERT_RULES[$r] ?? $r, $rules)) ?: 'none') ?>.
              New alerts are emailed to <?= marketing_setting_lines('alerts.recipients') !== [] ? htmlspecialchars(implode(', ', marketing_setting_lines('alerts.recipients'))) : 'everyone with full Marketing access' ?> (change in Admin → Settings).
            </p>
            <p class="form-hint">An alert resolves itself once its cause is gone. Acknowledge one to show someone is on it.</p>
          </div>
          <?php if ($canUpdate): ?>
          <div>
            <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
              <input type="hidden" name="action" value="check_alerts" />
              <button type="submit" class="btn-secondary" title="Evaluate the rules now; emails anything new">Check now</button>
            </form>
          </div>
          <?php endif; ?>
        </div>
        <?php marketing_render_tabs($baseHref, ['active' => 'Open & acknowledged', 'resolved' => 'Resolved', 'all' => 'All'], $alertView, ['tab' => 'alerts']); ?>

        <?php if ($alerts === []): ?>
        <p class="form-hint"><?= $alertView === 'active' ? 'No open alerts.' : 'No alerts.' ?></p>
        <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Alert</th><th>Rule</th><th>Severity</th><th>Status</th><th>First</th><th>Last</th><th>Emailed</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($alerts as $alert): ?>
              <tr>
                <td>
                  <?= $alert['Href'] ? '<a href="' . htmlspecialchars((string) $alert['Href']) . '">' . htmlspecialchars((string) $alert['Title']) . '</a>' : htmlspecialchars((string) $alert['Title']) ?>
                  <?php if ($alert['Detail']): ?><br /><span class="form-hint"><?= htmlspecialchars((string) $alert['Detail']) ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(MKT_ALERT_RULES[$alert['RuleKey']] ?? (string) $alert['RuleKey']) ?></td>
                <td><?= mkt_severity_badge((string) $alert['Severity']) ?></td>
                <td>
                  <?= htmlspecialchars(MKT_ALERT_STATUSES[$alert['Status']] ?? (string) $alert['Status']) ?>
                  <?php if ($alert['AcknowledgedByName']): ?><br /><span class="form-hint">by <?= htmlspecialchars((string) $alert['AcknowledgedByName']) ?></span><?php endif; ?>
                </td>
                <td><?= htmlspecialchars(marketing_format_datetime((string) $alert['FirstAt'])) ?></td>
                <td><?= htmlspecialchars(marketing_format_datetime((string) ($alert['ResolvedAt'] ?: $alert['LastAt']))) ?></td>
                <td>
                  <?= $alert['NotifiedAt'] ? htmlspecialchars(marketing_format_datetime((string) $alert['NotifiedAt'])) : '<span class="form-hint">Not yet</span>' ?>
                  <?php if ($alert['NotifyError']): ?><br /><span class="form-hint" style="color:var(--danger)"><?= htmlspecialchars((string) $alert['NotifyError']) ?></span><?php endif; ?>
                </td>
                <td>
                  <?php if ($canUpdate && $alert['Status'] === 'open'): ?>
                  <form method="post" action="<?= htmlspecialchars($baseHref) ?>" style="display:inline">
                    <input type="hidden" name="action" value="acknowledge" />
                    <input type="hidden" name="alert_id" value="<?= (int) $alert['AlertID'] ?>" />
                    <button type="submit" class="btn-secondary">Acknowledge</button>
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
    </div>
  </main>
<?php
require dirname(__DIR__, 2) . '/includes/footer.php';
