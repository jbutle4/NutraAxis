<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/po.php';

const PO_PAYMENT_TYPES = ['Check', 'ACH', 'CC'];

const PO_PAYMENT_LIST_SORT_COLUMNS = [
    'payment_date'   => 'Payment date',
    'po_number'      => 'PO number',
    'supplier'       => 'Supplier',
    'amount'         => 'Amount',
    'type'           => 'Type',
    'confirmation'   => 'Confirmation #',
    'made_by'        => 'Made by',
];

const PO_PAYMENT_LIST_SORT_SQL = [
    'payment_date' => 'p.PaymentDate',
    'po_number'    => 'po.PONumber',
    'supplier'     => 's.SupplierName',
    'amount'       => 'p.PaymentAmount',
    'type'         => 'p.PaymentType',
    'confirmation' => 'p.PaymentConfNumber',
    'made_by'      => 'p.PaymentMadeBy',
];

const PO_PAYMENT_LIST_SORT_NUMERIC = ['amount'];

function po_payment_can_read(): bool
{
    return po_can_access_po_pages();
}

function po_payment_can_create(): bool
{
    return po_can_update();
}

function po_payment_can_update(): bool
{
    return po_can_update();
}

function po_payment_can_delete(): bool
{
    return po_can_delete();
}

function po_payment_require_read(): void
{
    po_require_read();
}

function po_payment_require_create(): void
{
    po_require_update();
}

function po_payment_require_update(): void
{
    po_require_update();
}

function po_payment_require_delete(): void
{
    po_require_delete();
}

function po_payment_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y g:i A');
    } catch (Throwable) {
        return $value;
    }
}

function po_payment_datetime_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function po_payment_to_form(array $payment): array
{
    return [
        'payment_id'          => (int) $payment['PaymentID'],
        'po_id'               => (int) $payment['POID'],
        'payment_date'        => po_payment_datetime_input($payment['PaymentDate'] ?? null),
        'payment_amount'      => (string) $payment['PaymentAmount'],
        'payment_type'        => (string) $payment['PaymentType'],
        'payment_conf_number' => (string) ($payment['PaymentConfNumber'] ?? ''),
        'payment_made_by'     => (string) ($payment['PaymentMadeBy'] ?? ''),
        'payment_comments'    => (string) ($payment['PaymentComments'] ?? ''),
    ];
}

function po_payment_from_input(array $input): array
{
    return [
        'po_id'               => trim($input['po_id'] ?? ''),
        'payment_date'        => trim($input['payment_date'] ?? ''),
        'payment_amount'      => trim($input['payment_amount'] ?? ''),
        'payment_type'        => trim($input['payment_type'] ?? ''),
        'payment_conf_number' => trim($input['payment_conf_number'] ?? ''),
        'payment_made_by'     => trim($input['payment_made_by'] ?? ''),
        'payment_comments'    => trim($input['payment_comments'] ?? ''),
    ];
}

function po_payment_parse_datetime(string $value): ?string
{
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        $ts = strtotime($value);

        return $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
    }
}

function po_payment_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            p.PaymentID,
            p.POID,
            p.PaymentDate,
            p.PaymentAmount,
            p.PaymentType,
            p.PaymentConfNumber,
            p.PaymentMadeBy,
            po.PONumber,
            s.SupplierName
        FROM dbo.POPayment p
        INNER JOIN dbo.PurchaseOrder po ON po.POID = p.POID
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['po_id'])) {
        $sql .= ' AND p.POID = :po_id';
        $params['po_id'] = (int) $filters['po_id'];
    }

    if (!empty($filters['type'])) {
        $sql .= ' AND p.PaymentType = :type';
        $params['type'] = $filters['type'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            po.PONumber LIKE :q OR
            s.SupplierName LIKE :q OR
            p.PaymentConfNumber LIKE :q OR
            p.PaymentMadeBy LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(PO_PAYMENT_LIST_SORT_COLUMNS, 'payment_date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(PO_PAYMENT_LIST_SORT_SQL, $sortState, 'payment_date', 'po_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function po_payment_list_for_po(int $poId): array
{
    return po_payment_list(['po_id' => $poId]);
}

function po_payment_total_for_po(int $poId): float
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT ISNULL(SUM(PaymentAmount), 0) FROM dbo.POPayment WHERE POID = :po_id');
    $stmt->execute(['po_id' => $poId]);

    return (float) $stmt->fetchColumn();
}

function po_payment_get(int $paymentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            p.*,
            po.PONumber,
            s.SupplierName
        FROM dbo.POPayment p
        INNER JOIN dbo.PurchaseOrder po ON po.POID = p.POID
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        WHERE p.PaymentID = :id
    SQL);
    $stmt->execute(['id' => $paymentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function po_payment_po_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT po.POID, po.PONumber, s.SupplierName, po.POStatus
        FROM dbo.PurchaseOrder po
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        ORDER BY po.OrderDate DESC, po.POID DESC
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $options[] = [
            'id'    => (int) $row['POID'],
            'label' => $row['PONumber'] . ' · ' . $row['SupplierName'] . ' (' . $row['POStatus'] . ')',
        ];
    }

    return $options;
}

function po_payment_save(array $input, ?int $paymentId = null): array
{
    $data = po_payment_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    $poId = (int) ($data['po_id'] ?? 0);
    if ($poId <= 0) {
        return ['ok' => false, 'error' => 'Select a purchase order.'];
    }

    if (po_get_order($poId) === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    $paymentDate = po_payment_parse_datetime($data['payment_date']);
    if ($paymentDate === null) {
        return ['ok' => false, 'error' => 'Enter a valid payment date and time.'];
    }

    if ($data['payment_amount'] === '' || (float) $data['payment_amount'] <= 0) {
        return ['ok' => false, 'error' => 'Enter a payment amount greater than zero.'];
    }

    if (!in_array($data['payment_type'], PO_PAYMENT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid payment type.'];
    }

    $params = [
        'po_id'        => $poId,
        'payment_date' => $paymentDate,
        'amount'       => (float) $data['payment_amount'],
        'type'         => $data['payment_type'],
        'conf_number'  => $data['payment_conf_number'] !== '' ? $data['payment_conf_number'] : null,
        'made_by'      => $data['payment_made_by'] !== '' ? $data['payment_made_by'] : null,
        'comments'     => $data['payment_comments'] !== '' ? $data['payment_comments'] : null,
        'actor'        => $actorId,
    ];

    try {
        $pdo = db();

        if ($paymentId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.POPayment (
                    POID, PaymentDate, PaymentAmount, PaymentType,
                    PaymentConfNumber, PaymentMadeBy, PaymentComments,
                    CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.PaymentID AS inserted_id
                VALUES (
                    :po_id, :payment_date, :amount, :type,
                    :conf_number, :made_by, :comments,
                    :actor, :actor
                )
            SQL);
            $stmt->execute($params);
            $paymentId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (po_payment_get($paymentId) === null) {
                return ['ok' => false, 'error' => 'Payment not found.'];
            }

            $params['id'] = $paymentId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.POPayment
                SET POID = :po_id,
                    PaymentDate = :payment_date,
                    PaymentAmount = :amount,
                    PaymentType = :type,
                    PaymentConfNumber = :conf_number,
                    PaymentMadeBy = :made_by,
                    PaymentComments = :comments,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE PaymentID = :id
            SQL);
            $stmt->execute($params);
        }

        return ['ok' => true, 'error' => null, 'id' => $paymentId, 'po_id' => $poId];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save payment. Please check your entries and try again.'];
    }
}

function po_payment_delete(int $paymentId): array
{
    $payment = po_payment_get($paymentId);
    if ($payment === null) {
        return ['ok' => false, 'error' => 'Payment not found.'];
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM dbo.POPayment WHERE PaymentID = :id')->execute(['id' => $paymentId]);

    return ['ok' => true, 'error' => null, 'po_id' => (int) $payment['POID']];
}
