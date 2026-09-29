<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-issues.php';
require dirname(__DIR__, 2) . '/includes/marketing-content.php';
require dirname(__DIR__, 2) . '/includes/process-runner.php';

auth_require_module_read('marketing-issues');

$id = (int) ($_GET['id'] ?? 0);
$issue = mkt_issue_get($id);
if ($issue === null) {
    marketing_redirect('/marketing/issues/', ['error' => 'Issue not found.']);
}
$selfHref = '/marketing/issues/view.php?id=' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    marketing_require_update();
    $action = (string) ($_POST['action'] ?? '');
    $back = static fn(array $result, string $ok): never => marketing_redirect($selfHref, !empty($result['ok'])
        ? ['notice' => (string) ($result['message'] ?? $ok)]
        : ['error' => (string) ($result['error'] ?? 'That did not work.')]);

    if ($action === 'fix_spec') {
        $back(process_execute('seo-fix-spec', ['issue_id' => $id, 'advice' => mkt_issue_advice((string) $issue['Code'])], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Fix spec written.');
    }
    if ($action === 'verify') {
        $back(process_execute('seo-issue-verify', ['issue_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id()), 'Rechecked.');
    }
    if (in_array($action, ['acknowledge', 'fixed', 'ignore', 'unignore', 'assign'], true)) {
        $result = mkt_issue_action($id, $action, $_POST);
        if ($result['ok'] && $action === 'fixed') {
            $verify = process_execute('seo-issue-verify', ['issue_id' => $id], PROCESS_LOG_TRIGGER_MANUAL, marketing_user_id());
            $back($verify, 'Marked fixed and rechecked.');
        }
        $back($result, match ($action) {
            'acknowledge' => 'Acknowledged.',
            'ignore'      => 'Ignored. Audits keep tracking its pages but it stays out of the open list and alerts.',
            'unignore'    => 'Back in the list.',
            default       => 'Assignee saved.',
        });
    }
}

$status = (string) $issue['Status'];
$code = (string) $issue['Code'];
$info = mkt_page_issue_info($code);
$urls = mkt_issue_urls($id);
$openUrls = array_values(array_filter($urls, static fn(array $u): bool => $u['Status'] === 'open'));
$canUpdate = marketing_can_update();
$fromOpenRush = str_starts_with($code, 'or_');

$pageTitle = $issue['Title'] . ' | Audit & Issues | NutraAxis Operations';
$pageDescription = 'Site issue detail, affected pages, fix spec and verification.';

require dirname(__DIR__, 2) . '/includes/head.php';
require dirname(__DIR__, 2) . '/includes/header.php';

$by = static fn(?string $name, ?string $at): string => htmlspecialchars(trim(($name ?? '') . ($at ? ' · ' . marketing_format_datetime($at) : '')) ?: '—');
?>
  <main class="page-main">
    <div class="container page-inner">
      <?php
      render_list_page_header([
          'back_href'  => '/marketing/issues/',
          'back_label' => 'Back to Audit & Issues',
          'category'   => 'Marketing & Research',
          'title'      => (string) $issue['Title'],
          'lead'       => $info['advice'] ?: 'Found by the site audit.',
          'permission' => auth_module_permission_label('marketing-issues'),
      ]);
      marketing_render_notice($_GET['notice'] ?? null, $_GET['error'] ?? null);
      ?>

      <?php if ($status === 'fixed'): ?>
      <div class="status-banner status-banner-approval">
        <div>
          <strong>Marked fixed — waiting on the recheck</strong>
          <p><?= htmlspecialchars((string) ($issue['VerifyNote'] ?: 'The recrawl has not confirmed it yet.')) ?></p>
        </div>
      </div>
      <?php elseif ($status === 'open' && $issue['VerifyNote']): ?>
      <div class="status-banner status-banner-approval">
        <div><strong>Not fixed yet</strong><p><?= htmlspecialchars((string) $issue['VerifyNote']) ?></p></div>
      </div>
      <?php endif; ?>

      <div class="detail-card">
        <dl class="detail-list detail-list-inline detail-list-4col">
          <dt>Status</dt><dd><?= mkt_issue_badge($status) ?><?= (int) $issue['ReopenCount'] ? ' <span class="form-hint">reopened ' . (int) $issue['ReopenCount'] . '× — last ' . htmlspecialchars(marketing_format_datetime((string) $issue['ReopenedAt'])) . '</span>' : '' ?></dd>
          <dt>Severity</dt><dd><?= mkt_severity_badge((string) $issue['Severity']) ?></dd>
          <dt>Owner</dt><dd><?= htmlspecialchars(MKT_ISSUE_OWNERS[$issue['Owner']] ?? (string) $issue['Owner']) ?> <span class="form-hint">(<?= $issue['Owner'] === 'developer' ? 'templates, code or site configuration' : 'page copy and metadata in document authoring' ?>)</span></dd>
          <dt>Check</dt><dd><code><?= htmlspecialchars($code) ?></code> · <?= htmlspecialchars(ucfirst((string) $issue['Category'])) ?></dd>
          <dt>Open pages</dt><dd><?= count($openUrls) ?> of <?= count($urls) ?> ever affected</dd>
          <dt>Found by</dt><dd><?= htmlspecialchars(implode(', ', array_map(static fn(string $s): string => MKT_AUDIT_SOURCES[$s] ?? $s, array_filter(explode(',', (string) $issue['Sources']))))) ?></dd>
          <dt>First / last seen</dt><dd><?= htmlspecialchars(marketing_format_datetime((string) $issue['FirstSeenAt'])) ?> · <?= htmlspecialchars(marketing_format_datetime((string) $issue['LastSeenAt'])) ?></dd>
          <dt>Assignee</dt><dd><?= htmlspecialchars((string) ($issue['AssigneeName'] ?? 'Nobody')) ?></dd>
          <?php if ($issue['FixedAt']): ?><dt class="is-wide">Marked fixed</dt><dd><?= $by($issue['FixedByName'], $issue['FixedAt']) ?><?= $issue['FixNote'] ? ' — ' . htmlspecialchars((string) $issue['FixNote']) : '' ?></dd><?php endif; ?>
          <?php if ($issue['VerifiedAt']): ?><dt class="is-wide">Verified</dt><dd><?= htmlspecialchars(marketing_format_datetime((string) $issue['VerifiedAt'])) ?><?= $status === 'verified' && $issue['VerifyNote'] ? ' — ' . htmlspecialchars((string) $issue['VerifyNote']) : '' ?></dd><?php endif; ?>
          <?php if ($status === 'ignored'): ?><dt class="is-wide">Ignored</dt><dd><?= $by($issue['IgnoredByName'], $issue['IgnoredAt']) ?> — <?= htmlspecialchars((string) $issue['IgnoreReason']) ?></dd><?php endif; ?>
        </dl>
      </div>

      <?php if ($canUpdate): ?>
      <h2 class="hub-section-title">Actions</h2>
      <div class="form-actions" style="flex-wrap:wrap;gap:0.5rem;margin-bottom:0.75rem">
        <?php if ($status === 'new'): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <input type="hidden" name="action" value="acknowledge" />
          <button type="submit" class="btn-secondary" title="Move it from New to Open">Acknowledge</button>
        </form>
        <?php endif; ?>
        <?php if (in_array($status, ['fixed', 'open', 'new'], true) && !$fromOpenRush && $openUrls !== []): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <input type="hidden" name="action" value="verify" />
          <button type="submit" class="btn-secondary" title="Recrawl the <?= count($openUrls) ?> open page(s) now">Recheck pages now</button>
        </form>
        <?php endif; ?>
        <?php if ($openUrls !== []): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <input type="hidden" name="action" value="fix_spec" />
          <button type="submit" class="btn-secondary" title="AI writes a developer-ready spec from the affected pages (about 30 seconds)"><?= $issue['FixSpec'] ? 'Regenerate fix spec' : 'Generate fix spec' ?></button>
        </form>
        <?php endif; ?>
        <?php if ($status === 'ignored'): ?>
        <form method="post" action="<?= htmlspecialchars($selfHref) ?>" style="display:inline">
          <input type="hidden" name="action" value="unignore" />
          <button type="submit" class="btn-secondary">Stop ignoring</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (in_array($status, ['new', 'open'], true) && !$fromOpenRush): ?>
      <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
        <input type="hidden" name="action" value="fixed" />
        <div class="form-group"><label for="fix_note">Fix note</label><input class="form-input" type="text" id="fix_note" name="note" maxlength="2000" placeholder="Optional — what was changed, by whom" /></div>
        <div class="form-actions"><button type="submit" class="btn-primary" title="Recrawls the open pages right away; verified if the problem is gone">Mark fixed and recheck</button></div>
      </form>
      <?php elseif ($fromOpenRush && in_array($status, ['new', 'open'], true)): ?>
      <p class="form-hint">This check comes only from OpenRush. After fixing it, run a new OpenRush audit and import it on the Audits tab; pages it no longer flags are resolved.</p>
      <?php endif; ?>

      <div class="form-grid" style="gap:1rem">
        <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
          <input type="hidden" name="action" value="assign" />
          <div class="form-group">
            <label for="assignee">Assignee</label>
            <select class="form-input" id="assignee" name="assignee">
              <option value="">Nobody</option>
              <?php foreach (mkt_issue_assignees() as $userId => $name): ?>
              <option value="<?= (int) $userId ?>"<?= (int) $issue['AssigneeUserID'] === (int) $userId ? ' selected' : '' ?>><?= htmlspecialchars((string) $name) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-actions"><button type="submit" class="btn-secondary">Save assignee</button></div>
        </form>
        <?php if ($status !== 'ignored'): ?>
        <form class="admin-form" method="post" action="<?= htmlspecialchars($selfHref) ?>">
          <input type="hidden" name="action" value="ignore" />
          <div class="form-group"><label for="ignore_note">Ignore because</label><input class="form-input" type="text" id="ignore_note" name="note" maxlength="1000" required placeholder="e.g. account pages are meant to be noindex" /></div>
          <div class="form-actions"><button type="submit" class="btn-secondary">Ignore</button></div>
        </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <h2 class="hub-section-title">Fix spec</h2>
      <?php if ($issue['FixSpec']): ?>
      <p class="form-hint">
        Written by AI (<?= htmlspecialchars((string) $issue['FixSpecModel']) ?>) from <?= (int) $issue['FixSpecUrlCount'] ?> page<?= (int) $issue['FixSpecUrlCount'] === 1 ? '' : 's' ?>
        — <?= $by($issue['FixSpecByName'], $issue['FixSpecAt']) ?><?= $issue['FixSpecCostUsd'] !== null ? ' · ' . marketing_format_usd((float) $issue['FixSpecCostUsd'], 3) : '' ?>.
        Check it before sending; it is included in the developer packet.
      </p>
      <div class="detail-card mkt-article"><?= mkt_markdown_html((string) $issue['FixSpec']) ?></div>
      <?php else: ?>
      <p class="form-hint">No fix spec yet. <?= $openUrls !== [] ? 'Generate one to hand the developer exact steps, where to change it, and how it will be verified.' : '' ?></p>
      <?php endif; ?>

      <h2 class="hub-section-title">Pages</h2>
      <?php if ($urls === []): ?>
      <p class="form-hint">No pages recorded.</p>
      <?php else: ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Page</th><th>Type</th><th>Detail</th><th>Status</th><th>Found by</th><th>First seen</th><th>Last seen</th><th>Page crawled</th></tr></thead>
          <tbody>
            <?php foreach ($urls as $url): ?>
            <tr>
              <td>
                <?php if ($url['PageID']): ?><a href="/marketing/pages/view.php?id=<?= (int) $url['PageID'] ?>"><?= htmlspecialchars((string) ($url['Path'] ?? $url['Url'])) ?></a><?php else: ?><?= htmlspecialchars((string) $url['Url']) ?><?php endif; ?>
                <a class="form-hint" href="<?= htmlspecialchars((string) $url['Url']) ?>" target="_blank" rel="noopener noreferrer">open ↗</a>
                <?php if ($url['PageStatus'] && $url['PageStatus'] !== 'active'): ?> <?= mkt_render_badge((string) $url['PageStatus'], MKT_PAGE_STATUSES) ?><?php endif; ?>
              </td>
              <td><?= htmlspecialchars(MKT_PAGE_TYPES[$url['PageType'] ?? ''] ?? (string) ($url['PageType'] ?? '—')) ?></td>
              <td><?= htmlspecialchars((string) ($url['Detail'] ?? '')) ?></td>
              <td><?= $url['Status'] === 'open' ? '<span class="status-badge status-cancelled">Open</span>' : '<span class="status-badge status-approved">Resolved</span> <span class="form-hint">' . htmlspecialchars(marketing_format_date((string) $url['ResolvedAt'])) . '</span>' ?></td>
              <td><?= htmlspecialchars(implode(', ', array_map(static fn(string $s): string => MKT_AUDIT_SOURCES[$s] ?? $s, array_filter(explode(',', (string) $url['Sources']))))) ?></td>
              <td><?= htmlspecialchars(marketing_format_date((string) $url['FirstSeenAt'])) ?></td>
              <td><?= htmlspecialchars(marketing_format_date((string) $url['LastSeenAt'])) ?></td>
              <td><?= $url['LastCrawledAt'] ? htmlspecialchars(marketing_format_datetime((string) $url['LastCrawledAt'])) : '—' ?></td>
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
