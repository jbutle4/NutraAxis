<?php
require_once __DIR__ . '/marketing-pages.php';

/**
 * Audit & Issues: site audits (weekly crawl, page recrawls, OpenRush imports) grouped into one issue per check,
 * with the URLs it is on. Fixed → recrawl → verified, or reopened when it comes back. Alerts come from the
 * seo-alerts job. Audits, verification and fix specs run in the Function App.
 */
const MKT_ISSUE_STATUSES = [
    'new'      => 'New',
    'open'     => 'Open',
    'fixed'    => 'Fixed — rechecking',
    'verified' => 'Verified',
    'ignored'  => 'Ignored',
];
const MKT_ISSUE_SEVERITIES = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
const MKT_ISSUE_OWNERS = ['developer' => 'Developer', 'content' => 'Content'];
const MKT_ISSUE_TABS = [
    'open'     => ['new', 'open'],
    'fixed'    => ['fixed'],
    'resolved' => ['verified'],
    'ignored'  => ['ignored'],
];
const MKT_AUDIT_SOURCES = ['crawl' => 'Crawler', 'openrush' => 'OpenRush'];
const MKT_AUDIT_SCOPES = ['full' => 'Full site', 'page' => 'One page', 'verify' => 'Fix recheck', 'sample' => 'Sample'];
const MKT_ALERT_RULES = [
    'job_failed'         => 'Scheduled job failed',
    'traffic_drop'       => 'Traffic drop',
    'legacy_brand'       => 'Legacy brand name',
    'site_error'         => 'High-severity site issue',
    'escalation_overdue' => 'Compliance review overdue',
    'ai_budget'          => 'AI budget',
];
const MKT_ALERT_STATUSES = ['open' => 'Open', 'acknowledged' => 'Acknowledged', 'resolved' => 'Resolved'];

function mkt_issue_badge(string $status): string
{
    $class = match ($status) {
        'new', 'open' => 'status-cancelled',
        'fixed'       => 'status-submitted',
        'verified'    => 'status-approved',
        default       => 'status-draft',
    };

    return '<span class="status-badge ' . $class . '">' . htmlspecialchars(MKT_ISSUE_STATUSES[$status] ?? $status) . '</span>';
}

function mkt_severity_badge(string $severity): string
{
    $class = match ($severity) {
        'high'   => 'status-cancelled',
        'medium' => 'status-submitted',
        default  => 'status-draft',
    };

    return '<span class="status-badge ' . $class . '">' . htmlspecialchars(MKT_ISSUE_SEVERITIES[$severity] ?? $severity) . '</span>';
}

function mkt_issue_advice(string $code): string
{
    return mkt_page_issue_info($code)['advice'];
}

function mkt_issue_counts(): array
{
    $counts = array_fill_keys(array_keys(MKT_ISSUE_STATUSES), 0) + ['high_open' => 0, 'open_urls' => 0, 'alerts' => 0];
    foreach (db()->query('SELECT Status, Severity, COUNT(*) AS N, SUM(OpenUrlCount) AS Urls FROM dbo.MktIssue GROUP BY Status, Severity')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[$row['Status']] += (int) $row['N'];
        if (in_array($row['Status'], ['new', 'open'], true)) {
            $counts['open_urls'] += (int) $row['Urls'];
            if ($row['Severity'] === 'high') {
                $counts['high_open'] += (int) $row['N'];
            }
        }
    }
    $counts['alerts'] = (int) db()->query("SELECT COUNT(*) FROM dbo.MktAlert WHERE Status = N'open'")->fetchColumn();

    return $counts;
}

/** @param array{tab?: string, severity?: string, owner?: string, q?: string, status?: string} $filters */
function mkt_issues_list(array $filters = []): array
{
    $statuses = MKT_ISSUE_TABS[$filters['tab'] ?? 'open'] ?? MKT_ISSUE_TABS['open'];
    if (in_array($filters['status'] ?? '', $statuses, true)) {
        $statuses = [$filters['status']];
    }
    $params = [];
    $in = [];
    foreach ($statuses as $i => $status) {
        $in[] = ":s{$i}";
        $params["s{$i}"] = $status;
    }
    $where = ['i.Status IN (' . implode(', ', $in) . ')'];
    if (isset(MKT_ISSUE_SEVERITIES[$filters['severity'] ?? ''])) {
        $where[] = 'i.Severity = :sev';
        $params['sev'] = $filters['severity'];
    }
    if (isset(MKT_ISSUE_OWNERS[$filters['owner'] ?? ''])) {
        $where[] = 'i.Owner = :owner';
        $params['owner'] = $filters['owner'];
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(i.Title LIKE :q1 OR i.Code LIKE :q2 OR EXISTS (SELECT 1 FROM dbo.MktIssueUrl u WHERE u.IssueID = i.IssueID AND u.Url LIKE :q3))';
        $params['q1'] = $params['q2'] = $params['q3'] = '%' . $filters['q'] . '%';
    }
    $stmt = db()->prepare(
        'SELECT i.IssueID, i.Code, i.Title, i.Category, i.Severity, i.Owner, i.Status, i.OpenUrlCount, i.Sources, i.ReopenCount,
                i.FirstSeenAt, i.LastSeenAt, i.FixedAt, i.VerifiedAt, i.IgnoredAt, i.IgnoreReason, i.FixSpecAt, u.UserName AS AssigneeName,
                (SELECT COUNT(*) FROM dbo.MktIssueUrl x WHERE x.IssueID = i.IssueID) AS TotalUrls
         FROM dbo.MktIssue i
         LEFT JOIN dbo.[User] u ON u.UserID = i.AssigneeUserID
         WHERE ' . implode(' AND ', $where) . "
         ORDER BY CASE i.Severity WHEN N'high' THEN 0 WHEN N'medium' THEN 1 ELSE 2 END, CASE i.Status WHEN N'new' THEN 0 ELSE 1 END,
                  i.OpenUrlCount DESC, i.Title"
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_issue_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT i.*, a.UserName AS AssigneeName, f.UserName AS FixedByName, g.UserName AS IgnoredByName, s.UserName AS FixSpecByName
        FROM dbo.MktIssue i
        LEFT JOIN dbo.[User] a ON a.UserID = i.AssigneeUserID
        LEFT JOIN dbo.[User] f ON f.UserID = i.FixedBy
        LEFT JOIN dbo.[User] g ON g.UserID = i.IgnoredBy
        LEFT JOIN dbo.[User] s ON s.UserID = i.FixSpecBy
        WHERE i.IssueID = :id
    SQL);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function mkt_issue_urls(int $issueId, ?string $status = null): array
{
    $sql = <<<SQL
        SELECT u.IssueUrlID, u.PageID, u.Url, u.Status, u.Detail, u.Sources, u.FirstSeenAt, u.LastSeenAt, u.ResolvedAt,
               p.Path, p.PageType, p.Status AS PageStatus, p.LastCrawledAt
        FROM dbo.MktIssueUrl u
        LEFT JOIN dbo.MktPage p ON p.PageID = u.PageID
        WHERE u.IssueID = :id
    SQL;
    $params = ['id' => $issueId];
    if ($status !== null) {
        $sql .= ' AND u.Status = :st';
        $params['st'] = $status;
    }
    $stmt = db()->prepare($sql . " ORDER BY CASE u.Status WHEN N'open' THEN 0 ELSE 1 END, u.Url");
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** Open issues on one page, for the Page Inventory detail screen. */
function mkt_page_open_issues(int $pageId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT i.IssueID, i.Code, i.Title, i.Severity, i.Status, u.Detail
        FROM dbo.MktIssueUrl u INNER JOIN dbo.MktIssue i ON i.IssueID = u.IssueID
        WHERE u.PageID = :id AND u.Status = N'open'
        ORDER BY CASE i.Severity WHEN N'high' THEN 0 WHEN N'medium' THEN 1 ELSE 2 END, i.Title
    SQL);
    $stmt->execute(['id' => $pageId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** People who can be assigned issues: anyone who can update Marketing. */
function mkt_issue_assignees(): array
{
    return db()->query(<<<SQL
        SELECT u.UserID, u.UserName FROM dbo.[User] u INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE r.Marketing LIKE N'%U%' ORDER BY u.UserName
    SQL)->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
}

/**
 * Status changes made by people. "fixed" is followed by a recrawl (seo-issue-verify) from the page.
 *
 * @param 'acknowledge'|'fixed'|'ignore'|'unignore'|'assign' $action
 */
function mkt_issue_action(int $id, string $action, array $input = []): array
{
    $issue = mkt_issue_get($id);
    if ($issue === null) {
        return ['ok' => false, 'error' => 'Issue not found.'];
    }
    $status = (string) $issue['Status'];
    $note = trim((string) ($input['note'] ?? ''));
    $user = marketing_user_id();
    $set = [];
    switch ($action) {
        case 'acknowledge':
            if ($status !== 'new') {
                return ['ok' => false, 'error' => 'Only new issues can be acknowledged.'];
            }
            $set = ['Status' => 'open'];
            break;
        case 'fixed':
            if (!in_array($status, ['new', 'open'], true)) {
                return ['ok' => false, 'error' => 'Only new or open issues can be marked fixed.'];
            }
            if (str_starts_with((string) $issue['Code'], 'or_')) {
                return ['ok' => false, 'error' => 'This check comes only from OpenRush — re-run the OpenRush audit and import it; the import resolves it when it is gone.'];
            }
            $set = ['Status' => 'fixed', 'FixedAt' => gmdate('Y-m-d H:i:s'), 'FixedBy' => $user, 'FixNote' => mb_substr($note, 0, 2000) ?: null, 'VerifyNote' => null];
            break;
        case 'ignore':
            if ($status === 'ignored') {
                return ['ok' => false, 'error' => 'This issue is already ignored.'];
            }
            if ($note === '') {
                return ['ok' => false, 'error' => 'Say why it is being ignored (for example, "account pages are meant to be noindex").'];
            }
            $set = ['Status' => 'ignored', 'IgnoredAt' => gmdate('Y-m-d H:i:s'), 'IgnoredBy' => $user, 'IgnoreReason' => mb_substr($note, 0, 1000)];
            break;
        case 'unignore':
            if ($status !== 'ignored') {
                return ['ok' => false, 'error' => 'This issue is not ignored.'];
            }
            $set = ['Status' => (int) $issue['OpenUrlCount'] > 0 ? 'open' : 'verified', 'IgnoredAt' => null, 'IgnoredBy' => null, 'IgnoreReason' => null];
            break;
        case 'assign':
            $assignee = (int) ($input['assignee'] ?? 0) ?: null;
            if ($assignee !== null && !isset(mkt_issue_assignees()[$assignee])) {
                return ['ok' => false, 'error' => 'Pick someone with Marketing update access.'];
            }
            $set = ['AssigneeUserID' => $assignee];
            break;
        default:
            return ['ok' => false, 'error' => 'Unknown action.'];
    }

    $assignments = implode(', ', array_map(static fn(string $col): string => "{$col} = :{$col}", array_keys($set)));
    db()->prepare("UPDATE dbo.MktIssue SET {$assignments}, UpdatedAt = SYSUTCDATETIME(), UpdatedBy = :u WHERE IssueID = :id")
        ->execute($set + ['u' => $user, 'id' => $id]);
    $before = array_intersect_key($issue, $set);
    $built = audit_build_update('MktIssue', 'IssueID', (string) $id, $set, $before);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'status' => $set['Status'] ?? $status];
}

function mkt_audits_list(int $limit = 50): array
{
    $limit = max(1, min(500, $limit));

    return db()->query(<<<SQL
        SELECT TOP ({$limit}) a.*, u.UserName AS CreatedByName, i.Title AS IssueTitle
        FROM dbo.MktAudit a
        LEFT JOIN dbo.[User] u ON u.UserID = a.CreatedBy
        LEFT JOIN dbo.MktIssue i ON i.IssueID = a.IssueID
        ORDER BY a.AuditID DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_alerts_list(string $status = 'active', int $limit = 200): array
{
    $limit = max(1, min(1000, $limit));
    $where = match ($status) {
        'resolved' => "a.Status = N'resolved'",
        'all'      => '1 = 1',
        default    => "a.Status IN (N'open', N'acknowledged')",
    };

    return db()->query(<<<SQL
        SELECT TOP ({$limit}) a.*, u.UserName AS AcknowledgedByName
        FROM dbo.MktAlert a
        LEFT JOIN dbo.[User] u ON u.UserID = a.AcknowledgedBy
        WHERE {$where}
        ORDER BY CASE a.Status WHEN N'open' THEN 0 WHEN N'acknowledged' THEN 1 ELSE 2 END,
                 CASE a.Severity WHEN N'high' THEN 0 WHEN N'medium' THEN 1 ELSE 2 END, a.LastAt DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_alert_acknowledge(int $id): array
{
    $stmt = db()->prepare("UPDATE dbo.MktAlert SET Status = N'acknowledged', AcknowledgedAt = SYSUTCDATETIME(), AcknowledgedBy = :u WHERE AlertID = :id AND Status = N'open'");
    $stmt->execute(['u' => marketing_user_id(), 'id' => $id]);
    if ($stmt->rowCount() === 0) {
        return ['ok' => false, 'error' => 'That alert is not open.'];
    }
    $built = audit_build_update('MktAlert', 'AlertID', (string) $id, ['Status' => 'acknowledged'], ['Status' => 'open']);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/** An issues:triage task while new issues wait for someone to look at them. */
function mkt_issue_desired_tasks(callable $add): void
{
    $row = db()->query(<<<SQL
        SELECT COUNT(*) AS N, SUM(CASE WHEN Severity = N'high' THEN 1 ELSE 0 END) AS High,
               CONVERT(varchar(19), MIN(FirstSeenAt), 120) AS Since
        FROM dbo.MktIssue WHERE Status = N'new'
    SQL)->fetch(PDO::FETCH_ASSOC);
    $count = (int) ($row['N'] ?? 0);
    if ($count === 0) {
        return;
    }
    $add('issues:triage', [
        'Title' => 'Triage ' . $count . ' new site issue' . ($count === 1 ? '' : 's') . ' in Audit & Issues',
        'TaskType' => 'issues_triage', 'AssigneeRole' => 'coordinator', 'RefType' => 'issue', 'RefID' => null,
        'Href' => '/marketing/issues/?status=new', 'Priority' => (int) $row['High'] > 0 ? 'high' : 'normal',
        'DueDate' => mkt_task_due((string) $row['Since'], 'triage'),
        'Detail' => 'Acknowledge each one, assign it, or ignore it with a reason. Generate a fix spec for the developer where it helps.',
    ]);
}

/* ---------- Export ---------- */

/** One row per open URL (or every URL with $all) for the issues matching the filters. */
function mkt_issue_export_rows(array $filters, bool $all = false): array
{
    $rows = [];
    foreach (mkt_issues_list($filters) as $issue) {
        foreach (mkt_issue_urls((int) $issue['IssueID'], $all ? null : 'open') as $url) {
            $rows[] = [
                'Issue ID'   => (int) $issue['IssueID'],
                'Issue'      => (string) $issue['Title'],
                'Code'       => (string) $issue['Code'],
                'Severity'   => MKT_ISSUE_SEVERITIES[$issue['Severity']] ?? $issue['Severity'],
                'Owner'      => MKT_ISSUE_OWNERS[$issue['Owner']] ?? $issue['Owner'],
                'Status'     => MKT_ISSUE_STATUSES[$issue['Status']] ?? $issue['Status'],
                'URL'        => (string) $url['Url'],
                'URL status' => (string) $url['Status'],
                'Detail'     => (string) ($url['Detail'] ?? ''),
                'Found by'   => (string) ($url['Sources'] ?? ''),
                'First seen' => (string) $url['FirstSeenAt'],
                'Last seen'  => (string) $url['LastSeenAt'],
                'What to do' => mkt_issue_advice((string) $issue['Code']),
            ];
        }
    }

    return $rows;
}

/** Markdown hand-off for a developer: each issue, its fix spec (or advice) and its open URLs. */
function mkt_issue_packet_markdown(array $filters): string
{
    $issues = mkt_issues_list($filters);
    $siteUrl = (string) (marketing_setting('pages.site_url') ?? 'https://www.nutraaxislabs.com');
    $md = "# Site issues — developer packet\n\n"
        . 'Generated ' . gmdate('Y-m-d H:i') . " UTC from NutraAxis Operations → Marketing → Audit & Issues for {$siteUrl}.\n\n"
        . "Each issue lists the pages it is on. When you have fixed one, tell the marketing team; they mark it fixed and the portal recrawls those pages to confirm.\n\n";
    if ($issues === []) {
        return $md . "_No issues match._\n";
    }
    $md .= "| # | Issue | Severity | Owner | Open pages |\n|---|---|---|---|---|\n";
    foreach ($issues as $n => $issue) {
        $md .= '| ' . ($n + 1) . ' | ' . str_replace('|', '\|', (string) $issue['Title']) . ' | ' . (MKT_ISSUE_SEVERITIES[$issue['Severity']] ?? $issue['Severity'])
            . ' | ' . (MKT_ISSUE_OWNERS[$issue['Owner']] ?? $issue['Owner']) . ' | ' . (int) $issue['OpenUrlCount'] . " |\n";
    }
    foreach ($issues as $n => $issue) {
        $full = mkt_issue_get((int) $issue['IssueID']);
        $md .= "\n---\n\n## " . ($n + 1) . '. ' . $issue['Title'] . ' (`' . $issue['Code'] . "`)\n\n"
            . '**Severity:** ' . (MKT_ISSUE_SEVERITIES[$issue['Severity']] ?? $issue['Severity'])
            . ' · **Owner:** ' . (MKT_ISSUE_OWNERS[$issue['Owner']] ?? $issue['Owner'])
            . ' · **Status:** ' . (MKT_ISSUE_STATUSES[$issue['Status']] ?? $issue['Status'])
            . ' · **Portal:** ' . rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/') . '/marketing/issues/view.php?id=' . (int) $issue['IssueID'] . "\n\n";
        if (!empty($full['FixSpec'])) {
            $md .= trim((string) $full['FixSpec']) . "\n";
            continue;
        }
        $md .= mkt_issue_advice((string) $issue['Code']) . "\n\n### Affected URLs\n\n";
        foreach (mkt_issue_urls((int) $issue['IssueID'], 'open') as $url) {
            $md .= '- ' . $url['Url'] . ($url['Detail'] ? ' — ' . $url['Detail'] : '') . "\n";
        }
    }

    return $md;
}
