<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/supplier.php';
require_once __DIR__ . '/supplier-invoice.php';
require_once __DIR__ . '/attachment-storage.php';

const BID_PERMISSION_COLUMN = 'POManagement';

const BID_INITIATIVE_STATUSES = [
    'Draft',
    'Open for Bids',
    'Under Review',
    'Awarded',
    'Cancelled',
    'Closed',
];

const BID_ESTIMATE_STATUSES = [
    'Received',
    'Under Review',
    'Selected',
    'Not Selected',
    'Withdrawn',
];

const BID_INITIATIVE_CATEGORIES = [
    'Marketing',
    'IT',
    'Facilities',
    'Professional Services',
    'Transportation',
    'Other',
];

const BID_INITIATIVE_LIST_SORT_COLUMNS = [
    'number'   => 'Number',
    'title'    => 'Title',
    'category' => 'Category',
    'status'   => 'Status',
    'budget'   => 'Budget',
    'target'   => 'Target award',
    'modified' => 'Updated',
];

const BID_INITIATIVE_LIST_SORT_SQL = [
    'number'   => 'i.InitiativeNumber',
    'title'    => 'i.Title',
    'category' => 'i.Category',
    'status'   => 'i.Status',
    'budget'   => 'i.BudgetAmount',
    'target'   => 'i.TargetAwardDate',
    'modified' => 'i.ModifiedDate',
];

const BID_INITIATIVE_LIST_SORT_NUMERIC = ['budget'];

const BID_ESTIMATE_ATTACHMENT_KINDS = [
    'Estimate'   => 'Estimate',
    'Quote'      => 'Quote',
    'Invoice'    => 'Invoice',
    'Supporting' => 'Supporting document',
    'Other'      => 'Other',
];

const BID_ATTACHMENT_MAX_BYTES = 15 * 1024 * 1024;

function bid_permission_value(): ?string
{
    return auth_permission_value(BID_PERMISSION_COLUMN);
}

function bid_can_read(): bool
{
    return auth_can_read(BID_PERMISSION_COLUMN);
}

function bid_can_create(): bool
{
    return auth_can_create(BID_PERMISSION_COLUMN);
}

function bid_can_update(): bool
{
    return auth_can_update(BID_PERMISSION_COLUMN);
}

function bid_can_delete(): bool
{
    return auth_can_delete(BID_PERMISSION_COLUMN);
}

function bid_require_read(): void
{
    auth_require_login();
    if (bid_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Initiatives & Bids.');
}

function bid_require_create(): void
{
    bid_require_read();
    if (bid_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create initiatives or bids.');
}

function bid_require_update(): void
{
    bid_require_read();
    if (bid_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update initiatives or bids.');
}

function bid_initiative_status_class(string $status): string
{
    return match ($status) {
        'Draft'          => 'status-draft',
        'Open for Bids'  => 'status-submitted',
        'Under Review'   => 'status-sent-back',
        'Awarded'        => 'status-approved',
        'Cancelled'      => 'status-cancelled',
        'Closed'         => 'status-cancelled',
        default          => 'status-draft',
    };
}

function bid_estimate_status_class(string $status): string
{
    return match ($status) {
        'Received'      => 'status-draft',
        'Under Review'  => 'status-submitted',
        'Selected'      => 'status-approved',
        'Not Selected'  => 'status-cancelled',
        'Withdrawn'     => 'status-cancelled',
        default         => 'status-draft',
    };
}

function bid_category_to_supplier_type(?string $category): string
{
    return match ((string) $category) {
        'Marketing'              => 'Marketing',
        'IT'                     => 'IT Supplier',
        'Transportation'         => 'Transportation',
        'Professional Services'  => 'Other Contractor',
        'Facilities'             => 'Other Supplier',
        default                  => 'Other Supplier',
    };
}

function bid_generate_initiative_number(PDO $pdo): string
{
    $stmt = $pdo->query(<<<SQL
        SELECT MAX(
            TRY_CONVERT(INT, REPLACE(InitiativeNumber, N'INIT-', N''))
        ) AS MaxNum
        FROM dbo.BidInitiative
        WHERE InitiativeNumber LIKE N'INIT-%'
    SQL);
    $max = (int) ($stmt->fetchColumn() ?: 0);

    return sprintf('INIT-%04d', $max + 1);
}

function bid_initiative_list(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            i.InitiativeID,
            i.InitiativeNumber,
            i.Title,
            i.Category,
            i.Status,
            i.BudgetAmount,
            CONVERT(varchar(10), i.TargetAwardDate, 23) AS TargetAwardDate,
            CONVERT(varchar(19), i.ModifiedDate, 120) AS ModifiedDate,
            u.UserName AS OwnerName,
            (
                SELECT COUNT(*)
                FROM dbo.BidEstimate e
                WHERE e.InitiativeID = i.InitiativeID
            ) AS BidCount
        FROM dbo.BidInitiative i
        LEFT JOIN dbo.[User] u ON u.UserID = i.OwnerUserID
        WHERE 1 = 1
    SQL;

    $params = [];
    if (!empty($filters['status'])) {
        $sql .= ' AND i.Status = :status';
        $params['status'] = $filters['status'];
    }
    if (!empty($filters['q'])) {
        [$likeSql, $likeParams] = db_like_or([
            'i.InitiativeNumber',
            'i.Title',
            'i.Description',
            'i.Category'
        ], (string) $filters['q']);
        $sql .= ' AND ' . $likeSql;
        $params = array_merge($params, $likeParams);
    }

    $sortState = table_sort_state(BID_INITIATIVE_LIST_SORT_COLUMNS, 'modified', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(BID_INITIATIVE_LIST_SORT_SQL, $sortState, 'modified', 'number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function bid_initiative_get(int $initiativeId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            i.*,
            ou.UserName AS OwnerName,
            cu.UserName AS CreatedByName
        FROM dbo.BidInitiative i
        LEFT JOIN dbo.[User] ou ON ou.UserID = i.OwnerUserID
        LEFT JOIN dbo.[User] cu ON cu.UserID = i.CreatedByUser
        WHERE i.InitiativeID = :id
    SQL);
    $stmt->execute(['id' => $initiativeId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function bid_initiative_from_input(array $input): array
{
    return [
        'title'             => trim((string) ($input['title'] ?? '')),
        'description'       => trim((string) ($input['description'] ?? '')),
        'category'          => trim((string) ($input['category'] ?? '')),
        'owner_user_id'     => trim((string) ($input['owner_user_id'] ?? '')),
        'target_award_date' => trim((string) ($input['target_award_date'] ?? '')),
        'budget_amount'     => trim((string) ($input['budget_amount'] ?? '')),
        'status'            => trim((string) ($input['status'] ?? 'Draft')),
    ];
}

function bid_initiative_to_form(array $initiative): array
{
    return [
        'title'             => (string) ($initiative['Title'] ?? ''),
        'description'       => (string) ($initiative['Description'] ?? ''),
        'category'          => (string) ($initiative['Category'] ?? ''),
        'owner_user_id'     => !empty($initiative['OwnerUserID']) ? (string) (int) $initiative['OwnerUserID'] : '',
        'target_award_date' => supplier_invoice_normalize_form_date($initiative['TargetAwardDate'] ?? null),
        'budget_amount'     => $initiative['BudgetAmount'] !== null ? (string) $initiative['BudgetAmount'] : '',
        'status'            => (string) ($initiative['Status'] ?? 'Draft'),
    ];
}

function bid_initiative_save(array $input, ?int $initiativeId = null): array
{
    $data = bid_initiative_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    if ($data['title'] === '') {
        return ['ok' => false, 'error' => 'Enter an initiative title.'];
    }

    if ($data['category'] !== '' && !in_array($data['category'], BID_INITIATIVE_CATEGORIES, true)) {
        return ['ok' => false, 'error' => 'Select a valid category.'];
    }

    if (!in_array($data['status'], BID_INITIATIVE_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid status.'];
    }

    $ownerUserId = $data['owner_user_id'] !== '' ? (int) $data['owner_user_id'] : ($actorId ?: null);
    $budget = $data['budget_amount'] !== '' ? (float) $data['budget_amount'] : null;
    if ($budget !== null && $budget < 0) {
        return ['ok' => false, 'error' => 'Budget must be zero or greater.'];
    }

    $params = [
        'title'             => $data['title'],
        'description'       => $data['description'] !== '' ? $data['description'] : null,
        'category'          => $data['category'] !== '' ? $data['category'] : null,
        'owner_user_id'     => $ownerUserId,
        'target_award_date' => $data['target_award_date'] !== '' ? $data['target_award_date'] : null,
        'budget_amount'     => $budget,
        'status'            => $data['status'],
        'actor'             => $actorId,
    ];

    try {
        $pdo = db();

        if ($initiativeId === null) {
            $number = bid_generate_initiative_number($pdo);
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.BidInitiative (
                    InitiativeNumber, Title, Description, Category, OwnerUserID,
                    TargetAwardDate, BudgetAmount, Status, CreatedByUser, ModifiedByUser
                )
                OUTPUT INSERTED.InitiativeID AS inserted_id
                VALUES (
                    :number, :title, :description, :category, :owner_user_id,
                    :target_award_date, :budget_amount, :status, :actor, :actor
                )
            SQL);
            $stmt->bindValue(':number', $number);
            $stmt->bindValue(':title', $params['title']);
            $stmt->bindValue(':description', $params['description']);
            $stmt->bindValue(':category', $params['category']);
            $stmt->bindValue(':owner_user_id', $params['owner_user_id'], $params['owner_user_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':target_award_date', $params['target_award_date']);
            $stmt->bindValue(':budget_amount', $params['budget_amount']);
            $stmt->bindValue(':status', $params['status']);
            $stmt->bindValue(':actor', $params['actor'], $params['actor'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
            $initiativeId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (bid_initiative_get($initiativeId) === null) {
                return ['ok' => false, 'error' => 'Initiative not found.'];
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.BidInitiative
                SET Title = :title,
                    Description = :description,
                    Category = :category,
                    OwnerUserID = :owner_user_id,
                    TargetAwardDate = :target_award_date,
                    BudgetAmount = :budget_amount,
                    Status = :status,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedByUser = :actor
                WHERE InitiativeID = :id
            SQL);
            $stmt->bindValue(':title', $params['title']);
            $stmt->bindValue(':description', $params['description']);
            $stmt->bindValue(':category', $params['category']);
            $stmt->bindValue(':owner_user_id', $params['owner_user_id'], $params['owner_user_id'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':target_award_date', $params['target_award_date']);
            $stmt->bindValue(':budget_amount', $params['budget_amount']);
            $stmt->bindValue(':status', $params['status']);
            $stmt->bindValue(':actor', $params['actor'], $params['actor'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':id', $initiativeId, PDO::PARAM_INT);
            $stmt->execute();
        }

        return ['ok' => true, 'error' => null, 'id' => $initiativeId];
    } catch (Throwable $e) {
        error_log('bid_initiative_save: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save initiative. Please try again.'];
    }
}

function bid_estimate_list_for_initiative(int $initiativeId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            e.*,
            s.SupplierCode,
            s.SupplierName,
            (
                SELECT COUNT(*)
                FROM dbo.BidEstimateAttachment a
                WHERE a.BidEstimateID = e.BidEstimateID
            ) AS AttachmentCount
        FROM dbo.BidEstimate e
        LEFT JOIN dbo.Supplier s ON s.SupplierID = e.SupplierID
        WHERE e.InitiativeID = :id
        ORDER BY
            CASE e.Status
                WHEN N'Selected' THEN 0
                WHEN N'Under Review' THEN 1
                WHEN N'Received' THEN 2
                ELSE 3
            END,
            e.BidAmount ASC,
            e.BidEstimateID ASC
    SQL);
    $stmt->execute(['id' => $initiativeId]);

    return $stmt->fetchAll();
}

function bid_estimate_get(int $bidEstimateId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            e.*,
            i.InitiativeNumber,
            i.Title AS InitiativeTitle,
            i.Category AS InitiativeCategory,
            i.Status AS InitiativeStatus,
            s.SupplierCode,
            s.SupplierName
        FROM dbo.BidEstimate e
        INNER JOIN dbo.BidInitiative i ON i.InitiativeID = e.InitiativeID
        LEFT JOIN dbo.Supplier s ON s.SupplierID = e.SupplierID
        WHERE e.BidEstimateID = :id
    SQL);
    $stmt->execute(['id' => $bidEstimateId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function bid_estimate_from_input(array $input): array
{
    return [
        'supplier_id'     => trim((string) ($input['supplier_id'] ?? '')),
        'vendor_name'     => trim((string) ($input['vendor_name'] ?? '')),
        'contact_name'    => trim((string) ($input['contact_name'] ?? '')),
        'contact_email'   => trim((string) ($input['contact_email'] ?? '')),
        'contact_phone'   => trim((string) ($input['contact_phone'] ?? '')),
        'bid_amount'      => trim((string) ($input['bid_amount'] ?? '')),
        'currency_code'   => strtoupper(trim((string) ($input['currency_code'] ?? 'USD'))),
        'submitted_date'  => trim((string) ($input['submitted_date'] ?? '')),
        'valid_until'     => trim((string) ($input['valid_until'] ?? '')),
        'notes'           => trim((string) ($input['notes'] ?? '')),
        'status'          => trim((string) ($input['status'] ?? 'Received')),
    ];
}

function bid_estimate_to_form(array $bid): array
{
    return [
        'supplier_id'     => !empty($bid['SupplierID']) ? (string) (int) $bid['SupplierID'] : '',
        'vendor_name'     => (string) ($bid['VendorName'] ?? ''),
        'contact_name'    => (string) ($bid['ContactName'] ?? ''),
        'contact_email'   => (string) ($bid['ContactEmail'] ?? ''),
        'contact_phone'   => (string) ($bid['ContactPhone'] ?? ''),
        'bid_amount'      => $bid['BidAmount'] !== null ? (string) $bid['BidAmount'] : '',
        'currency_code'   => (string) ($bid['CurrencyCode'] ?? 'USD'),
        'submitted_date'  => supplier_invoice_normalize_form_date($bid['SubmittedDate'] ?? null),
        'valid_until'     => supplier_invoice_normalize_form_date($bid['ValidUntil'] ?? null),
        'notes'           => (string) ($bid['Notes'] ?? ''),
        'status'          => (string) ($bid['Status'] ?? 'Received'),
    ];
}

function bid_estimate_save(int $initiativeId, array $input, ?int $bidEstimateId = null): array
{
    $initiative = bid_initiative_get($initiativeId);
    if ($initiative === null) {
        return ['ok' => false, 'error' => 'Initiative not found.'];
    }

    if (in_array((string) $initiative['Status'], ['Awarded', 'Cancelled', 'Closed'], true) && $bidEstimateId === null) {
        return ['ok' => false, 'error' => 'This initiative is closed to new bids.'];
    }

    $data = bid_estimate_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    $supplierId = $data['supplier_id'] !== '' ? (int) $data['supplier_id'] : null;
    if ($supplierId !== null) {
        $supplier = supplier_get($supplierId);
        if ($supplier === null) {
            return ['ok' => false, 'error' => 'Supplier not found.'];
        }
        if ($data['vendor_name'] === '') {
            $data['vendor_name'] = (string) $supplier['SupplierName'];
        }
        if ($data['contact_name'] === '' && !empty($supplier['ContactName'])) {
            $data['contact_name'] = (string) $supplier['ContactName'];
        }
        if ($data['contact_email'] === '' && !empty($supplier['ContactEmail'])) {
            $data['contact_email'] = (string) $supplier['ContactEmail'];
        }
        if ($data['contact_phone'] === '' && !empty($supplier['ContactPhone'])) {
            $data['contact_phone'] = (string) $supplier['ContactPhone'];
        }
    }

    if ($data['vendor_name'] === '') {
        return ['ok' => false, 'error' => 'Enter a vendor / supplier name.'];
    }

    if ($data['bid_amount'] === '' || !is_numeric($data['bid_amount']) || (float) $data['bid_amount'] < 0) {
        return ['ok' => false, 'error' => 'Enter a valid bid amount.'];
    }

    if ($data['contact_email'] !== '' && !filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Enter a valid contact email.'];
    }

    if (!in_array($data['status'], BID_ESTIMATE_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid bid status.'];
    }

    if ($data['status'] === 'Selected') {
        return ['ok' => false, 'error' => 'Use the Award action to select a winning bid.'];
    }

    if ($data['currency_code'] === '') {
        $data['currency_code'] = 'USD';
    }

    $params = [
        'initiative_id'   => $initiativeId,
        'supplier_id'     => $supplierId,
        'vendor_name'     => $data['vendor_name'],
        'contact_name'    => $data['contact_name'] !== '' ? $data['contact_name'] : null,
        'contact_email'   => $data['contact_email'] !== '' ? $data['contact_email'] : null,
        'contact_phone'   => $data['contact_phone'] !== '' ? $data['contact_phone'] : null,
        'bid_amount'      => round((float) $data['bid_amount'], 2),
        'currency_code'   => $data['currency_code'],
        'submitted_date'  => $data['submitted_date'] !== '' ? $data['submitted_date'] : null,
        'valid_until'     => $data['valid_until'] !== '' ? $data['valid_until'] : null,
        'notes'           => $data['notes'] !== '' ? $data['notes'] : null,
        'status'          => $data['status'],
        'actor'           => $actorId,
    ];

    try {
        $pdo = db();

        if ($bidEstimateId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.BidEstimate (
                    InitiativeID, SupplierID, VendorName, ContactName, ContactEmail, ContactPhone,
                    BidAmount, CurrencyCode, SubmittedDate, ValidUntil, Notes, Status,
                    CreatedByUser, ModifiedByUser
                )
                OUTPUT INSERTED.BidEstimateID AS inserted_id
                VALUES (
                    :initiative_id, :supplier_id, :vendor_name, :contact_name, :contact_email, :contact_phone,
                    :bid_amount, :currency_code, :submitted_date, :valid_until, :notes, :status,
                    :created_by_user, :modified_by_user
                )
            SQL);
            db_bind_value($stmt, ':initiative_id', $params['initiative_id'], PDO::PARAM_INT);
            db_bind_value($stmt, ':supplier_id', $params['supplier_id'], PDO::PARAM_INT);
            db_bind_value($stmt, ':vendor_name', $params['vendor_name']);
            db_bind_value($stmt, ':contact_name', $params['contact_name']);
            db_bind_value($stmt, ':contact_email', $params['contact_email']);
            db_bind_value($stmt, ':contact_phone', $params['contact_phone']);
            db_bind_value($stmt, ':bid_amount', $params['bid_amount']);
            db_bind_value($stmt, ':currency_code', $params['currency_code']);
            db_bind_value($stmt, ':submitted_date', $params['submitted_date']);
            db_bind_value($stmt, ':valid_until', $params['valid_until']);
            db_bind_value($stmt, ':notes', $params['notes']);
            db_bind_value($stmt, ':status', $params['status']);
            db_bind_value($stmt, ':created_by_user', $params['actor'], PDO::PARAM_INT);
            db_bind_value($stmt, ':modified_by_user', $params['actor'], PDO::PARAM_INT);
            $stmt->execute();
            $bidEstimateId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $existing = bid_estimate_get($bidEstimateId);
            if ($existing === null || (int) $existing['InitiativeID'] !== $initiativeId) {
                return ['ok' => false, 'error' => 'Bid not found.'];
            }
            if ((string) $existing['Status'] === 'Selected') {
                return ['ok' => false, 'error' => 'The awarded bid cannot be edited here.'];
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.BidEstimate
                SET SupplierID = :supplier_id,
                    VendorName = :vendor_name,
                    ContactName = :contact_name,
                    ContactEmail = :contact_email,
                    ContactPhone = :contact_phone,
                    BidAmount = :bid_amount,
                    CurrencyCode = :currency_code,
                    SubmittedDate = :submitted_date,
                    ValidUntil = :valid_until,
                    Notes = :notes,
                    Status = :status,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedByUser = :modified_by_user
                WHERE BidEstimateID = :id
            SQL);
            db_bind_value($stmt, ':supplier_id', $params['supplier_id'], PDO::PARAM_INT);
            db_bind_value($stmt, ':vendor_name', $params['vendor_name']);
            db_bind_value($stmt, ':contact_name', $params['contact_name']);
            db_bind_value($stmt, ':contact_email', $params['contact_email']);
            db_bind_value($stmt, ':contact_phone', $params['contact_phone']);
            db_bind_value($stmt, ':bid_amount', $params['bid_amount']);
            db_bind_value($stmt, ':currency_code', $params['currency_code']);
            db_bind_value($stmt, ':submitted_date', $params['submitted_date']);
            db_bind_value($stmt, ':valid_until', $params['valid_until']);
            db_bind_value($stmt, ':notes', $params['notes']);
            db_bind_value($stmt, ':status', $params['status']);
            db_bind_value($stmt, ':modified_by_user', $params['actor'], PDO::PARAM_INT);
            db_bind_value($stmt, ':id', $bidEstimateId, PDO::PARAM_INT);
            $stmt->execute();
        }

        return ['ok' => true, 'error' => null, 'id' => $bidEstimateId];
    } catch (Throwable $e) {
        error_log('bid_estimate_save: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save bid. ' . $e->getMessage()];
    }
}

function bid_estimate_list_attachments(int $bidEstimateId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            a.AttachmentID,
            a.FileName,
            a.ContentType,
            a.FileSizeBytes,
            a.AttachmentKind,
            a.UploadDate,
            u.UserName AS UploadedByName
        FROM dbo.BidEstimateAttachment a
        INNER JOIN dbo.[User] u ON u.UserID = a.UploadedByUser
        WHERE a.BidEstimateID = :id
        ORDER BY a.UploadDate DESC
    SQL);
    $stmt->execute(['id' => $bidEstimateId]);

    return $stmt->fetchAll();
}

function bid_estimate_get_attachment(int $attachmentId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.BidEstimateAttachment WHERE AttachmentID = :id');
    $stmt->execute(['id' => $attachmentId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function bid_estimate_attachment_kind_label(string $kind): string
{
    return BID_ESTIMATE_ATTACHMENT_KINDS[$kind] ?? $kind;
}

function bid_estimate_format_file_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024, 1) . ' KB';
    }

    return round($bytes / 1048576, 1) . ' MB';
}

function bid_estimate_save_attachment(int $bidEstimateId, array $file, string $kind = 'Estimate'): array
{
    if (bid_estimate_get($bidEstimateId) === null) {
        return ['ok' => false, 'error' => 'Bid not found.'];
    }

    if (!bid_can_update()) {
        return ['ok' => false, 'error' => 'You do not have permission to upload bid attachments.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file uploaded.'];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'File upload failed.'];
    }

    if (($file['size'] ?? 0) > BID_ATTACHMENT_MAX_BYTES) {
        return ['ok' => false, 'error' => 'File is too large. Maximum size is 15 MB.'];
    }

    if (!array_key_exists($kind, BID_ESTIMATE_ATTACHMENT_KINDS)) {
        $kind = 'Other';
    }

    $content = file_get_contents($file['tmp_name']);
    if ($content === false) {
        return ['ok' => false, 'error' => 'Unable to read uploaded file.'];
    }

    try {
        $pdo = db();
        $fileName = (string) ($file['name'] ?? 'attachment');
        $contentType = (string) ($file['type'] ?? 'application/octet-stream');

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.BidEstimateAttachment (
                BidEstimateID, FileName, ContentType, FileSizeBytes, FileData, BlobPath,
                AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:bid_id, :name, :type, :size, NULL, NULL, :kind, :user)
        SQL);
        $stmt->bindValue(':bid_id', $bidEstimateId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', (int) $file['size'], PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $stored = attachment_storage_save('bid-estimate', $bidEstimateId, $id, $fileName, $contentType, $content);
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.BidEstimateAttachment WHERE AttachmentID = :id')->execute(['id' => $id]);

            return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to save attachment to blob storage.'];
        }

        $pdo->prepare('UPDATE dbo.BidEstimateAttachment SET BlobPath = :path, FileData = NULL WHERE AttachmentID = :id')
            ->execute(['path' => $stored['blob_path'], 'id' => $id]);

        return ['ok' => true, 'error' => null, 'id' => $id];
    } catch (Throwable $e) {
        error_log('bid_estimate_save_attachment: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to save attachment. Please try again.'];
    }
}

function bid_estimate_attachment_content(array $attachment): array
{
    $blobPath = trim((string) ($attachment['BlobPath'] ?? ''));
    if ($blobPath !== '') {
        return attachment_storage_read($blobPath);
    }

    $bytes = attachment_storage_row_file_bytes($attachment);
    if ($bytes === '') {
        return ['ok' => false, 'error' => 'Attachment file is empty.', 'content' => '', 'content_type' => ''];
    }

    return [
        'ok'           => true,
        'error'        => null,
        'content'      => $bytes,
        'content_type' => (string) ($attachment['ContentType'] ?? 'application/octet-stream'),
    ];
}

function bid_copy_attachment_to_supplier_invoice(int $invoiceId, array $attachment, string $kind = 'Supporting'): array
{
    $contentResult = bid_estimate_attachment_content($attachment);
    if (!$contentResult['ok']) {
        return ['ok' => false, 'error' => $contentResult['error'] ?? 'Unable to read bid attachment.'];
    }

    $content = (string) $contentResult['content'];
    $fileName = (string) ($attachment['FileName'] ?? 'bid-attachment');
    $contentType = (string) ($contentResult['content_type'] ?: ($attachment['ContentType'] ?? 'application/octet-stream'));
    $size = strlen($content);

    try {
        $pdo = db();
        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.SupplierInvoiceAttachment (
                SupplierInvoiceID, FileName, ContentType, FileSizeBytes, FileData, BlobPath,
                AttachmentKind, UploadedByUser
            )
            OUTPUT INSERTED.AttachmentID AS inserted_id
            VALUES (:invoice_id, :name, :type, :size, NULL, NULL, :kind, :user)
        SQL);
        $stmt->bindValue(':invoice_id', $invoiceId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $fileName);
        $stmt->bindValue(':type', $contentType);
        $stmt->bindValue(':size', $size, PDO::PARAM_INT);
        $stmt->bindValue(':kind', $kind);
        $stmt->bindValue(':user', auth_user()['UserID'] ?? 0, PDO::PARAM_INT);
        $stmt->execute();

        $id = db_fetch_inserted_int($stmt, 'inserted_id');
        $stored = attachment_storage_save('supplier-invoice', $invoiceId, $id, $fileName, $contentType, $content);
        if (!$stored['ok']) {
            $pdo->prepare('DELETE FROM dbo.SupplierInvoiceAttachment WHERE AttachmentID = :id')->execute(['id' => $id]);

            return ['ok' => false, 'error' => $stored['error'] ?? 'Unable to copy attachment to invoice.'];
        }

        $pdo->prepare('UPDATE dbo.SupplierInvoiceAttachment SET BlobPath = :path, FileData = NULL WHERE AttachmentID = :id')
            ->execute(['path' => $stored['blob_path'], 'id' => $id]);

        return ['ok' => true, 'error' => null, 'id' => $id];
    } catch (Throwable $e) {
        error_log('bid_copy_attachment_to_supplier_invoice: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to copy bid attachment to the supplier invoice.'];
    }
}

function bid_award_estimate(int $bidEstimateId): array
{
    if (!bid_can_update()) {
        return ['ok' => false, 'error' => 'You do not have permission to award bids.'];
    }

    $bid = bid_estimate_get($bidEstimateId);
    if ($bid === null) {
        return ['ok' => false, 'error' => 'Bid not found.'];
    }

    if ((string) $bid['Status'] === 'Selected' && !empty($bid['AwardedSupplierInvoiceID'])) {
        return [
            'ok'          => true,
            'error'       => null,
            'already'     => true,
            'invoice_id'  => (int) $bid['AwardedSupplierInvoiceID'],
            'supplier_id' => !empty($bid['SupplierID']) ? (int) $bid['SupplierID'] : null,
        ];
    }

    if (in_array((string) $bid['InitiativeStatus'], ['Cancelled', 'Closed'], true)) {
        return ['ok' => false, 'error' => 'This initiative cannot accept an award in its current status.'];
    }

    $pdo = db();
    $actorId = auth_user()['UserID'] ?? null;

    try {
        $supplierId = !empty($bid['SupplierID']) ? (int) $bid['SupplierID'] : null;
        if ($supplierId === null) {
            $created = supplier_save([
                'supplier_name'  => (string) $bid['VendorName'],
                'contact_name'   => (string) ($bid['ContactName'] ?? ''),
                'contact_email'  => (string) ($bid['ContactEmail'] ?? ''),
                'contact_phone'  => (string) ($bid['ContactPhone'] ?? ''),
                'supplier_type'  => bid_category_to_supplier_type($bid['InitiativeCategory'] ?? null),
                'notes'          => 'Created from awarded bid on ' . (string) $bid['InitiativeNumber'],
                'is_active'      => '1',
            ]);
            if (!$created['ok']) {
                return ['ok' => false, 'error' => $created['error'] ?? 'Unable to create supplier from bid.'];
            }
            $supplierId = (int) $created['id'];
        }

        $memo = 'Draft/estimate from initiative ' . (string) $bid['InitiativeNumber']
            . ' — ' . (string) $bid['InitiativeTitle'];
        $privateNote = 'Created by Initiatives & Bids award. Payment request should be created only after goods/services are delivered.';

        $invoiceResult = supplier_invoice_save([
            'supplier_id'            => (string) $supplierId,
            'po_id'                  => '',
            'doc_number'             => '',
            'txn_date'               => date('Y-m-d'),
            'due_date'               => '',
            'ap_account_ref_value'   => '',
            'ap_account_ref_name'    => '',
            'currency_ref_value'     => (string) ($bid['CurrencyCode'] ?? 'USD'),
            'global_tax_calculation' => '',
            'private_note'           => $privateNote,
            'memo'                   => $memo,
            'lines'                  => [[
                'description'       => (string) $bid['InitiativeTitle'],
                'amount'            => (string) $bid['BidAmount'],
                'detail_type'       => 'AccountBasedExpenseLineDetail',
                // Draft estimate: Accounting assigns the real QBO account before submit/post.
                'account_ref_value' => 'PENDING',
                'account_ref_name'  => 'To be assigned by Accounting',
                'item_ref_value'    => '',
                'item_ref_name'     => '',
                'qty'               => '',
                'unit_price'        => '',
            ]],
        ]);

        if (!$invoiceResult['ok']) {
            return ['ok' => false, 'error' => $invoiceResult['error'] ?? 'Unable to create draft supplier invoice.'];
        }

        $invoiceId = (int) $invoiceResult['id'];

        $attachments = bid_estimate_list_attachments($bidEstimateId);
        foreach ($attachments as $meta) {
            $full = bid_estimate_get_attachment((int) $meta['AttachmentID']);
            if ($full === null) {
                continue;
            }
            $kind = ((string) ($full['AttachmentKind'] ?? '') === 'Invoice') ? 'InvoicePDF' : 'Supporting';
            $copied = bid_copy_attachment_to_supplier_invoice($invoiceId, $full, $kind);
            if (!$copied['ok']) {
                return ['ok' => false, 'error' => $copied['error']];
            }
        }

        $pdo->prepare(<<<SQL
            UPDATE dbo.BidEstimate
            SET Status = N'Not Selected',
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :actor
            WHERE InitiativeID = :initiative_id
              AND BidEstimateID <> :bid_id
              AND Status <> N'Withdrawn'
        SQL)->execute([
            'actor'         => $actorId,
            'initiative_id' => (int) $bid['InitiativeID'],
            'bid_id'        => $bidEstimateId,
        ]);

        $pdo->prepare(<<<SQL
            UPDATE dbo.BidEstimate
            SET Status = N'Selected',
                SupplierID = :supplier_id,
                AwardedSupplierInvoiceID = :invoice_id,
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :actor
            WHERE BidEstimateID = :bid_id
        SQL)->execute([
            'supplier_id' => $supplierId,
            'invoice_id'  => $invoiceId,
            'actor'       => $actorId,
            'bid_id'      => $bidEstimateId,
        ]);

        $pdo->prepare(<<<SQL
            UPDATE dbo.BidInitiative
            SET Status = N'Awarded',
                ModifiedDate = SYSUTCDATETIME(),
                ModifiedByUser = :actor
            WHERE InitiativeID = :id
        SQL)->execute([
            'actor' => $actorId,
            'id'    => (int) $bid['InitiativeID'],
        ]);

        return [
            'ok'          => true,
            'error'       => null,
            'invoice_id'  => $invoiceId,
            'supplier_id' => $supplierId,
        ];
    } catch (Throwable $e) {
        error_log('bid_award_estimate: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Unable to award bid. Please try again.'];
    }
}

function bid_list_owner_options(): array
{
    $pdo = db();
    $stmt = $pdo->query(<<<SQL
        SELECT UserID, UserName
        FROM dbo.[User]
        ORDER BY UserName
    SQL);

    return $stmt->fetchAll();
}
