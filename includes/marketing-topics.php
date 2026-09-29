<?php

require_once __DIR__ . '/marketing-claims.php';

const MKT_TOPIC_STATUSES = [
    'proposed' => 'Proposed',
    'accepted' => 'Accepted',
    'parked'   => 'Parked',
    'rejected' => 'Rejected',
    'merged'   => 'Merged',
];
const MKT_ITEM_EVIDENCE_TYPES = [
    'peer_reviewed' => 'Peer-reviewed',
    'regulatory'    => 'Regulatory',
    'news'          => 'News',
    'competitor'    => 'Competitor',
    'opinion'       => 'Opinion / social',
];

/* ---------- Topics ---------- */

function mkt_topics_list(array $filters = [], int $limit = 200): array
{
    $limit = max(1, min(500, $limit));
    $where = [];
    $params = [];
    $statuses = array_values(array_filter((array) ($filters['statuses'] ?? []), static fn($s): bool => array_key_exists((string) $s, MKT_TOPIC_STATUSES)));
    if ($statuses !== []) {
        $in = [];
        foreach ($statuses as $i => $status) {
            $in[] = ':s' . $i;
            $params['s' . $i] = $status;
        }
        $where[] = 't.Status IN (' . implode(', ', $in) . ')';
    }
    if (!empty($filters['interest_id'])) {
        $where[] = 't.InterestID = :interest';
        $params['interest'] = (int) $filters['interest_id'];
    }
    if (($filters['area'] ?? '') !== '') {
        $where[] = 't.TherapeuticArea = :area';
        $params['area'] = (string) $filters['area'];
    }
    if (!empty($filters['emerging'])) {
        $where[] = 't.IsEmerging = 1';
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(t.Title LIKE :q1 OR t.Summary LIKE :q2 OR t.Angle LIKE :q3)';
        $like = '%' . $filters['q'] . '%';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    $order = ($filters['order'] ?? '') === 'recent' ? 't.UpdatedAt DESC' : 't.TrendScore DESC, t.LastItemAt DESC';

    $sql = "SELECT TOP ({$limit}) t.*, i.Name AS InterestName
            FROM dbo.MktTopic t
            LEFT JOIN dbo.MktInterest i ON i.InterestID = t.InterestID"
        . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
        . " ORDER BY {$order}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_topic_get(int $id): ?array
{
    $stmt = db()->prepare(<<<SQL
        SELECT t.*, i.Name AS InterestName, m.Title AS MergedIntoTitle
        FROM dbo.MktTopic t
        LEFT JOIN dbo.MktInterest i ON i.InterestID = t.InterestID
        LEFT JOIN dbo.MktTopic m ON m.TopicID = t.MergedIntoTopicID
        WHERE t.TopicID = :id
    SQL);
    $stmt->execute(['id' => $id]);
    $topic = $stmt->fetch();

    return $topic ?: null;
}

function mkt_topic_items(int $topicId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT ti.IsEvidence, h.ItemID, h.Title, h.Url, h.Domain, h.SourceType, h.EvidenceType, h.AiSummary,
               h.RelevanceMax, h.StudyJson, h.TagsJson, h.PublishedAt, h.FetchedAt, h.Status, s.Name AS SourceName
        FROM dbo.MktTopicItem ti
        INNER JOIN dbo.MktHarvestedItem h ON h.ItemID = ti.ItemID
        LEFT JOIN dbo.MktSource s ON s.SourceID = h.SourceID
        WHERE ti.TopicID = :id
        ORDER BY ti.IsEvidence DESC, COALESCE(h.PublishedAt, h.FetchedAt) DESC
    SQL);
    $stmt->execute(['id' => $topicId]);

    return $stmt->fetchAll();
}

function mkt_topic_claims(int $topicId): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT c.ClaimID, c.ClaimText, c.ClaimType, c.Status, c.EvidenceTier, p.Name AS ProductName
        FROM dbo.MktTopicClaim tc
        INNER JOIN dbo.MktClaim c ON c.ClaimID = tc.ClaimID
        LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
        WHERE tc.TopicID = :id
        ORDER BY p.Name, c.SortOrder, c.ClaimID
    SQL);
    $stmt->execute(['id' => $topicId]);

    return $stmt->fetchAll();
}

function mkt_topic_status_counts(): array
{
    $counts = array_fill_keys(array_keys(MKT_TOPIC_STATUSES), 0);
    foreach (db()->query('SELECT Status, COUNT(*) AS n FROM dbo.MktTopic GROUP BY Status')->fetchAll() as $row) {
        $counts[(string) $row['Status']] = (int) $row['n'];
    }
    $counts['emerging'] = (int) db()->query("SELECT COUNT(*) FROM dbo.MktTopic WHERE IsEmerging = 1 AND Status IN (N'proposed', N'parked')")->fetchColumn();

    return $counts;
}

/**
 * Pipeline snapshot for the board header: waiting to score, in an open batch, scored but not yet in a topic.
 */
function mkt_topic_pipeline_counts(): array
{
    $row = db()->query(<<<SQL
        SELECT
            SUM(CASE WHEN Status = N'new' AND ScoreBatchID IS NULL THEN 1 ELSE 0 END) AS Waiting,
            SUM(CASE WHEN Status = N'new' AND ScoreBatchID IS NOT NULL THEN 1 ELSE 0 END) AS InBatch,
            SUM(CASE WHEN Status = N'scored' THEN 1 ELSE 0 END) AS Scored,
            SUM(CASE WHEN Status = N'discarded' THEN 1 ELSE 0 END) AS Discarded,
            SUM(CASE WHEN Status = N'clustered' THEN 1 ELSE 0 END) AS InTopics
        FROM dbo.MktHarvestedItem
    SQL)->fetch();

    return array_map('intval', $row ?: []);
}

/**
 * Editable topic fields. Title and summary start as the AI draft; angle, audience and avoid notes are the human brief.
 */
function mkt_topic_save(int $id, array $input): array
{
    $before = mkt_topic_get($id);
    if ($before === null) {
        return ['ok' => false, 'error' => 'Topic not found.'];
    }
    if ($before['Status'] === 'merged') {
        return ['ok' => false, 'error' => 'Merged topics are read-only — edit the topic it was merged into.'];
    }
    $text = static fn(string $key, int $max): ?string => trim((string) ($input[$key] ?? '')) !== ''
        ? mb_substr(trim(str_replace("\r\n", "\n", (string) $input[$key])), 0, $max) : null;
    $interestId = (int) ($input['interest_id'] ?? 0) ?: null;
    $audience = (string) ($input['audience'] ?? '');
    $data = [
        'Title'           => $text('title', 300) ?? (string) $before['Title'],
        'Summary'         => $text('summary', 2000),
        'WhyItMatters'    => $text('why_it_matters', 1000),
        'TherapeuticArea' => in_array((string) ($input['therapeutic_area'] ?? ''), marketing_setting_lines('taxonomy.therapeutic_areas'), true) ? (string) $input['therapeutic_area'] : null,
        'InterestID'      => $interestId !== null && mkt_interest_get($interestId) !== null ? $interestId : null,
        'Angle'           => $text('angle', 2000),
        'Audience'        => array_key_exists($audience, MKT_CLAIM_AUDIENCES) ? $audience : null,
        'AvoidNotes'      => $text('avoid_notes', 1000),
        'UpdatedBy'       => marketing_user_id(),
    ];
    if ($before['Status'] === 'accepted' && $data['Angle'] === null) {
        return ['ok' => false, 'error' => 'Accepted topics need an angle.'];
    }
    $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
    db()->prepare("UPDATE dbo.MktTopic SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE TopicID = :id")->execute($data + ['id' => $id]);
    $built = audit_build_update('MktTopic', 'TopicID', $id, $data, array_intersect_key($before, $data));
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/**
 * Board decisions. Accepting requires an angle (the brief Campaign Studio writes from); any decided topic can be reopened.
 */
function mkt_topic_decide(int $id, string $decision, ?string $note = null): array
{
    $topic = mkt_topic_get($id);
    if ($topic === null) {
        return ['ok' => false, 'error' => 'Topic not found.'];
    }
    if (!in_array($decision, ['accepted', 'rejected', 'parked', 'proposed'], true)) {
        return ['ok' => false, 'error' => 'Unknown decision.'];
    }
    if ($topic['Status'] === 'merged') {
        return ['ok' => false, 'error' => 'Merged topics cannot change status.'];
    }
    if ($decision === 'accepted' && trim((string) ($topic['Angle'] ?? '')) === '') {
        return ['ok' => false, 'error' => 'Write the angle before accepting — it is the brief Campaign Studio works from.'];
    }
    $userId = marketing_user_id();
    $note = $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 500) : null;
    $data = [
        'Status'       => $decision,
        'DecisionNote' => $note ?? $topic['DecisionNote'],
        'DecidedBy'    => $decision === 'proposed' ? null : $userId,
    ];
    db()->prepare(<<<SQL
        UPDATE dbo.MktTopic
        SET Status = :Status, DecisionNote = :DecisionNote, DecidedBy = :DecidedBy,
            DecidedAt = CASE WHEN :DecidedBy2 IS NULL THEN NULL ELSE SYSUTCDATETIME() END,
            UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME()
        WHERE TopicID = :id
    SQL)->execute($data + ['DecidedBy2' => $data['DecidedBy'], 'u' => $userId, 'id' => $id]);
    $built = audit_build_update('MktTopic', 'TopicID', $id, $data, array_intersect_key($topic, $data));
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/**
 * Fold a duplicate topic into another: items and claim links move, the source is marked merged.
 */
function mkt_topic_merge(int $sourceId, int $targetId): array
{
    if ($sourceId === $targetId) {
        return ['ok' => false, 'error' => 'Choose a different topic to merge into.'];
    }
    $source = mkt_topic_get($sourceId);
    $target = mkt_topic_get($targetId);
    if ($source === null || $target === null) {
        return ['ok' => false, 'error' => 'Topic not found.'];
    }
    if ($source['Status'] === 'merged' || $target['Status'] === 'merged') {
        return ['ok' => false, 'error' => 'Merged topics cannot be merged again.'];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare(<<<SQL
            INSERT INTO dbo.MktTopicItem (TopicID, ItemID, IsEvidence)
            SELECT :target, s.ItemID, s.IsEvidence FROM dbo.MktTopicItem s
            WHERE s.TopicID = :source
              AND NOT EXISTS (SELECT 1 FROM dbo.MktTopicItem t WHERE t.TopicID = :target2 AND t.ItemID = s.ItemID)
        SQL)->execute(['target' => $targetId, 'source' => $sourceId, 'target2' => $targetId]);
        $pdo->prepare(<<<SQL
            INSERT INTO dbo.MktTopicClaim (TopicID, ClaimID, AddedBy)
            SELECT :target, s.ClaimID, s.AddedBy FROM dbo.MktTopicClaim s
            WHERE s.TopicID = :source
              AND NOT EXISTS (SELECT 1 FROM dbo.MktTopicClaim t WHERE t.TopicID = :target2 AND t.ClaimID = s.ClaimID)
        SQL)->execute(['target' => $targetId, 'source' => $sourceId, 'target2' => $targetId]);
        $pdo->prepare('DELETE FROM dbo.MktTopicItem WHERE TopicID = :id')->execute(['id' => $sourceId]);
        $pdo->prepare('DELETE FROM dbo.MktTopicClaim WHERE TopicID = :id')->execute(['id' => $sourceId]);
        $pdo->prepare(<<<SQL
            UPDATE dbo.MktTopic SET Status = N'merged', MergedIntoTopicID = :target, ItemCount = 0, Items7d = 0, ItemsPrior7d = 0,
                SourceDiversity = 0, EvidenceCount = 0, TrendScore = 0, DecidedBy = :u, DecidedAt = SYSUTCDATETIME(),
                UpdatedBy = :u2, UpdatedAt = SYSUTCDATETIME()
            WHERE TopicID = :id
        SQL)->execute(['target' => $targetId, 'u' => marketing_user_id(), 'u2' => marketing_user_id(), 'id' => $sourceId]);
        $pdo->prepare('UPDATE dbo.MktTopic SET UpdatedAt = SYSUTCDATETIME() WHERE TopicID = :id')->execute(['id' => $targetId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    mkt_topic_refresh_metrics([$targetId]);
    audit_log_change(
        "-- Merged MktTopic $sourceId into $targetId (items and claim links moved)\nUPDATE dbo.MktTopic SET Status = N'merged', MergedIntoTopicID = $targetId WHERE TopicID = $sourceId",
        "UPDATE dbo.MktTopic SET Status = " . audit_sql_literal((string) $source['Status']) . ", MergedIntoTopicID = NULL WHERE TopicID = $sourceId"
    );

    return ['ok' => true];
}

function mkt_topic_set_evidence(int $topicId, int $itemId, bool $isEvidence): bool
{
    $stmt = db()->prepare('UPDATE dbo.MktTopicItem SET IsEvidence = :e WHERE TopicID = :t AND ItemID = :i');
    $stmt->execute(['e' => $isEvidence ? 1 : 0, 't' => $topicId, 'i' => $itemId]);

    return $stmt->rowCount() > 0;
}

/**
 * Take an item out of a topic; it returns to the scored pool so the next clustering run can place it elsewhere.
 */
function mkt_topic_remove_item(int $topicId, int $itemId): bool
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM dbo.MktTopicItem WHERE TopicID = :t AND ItemID = :i');
    $stmt->execute(['t' => $topicId, 'i' => $itemId]);
    if ($stmt->rowCount() === 0) {
        return false;
    }
    $pdo->prepare(<<<SQL
        UPDATE dbo.MktHarvestedItem SET Status = N'scored'
        WHERE ItemID = :i AND Status = N'clustered' AND NOT EXISTS (SELECT 1 FROM dbo.MktTopicItem WHERE ItemID = :i2)
    SQL)->execute(['i' => $itemId, 'i2' => $itemId]);
    mkt_topic_refresh_metrics([$topicId]);

    return true;
}

function mkt_topic_link_claim(int $topicId, int $claimId): array
{
    $claim = mkt_claim_get($claimId);
    if ($claim === null || $claim['Status'] !== 'approved') {
        return ['ok' => false, 'error' => 'Only approved claims can be linked.'];
    }
    db()->prepare(<<<SQL
        IF NOT EXISTS (SELECT 1 FROM dbo.MktTopicClaim WHERE TopicID = :t AND ClaimID = :c)
            INSERT INTO dbo.MktTopicClaim (TopicID, ClaimID, AddedBy) VALUES (:t2, :c2, :u)
    SQL)->execute(['t' => $topicId, 'c' => $claimId, 't2' => $topicId, 'c2' => $claimId, 'u' => marketing_user_id()]);

    return ['ok' => true];
}

function mkt_topic_unlink_claim(int $topicId, int $claimId): void
{
    db()->prepare('DELETE FROM dbo.MktTopicClaim WHERE TopicID = :t AND ClaimID = :c')->execute(['t' => $topicId, 'c' => $claimId]);
}

/**
 * Recompute trend signals from topic items. Keep in step with refreshTopicMetrics() in
 * functions-marketing/src/lib/jobs/research-cluster.js.
 *
 * @param list<int> $topicIds
 */
function mkt_topic_refresh_metrics(array $topicIds): void
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $topicIds))));
    if ($ids === []) {
        return;
    }
    $list = implode(',', $ids);
    db()->exec(<<<SQL
        WITH stats AS (
            SELECT t.TopicID,
                   COUNT(h.ItemID) AS ItemCount,
                   SUM(CASE WHEN COALESCE(h.PublishedAt, h.FetchedAt) >= DATEADD(DAY, -7, SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS Items7d,
                   SUM(CASE WHEN COALESCE(h.PublishedAt, h.FetchedAt) >= DATEADD(DAY, -14, SYSUTCDATETIME())
                             AND COALESCE(h.PublishedAt, h.FetchedAt) < DATEADD(DAY, -7, SYSUTCDATETIME()) THEN 1 ELSE 0 END) AS ItemsPrior7d,
                   COUNT(DISTINCT h.Domain) AS SourceDiversity,
                   SUM(CASE WHEN h.EvidenceType IN (N'peer_reviewed', N'regulatory') THEN 1 ELSE 0 END) AS EvidenceCount,
                   MIN(COALESCE(h.PublishedAt, h.FetchedAt)) AS FirstItemAt,
                   MAX(COALESCE(h.PublishedAt, h.FetchedAt)) AS LastItemAt
            FROM dbo.MktTopic t
            LEFT JOIN dbo.MktTopicItem ti ON ti.TopicID = t.TopicID
            LEFT JOIN dbo.MktHarvestedItem h ON h.ItemID = ti.ItemID
            WHERE t.TopicID IN ($list)
            GROUP BY t.TopicID
        )
        UPDATE t
        SET ItemCount = s.ItemCount, Items7d = COALESCE(s.Items7d, 0), ItemsPrior7d = COALESCE(s.ItemsPrior7d, 0),
            SourceDiversity = s.SourceDiversity, EvidenceCount = COALESCE(s.EvidenceCount, 0),
            FirstItemAt = s.FirstItemAt, LastItemAt = s.LastItemAt,
            TrendScore = COALESCE(s.Items7d, 0) * 2
              + CASE WHEN COALESCE(s.Items7d, 0) > COALESCE(s.ItemsPrior7d, 0) THEN s.Items7d - COALESCE(s.ItemsPrior7d, 0) ELSE 0 END
              + s.SourceDiversity * 1.5 + COALESCE(s.EvidenceCount, 0)
        FROM dbo.MktTopic t
        INNER JOIN stats s ON s.TopicID = t.TopicID
    SQL);
}

/**
 * Suggested new interest (name + include terms) from an emerging topic, for prefilling the interest form.
 *
 * @return array{name: string, description: string, include: list<string>}|null
 */
function mkt_topic_interest_suggestion(int $topicId): ?array
{
    $topic = mkt_topic_get($topicId);
    if ($topic === null || empty($topic['IsEmerging'])) {
        return null;
    }
    $terms = json_decode((string) ($topic['SuggestedTermsJson'] ?? ''), true);

    return [
        'name'        => (string) ($topic['SuggestedInterestName'] ?: $topic['Title']),
        'description' => 'Suggested by Topic Synthesis from topic #' . $topicId . ': ' . $topic['Title'],
        'include'     => is_array($terms) ? array_values(array_filter(array_map('strval', $terms))) : [],
    ];
}

/* ---------- Scored items & batches ---------- */

function mkt_scored_items(array $filters = [], int $limit = 150): array
{
    $limit = max(1, min(500, $limit));
    $where = ['h.ScoredAt IS NOT NULL'];
    $params = [];
    if (($filters['status'] ?? '') !== '' && in_array($filters['status'], ['scored', 'discarded', 'clustered', 'promoted', 'rejected', 'ignored'], true)) {
        $where[] = 'h.Status = :status';
        $params['status'] = (string) $filters['status'];
    }
    if (!empty($filters['interest_id'])) {
        $where[] = 'EXISTS (SELECT 1 FROM dbo.MktItemScore sc WHERE sc.ItemID = h.ItemID AND sc.InterestID = :interest AND sc.Relevance >= :min_interest)';
        $params['interest'] = (int) $filters['interest_id'];
        $params['min_interest'] = (float) ($filters['min_relevance'] ?? 0.5);
    }
    if (($filters['evidence'] ?? '') !== '' && array_key_exists((string) $filters['evidence'], MKT_ITEM_EVIDENCE_TYPES)) {
        $where[] = 'h.EvidenceType = :evidence';
        $params['evidence'] = (string) $filters['evidence'];
    }
    if (($filters['area'] ?? '') !== '') {
        $where[] = 'h.TherapeuticArea = :area';
        $params['area'] = (string) $filters['area'];
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(h.Title LIKE :q1 OR h.AiSummary LIKE :q2 OR h.TagsJson LIKE :q3)';
        $like = '%' . $filters['q'] . '%';
        $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
    }
    $sql = "SELECT TOP ({$limit}) h.ItemID, h.Title, h.Url, h.Domain, h.SourceType, h.Status, h.RelevanceMax, h.EvidenceType,
                   h.TherapeuticArea, h.AiSummary, h.TagsJson, h.StudyJson, h.PublishedAt, h.ScoredAt,
                   i.Name AS PrimaryInterestName,
                   (SELECT TOP 1 ti.TopicID FROM dbo.MktTopicItem ti WHERE ti.ItemID = h.ItemID) AS TopicID
            FROM dbo.MktHarvestedItem h
            LEFT JOIN dbo.MktInterest i ON i.InterestID = h.PrimaryInterestID
            WHERE " . implode(' AND ', $where) . '
            ORDER BY h.ScoredAt DESC, h.RelevanceMax DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_ai_batches(int $limit = 50): array
{
    $limit = max(1, min(200, $limit));

    return db()->query("SELECT TOP ({$limit}) * FROM dbo.MktAiBatch ORDER BY BatchID DESC")->fetchAll();
}

/**
 * Decode an item's TagsJson into {areas, products, keywords}.
 */
function mkt_item_tags(?string $json): array
{
    $tags = json_decode((string) $json, true);

    return [
        'areas'    => is_array($tags['areas'] ?? null) ? $tags['areas'] : [],
        'products' => is_array($tags['products'] ?? null) ? $tags['products'] : [],
        'keywords' => is_array($tags['keywords'] ?? null) ? $tags['keywords'] : [],
    ];
}
