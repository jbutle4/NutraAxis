<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin.php';

const LEGAL_PERMISSION_COLUMN = 'LegalAgreements';

const LEGAL_CONTRACT_TYPES = [
    'NDA/MNDA',
    'Manufacturing Agreement',
    'Quality Agreement',
    'Supply Agreement',
    'SOW / Consulting',
    'MSA',
    'TMLA',
    'Partnership',
    'Distribution',
    'Tax Service',
    'Lab Services',
    'Other',
];

const LEGAL_CONTRACT_STATUSES = [
    'Draft',
    'In Review',
    'Under Negotiation',
    'Executed',
    'Active',
    'Expired',
    'Terminated',
];

const LEGAL_GOVERNING_LAWS = ['Colorado', 'California', 'Delaware', 'Other'];

const LEGAL_LIST_SORT_COLUMNS = [
    'contract_id'   => 'Contract ID',
    'contract_name' => 'Contract name',
    'type'          => 'Type',
    'counterparty'  => 'Counterparty',
    'status'        => 'Status',
    'expiry'        => 'Est. expiry',
    'annual_value'  => 'Annual value',
];

const LEGAL_LIST_SORT_SQL = [
    'contract_id'   => 'c.ContractNumber',
    'contract_name' => 'c.ContractName',
    'type'          => 'c.ContractType',
    'counterparty'  => 'c.Counterparty',
    'status'        => 'c.ContractStatus',
    'expiry'        => 'c.ExpirationDate',
    'annual_value'  => 'c.AnnualValue',
];

const LEGAL_LIST_SORT_NUMERIC = ['annual_value'];

function legal_permission_value(): ?string
{
    return auth_permission_value(LEGAL_PERMISSION_COLUMN);
}

function legal_can_read(): bool
{
    return auth_can_read(LEGAL_PERMISSION_COLUMN);
}

function legal_can_create(): bool
{
    return auth_can_create(LEGAL_PERMISSION_COLUMN);
}

function legal_can_update(): bool
{
    return auth_can_update(LEGAL_PERMISSION_COLUMN);
}

function legal_can_delete(): bool
{
    return auth_can_delete(LEGAL_PERMISSION_COLUMN);
}

function legal_require_read(): void
{
    auth_require_login();
    if (legal_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Legal Agreements.');
}

function legal_require_create(): void
{
    legal_require_read();
    if (legal_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create contracts.');
}

function legal_require_update(): void
{
    legal_require_read();
    if (legal_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update contracts.');
}

function legal_require_delete(): void
{
    legal_require_read();
    if (legal_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete contracts.');
}

function legal_status_class(string $status): string
{
    return match ($status) {
        'Draft'              => 'status-draft',
        'In Review'          => 'status-submitted',
        'Under Negotiation'  => 'status-submitted',
        'Executed'           => 'status-approved',
        'Active'             => 'status-received',
        'Expired'            => 'status-cancelled',
        'Terminated'         => 'status-cancelled',
        default              => 'status-draft',
    };
}

function legal_format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))->format('M j, Y');
    } catch (Throwable) {
        return $value;
    }
}

function legal_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}

function legal_date_input(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Throwable) {
        return '';
    }
}

function legal_contract_to_form(array $contract): array
{
    return [
        'contract_id'            => (int) $contract['ContractID'],
        'contract_number'        => (string) $contract['ContractNumber'],
        'contract_name'          => (string) $contract['ContractName'],
        'counterparty'           => (string) $contract['Counterparty'],
        'contract_type'          => (string) $contract['ContractType'],
        'contract_status'        => (string) $contract['ContractStatus'],
        'effective_date'         => legal_date_input($contract['EffectiveDate'] ?? null),
        'expiration_date'        => legal_date_input($contract['ExpirationDate'] ?? null),
        'expiration_notes'       => (string) ($contract['ExpirationNotes'] ?? ''),
        'auto_renewal'           => !empty($contract['AutoRenewal']),
        'renewal_notice_days'    => $contract['RenewalNoticeDays'] !== null ? (string) $contract['RenewalNoticeDays'] : '',
        'annual_value'           => $contract['AnnualValue'] !== null ? (string) $contract['AnnualValue'] : '',
        'internal_owner_user'    => $contract['InternalOwnerUser'] !== null ? (string) $contract['InternalOwnerUser'] : '',
        'external_signatory'     => (string) ($contract['ExternalSignatory'] ?? ''),
        'related_supplier'       => (string) ($contract['RelatedSupplier'] ?? ''),
        'governing_law'          => (string) ($contract['GoverningLaw'] ?? ''),
        'confidentiality_months' => $contract['ConfidentialityMonths'] !== null ? (string) $contract['ConfidentialityMonths'] : '',
        'key_obligations'        => (string) ($contract['KeyObligationsSummary'] ?? ''),
        'document_link'          => (string) ($contract['DocumentLink'] ?? ''),
        'amendment_links'        => (string) ($contract['AmendmentLinks'] ?? ''),
        'notes'                  => (string) ($contract['Notes'] ?? ''),
    ];
}

function legal_format_expiration(array $contract): string
{
    if (!empty($contract['ExpirationDate'])) {
        return legal_format_date($contract['ExpirationDate']);
    }

    if (!empty($contract['ExpirationNotes'])) {
        return (string) $contract['ExpirationNotes'];
    }

    return '—';
}

function legal_generate_contract_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare('SELECT ContractNumber FROM dbo.ContractRegister WHERE ContractNumber LIKE :prefix ORDER BY ContractID DESC');
    $stmt->execute(['prefix' => 'CTR-' . $year . '-%']);
    $last = $stmt->fetchColumn();
    $seq = 1;

    if ($last !== false && preg_match('/CTR-' . $year . '-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return sprintf('CTR-%s-%03d', $year, $seq);
}

function legal_list_contracts(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            c.ContractID,
            c.ContractNumber,
            c.ContractName,
            c.Counterparty,
            c.ContractType,
            c.ContractStatus,
            c.EffectiveDate,
            c.ExpirationDate,
            c.ExpirationNotes,
            c.AutoRenewal,
            c.AnnualValue,
            c.RelatedSupplier,
            u.UserName AS InternalOwnerName
        FROM dbo.ContractRegister c
        LEFT JOIN dbo.[User] u ON u.UserID = c.InternalOwnerUser
        WHERE 1 = 1
    SQL;
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND c.ContractStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['type'])) {
        $sql .= ' AND c.ContractType = :type';
        $params['type'] = $filters['type'];
    }

    if (!empty($filters['q'])) {
        $sql .= ' AND (
            c.ContractNumber LIKE :q OR
            c.ContractName LIKE :q OR
            c.Counterparty LIKE :q OR
            c.RelatedSupplier LIKE :q
        )';
        $params['q'] = '%' . $filters['q'] . '%';
    }

    $sortState = table_sort_state(LEGAL_LIST_SORT_COLUMNS, 'contract_id', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LEGAL_LIST_SORT_SQL, $sortState, 'contract_id', 'contract_id');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function legal_get_contract(int $contractId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            c.*,
            io.UserName AS InternalOwnerName,
            mu.UserName AS ModifiedByName
        FROM dbo.ContractRegister c
        LEFT JOIN dbo.[User] io ON io.UserID = c.InternalOwnerUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = c.ModifiedbyUser
        WHERE c.ContractID = :id
    SQL);
    $stmt->execute(['id' => $contractId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function legal_user_options(): array
{
    $options = [];
    foreach (admin_list_users() as $user) {
        $options[] = [
            'id'    => (int) $user['UserID'],
            'label' => $user['UserName'] . ' (' . $user['UserLogin'] . ')',
        ];
    }

    return $options;
}

function legal_contract_from_input(array $input): array
{
    return [
        'contract_number'        => trim($input['contract_number'] ?? ''),
        'contract_name'          => trim($input['contract_name'] ?? ''),
        'counterparty'           => trim($input['counterparty'] ?? ''),
        'contract_type'          => trim($input['contract_type'] ?? ''),
        'contract_status'        => trim($input['contract_status'] ?? 'Draft'),
        'effective_date'         => trim($input['effective_date'] ?? ''),
        'expiration_date'        => trim($input['expiration_date'] ?? ''),
        'expiration_notes'       => trim($input['expiration_notes'] ?? ''),
        'auto_renewal'           => (string) ($input['auto_renewal'] ?? '0') === '1',
        'renewal_notice_days'    => trim($input['renewal_notice_days'] ?? ''),
        'annual_value'           => trim($input['annual_value'] ?? ''),
        'internal_owner_user'    => trim($input['internal_owner_user'] ?? ''),
        'external_signatory'     => trim($input['external_signatory'] ?? ''),
        'related_supplier'       => trim($input['related_supplier'] ?? ''),
        'governing_law'          => trim($input['governing_law'] ?? ''),
        'confidentiality_months' => trim($input['confidentiality_months'] ?? ''),
        'key_obligations'        => trim($input['key_obligations'] ?? ''),
        'document_link'          => trim($input['document_link'] ?? ''),
        'amendment_links'        => trim($input['amendment_links'] ?? ''),
        'notes'                  => trim($input['notes'] ?? ''),
    ];
}

function legal_save_contract(array $input, ?int $contractId = null): array
{
    $data = legal_contract_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    if ($data['contract_name'] === '' || $data['counterparty'] === '') {
        return ['ok' => false, 'error' => 'Contract name and counterparty are required.'];
    }

    if (!in_array($data['contract_type'], LEGAL_CONTRACT_TYPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid contract type.'];
    }

    if (!in_array($data['contract_status'], LEGAL_CONTRACT_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid contract status.'];
    }

    if ($data['governing_law'] !== '' && !in_array($data['governing_law'], LEGAL_GOVERNING_LAWS, true)) {
        return ['ok' => false, 'error' => 'Select a valid governing law.'];
    }

    $pdo = db();

    if ($contractId === null) {
        $data['contract_number'] = $data['contract_number'] !== '' ? $data['contract_number'] : legal_generate_contract_number($pdo);
    } elseif ($data['contract_number'] === '') {
        return ['ok' => false, 'error' => 'Contract number is required.'];
    }

    $dup = $pdo->prepare('SELECT ContractID FROM dbo.ContractRegister WHERE ContractNumber = :number AND ContractID <> :id');
    $dup->execute(['number' => $data['contract_number'], 'id' => $contractId ?? 0]);
    if ($dup->fetch() !== false) {
        return ['ok' => false, 'error' => 'That contract number is already in use.'];
    }

    $ownerId = $data['internal_owner_user'] !== '' ? (int) $data['internal_owner_user'] : null;
    if ($ownerId !== null && $ownerId <= 0) {
        $ownerId = null;
    }

    $renewalDays = $data['renewal_notice_days'] !== '' ? (int) $data['renewal_notice_days'] : null;
    $annualValue = $data['annual_value'] !== '' ? (float) $data['annual_value'] : null;
    $confMonths = $data['confidentiality_months'] !== '' ? (int) $data['confidentiality_months'] : null;

    $params = [
        'number'           => $data['contract_number'],
        'name'             => $data['contract_name'],
        'counterparty'     => $data['counterparty'],
        'type'             => $data['contract_type'],
        'status'           => $data['contract_status'],
        'effective'        => $data['effective_date'] !== '' ? $data['effective_date'] : null,
        'expiration'       => $data['expiration_date'] !== '' ? $data['expiration_date'] : null,
        'expiration_notes' => $data['expiration_notes'] !== '' ? $data['expiration_notes'] : null,
        'auto_renewal'     => $data['auto_renewal'] ? 1 : 0,
        'renewal_days'     => $renewalDays,
        'annual_value'     => $annualValue,
        'owner'            => $ownerId,
        'signatory'        => $data['external_signatory'] !== '' ? $data['external_signatory'] : null,
        'supplier'         => $data['related_supplier'] !== '' ? $data['related_supplier'] : null,
        'governing_law'    => $data['governing_law'] !== '' ? $data['governing_law'] : null,
        'conf_months'      => $confMonths,
        'obligations'      => $data['key_obligations'] !== '' ? $data['key_obligations'] : null,
        'document_link'    => $data['document_link'] !== '' ? $data['document_link'] : null,
        'amendment_links'  => $data['amendment_links'] !== '' ? $data['amendment_links'] : null,
        'notes'            => $data['notes'] !== '' ? $data['notes'] : null,
        'actor'            => $actorId,
    ];

    try {
        if ($contractId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.ContractRegister (
                    ContractNumber, ContractName, Counterparty, ContractType, ContractStatus,
                    EffectiveDate, ExpirationDate, ExpirationNotes, AutoRenewal, RenewalNoticeDays,
                    AnnualValue, InternalOwnerUser, ExternalSignatory, RelatedSupplier, GoverningLaw,
                    ConfidentialityMonths, KeyObligationsSummary, DocumentLink, AmendmentLinks, Notes,
                    ModifiedbyUser
                )
                OUTPUT INSERTED.ContractID AS inserted_id
                VALUES (
                    :number, :name, :counterparty, :type, :status,
                    :effective, :expiration, :expiration_notes, :auto_renewal, :renewal_days,
                    :annual_value, :owner, :signatory, :supplier, :governing_law,
                    :conf_months, :obligations, :document_link, :amendment_links, :notes,
                    :actor
                )
            SQL);
            $stmt->execute($params);
            $contractId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (legal_get_contract($contractId) === null) {
                return ['ok' => false, 'error' => 'Contract not found.'];
            }

            $params['id'] = $contractId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.ContractRegister
                SET ContractNumber = :number,
                    ContractName = :name,
                    Counterparty = :counterparty,
                    ContractType = :type,
                    ContractStatus = :status,
                    EffectiveDate = :effective,
                    ExpirationDate = :expiration,
                    ExpirationNotes = :expiration_notes,
                    AutoRenewal = :auto_renewal,
                    RenewalNoticeDays = :renewal_days,
                    AnnualValue = :annual_value,
                    InternalOwnerUser = :owner,
                    ExternalSignatory = :signatory,
                    RelatedSupplier = :supplier,
                    GoverningLaw = :governing_law,
                    ConfidentialityMonths = :conf_months,
                    KeyObligationsSummary = :obligations,
                    DocumentLink = :document_link,
                    AmendmentLinks = :amendment_links,
                    Notes = :notes,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE ContractID = :id
            SQL);
            $stmt->execute($params);
        }

        return ['ok' => true, 'error' => null, 'id' => $contractId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unable to save contract. Please check your entries and try again.'];
    }
}

function legal_delete_contract(int $contractId): array
{
    if (legal_get_contract($contractId) === null) {
        return ['ok' => false, 'error' => 'Contract not found.'];
    }

    $pdo = db();
    $pdo->prepare('DELETE FROM dbo.ContractRegister WHERE ContractID = :id')->execute(['id' => $contractId]);

    return ['ok' => true, 'error' => null];
}
