<?php

require_once __DIR__ . '/marketing-performance.php';
require_once __DIR__ . '/marketing-calendar.php';
require_once __DIR__ . '/mail.php';

const MKT_RESPONSE_KINDS = [
    'comment'     => 'Comment',
    'reply'       => 'Reply to a comment',
    'dm'          => 'Direct message',
    'email_reply' => 'Email reply',
    'mention'     => 'Mention / tag',
    'review'      => 'Review',
    'other'       => 'Other',
];

/** label, badge tone, what to do */
const MKT_RESPONSE_TRIAGE = [
    'adverse_event' => ['Possible adverse event', 'danger', 'Compliance decides whether this is a serious adverse event that must be reported (serious adverse event reports for dietary supplements are due to FDA within 15 business days of receipt) and what may be said. Nobody replies until compliance clears it.'],
    'claims_risk'   => ['Claims risk', 'danger', 'A reply could make a disease or unapproved claim. Compliance records what may be said; the reply then uses only approved wording or points the person to their practitioner.'],
    'complaint'     => ['Complaint', 'warning', 'Reply promptly; move order or account details to a private channel.'],
    'question'      => ['Question', 'info', 'Reply with label-consistent, approved information — no health claims.'],
    'praise'        => ['Praise', 'success', 'Optional thank-you. Do not repost testimonials that describe treating a condition.'],
    'spam'          => ['Spam', 'muted', 'Hide or delete it on the platform, then close it here.'],
    'other'         => ['Other', 'muted', 'Decide whether a reply is needed.'],
];
const MKT_RESPONSE_ESCALATE = ['claims_risk', 'adverse_event'];
const MKT_RESPONSE_STATUSES = [
    'new'       => 'Waiting for triage',
    'open'      => 'Open',
    'escalated' => 'With compliance',
    'replied'   => 'Replied',
    'closed'    => 'Closed',
];
const MKT_RESPONSE_EXTRA_CHANNELS = ['email' => 'Email', 'website' => 'Website / reviews', 'other' => 'Other'];

/** column => [label, medium social|email|both] */
const MKT_METRIC_FIELDS = [
    'Impressions'       => ['Impressions', 'social'],
    'Reach'             => ['Reach', 'social'],
    'Likes'             => ['Likes / reactions', 'social'],
    'Comments'          => ['Comments', 'social'],
    'Shares'            => ['Shares / reposts', 'social'],
    'Saves'             => ['Saves', 'social'],
    'LinkClicks'        => ['Link clicks', 'social'],
    'VideoViews'        => ['Video views', 'social'],
    'EmailSent'         => ['Sent', 'email'],
    'EmailDelivered'    => ['Delivered', 'email'],
    'EmailOpens'        => ['Opens', 'email'],
    'EmailClicks'       => ['Clicks', 'email'],
    'EmailReplies'      => ['Replies', 'email'],
    'EmailUnsubscribes' => ['Unsubscribes', 'email'],
    'EmailBounces'      => ['Bounces', 'email'],
    'Leads'             => ['Leads', 'both'],
];

/** CSV header (lower-case, spaces/dashes → underscores) => column */
const MKT_METRIC_CSV_HEADERS = [
    'impressions' => 'Impressions', 'reach' => 'Reach', 'likes' => 'Likes', 'reactions' => 'Likes', 'comments' => 'Comments',
    'shares' => 'Shares', 'reposts' => 'Shares', 'saves' => 'Saves', 'link_clicks' => 'LinkClicks', 'clicks' => 'LinkClicks',
    'video_views' => 'VideoViews', 'views' => 'VideoViews', 'sent' => 'EmailSent', 'email_sent' => 'EmailSent',
    'delivered' => 'EmailDelivered', 'opens' => 'EmailOpens', 'email_opens' => 'EmailOpens', 'email_clicks' => 'EmailClicks',
    'replies' => 'EmailReplies', 'unsubscribes' => 'EmailUnsubscribes', 'bounces' => 'EmailBounces', 'leads' => 'Leads',
];

const MKT_SCORE_LEVELS = [
    'asset'    => 'Campaign assets',
    'campaign' => 'Campaigns',
    'content'  => 'Long-form content',
    'topic'    => 'Topics',
    'interest' => 'Interests',
];
const MKT_SCORE_CONFIDENCE = ['low' => 'Early read', 'medium' => 'Medium', 'high' => 'High'];

/* ---------- Redaction ---------- */

/**
 * Replace emails, phone numbers and @handles (same rules as functions-marketing/src/lib/mkt/redact.js).
 *
 * @return array{0: string, 1: int}
 */
function mkt_redact_text(string $text): array
{
    $count = 0;
    $text = preg_replace('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', '[email]', $text, -1, $n1) ?? $text;
    $text = preg_replace('/(?:\+?1[\s.-]?)?\(?\b\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/', '[phone]', $text, -1, $n2) ?? $text;
    $text = preg_replace('/(^|[^\w\[])@[A-Za-z0-9_.]{2,30}/u', '$1[handle]', $text, -1, $n3) ?? $text;
    $count = $n1 + $n2 + $n3;

    return [$text, $count];
}

/* ---------- Responses ---------- */

function mkt_response_triage_info(?string $label): array
{
    $info = MKT_RESPONSE_TRIAGE[$label ?? ''] ?? null;

    return $info === null
        ? ['label' => 'Not triaged', 'tone' => 'muted', 'advice' => 'Waiting for triage.']
        : ['label' => $info[0], 'tone' => $info[1], 'advice' => $info[2]];
}

function mkt_response_triage_badge(?string $label): string
{
    $info = mkt_response_triage_info($label);
    $class = match ($info['tone']) {
        'danger'  => 'status-cancelled',
        'warning' => 'status-submitted',
        'success' => 'status-approved',
        default   => 'status-draft',
    };

    return '<span class="status-badge ' . $class . '">' . htmlspecialchars($info['label']) . '</span>';
}

function mkt_response_channels(): array
{
    $channels = [];
    foreach (mkt_channels() as $key => $channel) {
        $channels[$key] = $channel['label'];
    }

    return $channels + MKT_RESPONSE_EXTRA_CHANNELS;
}

/** Scheduled and posted assets, newest first, for linking responses and metrics. */
function mkt_engagement_assets(): array
{
    $rows = db()->query(<<<SQL
        SELECT a.AssetID, a.Channel, a.SequenceNo, a.Title, a.Subject, a.Status, c.Name AS CampaignName,
               CONVERT(varchar(19), COALESCE(a.PostedAt, a.ScheduledAt), 120) AS WhenAt
        FROM dbo.MktAsset a
        INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
        WHERE a.Status IN (N'scheduled', N'posted')
        ORDER BY COALESCE(a.PostedAt, a.ScheduledAt) DESC, a.AssetID DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $channels = mkt_response_channels();
    $out = [];
    foreach ($rows as $row) {
        $title = trim((string) ($row['Title'] ?: $row['Subject'])) ?: 'Part ' . (int) $row['SequenceNo'];
        $out[(int) $row['AssetID']] = '#' . (int) $row['AssetID'] . ' ' . ($channels[$row['Channel']] ?? $row['Channel'])
            . ' — ' . mb_strimwidth((string) $row['CampaignName'], 0, 50, '…') . ': ' . mb_strimwidth($title, 0, 60, '…')
            . ' (' . ($row['Status'] === 'posted' ? 'posted ' : 'scheduled ') . mkt_cal_format($row['WhenAt'], 'M j') . ')';
    }

    return $out;
}

function mkt_response_escalation_hours(): int
{
    return max(1, (int) marketing_setting('engagement.escalation_hours', '24'));
}

/** Must match escalationTask() in functions-marketing/src/lib/jobs/engagement.js. */
function mkt_response_escalation_task(int $responseId, string $label): array
{
    $what = $label === 'adverse_event' ? 'possible adverse event' : 'claims-risk response';

    return [
        'title'  => 'Compliance review (' . mkt_response_escalation_hours() . "-hour): $what — response #$responseId",
        'detail' => $label === 'adverse_event'
            ? 'Someone may be describing a side effect or reaction. Decide whether it must be reported, record the decision, and clear it before anyone replies.'
            : 'A reply could make a disease or unapproved claim. Record what may (and may not) be said, then clear it so the coordinator can reply.',
    ];
}

function mkt_response_compliance_user(): ?int
{
    $reviewers = mkt_compliance_reviewers();

    return count($reviewers) === 1 ? (int) $reviewers[0]['UserID'] : null;
}

function mkt_response_open_escalation_task(int $responseId, string $label, string $dueUtc): void
{
    $task = mkt_response_escalation_task($responseId, $label);
    $due = (new DateTimeImmutable($dueUtc, new DateTimeZone('UTC')))->setTimezone(mkt_cal_tz())->format('Y-m-d');
    $key = "response:$responseId:compliance";
    $exists = db()->prepare("SELECT 1 FROM dbo.MktTask WHERE AutoKey = :k AND Status = N'open'");
    $exists->execute(['k' => $key]);
    if ($exists->fetchColumn()) {
        return;
    }
    try {
        db()->prepare(<<<SQL
            INSERT INTO dbo.MktTask (Title, Detail, TaskType, AssigneeRole, AssigneeUserID, RefType, RefID, Href, DueDate, Priority, AutoKey)
            VALUES (:t, :d, N'response_compliance', N'compliance', :u, N'response', :id, :h, :due, N'high', :k)
        SQL)->execute([
            't' => $task['title'], 'd' => $task['detail'], 'u' => mkt_response_compliance_user(), 'id' => $responseId,
            'h' => '/marketing/performance/response.php?id=' . $responseId, 'due' => $due, 'k' => $key,
        ]);
    } catch (PDOException) {
        // Opened concurrently by the triage job (filtered unique index on open AutoKey).
    }
}

function mkt_response_close_escalation_task(int $responseId, string $note): void
{
    db()->prepare(<<<SQL
        UPDATE dbo.MktTask SET Status = N'done', CompletedAt = SYSUTCDATETIME(), CompletedBy = :u, UpdatedAt = SYSUTCDATETIME(), CompletionNote = :n
        WHERE AutoKey = :k AND Status = N'open'
    SQL)->execute(['u' => marketing_user_id(), 'n' => mb_substr($note, 0, 1000), 'k' => "response:$responseId:compliance"]);
}

/**
 * Tasks the inbox calls for (merged into mkt_tasks_desired): one per escalated response, one grouped reply task,
 * and one for responses the AI could not triage.
 *
 * @param callable(string, array): void $add
 */
function mkt_response_desired_tasks(callable $add): void
{
    $complianceUser = mkt_response_compliance_user();
    $escalated = db()->query("SELECT ResponseID, Triage, CONVERT(varchar(19), EscalationDueAt, 120) AS DueAt FROM dbo.MktResponse WHERE Status = N'escalated'")->fetchAll();
    foreach ($escalated as $row) {
        $id = (int) $row['ResponseID'];
        $task = mkt_response_escalation_task($id, (string) $row['Triage']);
        $add("response:$id:compliance", [
            'Title' => $task['title'], 'Detail' => $task['detail'], 'TaskType' => 'response_compliance', 'AssigneeRole' => 'compliance',
            'AssigneeUserID' => $complianceUser, 'RefType' => 'response', 'RefID' => $id,
            'Href' => '/marketing/performance/response.php?id=' . $id, 'Priority' => 'high',
            'DueDate' => $row['DueAt'] ? mkt_cal_format((string) $row['DueAt'], 'Y-m-d') : null,
        ]);
    }

    $reply = db()->query(<<<SQL
        SELECT COUNT(*) AS N, SUM(CASE WHEN Triage = N'complaint' THEN 1 ELSE 0 END) AS Complaints,
               CONVERT(varchar(19), MIN(COALESCE(ComplianceAt, TriagedAt, ReceivedAt)), 120) AS Since
        FROM dbo.MktResponse
        WHERE Status = N'open' AND (Triage IN (N'question', N'complaint') OR ComplianceAt IS NOT NULL)
    SQL)->fetch();
    if ((int) ($reply['N'] ?? 0) > 0) {
        $count = (int) $reply['N'];
        $add('responses:reply', [
            'Title' => 'Reply to ' . $count . ' response' . ($count === 1 ? '' : 's') . ' in the Response Inbox',
            'TaskType' => 'response_reply', 'AssigneeRole' => 'coordinator', 'RefType' => 'response', 'RefID' => null,
            'Href' => '/marketing/performance/inbox.php', 'Priority' => (int) $reply['Complaints'] > 0 ? 'high' : 'normal',
            'DueDate' => mkt_task_due((string) $reply['Since'], 'reply'),
            'Detail' => 'Questions, complaints and compliance-cleared responses are waiting. Reply on the platform, then record it here.',
        ]);
    }

    $stuck = (int) db()->query("SELECT COUNT(*) FROM dbo.MktResponse WHERE Status = N'new' AND TriageAttempts >= 3")->fetchColumn();
    if ($stuck > 0) {
        $add('responses:triage', [
            'Title' => "Triage $stuck response" . ($stuck === 1 ? '' : 's') . ' by hand (AI triage failed)',
            'TaskType' => 'response_triage', 'AssigneeRole' => 'coordinator', 'RefType' => 'response', 'RefID' => null,
            'Href' => '/marketing/performance/inbox.php?tab=action', 'Priority' => 'high',
            'Detail' => 'Open each response, pick its label, and escalate anything that mentions a condition, a drug or a reaction.',
        ]);
    }
}

function mkt_response_create(array $input): array
{
    $channels = mkt_response_channels();
    $channel = (string) ($input['channel'] ?? '');
    $kind = (string) ($input['kind'] ?? '');
    $text = trim(str_replace("\r\n", "\n", (string) ($input['text'] ?? '')));
    $url = trim((string) ($input['url'] ?? ''));
    $assetId = (int) ($input['asset_id'] ?? 0) ?: null;

    if (!isset($channels[$channel])) {
        return ['ok' => false, 'error' => 'Choose a channel.'];
    }
    if (!isset(MKT_RESPONSE_KINDS[$kind])) {
        return ['ok' => false, 'error' => 'Choose what kind of response it is.'];
    }
    if ($text === '') {
        return ['ok' => false, 'error' => 'Paste the response text.'];
    }
    if (mb_strlen($text) > 4000) {
        return ['ok' => false, 'error' => 'Response text is limited to 4,000 characters.'];
    }
    if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url))) {
        return ['ok' => false, 'error' => 'The link must be a full http(s) URL.'];
    }
    $received = trim((string) ($input['received_at'] ?? ''));
    $receivedUtc = $received === '' ? gmdate('Y-m-d H:i:s') : mkt_cal_local_to_utc($received);
    if ($receivedUtc === null) {
        return ['ok' => false, 'error' => 'Enter when it was received.'];
    }
    if ($receivedUtc > gmdate('Y-m-d H:i:s', time() + 300)) {
        return ['ok' => false, 'error' => 'The received time is in the future.'];
    }
    $campaignId = null;
    if ($assetId !== null) {
        $stmt = db()->prepare('SELECT CampaignID FROM dbo.MktAsset WHERE AssetID = :id');
        $stmt->execute(['id' => $assetId]);
        $campaignId = $stmt->fetchColumn();
        if ($campaignId === false) {
            return ['ok' => false, 'error' => 'That asset was not found.'];
        }
        $campaignId = (int) $campaignId;
    }

    [$redacted, $count] = mkt_redact_text($text);
    $stmt = db()->prepare(<<<SQL
        INSERT INTO dbo.MktResponse (AssetID, CampaignID, Channel, Kind, ExternalUrl, ReceivedAt, BodyText, RedactionCount, CreatedBy)
        OUTPUT INSERTED.ResponseID
        VALUES (:asset, :campaign, :channel, :kind, :url, :received, :body, :count, :user)
    SQL);
    $stmt->execute([
        'asset' => $assetId, 'campaign' => $campaignId, 'channel' => $channel, 'kind' => $kind, 'url' => $url ?: null,
        'received' => $receivedUtc, 'body' => $redacted, 'count' => $count, 'user' => marketing_user_id(),
    ]);

    return ['ok' => true, 'id' => (int) $stmt->fetchColumn(), 'redacted' => $count];
}

function mkt_response_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT r.*, CONVERT(varchar(19), r.ReceivedAt, 120) AS ReceivedAtText, CONVERT(varchar(19), r.EscalationDueAt, 120) AS DueAtText,
               a.Title AS AssetTitle, a.Subject AS AssetSubject, a.SequenceNo, a.Channel AS AssetChannel, a.ExternalPostUrl,
               c.Name AS CampaignName,
               ut.UserName AS TriagedByName, uc.UserName AS ComplianceByName, ur.UserName AS RepliedByName,
               ux.UserName AS ClosedByName, un.UserName AS CreatedByName
        FROM dbo.MktResponse r
        LEFT JOIN dbo.MktAsset a ON a.AssetID = r.AssetID
        LEFT JOIN dbo.MktCampaign c ON c.CampaignID = r.CampaignID
        LEFT JOIN dbo.[User] ut ON ut.UserID = r.TriagedBy
        LEFT JOIN dbo.[User] uc ON uc.UserID = r.ComplianceBy
        LEFT JOIN dbo.[User] ur ON ur.UserID = r.RepliedBy
        LEFT JOIN dbo.[User] ux ON ux.UserID = r.ClosedBy
        LEFT JOIN dbo.[User] un ON un.UserID = r.CreatedBy
        WHERE r.ResponseID = :id
    SQL);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function mkt_responses_list(array $filters = []): array
{
    $where = [];
    $params = [];
    switch ($filters['tab'] ?? 'action') {
        case 'escalated':
            $where[] = "r.Status = N'escalated'";
            break;
        case 'done':
            $where[] = "r.Status IN (N'replied', N'closed')";
            break;
        case 'all':
            break;
        default:
            $where[] = "r.Status IN (N'new', N'open', N'escalated')";
    }
    if (isset(MKT_RESPONSE_TRIAGE[$filters['triage'] ?? ''])) {
        $where[] = 'r.Triage = :triage';
        $params['triage'] = $filters['triage'];
    }
    if ((int) ($filters['asset_id'] ?? 0) > 0) {
        $where[] = 'r.AssetID = :asset';
        $params['asset'] = (int) $filters['asset_id'];
    }
    if (trim((string) ($filters['q'] ?? '')) !== '') {
        $where[] = 'r.BodyText LIKE :q';
        $params['q'] = '%' . trim((string) $filters['q']) . '%';
    }
    $sql = <<<SQL
        SELECT TOP (300) r.ResponseID, r.AssetID, r.CampaignID, r.Channel, r.Kind, r.ExternalUrl, r.BodyText, r.Triage, r.TriageSource,
               r.Status, r.TriageError, r.TriageAttempts, CONVERT(varchar(19), r.ReceivedAt, 120) AS ReceivedAt,
               CONVERT(varchar(19), r.EscalationDueAt, 120) AS EscalationDueAt,
               CASE WHEN r.Status = N'escalated' AND r.EscalationDueAt < SYSUTCDATETIME() THEN 1 ELSE 0 END AS Overdue,
               c.Name AS CampaignName
        FROM dbo.MktResponse r
        LEFT JOIN dbo.MktCampaign c ON c.CampaignID = r.CampaignID
    SQL;
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= " ORDER BY CASE r.Status WHEN N'escalated' THEN 0 WHEN N'new' THEN 1 WHEN N'open' THEN 2 ELSE 3 END, r.EscalationDueAt, r.ReceivedAt DESC";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_response_counts(): array
{
    $row = db()->query(<<<SQL
        SELECT COUNT(*) AS Total,
               SUM(CASE WHEN Status = N'new' THEN 1 ELSE 0 END) AS Untriaged,
               SUM(CASE WHEN Status IN (N'new', N'open', N'escalated') THEN 1 ELSE 0 END) AS Action,
               SUM(CASE WHEN Status = N'escalated' THEN 1 ELSE 0 END) AS Escalated,
               SUM(CASE WHEN Status = N'escalated' AND EscalationDueAt < SYSUTCDATETIME() THEN 1 ELSE 0 END) AS Overdue,
               SUM(CASE WHEN Status = N'open' AND (Triage IN (N'question', N'complaint') OR ComplianceAt IS NOT NULL) THEN 1 ELSE 0 END) AS AwaitingReply
        FROM dbo.MktResponse
    SQL)->fetch(PDO::FETCH_ASSOC) ?: [];

    return array_map('intval', $row + ['Total' => 0, 'Untriaged' => 0, 'Action' => 0, 'Escalated' => 0, 'Overdue' => 0, 'AwaitingReply' => 0]);
}

/** Manual label. Escalating labels start the compliance clock; only compliance can take a response out of escalation. */
function mkt_response_set_triage(int $id, string $label, string $note = ''): array
{
    $row = mkt_response_get($id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Response not found.'];
    }
    if (!isset(MKT_RESPONSE_TRIAGE[$label])) {
        return ['ok' => false, 'error' => 'Choose a label.'];
    }
    if (in_array($row['Status'], ['replied', 'closed'], true)) {
        return ['ok' => false, 'error' => 'Reopen the response before changing its label.'];
    }
    $escalate = in_array($label, MKT_RESPONSE_ESCALATE, true);
    if ($row['Status'] === 'escalated' && !$escalate) {
        return ['ok' => false, 'error' => 'This response is with compliance — only a compliance reviewer can clear it.'];
    }
    $reason = trim($note) !== '' ? mb_substr(trim($note), 0, 1000) : 'Labeled by hand.';
    $due = $row['Status'] === 'escalated' ? (string) $row['DueAtText'] : gmdate('Y-m-d H:i:s', time() + mkt_response_escalation_hours() * 3600);
    db()->prepare(<<<SQL
        UPDATE dbo.MktResponse
        SET Triage = :label, TriageSource = N'manual', TriageConfidence = NULL, TriageReason = :reason, TriagedAt = SYSUTCDATETIME(),
            TriagedBy = :user, TriageError = NULL, Status = :status,
            EscalatedAt = CASE WHEN :esc1 = 1 THEN COALESCE(EscalatedAt, SYSUTCDATETIME()) ELSE NULL END,
            EscalationDueAt = CASE WHEN :esc2 = 1 THEN :due ELSE NULL END,
            UpdatedAt = SYSUTCDATETIME()
        WHERE ResponseID = :id
    SQL)->execute([
        'label' => $label, 'reason' => $reason, 'user' => marketing_user_id(), 'status' => $escalate ? 'escalated' : 'open',
        'esc1' => $escalate ? 1 : 0, 'esc2' => $escalate ? 1 : 0, 'due' => $due, 'id' => $id,
    ]);
    if ($escalate) {
        mkt_response_open_escalation_task($id, $label, $due);
        mkt_response_notify_escalations();
    }

    return ['ok' => true, 'escalated' => $escalate];
}

/** Compliance decision on an escalated response: cleared to reply (with guidance) or no public reply. */
function mkt_response_clear(int $id, string $decision, string $note): array
{
    if (!mkt_can_compliance_review()) {
        return ['ok' => false, 'error' => 'Only a compliance reviewer can clear an escalated response.'];
    }
    $row = mkt_response_get($id);
    if ($row === null || $row['Status'] !== 'escalated') {
        return ['ok' => false, 'error' => 'This response is not waiting for compliance.'];
    }
    $note = trim($note);
    if ($note === '') {
        return ['ok' => false, 'error' => 'Record the decision — what may be said, or why there is no reply (and whether it was reported).'];
    }
    if (!in_array($decision, ['reply', 'close'], true)) {
        return ['ok' => false, 'error' => 'Choose a decision.'];
    }
    $user = marketing_user_id();
    db()->prepare(<<<SQL
        UPDATE dbo.MktResponse
        SET ComplianceNote = :note, ComplianceBy = :u1, ComplianceAt = SYSUTCDATETIME(), Status = :status,
            ClosedBy = CASE WHEN :close1 = 1 THEN :u2 ELSE NULL END, ClosedAt = CASE WHEN :close2 = 1 THEN SYSUTCDATETIME() ELSE NULL END,
            UpdatedAt = SYSUTCDATETIME()
        WHERE ResponseID = :id AND Status = N'escalated'
    SQL)->execute([
        'note' => mb_substr($note, 0, 2000), 'u1' => $user, 'u2' => $user, 'status' => $decision === 'reply' ? 'open' : 'closed',
        'close1' => $decision === 'close' ? 1 : 0, 'close2' => $decision === 'close' ? 1 : 0, 'id' => $id,
    ]);
    mkt_response_close_escalation_task($id, 'Compliance: ' . ($decision === 'reply' ? 'cleared to reply. ' : 'no public reply. ') . $note);

    return ['ok' => true];
}

function mkt_response_record_reply(int $id, string $note): array
{
    $row = mkt_response_get($id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Response not found.'];
    }
    if ($row['Status'] === 'escalated') {
        return ['ok' => false, 'error' => 'Compliance has not cleared this response yet — do not reply.'];
    }
    if ($row['Status'] !== 'open') {
        return ['ok' => false, 'error' => $row['Status'] === 'new' ? 'Triage the response first.' : 'This response is already ' . strtolower(MKT_RESPONSE_STATUSES[$row['Status']]) . '.'];
    }
    [$note] = mkt_redact_text(trim($note));
    db()->prepare("UPDATE dbo.MktResponse SET Status = N'replied', ReplyNote = :n, RepliedBy = :u, RepliedAt = SYSUTCDATETIME(), UpdatedAt = SYSUTCDATETIME() WHERE ResponseID = :id AND Status = N'open'")
        ->execute(['n' => $note !== '' ? mb_substr($note, 0, 2000) : null, 'u' => marketing_user_id(), 'id' => $id]);

    return ['ok' => true];
}

function mkt_response_close(int $id, string $note = ''): array
{
    $row = mkt_response_get($id);
    if ($row === null) {
        return ['ok' => false, 'error' => 'Response not found.'];
    }
    if ($row['Status'] === 'escalated') {
        return ['ok' => false, 'error' => 'Only compliance can close an escalated response (clear it with "no public reply").'];
    }
    if (!in_array($row['Status'], ['new', 'open'], true)) {
        return ['ok' => false, 'error' => 'This response is already closed.'];
    }
    [$note] = mkt_redact_text(trim($note));
    db()->prepare("UPDATE dbo.MktResponse SET Status = N'closed', ClosedBy = :u, ClosedAt = SYSUTCDATETIME(), ReplyNote = COALESCE(:n, ReplyNote), UpdatedAt = SYSUTCDATETIME() WHERE ResponseID = :id")
        ->execute(['u' => marketing_user_id(), 'n' => $note !== '' ? mb_substr($note, 0, 2000) : null, 'id' => $id]);

    return ['ok' => true];
}

function mkt_response_reopen(int $id): array
{
    $row = mkt_response_get($id);
    if ($row === null || !in_array($row['Status'], ['replied', 'closed'], true)) {
        return ['ok' => false, 'error' => 'Only replied or closed responses can be reopened.'];
    }
    db()->prepare("UPDATE dbo.MktResponse SET Status = CASE WHEN Triage IS NULL THEN N'new' ELSE N'open' END, ClosedBy = NULL, ClosedAt = NULL, UpdatedAt = SYSUTCDATETIME() WHERE ResponseID = :id")
        ->execute(['id' => $id]);

    return ['ok' => true];
}

/**
 * Email compliance reviewers about escalations not yet notified. Marks them notified only when a message went out.
 *
 * @return array{sent: int, pending: int, error: ?string}
 */
function mkt_response_notify_escalations(): array
{
    $rows = db()->query(<<<SQL
        SELECT ResponseID, Triage, Channel, Kind, BodyText, CONVERT(varchar(19), ReceivedAt, 120) AS ReceivedAt,
               CONVERT(varchar(19), EscalationDueAt, 120) AS DueAt
        FROM dbo.MktResponse WHERE Status = N'escalated' AND EscalationNotifiedAt IS NULL
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if ($rows === []) {
        return ['sent' => 0, 'pending' => 0, 'error' => null];
    }
    $to = [];
    foreach (mkt_compliance_reviewers() as $reviewer) {
        $email = trim((string) $reviewer['UserLogin']);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $to[$email] = (string) $reviewer['UserName'];
        }
    }
    if ($to === []) {
        return ['sent' => 0, 'pending' => count($rows), 'error' => 'No compliance reviewer has an email login.'];
    }
    $base = rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
    $channels = mkt_response_channels();
    $mark = db()->prepare('UPDATE dbo.MktResponse SET EscalationNotifiedAt = SYSUTCDATETIME() WHERE ResponseID = :id');
    $sent = 0;
    $error = null;
    foreach ($rows as $row) {
        $id = (int) $row['ResponseID'];
        $label = mkt_response_triage_info((string) $row['Triage'])['label'];
        $due = mkt_cal_format((string) $row['DueAt'], 'D M j, g:i A T');
        $link = $base . '/marketing/performance/response.php?id=' . $id;
        $body = "A response needs compliance review by $due.\n\n"
            . "Label: $label\n"
            . 'Channel: ' . ($channels[$row['Channel']] ?? $row['Channel']) . ' (' . (MKT_RESPONSE_KINDS[$row['Kind']] ?? $row['Kind']) . ")\n"
            . 'Received: ' . mkt_cal_format((string) $row['ReceivedAt'], 'D M j, g:i A T') . "\n\n"
            . "Text (names, handles, emails and phone numbers removed):\n" . (string) $row['BodyText'] . "\n\n"
            . "Review and clear it: $link\n\nNobody replies until it is cleared.";
        $result = mail_send_multi_result($to, [], "Compliance review due $due: $label (response #$id)", $body);
        if (!empty($result['ok'])) {
            $mark->execute(['id' => $id]);
            $sent++;
        } else {
            $error = (string) ($result['error'] ?? 'Email failed.');
        }
    }

    return ['sent' => $sent, 'pending' => count($rows) - $sent, 'error' => $error];
}

/* ---------- Asset metrics ---------- */

/**
 * Save a snapshot of lifetime totals for an asset as of a date. Blank fields stay empty; at least one is required.
 *
 * @param array<string, mixed> $values column => value
 */
function mkt_asset_metric_save(int $assetId, string $asOf, array $values, string $note = '', string $source = 'manual'): array
{
    $stmt = db()->prepare("SELECT AssetID FROM dbo.MktAsset WHERE AssetID = :id AND Status IN (N'scheduled', N'posted')");
    $stmt->execute(['id' => $assetId]);
    if (!$stmt->fetchColumn()) {
        return ['ok' => false, 'error' => "Asset #$assetId is not scheduled or posted."];
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $asOf);
    if ($date === false || $date->format('Y-m-d') !== $asOf) {
        return ['ok' => false, 'error' => 'Enter the as-of date.'];
    }
    if ($asOf > mkt_cal_now_local()->format('Y-m-d')) {
        return ['ok' => false, 'error' => 'The as-of date is in the future.'];
    }
    $clean = [];
    foreach (MKT_METRIC_FIELDS as $column => $_) {
        $raw = trim(str_replace(',', '', (string) ($values[$column] ?? '')));
        if ($raw === '') {
            $clean[$column] = null;
            continue;
        }
        if (!ctype_digit($raw) || strlen($raw) > 9) {
            return ['ok' => false, 'error' => MKT_METRIC_FIELDS[$column][0] . ' must be a whole number.'];
        }
        $clean[$column] = (int) $raw;
    }
    if (array_filter($clean, static fn($v): bool => $v !== null) === []) {
        return ['ok' => false, 'error' => 'Enter at least one number.'];
    }
    $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($clean)));
    $cols = implode(', ', array_keys($clean));
    $vals = implode(', ', array_map(static fn(string $c): string => ":i$c", array_keys($clean)));
    $params = ['asset' => $assetId, 'asof' => $asOf, 'source' => $source, 'note' => trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null,
        'user' => marketing_user_id(), 'asset2' => $assetId, 'asof2' => $asOf, 'source2' => $source, 'note2' => trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null, 'user2' => marketing_user_id()];
    foreach ($clean as $column => $value) {
        $params[$column] = $value;
        $params['i' . $column] = $value;
    }
    db()->prepare(<<<SQL
        IF EXISTS (SELECT 1 FROM dbo.MktAssetMetric WHERE AssetID = :asset AND AsOfDate = :asof AND Source = :source)
            UPDATE dbo.MktAssetMetric SET {$sets}, Note = :note, UpdatedBy = :user, UpdatedAt = SYSUTCDATETIME()
            WHERE AssetID = :asset2 AND AsOfDate = :asof2 AND Source = :source2;
        ELSE
            INSERT INTO dbo.MktAssetMetric (AssetID, AsOfDate, Source, Note, EnteredBy, {$cols})
            VALUES (:asset2x, :asof2x, :source2x, :note2, :user2, {$vals});
    SQL)->execute($params + ['asset2x' => $assetId, 'asof2x' => $asOf, 'source2x' => $source]);

    return ['ok' => true];
}

function mkt_asset_metrics(int $assetId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT m.*, CONVERT(varchar(10), m.AsOfDate, 23) AS AsOf, u.UserName AS EnteredByName
        FROM dbo.MktAssetMetric m LEFT JOIN dbo.[User] u ON u.UserID = COALESCE(m.UpdatedBy, m.EnteredBy)
        WHERE m.AssetID = :id ORDER BY m.AsOfDate DESC, m.MetricID DESC
    SQL);
    $stmt->execute(['id' => $assetId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_metrics_recent(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));

    return db()->query(<<<SQL
        SELECT TOP ({$limit}) m.*, CONVERT(varchar(10), m.AsOfDate, 23) AS AsOf, a.Channel, a.Title, a.Subject, a.SequenceNo,
               c.Name AS CampaignName, u.UserName AS EnteredByName
        FROM dbo.MktAssetMetric m
        INNER JOIN dbo.MktAsset a ON a.AssetID = m.AssetID
        INNER JOIN dbo.MktCampaign c ON c.CampaignID = a.CampaignID
        LEFT JOIN dbo.[User] u ON u.UserID = COALESCE(m.UpdatedBy, m.EnteredBy)
        ORDER BY COALESCE(m.UpdatedAt, m.EnteredAt) DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_metric_delete(int $metricId): void
{
    db()->prepare('DELETE FROM dbo.MktAssetMetric WHERE MetricID = :id')->execute(['id' => $metricId]);
}

/**
 * CSV with a header row: asset_id (or external_post_id), as_of, then any metric columns (see MKT_METRIC_CSV_HEADERS).
 *
 * @return array{ok: bool, imported: int, errors: list<string>}
 */
function mkt_metrics_import_csv(string $csv): array
{
    $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
    if (count($lines) < 2) {
        return ['ok' => false, 'imported' => 0, 'errors' => ['Paste a header row and at least one data row.']];
    }
    $normalize = static fn(string $h): string => trim(preg_replace('/[\s\-]+/', '_', strtolower(trim($h, " \t\"\xEF\xBB\xBF"))) ?? '', '_');
    $headers = array_map($normalize, str_getcsv(array_shift($lines)));
    $idCol = array_search('asset_id', $headers, true);
    $extCol = array_search('external_post_id', $headers, true);
    $dateCol = array_search('as_of', $headers, true);
    if ($dateCol === false) {
        $dateCol = array_search('date', $headers, true);
    }
    if (($idCol === false && $extCol === false) || $dateCol === false) {
        return ['ok' => false, 'imported' => 0, 'errors' => ['The header row needs asset_id (or external_post_id) and as_of.']];
    }
    $metricCols = [];
    foreach ($headers as $index => $header) {
        if (isset(MKT_METRIC_CSV_HEADERS[$header])) {
            $metricCols[$index] = MKT_METRIC_CSV_HEADERS[$header];
        }
    }
    if ($metricCols === []) {
        return ['ok' => false, 'imported' => 0, 'errors' => ['No metric columns recognized (e.g. impressions, likes, comments, shares, opens, clicks).']];
    }
    $byExternal = db()->prepare("SELECT TOP 1 AssetID FROM dbo.MktAsset WHERE ExternalPostID = :e AND Status IN (N'scheduled', N'posted')");
    $imported = 0;
    $errors = [];
    foreach ($lines as $offset => $line) {
        if (trim($line) === '') {
            continue;
        }
        $lineNo = $offset + 2;
        $cells = str_getcsv($line);
        $assetId = $idCol !== false ? (int) ($cells[$idCol] ?? 0) : 0;
        if ($assetId <= 0 && $extCol !== false && trim((string) ($cells[$extCol] ?? '')) !== '') {
            $byExternal->execute(['e' => trim((string) $cells[$extCol])]);
            $assetId = (int) $byExternal->fetchColumn();
        }
        if ($assetId <= 0) {
            $errors[] = "Line $lineNo: no matching asset.";
            continue;
        }
        $rawDate = trim((string) ($cells[$dateCol] ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $rawDate) ?: DateTimeImmutable::createFromFormat('!n/j/Y', $rawDate);
        if ($date === false) {
            $errors[] = "Line $lineNo: as_of must be YYYY-MM-DD or M/D/YYYY.";
            continue;
        }
        $values = [];
        foreach ($metricCols as $index => $column) {
            $values[$column] = (string) ($cells[$index] ?? '');
        }
        $result = mkt_asset_metric_save($assetId, $date->format('Y-m-d'), $values, 'CSV import', 'import');
        if ($result['ok']) {
            $imported++;
        } else {
            $errors[] = "Line $lineNo: " . $result['error'];
        }
    }

    return ['ok' => $imported > 0, 'imported' => $imported, 'errors' => array_slice($errors, 0, 50)];
}

/* ---------- Scores ---------- */

function mkt_scores_date(): ?string
{
    $value = db()->query('SELECT CONVERT(varchar(10), MAX(ScoreDate), 23) FROM dbo.MktEngagementScore')->fetchColumn();

    return $value ? (string) $value : null;
}

/** Latest scores for a level, with a display name, best first. */
function mkt_scores_list(string $level): array
{
    if (!isset(MKT_SCORE_LEVELS[$level])) {
        return [];
    }
    $name = match ($level) {
        'asset'    => "COALESCE(NULLIF(a.Title, N''), a.Subject, N'Part ' + CAST(a.SequenceNo AS nvarchar(10)))",
        'campaign' => 'c.Name',
        'content'  => 'ct.Title',
        'topic'    => 't.Title',
        'interest' => 'i.Name',
    };
    $stmt = db()->prepare(<<<SQL
        SELECT s.*, {$name} AS Name, a.Channel, a.CampaignID AS AssetCampaignID, ac.Name AS AssetCampaignName,
               i.Priority, i.SuggestedPriority, i.RelevanceWeight
        FROM dbo.MktEngagementScore s
        LEFT JOIN dbo.MktAsset a ON s.Level = N'asset' AND a.AssetID = s.RefID
        LEFT JOIN dbo.MktCampaign ac ON ac.CampaignID = a.CampaignID
        LEFT JOIN dbo.MktCampaign c ON s.Level = N'campaign' AND c.CampaignID = s.RefID
        LEFT JOIN dbo.MktContent ct ON s.Level = N'content' AND ct.ContentID = s.RefID
        LEFT JOIN dbo.MktTopic t ON s.Level = N'topic' AND t.TopicID = s.RefID
        LEFT JOIN dbo.MktInterest i ON s.Level = N'interest' AND i.InterestID = s.RefID
        WHERE s.Level = :level AND s.ScoreDate = (SELECT MAX(ScoreDate) FROM dbo.MktEngagementScore)
        ORDER BY s.Score DESC, s.Points DESC
    SQL);
    $stmt->execute(['level' => $level]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_score_latest(string $level, int $refId): ?array
{
    $stmt = db()->prepare('SELECT TOP 1 *, CONVERT(varchar(10), ScoreDate, 23) AS ScoreDay FROM dbo.MktEngagementScore WHERE Level = :l AND RefID = :id ORDER BY ScoreDate DESC');
    $stmt->execute(['l' => $level, 'id' => $refId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function mkt_interest_apply_priority(int $interestId): array
{
    $stmt = db()->prepare('SELECT Priority, SuggestedPriority FROM dbo.MktInterest WHERE InterestID = :id');
    $stmt->execute(['id' => $interestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['SuggestedPriority'] === null || (int) $row['SuggestedPriority'] === (int) $row['Priority']) {
        return ['ok' => false, 'error' => 'There is no priority change to apply.'];
    }
    db()->prepare('UPDATE dbo.MktInterest SET Priority = SuggestedPriority, UpdatedAt = SYSUTCDATETIME(), UpdatedBy = :u WHERE InterestID = :id')
        ->execute(['u' => marketing_user_id(), 'id' => $interestId]);
    $built = audit_build_update('MktInterest', 'InterestID', (string) $interestId, ['Priority' => (int) $row['SuggestedPriority']], ['Priority' => (int) $row['Priority']]);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'priority' => (int) $row['SuggestedPriority']];
}

/* ---------- Digests ---------- */

function mkt_digests_list(int $limit = 26): array
{
    $limit = max(1, min(200, $limit));

    return db()->query(<<<SQL
        SELECT TOP ({$limit}) DigestID, CONVERT(varchar(10), PeriodStart, 23) AS PeriodStart, CONVERT(varchar(10), PeriodEnd, 23) AS PeriodEnd,
               Status, TaskCount, CostUsd, CONVERT(varchar(19), CreatedAt, 120) AS CreatedAt,
               (SELECT COUNT(*) FROM dbo.MktTask t WHERE t.RefType = N'digest' AND t.RefID = d.DigestID AND t.Status = N'done') AS DoneCount
        FROM dbo.MktDigest d ORDER BY PeriodEnd DESC
    SQL)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mkt_digest_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT d.*, CONVERT(varchar(10), d.PeriodStart, 23) AS PeriodStartText, CONVERT(varchar(10), d.PeriodEnd, 23) AS PeriodEndText,
               CONVERT(varchar(19), d.CreatedAt, 120) AS CreatedAtText, u.UserName AS CreatedByName
        FROM dbo.MktDigest d LEFT JOIN dbo.[User] u ON u.UserID = d.CreatedBy
        WHERE d.DigestID = :id
    SQL);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    foreach (['FactsJson' => 'facts', 'RecommendationsJson' => 'recommendations', 'DroppedJson' => 'dropped'] as $column => $key) {
        $row[$key] = json_decode((string) ($row[$column] ?? ''), true) ?: [];
    }

    return $row;
}

function mkt_digest_tasks(int $digestId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT t.TaskID, t.Title, t.TaskType, t.AssigneeRole, t.Status, t.Href, CONVERT(varchar(10), t.DueDate, 23) AS DueDate, u.UserName AS AssigneeName
        FROM dbo.MktTask t LEFT JOIN dbo.[User] u ON u.UserID = t.AssigneeUserID
        WHERE t.RefType = N'digest' AND t.RefID = :id ORDER BY t.TaskID
    SQL);
    $stmt->execute(['id' => $digestId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
