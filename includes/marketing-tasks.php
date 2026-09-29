<?php

require_once __DIR__ . '/marketing-content.php';

const MKT_TASK_ROLES = [
    'writer'      => 'Writer',
    'compliance'  => 'Compliance',
    'editorial'   => 'Editorial',
    'coordinator' => 'Coordinator',
];
const MKT_TASK_STATUSES = ['open' => 'Open', 'done' => 'Done', 'cancelled' => 'Cancelled'];
const MKT_TASK_PRIORITIES = ['normal' => 'Normal', 'high' => 'High'];

function mkt_task_sla_days(string $kind): int
{
    static $sla = null;
    if ($sla === null) {
        $sla = [];
        foreach (marketing_setting_lines('tasks.sla_days') as $line) {
            [$key, $days] = array_pad(array_map('trim', explode('|', $line)), 2, '');
            if ($key !== '' && is_numeric($days)) {
                $sla[$key] = max(0, (int) $days);
            }
        }
    }

    return $sla[$kind] ?? 2;
}

/** Central-time due date: the given UTC moment (or now) plus the SLA days for the kind. */
function mkt_task_due(?string $utcStart, string $kind): string
{
    $start = new DateTimeImmutable($utcStart ?: 'now', new DateTimeZone('UTC'));

    return $start->setTimezone(mkt_cal_tz())->modify('+' . mkt_task_sla_days($kind) . ' days')->format('Y-m-d');
}

function mkt_task_local_date(string $utc): string
{
    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(mkt_cal_tz())->format('Y-m-d');
}

/** The single named compliance reviewer, when there is exactly one, so compliance tasks land on a person. */
function mkt_task_compliance_user(): ?int
{
    $reviewers = mkt_compliance_reviewers();

    return count($reviewers) === 1 ? (int) $reviewers[0]['UserID'] : null;
}

/**
 * Tasks the current state of the pipeline and calendar calls for, keyed by AutoKey.
 *
 * @return array<string, array<string, mixed>>
 */
function mkt_tasks_desired(): array
{
    $tasks = [];
    $complianceUser = mkt_task_compliance_user();
    $add = static function (string $key, array $task) use (&$tasks): void {
        $tasks[$key] = $task + ['Detail' => null, 'AssigneeUserID' => null, 'DueDate' => null, 'Priority' => 'normal'];
    };

    $content = db()->query(<<<SQL
        SELECT ContentID, Title, Stage, BriefText, OwnerUserID, CreatedBy, CONVERT(varchar(10), DueDate, 23) AS DueDate, CreatedAt, BriefAt, BriefApprovedAt,
               SubmittedVersionID, SubmittedAt, ComplianceStatus, ComplianceAt, EditorialStatus, EditorialAt
        FROM dbo.MktContent
        WHERE Stage IN (N'idea', N'brief', N'draft', N'compliance_review', N'editorial', N'approved')
    SQL)->fetchAll();
    foreach ($content as $row) {
        $id = (int) $row['ContentID'];
        $href = '/marketing/content/view.php?id=' . $id;
        $title = mb_substr((string) $row['Title'], 0, 200);
        $owner = (int) ($row['OwnerUserID'] ?: $row['CreatedBy']) ?: null;
        $pieceDue = $row['DueDate'] ? substr((string) $row['DueDate'], 0, 10) : null;
        $base = ['RefType' => 'content', 'RefID' => $id, 'Href' => $href];
        switch ($row['Stage']) {
            case 'idea':
                $add("content:$id:brief", $base + ['Title' => "Write the brief: $title", 'TaskType' => 'content_brief', 'AssigneeRole' => 'writer',
                    'AssigneeUserID' => $owner, 'DueDate' => $pieceDue ?? mkt_task_due((string) $row['CreatedAt'], 'brief')]);
                break;
            case 'brief':
                if (trim((string) $row['BriefText']) === '') {
                    $add("content:$id:brief", $base + ['Title' => "Write the brief: $title", 'TaskType' => 'content_brief', 'AssigneeRole' => 'writer',
                        'AssigneeUserID' => $owner, 'DueDate' => $pieceDue ?? mkt_task_due((string) $row['CreatedAt'], 'brief')]);
                } else {
                    $add("content:$id:brief_approve", $base + ['Title' => "Approve the brief: $title", 'TaskType' => 'content_brief_approve',
                        'AssigneeRole' => 'coordinator', 'AssigneeUserID' => $owner, 'DueDate' => mkt_task_due((string) $row['BriefAt'], 'brief'),
                        'Detail' => 'Read the brief, edit it if needed, then approve it so the draft can be written.']);
                }
                break;
            case 'draft':
                $changes = $row['ComplianceStatus'] === 'changes_requested' || $row['EditorialStatus'] === 'changes_requested';
                $add("content:$id:draft", $base + [
                    'Title'          => ($changes ? 'Address review changes: ' : 'Finish the draft and submit: ') . $title,
                    'TaskType'       => 'content_draft',
                    'AssigneeRole'   => 'writer',
                    'AssigneeUserID' => $owner,
                    'DueDate'        => $pieceDue,
                    'Priority'       => $changes ? 'high' : 'normal',
                    'Detail'         => $changes ? 'A reviewer asked for changes — see the review history, revise, re-check and resubmit.' : 'Draft (or AI-draft) the piece, clear the claims check, then submit it for review.',
                ]);
                break;
            case 'compliance_review':
                $add("content:$id:compliance:{$row['SubmittedVersionID']}", $base + ['Title' => "Compliance review: $title", 'TaskType' => 'content_compliance',
                    'AssigneeRole' => 'compliance', 'AssigneeUserID' => $complianceUser, 'DueDate' => mkt_task_due((string) $row['SubmittedAt'], 'compliance')]);
                break;
            case 'editorial':
                $add("content:$id:editorial:{$row['SubmittedVersionID']}", $base + ['Title' => "Editorial review: $title", 'TaskType' => 'content_editorial',
                    'AssigneeRole' => 'editorial', 'DueDate' => mkt_task_due((string) ($row['ComplianceAt'] ?: $row['SubmittedAt']), 'editorial')]);
                break;
            case 'approved':
                $add("content:$id:publish", $base + ['Title' => "Publish and record the URL: $title", 'TaskType' => 'content_publish',
                    'AssigneeRole' => 'coordinator', 'AssigneeUserID' => $owner, 'DueDate' => $pieceDue ?? mkt_task_due((string) $row['EditorialAt'], 'publish'),
                    'Detail' => 'Put the approved version live on the site (nothing publishes automatically), then record the live URL.']);
                break;
        }
    }

    $assets = db()->query(<<<SQL
        SELECT a.CampaignID, c.Name, c.CreatedBy,
               SUM(CASE WHEN a.Status = N'changes_requested' THEN 1 ELSE 0 END) AS ChangesN,
               SUM(CASE WHEN a.Status = N'in_review' AND a.ComplianceStatus = N'pending' THEN 1 ELSE 0 END) AS ComplianceN,
               MIN(CASE WHEN a.Status = N'in_review' AND a.ComplianceStatus = N'pending' THEN a.SubmittedAt END) AS ComplianceSince,
               SUM(CASE WHEN a.Status = N'in_review' AND a.EditorialStatus = N'pending' AND a.ComplianceStatus IN (N'approved', N'not_required') THEN 1 ELSE 0 END) AS EditorialN,
               MIN(CASE WHEN a.Status = N'in_review' AND a.EditorialStatus = N'pending' AND a.ComplianceStatus IN (N'approved', N'not_required') THEN COALESCE(a.ComplianceAt, a.SubmittedAt) END) AS EditorialSince,
               SUM(CASE WHEN a.Status = N'approved' THEN 1 ELSE 0 END) AS ApprovedN,
               MIN(CASE WHEN a.Status = N'approved' THEN a.EditorialAt END) AS ApprovedSince,
               SUM(CASE WHEN a.Status = N'scheduled' AND a.ExternalPostID IS NULL AND a.ScheduledAt >= SYSUTCDATETIME() THEN 1 ELSE 0 END) AS UnloadedN,
               MIN(CASE WHEN a.Status = N'scheduled' AND a.ExternalPostID IS NULL AND a.ScheduledAt >= SYSUTCDATETIME() THEN a.ScheduledAt END) AS UnloadedNext,
               SUM(CASE WHEN a.Status = N'scheduled' AND a.ScheduledAt < SYSUTCDATETIME() THEN 1 ELSE 0 END) AS PastDueN,
               MIN(CASE WHEN a.Status = N'scheduled' AND a.ScheduledAt < SYSUTCDATETIME() THEN a.ScheduledAt END) AS PastDueSince
        FROM dbo.MktAsset a
        INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
        WHERE a.Status IN (N'changes_requested', N'in_review', N'approved', N'scheduled')
        GROUP BY a.CampaignID, c.Name, c.CreatedBy
    SQL)->fetchAll();
    $plural = static fn(int $n, string $word): string => $n . ' ' . $word . ($n === 1 ? '' : 's');
    foreach ($assets as $row) {
        $id = (int) $row['CampaignID'];
        $name = mb_substr((string) $row['Name'], 0, 180);
        $base = ['RefType' => 'campaign', 'RefID' => $id, 'Href' => '/marketing/campaigns/campaign.php?id=' . $id];
        $owner = (int) $row['CreatedBy'] ?: null;
        if ((int) $row['ChangesN'] > 0) {
            $add("campaign:$id:changes", $base + ['Title' => 'Address review changes on ' . $plural((int) $row['ChangesN'], 'asset') . ": $name",
                'TaskType' => 'campaign_changes', 'AssigneeRole' => 'writer', 'AssigneeUserID' => $owner, 'Priority' => 'high']);
        }
        if ((int) $row['ComplianceN'] > 0) {
            $add("campaign:$id:compliance", $base + ['Title' => 'Compliance review of ' . $plural((int) $row['ComplianceN'], 'asset') . ": $name",
                'TaskType' => 'campaign_compliance', 'AssigneeRole' => 'compliance', 'AssigneeUserID' => $complianceUser,
                'DueDate' => mkt_task_due((string) $row['ComplianceSince'], 'compliance'), 'Href' => '/marketing/campaigns/?tab=review']);
        }
        if ((int) $row['EditorialN'] > 0) {
            $add("campaign:$id:editorial", $base + ['Title' => 'Editorial review of ' . $plural((int) $row['EditorialN'], 'asset') . ": $name",
                'TaskType' => 'campaign_editorial', 'AssigneeRole' => 'editorial',
                'DueDate' => mkt_task_due((string) $row['EditorialSince'], 'editorial'), 'Href' => '/marketing/campaigns/?tab=review']);
        }
        if ((int) $row['ApprovedN'] > 0) {
            $add("campaign:$id:schedule", $base + ['Title' => 'Schedule ' . $plural((int) $row['ApprovedN'], 'approved asset') . ": $name",
                'TaskType' => 'campaign_schedule', 'AssigneeRole' => 'coordinator', 'AssigneeUserID' => $owner,
                'DueDate' => mkt_task_due((string) $row['ApprovedSince'], 'schedule')]);
        }
        if ((int) $row['UnloadedN'] > 0) {
            $add("campaign:$id:load", $base + ['Title' => 'Load ' . $plural((int) $row['UnloadedN'], 'scheduled asset') . " into GoHighLevel: $name",
                'TaskType' => 'campaign_load', 'AssigneeRole' => 'coordinator', 'AssigneeUserID' => $owner,
                'DueDate' => mkt_task_local_date((string) $row['UnloadedNext']), 'Href' => '/marketing/calendar/?tab=todo']);
        }
        if ((int) $row['PastDueN'] > 0) {
            $add("campaign:$id:posted", $base + ['Title' => 'Confirm ' . $plural((int) $row['PastDueN'], 'post') . " went live: $name",
                'TaskType' => 'campaign_posted', 'AssigneeRole' => 'coordinator', 'AssigneeUserID' => $owner, 'Priority' => 'high',
                'DueDate' => mkt_task_local_date((string) $row['PastDueSince']), 'Href' => '/marketing/calendar/?tab=todo',
                'Detail' => 'The scheduled time has passed — check each post is live and mark it posted with its URL.']);
        }
    }

    return $tasks;
}

/**
 * Open the tasks the current state calls for, refresh open ones, and close the ones whose condition has cleared.
 * A reassigned auto task keeps its assignee.
 *
 * @return array{opened: int, closed: int}
 */
function mkt_tasks_sync(): array
{
    $desired = mkt_tasks_desired();
    $open = [];
    foreach (db()->query("SELECT TaskID, AutoKey, Title, Detail, CONVERT(varchar(10), DueDate, 23) AS DueDate, Priority, Href FROM dbo.MktTask WHERE Status = N'open' AND AutoKey IS NOT NULL")->fetchAll() as $row) {
        $open[(string) $row['AutoKey']] = $row;
    }
    $opened = 0;
    $insert = db()->prepare(<<<SQL
        INSERT INTO dbo.MktTask (Title, Detail, TaskType, AssigneeRole, AssigneeUserID, RefType, RefID, Href, DueDate, Priority, AutoKey)
        VALUES (:Title, :Detail, :TaskType, :AssigneeRole, :AssigneeUserID, :RefType, :RefID, :Href, :DueDate, :Priority, :AutoKey)
    SQL);
    $update = db()->prepare('UPDATE dbo.MktTask SET Title = :t, Detail = :d, DueDate = :due, Priority = :p, Href = :h, UpdatedAt = SYSUTCDATETIME() WHERE TaskID = :id');
    foreach ($desired as $key => $task) {
        if (!isset($open[$key])) {
            try {
                $insert->execute(['AutoKey' => $key] + array_intersect_key($task, array_flip(['Title', 'Detail', 'TaskType', 'AssigneeRole', 'AssigneeUserID', 'RefType', 'RefID', 'Href', 'DueDate', 'Priority'])));
                $opened++;
            } catch (PDOException $e) {
                // A concurrent sync opened it first (filtered unique index on open AutoKey).
            }
            continue;
        }
        $row = $open[$key];
        $due = $row['DueDate'] ? substr((string) $row['DueDate'], 0, 10) : null;
        if ($row['Title'] !== $task['Title'] || (string) $row['Detail'] !== (string) $task['Detail'] || $due !== $task['DueDate']
            || $row['Priority'] !== $task['Priority'] || (string) $row['Href'] !== (string) $task['Href']) {
            $update->execute(['t' => $task['Title'], 'd' => $task['Detail'], 'due' => $task['DueDate'], 'p' => $task['Priority'], 'h' => $task['Href'], 'id' => (int) $row['TaskID']]);
        }
    }
    $stale = array_diff_key($open, $desired);
    if ($stale !== []) {
        db()->exec("UPDATE dbo.MktTask SET Status = N'done', CompletedAt = SYSUTCDATETIME(), UpdatedAt = SYSUTCDATETIME(), CompletionNote = N'Closed automatically — the work is done or no longer needed.' WHERE TaskID IN ("
            . implode(',', array_map(static fn(array $r): int => (int) $r['TaskID'], $stale)) . ')');
    }

    return ['opened' => $opened, 'closed' => count($stale)];
}

/** Roles whose unassigned tasks count as mine. */
function mkt_task_my_roles(): array
{
    $roles = [];
    if (marketing_can_update()) {
        $roles[] = 'writer';
        $roles[] = 'coordinator';
    }
    if (mkt_can_compliance_review()) {
        $roles[] = 'compliance';
    }
    if (mkt_can_editorial_review()) {
        $roles[] = 'editorial';
    }

    return $roles;
}

function mkt_tasks_list(array $filters = []): array
{
    $where = [];
    $params = [];
    $status = (string) ($filters['status'] ?? 'open');
    if (array_key_exists($status, MKT_TASK_STATUSES)) {
        $where[] = 't.Status = :status';
        $params['status'] = $status;
    }
    if (($filters['scope'] ?? '') === 'mine') {
        $me = (int) marketing_user_id();
        $roles = mkt_task_my_roles();
        $roleSql = $roles !== [] ? " OR (t.AssigneeUserID IS NULL AND t.AssigneeRole IN ('" . implode("','", $roles) . "'))" : '';
        $where[] = "(t.AssigneeUserID = :me{$roleSql})";
        $params['me'] = $me;
    }
    if (array_key_exists((string) ($filters['role'] ?? ''), MKT_TASK_ROLES)) {
        $where[] = 't.AssigneeRole = :role';
        $params['role'] = (string) $filters['role'];
    }
    if (($filters['kind'] ?? '') === 'manual') {
        $where[] = 't.AutoKey IS NULL';
    } elseif (($filters['kind'] ?? '') === 'auto') {
        $where[] = 't.AutoKey IS NOT NULL';
    }
    $order = $status === 'open'
        ? "CASE WHEN t.Priority = N'high' THEN 0 ELSE 1 END, CASE WHEN t.DueDate IS NULL THEN 1 ELSE 0 END, t.DueDate, t.TaskID"
        : 'COALESCE(t.CompletedAt, t.UpdatedAt) DESC';
    $stmt = db()->prepare(<<<SQL
        SELECT TOP (500) t.*, CONVERT(varchar(10), t.DueDate, 23) AS DueDateIso, a.UserName AS AssigneeName, c.UserName AS CompletedByName, cr.UserName AS CreatedByName
        FROM dbo.MktTask t
        LEFT JOIN dbo.[User] a ON a.UserID = t.AssigneeUserID
        LEFT JOIN dbo.[User] c ON c.UserID = t.CompletedBy
        LEFT JOIN dbo.[User] cr ON cr.UserID = t.CreatedBy
    SQL . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY {$order}");
    $stmt->execute($params);

    return array_map(static fn(array $row): array => ['DueDate' => $row['DueDateIso']] + $row, $stmt->fetchAll());
}

function mkt_task_counts(): array
{
    $me = (int) marketing_user_id();
    $roles = mkt_task_my_roles();
    $roleSql = $roles !== [] ? " OR (AssigneeUserID IS NULL AND AssigneeRole IN ('" . implode("','", $roles) . "'))" : '';
    $today = mkt_cal_now_local()->format('Y-m-d');
    $stmt = db()->prepare(<<<SQL
        SELECT COUNT(*) AS OpenN,
               SUM(CASE WHEN AssigneeUserID = :me{$roleSql} THEN 1 ELSE 0 END) AS MineN,
               SUM(CASE WHEN DueDate < :today THEN 1 ELSE 0 END) AS OverdueN
        FROM dbo.MktTask WHERE Status = N'open'
    SQL);
    $stmt->execute(['me' => $me, 'today' => $today]);
    $row = $stmt->fetch() ?: [];

    return ['open' => (int) ($row['OpenN'] ?? 0), 'mine' => (int) ($row['MineN'] ?? 0), 'overdue' => (int) ($row['OverdueN'] ?? 0)];
}

function mkt_task_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktTask WHERE TaskID = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

function mkt_task_create(array $input): array
{
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'Give the task a title.'];
    }
    $due = trim((string) ($input['due_date'] ?? ''));
    $href = trim((string) ($input['href'] ?? ''));
    if ($href !== '' && !str_starts_with($href, '/') && !mkt_cal_valid_url($href)) {
        return ['ok' => false, 'error' => 'The link must be a portal path (starting with /) or a full http(s) URL.'];
    }
    $role = (string) ($input['assignee_role'] ?? '');
    $data = [
        'Title'          => mb_substr($title, 0, 300),
        'Detail'         => trim((string) ($input['detail'] ?? '')) !== '' ? mb_substr(trim((string) $input['detail']), 0, 2000) : null,
        'TaskType'       => 'manual',
        'AssigneeRole'   => array_key_exists($role, MKT_TASK_ROLES) ? $role : null,
        'AssigneeUserID' => (int) ($input['assignee_user_id'] ?? 0) ?: null,
        'Href'           => $href !== '' ? mb_substr($href, 0, 500) : null,
        'DueDate'        => preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null,
        'Priority'       => ($input['priority'] ?? '') === 'high' ? 'high' : 'normal',
        'CreatedBy'      => marketing_user_id(),
    ];
    $cols = array_keys($data);
    $stmt = db()->prepare('INSERT INTO dbo.MktTask (' . implode(', ', $cols) . ') OUTPUT INSERTED.TaskID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
    $stmt->execute($data);
    $id = db_fetch_inserted_int($stmt, 'inserted_id');
    $built = audit_build_insert('MktTask', $data, 'TaskID', $id);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'id' => $id];
}

/** Manual tasks are completed, cancelled or reopened by people; automatic tasks close when the work is done. */
function mkt_task_set_status(int $id, string $status, string $note = ''): array
{
    $task = mkt_task_get($id);
    if ($task === null) {
        return ['ok' => false, 'error' => 'Task not found.'];
    }
    if ($task['AutoKey'] !== null) {
        return ['ok' => false, 'error' => 'Automatic tasks close by themselves once the work is done.'];
    }
    if (!array_key_exists($status, MKT_TASK_STATUSES) || $status === $task['Status']) {
        return ['ok' => false, 'error' => 'Nothing to change.'];
    }
    $closing = $status !== 'open';
    db()->prepare('UPDATE dbo.MktTask SET Status = :s, CompletedBy = :u, CompletedAt = ' . ($closing ? 'SYSUTCDATETIME()' : 'NULL') . ', CompletionNote = :n, UpdatedAt = SYSUTCDATETIME() WHERE TaskID = :id')
        ->execute([
            's'  => $status,
            'u'  => $closing ? marketing_user_id() : null,
            'n'  => $closing && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
            'id' => $id,
        ]);
    $built = audit_build_update('MktTask', 'TaskID', $id, ['Status' => $status], ['Status' => $task['Status']]);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/** Anyone with Marketing update access can (re)assign a task, automatic or manual. */
function mkt_task_assign(int $id, int $userId): array
{
    $task = mkt_task_get($id);
    if ($task === null || $task['Status'] !== 'open') {
        return ['ok' => false, 'error' => 'Only open tasks can be reassigned.'];
    }
    db()->prepare('UPDATE dbo.MktTask SET AssigneeUserID = :u, UpdatedAt = SYSUTCDATETIME() WHERE TaskID = :id')
        ->execute(['u' => $userId > 0 ? $userId : null, 'id' => $id]);

    return ['ok' => true];
}
