<?php

require_once __DIR__ . '/marketing-calendar.php';

const MKT_CONTENT_STAGES = [
    'idea'              => 'Idea',
    'brief'             => 'Brief',
    'draft'             => 'Draft',
    'compliance_review' => 'Compliance review',
    'editorial'         => 'Editorial review',
    'approved'          => 'Approved',
    'published'         => 'Published',
    'monitoring'        => 'Monitoring',
    'archived'          => 'Archived',
];
const MKT_CONTENT_BOARD = ['idea', 'brief', 'draft', 'compliance_review', 'editorial', 'approved', 'published'];
const MKT_CONTENT_EDITABLE = ['draft', 'compliance_review', 'editorial', 'approved', 'published', 'monitoring'];
const MKT_CONTENT_SOURCES = ['ai_draft' => 'AI draft', 'ai_revision' => 'AI revision', 'manual' => 'Manual edit'];
const MKT_CONTENT_META_TITLE_MAX = 60;
const MKT_CONTENT_META_DESCRIPTION_MAX = 155;

/**
 * @return array<string, array{key: string, label: string, words: int, guidance: string}>
 */
function mkt_content_types(): array
{
    $types = [];
    foreach (marketing_setting_lines('content.types') as $line) {
        [$key, $label, $words, $guidance] = array_pad(array_map('trim', explode('|', $line)), 4, '');
        if ($key !== '') {
            $types[$key] = ['key' => $key, 'label' => $label ?: $key, 'words' => (int) $words ?: 1200, 'guidance' => $guidance];
        }
    }

    return $types;
}

function mkt_content_type_label(string $key): string
{
    return mkt_content_types()[$key]['label'] ?? $key;
}

function mkt_content_select(): string
{
    return <<<SQL
        SELECT c.*, CONVERT(varchar(10), c.DueDate, 23) AS DueDateIso, t.Title AS TopicTitle, t.Status AS TopicStatus, p.Name AS ProductName, k.Keyword AS KeywordText,
               v.VersionNo AS CurrentVersionNo, v.ClaimsScore, v.ClaimsCheckedAt, v.NeedsCompliance, v.WordCount,
               o.UserName AS OwnerName
        FROM dbo.MktContent c
        LEFT JOIN dbo.MktTopic t ON t.TopicID = c.TopicID
        LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
        LEFT JOIN dbo.MktKeyword k ON k.KeywordID = c.KeywordID
        LEFT JOIN dbo.MktContentVersion v ON v.VersionID = c.CurrentVersionID
        LEFT JOIN dbo.[User] o ON o.UserID = c.OwnerUserID
    SQL;
}

function mkt_content_list(array $filters = []): array
{
    $where = [];
    $params = [];
    $stage = (string) ($filters['stage'] ?? '');
    if ($stage === 'active') {
        $where[] = "c.Stage NOT IN (N'archived')";
    } elseif (array_key_exists($stage, MKT_CONTENT_STAGES)) {
        $where[] = 'c.Stage = :stage';
        $params['stage'] = $stage;
    }
    if (($filters['type'] ?? '') !== '') {
        $where[] = 'c.ContentType = :type';
        $params['type'] = (string) $filters['type'];
    }
    if (!empty($filters['topic_id'])) {
        $where[] = 'c.TopicID = :topic';
        $params['topic'] = (int) $filters['topic_id'];
    }
    if (trim((string) ($filters['q'] ?? '')) !== '') {
        $where[] = '(c.Title LIKE :q OR c.PrimaryKeyword LIKE :q2)';
        $params['q'] = '%' . trim((string) $filters['q']) . '%';
        $params['q2'] = $params['q'];
    }
    $sql = mkt_content_select() . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY c.UpdatedAt DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return array_map('mkt_content_row', $stmt->fetchAll());
}

/** DATE columns come back in the driver's locale format; expose them as Y-m-d. */
function mkt_content_row(array $row): array
{
    $row['DueDate'] = $row['DueDateIso'];

    return $row;
}

function mkt_content_get(int $id): ?array
{
    $stmt = db()->prepare(mkt_content_select() . ' WHERE c.ContentID = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row ? mkt_content_row($row) : null;
}

function mkt_content_versions(int $contentId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT v.VersionID, v.VersionNo, v.Title, v.WordCount, v.Source, v.Note, v.ClaimsScore, v.NeedsCompliance, v.ClaimsCheckedAt,
               v.CreatedBy, v.CreatedAt, u.UserName AS CreatedByName
        FROM dbo.MktContentVersion v
        LEFT JOIN dbo.[User] u ON u.UserID = v.CreatedBy
        WHERE v.ContentID = :id
        ORDER BY v.VersionNo DESC
    SQL);
    $stmt->execute(['id' => $contentId]);

    return $stmt->fetchAll();
}

function mkt_content_version_get(int $versionId, ?int $contentId = null): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktContentVersion WHERE VersionID = :id' . ($contentId !== null ? ' AND ContentID = :c' : ''));
    $stmt->execute(['id' => $versionId] + ($contentId !== null ? ['c' => $contentId] : []));

    return $stmt->fetch() ?: null;
}

function mkt_content_version_by_no(int $contentId, int $versionNo): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktContentVersion WHERE ContentID = :c AND VersionNo = :n');
    $stmt->execute(['c' => $contentId, 'n' => $versionNo]);

    return $stmt->fetch() ?: null;
}

function mkt_content_reviews(int $contentId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT r.*, v.VersionNo, u.UserName
        FROM dbo.MktContentReview r
        LEFT JOIN dbo.MktContentVersion v ON v.VersionID = r.VersionID
        LEFT JOIN dbo.[User] u ON u.UserID = r.UserID
        WHERE r.ContentID = :id
        ORDER BY r.ReviewID DESC
    SQL);
    $stmt->execute(['id' => $contentId]);

    return $stmt->fetchAll();
}

function mkt_content_log(int $contentId, ?int $versionId, string $gate, string $decision, ?string $note = null): void
{
    db()->prepare('INSERT INTO dbo.MktContentReview (ContentID, VersionID, Gate, Decision, Note, UserID) VALUES (:c, :v, :g, :d, :n, :u)')
        ->execute([
            'c' => $contentId,
            'v' => $versionId,
            'g' => $gate,
            'd' => $decision,
            'n' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null,
            'u' => marketing_user_id(),
        ]);
}

function mkt_content_check(?array $version): ?array
{
    $decoded = json_decode((string) ($version['ClaimsCheckJson'] ?? ''), true);

    return is_array($decoded) ? $decoded : null;
}

function mkt_content_passes(?array $version): bool
{
    return $version !== null && $version['ClaimsCheckedAt'] !== null && $version['ClaimsScore'] !== null
        && (float) $version['ClaimsScore'] >= (float) marketing_setting('claims.min_score', '7');
}

function mkt_content_word_count(string $markdown): int
{
    return preg_match_all("/[A-Za-z0-9][A-Za-z0-9'’.%-]*/u", (string) preg_replace('/[#*_>`\-\[\]()]/', ' ', $markdown));
}

function mkt_content_claims(?string $claimIdsJson): array
{
    $ids = array_values(array_filter(array_map('intval', (array) (json_decode((string) $claimIdsJson, true) ?: []))));
    if ($ids === []) {
        return [];
    }

    return db()->query('SELECT c.ClaimID, c.ClaimText, c.RequiresDisclaimer, p.Name AS ProductName FROM dbo.MktClaim c LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID WHERE c.ClaimID IN (' . implode(',', $ids) . ') ORDER BY p.Name, c.ClaimID')->fetchAll();
}

function mkt_content_owner_options(): array
{
    return db()->query(<<<SQL
        SELECT u.UserID, u.UserName
        FROM dbo.[User] u
        INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
        WHERE r.Marketing LIKE N'%R%'
        ORDER BY u.UserName
    SQL)->fetchAll();
}

/* ---------- Create and targeting ---------- */

/**
 * @return array{ok: bool, error?: string, data?: array<string, mixed>}
 */
function mkt_content_targeting(array $input, ?array $before = null): array
{
    $types = mkt_content_types();
    $type = (string) ($input['content_type'] ?? ($before['ContentType'] ?? 'article'));
    if (!isset($types[$type])) {
        return ['ok' => false, 'error' => 'Choose a content type.'];
    }
    $topicId = (int) ($input['topic_id'] ?? 0);
    $topic = null;
    if ($topicId > 0) {
        $topic = mkt_topic_get($topicId);
        if ($topic === null || ($topic['Status'] !== 'accepted' && (int) ($before['TopicID'] ?? 0) !== $topicId)) {
            return ['ok' => false, 'error' => 'Link an accepted topic (or none).'];
        }
    }
    $productId = (int) ($input['product_id'] ?? 0);
    if ($productId > 0) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM dbo.MktProduct WHERE ProductID = :id');
        $stmt->execute(['id' => $productId]);
        if ((int) $stmt->fetchColumn() === 0) {
            return ['ok' => false, 'error' => 'Unknown product.'];
        }
    }
    if ($type === 'product_page' && $productId === 0) {
        return ['ok' => false, 'error' => 'A product page needs a product.'];
    }
    if ($topicId === 0 && $productId === 0) {
        return ['ok' => false, 'error' => 'Link an accepted topic or a product — the brief is built from its evidence or claims.'];
    }
    $keywordId = (int) ($input['keyword_id'] ?? 0);
    $keywordText = null;
    if ($keywordId > 0) {
        $stmt = db()->prepare('SELECT Keyword FROM dbo.MktKeyword WHERE KeywordID = :id');
        $stmt->execute(['id' => $keywordId]);
        $keywordText = $stmt->fetchColumn();
        if ($keywordText === false) {
            return ['ok' => false, 'error' => 'Unknown keyword.'];
        }
    }
    $primary = trim((string) ($input['primary_keyword'] ?? '')) ?: ($keywordText !== null ? (string) $keywordText : '');
    $audience = (string) ($input['audience'] ?? '');
    if (!array_key_exists($audience, MKT_CLAIM_AUDIENCES)) {
        $audience = array_key_exists((string) ($topic['Audience'] ?? ''), MKT_CLAIM_AUDIENCES) ? (string) $topic['Audience'] : 'both';
    }
    $words = (int) ($input['target_words'] ?? 0);

    return ['ok' => true, 'data' => [
        'ContentType'       => $type,
        'TopicID'           => $topicId ?: null,
        'ProductID'         => $productId ?: null,
        'KeywordID'         => $keywordId ?: null,
        'PrimaryKeyword'    => $primary !== '' ? mb_substr($primary, 0, 200) : null,
        'SecondaryKeywords' => trim((string) ($input['secondary_keywords'] ?? '')) !== '' ? mb_substr(trim((string) $input['secondary_keywords']), 0, 1000) : null,
        'Audience'          => $audience,
        'TargetWords'       => $words > 0 ? max(200, min(5000, $words)) : null,
    ], 'topic' => $topic];
}

function mkt_content_details(array $input): array
{
    $url = trim((string) ($input['target_url'] ?? ''));
    $due = trim((string) ($input['due_date'] ?? ''));
    $owner = (int) ($input['owner_user_id'] ?? 0);

    return [
        'TargetUrl'   => $url !== '' ? mb_substr($url, 0, 1000) : null,
        'Notes'       => trim((string) ($input['notes'] ?? '')) !== '' ? mb_substr(trim((string) $input['notes']), 0, 2000) : null,
        'OwnerUserID' => $owner > 0 ? $owner : null,
        'DueDate'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null,
    ];
}

function mkt_content_create(array $input): array
{
    $targeting = mkt_content_targeting($input);
    if (!$targeting['ok']) {
        return $targeting;
    }
    $details = mkt_content_details($input);
    if ($details['TargetUrl'] !== null && !mkt_cal_valid_url($details['TargetUrl'])) {
        return ['ok' => false, 'error' => 'The target URL must be a full http(s) address.'];
    }
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        $title = (string) ($targeting['topic']['Title'] ?? '');
    }
    if ($title === '') {
        return ['ok' => false, 'error' => 'Give the piece a working title.'];
    }
    $userId = marketing_user_id();
    $data = ['Title' => mb_substr($title, 0, 300)] + $targeting['data'] + $details
        + ['Stage' => 'idea', 'CreatedBy' => $userId, 'UpdatedBy' => $userId];
    $data['OwnerUserID'] ??= $userId;
    $cols = array_keys($data);
    $stmt = db()->prepare('INSERT INTO dbo.MktContent (' . implode(', ', $cols) . ') OUTPUT INSERTED.ContentID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
    $stmt->execute($data);
    $id = db_fetch_inserted_int($stmt, 'inserted_id');
    $built = audit_build_insert('MktContent', $data, 'ContentID', $id);
    audit_log_change($built['change'], $built['reverse']);
    mkt_content_log($id, null, 'system', 'created');

    return ['ok' => true, 'id' => $id];
}

/**
 * Title, owner, due date, notes and target URL change any time. Type, links, keywords, audience and length
 * shape the brief, so they lock once the brief is approved.
 */
function mkt_content_update(int $id, array $input): array
{
    $before = mkt_content_get($id);
    if ($before === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if ($before['Stage'] === 'archived') {
        return ['ok' => false, 'error' => 'Archived content cannot be edited.'];
    }
    $data = mkt_content_details($input);
    if ($data['TargetUrl'] !== null && !mkt_cal_valid_url($data['TargetUrl'])) {
        return ['ok' => false, 'error' => 'The target URL must be a full http(s) address.'];
    }
    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'The working title cannot be empty.'];
    }
    $data['Title'] = mb_substr($title, 0, 300);
    if ($before['BriefApprovedAt'] === null) {
        $targeting = mkt_content_targeting($input, $before);
        if (!$targeting['ok']) {
            return $targeting;
        }
        $data += $targeting['data'];
    }
    $changed = array_filter($data, static fn($value, string $col): bool => (string) ($before[$col] ?? '') !== (string) ($value ?? ''), ARRAY_FILTER_USE_BOTH);
    if ($changed === []) {
        return ['ok' => true, 'changed' => false];
    }
    $sets = array_map(static fn(string $c): string => "$c = :$c", array_keys($changed));
    db()->prepare('UPDATE dbo.MktContent SET ' . implode(', ', $sets) . ', UpdatedBy = :UpdatedBy, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id')
        ->execute($changed + ['UpdatedBy' => marketing_user_id(), 'id' => $id]);
    $built = audit_build_update('MktContent', 'ContentID', $id, $changed, array_intersect_key($before, $changed));
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'changed' => true];
}

/* ---------- Brief ---------- */

function mkt_content_save_brief(int $id, string $text): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if (!in_array($content['Stage'], ['idea', 'brief'], true) || $content['BriefApprovedAt'] !== null) {
        return ['ok' => false, 'error' => 'Reopen the brief before editing it.'];
    }
    $text = trim(str_replace("\r\n", "\n", $text));
    if ($text === '') {
        return ['ok' => false, 'error' => 'The brief cannot be empty.'];
    }
    if ($text === trim((string) $content['BriefText']) && $content['Stage'] === 'brief') {
        return ['ok' => true, 'changed' => false];
    }
    db()->prepare("UPDATE dbo.MktContent SET BriefText = :t, BriefBy = :u, BriefAt = SYSUTCDATETIME(), Stage = N'brief', UpdatedBy = :u2, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id")
        ->execute(['t' => mb_substr($text, 0, 30000), 'u' => marketing_user_id(), 'u2' => marketing_user_id(), 'id' => $id]);
    mkt_content_log($id, null, 'brief', 'edited');

    return ['ok' => true, 'changed' => true];
}

/** Any Marketing editor may approve the brief — it is a plan, not published copy. Approval moves the piece to draft. */
function mkt_content_approve_brief(int $id): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if ($content['Stage'] !== 'brief' || $content['BriefApprovedAt'] !== null) {
        return ['ok' => false, 'error' => 'There is no brief waiting for approval.'];
    }
    if (trim((string) $content['BriefText']) === '') {
        return ['ok' => false, 'error' => 'Write or generate the brief first.'];
    }
    db()->prepare("UPDATE dbo.MktContent SET BriefApprovedBy = :u, BriefApprovedAt = SYSUTCDATETIME(), Stage = N'draft', UpdatedBy = :u2, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id")
        ->execute(['u' => marketing_user_id(), 'u2' => marketing_user_id(), 'id' => $id]);
    mkt_content_log($id, null, 'brief', 'approved');

    return ['ok' => true];
}

function mkt_content_reopen_brief(int $id): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if ($content['Stage'] !== 'draft' || $content['BriefApprovedAt'] === null) {
        return ['ok' => false, 'error' => 'The brief can only be reopened while the piece is at the draft stage.'];
    }
    db()->prepare("UPDATE dbo.MktContent SET BriefApprovedBy = NULL, BriefApprovedAt = NULL, Stage = N'brief', UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id")
        ->execute(['u' => marketing_user_id(), 'id' => $id]);
    mkt_content_log($id, null, 'brief', 'reopened');

    return ['ok' => true];
}

/* ---------- Versions and gates ---------- */

/**
 * Save a manual edit as a new version. Editing anything past draft sends the piece back to draft and clears both
 * reviews; a published piece keeps its URL so the update can be re-published over it.
 */
function mkt_content_save_version(int $id, array $input): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if (!in_array($content['Stage'], MKT_CONTENT_EDITABLE, true)) {
        return ['ok' => false, 'error' => 'Approve the brief before writing the draft.'];
    }
    $title = trim((string) ($input['title'] ?? ''));
    $body = trim(str_replace("\r\n", "\n", (string) ($input['body'] ?? '')));
    if ($title === '' || $body === '') {
        return ['ok' => false, 'error' => 'Title and body are required.'];
    }
    $metaTitle = trim((string) ($input['meta_title'] ?? ''));
    $metaDescription = trim((string) ($input['meta_description'] ?? ''));
    $current = $content['CurrentVersionID'] ? mkt_content_version_get((int) $content['CurrentVersionID']) : null;
    if ($current !== null && $title === (string) $current['Title'] && $body === (string) $current['Body']
        && $metaTitle === (string) ($current['MetaTitle'] ?? '') && $metaDescription === (string) ($current['MetaDescription'] ?? '')) {
        return ['ok' => true, 'changed' => false];
    }
    $claimIds = $current['ClaimIdsJson'] ?? null;
    $userId = marketing_user_id();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(<<<SQL
            DECLARE @next INT = (SELECT ISNULL(MAX(VersionNo), 0) + 1 FROM dbo.MktContentVersion WITH (UPDLOCK, HOLDLOCK) WHERE ContentID = :c);
            INSERT INTO dbo.MktContentVersion (ContentID, VersionNo, Title, MetaTitle, MetaDescription, Body, WordCount, Source, Note, ClaimIdsJson, CreatedBy)
            OUTPUT INSERTED.VersionID AS inserted_id
            VALUES (:c2, @next, :t, :mt, :md, :b, :w, N'manual', :n, :cl, :u);
        SQL);
        $stmt->execute([
            'c'  => $id,
            'c2' => $id,
            't'  => mb_substr($title, 0, 300),
            'mt' => $metaTitle !== '' ? mb_substr($metaTitle, 0, 200) : null,
            'md' => $metaDescription !== '' ? mb_substr($metaDescription, 0, 400) : null,
            'b'  => $body,
            'w'  => mkt_content_word_count($body),
            'n'  => trim((string) ($input['note'] ?? '')) !== '' ? mb_substr(trim((string) $input['note']), 0, 500) : null,
            'cl' => $claimIds,
            'u'  => $userId,
        ]);
        $versionId = db_fetch_inserted_int($stmt, 'inserted_id');
        $reset = $content['Stage'] !== 'draft';
        $gateReset = $reset ? ', SubmittedVersionID = NULL, SubmittedBy = NULL, SubmittedAt = NULL, ComplianceStatus = NULL, ComplianceBy = NULL, ComplianceAt = NULL, EditorialStatus = NULL, EditorialBy = NULL, EditorialAt = NULL' : '';
        $pdo->prepare("UPDATE dbo.MktContent SET CurrentVersionID = :v, Stage = N'draft', UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME(){$gateReset} WHERE ContentID = :id")
            ->execute(['v' => $versionId, 'u' => $userId, 'id' => $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    mkt_content_log($id, $versionId, 'system', $reset ? 'returned_to_draft' : 'edited',
        $reset ? 'Edited while ' . (MKT_CONTENT_STAGES[$content['Stage']] ?? $content['Stage']) . ' — reviews cleared.' : null);

    return ['ok' => true, 'changed' => true, 'reset' => $reset, 'version_id' => $versionId];
}

/**
 * Submit the current version: it needs a claims check at or above claims.min_score. Compliance review is required
 * when the check found claims, efficacy/condition references or flag terms (or when review.compliance_mode = all).
 */
function mkt_content_submit(int $id): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if ($content['Stage'] !== 'draft') {
        return ['ok' => false, 'error' => 'Only a piece at the draft stage can be submitted.'];
    }
    $version = $content['CurrentVersionID'] ? mkt_content_version_get((int) $content['CurrentVersionID']) : null;
    if ($version === null) {
        return ['ok' => false, 'error' => 'Write or generate the draft first.'];
    }
    if ($version['ClaimsCheckedAt'] === null) {
        return ['ok' => false, 'error' => 'Run the claims check on the current version before submitting.'];
    }
    if (!mkt_content_passes($version)) {
        return ['ok' => false, 'error' => 'Claims score ' . $version['ClaimsScore'] . ' is below the minimum of ' . marketing_setting('claims.min_score', '7') . ' — revise the draft first.'];
    }
    $needsCompliance = marketing_setting('review.compliance_mode', 'claims') === 'all' || !empty($version['NeedsCompliance']);
    db()->prepare(<<<SQL
        UPDATE dbo.MktContent
        SET Stage = :stage, SubmittedVersionID = :v, SubmittedBy = :u, SubmittedAt = SYSUTCDATETIME(),
            ComplianceStatus = :compliance, ComplianceBy = NULL, ComplianceAt = NULL,
            EditorialStatus = N'pending', EditorialBy = NULL, EditorialAt = NULL,
            UpdatedBy = :u2, UpdatedAt = SYSUTCDATETIME()
        WHERE ContentID = :id
    SQL)->execute([
        'stage'      => $needsCompliance ? 'compliance_review' : 'editorial',
        'v'          => (int) $version['VersionID'],
        'u'          => marketing_user_id(),
        'u2'         => marketing_user_id(),
        'compliance' => $needsCompliance ? 'pending' : 'not_required',
        'id'         => $id,
    ]);
    mkt_content_log($id, (int) $version['VersionID'], 'submit', 'submitted',
        $needsCompliance ? 'Compliance review required.' : 'No claims found — compliance review not required.');

    return ['ok' => true, 'compliance' => $needsCompliance];
}

/** Users who may not review the submitted version: whoever created it and whoever submitted it. */
function mkt_content_own_work(array $content): bool
{
    $me = marketing_user_id();
    if ($me === null) {
        return false;
    }
    $version = $content['SubmittedVersionID'] ? mkt_content_version_get((int) $content['SubmittedVersionID']) : null;

    return in_array($me, [(int) ($version['CreatedBy'] ?? 0), (int) ($content['SubmittedBy'] ?? 0)], true);
}

function mkt_content_pending_gate(array $content): ?string
{
    return match ($content['Stage']) {
        'compliance_review' => 'compliance',
        'editorial'         => 'editorial',
        default             => null,
    };
}

function mkt_content_review(int $id, string $gate, string $decision, ?string $note = null): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if (!in_array($gate, ['compliance', 'editorial'], true) || !in_array($decision, ['approved', 'changes_requested'], true)) {
        return ['ok' => false, 'error' => 'Unknown review action.'];
    }
    if (mkt_content_pending_gate($content) !== $gate) {
        return ['ok' => false, 'error' => ucfirst($gate) . ' review is not pending on this piece.'];
    }
    if ((int) $content['SubmittedVersionID'] !== (int) $content['CurrentVersionID']) {
        return ['ok' => false, 'error' => 'The piece changed after it was submitted — it must be resubmitted.'];
    }
    if (mkt_content_own_work($content)) {
        return ['ok' => false, 'error' => 'You wrote or submitted this version — another reviewer must review it.'];
    }
    if ($decision === 'changes_requested' && trim((string) $note) === '') {
        return ['ok' => false, 'error' => 'Say what needs to change.'];
    }
    if ($gate === 'compliance' && !mkt_can_compliance_review()) {
        return ['ok' => false, 'error' => 'Only users whose role grants Marketing Compliance Review can clear compliance.'];
    }
    if ($gate === 'editorial' && !mkt_can_editorial_review()) {
        return ['ok' => false, 'error' => 'Editorial approval requires full Marketing access.'];
    }

    $prefix = $gate === 'compliance' ? 'Compliance' : 'Editorial';
    $stage = match (true) {
        $decision === 'changes_requested' => 'draft',
        $gate === 'compliance'            => 'editorial',
        default                           => 'approved',
    };
    $extra = $decision === 'changes_requested' && $gate === 'compliance' ? ', EditorialStatus = NULL' : '';
    db()->prepare("UPDATE dbo.MktContent SET {$prefix}Status = :d, {$prefix}By = :u, {$prefix}At = SYSUTCDATETIME(), Stage = :s, UpdatedAt = SYSUTCDATETIME(){$extra} WHERE ContentID = :id")
        ->execute(['d' => $decision, 'u' => marketing_user_id(), 's' => $stage, 'id' => $id]);
    mkt_content_log($id, (int) $content['SubmittedVersionID'], $gate, $decision, $note);

    return ['ok' => true, 'stage' => $stage];
}

/** Publishing is manual: someone puts the approved copy live and records where. */
function mkt_content_publish(int $id, string $url): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if ($content['Stage'] !== 'approved') {
        return ['ok' => false, 'error' => 'Only approved content can be marked published.'];
    }
    $url = trim($url);
    if ($url === '' || !mkt_cal_valid_url($url)) {
        return ['ok' => false, 'error' => 'Enter the live page URL (http or https).'];
    }
    db()->prepare("UPDATE dbo.MktContent SET Stage = N'published', PublishedUrl = :url, PublishedAt = SYSUTCDATETIME(), PublishedBy = :u, UpdatedBy = :u2, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id")
        ->execute(['url' => mb_substr($url, 0, 1000), 'u' => marketing_user_id(), 'u2' => marketing_user_id(), 'id' => $id]);
    mkt_content_log($id, (int) $content['CurrentVersionID'], 'publish', 'published', $url);

    return ['ok' => true];
}

function mkt_content_update_url(int $id, string $url): array
{
    $content = mkt_content_get($id);
    if ($content === null || !in_array($content['Stage'], ['published', 'monitoring'], true)) {
        return ['ok' => false, 'error' => 'Only published content has a live URL.'];
    }
    $url = trim($url);
    if ($url === '' || !mkt_cal_valid_url($url)) {
        return ['ok' => false, 'error' => 'Enter the live page URL (http or https).'];
    }
    db()->prepare('UPDATE dbo.MktContent SET PublishedUrl = :url, UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id')
        ->execute(['url' => mb_substr($url, 0, 1000), 'u' => marketing_user_id(), 'id' => $id]);
    mkt_content_log($id, null, 'publish', 'url_updated', $url);

    return ['ok' => true];
}

function mkt_content_set_stage(int $id, string $stage): array
{
    $content = mkt_content_get($id);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    $allowed = match ($stage) {
        'monitoring' => $content['Stage'] === 'published',
        'archived'   => !in_array($content['Stage'], ['published', 'monitoring', 'archived'], true),
        default      => false,
    };
    if (!$allowed) {
        return ['ok' => false, 'error' => $stage === 'archived' ? 'Published content stays on record; it cannot be archived.' : 'That stage change is not available.'];
    }
    db()->prepare('UPDATE dbo.MktContent SET Stage = :s, UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id')
        ->execute(['s' => $stage, 'u' => marketing_user_id(), 'id' => $id]);
    mkt_content_log($id, null, 'system', $stage);

    return ['ok' => true];
}

function mkt_content_stage_counts(): array
{
    $counts = array_fill_keys(array_keys(MKT_CONTENT_STAGES), 0);
    foreach (db()->query('SELECT Stage, COUNT(*) AS n FROM dbo.MktContent GROUP BY Stage')->fetchAll() as $row) {
        $counts[(string) $row['Stage']] = (int) $row['n'];
    }

    return $counts;
}

function mkt_content_badge(string $stage): string
{
    $class = match ($stage) {
        'approved', 'published', 'monitoring' => 'active',
        'compliance_review', 'editorial'      => 'running',
        'archived'                            => 'failed',
        default                               => 'draft',
    };

    return mkt_render_badge($class, [$class => MKT_CONTENT_STAGES[$stage] ?? $stage]);
}

function mkt_content_score_badge(?array $row): string
{
    if ($row === null || $row['ClaimsScore'] === null || $row['ClaimsCheckedAt'] === null) {
        return '<span class="form-hint">Not checked</span>';
    }
    $class = mkt_content_passes($row) ? 'active' : 'failed';

    return mkt_render_badge($class, [$class => number_format((float) $row['ClaimsScore'], 1) . ' / 10']);
}

/* ---------- Markdown rendering and diff ---------- */

function mkt_md_inline(string $text): string
{
    $html = htmlspecialchars($text, ENT_QUOTES);
    $html = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', static fn(array $m): string => '<a href="' . $m[2] . '" rel="noopener nofollow" target="_blank">' . $m[1] . '</a>', $html) ?? $html;
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;
    $html = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $html) ?? $html;
    $html = preg_replace('/(?<!\w)_(?!\s)(.+?)(?<!\s)_(?!\w)/s', '<em>$1</em>', $html) ?? $html;

    return preg_replace('/`([^`]+)`/', '<code>$1</code>', $html) ?? $html;
}

/**
 * Minimal, safe Markdown to HTML for the subset the prompts produce: ##–#### headings, paragraphs, - / 1. lists,
 * > quotes, ---, **bold**, *italic*, `code`, and http(s) links. Everything else is escaped text.
 */
function mkt_markdown_html(string $markdown): string
{
    $out = [];
    $para = [];
    $list = null;
    $items = [];
    $flushPara = static function () use (&$para, &$out): void {
        if ($para !== []) {
            $out[] = '<p>' . mkt_md_inline(implode(' ', $para)) . '</p>';
            $para = [];
        }
    };
    $flushList = static function () use (&$list, &$items, &$out): void {
        if ($list !== null) {
            $out[] = "<{$list}>" . implode('', array_map(static fn(string $i): string => '<li>' . mkt_md_inline($i) . '</li>', $items)) . "</{$list}>";
            $list = null;
            $items = [];
        }
    };
    $rows = [];
    $flushTable = static function () use (&$rows, &$out): void {
        if ($rows === []) {
            return;
        }
        $split = static fn(string $row): array => array_map(
            static fn(string $c): string => str_replace('\|', '|', trim($c)),
            explode("\x1F", preg_replace('/(?<!\\\\)\|/', "\x1F", trim($row, '|')) ?? '')
        );
        $html = '<div class="admin-table-wrap"><table class="admin-table">';
        $header = count($rows) > 1 && preg_match('/^\|?\s*:?-{3,}/', $rows[1]) === 1;
        foreach ($rows as $i => $row) {
            if ($header && $i === 1) {
                continue;
            }
            $tag = $header && $i === 0 ? 'th' : 'td';
            $html .= '<tr>' . implode('', array_map(static fn(string $c): string => "<{$tag}>" . mkt_md_inline($c) . "</{$tag}>", $split($row))) . '</tr>';
        }
        $out[] = $html . '</table></div>';
        $rows = [];
    };
    foreach (preg_split('/\r?\n/', $markdown) as $line) {
        $trim = trim($line);
        if (str_starts_with($trim, '|')) {
            $flushPara();
            $flushList();
            $rows[] = $trim;
            continue;
        }
        $flushTable();
        if ($trim === '') {
            $flushPara();
            $flushList();
            continue;
        }
        if (preg_match('/^(#{1,4})\s+(.+)$/', $trim, $m)) {
            $flushPara();
            $flushList();
            $level = max(2, strlen($m[1]));
            $out[] = "<h{$level}>" . mkt_md_inline(rtrim($m[2], '# ')) . "</h{$level}>";
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})$/', $trim)) {
            $flushPara();
            $flushList();
            $out[] = '<hr>';
            continue;
        }
        $type = match (true) {
            (bool) preg_match('/^[-*+]\s+(.+)$/', $trim, $m) => 'ul',
            (bool) preg_match('/^\d+[.)]\s+(.+)$/', $trim, $m) => 'ol',
            default => null,
        };
        if ($type !== null) {
            $flushPara();
            if ($list !== $type) {
                $flushList();
                $list = $type;
            }
            $items[] = $m[1];
            continue;
        }
        if (str_starts_with($trim, '>')) {
            $flushPara();
            $flushList();
            $out[] = '<blockquote>' . mkt_md_inline(ltrim(substr($trim, 1))) . '</blockquote>';
            continue;
        }
        if ($list !== null && preg_match('/^\s{2,}/', $line)) {
            $items[count($items) - 1] .= ' ' . $trim;
            continue;
        }
        $flushList();
        $para[] = $trim;
    }
    $flushTable();
    $flushPara();
    $flushList();

    return implode("\n", $out);
}

/**
 * Paragraph-level diff (LCS over blank-line-separated blocks).
 *
 * @return list<array{op: 'same'|'add'|'del', text: string}>
 */
function mkt_content_diff(string $old, string $new): array
{
    $split = static fn(string $s): array => array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', str_replace("\r\n", "\n", $s))), static fn(string $p): bool => $p !== ''));
    $a = $split($old);
    $b = $split($new);
    $n = count($a);
    $m = count($b);
    $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
    for ($i = $n - 1; $i >= 0; $i--) {
        for ($j = $m - 1; $j >= 0; $j--) {
            $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
        }
    }
    $ops = [];
    $i = $j = 0;
    while ($i < $n && $j < $m) {
        if ($a[$i] === $b[$j]) {
            $ops[] = ['op' => 'same', 'text' => $a[$i]];
            $i++;
            $j++;
        } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            $ops[] = ['op' => 'del', 'text' => $a[$i++]];
        } else {
            $ops[] = ['op' => 'add', 'text' => $b[$j++]];
        }
    }
    while ($i < $n) {
        $ops[] = ['op' => 'del', 'text' => $a[$i++]];
    }
    while ($j < $m) {
        $ops[] = ['op' => 'add', 'text' => $b[$j++]];
    }

    return $ops;
}
