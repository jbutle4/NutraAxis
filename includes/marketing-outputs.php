<?php

require_once __DIR__ . '/marketing-docs.php';
require_once __DIR__ . '/marketing-literature.php';
require_once __DIR__ . '/marketing-content.php';

/** Output Generator documents: key => [title, what it is for]. */
const MKT_OUTPUT_TYPES = [
    'evidence-pack' => ['Product evidence pack', 'Every approved claim for a product with the studies behind it, their evidence level and findings, and the flyer reference list matched to papers. For compliance files and practitioner or retailer requests.'],
    'claim'         => ['Claim evidence summary', 'One approved claim with full study facts, takeaways and limitations for each study behind it.'],
    'topic'         => ['Topic research brief', 'An accepted or proposed topic: the angle, the evidence items with their study facts, linked claims, and the other items in the topic.'],
    'bibliography'  => ['Library bibliography', 'Library sources as a formatted reference list, grouped by evidence level, with takeaways. Filter by product, area or level.'],
    'article'       => ['Article for the site author', 'A Content Pipeline piece with its meta title, meta description and body, marked approved or not approved to publish.'],
];

/* ---------- Shared pieces ---------- */

/** Active library sources by ID, each with Level, Study, Citation and Links. */
function mkt_out_sources(array $sourceIds): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $sourceIds))));
    if ($ids === []) {
        return [];
    }
    $cols = MKT_LIT_ITEM_COLS;
    $rows = db()->query("SELECT s.SourceID, s.LevelOverride, s.Takeaway, s.Limitations, COALESCE(s.StudyJson, h.StudyJson) AS EffectiveStudyJson, $cols
        FROM dbo.MktLitSource s JOIN dbo.MktHarvestedItem h ON h.ItemID = s.ItemID
        WHERE s.Status = N'active' AND s.SourceID IN (" . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $row) {
        $row['Level'] = mkt_lit_row_level($row);
        $row['Study'] = mkt_lit_study($row['EffectiveStudyJson']);
        $row['Citation'] = mkt_lit_citation($row);
        $row['Links'] = mkt_out_links($row);
        $out[(int) $row['SourceID']] = $row;
    }

    return $out;
}

/** "[PubMed 123](…) · [DOI](…)" for a library source or research item. */
function mkt_out_links(array $row): string
{
    $meta = mkt_lit_meta($row);
    $links = [];
    if (!empty($meta['pmid'])) {
        $links[] = '[PubMed ' . $meta['pmid'] . '](https://pubmed.ncbi.nlm.nih.gov/' . rawurlencode((string) $meta['pmid']) . '/)';
    }
    if (!empty($meta['doi'])) {
        $links[] = '[DOI](https://doi.org/' . str_replace(['(', ')', ' '], ['%28', '%29', '%20'], (string) $meta['doi']) . ')';
    }
    if (!empty($meta['nct_id'])) {
        $links[] = '[' . $meta['nct_id'] . '](https://clinicaltrials.gov/study/' . rawurlencode((string) $meta['nct_id']) . ')';
    }
    if ($links === [] && !empty($row['Url']) && preg_match('#^https?://#', (string) $row['Url'])) {
        $links[] = '[' . mkt_doc_literal((string) ($row['Domain'] ?? 'Link')) . '](' . str_replace([' ', ')'], ['%20', '%29'], (string) $row['Url']) . ')';
    }

    return implode(' · ', $links);
}

/** "n = 63 · 600 mg/day · 12 weeks" from study facts. */
function mkt_out_study_line(array $study): string
{
    $parts = [];
    if (!empty($study['n'])) {
        $parts[] = 'n = ' . number_format((int) $study['n']);
    }
    foreach (['dose', 'duration'] as $key) {
        if (!empty($study[$key])) {
            $parts[] = (string) $study[$key];
        }
    }

    return mkt_doc_literal(implode(' · ', $parts));
}

function mkt_out_level_label(?string $level): string
{
    return $level !== null ? (MKT_LIT_LEVELS[$level] ?? '—') : '—';
}

function mkt_out_levels_note(): array
{
    return ['type' => 'note', 'text' => 'Evidence levels, strongest first: meta-analysis or systematic review, randomised trial, other human study, registered trial with no results yet, animal or lab study, narrative review, other source. Clinical claims need a human study; mechanistic claims also accept lab studies and reviews. Levels are worked out from each study’s design unless set by hand in Literature & Intelligence.'];
}

/** Full study facts for one source, as key-value rows. */
function mkt_out_study_rows(array $src): array
{
    $s = $src['Study'];
    $rows = [['Citation', mkt_doc_literal($src['Citation']) . ($src['Links'] !== '' ? ' ' . $src['Links'] : '')], ['Evidence level', mkt_out_level_label($src['Level'])]];
    foreach (['design' => 'Design', 'population' => 'Population', 'n' => 'Participants', 'intervention' => 'Intervention', 'dose' => 'Dose', 'duration' => 'Duration', 'outcomes' => 'Outcomes', 'result' => 'Result'] as $key => $label) {
        if (isset($s[$key]) && $s[$key] !== '' && $s[$key] !== null) {
            $rows[] = [$label, mkt_doc_literal($key === 'n' ? number_format((int) $s[$key]) : (string) $s[$key])];
        }
    }
    if (!empty($src['Takeaway'])) {
        $rows[] = ['Takeaway', mkt_doc_literal((string) $src['Takeaway'])];
    }
    if (!empty($src['Limitations'])) {
        $rows[] = ['Limitations', mkt_doc_literal((string) $src['Limitations'])];
    }

    return $rows;
}

/**
 * Claims for a product (or one claim) with the evidence rows behind each: cited flyer references and direct links.
 *
 * @return array{claims: list<array>, refs: array<int, array>, sources: array<int, array>}
 */
function mkt_out_claim_evidence(int $productId, ?int $claimId = null): array
{
    $sql = "SELECT ClaimID, ProductID, ClaimText, ClaimType, Ingredient, EvidenceTier, ReferenceNumbers, RequiresDisclaimer, SortOrder
            FROM dbo.MktClaim WHERE Status = N'approved' AND " . ($claimId !== null ? 'ClaimID = :id' : 'ProductID = :id') . ' ORDER BY SortOrder, ClaimID';
    $stmt = db()->prepare($sql);
    $stmt->execute(['id' => $claimId ?? $productId]);
    $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($claims === []) {
        return ['claims' => [], 'refs' => [], 'sources' => []];
    }
    $productId = (int) $claims[0]['ProductID'];
    $refStmt = db()->prepare('SELECT RefNumber, RefText, IsStudy, MatchStatus, SourceID FROM dbo.MktLitFlyerRef WHERE ProductID = :p ORDER BY RefNumber');
    $refStmt->execute(['p' => $productId]);
    $refs = [];
    foreach ($refStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $refs[(int) $r['RefNumber']] = $r;
    }
    $claimIds = implode(',', array_map('intval', array_column($claims, 'ClaimID')));
    $direct = [];
    foreach (db()->query("SELECT ClaimID, SourceID FROM dbo.MktLitClaimSource WHERE ClaimID IN ($claimIds)")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $direct[(int) $r['ClaimID']][] = (int) $r['SourceID'];
    }
    $sources = mkt_out_sources(array_merge(array_column($refs, 'SourceID'), ...array_values($direct ?: [[]])));
    $coverage = [];
    foreach (mkt_lit_claims_coverage() as $c) {
        $coverage[(int) $c['ClaimID']] = $c;
    }
    foreach ($claims as &$claim) {
        $id = (int) $claim['ClaimID'];
        $claim['Numbers'] = mkt_lit_ref_numbers($claim['ReferenceNumbers']);
        $claim['Direct'] = array_values(array_filter($direct[$id] ?? [], static fn(int $s): bool => isset($sources[$s])));
        $claim['Coverage'] = $coverage[$id]['Coverage'] ?? 'none';
        $claim['BestLevel'] = $coverage[$id]['BestLevel'] ?? null;
        $claim['TierGap'] = (bool) ($coverage[$id]['TierGap'] ?? false);
    }
    unset($claim);

    return ['claims' => $claims, 'refs' => $refs, 'sources' => $sources];
}

/** Table rows (Ref, Study, Level, Design and size, Finding) for one claim's evidence. */
function mkt_out_evidence_rows(array $claim, array $refs, array $sources): array
{
    $rows = [];
    foreach ($claim['Numbers'] as $n) {
        $ref = $refs[$n] ?? null;
        if ($ref === null) {
            $rows[] = [(string) $n, '*Not on the product’s reference list*', '—', '', ''];
            continue;
        }
        $src = $ref['SourceID'] !== null ? ($sources[(int) $ref['SourceID']] ?? null) : null;
        if ($src !== null && $ref['MatchStatus'] === 'matched') {
            $rows[] = [(string) $n, mkt_doc_literal($src['Citation']) . ($src['Links'] !== '' ? ' ' . $src['Links'] : ''), mkt_out_level_label($src['Level']),
                mkt_doc_literal(trim(((string) ($src['Study']['design'] ?? '')) . ' ' . (mkt_out_study_line($src['Study']) !== '' ? '(' . mkt_out_study_line($src['Study']) . ')' : ''))),
                mkt_doc_literal((string) ($src['Takeaway'] ?: ($src['Study']['result'] ?? '')))];
            continue;
        }
        $status = (bool) $ref['IsStudy'] ? ($ref['MatchStatus'] === 'no_match' ? '*No PubMed match found*' : '*Not matched to a paper yet*') : '*Not a journal article*';
        $rows[] = [(string) $n, mkt_doc_literal((string) $ref['RefText']), '—', $status, ''];
    }
    foreach ($claim['Direct'] as $sourceId) {
        $src = $sources[$sourceId];
        $rows[] = ['Linked', mkt_doc_literal($src['Citation']) . ($src['Links'] !== '' ? ' ' . $src['Links'] : ''), mkt_out_level_label($src['Level']),
            mkt_doc_literal(trim(((string) ($src['Study']['design'] ?? '')) . ' ' . (mkt_out_study_line($src['Study']) !== '' ? '(' . mkt_out_study_line($src['Study']) . ')' : ''))),
            mkt_doc_literal((string) ($src['Takeaway'] ?: ($src['Study']['result'] ?? '')))];
    }

    return $rows;
}

function mkt_out_coverage_text(array $claim): string
{
    $label = MKT_LIT_COVERAGE[$claim['Coverage']] ?? '';
    if ($claim['TierGap'] && $claim['Coverage'] !== 'none') {
        $label .= ' — no matched human study yet for this clinical claim';
    }

    return $label;
}

/* ---------- Documents ---------- */

function mkt_out_evidence_pack(int $productId): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktProduct WHERE ProductID = :id');
    $stmt->execute(['id' => $productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        return null;
    }
    ['claims' => $claims, 'refs' => $refs, 'sources' => $sources] = mkt_out_claim_evidence($productId);
    $name = (string) $product['Name'];
    $studyRefs = array_filter($refs, static fn(array $r): bool => (bool) $r['IsStudy']);
    $matched = array_filter($studyRefs, static fn(array $r): bool => $r['MatchStatus'] === 'matched' && isset($sources[(int) $r['SourceID']]));
    $nonLabel = array_filter($claims, static fn(array $c): bool => $c['Coverage'] !== 'label');
    $backed = array_filter($nonLabel, static fn(array $c): bool => $c['Coverage'] === 'matched');

    $blocks = [
        ['type' => 'h2', 'text' => 'At a glance'],
        ['type' => 'metrics', 'items' => [
            ['Approved claims', (string) count($claims)],
            ['Claims fully backed', count($backed) . ' of ' . count($nonLabel), '', 'every cited study matched'],
            ['Flyer references', (string) count($refs), '', count($studyRefs) . ' journal articles'],
            ['Studies matched', count($matched) . ' of ' . count($studyRefs), '', 'to a paper in the library'],
        ]],
        ['type' => 'h2', 'text' => 'Product'],
        ['type' => 'kv', 'rows' => array_values(array_filter([
            ['Therapeutic area', mkt_doc_literal((string) ($product['TherapeuticArea'] ?? ''))],
            ['Summary', mkt_doc_literal((string) ($product['Summary'] ?? ''))],
            ['Suggested use', mkt_doc_literal((string) ($product['SuggestedUse'] ?? ''))],
            ['Formula', mkt_doc_literal((string) ($product['Formula'] ?? ''))],
        ], static fn(array $r): bool => trim((string) $r[1]) !== ''))],
        ['type' => 'h2', 'text' => 'Claims and their evidence'],
    ];
    if ($claims === []) {
        $blocks[] = ['type' => 'note', 'text' => 'This product has no approved claims in the Claims Matrix.'];
    }
    foreach ($claims as $i => $claim) {
        $blocks[] = ['type' => 'h3', 'text' => ($i + 1) . '. ' . ucfirst((string) $claim['EvidenceTier']) . ' claim' . (!empty($claim['Ingredient']) ? ' — ' . mkt_doc_literal((string) $claim['Ingredient']) : '')];
        $blocks[] = ['type' => 'p', 'text' => '“' . mkt_doc_literal((string) $claim['ClaimText']) . '”'];
        $blocks[] = ['type' => 'kv', 'rows' => array_values(array_filter([
            ['Flyer references', $claim['ReferenceNumbers'] !== null && trim((string) $claim['ReferenceNumbers']) !== '' ? mkt_doc_literal((string) $claim['ReferenceNumbers']) : 'None cited'],
            ['Coverage', mkt_out_coverage_text($claim)],
            ['Best evidence', mkt_out_level_label($claim['BestLevel'])],
            !empty($claim['RequiresDisclaimer']) ? ['Disclaimer', 'Required wherever this claim is used'] : null,
        ]))];
        if ($claim['Coverage'] !== 'label' || $claim['Numbers'] !== [] || $claim['Direct'] !== []) {
            $blocks[] = ['type' => 'table', 'head' => ['Ref', 'Study', 'Evidence level', 'Design and size', 'Finding'], 'widths' => [7, 36, 14, 18, 25],
                'rows' => mkt_out_evidence_rows($claim, $refs, $sources), 'empty' => 'No references cited and no library studies linked.'];
        }
    }
    $blocks[] = ['type' => 'h2', 'text' => 'Reference list'];
    if ($refs === []) {
        $blocks[] = ['type' => 'note', 'text' => 'No reference list on file for this product. Add the flyer’s references on the product in the Claims Matrix.'];
    } else {
        $items = [];
        foreach ($refs as $ref) {
            $src = $ref['SourceID'] !== null ? ($sources[(int) $ref['SourceID']] ?? null) : null;
            $items[] = mkt_doc_literal((string) $ref['RefText']) . ' — ' . match (true) {
                $src !== null && $ref['MatchStatus'] === 'matched' => 'matched: ' . ($src['Links'] !== '' ? $src['Links'] : mkt_doc_literal($src['Citation'])),
                !(bool) $ref['IsStudy']                            => '*not a journal article*',
                $ref['MatchStatus'] === 'no_match'                 => '*no PubMed match found*',
                default                                            => '*not matched to a paper yet*',
            };
        }
        $blocks[] = ['type' => 'bullets', 'ordered' => true, 'items' => $items];
    }
    $blocks[] = ['type' => 'h2', 'text' => 'About this pack'];
    $blocks[] = ['type' => 'note', 'text' => 'Claim wording is the approved Claims Matrix wording. A claim counts as fully backed when every journal article it cites on the flyer is matched to a paper in the evidence library. Study facts are extracted from each abstract and corrected by the team where needed; check the paper before relying on a figure.'];
    $blocks[] = mkt_out_levels_note();

    return [
        'title'    => $name . ' evidence pack',
        'subtitle' => mkt_doc_literal((string) ($product['Headline'] ?? '')),
        'meta'     => [['Product', mkt_doc_literal($name)], ['Approved claims', (string) count($claims)], mkt_doc_generated_meta()],
        'notice'   => MKT_DOC_INTERNAL_NOTICE,
        'filename' => 'evidence-pack-' . $name . '-' . mkt_local_date(),
        'blocks'   => $blocks,
    ];
}

function mkt_out_claim_summary(int $claimId): ?array
{
    ['claims' => $claims, 'refs' => $refs, 'sources' => $sources] = mkt_out_claim_evidence(0, $claimId);
    if ($claims === []) {
        return null;
    }
    $claim = $claims[0];
    $stmt = db()->prepare('SELECT Name FROM dbo.MktProduct WHERE ProductID = :id');
    $stmt->execute(['id' => (int) $claim['ProductID']]);
    $productName = (string) ($stmt->fetchColumn() ?: '');

    $blocks = [
        ['type' => 'h2', 'text' => 'Claim'],
        ['type' => 'p', 'text' => '“' . mkt_doc_literal((string) $claim['ClaimText']) . '”'],
        ['type' => 'kv', 'rows' => array_values(array_filter([
            ['Product', mkt_doc_literal($productName)],
            ['Evidence tier', ucfirst((string) $claim['EvidenceTier'])],
            !empty($claim['Ingredient']) ? ['Ingredient', mkt_doc_literal((string) $claim['Ingredient'])] : null,
            ['Flyer references', trim((string) $claim['ReferenceNumbers']) !== '' ? mkt_doc_literal((string) $claim['ReferenceNumbers']) : 'None cited'],
            ['Coverage', mkt_out_coverage_text($claim)],
            ['Best evidence', mkt_out_level_label($claim['BestLevel'])],
            !empty($claim['RequiresDisclaimer']) ? ['Disclaimer', 'Required wherever this claim is used'] : null,
        ]))],
        ['type' => 'h2', 'text' => 'Evidence summary'],
        ['type' => 'table', 'head' => ['Ref', 'Study', 'Evidence level', 'Design and size', 'Finding'], 'widths' => [7, 36, 14, 18, 25],
            'rows' => mkt_out_evidence_rows($claim, $refs, $sources), 'empty' => 'No references cited and no library studies linked.'],
    ];
    $detail = [];
    foreach ($claim['Numbers'] as $n) {
        $ref = $refs[$n] ?? null;
        if ($ref !== null && $ref['MatchStatus'] === 'matched' && isset($sources[(int) $ref['SourceID']])) {
            $detail['Reference ' . $n] = $sources[(int) $ref['SourceID']];
        }
    }
    foreach ($claim['Direct'] as $sourceId) {
        $detail['Linked study ' . $sourceId] ??= $sources[$sourceId];
    }
    if ($detail !== []) {
        $blocks[] = ['type' => 'h2', 'text' => 'Study details'];
        foreach ($detail as $label => $src) {
            $blocks[] = ['type' => 'h3', 'text' => $label . ': ' . mkt_doc_literal((string) $src['Title'])];
            $blocks[] = ['type' => 'kv', 'rows' => mkt_out_study_rows($src)];
        }
    }
    $blocks[] = mkt_out_levels_note();

    return [
        'title'    => 'Claim evidence summary',
        'subtitle' => mkt_doc_literal($productName) . ' · ' . ucfirst((string) $claim['EvidenceTier']) . ' claim',
        'meta'     => [['Claim', '#' . (int) $claim['ClaimID']], mkt_doc_generated_meta()],
        'notice'   => MKT_DOC_INTERNAL_NOTICE,
        'filename' => 'claim-' . (int) $claim['ClaimID'] . '-evidence-' . mkt_local_date(),
        'blocks'   => $blocks,
    ];
}

function mkt_out_topic_brief(int $topicId): ?array
{
    $topic = mkt_topic_get($topicId);
    if ($topic === null) {
        return null;
    }
    $items = mkt_topic_items($topicId);
    $claims = mkt_topic_claims($topicId);
    $evidence = array_values(array_filter($items, static fn(array $i): bool => !empty($i['IsEvidence'])));
    $others = array_values(array_filter($items, static fn(array $i): bool => empty($i['IsEvidence'])));
    $lit = static fn(?string $v): string => mkt_doc_literal((string) $v);

    $blocks = [
        ['type' => 'h2', 'text' => 'Brief'],
        ['type' => 'kv', 'rows' => array_values(array_filter([
            ['Angle', $lit($topic['Angle'] ?? '') ?: '*No angle written yet*'],
            ['Audience', ['both' => 'Practitioners and consumers', 'practitioner' => 'Practitioners', 'consumer' => 'Consumers'][(string) ($topic['Audience'] ?? '')] ?? '—'],
            ['Why it matters', $lit($topic['WhyItMatters'] ?? '')],
            ['Avoid', $lit($topic['AvoidNotes'] ?? '')],
            ['Decision note', $lit($topic['DecisionNote'] ?? '')],
        ], static fn(array $r): bool => trim((string) $r[1]) !== ''))],
        ['type' => 'h2', 'text' => 'Evidence to cite (' . count($evidence) . ')'],
    ];
    if ($evidence === []) {
        $blocks[] = ['type' => 'note', 'text' => 'No items are marked as evidence on this topic yet.'];
    }
    foreach ($evidence as $item) {
        $study = mkt_lit_study($item['StudyJson'] ?? null);
        $blocks[] = ['type' => 'h3', 'text' => $lit($item['Title'])];
        if (!empty($item['AiSummary'])) {
            $blocks[] = ['type' => 'p', 'text' => $lit($item['AiSummary'])];
        }
        $rows = [['Source', $lit($item['SourceName'] ?: $item['Domain']) . ($item['PublishedAt'] ? ', ' . substr((string) $item['PublishedAt'], 0, 10) : '') . ' · [Open](' . str_replace([' ', ')'], ['%20', '%29'], (string) $item['Url']) . ')']];
        if ($study !== []) {
            $rows[] = ['Evidence level', mkt_out_level_label(mkt_lit_level($study['design'] ?? null, (string) $item['SourceType'], !empty($study['result'])))];
            foreach (['design' => 'Design', 'n' => 'Participants', 'dose' => 'Dose', 'duration' => 'Duration', 'result' => 'Result'] as $key => $label) {
                if (!empty($study[$key])) {
                    $rows[] = [$label, $lit($key === 'n' ? number_format((int) $study[$key]) : (string) $study[$key])];
                }
            }
        }
        $blocks[] = ['type' => 'kv', 'rows' => $rows];
    }
    $blocks[] = ['type' => 'h2', 'text' => 'Linked claims (' . count($claims) . ')'];
    $blocks[] = ['type' => 'table', 'head' => ['Claim', 'Product', 'Tier', 'Status'], 'widths' => [60, 15, 12, 13], 'empty' => 'No claims linked. Content on this topic may not make product or ingredient benefit claims.',
        'rows' => array_map(static fn(array $c): array => [mkt_doc_literal((string) $c['ClaimText']), mkt_doc_literal((string) $c['ProductName']), ucfirst((string) $c['EvidenceTier']), ucfirst((string) $c['Status'])], $claims)];
    $blocks[] = ['type' => 'h2', 'text' => 'Other items in the topic (' . count($others) . ')'];
    $blocks[] = ['type' => 'table', 'head' => ['Item', 'Source', 'Type', 'Published'], 'widths' => [58, 18, 12, 12], 'empty' => 'None.',
        'rows' => array_map(static fn(array $i): array => [
            '[' . mkt_doc_literal((string) $i['Title']) . '](' . str_replace([' ', ')'], ['%20', '%29'], (string) $i['Url']) . ')',
            mkt_doc_literal((string) ($i['SourceName'] ?: $i['Domain'])),
            mkt_doc_literal(ucwords(str_replace('_', ' ', (string) ($i['EvidenceType'] ?: $i['SourceType'])))),
            $i['PublishedAt'] ? substr((string) $i['PublishedAt'], 0, 10) : '—',
        ], $others)];

    return [
        'title'    => 'Topic brief: ' . (string) $topic['Title'],
        'subtitle' => mkt_doc_literal((string) ($topic['Summary'] ?? '')),
        'meta'     => [
            ['Status', ucfirst((string) $topic['Status'])],
            ['Interest', mkt_doc_literal((string) ($topic['InterestName'] ?? '—'))],
            ['Therapeutic area', mkt_doc_literal((string) ($topic['TherapeuticArea'] ?? '—'))],
            ['Items', count($items) . ' (' . count($evidence) . ' evidence)'],
            mkt_doc_generated_meta(),
        ],
        'notice'   => MKT_DOC_INTERNAL_NOTICE,
        'filename' => 'topic-brief-' . $topicId . '-' . mkt_local_date(),
        'blocks'   => $blocks,
    ];
}

function mkt_out_bibliography(array $filters): array
{
    $rows = mkt_lit_sources($filters);
    $describe = array_values(array_filter([
        ($filters['product'] ?? '') !== '' ? mkt_doc_literal((string) $filters['product']) : null,
        ($filters['area'] ?? '') !== '' ? mkt_doc_literal((string) $filters['area']) : null,
        ($filters['level'] ?? '') !== '' ? mkt_out_level_label((string) $filters['level']) : null,
    ]));
    $blocks = [];
    if ($rows === []) {
        $blocks[] = ['type' => 'note', 'text' => 'No library sources match. Studies are added to the library from Literature & Intelligence.'];
    }
    foreach (MKT_LIT_LEVELS as $level => $label) {
        $group = array_values(array_filter($rows, static fn(array $r): bool => $r['Level'] === $level));
        if ($group === []) {
            continue;
        }
        $blocks[] = ['type' => 'h2', 'text' => $label . ' (' . count($group) . ')'];
        $blocks[] = ['type' => 'bullets', 'ordered' => true, 'items' => array_map(static function (array $r): string {
            $study = mkt_lit_study($r['EffectiveStudyJson']);
            $line = mkt_doc_literal(mkt_lit_citation($r));
            $links = mkt_out_links($r);
            $facts = mkt_out_study_line($study);

            return $line . ($links !== '' ? ' ' . $links : '') . ($facts !== '' ? ' — ' . $facts : '')
                . (!empty($r['Takeaway']) ? "\n*" . mkt_doc_literal((string) $r['Takeaway']) . '*' : '');
        }, $group)];
    }
    $blocks[] = mkt_out_levels_note();

    return [
        'title'    => 'Evidence library bibliography',
        'subtitle' => $describe !== [] ? implode(' · ', $describe) : 'All library sources',
        'meta'     => [['Sources', (string) count($rows)], mkt_doc_generated_meta()],
        'notice'   => MKT_DOC_INTERNAL_NOTICE,
        'filename' => 'bibliography-' . ($describe !== [] ? implode('-', $describe) . '-' : '') . mkt_local_date(),
        'blocks'   => $blocks,
    ];
}

function mkt_out_article(int $contentId): ?array
{
    $piece = mkt_content_get($contentId);
    if ($piece === null || empty($piece['CurrentVersionID'])) {
        return null;
    }
    $version = mkt_content_version_get((int) $piece['CurrentVersionID'], $contentId);
    if ($version === null) {
        return null;
    }
    $stage = (string) $piece['Stage'];
    $approved = in_array($stage, ['approved', 'published', 'monitoring'], true);
    $metaTitle = (string) ($version['MetaTitle'] ?? '');
    $metaDesc = (string) ($version['MetaDescription'] ?? '');
    $blocks = [
        ['type' => 'p', 'text' => $approved
            ? '**Approved to publish** — version ' . (int) $version['VersionNo'] . '. Publish it exactly as written; any change goes back through review.'
            : '**Not approved — do not publish.** This is version ' . (int) $version['VersionNo'] . ' at the ' . (MKT_CONTENT_STAGES[$stage] ?? $stage) . ' stage.'],
        ['type' => 'kv', 'rows' => array_values(array_filter([
            ['Meta title', mkt_doc_literal($metaTitle) . ($metaTitle !== '' ? ' *(' . mb_strlen($metaTitle) . ' of ' . MKT_CONTENT_META_TITLE_MAX . ' characters)*' : '')],
            ['Meta description', mkt_doc_literal($metaDesc) . ($metaDesc !== '' ? ' *(' . mb_strlen($metaDesc) . ' characters)*' : '')],
            ['Target keyword', mkt_doc_literal((string) ($piece['PrimaryKeyword'] ?? ''))],
            ['Page address', mkt_doc_literal((string) ($piece['PublishedUrl'] ?: ($piece['TargetUrl'] ?? '')))],
        ], static fn(array $r): bool => trim((string) $r[1]) !== ''))],
        ['type' => 'h1', 'text' => mkt_doc_literal((string) $version['Title'])],
        ['type' => 'markdown', 'text' => (string) preg_replace('/^\s*#\s+' . preg_quote(trim((string) $version['Title']), '/') . '\s*\R/u', '', (string) $version['Body'])],
    ];

    return [
        'title'    => (string) $version['Title'],
        'subtitle' => ($approved ? 'Approved' : 'Not approved — do not publish') . ' · ' . mkt_doc_literal(ucwords(str_replace('_', ' ', (string) $piece['ContentType']))),
        'meta'     => [['Version', (string) (int) $version['VersionNo']], ['Words', number_format((int) ($version['WordCount'] ?? 0))], ['Stage', MKT_CONTENT_STAGES[$stage] ?? $stage], mkt_doc_generated_meta()],
        'notice'   => $approved ? 'Approved version ' . (int) $version['VersionNo'] . ' from the NutraAxis Content Pipeline.' : 'Not approved — do not publish. Draft from the NutraAxis Content Pipeline.',
        'filename' => 'article-' . $contentId . '-v' . (int) $version['VersionNo'],
        'blocks'   => $blocks,
    ];
}

/** Build a document from request parameters, or null with an error message. */
function mkt_out_build(string $type, array $params): array
{
    $doc = match ($type) {
        'evidence-pack' => mkt_out_evidence_pack((int) ($params['product_id'] ?? 0)),
        'claim'         => mkt_out_claim_summary((int) ($params['claim_id'] ?? 0)),
        'topic'         => mkt_out_topic_brief((int) ($params['topic_id'] ?? 0)),
        'bibliography'  => mkt_out_bibliography([
            'product' => (string) ($params['product'] ?? ''),
            'area'    => (string) ($params['area'] ?? ''),
            'level'   => array_key_exists((string) ($params['level'] ?? ''), MKT_LIT_LEVELS) ? (string) $params['level'] : '',
        ]),
        'article'       => mkt_out_article((int) ($params['content_id'] ?? 0)),
        default         => null,
    };

    return $doc === null ? ['doc' => null, 'error' => isset(MKT_OUTPUT_TYPES[$type]) ? 'Nothing found for that choice.' : 'Unknown document type.'] : ['doc' => $doc, 'error' => null];
}
