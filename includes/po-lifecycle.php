<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/po-approval.php';

const PO_LIFECYCLE_STEPS = [
    'created'     => 'PO Created',
    'submitted'   => 'PO Submitted for Approval',
    'approved'    => 'PO Approved',
    'qbo'         => 'PO Sent to QuickBooks',
    'receivers'   => 'PO Receivers Created',
    'asns'        => 'PO ASNs Transmitted',
    'payments'    => 'Payment Requests Created',
    'closed'      => 'PO Closed',
];

function po_lifecycle_requires_receiving(array $order): bool
{
    $type = trim((string) ($order['SupplierType'] ?? ''));

    if ($type === '') {
        return true;
    }

    return strcasecmp($type, 'CMO') === 0;
}

function po_lifecycle_format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return '—';
    }
}

function po_lifecycle_first_status_dates(int $poId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT ChangeDate, ChangeSQL
        FROM dbo.AuditChangeLog
        WHERE RolledBackDate IS NULL
          AND ChangeSQL LIKE '%[PurchaseOrder]%'
          AND ChangeSQL LIKE '%[POStatus]%'
          AND (
              ChangeSQL LIKE :po_id_eq
              OR ChangeSQL LIKE :po_id_literal
          )
        ORDER BY ChangeDate ASC
    SQL);
    $stmt->execute([
        'po_id_eq'      => '%POID = ' . $poId . '%',
        'po_id_literal' => '%POID = N\'' . $poId . '\'%',
    ]);

    $dates = [];
    foreach ($stmt->fetchAll() as $row) {
        $sql = (string) ($row['ChangeSQL'] ?? '');
        if (!preg_match('/\[POStatus\]\s*=\s*N\'([^\']*)\'/u', $sql, $matches)) {
            continue;
        }

        $status = (string) $matches[1];
        if (!isset($dates[$status])) {
            $dates[$status] = (string) $row['ChangeDate'];
        }
    }

    return $dates;
}

function po_lifecycle_first_approval_date(int $poId): ?string
{
    require_once __DIR__ . '/approval.php';

    return approval_first_result_date('PO', $poId, 'Approved');
}

function po_lifecycle_qbo_sent_date(int $poId, array $order): ?string
{
    if (empty($order['QBO_POID']) && empty($order['POQBOCreated'])) {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT MIN(ChangeDate) AS first_date
        FROM dbo.AuditChangeLog
        WHERE RolledBackDate IS NULL
          AND ChangeSQL LIKE '%[PurchaseOrder]%'
          AND (
              ChangeSQL LIKE '%[QBO_POID]%'
              OR ChangeSQL LIKE '%[POQBOCreated]%'
          )
          AND (
              ChangeSQL LIKE :po_id_eq
              OR ChangeSQL LIKE :po_id_literal
          )
    SQL);
    $stmt->execute([
        'po_id_eq'      => '%POID = ' . $poId . '%',
        'po_id_literal' => '%POID = N\'' . $poId . '\'%',
    ]);
    $value = $stmt->fetchColumn();
    if ($value !== false && $value !== null && $value !== '') {
        return (string) $value;
    }

    $modified = trim((string) ($order['ModifiedDate'] ?? ''));

    return $modified !== '' ? $modified : null;
}

function po_lifecycle_first_receipt_date(int $poId): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT MIN(CreateDate) FROM dbo.POReceipt WHERE POID = :po_id');
    $stmt->execute(['po_id' => $poId]);
    $value = $stmt->fetchColumn();

    return ($value === false || $value === null || $value === '') ? null : (string) $value;
}

function po_lifecycle_first_asn_date(int $poId): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT MIN(COALESCE(r.JazzASNModifiedDate, r.ModifiedDate, r.CreateDate)) AS first_date
        FROM dbo.POReceipt r
        WHERE r.POID = :po_id
          AND (
              NULLIF(LTRIM(RTRIM(r.JazzASN)), '') IS NOT NULL
              OR r.PORStatus = N'Transmitted'
          )
    SQL);
    $stmt->execute(['po_id' => $poId]);
    $value = $stmt->fetchColumn();

    return ($value === false || $value === null || $value === '') ? null : (string) $value;
}

function po_lifecycle_first_payment_date(int $poId): ?string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT MIN(CreateDate) FROM dbo.POPayment WHERE POID = :po_id');
    $stmt->execute(['po_id' => $poId]);
    $value = $stmt->fetchColumn();

    return ($value === false || $value === null || $value === '') ? null : (string) $value;
}

function po_lifecycle_timeline(int $poId, array $order): array
{
    $requiresReceiving = po_lifecycle_requires_receiving($order);
    $statusDates = po_lifecycle_first_status_dates($poId);

    $createdDate = trim((string) ($order['CreateDate'] ?? ''));
    $submittedDate = $statusDates[PO_STATUS_SUBMITTED] ?? null;

    $approvedDate = po_lifecycle_first_approval_date($poId);
    $approvedStatusDate = $statusDates[PO_STATUS_APPROVED] ?? null;
    if ($approvedDate !== null && $approvedStatusDate !== null) {
        $approvedDate = min($approvedDate, $approvedStatusDate);
    } elseif ($approvedStatusDate !== null) {
        $approvedDate = $approvedStatusDate;
    } elseif ($approvedDate === null && po_is_legacy_accounting_po_status((string) ($order['POStatus'] ?? ''))) {
        $legacyDate = $statusDates[PO_STATUS_ACCOUNTING] ?? null;
        $approvedDate = $legacyDate ?? (($order['ModifiedDate'] ?? '') !== '' ? (string) $order['ModifiedDate'] : null);
    }

    $qboDate = po_lifecycle_qbo_sent_date($poId, $order);
    $receiverDate = $requiresReceiving ? po_lifecycle_first_receipt_date($poId) : null;
    $asnDate = $requiresReceiving ? po_lifecycle_first_asn_date($poId) : null;
    $paymentDate = po_lifecycle_first_payment_date($poId);
    $closedDate = $statusDates[PO_STATUS_PAID] ?? null;
    if ($closedDate === null && (string) ($order['POStatus'] ?? '') === PO_STATUS_PAID) {
        $closedDate = ($order['ModifiedDate'] ?? '') !== '' ? (string) $order['ModifiedDate'] : null;
    }

    $steps = [
        'created'   => ['label' => PO_LIFECYCLE_STEPS['created'], 'date' => $createdDate !== '' ? $createdDate : null, 'applicable' => true],
        'submitted' => ['label' => PO_LIFECYCLE_STEPS['submitted'], 'date' => $submittedDate, 'applicable' => true],
        'approved'  => ['label' => PO_LIFECYCLE_STEPS['approved'], 'date' => $approvedDate, 'applicable' => true],
        'qbo'       => ['label' => PO_LIFECYCLE_STEPS['qbo'], 'date' => $qboDate, 'applicable' => true],
        'receivers' => ['label' => PO_LIFECYCLE_STEPS['receivers'], 'date' => $receiverDate, 'applicable' => $requiresReceiving],
        'asns'      => ['label' => PO_LIFECYCLE_STEPS['asns'], 'date' => $asnDate, 'applicable' => $requiresReceiving],
        'payments'  => ['label' => PO_LIFECYCLE_STEPS['payments'], 'date' => $paymentDate, 'applicable' => true],
        'closed'    => ['label' => PO_LIFECYCLE_STEPS['closed'], 'date' => $closedDate, 'applicable' => true],
    ];

    foreach ($steps as $key => $step) {
        $steps[$key]['complete'] = $step['date'] !== null && $step['date'] !== '';
    }

    return $steps;
}
