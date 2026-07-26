<?php
/**
 * One-shot sandbox smoke for inventory adjustments.
 * Invoke: /scripts/smoke-inventory-adjustment.php?key=...
 * Deletes nothing; safe to leave (gated by SMOKE_KEY / NUTRA_SMOKE_KEY).
 */
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/inventory-adjustments.php';

header('Content-Type: application/json');

$expected = trim((string) (env('SMOKE_KEY', env('NUTRA_SMOKE_KEY', ''))));
$provided = trim((string) ($_GET['key'] ?? ''));
if ($expected === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$pdo = db();
$userStmt = $pdo->query(<<<SQL
    SELECT TOP (1) u.UserID, u.UserName, u.UserLogin, u.UserAssignedRole, r.RoleName
    FROM dbo.[User] u
    INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
    ORDER BY u.UserID
SQL);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRow) {
    echo json_encode(['ok' => false, 'error' => 'No user available for smoke auth.']);
    exit;
}
$userId = (int) $userRow['UserID'];

auth_start_session();
$_SESSION[AUTH_SESSION_KEY] = [
    'UserID' => $userId,
    'UserName' => (string) $userRow['UserName'],
    'UserLogin' => (string) $userRow['UserLogin'],
    'UserAssignedRole' => (int) $userRow['UserAssignedRole'],
    'RoleName' => (string) $userRow['RoleName'],
    'permissions' => ['InventoryReporting' => 'CRUD'],
];

$reasonStmt = $pdo->query(<<<SQL
    SELECT TOP (1) ReasonCodeID FROM dbo.InvReasonCode
    WHERE ReasonCode = N'DAMAGE' AND AppliesToAdjustment = 1
SQL);
$reasonId = (int) ($reasonStmt->fetchColumn() ?: 0);
if ($reasonId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'DAMAGE reason code missing']);
    exit;
}

$before = $pdo->prepare(<<<SQL
    SELECT QtyOK FROM dbo.InvCurrentBalance
    WHERE SKUCode = N'NA-MT-004' AND FacilityCode = N'CART'
SQL);
$before->execute();
$qtyBefore = (float) ($before->fetchColumn() ?: 0);

$create = inventory_adjustments_create([
    'sku_code' => 'NA-MT-004',
    'facility_code' => 'CART',
    'status_bucket' => 'OK',
    'reason_code_id' => $reasonId,
    'direction' => '-',
    'qty' => 1,
    'notes' => 'Sandbox smoke shrink via smoke-inventory-adjustment.php',
]);
if (!$create['ok']) {
    echo json_encode(['ok' => false, 'phase' => 'create', 'error' => $create['error']]);
    exit;
}

$adjustmentId = (int) $create['adjustment_id'];
$approve = inventory_adjustments_approve($adjustmentId, $userId);

$after = $pdo->prepare(<<<SQL
    SELECT QtyOK FROM dbo.InvCurrentBalance
    WHERE SKUCode = N'NA-MT-004' AND FacilityCode = N'CART'
SQL);
$after->execute();
$qtyAfter = (float) ($after->fetchColumn() ?: 0);

$doc = inventory_adjustments_doc_number($adjustmentId);
$sync = $pdo->prepare('SELECT SyncStatus, QBO_TxnId, SyncError FROM dbo.QBOInventorySyncLog WHERE DocNumber = :doc');
$sync->execute(['doc' => $doc]);
$syncRow = $sync->fetch(PDO::FETCH_ASSOC) ?: null;

$adj = inventory_adjustments_get($adjustmentId);

echo json_encode([
    'ok' => (bool) ($approve['ok'] ?? false),
    'adjustment_id' => $adjustmentId,
    'approve' => $approve,
    'qty_before' => $qtyBefore,
    'qty_after' => $qtyAfter,
    'adjustment' => [
        'AdjStatus' => $adj['AdjStatus'] ?? null,
        'TransactionID' => $adj['TransactionID'] ?? null,
        'QtyAdjusted' => $adj['QtyAdjusted'] ?? null,
    ],
    'qbo_doc' => $doc,
    'sync' => $syncRow,
], JSON_PRETTY_PRINT);
