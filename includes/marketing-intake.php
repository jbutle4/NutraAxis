<?php

require_once __DIR__ . '/marketing.php';

const MKT_SOURCE_TYPES = [
    'rss'            => ['label' => 'RSS / Atom feed', 'field' => 'url', 'hint' => 'Feed URL (RSS 2.0, Atom, or RDF).'],
    'google_news'    => ['label' => 'Google News search', 'field' => 'query', 'hint' => 'Search query; last 7 days of Google News results.'],
    'pubmed'         => ['label' => 'PubMed search', 'field' => 'query', 'hint' => 'PubMed search term, e.g. berberine AND (glucose OR insulin).'],
    'clinicaltrials' => ['label' => 'ClinicalTrials.gov search', 'field' => 'query', 'hint' => 'Search term; studies updated within the lookback window.'],
    'crawl'          => ['label' => 'Site crawl (listing page)', 'field' => 'url', 'hint' => 'News/blog listing page. Respects robots.txt; fetches new article links only.'],
    'reddit'         => ['label' => 'Reddit (RSS)', 'field' => 'url', 'hint' => 'e.g. https://www.reddit.com/r/Supplements/.rss or a search .rss URL.'],
    'youtube'        => ['label' => 'YouTube channel (RSS)', 'field' => 'url', 'hint' => 'https://www.youtube.com/feeds/videos.xml?channel_id=…'],
];

const MKT_SCHEDULES = ['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly'];
const MKT_RECORD_STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'retired' => 'Retired'];
const MKT_SOURCE_STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'auto_paused' => 'Auto-paused (failing)'];
const MKT_TERM_TYPES = ['include' => 'Include terms', 'exclude' => 'Exclude terms', 'hashtag' => 'Hashtags', 'query' => 'Search queries'];
const MKT_KEYWORD_PURPOSES = ['seo' => 'SEO', 'interest' => 'Interest', 'both' => 'Both'];
const MKT_ITEM_STATUSES = [
    'new'       => 'New',
    'scored'    => 'Scored',
    'clustered' => 'Clustered',
    'promoted'  => 'Promoted',
    'discarded' => 'Discarded (low relevance)',
    'ignored'   => 'Ignored',
    'rejected'  => 'Rejected (failed verification)',
    'duplicate' => 'Duplicate',
];
const MKT_ITEM_SOURCE_TYPES = MKT_SOURCE_TYPES + [
    'manual'      => ['label' => 'Manual add'],
    'ai_research' => ['label' => 'AI research agent'],
];

function mkt_lines(string $text): array
{
    $lines = array_map('trim', preg_split('/\r?\n/', $text) ?: []);

    return array_values(array_unique(array_filter($lines, static fn(string $l): bool => $l !== '')));
}

/* ---------- Keywords ---------- */

function mkt_keyword_upsert(string $keyword, string $purpose, ?int $userId = null): int
{
    $keyword = mb_substr(trim($keyword), 0, 200);
    $pdo = db();
    $stmt = $pdo->prepare('SELECT KeywordID, Purpose FROM dbo.MktKeyword WHERE Keyword = :k');
    $stmt->execute(['k' => $keyword]);
    $row = $stmt->fetch();
    if ($row) {
        $current = (string) $row['Purpose'];
        if ($current !== $purpose && $current !== 'both') {
            $pdo->prepare("UPDATE dbo.MktKeyword SET Purpose = N'both', UpdatedAt = SYSUTCDATETIME(), UpdatedBy = :u WHERE KeywordID = :id")
                ->execute(['u' => $userId, 'id' => $row['KeywordID']]);
        }

        return (int) $row['KeywordID'];
    }

    $insert = $pdo->prepare('INSERT INTO dbo.MktKeyword (Keyword, Purpose, UpdatedBy) OUTPUT INSERTED.KeywordID AS inserted_id VALUES (:k, :p, :u)');
    $insert->execute(['k' => $keyword, 'p' => $purpose, 'u' => $userId]);

    return db_fetch_inserted_int($insert, 'inserted_id');
}

function mkt_keywords_list(array $filters = []): array
{
    $sql = 'SELECT k.*, (SELECT COUNT(*) FROM dbo.MktInterestTerm t WHERE t.KeywordID = k.KeywordID) AS InterestLinks FROM dbo.MktKeyword k WHERE 1 = 1';
    $params = [];
    if (!empty($filters['purpose'])) {
        $sql .= ' AND k.Purpose = :purpose';
        $params['purpose'] = $filters['purpose'];
    }
    if (!empty($filters['status'])) {
        $sql .= ' AND k.Status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['q'])) {
        [$like, $likeParams] = db_like_or(['k.Keyword', 'k.Cluster', 'k.Notes'], (string) $filters['q']);
        $sql .= ' AND ' . $like;
        $params += $likeParams;
    }
    $sql .= ' ORDER BY k.Priority DESC, k.Keyword';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_keyword_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktKeyword WHERE KeywordID = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

function mkt_keyword_normalize(array $input): array
{
    $int = static fn($v): ?int => ($v === null || trim((string) $v) === '') ? null : (int) $v;

    return [
        'Keyword'    => mb_substr(trim((string) ($input['keyword'] ?? '')), 0, 200),
        'Purpose'    => array_key_exists((string) ($input['purpose'] ?? ''), MKT_KEYWORD_PURPOSES) ? (string) $input['purpose'] : 'seo',
        'Priority'   => max(1, min(5, (int) ($input['priority'] ?? 3) ?: 3)),
        'Cluster'    => trim((string) ($input['cluster'] ?? '')) ?: null,
        'Intent'     => trim((string) ($input['intent'] ?? '')) ?: null,
        'Volume'     => $int($input['volume'] ?? null),
        'Difficulty' => $int($input['difficulty'] ?? null),
        'Notes'      => trim((string) ($input['notes'] ?? '')) ?: null,
        'Status'     => array_key_exists((string) ($input['status'] ?? ''), MKT_RECORD_STATUSES) ? (string) $input['status'] : 'active',
    ];
}

function mkt_keyword_save(array $input, ?int $id = null): array
{
    $data = mkt_keyword_normalize($input);
    if ($data['Keyword'] === '') {
        return ['ok' => false, 'error' => 'Keyword is required.'];
    }
    $pdo = db();
    $dup = $pdo->prepare('SELECT KeywordID FROM dbo.MktKeyword WHERE Keyword = :k AND (:id1 IS NULL OR KeywordID <> :id2)');
    $dup->execute(['k' => $data['Keyword'], 'id1' => $id, 'id2' => $id]);
    if ($dup->fetch()) {
        return ['ok' => false, 'error' => 'That keyword already exists.'];
    }

    $data['UpdatedBy'] = marketing_user_id();
    if ($id === null) {
        $cols = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO dbo.MktKeyword (' . implode(', ', $cols) . ') OUTPUT INSERTED.KeywordID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
        $stmt->execute($data);
        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $built = audit_build_insert('MktKeyword', $data, 'KeywordID', $id);
    } else {
        $before = mkt_keyword_get($id) ?? [];
        $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
        $stmt = $pdo->prepare("UPDATE dbo.MktKeyword SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE KeywordID = :id");
        $stmt->execute($data + ['id' => $id]);
        $built = audit_build_update('MktKeyword', 'KeywordID', $id, $data, array_intersect_key($before, $data));
    }
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'id' => $id];
}

/**
 * CSV columns (header row required): keyword, purpose, priority, cluster, intent, volume, difficulty, notes.
 */
function mkt_keywords_import_csv(string $path): array
{
    $handle = fopen($path, 'r');
    if ($handle === false) {
        return ['ok' => false, 'error' => 'Could not read the uploaded file.'];
    }
    $header = fgetcsv($handle, escape: '\\');
    if (!is_array($header)) {
        return ['ok' => false, 'error' => 'The CSV is empty.'];
    }
    $header = array_map(static fn($h): string => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
    if (!in_array('keyword', $header, true)) {
        return ['ok' => false, 'error' => 'The CSV needs a "keyword" header column.'];
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $pdo = db();
    $find = $pdo->prepare('SELECT KeywordID FROM dbo.MktKeyword WHERE Keyword = :k');
    while (($row = fgetcsv($handle, escape: '\\')) !== false) {
        $record = [];
        foreach ($header as $i => $col) {
            $record[$col] = $row[$i] ?? null;
        }
        $keyword = trim((string) ($record['keyword'] ?? ''));
        if ($keyword === '') {
            $skipped++;
            continue;
        }
        $find->execute(['k' => mb_substr($keyword, 0, 200)]);
        $existing = $find->fetchColumn();
        $result = mkt_keyword_save($record, $existing !== false ? (int) $existing : null);
        if (!$result['ok']) {
            $skipped++;
        } elseif ($existing !== false) {
            $updated++;
        } else {
            $created++;
        }
    }
    fclose($handle);

    return ['ok' => true, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped];
}

/* ---------- Interests ---------- */

function mkt_interests_list(?string $status = null): array
{
    $sql = <<<SQL
        SELECT i.*,
               (SELECT COUNT(*) FROM dbo.MktInterestTerm t WHERE t.InterestID = i.InterestID AND t.TermType = N'include') AS IncludeCount,
               (SELECT COUNT(*) FROM dbo.MktInterestSource s WHERE s.InterestID = i.InterestID) AS SourceCount,
               (SELECT COUNT(*) FROM dbo.MktHarvestedItem h
                  WHERE h.FetchedAt >= DATEADD(DAY, -7, SYSUTCDATETIME())
                    AND (h.InterestID = i.InterestID OR h.SourceID IN (SELECT s2.SourceID FROM dbo.MktInterestSource s2 WHERE s2.InterestID = i.InterestID))) AS Items7d
        FROM dbo.MktInterest i
    SQL;
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE i.Status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY i.Priority DESC, i.Name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_interest_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktInterest WHERE InterestID = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $terms = db()->prepare('SELECT TermType, Term FROM dbo.MktInterestTerm WHERE InterestID = :id ORDER BY TermID');
    $terms->execute(['id' => $id]);
    $row['terms'] = array_fill_keys(array_keys(MKT_TERM_TYPES), []);
    foreach ($terms->fetchAll() as $term) {
        $row['terms'][(string) $term['TermType']][] = (string) $term['Term'];
    }

    $sources = db()->prepare('SELECT SourceID FROM dbo.MktInterestSource WHERE InterestID = :id');
    $sources->execute(['id' => $id]);
    $row['source_ids'] = array_map('intval', $sources->fetchAll(PDO::FETCH_COLUMN));

    return $row;
}

function mkt_interest_save(array $input, ?int $id = null): array
{
    $data = [
        'Name'            => mb_substr(trim((string) ($input['name'] ?? '')), 0, 150),
        'Description'     => trim((string) ($input['description'] ?? '')) ?: null,
        'TherapeuticArea' => trim((string) ($input['therapeutic_area'] ?? '')) ?: null,
        'ProductLine'     => trim((string) ($input['product_line'] ?? '')) ?: null,
        'Audience'        => trim((string) ($input['audience'] ?? '')) ?: null,
        'Priority'        => max(1, min(5, (int) ($input['priority'] ?? 3) ?: 3)),
        'Status'          => array_key_exists((string) ($input['status'] ?? ''), MKT_RECORD_STATUSES) ? (string) $input['status'] : 'active',
        'AgentEnabled'    => !empty($input['agent_enabled']) ? 1 : 0,
    ];
    if ($data['Name'] === '') {
        return ['ok' => false, 'error' => 'Interest name is required.'];
    }
    $terms = [];
    foreach (array_keys(MKT_TERM_TYPES) as $type) {
        $terms[$type] = array_map(static fn(string $t): string => mb_substr($t, 0, 300), mkt_lines((string) ($input['terms_' . $type] ?? '')));
    }
    if ($terms['include'] === [] && $terms['query'] === []) {
        return ['ok' => false, 'error' => 'Add at least one include term or search query.'];
    }
    $sourceIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['source_ids'] ?? [])))));

    $pdo = db();
    $userId = marketing_user_id();
    $data['UpdatedBy'] = $userId;
    $before = $id !== null ? mkt_interest_get($id) : null;

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();
        if ($id === null) {
            $cols = array_keys($data);
            $stmt = $pdo->prepare('INSERT INTO dbo.MktInterest (' . implode(', ', $cols) . ') OUTPUT INSERTED.InterestID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
            $stmt->execute($data);
            $id = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
            $pdo->prepare("UPDATE dbo.MktInterest SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE InterestID = :id")->execute($data + ['id' => $id]);
            $pdo->prepare('DELETE FROM dbo.MktInterestTerm WHERE InterestID = :id')->execute(['id' => $id]);
            $pdo->prepare('DELETE FROM dbo.MktInterestSource WHERE InterestID = :id')->execute(['id' => $id]);
        }

        $termStmt = $pdo->prepare('INSERT INTO dbo.MktInterestTerm (InterestID, TermType, Term, KeywordID) VALUES (:i, :type, :term, :kw)');
        foreach ($terms as $type => $list) {
            foreach ($list as $term) {
                $keywordId = $type === 'include' ? mkt_keyword_upsert($term, 'interest', $userId) : null;
                $termStmt->execute(['i' => $id, 'type' => $type, 'term' => $term, 'kw' => $keywordId]);
            }
        }
        $linkStmt = $pdo->prepare('INSERT INTO dbo.MktInterestSource (InterestID, SourceID) SELECT :i, SourceID FROM dbo.MktSource WHERE SourceID = :s');
        foreach ($sourceIds as $sourceId) {
            $linkStmt->execute(['i' => $id, 's' => $sourceId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (str_contains($e->getMessage(), 'UQ_MktInterest_Name')) {
            return ['ok' => false, 'error' => 'An interest with that name already exists.'];
        }

        return ['ok' => false, 'error' => 'Could not save the interest: ' . $e->getMessage()];
    }

    $built = $before === null
        ? audit_build_insert('MktInterest', $data, 'InterestID', $id)
        : audit_build_update('MktInterest', 'InterestID', $id, $data, array_intersect_key($before, $data));
    audit_log_change(
        $built['change'] . "\n-- terms: " . json_encode($terms) . ' sources: ' . json_encode($sourceIds),
        $built['reverse']
    );

    return ['ok' => true, 'id' => $id];
}

/* ---------- Sources ---------- */

function mkt_sources_list(array $filters = []): array
{
    $sql = <<<SQL
        SELECT s.*,
               STUFF((SELECT N', ' + i.Name FROM dbo.MktInterestSource l JOIN dbo.MktInterest i ON i.InterestID = l.InterestID
                      WHERE l.SourceID = s.SourceID ORDER BY i.Name FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, N'') AS InterestNames,
               (SELECT COUNT(*) FROM dbo.MktHarvestedItem h WHERE h.SourceID = s.SourceID AND h.FetchedAt >= DATEADD(DAY, -7, SYSUTCDATETIME())) AS Items7d
        FROM dbo.MktSource s
        WHERE 1 = 1
    SQL;
    $params = [];
    if (!empty($filters['status'])) {
        $sql .= ' AND s.Status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['type'])) {
        $sql .= ' AND s.SourceType = :type';
        $params['type'] = $filters['type'];
    }
    $sql .= " ORDER BY CASE s.Status WHEN N'auto_paused' THEN 0 WHEN N'active' THEN 1 ELSE 2 END, s.Name";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_source_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktSource WHERE SourceID = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $links = db()->prepare('SELECT InterestID FROM dbo.MktInterestSource WHERE SourceID = :id');
    $links->execute(['id' => $id]);
    $row['interest_ids'] = array_map('intval', $links->fetchAll(PDO::FETCH_COLUMN));

    return $row;
}

function mkt_source_save(array $input, ?int $id = null): array
{
    $type = (string) ($input['source_type'] ?? '');
    if (!isset(MKT_SOURCE_TYPES[$type])) {
        return ['ok' => false, 'error' => 'Choose a source type.'];
    }
    $field = MKT_SOURCE_TYPES[$type]['field'];
    $url = trim((string) ($input['url'] ?? ''));
    $query = trim((string) ($input['query'] ?? ''));
    if ($field === 'url' && !filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'A valid URL is required for this source type.'];
    }
    if ($field === 'query' && $query === '') {
        return ['ok' => false, 'error' => 'A search query is required for this source type.'];
    }
    $config = [];
    if ($type === 'crawl') {
        $pattern = trim((string) ($input['link_pattern'] ?? ''));
        if ($pattern !== '' && @preg_match('~' . str_replace('~', '\~', $pattern) . '~', '') === false) {
            return ['ok' => false, 'error' => 'Link pattern is not a valid regular expression.'];
        }
        $config = array_filter([
            'link_pattern' => $pattern,
            'max_pages'    => max(1, min(25, (int) ($input['max_pages'] ?? 10) ?: 10)),
        ], static fn($v): bool => $v !== '' && $v !== null);
    }

    $data = [
        'Name'       => mb_substr(trim((string) ($input['name'] ?? '')), 0, 150),
        'SourceType' => $type,
        'Url'        => $field === 'url' ? mb_substr($url, 0, 2000) : null,
        'Query'      => $field === 'query' ? mb_substr($query, 0, 1000) : null,
        'ConfigJson' => $config !== [] ? json_encode($config, JSON_UNESCAPED_SLASHES) : null,
        'Schedule'   => array_key_exists((string) ($input['schedule'] ?? ''), MKT_SCHEDULES) ? (string) $input['schedule'] : 'daily',
        'Status'     => array_key_exists((string) ($input['status'] ?? ''), MKT_SOURCE_STATUSES) ? (string) $input['status'] : 'active',
        'TosNotes'   => trim((string) ($input['tos_notes'] ?? '')) ?: null,
        'UpdatedBy'  => marketing_user_id(),
    ];
    if ($data['Name'] === '') {
        return ['ok' => false, 'error' => 'Source name is required.'];
    }
    $interestIds = array_values(array_unique(array_filter(array_map('intval', (array) ($input['interest_ids'] ?? [])))));

    $pdo = db();
    $before = $id !== null ? mkt_source_get($id) : null;
    if ($id === null) {
        $cols = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO dbo.MktSource (' . implode(', ', $cols) . ') OUTPUT INSERTED.SourceID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
        $stmt->execute($data);
        $id = db_fetch_inserted_int($stmt, 'inserted_id');
    } else {
        $resetHealth = ($before['Status'] ?? '') !== 'active' && $data['Status'] === 'active' ? ', ConsecutiveFailures = 0, NextRunAt = NULL' : '';
        $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
        $pdo->prepare("UPDATE dbo.MktSource SET $sets, UpdatedAt = SYSUTCDATETIME() $resetHealth WHERE SourceID = :id")->execute($data + ['id' => $id]);
        $pdo->prepare('DELETE FROM dbo.MktInterestSource WHERE SourceID = :id')->execute(['id' => $id]);
    }
    $link = $pdo->prepare('INSERT INTO dbo.MktInterestSource (InterestID, SourceID) SELECT InterestID, :s FROM dbo.MktInterest WHERE InterestID = :i');
    foreach ($interestIds as $interestId) {
        $link->execute(['s' => $id, 'i' => $interestId]);
    }

    $built = $before === null
        ? audit_build_insert('MktSource', $data, 'SourceID', $id)
        : audit_build_update('MktSource', 'SourceID', $id, $data, array_intersect_key($before, $data));
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'id' => $id];
}

function mkt_source_set_status(int $id, string $status): bool
{
    if (!isset(MKT_SOURCE_STATUSES[$status])) {
        return false;
    }
    $before = mkt_source_get($id);
    if ($before === null) {
        return false;
    }
    $reset = $status === 'active' ? ', ConsecutiveFailures = 0, NextRunAt = NULL' : '';
    db()->prepare("UPDATE dbo.MktSource SET Status = :s, UpdatedAt = SYSUTCDATETIME(), UpdatedBy = :u $reset WHERE SourceID = :id")
        ->execute(['s' => $status, 'u' => marketing_user_id(), 'id' => $id]);
    $built = audit_build_update('MktSource', 'SourceID', $id, ['Status' => $status], ['Status' => $before['Status']]);
    audit_log_change($built['change'], $built['reverse']);

    return true;
}

function mkt_source_display_target(array $source): string
{
    return (string) (MKT_SOURCE_TYPES[(string) $source['SourceType']]['field'] ?? 'url') === 'query'
        ? (string) ($source['Query'] ?? '')
        : (string) ($source['Url'] ?? '');
}

/* ---------- Harvested items and runs ---------- */

function mkt_items_list(array $filters = [], int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $sql = "SELECT TOP ({$limit}) h.ItemID, h.SourceType, h.Url, h.Domain, h.Title, h.Summary, h.Author, h.PublishedAt, h.FetchedAt,
                   h.Status, h.VerificationNote, s.Name AS SourceName, i.Name AS InterestName
            FROM dbo.MktHarvestedItem h
            LEFT JOIN dbo.MktSource s ON s.SourceID = h.SourceID
            LEFT JOIN dbo.MktInterest i ON i.InterestID = h.InterestID
            WHERE 1 = 1";
    $params = [];
    $status = (string) ($filters['status'] ?? '');
    if ($status === 'open') {
        $sql .= " AND h.Status IN (N'new', N'scored', N'clustered', N'promoted')";
    } elseif ($status !== '' && isset(MKT_ITEM_STATUSES[$status])) {
        $sql .= ' AND h.Status = :status';
        $params['status'] = $status;
    }
    if (!empty($filters['source_id'])) {
        $sql .= ' AND h.SourceID = :source_id';
        $params['source_id'] = (int) $filters['source_id'];
    }
    if (!empty($filters['source_type'])) {
        $sql .= ' AND h.SourceType = :source_type';
        $params['source_type'] = (string) $filters['source_type'];
    }
    if (!empty($filters['interest_id'])) {
        $sql .= ' AND (h.InterestID = :interest_id1 OR h.SourceID IN (SELECT SourceID FROM dbo.MktInterestSource WHERE InterestID = :interest_id2))';
        $params['interest_id1'] = (int) $filters['interest_id'];
        $params['interest_id2'] = (int) $filters['interest_id'];
    }
    if (!empty($filters['q'])) {
        [$like, $likeParams] = db_like_or(['h.Title', 'h.Summary', 'h.Domain'], (string) $filters['q']);
        $sql .= ' AND ' . $like;
        $params += $likeParams;
    }
    $sql .= ' ORDER BY h.FetchedAt DESC, h.ItemID DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_item_status_counts(): array
{
    $rows = db()->query('SELECT Status, COUNT(*) AS N FROM dbo.MktHarvestedItem GROUP BY Status')->fetchAll();
    $counts = [];
    foreach ($rows as $row) {
        $counts[(string) $row['Status']] = (int) $row['N'];
    }

    return $counts;
}

function mkt_item_set_status(int $itemId, string $status): bool
{
    if (!in_array($status, ['ignored', 'new'], true)) {
        return false;
    }
    $stmt = db()->prepare('SELECT Status FROM dbo.MktHarvestedItem WHERE ItemID = :id');
    $stmt->execute(['id' => $itemId]);
    $old = $stmt->fetchColumn();
    if ($old === false) {
        return false;
    }
    db()->prepare('UPDATE dbo.MktHarvestedItem SET Status = :s WHERE ItemID = :id')->execute(['s' => $status, 'id' => $itemId]);
    $built = audit_build_update('MktHarvestedItem', 'ItemID', $itemId, ['Status' => $status], ['Status' => $old]);
    audit_log_change($built['change'], $built['reverse']);

    return true;
}

function mkt_canonical_url(string $url): string
{
    $parts = parse_url(trim($url));
    if (!is_array($parts) || empty($parts['host'])) {
        return trim($url);
    }
    $host = preg_replace('/^www\./', '', strtolower($parts['host']));
    $query = [];
    parse_str($parts['query'] ?? '', $query);
    foreach (array_keys($query) as $key) {
        if (preg_match('/^(utm_[a-z]+|fbclid|gclid|mc_cid|mc_eid|ref|ref_src|igshid|cmpid|ncid)$/i', (string) $key)) {
            unset($query[$key]);
        }
    }
    ksort($query);
    $path = $parts['path'] ?? '/';
    if ($path !== '/' && str_ends_with($path, '/') && $query === []) {
        $path = rtrim($path, '/');
    }
    $canonical = ($parts['scheme'] ?? 'https') . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '') . $path;

    return $query !== [] ? $canonical . '?' . http_build_query($query) : $canonical;
}

function mkt_fetch_page_meta(string $url): array
{
    if (!function_exists('curl_init')) {
        return [];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => (string) marketing_setting('harvest.user_agent', 'NutraAxisResearchBot/1.0'),
    ]);
    $html = curl_exec($ch);
    curl_close($ch);
    if (!is_string($html) || $html === '') {
        return [];
    }
    $meta = static function (string $name) use ($html): ?string {
        $n = preg_quote($name, '/');
        if (preg_match('/<meta[^>]+(?:name|property)=["\']' . $n . '["\'][^>]*content=["\']([^"\']*)["\']/i', $html, $m)
            || preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:name|property)=["\']' . $n . '["\']/i', $html, $m)) {
            return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5);
        }

        return null;
    };
    $title = $meta('og:title');
    if ($title === null && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
        $title = html_entity_decode(trim(preg_replace('/\s+/', ' ', $m[1])), ENT_QUOTES | ENT_HTML5);
    }

    return array_filter(['title' => $title, 'summary' => $meta('description') ?? $meta('og:description')]);
}

function mkt_item_add_manual(array $input): array
{
    $url = trim((string) ($input['url'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'A valid URL is required.'];
    }
    $title = trim((string) ($input['title'] ?? ''));
    $summary = trim((string) ($input['summary'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    if ($title === '' || $summary === '') {
        $meta = mkt_fetch_page_meta($url);
        $title = $title !== '' ? $title : (string) ($meta['title'] ?? '');
        $summary = $summary !== '' ? $summary : (string) ($meta['summary'] ?? '');
    }
    if ($title === '') {
        return ['ok' => false, 'error' => 'Could not read a title from that page — enter one manually.'];
    }

    $canonical = mkt_canonical_url($url);
    $hashHex = hash('sha256', $canonical);
    $pdo = db();
    $exists = $pdo->prepare('SELECT ItemID FROM dbo.MktHarvestedItem WHERE UrlHash = CONVERT(BINARY(32), :h, 2)');
    $exists->execute(['h' => $hashHex]);
    if ($exists->fetchColumn() !== false) {
        return ['ok' => false, 'error' => 'That URL is already in the queue.'];
    }

    $interestId = (int) ($input['interest_id'] ?? 0) ?: null;
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.MktHarvestedItem (SourceType, Url, CanonicalUrl, UrlHash, Domain, Title, Summary, BodyText, InterestID, AddedByUserID)
        OUTPUT INSERTED.ItemID AS inserted_id
        VALUES (N'manual', :url, :canonical, CONVERT(BINARY(32), :hash, 2), :domain, :title, :summary, :body, :interest, :user)
    SQL);
    $stmt->bindValue('url', mb_substr($url, 0, 2000));
    $stmt->bindValue('canonical', mb_substr($canonical, 0, 2000));
    $stmt->bindValue('hash', $hashHex);
    $stmt->bindValue('domain', preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST))));
    $stmt->bindValue('title', mb_substr($title, 0, 500));
    $stmt->bindValue('summary', $summary !== '' ? mb_substr($summary, 0, 4000) : null);
    $stmt->bindValue('body', $body !== '' ? $body : null);
    $stmt->bindValue('interest', $interestId, $interestId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue('user', marketing_user_id(), PDO::PARAM_INT);
    $stmt->execute();
    $id = db_fetch_inserted_int($stmt, 'inserted_id');
    audit_log_change(
        'INSERT INTO dbo.MktHarvestedItem (SourceType, Url, Title) VALUES (N\'manual\', ' . audit_sql_literal($url) . ', ' . audit_sql_literal($title) . ')',
        'DELETE FROM dbo.MktHarvestedItem WHERE ItemID = ' . $id
    );

    return ['ok' => true, 'id' => $id];
}

function mkt_harvest_runs(int $limit = 100, ?int $sourceId = null): array
{
    $limit = max(1, min(500, $limit));
    $sql = "SELECT TOP ({$limit}) r.*, s.Name AS SourceName, i.Name AS InterestName
            FROM dbo.MktHarvestRun r
            LEFT JOIN dbo.MktSource s ON s.SourceID = r.SourceID
            LEFT JOIN dbo.MktInterest i ON i.InterestID = r.InterestID";
    $params = [];
    if ($sourceId !== null) {
        $sql .= ' WHERE r.SourceID = :source_id';
        $params['source_id'] = $sourceId;
    }
    $sql .= ' ORDER BY r.HarvestRunID DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Domains the AI research agent keeps citing that are not yet a registered source.
 */
function mkt_suggested_source_domains(int $minItems = 2): array
{
    $stmt = db()->prepare(<<<SQL
        SELECT h.Domain, COUNT(*) AS Items, MAX(h.FetchedAt) AS LastSeen,
               MAX(h.Url) AS SampleUrl
        FROM dbo.MktHarvestedItem h
        WHERE h.SourceType = N'ai_research' AND h.Status <> N'rejected' AND h.Domain IS NOT NULL
          AND NOT EXISTS (
              SELECT 1 FROM dbo.MktSource s
              WHERE s.Url LIKE N'%' + h.Domain + N'%'
          )
        GROUP BY h.Domain
        HAVING COUNT(*) >= :min
        ORDER BY COUNT(*) DESC
    SQL);
    $stmt->execute(['min' => $minItems]);

    return $stmt->fetchAll();
}
