<?php

require_once __DIR__ . '/po.php';
require_once __DIR__ . '/po-receiving.php';
require_once __DIR__ . '/mail.php';

const DAS_STATUSES = ['Not Scheduled', 'Scheduled', 'Canceled'];

function das_can_read(): bool
{
    return po_can_access_po_pages();
}

function das_can_create(): bool
{
    return po_can_update();
}

function das_can_update(): bool
{
    return po_can_update();
}

function das_require_read(): void
{
    auth_require_module_read('delivery-scheduling-log');
    po_require_read();
}

function das_require_create(): void
{
    auth_require_module_read('delivery-scheduling-log');
    po_require_update();
}

function das_require_update(): void
{
    auth_require_module_read('delivery-scheduling-log');
    po_require_update();
}

function das_actor_name(): string
{
    $user = auth_user();

    return (string) ($user['UserName'] ?? '');
}

function das_status_class(string $status): string
{
    return match ($status) {
        'Scheduled'     => 'status-received',
        'Canceled'      => 'status-cancelled',
        'Not Scheduled' => 'status-draft',
        default         => 'status-draft',
    };
}

function das_format_datetime(?string $value): string
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

function das_datetime_input(?string $value): string
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

function das_parse_datetime(string $value): ?string
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

function das_supplier_snapshot(int $supplierId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT SupplierID, SupplierName, ContactName, ContactEmail, ContactPhone
        FROM dbo.Supplier
        WHERE SupplierID = :id
    SQL);
    $stmt->execute(['id' => $supplierId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function das_get(int $apptId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.*,
            po.PONumber,
            r.PONumber AS ReceiptPONumber,
            r.JazzASN AS ReceiptJazzASN
        FROM dbo.DeliveryAppointmentScheduling a
        INNER JOIN dbo.PurchaseOrder po ON po.POID = a.POID
        LEFT JOIN dbo.POReceipt r ON r.PORID = a.POReceiptID
        WHERE a.ApptID = :id
    SQL);
    $stmt->execute(['id' => $apptId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function das_get_by_por_id(int $porId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP (1) ApptID
        FROM dbo.DeliveryAppointmentScheduling
        WHERE POReceiptID = :por_id
        ORDER BY ApptID DESC
    SQL);
    $stmt->execute(['por_id' => $porId]);
    $apptId = $stmt->fetchColumn();

    return $apptId !== false ? das_get((int) $apptId) : null;
}

function das_por_id_for_jazz_asn(string $jazzAsn): ?int
{
    $jazzAsn = trim($jazzAsn);
    if ($jazzAsn === '') {
        return null;
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP (1) PORID
        FROM dbo.POReceipt
        WHERE JazzASN = :jazz_asn
        ORDER BY PORID DESC
    SQL);
    $stmt->execute(['jazz_asn' => $jazzAsn]);
    $porId = $stmt->fetchColumn();

    return $porId !== false ? (int) $porId : null;
}

function das_default_from_receipt(int $porId): ?array
{
    $receipt = por_get($porId);
    if ($receipt === null) {
        return null;
    }

    $supplier = das_supplier_snapshot((int) $receipt['SupplierID']);
    if ($supplier === null) {
        return null;
    }

    $appointmentAt = null;
    $scheduledDate = trim((string) ($receipt['ScheduledReceiptDate'] ?? ''));
    $scheduledTime = trim((string) ($receipt['ScheduledReceiptTime'] ?? ''));
    if ($scheduledDate !== '') {
        $combined = $scheduledDate . ($scheduledTime !== '' ? ' ' . substr($scheduledTime, 0, 8) : ' 09:00:00');
        $appointmentAt = das_parse_datetime(str_replace(' ', 'T', substr($combined, 0, 16)));
    }

    $jazzAsn = trim((string) ($receipt['JazzASN'] ?? ''));

    return [
        'po_receipt_id'            => $porId,
        'po_id'                    => (int) $receipt['POID'],
        'supplier_id'              => (int) $receipt['SupplierID'],
        'company_name'             => (string) $supplier['SupplierName'],
        'contact_name'             => (string) ($supplier['ContactName'] ?? ''),
        'contact_email'            => (string) ($supplier['ContactEmail'] ?? ''),
        'contact_phone'            => (string) ($supplier['ContactPhone'] ?? ''),
        'appointment_datetime'     => das_datetime_input($appointmentAt),
        'appointment_address'      => (string) ($receipt['DeliveryAddress'] ?? ''),
        'appointment_company_name' => (string) ($receipt['Facility'] ?? $supplier['SupplierName']),
        'receiving_company_contact' => '',
        'receiving_company_email'   => '',
        'receiving_company_phone'   => '',
        'appointment_status'       => !empty($receipt['AppointmentMade']) ? 'Scheduled' : 'Not Scheduled',
        'appointment_asn_created'  => $jazzAsn !== '' ? 1 : 0,
        'appointment_asn_number'   => $jazzAsn,
        'appointment_notes'        => '',
    ];
}

function das_to_form(array $row): array
{
    return [
        'appt_id'                  => (int) ($row['ApptID'] ?? 0),
        'po_receipt_id'            => (string) ($row['POReceiptID'] ?? ''),
        'po_id'                    => (string) ($row['POID'] ?? ''),
        'supplier_id'              => (string) ($row['SupplierID'] ?? ''),
        'company_name'             => (string) ($row['CompanyName'] ?? ''),
        'contact_name'             => (string) ($row['ContactName'] ?? ''),
        'contact_email'            => (string) ($row['ContactEmail'] ?? ''),
        'contact_phone'            => (string) ($row['ContactPhone'] ?? ''),
        'appointment_datetime'     => das_datetime_input($row['AppointmentDateTime'] ?? null),
        'appointment_address'      => (string) ($row['AppointmentAddress'] ?? ''),
        'appointment_company_name' => (string) ($row['AppointmentCompanyName'] ?? ''),
        'receiving_company_contact' => (string) (das_record_value($row, 'ReceivingCompanyContact') ?? ''),
        'receiving_company_email'   => (string) (das_record_value($row, 'ReceivingCompanyEmail') ?? ''),
        'receiving_company_phone'   => (string) (das_record_value($row, 'ReceivingCompanyPhone') ?? ''),
        'appointment_status'       => (string) ($row['AppointmentStatus'] ?? 'Not Scheduled'),
        'appointment_asn_created'  => !empty($row['AppointmentASNCreated']) ? '1' : '0',
        'appointment_asn_number'   => (string) ($row['AppointmentASNNumber'] ?? ''),
        'appointment_notes'        => (string) ($row['AppointmentNotes'] ?? ''),
    ];
}

function das_from_input(array $input): array
{
    return [
        'po_receipt_id'            => trim($input['po_receipt_id'] ?? ''),
        'po_id'                    => trim($input['po_id'] ?? ''),
        'supplier_id'              => trim($input['supplier_id'] ?? ''),
        'company_name'             => trim($input['company_name'] ?? ''),
        'contact_name'             => trim($input['contact_name'] ?? ''),
        'contact_email'            => trim($input['contact_email'] ?? ''),
        'contact_phone'            => trim($input['contact_phone'] ?? ''),
        'appointment_datetime'     => trim($input['appointment_datetime'] ?? ''),
        'appointment_address'      => trim($input['appointment_address'] ?? ''),
        'appointment_company_name' => trim($input['appointment_company_name'] ?? ''),
        'receiving_company_contact' => trim($input['receiving_company_contact'] ?? ''),
        'receiving_company_email'   => trim($input['receiving_company_email'] ?? ''),
        'receiving_company_phone'   => trim($input['receiving_company_phone'] ?? ''),
        'appointment_status'       => trim($input['appointment_status'] ?? ''),
        'appointment_asn_created'  => !empty($input['appointment_asn_created']),
        'appointment_asn_number'   => trim($input['appointment_asn_number'] ?? ''),
        'appointment_notes'        => trim($input['appointment_notes'] ?? ''),
    ];
}

function das_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            a.ApptID,
            a.POReceiptID,
            a.POID,
            a.AppointmentDateTime,
            a.AppointmentStatus,
            a.AppointmentASNNumber,
            a.CompanyName,
            a.ContactName,
            a.ContactEmail,
            a.ModifiedDate,
            po.PONumber
        FROM dbo.DeliveryAppointmentScheduling a
        INNER JOIN dbo.PurchaseOrder po ON po.POID = a.POID
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND a.AppointmentStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['po_id'])) {
        $sql .= ' AND a.POID = :po_id';
        $params['po_id'] = (int) $filters['po_id'];
    }

    if (!empty($filters['por_id'])) {
        $sql .= ' AND a.POReceiptID = :por_id';
        $params['por_id'] = (int) $filters['por_id'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            po.PONumber LIKE :q OR
            a.CompanyName LIKE :q OR
            a.ContactName LIKE :q OR
            a.ContactEmail LIKE :q OR
            a.AppointmentASNNumber LIKE :q OR
            a.AppointmentAddress LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sql .= ' ORDER BY a.AppointmentDateTime DESC, a.ApptID DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function das_refresh_supplier_fields(array $data): array
{
    $supplierId = (int) ($data['supplier_id'] ?? 0);
    if ($supplierId <= 0) {
        return $data;
    }

    $supplier = das_supplier_snapshot($supplierId);
    if ($supplier === null) {
        return $data;
    }

    $data['company_name'] = (string) $supplier['SupplierName'];
    if (trim($data['contact_name'] ?? '') === '') {
        $data['contact_name'] = (string) ($supplier['ContactName'] ?? '');
    }
    if (trim($data['contact_email'] ?? '') === '') {
        $data['contact_email'] = (string) ($supplier['ContactEmail'] ?? '');
    }
    if (trim($data['contact_phone'] ?? '') === '') {
        $data['contact_phone'] = (string) ($supplier['ContactPhone'] ?? '');
    }

    return $data;
}

function das_save(array $input, ?int $apptId = null): array
{
    $data = das_refresh_supplier_fields(das_from_input($input));
    $actor = das_actor_name();

    $poId = (int) ($data['po_id'] ?? 0);
    if ($poId <= 0) {
        return ['ok' => false, 'error' => 'Select a purchase order.'];
    }

    if (po_get_order($poId) === null) {
        return ['ok' => false, 'error' => 'Purchase order not found.'];
    }

    $supplierId = (int) ($data['supplier_id'] ?? 0);
    if ($supplierId <= 0) {
        return ['ok' => false, 'error' => 'Select a supplier.'];
    }

    if (das_supplier_snapshot($supplierId) === null) {
        return ['ok' => false, 'error' => 'Supplier not found.'];
    }

    if (!in_array($data['appointment_status'], DAS_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid appointment status.'];
    }

    $porId = (int) ($data['po_receipt_id'] ?? 0);
    if ($porId > 0 && por_get($porId) === null) {
        return ['ok' => false, 'error' => 'PO receipt not found.'];
    }

    $appointmentAt = das_parse_datetime($data['appointment_datetime']);
    if ($data['appointment_datetime'] !== '' && $appointmentAt === null) {
        return ['ok' => false, 'error' => 'Appointment date/time is not valid.'];
    }

    $params = [
        'po_receipt_id'            => $porId > 0 ? $porId : null,
        'po_id'                    => $poId,
        'supplier_id'              => $supplierId,
        'company_name'             => $data['company_name'] !== '' ? $data['company_name'] : null,
        'contact_name'             => $data['contact_name'] !== '' ? $data['contact_name'] : null,
        'contact_email'            => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        'contact_phone'            => $data['contact_phone'] !== '' ? $data['contact_phone'] : null,
        'appointment_datetime'     => $appointmentAt,
        'appointment_address'      => $data['appointment_address'] !== '' ? $data['appointment_address'] : null,
        'appointment_company_name' => $data['appointment_company_name'] !== '' ? $data['appointment_company_name'] : null,
        'receiving_company_contact' => $data['receiving_company_contact'] !== '' ? $data['receiving_company_contact'] : null,
        'receiving_company_email'   => $data['receiving_company_email'] !== '' ? $data['receiving_company_email'] : null,
        'receiving_company_phone'   => $data['receiving_company_phone'] !== '' ? $data['receiving_company_phone'] : null,
        'appointment_status'       => $data['appointment_status'],
        'appointment_asn_created'  => $data['appointment_asn_created'] ? 1 : 0,
        'appointment_asn_number'   => $data['appointment_asn_number'] !== '' ? $data['appointment_asn_number'] : null,
        'appointment_notes'        => $data['appointment_notes'] !== '' ? $data['appointment_notes'] : null,
        'modified_by'              => $actor !== '' ? $actor : null,
    ];

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);

        if ($apptId === null) {
            $params['created_by'] = $actor !== '' ? $actor : null;
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.DeliveryAppointmentScheduling (
                    POReceiptID, POID, SupplierID,
                    CompanyName, ContactName, ContactEmail, ContactPhone,
                    AppointmentDateTime, AppointmentAddress, AppointmentCompanyName,
                    ReceivingCompanyContact, ReceivingCompanyEmail, ReceivingCompanyPhone,
                    AppointmentStatus, AppointmentASNCreated, AppointmentASNNumber, AppointmentNotes,
                    CreatedBy, ModifiedBy
                )
                OUTPUT INSERTED.ApptID AS inserted_id
                VALUES (
                    :po_receipt_id, :po_id, :supplier_id,
                    :company_name, :contact_name, :contact_email, :contact_phone,
                    :appointment_datetime, :appointment_address, :appointment_company_name,
                    :receiving_company_contact, :receiving_company_email, :receiving_company_phone,
                    :appointment_status, :appointment_asn_created, :appointment_asn_number, :appointment_notes,
                    :created_by, :modified_by
                )
            SQL);
            $stmt->execute($params);
            $apptId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (das_get($apptId) === null) {
                return ['ok' => false, 'error' => 'Appointment not found.'];
            }

            $params['id'] = $apptId;
            $pdo->prepare(<<<SQL
                UPDATE dbo.DeliveryAppointmentScheduling
                SET POReceiptID = :po_receipt_id,
                    POID = :po_id,
                    SupplierID = :supplier_id,
                    CompanyName = :company_name,
                    ContactName = :contact_name,
                    ContactEmail = :contact_email,
                    ContactPhone = :contact_phone,
                    AppointmentDateTime = :appointment_datetime,
                    AppointmentAddress = :appointment_address,
                    AppointmentCompanyName = :appointment_company_name,
                    ReceivingCompanyContact = :receiving_company_contact,
                    ReceivingCompanyEmail = :receiving_company_email,
                    ReceivingCompanyPhone = :receiving_company_phone,
                    AppointmentStatus = :appointment_status,
                    AppointmentASNCreated = :appointment_asn_created,
                    AppointmentASNNumber = :appointment_asn_number,
                    AppointmentNotes = :appointment_notes,
                    ModifiedBy = :modified_by,
                    ModifiedDate = SYSUTCDATETIME()
                WHERE ApptID = :id
            SQL)->execute($params);
        }

        return ['ok' => true, 'error' => null, 'appt_id' => $apptId];
    } catch (Throwable $e) {
        error_log('das_save failed: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save appointment. Please try again.'];
    }
}

function das_get_or_create_for_por(int $porId): array
{
    $existing = das_get_by_por_id($porId);
    if ($existing !== null) {
        return ['ok' => true, 'error' => null, 'appt_id' => (int) $existing['ApptID'], 'created' => false];
    }

    $defaults = das_default_from_receipt($porId);
    if ($defaults === null) {
        return ['ok' => false, 'error' => 'PO receipt not found.', 'appt_id' => null, 'created' => false];
    }

    $result = das_save($defaults);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'appt_id' => null, 'created' => false];
    }

    return ['ok' => true, 'error' => null, 'appt_id' => (int) $result['appt_id'], 'created' => true];
}

function das_po_options(): array
{
    return por_po_options();
}

function das_por_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT r.PORID, r.POID, r.PONumber, r.PORStatus, r.JazzASN, s.SupplierName
        FROM dbo.POReceipt r
        INNER JOIN dbo.PurchaseOrder po ON po.POID = r.POID
        INNER JOIN dbo.Supplier s ON s.SupplierID = po.SupplierID
        ORDER BY r.CreateDate DESC, r.PORID DESC
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $options[] = [
            'id'       => (int) $row['PORID'],
            'po_id'    => (int) $row['POID'],
            'jazz_asn' => trim((string) ($row['JazzASN'] ?? '')),
            'label'    => $row['PONumber'] . ' · Receipt #' . $row['PORID'] . ' · ' . $row['SupplierName'] . ' (' . $row['PORStatus'] . ')',
        ];
    }

    return $options;
}

function das_supplier_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT SupplierID, SupplierName, SupplierCode, ContactName, ContactEmail, ContactPhone
        FROM dbo.Supplier
        WHERE IsActive = 1
        ORDER BY SupplierName
    SQL);

    $options = [];
    foreach ($stmt->fetchAll() as $row) {
        $label = $row['SupplierName'];
        if (!empty($row['SupplierCode'])) {
            $label .= ' (' . $row['SupplierCode'] . ')';
        }
        $options[] = [
            'id'            => (int) $row['SupplierID'],
            'label'         => $label,
            'company_name'  => (string) $row['SupplierName'],
            'contact_name'  => (string) ($row['ContactName'] ?? ''),
            'contact_email' => (string) ($row['ContactEmail'] ?? ''),
            'contact_phone' => (string) ($row['ContactPhone'] ?? ''),
        ];
    }

    return $options;
}

function das_return_context_from_query(): array
{
    return [
        'return_to'    => trim((string) ($_GET['return_to'] ?? $_POST['return_to'] ?? '')),
        'por_id'       => (int) ($_GET['por_id'] ?? $_POST['por_id'] ?? 0),
        'jazz_asn_id'  => trim((string) ($_GET['jazz_asn_id'] ?? $_POST['jazz_asn_id'] ?? '')),
    ];
}

function das_return_query(array $context): string
{
    $params = [];
    if (($context['return_to'] ?? '') !== '') {
        $params['return_to'] = $context['return_to'];
    }
    if (!empty($context['por_id'])) {
        $params['por_id'] = (int) $context['por_id'];
    }
    if (($context['jazz_asn_id'] ?? '') !== '') {
        $params['jazz_asn_id'] = $context['jazz_asn_id'];
    }

    return $params === [] ? '' : '&' . http_build_query($params);
}

function das_breadcrumb(array $context): array
{
    $returnTo = $context['return_to'] ?? '';
    $porId = (int) ($context['por_id'] ?? 0);
    $jazzAsnId = trim((string) ($context['jazz_asn_id'] ?? ''));

    if ($returnTo === 'por' && $porId > 0) {
        return ['href' => '/po-receiving/view.php?id=' . $porId, 'label' => 'Back to PO Receipt'];
    }

    if ($returnTo === 'asn' && $porId > 0) {
        return ['href' => '/po-receiving/asn.php?id=' . $porId, 'label' => 'Back to ASN Data'];
    }

    if ($returnTo === 'jazz-asn' && $jazzAsnId !== '') {
        return ['href' => '/po-receiving/jazz-asn.php?id=' . rawurlencode($jazzAsnId), 'label' => 'Back to Jazz ASN'];
    }

    if ($returnTo === 'jazz-asns') {
        return ['href' => '/po-receiving/jazz-asns.php', 'label' => 'Back to Jazz ASNs'];
    }

    return ['href' => '/delivery-scheduling-log/', 'label' => 'Back to Delivery Scheduling Log'];
}

function das_appointment_url_for_por(int $porId, array $context = []): string
{
    $query = das_return_query(array_merge($context, [
        'return_to' => $context['return_to'] ?? 'por',
        'por_id'    => $porId,
    ]));

    $existing = das_get_by_por_id($porId);
    if ($existing !== null) {
        return '/delivery-scheduling-log/edit.php?id=' . (int) $existing['ApptID'] . $query;
    }

    return '/delivery-scheduling-log/new.php?por_id=' . $porId . $query;
}

function das_appointment_url_for_jazz_asn(string $jazzAsnId): string
{
    $porId = das_por_id_for_jazz_asn($jazzAsnId);

    if ($porId !== null) {
        return das_appointment_url_for_por($porId, [
            'return_to'   => 'jazz-asn',
            'por_id'      => $porId,
            'jazz_asn_id' => $jazzAsnId,
        ]);
    }

    return '/delivery-scheduling-log/new.php?' . http_build_query([
        'return_to'   => 'jazz-asn',
        'jazz_asn_id' => $jazzAsnId,
        'appointment_asn_number' => $jazzAsnId,
    ]);
}

function das_send_scheduling_request_email(int $apptId, string $extraMessage = ''): array
{
    return das_send_scheduling_email($apptId, $extraMessage, ['mode' => 'request']);
}

function das_send_scheduling_reminder_email(int $apptId, string $reminderMessage = ''): array
{
    return das_send_scheduling_email($apptId, $reminderMessage, ['mode' => 'reminder']);
}

/**
 * @return array{to: array<string, string>, cc: array<string, string>}
 */
function das_scheduling_email_recipients(array $appointment): array
{
    $toRecipients = [];
    $ccRecipients = [];

    $supplierEmail = trim((string) ($appointment['ContactEmail'] ?? ''));
    if ($supplierEmail !== '' && filter_var($supplierEmail, FILTER_VALIDATE_EMAIL)) {
        $supplierName = trim((string) ($appointment['ContactName'] ?? ''));
        $toRecipients[$supplierEmail] = $supplierName !== '' ? $supplierName : $supplierEmail;
    }

    $receivingEmail = trim((string) (das_record_value($appointment, 'ReceivingCompanyEmail') ?? ''));
    if ($receivingEmail !== '' && filter_var($receivingEmail, FILTER_VALIDATE_EMAIL)) {
        $receivingContact = trim((string) (das_record_value($appointment, 'ReceivingCompanyContact') ?? ''));
        $duplicate = false;
        foreach (array_keys($toRecipients) as $existingEmail) {
            if (strcasecmp($existingEmail, $receivingEmail) === 0) {
                $duplicate = true;
                break;
            }
        }
        if (!$duplicate) {
            $ccRecipients[$receivingEmail] = $receivingContact !== '' ? $receivingContact : $receivingEmail;
        }
    }

    return ['to' => $toRecipients, 'cc' => $ccRecipients];
}

/**
 * @param array{to?: string, cc?: list<string>} $sendResult
 */
function das_format_scheduling_email_recipients(array $sendResult): string
{
    $parts = [];
    if (!empty($sendResult['to'])) {
        $parts[] = (string) $sendResult['to'];
    }
    foreach ($sendResult['cc'] ?? [] as $email) {
        $parts[] = $email . ' (CC)';
    }

    return implode(' and ', $parts);
}

/**
 * @param array{mode?: string} $options
 * @return array{ok: bool, error: ?string, to?: string, cc?: list<string>}
 */
function das_send_scheduling_email(int $apptId, string $message, array $options = []): array
{
    $appointment = das_get($apptId);
    if ($appointment === null) {
        return ['ok' => false, 'error' => 'Appointment not found.'];
    }

    $recipients = das_scheduling_email_recipients($appointment);
    if ($recipients['to'] === []) {
        return ['ok' => false, 'error' => 'A valid supplier contact email is required on this appointment.'];
    }

    $isReminder = ($options['mode'] ?? '') === 'reminder';
    $sender = das_actor_name();
    $poNumber = (string) ($appointment['PONumber'] ?? '');
    $subject = $isReminder
        ? 'Reminder: Delivery appointment scheduling — PO ' . $poNumber
        : 'Delivery appointment scheduling request — PO ' . $poNumber;
    $email = das_build_scheduling_request_email($appointment, $sender, $message, $options);

    $send = mail_send_html_multi_result(
        $recipients['to'],
        $recipients['cc'],
        $subject,
        $email['html'],
        $email['plain']
    );

    if (!$send['ok']) {
        return ['ok' => false, 'error' => $send['error'] ?? 'Unable to send email.'];
    }

    $primaryTo = array_key_first($recipients['to']);

    return [
        'ok'    => true,
        'error' => null,
        'to'    => $primaryTo !== null ? (string) $primaryTo : null,
        'cc'    => array_keys($recipients['cc']),
    ];
}

function das_send_reminder_url(int $apptId, array $context = []): string
{
    return '/delivery-scheduling-log/send-reminder.php?id=' . $apptId . das_return_query($context);
}

/**
 * @param array{mode?: string} $options
 * @return array{plain: string, html: string}
 */
function das_build_scheduling_request_email(array $appointment, string $sender, string $extraMessage = '', array $options = []): array
{
    $isReminder = ($options['mode'] ?? '') === 'reminder';
    $poNumber = (string) ($appointment['PONumber'] ?? '');
    $contactName = trim((string) ($appointment['ContactName'] ?? ''));
    $asnContext = das_asn_context_for_appointment($appointment);
    $intro = $isReminder
        ? 'This is a reminder to schedule a delivery appointment for the following purchase order receipt.'
        : 'We are requesting delivery appointment scheduling for the following purchase order receipt.';
    $closing = $isReminder
        ? 'Please reply with your proposed delivery date and time at your earliest convenience.'
        : 'Please reply with your proposed delivery date and time.';

    $plainLines = [
        'Hello' . ($contactName !== '' ? ' ' . $contactName : '') . ',',
        '',
        $intro,
        '',
        'PO number: ' . $poNumber,
        'Supplier: ' . (string) ($appointment['CompanyName'] ?? ''),
    ];

    if (($appointment['AppointmentASNNumber'] ?? '') !== '') {
        $plainLines[] = 'ASN number: ' . $appointment['AppointmentASNNumber'];
    }

    if ($asnContext !== null && $asnContext['ok']) {
        $plainLines = array_merge($plainLines, das_asn_email_lines($asnContext));
    } elseif ($asnContext !== null && !empty($asnContext['error']) && das_asn_number_from_record($appointment) !== '') {
        $plainLines[] = '';
        $plainLines[] = 'ASN details could not be loaded from Jazz OMS: ' . $asnContext['error'];
    }

    if (($appointment['AppointmentAddress'] ?? '') !== '') {
        $plainLines[] = 'Delivery address: ' . $appointment['AppointmentAddress'];
    }

    if (($appointment['AppointmentCompanyName'] ?? '') !== '') {
        $plainLines[] = 'Receiving location: ' . $appointment['AppointmentCompanyName'];
    }

    $plainLines = array_merge($plainLines, das_receiving_company_email_lines($appointment));

    if (($appointment['AppointmentDateTime'] ?? '') !== '') {
        $plainLines[] = 'Requested appointment: ' . das_format_datetime($appointment['AppointmentDateTime']);
    } else {
        $plainLines[] = 'Requested appointment: To be confirmed';
    }

    if ($isReminder) {
        if (trim($extraMessage) !== '') {
            $plainLines[] = '';
            $plainLines[] = 'Reminder message:';
            $plainLines[] = trim($extraMessage);
        }
    } else {
        if (($appointment['AppointmentNotes'] ?? '') !== '') {
            $plainLines[] = '';
            $plainLines[] = 'Notes:';
            $plainLines[] = (string) $appointment['AppointmentNotes'];
        }

        if (trim($extraMessage) !== '') {
            $plainLines[] = '';
            $plainLines[] = trim($extraMessage);
        }
    }

    $plainLines[] = '';
    $plainLines[] = $closing;
    $plainLines[] = '';
    $plainLines[] = 'Thank you,';
    $plainLines[] = $sender !== '' ? $sender : 'NutraAxis Operations';

    $html = das_build_scheduling_request_email_html(
        $appointment,
        $contactName,
        $sender,
        $extraMessage,
        $asnContext,
        $options
    );

    return [
        'plain' => implode("\n", $plainLines),
        'html'  => $html,
    ];
}

function das_mail_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * @return list<string>
 */
function das_receiving_company_email_lines(array $appointment): array
{
    $lines = [];
    $rows = das_receiving_company_summary_rows($appointment);
    if ($rows === []) {
        return $lines;
    }

    $lines[] = '';
    $lines[] = 'Receiving company contact:';
    foreach ($rows as $label => $value) {
        $lines[] = $label . ': ' . $value;
    }

    return $lines;
}

/**
 * @return array<string, string>
 */
function das_receiving_company_summary_rows(array $appointment): array
{
    $rows = [];
    $contact = trim((string) (das_record_value($appointment, 'ReceivingCompanyContact') ?? ''));
    $email = trim((string) (das_record_value($appointment, 'ReceivingCompanyEmail') ?? ''));
    $phone = trim((string) (das_record_value($appointment, 'ReceivingCompanyPhone') ?? ''));

    if ($contact !== '') {
        $rows['Receiving contact'] = $contact;
    }
    if ($email !== '') {
        $rows['Receiving email'] = $email;
    }
    if ($phone !== '') {
        $rows['Receiving phone'] = $phone;
    }

    return $rows;
}

function das_mail_nl2br(string $value): string
{
    return nl2br(das_mail_h($value), false);
}

/**
 * @param array<string, mixed>|null $asnContext
 * @param array{mode?: string} $options
 */
function das_build_scheduling_request_email_html(
    array $appointment,
    string $contactName,
    string $sender,
    string $extraMessage,
    ?array $asnContext,
    array $options = []
): string {
    $isReminder = ($options['mode'] ?? '') === 'reminder';
    $closing = $isReminder
        ? 'Please reply with your proposed delivery date and time at your earliest convenience.'
        : 'Please reply with your proposed delivery date and time.';
    $intro = $isReminder
        ? 'This is a reminder to schedule a delivery appointment for the following purchase order receipt.'
        : 'We are requesting delivery appointment scheduling for the following purchase order receipt.';
    $emailTitle = $isReminder
        ? 'Delivery appointment scheduling reminder'
        : 'Delivery appointment scheduling request';
    $poNumber = (string) ($appointment['PONumber'] ?? '');
    $summaryRows = [
        'PO number' => $poNumber,
        'Supplier'  => (string) ($appointment['CompanyName'] ?? '—'),
    ];

    if (($appointment['AppointmentASNNumber'] ?? '') !== '') {
        $summaryRows['ASN number'] = (string) $appointment['AppointmentASNNumber'];
    }

    if (($appointment['AppointmentAddress'] ?? '') !== '') {
        $summaryRows['Delivery address'] = (string) $appointment['AppointmentAddress'];
    }

    if (($appointment['AppointmentCompanyName'] ?? '') !== '') {
        $summaryRows['Receiving location'] = (string) $appointment['AppointmentCompanyName'];
    }

    foreach (das_receiving_company_summary_rows($appointment) as $label => $value) {
        $summaryRows[$label] = $value;
    }

    $requestedAppointment = ($appointment['AppointmentDateTime'] ?? '') !== ''
        ? das_format_datetime($appointment['AppointmentDateTime'])
        : 'To be confirmed';
    $summaryRows['Requested appointment'] = $requestedAppointment;

    $content = '<p style="margin:0 0 16px;font-size:15px;line-height:1.5;color:#1a2e2a;">'
        . 'Hello' . ($contactName !== '' ? ' ' . das_mail_h($contactName) : '') . ','
        . '</p>';
    $content .= '<p style="margin:0 0 24px;font-size:15px;line-height:1.5;color:#1a2e2a;">'
        . das_mail_h($intro)
        . '</p>';

    $content .= das_mail_html_section('Summary', das_mail_html_key_value_table($summaryRows));

    if ($asnContext !== null && $asnContext['ok']) {
        $content .= das_mail_html_asn_section($asnContext);
    } elseif ($asnContext !== null && !empty($asnContext['error']) && das_asn_number_from_record($appointment) !== '') {
        $content .= das_mail_html_notice(
            'ASN details could not be loaded from Jazz OMS: ' . (string) $asnContext['error']
        );
    }

    if ($isReminder) {
        if (trim($extraMessage) !== '') {
            $content .= das_mail_html_section(
                'Reminder message',
                '<p style="margin:0;font-size:14px;line-height:1.55;color:#1a2e2a;white-space:pre-wrap;">'
                . das_mail_h(trim($extraMessage))
                . '</p>'
            );
        }
    } else {
        if (($appointment['AppointmentNotes'] ?? '') !== '') {
            $content .= das_mail_html_section(
                'Notes',
                '<p style="margin:0;font-size:14px;line-height:1.55;color:#1a2e2a;white-space:pre-wrap;">'
                . das_mail_h((string) $appointment['AppointmentNotes'])
                . '</p>'
            );
        }

        if (trim($extraMessage) !== '') {
            $content .= das_mail_html_section(
                'Additional message',
                '<p style="margin:0;font-size:14px;line-height:1.55;color:#1a2e2a;white-space:pre-wrap;">'
                . das_mail_h(trim($extraMessage))
                . '</p>'
            );
        }
    }

    $content .= '<p style="margin:24px 0 0;font-size:15px;line-height:1.5;color:#1a2e2a;">'
        . das_mail_h($closing)
        . '</p>';
    $content .= '<p style="margin:16px 0 0;font-size:15px;line-height:1.5;color:#1a2e2a;">'
        . 'Thank you,<br>'
        . das_mail_h($sender !== '' ? $sender : 'NutraAxis Operations')
        . '</p>';

    return das_mail_html_wrap($emailTitle, $content);
}

function das_mail_html_wrap(string $title, string $content): string
{
    return '<!DOCTYPE html>'
        . '<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . das_mail_h($title) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#eef4f2;font-family:Arial,Helvetica,sans-serif;color:#1a2e2a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef4f2;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:720px;background:#ffffff;border:1px solid #d5e0dc;border-radius:10px;overflow:hidden;">'
        . '<tr><td style="padding:24px 28px;background:#0d5c4f;color:#ffffff;">'
        . '<h1 style="margin:0;font-size:20px;line-height:1.3;font-weight:700;">' . das_mail_h($title) . '</h1>'
        . '</td></tr>'
        . '<tr><td style="padding:28px;">' . $content . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#f7faf9;border-top:1px solid #d5e0dc;font-size:12px;line-height:1.5;color:#647872;">'
        . 'NutraAxis Operations'
        . '</td></tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
        . '</body></html>';
}

function das_mail_html_section(string $title, string $innerHtml): string
{
    return '<div style="margin:0 0 24px;">'
        . '<h2 style="margin:0 0 12px;font-size:16px;line-height:1.3;color:#0d5c4f;">' . das_mail_h($title) . '</h2>'
        . $innerHtml
        . '</div>';
}

/**
 * @param array<string, string> $rows
 */
function das_mail_html_key_value_table(array $rows): string
{
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">';

    foreach ($rows as $label => $value) {
        $html .= '<tr>'
            . '<td style="padding:8px 12px 8px 0;vertical-align:top;width:38%;font-size:12px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#647872;">'
            . das_mail_h((string) $label)
            . '</td>'
            . '<td style="padding:8px 0;vertical-align:top;font-weight:600;color:#1a2e2a;white-space:pre-wrap;">'
            . das_mail_h((string) $value)
            . '</td>'
            . '</tr>';
    }

    return $html . '</table>';
}

function das_mail_html_notice(string $message): string
{
    return '<div style="margin:0 0 24px;padding:12px 14px;background:#fff8e8;border:1px solid #ead9a8;border-radius:8px;font-size:14px;line-height:1.5;color:#5c4a1d;">'
        . das_mail_h($message)
        . '</div>';
}

/**
 * @param array<string, mixed> $asnContext
 */
function das_mail_html_asn_section(array $asnContext): string
{
    $source = (string) ($asnContext['source'] ?? 'jazz');
    $headerRows = [];

    foreach ($asnContext['header_fields'] as $field => $value) {
        $formatted = das_asn_display_value($value, $source);
        if ($formatted === '' || $formatted === '—') {
            continue;
        }
        $headerRows[das_asn_display_label((string) $field, $source)] = $formatted;
    }

    $html = das_mail_html_section('ASN details', das_mail_html_key_value_table($headerRows));

    $lineTable = das_mail_html_asn_line_table($asnContext);
    if ($lineTable !== '') {
        $html .= das_mail_html_section('ASN line items', $lineTable);
    }

    return $html;
}

/**
 * @param array<string, mixed> $asnContext
 */
function das_mail_html_asn_line_table(array $asnContext): string
{
    $source = (string) ($asnContext['source'] ?? 'jazz');
    $columns = das_asn_email_line_columns($asnContext);
    $rows = [];

    if (!empty($asnContext['asn_rows'])) {
        foreach ($asnContext['asn_rows'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = $row;
        }
    } elseif (!empty($asnContext['details'])) {
        foreach ($asnContext['details'] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $rows[] = $line;
        }
    }

    if ($columns === [] || $rows === []) {
        return '<p style="margin:0;font-size:14px;color:#647872;">No detail lines returned.</p>';
    }

    $html = '<p style="margin:0 0 12px;font-size:13px;color:#647872;">'
        . count($rows) . ' line' . (count($rows) === 1 ? '' : 's') . ' on this ASN.'
        . '</p>';
    $html .= '<div style="overflow-x:auto;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:13px;min-width:520px;">'
        . '<thead><tr style="background:#eef4f2;">';

    foreach ($columns as $column) {
        $label = $source === 'receipt'
            ? (string) $column
            : das_asn_display_label((string) $column, $source);
        $html .= '<th style="padding:10px 12px;text-align:left;border-bottom:2px solid #d5e0dc;font-size:11px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;color:#647872;white-space:nowrap;">'
            . das_mail_h($label)
            . '</th>';
    }

    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>';
        foreach ($columns as $column) {
            $cell = $source === 'receipt'
                ? trim((string) ($row[$column] ?? ''))
                : das_asn_display_value($row[$column] ?? null, $source);
            if ($cell === '—') {
                $cell = '';
            }
            $html .= '<td style="padding:10px 12px;border-bottom:1px solid #e7eeeb;color:#1a2e2a;vertical-align:top;">'
                . das_mail_h($cell)
                . '</td>';
        }
        $html .= '</tr>';
    }

    return $html . '</tbody></table></div>';
}

/**
 * @param array<string, mixed> $asnContext
 * @return list<string>
 */
function das_asn_email_line_columns(array $asnContext): array
{
    $jazzPreferred = [
        'sku_code', 'item_code', 'description', 'barcode', 'quantity', 'received',
        'status', 'facility', 'case_barcode', 'size_desc',
    ];
    $receiptPreferred = [
        'Sku Code', 'Sku Barcode', 'Quantity', 'Case Barcode', 'Facility', 'Arrival At',
    ];

    if (!empty($asnContext['asn_rows'])) {
        return das_pick_email_columns($asnContext['asn_columns'] ?? [], $receiptPreferred);
    }

    if (!empty($asnContext['details'])) {
        return das_pick_email_columns($asnContext['detail_columns'] ?? [], $jazzPreferred);
    }

    return [];
}

/**
 * @param list<string> $available
 * @param list<string> $preferred
 * @return list<string>
 */
function das_pick_email_columns(array $available, array $preferred): array
{
    $picked = [];

    foreach ($preferred as $preferredColumn) {
        foreach ($available as $column) {
            if (strcasecmp((string) $column, $preferredColumn) === 0) {
                $picked[] = (string) $column;
                break;
            }
        }
    }

    foreach ($available as $column) {
        $column = (string) $column;
        if (!in_array($column, $picked, true) && count($picked) < 10) {
            $picked[] = $column;
        }
    }

    return $picked;
}

function das_asn_number_from_record(array $record): string
{
    foreach (['AppointmentASNNumber', 'appointment_asn_number'] as $field) {
        $asn = trim((string) (das_record_value($record, $field) ?? ''));
        if ($asn !== '') {
            return $asn;
        }
    }

    return trim((string) (das_record_value($record, 'ReceiptJazzASN') ?? ''));
}

function das_record_value(array $record, string $field): mixed
{
    foreach ($record as $key => $value) {
        if (strcasecmp((string) $key, $field) === 0) {
            return $value;
        }
    }

    return null;
}

function das_record_int(array $record, string $field): int
{
    return (int) (das_record_value($record, $field) ?? 0);
}

function das_asn_display_label(string $field, string $source = 'jazz'): string
{
    if ($source === 'receipt') {
        $labels = [
            'po_number'         => 'PO number',
            'jazz_asn'          => 'Jazz ASN',
            'por_status'        => 'Receipt status',
            'shipment_number'   => 'Shipment number',
            'facility'          => 'Facility',
            'carrier_number'    => 'Carrier number',
            'delivery_address'  => 'Delivery address',
            'jazz_asn_status'   => 'Jazz ASN status',
        ];

        return $labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    if (!function_exists('jazz_oms_asn_column_label')) {
        require_once __DIR__ . '/po-receiving-asn.php';
    }

    return jazz_oms_asn_column_label($field);
}

function das_asn_display_value($value, string $source = 'jazz'): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    if ($source === 'receipt') {
        return is_scalar($value) ? (string) $value : '—';
    }

    if (!function_exists('jazz_oms_asn_format_field_value')) {
        require_once __DIR__ . '/po-receiving-asn.php';
    }

    return jazz_oms_asn_format_field_value($value);
}

/**
 * Load ASN display context for an appointment, with PO receipt fallback.
 *
 * @return array<string, mixed>|null
 */
function das_asn_context_for_appointment(array $appointment): ?array
{
    $asnNumber = das_asn_number_from_record($appointment);
    if ($asnNumber === '') {
        return null;
    }

    $context = das_asn_context($asnNumber);
    if ($context['ok']) {
        $context['source'] = 'jazz';
        das_asn_merge_receipt_lines($context, $appointment, $asnNumber);

        return $context;
    }

    $porId = das_record_int($appointment, 'POReceiptID');
    if ($porId > 0) {
        $fallback = das_asn_context_from_receipt($porId, $asnNumber);
        if ($fallback['ok']) {
            if (!empty($context['error'])) {
                $fallback['jazz_error'] = $context['error'];
            }

            return $fallback;
        }
    }

    $context['source'] = 'jazz';

    return $context;
}

function das_asn_merge_receipt_lines(array &$context, array $appointment, string $asnNumber): void
{
    if ($context['details'] !== [] || !empty($context['asn_rows'])) {
        return;
    }

    $porId = das_record_int($appointment, 'POReceiptID');
    if ($porId <= 0) {
        return;
    }

    $receiptContext = das_asn_context_from_receipt($porId, $asnNumber);
    if (!$receiptContext['ok'] || empty($receiptContext['asn_rows'])) {
        return;
    }

    $context['asn_rows'] = $receiptContext['asn_rows'];
    $context['asn_columns'] = $receiptContext['asn_columns'];
    $context['line_source'] = 'receipt';
}

/**
 * @return array<string, mixed>
 */
function das_asn_context_from_receipt(int $porId, string $asnNumber): array
{
    if (!function_exists('por_asn_rows')) {
        require_once __DIR__ . '/po-receiving-asn.php';
    }

    $receipt = por_get($porId);
    if ($receipt === null) {
        return [
            'asn_number'     => $asnNumber,
            'ok'             => false,
            'error'          => 'PO receipt not found.',
            'row'            => null,
            'header_fields'  => [],
            'details'        => [],
            'detail_columns' => [],
            'source'         => 'receipt',
            'asn_rows'       => [],
            'asn_columns'    => [],
        ];
    }

    $lines = por_get_lines($porId);
    $asnRows = por_asn_rows($receipt, $lines);
    $headerFields = array_filter([
        'po_number'        => (string) ($receipt['PONumber'] ?? ''),
        'jazz_asn'         => trim((string) ($receipt['JazzASN'] ?? '')) !== '' ? (string) $receipt['JazzASN'] : $asnNumber,
        'por_status'       => (string) ($receipt['PORStatus'] ?? ''),
        'shipment_number'  => por_asn_shipment_number($receipt),
        'facility'         => (string) ($receipt['Facility'] ?? ''),
        'carrier_number'   => (string) ($receipt['CarrierNumber'] ?? ''),
        'delivery_address' => (string) ($receipt['DeliveryAddress'] ?? ''),
        'jazz_asn_status'  => (string) ($receipt['JazzASNStatus'] ?? ''),
    ], static fn($value): bool => trim((string) $value) !== '');

    return [
        'asn_number'     => $asnNumber,
        'ok'             => true,
        'error'          => null,
        'row'            => null,
        'header_fields'  => $headerFields,
        'details'        => [],
        'detail_columns' => [],
        'source'         => 'receipt',
        'asn_rows'       => $asnRows,
        'asn_columns'    => POR_ASN_COLUMNS,
    ];
}

/**
 * @return array{
 *     asn_number: string,
 *     ok: bool,
 *     error: ?string,
 *     row: ?array<string, mixed>,
 *     header_fields: array<string, mixed>,
 *     details: list<array<string, mixed>>,
 *     detail_columns: list<string>
 * }
 */
function das_asn_context(string $asnNumber): array
{
    $asnNumber = trim($asnNumber);
    $empty = [
        'asn_number'      => $asnNumber,
        'ok'              => false,
        'error'           => null,
        'row'             => null,
        'header_fields'   => [],
        'details'         => [],
        'detail_columns'  => [],
    ];

    if ($asnNumber === '') {
        return $empty;
    }

    if (!function_exists('jazz_oms_get_asn')) {
        require_once __DIR__ . '/po-receiving-asn.php';
    }

    try {
        $result = jazz_oms_get_asn($asnNumber);
    } catch (Throwable $e) {
        error_log('das_asn_context Jazz lookup failed for ASN ' . $asnNumber . ': ' . $e->getMessage());

        return array_merge($empty, ['error' => 'Unable to load ASN from Jazz OMS.']);
    }

    if (!$result['ok'] || !is_array($result['row'])) {
        return array_merge($empty, ['error' => $result['error'] ?? 'ASN not found.']);
    }

    $row = $result['row'];
    $details = is_array($row['detail'] ?? null)
        ? $row['detail']
        : (is_array($row['details'] ?? null) ? $row['details'] : []);

    return [
        'asn_number'     => $asnNumber,
        'ok'             => true,
        'error'          => null,
        'row'            => $row,
        'header_fields'  => jazz_oms_asn_header_fields($row),
        'details'        => $details,
        'detail_columns' => jazz_oms_asn_detail_columns($details),
    ];
}

/**
 * @param array<string, mixed> $asnContext
 * @return list<string>
 */
function das_asn_email_lines(array $asnContext): array
{
    if (!$asnContext['ok']) {
        return [];
    }

    $source = (string) ($asnContext['source'] ?? 'jazz');
    $lines = ['', 'ASN details:'];

    foreach ($asnContext['header_fields'] as $field => $value) {
        $formatted = das_asn_display_value($value, $source);
        if ($formatted === '' || $formatted === '—') {
            continue;
        }
        $lines[] = das_asn_display_label((string) $field, $source) . ': ' . $formatted;
    }

    if (!empty($asnContext['asn_rows'])) {
        $lines[] = '';
        $lines[] = 'ASN line items:';

        foreach ($asnContext['asn_rows'] as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $parts = [];
            foreach ($asnContext['asn_columns'] as $column) {
                $cell = trim((string) ($row[$column] ?? ''));
                if ($cell === '') {
                    continue;
                }
                $parts[] = $column . ': ' . $cell;
            }

            if ($parts !== []) {
                $lines[] = '  Line ' . ($index + 1) . ': ' . implode(' · ', $parts);
            }
        }

        return $lines;
    }

    if ($asnContext['details'] !== []) {
        $lines[] = '';
        $lines[] = 'ASN line items:';

        foreach ($asnContext['details'] as $index => $line) {
            if (!is_array($line)) {
                continue;
            }

            $parts = [];
            foreach ($asnContext['detail_columns'] as $column) {
                $cell = jazz_oms_asn_format_field_value($line[$column] ?? null);
                if ($cell === '' || $cell === '—') {
                    continue;
                }
                $parts[] = jazz_oms_asn_column_label($column) . ': ' . $cell;
            }

            if ($parts !== []) {
                $lines[] = '  Line ' . ($index + 1) . ': ' . implode(' · ', $parts);
            }
        }
    }

    return $lines;
}

function das_load_asn_context(array $appointment): ?array
{
    $asnNumber = das_asn_number_from_record($appointment);
    if ($asnNumber === '') {
        return null;
    }

    $porId = das_record_int($appointment, 'POReceiptID');
    $receiptContext = $porId > 0 ? das_asn_context_from_receipt($porId, $asnNumber) : null;
    $jazzContext = null;

    try {
        $jazzContext = das_asn_context($asnNumber);
    } catch (Throwable $e) {
        error_log('das_load_asn_context Jazz lookup failed: ' . $e->getMessage());
    }

    if (is_array($jazzContext) && !empty($jazzContext['ok'])) {
        $jazzContext['source'] = 'jazz';
        das_asn_merge_receipt_lines($jazzContext, $appointment, $asnNumber);
        $jazzContext['asn_number'] = $asnNumber;

        return $jazzContext;
    }

    if (is_array($receiptContext) && !empty($receiptContext['ok'])) {
        if (!empty($jazzContext['error'])) {
            $receiptContext['jazz_error'] = $jazzContext['error'];
        }
        $receiptContext['asn_number'] = $asnNumber;

        return $receiptContext;
    }

    return [
        'asn_number'     => $asnNumber,
        'ok'             => false,
        'error'          => $jazzContext['error'] ?? 'Unable to load ASN details.',
        'row'            => null,
        'header_fields'  => [],
        'details'        => [],
        'detail_columns' => [],
        'source'         => 'jazz',
        'asn_rows'       => [],
        'asn_columns'    => [],
    ];
}

function das_view_asn_context(array $appointment): ?array
{
    $asnNumber = das_asn_number_from_record($appointment);
    if ($asnNumber === '') {
        return null;
    }

    $porId = das_record_int($appointment, 'POReceiptID');
    if ($porId > 0) {
        $context = das_asn_context_from_receipt($porId, $asnNumber);
        if ($context['ok']) {
            $context['asn_number'] = $asnNumber;

            return $context;
        }
    }

    return [
        'asn_number'     => $asnNumber,
        'ok'             => false,
        'error'          => 'Unable to load ASN details from the linked PO receipt.',
        'row'            => null,
        'header_fields'  => [],
        'details'        => [],
        'detail_columns' => [],
        'source'         => 'receipt',
        'asn_rows'       => [],
        'asn_columns'    => [],
    ];
}

function das_render_asn_detail_panel(array $appointment, bool $viewMode = false): void
{
    $asnContext = $viewMode
        ? das_view_asn_context($appointment)
        : das_load_asn_context($appointment);
    if ($asnContext === null) {
        return;
    }

    require __DIR__ . '/delivery-appointment-asn-detail.php';
}
