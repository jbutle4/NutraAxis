<?php

require_once __DIR__ . '/marketing-intake.php';

const MKT_CLAIM_TYPES = [
    'headline'   => 'Headline',
    'benefit'    => 'Benefit claim',
    'mechanism'  => 'Mechanism',
    'ingredient' => 'Ingredient',
    'general'    => 'General / formula',
];
const MKT_EVIDENCE_TIERS = [
    'clinical'    => 'Clinical (human studies)',
    'mechanistic' => 'Mechanistic',
    'traditional' => 'Traditional use',
    'label'       => 'Label / formula fact',
];
const MKT_CLAIM_AUDIENCES = ['both' => 'Both', 'practitioner' => 'Practitioner', 'consumer' => 'Consumer'];
const MKT_CLAIM_STATUSES = ['draft' => 'Draft', 'approved' => 'Approved', 'retired' => 'Retired'];

/* ---------- Products ---------- */

function mkt_products_list(?string $status = null): array
{
    $sql = <<<SQL
        SELECT p.*,
               (SELECT COUNT(*) FROM dbo.MktClaim c WHERE c.ProductID = p.ProductID AND c.Status = N'approved') AS ApprovedClaims,
               (SELECT COUNT(*) FROM dbo.MktClaim c WHERE c.ProductID = p.ProductID AND c.Status = N'draft') AS DraftClaims
        FROM dbo.MktProduct p
    SQL;
    $params = [];
    if ($status !== null && $status !== '') {
        $sql .= ' WHERE p.Status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY p.TherapeuticArea, p.Name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_product_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktProduct WHERE ProductID = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

function mkt_product_find(string $name): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktProduct WHERE Name = :n');
    $stmt->execute(['n' => $name]);

    return $stmt->fetch() ?: null;
}

function mkt_product_save(array $input, ?int $id = null): array
{
    $text = static fn(string $key, int $max): ?string => ($v = trim(str_replace("\r\n", "\n", (string) ($input[$key] ?? '')))) === '' ? null : mb_substr($v, 0, $max);
    $data = [
        'Name'            => mb_substr(trim((string) ($input['name'] ?? '')), 0, 100),
        'TherapeuticArea' => $text('therapeutic_area', 100),
        'Headline'        => $text('headline', 300),
        'Summary'         => $text('summary', 2000),
        'Formula'         => $text('formula', 2000),
        'SuggestedUse'    => $text('suggested_use', 1000),
        'IntendedUse'     => $text('intended_use', 2000),
        'ReferenceList'   => $text('reference_list', 100000),
        'SourceDocument'  => $text('source_document', 200),
        'Notes'           => $text('notes', 2000),
        'Status'          => array_key_exists((string) ($input['status'] ?? ''), MKT_RECORD_STATUSES) ? (string) $input['status'] : 'active',
        'UpdatedBy'       => marketing_user_id(),
    ];
    if ($data['Name'] === '') {
        return ['ok' => false, 'error' => 'Product name is required.'];
    }
    $pdo = db();
    $dup = $pdo->prepare('SELECT ProductID FROM dbo.MktProduct WHERE Name = :n AND (:id1 IS NULL OR ProductID <> :id2)');
    $dup->execute(['n' => $data['Name'], 'id1' => $id, 'id2' => $id]);
    if ($dup->fetch()) {
        return ['ok' => false, 'error' => 'A product with that name already exists.'];
    }

    if ($id === null) {
        $cols = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO dbo.MktProduct (' . implode(', ', $cols) . ') OUTPUT INSERTED.ProductID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
        $stmt->execute($data);
        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $built = audit_build_insert('MktProduct', $data, 'ProductID', $id);
    } else {
        $before = mkt_product_get($id) ?? [];
        $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
        $pdo->prepare("UPDATE dbo.MktProduct SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE ProductID = :id")->execute($data + ['id' => $id]);
        $built = audit_build_update('MktProduct', 'ProductID', $id, $data, array_intersect_key($before, $data));
    }
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'id' => $id];
}

/* ---------- Claims ---------- */

function mkt_claims_list(array $filters = []): array
{
    $sql = <<<SQL
        SELECT c.*, p.Name AS ProductName, p.TherapeuticArea
        FROM dbo.MktClaim c
        LEFT JOIN dbo.MktProduct p ON p.ProductID = c.ProductID
        WHERE 1 = 1
    SQL;
    $params = [];
    if (!empty($filters['product_id'])) {
        if ($filters['product_id'] === 'general') {
            $sql .= ' AND c.ProductID IS NULL';
        } else {
            $sql .= ' AND c.ProductID = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }
    }
    foreach (['area' => 'p.TherapeuticArea', 'type' => 'c.ClaimType', 'status' => 'c.Status', 'evidence' => 'c.EvidenceTier'] as $key => $col) {
        if (!empty($filters[$key])) {
            $sql .= " AND $col = :$key";
            $params[$key] = (string) $filters[$key];
        }
    }
    if (!empty($filters['q'])) {
        [$like, $likeParams] = db_like_or(['c.ClaimText', 'c.Ingredient', 'c.Notes', 'p.Name'], (string) $filters['q']);
        $sql .= ' AND ' . $like;
        $params += $likeParams;
    }
    $sql .= " ORDER BY CASE WHEN p.Name IS NULL THEN 1 ELSE 0 END, p.Name,
              CASE c.ClaimType WHEN N'headline' THEN 0 WHEN N'benefit' THEN 1 WHEN N'mechanism' THEN 2 WHEN N'ingredient' THEN 3 ELSE 4 END,
              c.SortOrder, c.ClaimID";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function mkt_claim_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM dbo.MktClaim WHERE ClaimID = :id');
    $stmt->execute(['id' => $id]);

    return $stmt->fetch() ?: null;
}

/**
 * Editors can draft or retire; approval goes through mkt_claim_approve(). Changing the
 * wording or evidence of an approved claim returns it to draft for re-approval.
 */
function mkt_claim_save(array $input, ?int $id = null): array
{
    $productId = (int) ($input['product_id'] ?? 0) ?: null;
    $data = [
        'ProductID'          => $productId,
        'ClaimText'          => mb_substr(trim(str_replace("\r\n", "\n", (string) ($input['claim_text'] ?? ''))), 0, 1000),
        'ClaimType'          => array_key_exists((string) ($input['claim_type'] ?? ''), MKT_CLAIM_TYPES) ? (string) $input['claim_type'] : 'benefit',
        'Ingredient'         => trim((string) ($input['ingredient'] ?? '')) !== '' ? mb_substr(trim((string) $input['ingredient']), 0, 150) : null,
        'EvidenceTier'       => array_key_exists((string) ($input['evidence_tier'] ?? ''), MKT_EVIDENCE_TIERS) ? (string) $input['evidence_tier'] : 'clinical',
        'ReferenceNumbers'   => trim((string) ($input['reference_numbers'] ?? '')) !== '' ? mb_substr(trim((string) $input['reference_numbers']), 0, 100) : null,
        'Audience'           => array_key_exists((string) ($input['audience'] ?? ''), MKT_CLAIM_AUDIENCES) ? (string) $input['audience'] : 'both',
        'RequiresDisclaimer' => !empty($input['requires_disclaimer']) ? 1 : 0,
        'SortOrder'          => (int) ($input['sort_order'] ?? 100) ?: 100,
        'SourceDocument'     => trim((string) ($input['source_document'] ?? '')) !== '' ? mb_substr(trim((string) $input['source_document']), 0, 200) : null,
        'Notes'              => trim((string) ($input['notes'] ?? '')) !== '' ? mb_substr(trim((string) $input['notes']), 0, 1000) : null,
        'UpdatedBy'          => marketing_user_id(),
    ];
    if ($data['ClaimText'] === '') {
        return ['ok' => false, 'error' => 'Claim wording is required.'];
    }
    if ($productId !== null && mkt_product_get($productId) === null) {
        return ['ok' => false, 'error' => 'Choose a valid product.'];
    }

    $requested = (string) ($input['status'] ?? 'draft');
    $before = $id !== null ? mkt_claim_get($id) : null;
    if ($id !== null && $before === null) {
        return ['ok' => false, 'error' => 'Claim not found.'];
    }
    $status = $requested === 'retired' ? 'retired' : 'draft';
    if ($before !== null && $before['Status'] === 'approved' && $requested !== 'retired') {
        $material = ['ProductID', 'ClaimText', 'ClaimType', 'Ingredient', 'EvidenceTier', 'ReferenceNumbers', 'Audience', 'RequiresDisclaimer'];
        $changed = false;
        foreach ($material as $col) {
            if ((string) ($before[$col] ?? '') !== (string) ($data[$col] ?? '')) {
                $changed = true;
                break;
            }
        }
        $status = $changed ? 'draft' : 'approved';
    }
    $data['Status'] = $status;
    if ($status !== 'approved') {
        $data['ApprovedBy'] = null;
        $data['ApprovedAt'] = null;
    }

    $pdo = db();
    if ($id === null) {
        $cols = array_keys($data);
        $stmt = $pdo->prepare('INSERT INTO dbo.MktClaim (' . implode(', ', $cols) . ') OUTPUT INSERTED.ClaimID AS inserted_id VALUES (:' . implode(', :', $cols) . ')');
        $stmt->execute($data);
        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $built = audit_build_insert('MktClaim', $data, 'ClaimID', $id);
    } else {
        $sets = implode(', ', array_map(static fn(string $c): string => "$c = :$c", array_keys($data)));
        $pdo->prepare("UPDATE dbo.MktClaim SET $sets, UpdatedAt = SYSUTCDATETIME() WHERE ClaimID = :id")->execute($data + ['id' => $id]);
        $built = audit_build_update('MktClaim', 'ClaimID', $id, $data, array_intersect_key($before, $data));
    }
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true, 'id' => $id, 'status' => $status, 'reapproval' => $before !== null && $before['Status'] === 'approved' && $status === 'draft'];
}

/**
 * Approval requires full Marketing admin access, and the approver cannot be the last editor.
 */
function mkt_claim_approve(int $id): array
{
    $claim = mkt_claim_get($id);
    if ($claim === null) {
        return ['ok' => false, 'error' => 'Claim not found.'];
    }
    if ($claim['Status'] !== 'draft') {
        return ['ok' => false, 'error' => 'Only draft claims can be approved.'];
    }
    $userId = marketing_user_id();
    if ($userId !== null && (int) ($claim['UpdatedBy'] ?? 0) === $userId) {
        return ['ok' => false, 'error' => 'You edited this claim last — another approver must approve it.'];
    }
    db()->prepare("UPDATE dbo.MktClaim SET Status = N'approved', ApprovedBy = :u, ApprovedAt = SYSUTCDATETIME(), UpdatedAt = SYSUTCDATETIME() WHERE ClaimID = :id")
        ->execute(['u' => $userId, 'id' => $id]);
    $built = audit_build_update('MktClaim', 'ClaimID', $id, ['Status' => 'approved', 'ApprovedBy' => $userId], ['Status' => $claim['Status'], 'ApprovedBy' => $claim['ApprovedBy']]);
    audit_log_change($built['change'], $built['reverse']);

    return ['ok' => true];
}

/**
 * Approved wording used as the allowed-claims context for generation and the claims check.
 *
 * @return list<array{ClaimText: string, ClaimType: string, ProductName: ?string, Ingredient: ?string, EvidenceTier: string}>
 */
function mkt_claims_approved(?int $productId = null): array
{
    return mkt_claims_list(['status' => 'approved'] + ($productId !== null ? ['product_id' => $productId] : []));
}

function mkt_claims_flag_terms(): array
{
    return marketing_setting_lines('claims.flag_terms');
}
