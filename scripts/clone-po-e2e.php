#!/usr/bin/env php
<?php
/**
 * Clone a purchase order for procurement E2E validation.
 *
 * Usage:
 *   php scripts/clone-po-e2e.php [source_poid] [--po-number=NS20251111-UAT-E2E]
 *
 * Creates a new UAT PO in Created status (same supplier/lines as source).
 */

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po.php';

$sourcePoId = 20;
$customPoNumber = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--po-number=')) {
        $customPoNumber = trim(substr($arg, strlen('--po-number=')));
        continue;
    }
    if (ctype_digit($arg)) {
        $sourcePoId = (int) $arg;
    }
}

$pdo = db();
$userStmt = $pdo->query(<<<SQL
    SELECT TOP (1)
        u.UserID,
        u.UserName,
        u.UserLogin,
        u.UserAssignedRole,
        r.RoleName,
        r.POManagement,
        r.POApproval,
        r.InventoryReporting,
        r.SalesReporting,
        r.InventoryForecasting,
        r.LabelingOperations,
        r.OperationsDashboard,
        r.LegalAgreements,
        r.ProductCatalog,
        r.LinksIndex,
        r.ContactsList,
        r.Support,
        r.Accounting,
        r.UserAdmin,
        r.RoleAdmin,
        r.TEManagement,
        r.TEApproval,
        r.TEProcessing,
        r.QBOInsertApproval,
        r.PaymentApproval,
        r.ProviderAccountReview
    FROM dbo.[User] u
    INNER JOIN dbo.Role r ON r.RoleID = u.UserAssignedRole
    WHERE r.POManagement IN ('CRUD', 'CRU', 'CR')
    ORDER BY u.UserID
SQL);
$userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$userRow) {
    fwrite(STDERR, "No user with POManagement create permission found.\n");
    exit(1);
}

auth_start_session();
$_SESSION[AUTH_SESSION_KEY] = [
    'UserID'            => (int) $userRow['UserID'],
    'UserName'          => (string) $userRow['UserName'],
    'UserLogin'         => (string) $userRow['UserLogin'],
    'UserAssignedRole'  => (int) $userRow['UserAssignedRole'],
    'RoleName'          => (string) $userRow['RoleName'],
    'permissions'       => auth_permissions_from_role_row($userRow),
];

$duplicate = po_duplicate_form_from_order($sourcePoId);
if (!$duplicate['ok']) {
    fwrite(STDERR, $duplicate['error'] . "\n");
    exit(1);
}

$form = $duplicate['form'];
$lines = $duplicate['lines'];
$form['po_number'] = $customPoNumber !== null && $customPoNumber !== ''
    ? $customPoNumber
    : sprintf('NS20251111-UAT-E2E-%s', date('Ymd-His'));
$form['notes'] = trim(($form['notes'] ?? '') . "\n\nCloned from PO {$sourcePoId} ({$duplicate['source_po_number']}) for procurement E2E validation.");

$input = array_merge($form, ['lines' => $lines]);
$result = po_save_order($input);

if (!$result['ok']) {
    fwrite(STDERR, $result['error'] . "\n");
    exit(1);
}

$newPoId = (int) $result['id'];
$order = po_get_order($newPoId);

echo json_encode([
    'ok'               => true,
    'source_poid'      => $sourcePoId,
    'source_po_number' => $duplicate['source_po_number'],
    'new_poid'         => $newPoId,
    'new_po_number'    => (string) ($order['PONumber'] ?? ''),
    'ledger_profile'   => po_order_ledger_profile($order ?? []),
    'status'           => (string) ($order['POStatus'] ?? ''),
    'line_count'       => count(po_get_lines($newPoId)),
    'total_due'        => (float) ($order['TotalDue'] ?? 0),
    'view_url'         => '/po-management/view.php?id=' . $newPoId,
    'submit_url'       => '/po-management/view.php?id=' . $newPoId,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
