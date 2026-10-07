<?php

require_once __DIR__ . '/marketing-topics.php';
require_once __DIR__ . '/marketing-ranks.php';

/**
 * Literature & Intelligence — the evidence library behind claims and content. Every library source is a research
 * item (from the feed, or looked up by PMID/DOI/NCT by the literature-add-source job). Product flyer reference lists
 * are split into numbered rows and matched to PubMed papers by the literature-flyer-match job; a person confirms each
 * match. Regulatory items from the feed get a review status; "Needs action" opens a Compliance task.
 */

const MKT_LIT_LEVELS = [
    'meta'        => 'Meta-analysis / systematic review',
    'rct'         => 'Randomised trial',
    'clinical'    => 'Other human study',
    'registered'  => 'Registered trial, no results yet',
    'preclinical' => 'Animal or lab study',
    'review'      => 'Narrative review',
    'other'       => 'Other source',
];
const MKT_LIT_LEVEL_RANK = ['meta' => 1, 'rct' => 2, 'clinical' => 3, 'registered' => 4, 'preclinical' => 5, 'review' => 6, 'other' => 7];

/** Levels that can back a claim of each Claims Matrix evidence tier. */
const MKT_LIT_TIER_LEVELS = [
    'clinical'    => ['meta', 'rct', 'clinical'],
    'mechanistic' => ['meta', 'rct', 'clinical', 'preclinical', 'review'],
];

const MKT_LIT_MATCH_STATUSES = [
    'unmatched'  => 'Not checked yet',
    'candidates' => 'Confirm a match',
    'no_match'   => 'No match found',
    'matched'    => 'Matched',
    'other'      => 'Not a journal article',
];
const MKT_LIT_REG_STATUSES = [
    'new'      => 'New',
    'reviewed' => 'Reviewed — no action',
    'action'   => 'Action needed',
    'done'     => 'Action done',
];
const MKT_LIT_COVERAGE = [
    'none'     => 'No references',
    'no_study' => 'No study cited',
    'partial'  => 'Some not matched yet',
    'matched'  => 'All studies matched',
    'label'    => 'Label claim — no study needed',
];

/* ---------- Pure helpers ---------- */

function mkt_lit_level(?string $design, string $sourceType = '', bool $hasResult = true): string
{
    $d = strtolower((string) $design);
    if ($sourceType === 'clinicaltrials' && !$hasResult) {
        return 'registered';
    }
    if ($d === '') {
        return 'other';
    }
    if (preg_match('/meta-analy|systematic review/', $d) && !preg_match('/animal stud/', $d)) {
        return 'meta';
    }
    if (preg_match('/randomi[sz]|placebo-controlled|\brct\b|sham-controlled/', $d)) {
        return 'rct';
    }
    if (preg_match('/narrative|literature review|\breview\b|bibliometric/', $d)) {
        return 'review';
    }
    if (preg_match('/\banimal|rodent|\bmice\b|\bmouse\b|murine|\brats?\b|in vitro|cell line|cell[- ]culture|preclinical/', $d)
        && !preg_match('/patient|participant|cohort|human/', $d)) {
        return 'preclinical';
    }
    if (preg_match('/cohort|case-control|cross-sectional|observational|trial|pilot|comparative|case report|proteomic|clinical|experimental/', $d)) {
        return 'clinical';
    }

    return 'other';
}

function mkt_lit_level_badge(?string $level): string
{
    if ($level === null) {
        return '<span class="form-hint">—</span>';
    }
    $class = match ($level) {
        'meta', 'rct' => 'status-approved',
        'clinical'    => 'status-submitted',
        default       => 'status-draft',
    };

    return '<span class="status-badge ' . $class . '">' . htmlspecialchars(MKT_LIT_LEVELS[$level] ?? 'Other source') . '</span>';
}

function mkt_lit_study(?string $json): array
{
    $study = json_decode((string) $json, true);

    return is_array($study) ? $study : [];
}

/** Level of a library source or research item row (LevelOverride, else worked out from the study design). */
function mkt_lit_row_level(array $row): string
{
    if (!empty($row['LevelOverride']) && isset(MKT_LIT_LEVELS[$row['LevelOverride']])) {
        return (string) $row['LevelOverride'];
    }
    $study = mkt_lit_study($row['EffectiveStudyJson'] ?? $row['StudyJson'] ?? null);

    return mkt_lit_level($study['design'] ?? null, (string) ($row['SourceType'] ?? ''), !empty($study['result']));
}

/** A UTC timestamp as its Central-time calendar day, e.g. "Sep 29, 2026". */
function mkt_lit_day(?string $utc): string
{
    $formatted = marketing_format_datetime($utc);

    return preg_replace('/ \d{1,2}:\d{2} [AP]M$/', '', $formatted) ?? $formatted;
}

function mkt_lit_best_level(array $levels): ?string
{
    $levels = array_values(array_filter($levels));
    if ($levels === []) {
        return null;
    }
    usort($levels, static fn(string $a, string $b): int => MKT_LIT_LEVEL_RANK[$a] <=> MKT_LIT_LEVEL_RANK[$b]);

    return $levels[0];
}

/**
 * A product's flyer reference list as numbered entries. The stored lists sometimes split "Journal. 2020" and
 * "12(6):1640" onto separate numbered lines; those continuation lines are joined back onto the entry before them.
 *
 * @return list<string>
 */
function mkt_lit_flyer_refs(?string $list): array
{
    $refs = [];
    foreach (preg_split('/\R/', trim((string) $list)) ?: [] as $line) {
        $text = trim((string) preg_replace('/^\d+[.)]\s*/', '', trim($line)));
        if ($text === '') {
            continue;
        }
        if ($refs !== [] && preg_match('/^(\d+(\s*\([^)]*\))?(\s+Suppl\s*\d*)?:[\w\-–]+|\d{4}:\d+)\.?$/u', $text)) {
            $refs[count($refs) - 1] .= ';' . $text;
            continue;
        }
        $refs[] = $text;
    }

    return $refs;
}

/** Whether a flyer reference looks like a journal article (vs a database entry, label, or internal report). */
function mkt_lit_ref_is_study(string $ref): bool
{
    if (preg_match('/pubchem|internal report|monograph|label|duplicate|database|compound summary/i', $ref)) {
        return false;
    }

    return (bool) preg_match('/\.\s*(19|20)\d{2}\b/', $ref);
}

/** "1-3, 7-8" → [1, 2, 3, 7, 8]. */
function mkt_lit_ref_numbers(?string $text): array
{
    $numbers = [];
    foreach (preg_split('/[,;]\s*/', trim((string) $text)) ?: [] as $part) {
        $part = trim($part);
        if (preg_match('/^(\d+)\s*[-–]\s*(\d+)$/u', $part, $m) && (int) $m[2] >= (int) $m[1] && (int) $m[2] - (int) $m[1] < 100) {
            array_push($numbers, ...range((int) $m[1], (int) $m[2]));
        } elseif ($part !== '' && ctype_digit($part)) {
            $numbers[] = (int) $part;
        }
    }
    $numbers = array_values(array_unique($numbers));
    sort($numbers);

    return $numbers;
}

/** Short AMA-style citation: first three authors, title, journal, year, DOI (or the trial number). */
function mkt_lit_citation(array $row): string
{
    $meta = json_decode((string) ($row['MetadataJson'] ?? ''), true) ?: [];
    $authors = array_values(array_filter(array_map('trim', explode(',', (string) ($row['Author'] ?? '')))));
    $byline = $authors === [] ? '' : implode(', ', array_slice($authors, 0, 3)) . (count($authors) > 3 ? ', et al' : '') . '. ';
    $year = !empty($row['PublishedAt']) ? substr((string) $row['PublishedAt'], 0, 4) : '';
    $title = rtrim(trim((string) ($row['Title'] ?? '')), '.');
    $journal = trim((string) ($meta['journal'] ?? ''));
    $id = !empty($meta['doi']) ? ' doi:' . $meta['doi'] : (!empty($meta['nct_id']) ? ' ClinicalTrials.gov ' . $meta['nct_id'] . '.' : '');

    return $byline . $title . '.' . ($journal !== '' ? ' ' . $journal . '.' : '') . ($year !== '' ? ' ' . $year . '.' : '') . $id;
}

function mkt_lit_meta(array $row): array
{
    $meta = json_decode((string) ($row['MetadataJson'] ?? ''), true);

    return is_array($meta) ? $meta : [];
}

/** Whether a source at this level can back a claim of this tier (label and traditional claims accept any source). */
function mkt_lit_level_fits_tier(string $level, string $tier): bool
{
    return !isset(MKT_LIT_TIER_LEVELS[$tier]) || in_array($level, MKT_LIT_TIER_LEVELS[$tier], true);
}

/* ---------- Library ---------- */

const MKT_LIT_ITEM_COLS = 'h.ItemID, h.SourceType, h.Url, h.Domain, h.Title, h.Author, CONVERT(varchar(10), h.PublishedAt, 23) AS PublishedAt,
    h.MetadataJson, h.TherapeuticArea, h.EvidenceType, h.AiSummary, h.TagsJson, h.RelevanceMax, h.Status AS ItemStatus';

/** Library sources with their claim and topic counts. Level filtering happens here because levels are derived. */
function mkt_lit_sources(array $filters = []): array
{
    $where = [];
    $params = [];
    $where[] = ($filters['status'] ?? '') === 'retired' ? "s.Status = N'retired'" : "s.Status = N'active'";
    if (($filters['product'] ?? '') !== '') {
        $where[] = 'h.TagsJson LIKE :product';
        $params['product'] = '%"' . str_replace(['%', '_', '['], ['[%]', '[_]', '[[]'], (string) $filters['product']) . '"%';
    }
    if (($filters['area'] ?? '') !== '') {
        $where[] = 'h.TherapeuticArea = :area';
        $params['area'] = (string) $filters['area'];
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(h.Title LIKE :q1 OR h.MetadataJson LIKE :q2 OR h.TagsJson LIKE :q3 OR s.Takeaway LIKE :q4)';
        foreach (['q1', 'q2', 'q3', 'q4'] as $k) {
            $params[$k] = '%' . (string) $filters['q'] . '%';
        }
    }
    $cols = MKT_LIT_ITEM_COLS;
    $stmt = db()->prepare("
        SELECT s.SourceID, s.AddedFrom, s.LevelOverride, s.Takeaway, s.Status, CONVERT(varchar(19), s.CreatedAt, 120) AS AddedAt,
               COALESCE(s.StudyJson, h.StudyJson) AS EffectiveStudyJson, $cols,
               (SELECT COUNT(*) FROM dbo.MktTopicItem ti WHERE ti.ItemID = s.ItemID) AS TopicCount,
               (SELECT STRING_AGG(p.Name + ' #' + CAST(f.RefNumber AS varchar(10)), ', ') FROM dbo.MktLitFlyerRef f JOIN dbo.MktProduct p ON p.ProductID = f.ProductID
                WHERE f.SourceID = s.SourceID AND f.MatchStatus = N'matched') AS FlyerRefs
        FROM dbo.MktLitSource s
        JOIN dbo.MktHarvestedItem h ON h.ItemID = s.ItemID
        WHERE " . implode(' AND ', $where) . '
        ORDER BY s.CreatedAt DESC, s.SourceID DESC');
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['Level'] = mkt_lit_row_level($row);
        if (($filters['level'] ?? '') !== '' && $row['Level'] !== $filters['level']) {
            continue;
        }
        $rows[] = $row;
    }

    return mkt_lit_fill_flyer_claim_counts($rows);
}

/** ClaimCount: claims linked directly plus claims citing a flyer reference matched to the source. */
function mkt_lit_fill_flyer_claim_counts(array $rows): array
{
    if ($rows === []) {
        return $rows;
    }
    $ids = array_map('intval', array_column($rows, 'SourceID'));
    $in = implode(',', $ids);
    $direct = [];
    foreach (db()->query("SELECT SourceID, ClaimID FROM dbo.MktLitClaimSource WHERE SourceID IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $direct[(int) $r['SourceID']][(int) $r['ClaimID']] = true;
    }
    foreach (mkt_lit_flyer_claims_by_source($ids) as $sourceId => $claimIds) {
        foreach ($claimIds as $claimId) {
            $direct[$sourceId][$claimId] = true;
        }
    }
    foreach ($rows as &$row) {
        $row['ClaimCount'] = count($direct[(int) $row['SourceID']] ?? []);
    }

    return $rows;
}

/** @return array<int, list<int>> source ID → approved claim IDs citing a flyer reference matched to it */
function mkt_lit_flyer_claims_by_source(array $sourceIds): array
{
    if ($sourceIds === []) {
        return [];
    }
    $in = implode(',', array_map('intval', $sourceIds));
    $refs = db()->query("SELECT SourceID, ProductID, RefNumber FROM dbo.MktLitFlyerRef WHERE MatchStatus = N'matched' AND SourceID IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
    if ($refs === []) {
        return [];
    }
    $products = implode(',', array_unique(array_map('intval', array_column($refs, 'ProductID'))));
    $claims = db()->query("SELECT ClaimID, ProductID, ReferenceNumbers FROM dbo.MktClaim WHERE Status = N'approved' AND ProductID IN ($products) AND ReferenceNumbers IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($refs as $ref) {
        foreach ($claims as $claim) {
            if ((int) $claim['ProductID'] === (int) $ref['ProductID'] && in_array((int) $ref['RefNumber'], mkt_lit_ref_numbers($claim['ReferenceNumbers']), true)) {
                $out[(int) $ref['SourceID']][] = (int) $claim['ClaimID'];
            }
        }
    }

    return $out;
}

function mkt_lit_source_get(int $sourceId): ?array
{
    $cols = MKT_LIT_ITEM_COLS;
    $stmt = db()->prepare("
        SELECT s.*, COALESCE(s.StudyJson, h.StudyJson) AS EffectiveStudyJson, h.StudyJson AS ItemStudyJson, $cols,
               cu.UserName AS CreatedByName, uu.UserName AS UpdatedByName
        FROM dbo.MktLitSource s
        JOIN dbo.MktHarvestedItem h ON h.ItemID = s.ItemID
        LEFT JOIN dbo.[User] cu ON cu.UserID = s.CreatedBy
        LEFT JOIN dbo.[User] uu ON uu.UserID = s.UpdatedBy
        WHERE s.SourceID = :id");
    $stmt->execute(['id' => $sourceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    $row['Level'] = mkt_lit_row_level($row);

    return $row;
}

function mkt_lit_source_id_for_item(int $itemId): ?int
{
    $stmt = db()->prepare('SELECT SourceID FROM dbo.MktLitSource WHERE ItemID = :id');
    $stmt->execute(['id' => $itemId]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}

/** Promote a research item to the library (or bring a retired one back). */
function mkt_lit_add_from_feed(int $itemId): array
{
    $stmt = db()->prepare('SELECT ItemID, Title, EvidenceType FROM dbo.MktHarvestedItem WHERE ItemID = :id');
    $stmt->execute(['id' => $itemId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return ['ok' => false, 'error' => 'Research item not found.'];
    }
    $existing = mkt_lit_source_id_for_item($itemId);
    if ($existing !== null) {
        db()->prepare("UPDATE dbo.MktLitSource SET Status = N'active', UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE SourceID = :id")
            ->execute(['u' => marketing_user_id(), 'id' => $existing]);

        return ['ok' => true, 'id' => $existing, 'message' => 'Already in the library.'];
    }
    $stmt = db()->prepare("INSERT INTO dbo.MktLitSource (ItemID, AddedFrom, CreatedBy, UpdatedBy) OUTPUT INSERTED.SourceID AS inserted_id VALUES (:item, N'feed', :u, :u2)");
    $stmt->execute(['item' => $itemId, 'u' => marketing_user_id(), 'u2' => marketing_user_id()]);
    $sourceId = db_fetch_inserted_int($stmt, 'inserted_id');
    db()->prepare('DELETE FROM dbo.MktLitDismissed WHERE ItemID = :id')->execute(['id' => $itemId]);

    return ['ok' => true, 'id' => $sourceId, 'message' => 'Added to the library: ' . mb_strimwidth((string) $item['Title'], 0, 90, '…')];
}

/** Save notes, level and corrected study facts. Study facts are stored only when they differ from the item's. */
function mkt_lit_source_update(int $sourceId, array $input): array
{
    $source = mkt_lit_source_get($sourceId);
    if ($source === null) {
        return ['ok' => false, 'error' => 'Library source not found.'];
    }
    $level = trim((string) ($input['level'] ?? ($source['LevelOverride'] ?? '')));
    if ($level !== '' && !isset(MKT_LIT_LEVELS[$level])) {
        return ['ok' => false, 'error' => 'Choose an evidence level from the list.'];
    }
    $computed = mkt_lit_row_level(['StudyJson' => $source['ItemStudyJson'], 'SourceType' => $source['SourceType']]);
    $original = mkt_lit_study($source['ItemStudyJson']);
    $study = mkt_lit_study($source['EffectiveStudyJson']);
    foreach (['design', 'population', 'intervention', 'dose', 'duration', 'outcomes', 'result'] as $key) {
        if (array_key_exists($key, $input)) {
            $value = trim((string) $input[$key]);
            $study[$key] = $value === '' ? null : mb_substr($value, 0, 2000);
        }
    }
    if (array_key_exists('n', $input)) {
        $n = trim((string) $input['n']);
        if ($n !== '' && (!ctype_digit($n) || (int) $n > 10000000)) {
            return ['ok' => false, 'error' => 'Participants must be a whole number.'];
        }
        $study['n'] = $n === '' ? null : (int) $n;
    }
    $normalize = static fn(array $s): array => array_map(static fn($v) => $v === '' ? null : $v, $s);
    $studyJson = $normalize($study) == $normalize($original) ? null : json_encode($study, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    db()->prepare('UPDATE dbo.MktLitSource SET Takeaway = :t, Limitations = :l, LevelOverride = :lv, StudyJson = :s, UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE SourceID = :id')
        ->execute([
            't'  => ($t = trim((string) ($input['takeaway'] ?? $source['Takeaway'] ?? ''))) === '' ? null : mb_substr($t, 0, 1000),
            'l'  => ($l = trim((string) ($input['limitations'] ?? $source['Limitations'] ?? ''))) === '' ? null : mb_substr($l, 0, 1000),
            'lv' => $level === '' || $level === $computed ? null : $level,
            's'  => $studyJson,
            'u'  => marketing_user_id(),
            'id' => $sourceId,
        ]);

    return ['ok' => true];
}

function mkt_lit_source_set_status(int $sourceId, string $status): bool
{
    if (!in_array($status, ['active', 'retired'], true)) {
        return false;
    }
    $stmt = db()->prepare('UPDATE dbo.MktLitSource SET Status = :s, UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE SourceID = :id');
    $stmt->execute(['s' => $status, 'u' => marketing_user_id(), 'id' => $sourceId]);

    return $stmt->rowCount() > 0;
}

/** Library sources for the citations export, one row each. */
function mkt_lit_export_csv(array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="literature-library-' . mkt_local_date() . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Citation', 'Evidence level', 'PMID', 'DOI', 'Trial', 'Products', 'Therapeutic area', 'Takeaway', 'Claims backed', 'Flyer references', 'Added'], ',', '"', '');
    foreach ($rows as $row) {
        $meta = mkt_lit_meta($row);
        $tags = mkt_item_tags($row['TagsJson'] ?? null);
        fputcsv($out, [
            mkt_lit_citation($row), MKT_LIT_LEVELS[$row['Level']] ?? '', $meta['pmid'] ?? '', $meta['doi'] ?? '', $meta['nct_id'] ?? '',
            implode(', ', $tags['products']), (string) ($row['TherapeuticArea'] ?? ''), (string) ($row['Takeaway'] ?? ''),
            (int) $row['ClaimCount'], (string) ($row['FlyerRefs'] ?? ''), (new DateTimeImmutable((string) $row['AddedAt'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Chicago'))->format('Y-m-d'),
        ], ',', '"', '');
    }
    fclose($out);
    exit;
}

/* ---------- Review queue ---------- */

/** Peer-reviewed research items not in the library and not dismissed, strongest evidence first. */
function mkt_lit_review_queue(array $filters = [], int $limit = 150): array
{
    $where = ["h.EvidenceType = N'peer_reviewed'", "h.Status IN (N'scored', N'clustered')",
        'NOT EXISTS (SELECT 1 FROM dbo.MktLitSource s WHERE s.ItemID = h.ItemID)',
        'NOT EXISTS (SELECT 1 FROM dbo.MktLitDismissed d WHERE d.ItemID = h.ItemID)'];
    $params = [];
    if (($filters['product'] ?? '') !== '') {
        $where[] = 'h.TagsJson LIKE :product';
        $params['product'] = '%"' . str_replace(['%', '_', '['], ['[%]', '[_]', '[[]'], (string) $filters['product']) . '"%';
    }
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(h.Title LIKE :q1 OR h.AiSummary LIKE :q2)';
        $params['q1'] = $params['q2'] = '%' . (string) $filters['q'] . '%';
    }
    $cols = MKT_LIT_ITEM_COLS;
    $stmt = db()->prepare("SELECT $cols, h.StudyJson FROM dbo.MktHarvestedItem h WHERE " . implode(' AND ', $where) . ' ORDER BY h.RelevanceMax DESC, h.ItemID DESC');
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['Level'] = $row['StudyJson'] !== null ? mkt_lit_row_level($row) : null;
        $row['IsNews'] = !in_array((string) $row['SourceType'], ['pubmed', 'clinicaltrials'], true);
        if (($filters['level'] ?? '') !== '' && $row['Level'] !== $filters['level']) {
            continue;
        }
        $rows[] = $row;
    }
    usort($rows, static fn(array $a, array $b): int => [$a['IsNews'], MKT_LIT_LEVEL_RANK[$a['Level'] ?? 'other'], -(float) $a['RelevanceMax']]
        <=> [$b['IsNews'], MKT_LIT_LEVEL_RANK[$b['Level'] ?? 'other'], -(float) $b['RelevanceMax']]);

    return ['total' => count($rows), 'rows' => array_slice($rows, 0, $limit)];
}

function mkt_lit_dismiss(int $itemId): void
{
    db()->prepare('IF NOT EXISTS (SELECT 1 FROM dbo.MktLitDismissed WHERE ItemID = :id) INSERT INTO dbo.MktLitDismissed (ItemID, CreatedBy) SELECT ItemID, :u FROM dbo.MktHarvestedItem WHERE ItemID = :id2')
        ->execute(['id' => $itemId, 'u' => marketing_user_id(), 'id2' => $itemId]);
}

function mkt_lit_undismiss(int $itemId): void
{
    db()->prepare('DELETE FROM dbo.MktLitDismissed WHERE ItemID = :id')->execute(['id' => $itemId]);
}

function mkt_lit_dismissed(int $limit = 100): array
{
    $cols = MKT_LIT_ITEM_COLS;

    return db()->query("SELECT TOP ($limit) $cols, CONVERT(varchar(19), d.CreatedAt, 120) AS DismissedAt, u.UserName AS DismissedBy
        FROM dbo.MktLitDismissed d JOIN dbo.MktHarvestedItem h ON h.ItemID = d.ItemID LEFT JOIN dbo.[User] u ON u.UserID = d.CreatedBy
        ORDER BY d.CreatedAt DESC")->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- Flyer references ---------- */

/** Bring MktLitFlyerRef in line with each product's reference list; a changed entry loses its match. */
function mkt_lit_flyer_sync(): array
{
    $counts = ['added' => 0, 'changed' => 0, 'removed' => 0];
    $existing = [];
    foreach (db()->query('SELECT FlyerRefID, ProductID, RefNumber, RefText FROM dbo.MktLitFlyerRef')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int) $row['ProductID']][(int) $row['RefNumber']] = $row;
    }
    $insert = db()->prepare('INSERT INTO dbo.MktLitFlyerRef (ProductID, RefNumber, RefText, IsStudy, MatchStatus) VALUES (:p, :n, :t, :s, :m)');
    $update = db()->prepare('UPDATE dbo.MktLitFlyerRef SET RefText = :t, IsStudy = :s, MatchStatus = :m, SourceID = NULL, CandidatesJson = NULL, CheckedAt = NULL, UpdatedAt = SYSUTCDATETIME() WHERE FlyerRefID = :id');
    $delete = db()->prepare('DELETE FROM dbo.MktLitFlyerRef WHERE FlyerRefID = :id');
    foreach (db()->query('SELECT ProductID, ReferenceList FROM dbo.MktProduct')->fetchAll(PDO::FETCH_ASSOC) as $product) {
        $productId = (int) $product['ProductID'];
        $refs = mkt_lit_flyer_refs($product['ReferenceList']);
        foreach ($refs as $i => $text) {
            $number = $i + 1;
            $text = mb_substr($text, 0, 1000);
            $isStudy = mkt_lit_ref_is_study($text);
            $row = $existing[$productId][$number] ?? null;
            if ($row === null) {
                $insert->execute(['p' => $productId, 'n' => $number, 't' => $text, 's' => $isStudy ? 1 : 0, 'm' => $isStudy ? 'unmatched' : 'other']);
                $counts['added']++;
            } elseif ((string) $row['RefText'] !== $text) {
                $update->execute(['t' => $text, 's' => $isStudy ? 1 : 0, 'm' => $isStudy ? 'unmatched' : 'other', 'id' => (int) $row['FlyerRefID']]);
                $counts['changed']++;
            }
            unset($existing[$productId][$number]);
        }
        foreach ($existing[$productId] ?? [] as $row) {
            $delete->execute(['id' => (int) $row['FlyerRefID']]);
            $counts['removed']++;
        }
    }

    return $counts;
}

/** @return list<array{ProductID:int, Name:string, refs:list<array>}> products with their numbered references and citing claims */
function mkt_lit_flyer_list(string $filter = ''): array
{
    $claims = [];
    foreach (db()->query("SELECT ClaimID, ProductID, ReferenceNumbers FROM dbo.MktClaim WHERE Status = N'approved' AND ReferenceNumbers IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC) as $c) {
        foreach (mkt_lit_ref_numbers($c['ReferenceNumbers']) as $n) {
            $claims[(int) $c['ProductID']][$n][] = (int) $c['ClaimID'];
        }
    }
    $rows = db()->query('SELECT f.*, p.Name AS ProductName, h.Title AS SourceTitle
        FROM dbo.MktLitFlyerRef f JOIN dbo.MktProduct p ON p.ProductID = f.ProductID
        LEFT JOIN dbo.MktLitSource s ON s.SourceID = f.SourceID LEFT JOIN dbo.MktHarvestedItem h ON h.ItemID = s.ItemID
        ORDER BY p.Name, f.RefNumber')->fetchAll(PDO::FETCH_ASSOC);
    $products = [];
    foreach ($rows as $row) {
        if ($filter !== '' && $row['MatchStatus'] !== $filter) {
            continue;
        }
        $pid = (int) $row['ProductID'];
        $products[$pid] ??= ['ProductID' => $pid, 'Name' => (string) $row['ProductName'], 'refs' => []];
        $row['Candidates'] = json_decode((string) ($row['CandidatesJson'] ?? ''), true) ?: [];
        $row['Claims'] = $claims[$pid][(int) $row['RefNumber']] ?? [];
        $products[$pid]['refs'][] = $row;
    }

    return array_values($products);
}

function mkt_lit_flyer_counts(): array
{
    $counts = array_fill_keys(array_keys(MKT_LIT_MATCH_STATUSES), 0);
    foreach (db()->query('SELECT MatchStatus, COUNT(*) AS N FROM dbo.MktLitFlyerRef GROUP BY MatchStatus')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $counts[(string) $row['MatchStatus']] = (int) $row['N'];
    }

    return $counts;
}

function mkt_lit_products_without_refs(): array
{
    return db()->query("SELECT ProductID, Name FROM dbo.MktProduct WHERE NULLIF(LTRIM(RTRIM(CAST(ReferenceList AS NVARCHAR(MAX)))), N'') IS NULL ORDER BY Name")->fetchAll(PDO::FETCH_ASSOC);
}

function mkt_lit_flyer_ref_get(int $flyerRefId): ?array
{
    $stmt = db()->prepare('SELECT f.*, p.Name AS ProductName FROM dbo.MktLitFlyerRef f JOIN dbo.MktProduct p ON p.ProductID = f.ProductID WHERE f.FlyerRefID = :id');
    $stmt->execute(['id' => $flyerRefId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/** Person decisions on a flyer reference that need no lookup: no match, not an article, or back to unchecked. */
function mkt_lit_flyer_set(int $flyerRefId, string $status): bool
{
    if (!in_array($status, ['no_match', 'other', 'unmatched'], true)) {
        return false;
    }
    $stmt = db()->prepare('UPDATE dbo.MktLitFlyerRef SET MatchStatus = :s, SourceID = NULL, CandidatesJson = CASE WHEN :s2 = N\'unmatched\' THEN NULL ELSE CandidatesJson END,
        UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE FlyerRefID = :id');
    $stmt->execute(['s' => $status, 's2' => $status, 'u' => marketing_user_id(), 'id' => $flyerRefId]);

    return $stmt->rowCount() > 0;
}

/** PMID, DOI, NCT number, or a PubMed / doi.org / ClinicalTrials.gov link — normalised, or null when unrecognised. */
function mkt_lit_identifier(string $raw): ?string
{
    $raw = trim($raw);
    if (preg_match('~pubmed\.ncbi\.nlm\.nih\.gov/(\d{1,9})~i', $raw, $m) || preg_match('/^(?:pmid:?\s*)?(\d{1,9})$/i', $raw, $m)) {
        return $m[1];
    }
    if (preg_match('~(?:clinicaltrials\.gov/(?:study|ct2/show)/)?\b(NCT\d{8})\b~i', $raw, $m)) {
        return strtoupper($m[1]);
    }
    if (preg_match('~(?:doi\.org/|doi:\s*)?(10\.\d{4,9}/\S+)~i', $raw, $m)) {
        return rtrim($m[1], '.,;');
    }

    return null;
}

/* ---------- Claims coverage ---------- */

/** Every approved claim with the flyer studies it cites, how many are matched, direct links, and the best level. */
function mkt_lit_claims_coverage(): array
{
    $refs = [];
    $sourceIds = [];
    foreach (db()->query('SELECT ProductID, RefNumber, IsStudy, MatchStatus, SourceID FROM dbo.MktLitFlyerRef')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $refs[(int) $r['ProductID']][(int) $r['RefNumber']] = $r;
        if ($r['SourceID'] !== null) {
            $sourceIds[] = (int) $r['SourceID'];
        }
    }
    $direct = [];
    foreach (db()->query('SELECT ClaimID, SourceID FROM dbo.MktLitClaimSource')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $direct[(int) $r['ClaimID']][] = (int) $r['SourceID'];
        $sourceIds[] = (int) $r['SourceID'];
    }
    $levels = [];
    if ($sourceIds !== []) {
        $in = implode(',', array_unique($sourceIds));
        foreach (db()->query("SELECT s.SourceID, s.LevelOverride, h.SourceType, COALESCE(s.StudyJson, h.StudyJson) AS EffectiveStudyJson
            FROM dbo.MktLitSource s JOIN dbo.MktHarvestedItem h ON h.ItemID = s.ItemID WHERE s.Status = N'active' AND s.SourceID IN ($in)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $levels[(int) $r['SourceID']] = mkt_lit_row_level($r);
        }
    }
    $claims = db()->query("SELECT c.ClaimID, c.ClaimText, c.EvidenceTier, c.ReferenceNumbers, c.ProductID, p.Name AS ProductName
        FROM dbo.MktClaim c LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
        WHERE c.Status = N'approved' ORDER BY p.Name, c.SortOrder, c.ClaimID")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($claims as &$c) {
        $nums = mkt_lit_ref_numbers($c['ReferenceNumbers']);
        $productRefs = $refs[(int) $c['ProductID']] ?? [];
        $studies = array_filter($nums, static fn(int $n): bool => !empty($productRefs[$n]['IsStudy']));
        $matchedSources = [];
        foreach ($studies as $n) {
            if (($productRefs[$n]['MatchStatus'] ?? '') === 'matched' && isset($levels[(int) $productRefs[$n]['SourceID']])) {
                $matchedSources[] = (int) $productRefs[$n]['SourceID'];
            }
        }
        $linked = array_values(array_filter($direct[(int) $c['ClaimID']] ?? [], static fn(int $id): bool => isset($levels[$id])));
        $c['Cited'] = count($nums);
        $c['Studies'] = count($studies);
        $c['Matched'] = count($matchedSources);
        $c['Linked'] = count($linked);
        $c['BestLevel'] = mkt_lit_best_level(array_map(static fn(int $id): string => $levels[$id], array_merge($matchedSources, $linked)));
        $c['Coverage'] = match (true) {
            $c['EvidenceTier'] === 'label'                  => 'label',
            $c['Cited'] === 0 && $c['Linked'] === 0         => 'none',
            $c['Studies'] === 0 && $c['Linked'] === 0       => 'no_study',
            $c['Matched'] < $c['Studies']                   => 'partial',
            default                                         => 'matched',
        };
        $c['TierGap'] = $c['EvidenceTier'] === 'clinical' && $c['Coverage'] !== 'label'
            && ($c['BestLevel'] === null || !mkt_lit_level_fits_tier($c['BestLevel'], 'clinical'));
    }
    unset($c);
    $order = ['none' => 0, 'no_study' => 1, 'partial' => 2, 'matched' => 3, 'label' => 4];
    usort($claims, static fn(array $a, array $b): int => [$order[$a['Coverage']], (string) $a['ProductName'], (int) $a['ClaimID']]
        <=> [$order[$b['Coverage']], (string) $b['ProductName'], (int) $b['ClaimID']]);

    return $claims;
}

/** Claims a source backs (direct links and flyer citations) plus suggestions from the products it is tagged with. */
function mkt_lit_source_claims(array $source): array
{
    $sourceId = (int) $source['SourceID'];
    $linked = [];
    $stmt = db()->prepare('SELECT c.ClaimID FROM dbo.MktLitClaimSource cs JOIN dbo.MktClaim c ON c.ClaimID = cs.ClaimID WHERE cs.SourceID = :id');
    $stmt->execute(['id' => $sourceId]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $linked[(int) $id] = 'direct';
    }
    foreach (mkt_lit_flyer_claims_by_source([$sourceId])[$sourceId] ?? [] as $id) {
        $linked[$id] ??= 'flyer';
    }
    $products = mkt_item_tags($source['TagsJson'] ?? null)['products'];
    $params = [];
    $conds = [];
    if ($linked !== []) {
        $conds[] = 'c.ClaimID IN (' . implode(',', array_keys($linked)) . ')';
    }
    if ($products !== []) {
        $holders = [];
        foreach (array_values($products) as $i => $name) {
            $holders[] = ':p' . $i;
            $params['p' . $i] = $name;
        }
        $conds[] = "(c.Status = N'approved' AND c.EvidenceTier <> N'label' AND p.Name IN (" . implode(',', $holders) . '))';
    }
    if ($conds === []) {
        return [];
    }
    $stmt = db()->prepare('SELECT c.ClaimID, c.ClaimText, c.EvidenceTier, c.Status, p.Name AS ProductName FROM dbo.MktClaim c LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
        WHERE ' . implode(' OR ', $conds) . ' ORDER BY p.Name, c.SortOrder, c.ClaimID');
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['Link'] = $linked[(int) $row['ClaimID']] ?? null;
        $row['Fits'] = mkt_lit_level_fits_tier((string) $source['Level'], (string) $row['EvidenceTier']);
        $rows[] = $row;
    }
    usort($rows, static fn(array $a, array $b): int => [$a['Link'] === null, (string) $a['ProductName']] <=> [$b['Link'] === null, (string) $b['ProductName']]);

    return $rows;
}

function mkt_lit_claim_link(int $claimId, int $sourceId): array
{
    $source = mkt_lit_source_get($sourceId);
    $stmt = db()->prepare('SELECT ClaimID, EvidenceTier FROM dbo.MktClaim WHERE ClaimID = :id');
    $stmt->execute(['id' => $claimId]);
    $claim = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($source === null || !$claim) {
        return ['ok' => false, 'error' => 'Claim or source not found.'];
    }
    if (!mkt_lit_level_fits_tier($source['Level'], (string) $claim['EvidenceTier'])) {
        $levelLabel = strtolower(MKT_LIT_LEVELS[$source['Level']]);

        return ['ok' => false, 'error' => (preg_match('/^[aeiou]/', $levelLabel) ? 'An ' : 'A ') . $levelLabel . ' cannot back a ' . $claim['EvidenceTier'] . ' claim. Clinical claims need a human study.'];
    }
    db()->prepare('IF NOT EXISTS (SELECT 1 FROM dbo.MktLitClaimSource WHERE ClaimID = :c AND SourceID = :s) INSERT INTO dbo.MktLitClaimSource (ClaimID, SourceID, CreatedBy) VALUES (:c2, :s2, :u)')
        ->execute(['c' => $claimId, 's' => $sourceId, 'c2' => $claimId, 's2' => $sourceId, 'u' => marketing_user_id()]);

    return ['ok' => true];
}

function mkt_lit_claim_unlink(int $claimId, int $sourceId): void
{
    db()->prepare('DELETE FROM dbo.MktLitClaimSource WHERE ClaimID = :c AND SourceID = :s')->execute(['c' => $claimId, 's' => $sourceId]);
}

/* ---------- Regulatory watch ---------- */

function mkt_lit_regulatory(string $status = ''): array
{
    $cols = MKT_LIT_ITEM_COLS;
    $rows = db()->query("SELECT $cols, COALESCE(r.Status, N'new') AS WatchStatus, r.Note, CONVERT(varchar(19), r.UpdatedAt, 120) AS WatchUpdatedAt, u.UserName AS WatchUpdatedBy
        FROM dbo.MktHarvestedItem h
        LEFT JOIN dbo.MktLitRegulatory r ON r.ItemID = h.ItemID
        LEFT JOIN dbo.[User] u ON u.UserID = r.UpdatedBy
        WHERE h.EvidenceType = N'regulatory' AND (h.Status <> N'discarded' OR r.ItemID IS NOT NULL)
        ORDER BY CASE COALESCE(r.Status, N'new') WHEN N'action' THEN 0 WHEN N'new' THEN 1 ELSE 2 END, h.PublishedAt DESC, h.ItemID DESC")->fetchAll(PDO::FETCH_ASSOC);

    return $status === '' ? $rows : array_values(array_filter($rows, static fn(array $r): bool => $r['WatchStatus'] === $status));
}

function mkt_lit_regulatory_counts(): array
{
    $counts = array_fill_keys(array_keys(MKT_LIT_REG_STATUSES), 0);
    foreach (mkt_lit_regulatory() as $row) {
        $counts[$row['WatchStatus']]++;
    }

    return $counts;
}

function mkt_lit_regulatory_set(int $itemId, string $status, string $note = ''): array
{
    if (!isset(MKT_LIT_REG_STATUSES[$status])) {
        return ['ok' => false, 'error' => 'Unknown status.'];
    }
    $note = trim($note);
    if ($status === 'action' && $note === '') {
        return ['ok' => false, 'error' => 'Say what needs to happen — the note becomes the Compliance task.'];
    }
    $stmt = db()->prepare("SELECT ItemID FROM dbo.MktHarvestedItem WHERE ItemID = :id AND EvidenceType = N'regulatory'");
    $stmt->execute(['id' => $itemId]);
    if ($stmt->fetchColumn() === false) {
        return ['ok' => false, 'error' => 'Regulatory item not found.'];
    }
    if ($status === 'new') {
        db()->prepare('DELETE FROM dbo.MktLitRegulatory WHERE ItemID = :id')->execute(['id' => $itemId]);

        return ['ok' => true];
    }
    db()->prepare('MERGE dbo.MktLitRegulatory AS t USING (SELECT :id AS ItemID) AS s ON t.ItemID = s.ItemID
        WHEN MATCHED THEN UPDATE SET Status = :st, Note = COALESCE(:n, t.Note), UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME()
        WHEN NOT MATCHED THEN INSERT (ItemID, Status, Note, UpdatedBy) VALUES (:id2, :st2, :n2, :u2);')
        ->execute(['id' => $itemId, 'st' => $status, 'n' => $note === '' ? null : mb_substr($note, 0, 1000), 'u' => marketing_user_id(),
            'id2' => $itemId, 'st2' => $status, 'n2' => $note === '' ? null : mb_substr($note, 0, 1000), 'u2' => marketing_user_id()]);

    return ['ok' => true];
}

/** One Compliance task per regulatory item marked "Action needed"; marking it done or reviewed closes the task. */
function mkt_lit_desired_tasks(callable $add): void
{
    $rows = db()->query("SELECT r.ItemID, r.Note, CONVERT(varchar(19), r.UpdatedAt, 120) AS UpdatedAt, h.Title
        FROM dbo.MktLitRegulatory r JOIN dbo.MktHarvestedItem h ON h.ItemID = r.ItemID WHERE r.Status = N'action'")->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === []) {
        return;
    }
    $complianceUser = mkt_task_compliance_user();
    foreach ($rows as $row) {
        $add('regulatory:' . (int) $row['ItemID'] . ':action', [
            'Title' => 'Regulatory action: ' . mb_strimwidth((string) $row['Title'], 0, 150, '…'),
            'TaskType' => 'regulatory_action', 'AssigneeRole' => 'compliance', 'AssigneeUserID' => $complianceUser,
            'RefType' => 'item', 'RefID' => (int) $row['ItemID'], 'Href' => '/marketing/literature/?tab=regulatory&status=action',
            'Priority' => 'high', 'DueDate' => mkt_task_due((string) $row['UpdatedAt'], 'compliance'),
            'Detail' => (string) $row['Note'] . ' When it is handled, mark the item "Action done" on the Regulatory watch tab.',
        ]);
    }
}

/* ---------- Summary ---------- */

function mkt_lit_summary(): array
{
    $sources = db()->query("SELECT s.LevelOverride, h.SourceType, COALESCE(s.StudyJson, h.StudyJson) AS EffectiveStudyJson
        FROM dbo.MktLitSource s JOIN dbo.MktHarvestedItem h ON h.ItemID = s.ItemID WHERE s.Status = N'active'")->fetchAll(PDO::FETCH_ASSOC);
    $levels = array_count_values(array_map('mkt_lit_row_level', $sources));

    return ['sources' => count($sources), 'levels' => $levels];
}
