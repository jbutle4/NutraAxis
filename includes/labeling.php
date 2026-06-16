<?php

require_once __DIR__ . '/auth.php';

const LABEL_PERMISSION_COLUMN = 'LabelingOperations';

const LABEL_SCOPES = ['Customer', 'Internal'];
const LABEL_TEMPLATE_STATUSES = ['Active', 'Draft', 'Retired'];
const LABEL_VERSION_STATUSES = ['Draft', 'Approved', 'Superseded'];
const LABEL_RUN_STATUSES = ['Planned', 'In Progress', 'Completed', 'Cancelled'];
const LABEL_PRINT_STATUSES = ['Ordered', 'In Production', 'Shipped', 'Received', 'Cancelled'];
const LABEL_REVIEW_SUBJECTS = [
    'BatchPrintOrder' => 'Batch Print Order',
    'LabelOrderRun'   => 'Label Order Run',
    'WhiteLabelOrder' => 'White Label Production Order',
    'LabelTemplate'   => 'Label Template',
];
const LABEL_REVIEW_STATUSES = ['Pending', 'In Review', 'Approved', 'Rejected'];
const WL_ORDER_STATUSES = ['Received', 'In Production', 'Labeling', 'Ready to Ship', 'Shipped', 'Cancelled'];
const WL_LINE_STATUSES = ['Open', 'In Production', 'Labeling', 'Complete', 'Cancelled'];

const LABEL_TEMPLATE_LIST_SORT_COLUMNS = [
    'scope'   => 'Scope',
    'customer'=> 'Customer',
    'sku'     => 'SKU',
    'name'    => 'Label Name',
    'version' => 'Version',
    'status'  => 'Status',
];

const LABEL_TEMPLATE_LIST_SORT_SQL = [
    'scope'    => 't.LabelScope',
    'customer' => 't.CustomerName',
    'sku'      => 't.SKU',
    'name'     => 't.LabelName',
    'version'  => 't.CurrentVersionNo',
    'status'   => 't.TemplateStatus',
];

const LABEL_VERSION_LIST_SORT_COLUMNS = [
    'version'  => 'Version',
    'scope'    => 'Scope',
    'customer' => 'Customer',
    'sku'      => 'SKU',
    'label'    => 'Label',
    'status'   => 'Status',
    'notes'    => 'Revision Notes',
    'created'  => 'Created',
];

const LABEL_VERSION_LIST_SORT_SQL = [
    'version'  => 'v.VersionNumber',
    'scope'    => 't.LabelScope',
    'customer' => 't.CustomerName',
    'sku'      => 't.SKU',
    'label'    => 't.LabelName',
    'status'   => 'v.VersionStatus',
    'notes'    => 'v.RevisionNotes',
    'created'  => 'v.CreateDate',
];

const LABEL_RUN_LIST_SORT_COLUMNS = [
    'run_number'    => 'Run Number',
    'run_date'      => 'Run Date',
    'status'        => 'Status',
    'print_orders'  => 'Print Orders',
    'created_by'    => 'Created By',
];

const LABEL_RUN_LIST_SORT_SQL = [
    'run_number'   => 'r.RunNumber',
    'run_date'     => 'r.RunDate',
    'status'       => 'r.RunStatus',
    'print_orders' => 'PrintOrderCount',
    'created_by'   => 'u.UserName',
];

const LABEL_RUN_LIST_SORT_NUMERIC = ['print_orders'];

const LABEL_PRINT_LIST_SORT_COLUMNS = [
    'vendor'            => 'Vendor',
    'vendor_order'      => 'Vendor Order #',
    'run'               => 'Label Order Run',
    'order_date'        => 'Order Date',
    'status'            => 'Status',
    'expected_delivery' => 'Expected Delivery',
];

const LABEL_PRINT_LIST_SORT_SQL = [
    'vendor'            => 'p.VendorName',
    'vendor_order'      => 'p.VendorOrderNumber',
    'run'               => 'r.RunNumber',
    'order_date'        => 'p.OrderDate',
    'status'            => 'p.OrderStatus',
    'expected_delivery' => 'p.ExpectedDeliveryDate',
];

const LABEL_COMPLIANCE_LIST_SORT_COLUMNS = [
    'date'       => 'Date',
    'subject'    => 'Subject',
    'record_id'  => 'Record ID',
    'status'     => 'Status',
    'reviewer'   => 'Reviewer',
    'comments'   => 'Comments',
];

const LABEL_COMPLIANCE_LIST_SORT_SQL = [
    'date'      => 'r.ReviewDate',
    'subject'   => 'r.ReviewSubject',
    'record_id' => 'r.SubjectID',
    'status'    => 'r.ReviewStatus',
    'reviewer'  => 'r.ReviewerName',
    'comments'  => 'r.Comments',
];

const LABEL_COMPLIANCE_LIST_SORT_NUMERIC = ['record_id'];

const WL_LIST_SORT_COLUMNS = [
    'adobe_order_id' => 'Adobe Order ID',
    'order_number'   => 'Order Number',
    'customer'       => 'Customer',
    'order_date'     => 'Order Date',
    'status'         => 'Status',
    'lines'          => 'Lines',
    'imported'       => 'Imported',
];

const WL_LIST_SORT_SQL = [
    'adobe_order_id' => 'o.ExternalOrderID',
    'order_number'   => 'o.ExternalOrderNumber',
    'customer'       => 'o.CustomerName',
    'order_date'     => 'o.OrderDate',
    'status'         => 'o.OrderStatus',
    'lines'          => 'LineCount',
    'imported'       => 'o.ImportedDate',
];

const WL_LIST_SORT_NUMERIC = ['lines'];

function label_module_title(): string
{
    return 'Custom Order Fulfillment Operations';
}

function label_page_title(string $section): string
{
    return $section . ' | ' . label_module_title();
}

function label_hub_areas(): array
{
    return [
        [
            'href'  => '/labeling-operations/templates/',
            'title' => 'Label Templates',
            'desc'  => 'Track labels for each customer and SKU, plus internal label definitions.',
        ],
        [
            'href'  => '/labeling-operations/batch-printing/',
            'title' => 'Label Batch Printing',
            'desc'  => 'Track third-party print orders associated with label order runs.',
        ],
        [
            'href'  => '/labeling-operations/compliance/',
            'title' => 'Label Compliance Review',
            'desc'  => 'Log approvals and review activity for batch printing and label order production.',
        ],
        [
            'href'  => '/labeling-operations/versions/',
            'title' => 'Label Version Control',
            'desc'  => 'Track label revisions for customer and internal labels.',
        ],
        [
            'href'  => '/labeling-operations/white-label-orders/',
            'title' => 'White Label Production Order',
            'desc'  => 'Track production orders received from Adobe Commerce with header and line detail.',
        ],
        [
            'href'  => '/labeling-operations/one-a-day-pack-batch-order-po/',
            'title' => 'One-A-Day Pack Batch Order PO',
            'desc'  => 'Manage purchase orders for One-A-Day pack batch production runs.',
        ],
        [
            'href'  => '/labeling-operations/one-a-day-pack-inventory/',
            'title' => 'One-A-Day Pack Inventory',
            'desc'  => 'View on-hand and available One-A-Day pack inventory by SKU.',
        ],
        [
            'href'  => '/labeling-operations/one-a-day-pack-demand/',
            'title' => 'One-A-Day Pack Demand',
            'desc'  => 'Review projected and actual demand for One-A-Day pack SKUs.',
        ],
    ];
}

function label_permission_value(): ?string
{
    return auth_permission_value(LABEL_PERMISSION_COLUMN);
}

function label_can_read(): bool
{
    return auth_can_read(LABEL_PERMISSION_COLUMN);
}

function label_can_create(): bool
{
    return auth_can_create(LABEL_PERMISSION_COLUMN);
}

function label_can_update(): bool
{
    return auth_can_update(LABEL_PERMISSION_COLUMN);
}

function label_can_delete(): bool
{
    return auth_can_delete(LABEL_PERMISSION_COLUMN);
}

function label_require_read(): void
{
    auth_require_login();
    if (label_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view ' . label_module_title() . '.');
}

function label_require_create(): void
{
    label_require_read();
    if (label_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create labeling records.');
}

function label_require_update(): void
{
    label_require_read();
    if (label_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update labeling records.');
}

function label_format_date(?string $value): string
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

function label_format_datetime(?string $value): string
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

function label_generate_run_number(PDO $pdo): string
{
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT RunNumber FROM dbo.LabelOrderRun WHERE RunNumber LIKE :prefix ORDER BY RunID DESC");
    $stmt->execute(['prefix' => 'LOR-' . $year . '-%']);
    $last = $stmt->fetchColumn();
    $seq = 1;
    if ($last !== false && preg_match('/LOR-' . $year . '-(\d+)$/', (string) $last, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return sprintf('LOR-%s-%04d', $year, $seq);
}

function label_list_templates(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            t.TemplateID,
            t.LabelScope,
            t.CustomerName,
            t.SKU,
            t.LabelName,
            t.TemplateStatus,
            t.CurrentVersionNo,
            t.CreateDate,
            u.UserName AS CreatedByName
        FROM dbo.LabelTemplate t
        INNER JOIN dbo.[User] u ON u.UserID = t.CreatedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    $scope = $filters['scope'] ?? null;
    if ($scope !== null && $scope !== '') {
        $sql .= ' AND t.LabelScope = :scope';
        $params['scope'] = $scope;
    }

    $search = $filters['q'] ?? null;
    if ($search !== null && $search !== '') {
        $sql .= ' AND (t.CustomerName LIKE :q OR t.SKU LIKE :q OR t.LabelName LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    $sortState = table_sort_state(LABEL_TEMPLATE_LIST_SORT_COLUMNS, 'scope', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LABEL_TEMPLATE_LIST_SORT_SQL, $sortState, 'scope', 'sku');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function label_get_template(int $templateId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT t.*, cu.UserName AS CreatedByName, mu.UserName AS ModifiedByName
        FROM dbo.LabelTemplate t
        INNER JOIN dbo.[User] cu ON cu.UserID = t.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = t.ModifiedbyUser
        WHERE t.TemplateID = :id
    SQL);
    $stmt->execute(['id' => $templateId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function label_save_template(array $input, ?int $templateId = null): array
{
    $scope = trim($input['label_scope'] ?? 'Customer');
    $customerName = trim($input['customer_name'] ?? '');
    $sku = trim($input['sku'] ?? '');
    $labelName = trim($input['label_name'] ?? '');
    $status = trim($input['template_status'] ?? 'Active');
    $notes = trim($input['notes'] ?? '');
    $versionNotes = trim($input['version_notes'] ?? '');
    $actorId = auth_user()['UserID'] ?? null;

    if ($actorId === null || $actorId <= 0) {
        return ['ok' => false, 'error' => 'Your session has expired. Sign in again.'];
    }

    if (!in_array($scope, LABEL_SCOPES, true)) {
        return ['ok' => false, 'error' => 'Select a valid label scope.'];
    }

    if ($scope === 'Customer' && $customerName === '') {
        return ['ok' => false, 'error' => 'Customer name is required for customer labels.'];
    }

    if ($sku === '' || $labelName === '') {
        return ['ok' => false, 'error' => 'SKU and label name are required.'];
    }

    if (!in_array($status, LABEL_TEMPLATE_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid template status.'];
    }

    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if ($templateId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.LabelTemplate (
                    LabelScope, CustomerName, SKU, LabelName, TemplateStatus,
                    CurrentVersionNo, Notes, CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.TemplateID AS inserted_id
                VALUES (
                    :scope, :customer, :sku, :name, :status,
                    N'1.0', :notes, :actor, :actor
                )
            SQL);
            $stmt->execute([
                'scope'    => $scope,
                'customer' => $scope === 'Customer' ? $customerName : null,
                'sku'      => $sku,
                'name'     => $labelName,
                'status'   => $status,
                'notes'    => $notes !== '' ? $notes : null,
                'actor'    => $actorId,
            ]);
            $templateId = db_fetch_inserted_int($stmt, 'inserted_id');

            $version = $pdo->prepare(<<<SQL
                INSERT INTO dbo.LabelTemplateVersion (
                    TemplateID, VersionNumber, RevisionNotes, VersionStatus, CreatedByUser
                )
                VALUES (:template, N'1.0', :notes, N'Draft', :actor)
            SQL);
            $version->execute([
                'template' => $templateId,
                'notes'    => $versionNotes !== '' ? $versionNotes : 'Initial version',
                'actor'    => $actorId,
            ]);
        } else {
            if (label_get_template($templateId) === null) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'Label template not found.'];
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.LabelTemplate
                SET LabelScope = :scope,
                    CustomerName = :customer,
                    SKU = :sku,
                    LabelName = :name,
                    TemplateStatus = :status,
                    Notes = :notes,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE TemplateID = :id
            SQL);
            $stmt->execute([
                'scope'    => $scope,
                'customer' => $scope === 'Customer' ? $customerName : null,
                'sku'      => $sku,
                'name'     => $labelName,
                'status'   => $status,
                'notes'    => $notes !== '' ? $notes : null,
                'actor'    => $actorId,
                'id'       => $templateId,
            ]);
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null, 'id' => $templateId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (stripos($e->getMessage(), 'UX_LabelTemplate') !== false || stripos($e->getMessage(), 'duplicate') !== false) {
            return ['ok' => false, 'error' => 'A label template already exists for this customer and SKU.'];
        }

        return ['ok' => false, 'error' => 'Unable to save label template.'];
    }
}

function label_list_versions(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            v.VersionID,
            v.TemplateID,
            v.VersionNumber,
            v.RevisionNotes,
            v.VersionStatus,
            v.EffectiveDate,
            v.ApprovedDate,
            v.CreateDate,
            t.LabelScope,
            t.CustomerName,
            t.SKU,
            t.LabelName,
            cu.UserName AS CreatedByName,
            au.UserName AS ApprovedByName
        FROM dbo.LabelTemplateVersion v
        INNER JOIN dbo.LabelTemplate t ON t.TemplateID = v.TemplateID
        INNER JOIN dbo.[User] cu ON cu.UserID = v.CreatedByUser
        LEFT JOIN dbo.[User] au ON au.UserID = v.ApprovedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    $search = $filters['q'] ?? null;
    if ($search !== null && $search !== '') {
        $sql .= ' AND (t.CustomerName LIKE :q OR t.SKU LIKE :q OR t.LabelName LIKE :q OR v.VersionNumber LIKE :q)';
        $params['q'] = '%' . $search . '%';
    }

    $sortState = table_sort_state(LABEL_VERSION_LIST_SORT_COLUMNS, 'created', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LABEL_VERSION_LIST_SORT_SQL, $sortState, 'created', 'version');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function label_list_template_versions(int $templateId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT v.*, cu.UserName AS CreatedByName, au.UserName AS ApprovedByName
        FROM dbo.LabelTemplateVersion v
        INNER JOIN dbo.[User] cu ON cu.UserID = v.CreatedByUser
        LEFT JOIN dbo.[User] au ON au.UserID = v.ApprovedByUser
        WHERE v.TemplateID = :id
        ORDER BY v.CreateDate DESC, v.VersionID DESC
    SQL);
    $stmt->execute(['id' => $templateId]);

    return $stmt->fetchAll();
}

function label_list_order_runs(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT r.*, u.UserName AS CreatedByName,
            (SELECT COUNT(*) FROM dbo.BatchPrintOrder p WHERE p.RunID = r.RunID) AS PrintOrderCount
        FROM dbo.LabelOrderRun r
        INNER JOIN dbo.[User] u ON u.UserID = r.CreatedByUser
    SQL;

    $sortState = table_sort_state(LABEL_RUN_LIST_SORT_COLUMNS, 'run_date', 'desc', $filters, 'sort_runs', 'dir_runs');
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LABEL_RUN_LIST_SORT_SQL, $sortState, 'run_date', 'run_number');

    return $pdo->query($sql)->fetchAll();
}

function label_get_order_run(int $runId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM dbo.LabelOrderRun WHERE RunID = :id');
    $stmt->execute(['id' => $runId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function label_save_order_run(array $input, ?int $runId = null): array
{
    $runDate = trim($input['run_date'] ?? '');
    $status = trim($input['run_status'] ?? 'Planned');
    $notes = trim($input['notes'] ?? '');
    $actorId = auth_user()['UserID'] ?? null;

    if ($actorId === null || $runDate === '') {
        return ['ok' => false, 'error' => 'Run date is required.'];
    }

    if (!in_array($status, LABEL_RUN_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid run status.'];
    }

    $pdo = db();

    try {
        if ($runId === null) {
            $runNumber = label_generate_run_number($pdo);
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.LabelOrderRun (RunNumber, RunStatus, RunDate, Notes, CreatedByUser, ModifiedbyUser)
                OUTPUT INSERTED.RunID AS inserted_id
                VALUES (:number, :status, :run_date, :notes, :actor, :actor)
            SQL);
            $stmt->execute([
                'number'   => $runNumber,
                'status'   => $status,
                'run_date' => $runDate,
                'notes'    => $notes !== '' ? $notes : null,
                'actor'    => $actorId,
            ]);
            $runId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.LabelOrderRun
                SET RunStatus = :status, RunDate = :run_date, Notes = :notes,
                    ModifiedDate = SYSUTCDATETIME(), ModifiedbyUser = :actor
                WHERE RunID = :id
            SQL);
            $stmt->execute([
                'status'   => $status,
                'run_date' => $runDate,
                'notes'    => $notes !== '' ? $notes : null,
                'actor'    => $actorId,
                'id'       => $runId,
            ]);
        }

        return ['ok' => true, 'error' => null, 'id' => $runId];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save label order run.'];
    }
}

function label_list_print_orders(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            p.*,
            r.RunNumber,
            u.UserName AS CreatedByName
        FROM dbo.BatchPrintOrder p
        INNER JOIN dbo.LabelOrderRun r ON r.RunID = p.RunID
        INNER JOIN dbo.[User] u ON u.UserID = p.CreatedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    $runId = $filters['run_id'] ?? null;
    if ($runId !== null && (int) $runId > 0) {
        $sql .= ' AND p.RunID = :run';
        $params['run'] = (int) $runId;
    }

    $sortState = table_sort_state(LABEL_PRINT_LIST_SORT_COLUMNS, 'order_date', 'desc', $filters, 'sort_prints', 'dir_prints');
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LABEL_PRINT_LIST_SORT_SQL, $sortState, 'order_date', 'vendor');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function label_save_print_order(array $input, ?int $printOrderId = null): array
{
    $runId = (int) ($input['run_id'] ?? 0);
    $vendorName = trim($input['vendor_name'] ?? '');
    $vendorOrderNumber = trim($input['vendor_order_number'] ?? '');
    $status = trim($input['order_status'] ?? 'Ordered');
    $orderDate = trim($input['order_date'] ?? '');
    $expectedDate = trim($input['expected_delivery_date'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $actorId = auth_user()['UserID'] ?? null;

    if ($runId <= 0 || $vendorName === '' || $orderDate === '') {
        return ['ok' => false, 'error' => 'Label order run, vendor name, and order date are required.'];
    }

    if (label_get_order_run($runId) === null) {
        return ['ok' => false, 'error' => 'Select a valid label order run.'];
    }

    if (!in_array($status, LABEL_PRINT_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid print order status.'];
    }

    $pdo = db();

    try {
        if ($printOrderId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.BatchPrintOrder (
                    RunID, VendorName, VendorOrderNumber, OrderStatus, OrderDate,
                    ExpectedDeliveryDate, Notes, CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.PrintOrderID AS inserted_id
                VALUES (
                    :run, :vendor, :vendor_order, :status, :order_date,
                    :expected, :notes, :actor, :actor
                )
            SQL);
            $stmt->execute([
                'run'          => $runId,
                'vendor'       => $vendorName,
                'vendor_order' => $vendorOrderNumber !== '' ? $vendorOrderNumber : null,
                'status'       => $status,
                'order_date'   => $orderDate,
                'expected'     => $expectedDate !== '' ? $expectedDate : null,
                'notes'        => $notes !== '' ? $notes : null,
                'actor'        => $actorId,
            ]);
            $printOrderId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.BatchPrintOrder
                SET RunID = :run, VendorName = :vendor, VendorOrderNumber = :vendor_order,
                    OrderStatus = :status, OrderDate = :order_date, ExpectedDeliveryDate = :expected,
                    Notes = :notes, ModifiedDate = SYSUTCDATETIME(), ModifiedbyUser = :actor
                WHERE PrintOrderID = :id
            SQL);
            $stmt->execute([
                'run'          => $runId,
                'vendor'       => $vendorName,
                'vendor_order' => $vendorOrderNumber !== '' ? $vendorOrderNumber : null,
                'status'       => $status,
                'order_date'   => $orderDate,
                'expected'     => $expectedDate !== '' ? $expectedDate : null,
                'notes'        => $notes !== '' ? $notes : null,
                'actor'        => $actorId,
                'id'           => $printOrderId,
            ]);
        }

        return ['ok' => true, 'error' => null, 'id' => $printOrderId];
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Unable to save batch print order.'];
    }
}

function label_list_compliance_reviews(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT r.*, u.UserName AS CreatedByName
        FROM dbo.LabelComplianceReview r
        INNER JOIN dbo.[User] u ON u.UserID = r.CreatedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    $subject = $filters['subject'] ?? null;
    if ($subject !== null && $subject !== '') {
        $sql .= ' AND r.ReviewSubject = :subject';
        $params['subject'] = $subject;
    }

    $sortState = table_sort_state(LABEL_COMPLIANCE_LIST_SORT_COLUMNS, 'date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LABEL_COMPLIANCE_LIST_SORT_SQL, $sortState, 'date', 'record_id');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function label_save_compliance_review(array $input): array
{
    $subject = trim($input['review_subject'] ?? '');
    $subjectId = (int) ($input['subject_id'] ?? 0);
    $status = trim($input['review_status'] ?? 'Pending');
    $reviewerName = trim($input['reviewer_name'] ?? '');
    $comments = trim($input['comments'] ?? '');
    $actorId = auth_user()['UserID'] ?? null;

    if (!array_key_exists($subject, LABEL_REVIEW_SUBJECTS) || $subjectId <= 0) {
        return ['ok' => false, 'error' => 'Select a valid review subject and record ID.'];
    }

    if ($reviewerName === '') {
        $reviewerName = (string) (auth_user()['UserName'] ?? 'Reviewer');
    }

    if (!in_array($status, LABEL_REVIEW_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid review status.'];
    }

    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.LabelComplianceReview (
            ReviewSubject, SubjectID, ReviewStatus, ReviewerName, Comments, CreatedByUser
        )
        OUTPUT INSERTED.ReviewID AS inserted_id
        VALUES (:subject, :subject_id, :status, :reviewer, :comments, :actor)
    SQL);
    $stmt->execute([
        'subject'    => $subject,
        'subject_id' => $subjectId,
        'status'     => $status,
        'reviewer'   => $reviewerName,
        'comments'   => $comments !== '' ? $comments : null,
        'actor'      => $actorId,
    ]);

    return ['ok' => true, 'error' => null, 'id' => db_fetch_inserted_int($stmt, 'inserted_id')];
}

function label_review_subject_label(string $subject): string
{
    return LABEL_REVIEW_SUBJECTS[$subject] ?? $subject;
}

function wl_list_orders(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            o.*,
            u.UserName AS CreatedByName,
            (SELECT COUNT(*) FROM dbo.WhiteLabelProductionOrderLine l WHERE l.WLPOID = o.WLPOID) AS LineCount
        FROM dbo.WhiteLabelProductionOrder o
        INNER JOIN dbo.[User] u ON u.UserID = o.CreatedByUser
        WHERE 1 = 1
    SQL;
    $params = [];

    $status = $filters['status'] ?? null;
    if ($status !== null && $status !== '') {
        $sql .= ' AND o.OrderStatus = :status';
        $params['status'] = $status;
    }

    $sortState = table_sort_state(WL_LIST_SORT_COLUMNS, 'order_date', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(WL_LIST_SORT_SQL, $sortState, 'order_date', 'adobe_order_id');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function wl_get_order(int $wlpoId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT o.*, cu.UserName AS CreatedByName, mu.UserName AS ModifiedByName
        FROM dbo.WhiteLabelProductionOrder o
        INNER JOIN dbo.[User] cu ON cu.UserID = o.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = o.ModifiedbyUser
        WHERE o.WLPOID = :id
    SQL);
    $stmt->execute(['id' => $wlpoId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function wl_get_lines(int $wlpoId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT l.*, t.LabelName, t.CustomerName AS TemplateCustomerName
        FROM dbo.WhiteLabelProductionOrderLine l
        LEFT JOIN dbo.LabelTemplate t ON t.TemplateID = l.TemplateID
        WHERE l.WLPOID = :id
        ORDER BY l.LineNumber
    SQL);
    $stmt->execute(['id' => $wlpoId]);

    return $stmt->fetchAll();
}

function wl_parse_lines(array $input): array
{
    $lines = [];
    $rows = $input['lines'] ?? [];

    if (!is_array($rows)) {
        return ['lines' => []];
    }

    $lineNumber = 1;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $sku = trim($row['sku'] ?? '');
        $productName = trim($row['product_name'] ?? '');
        $quantity = (float) ($row['quantity'] ?? 0);
        $templateId = (int) ($row['template_id'] ?? 0);

        if ($sku === '' && $productName === '' && $quantity <= 0) {
            continue;
        }

        if ($sku === '' || $productName === '' || $quantity <= 0) {
            return ['error' => 'Each line item requires SKU, product name, and quantity.'];
        }

        $lines[] = [
            'line_number'  => $lineNumber++,
            'sku'          => $sku,
            'product_name' => $productName,
            'quantity'     => $quantity,
            'template_id'  => $templateId > 0 ? $templateId : null,
            'line_status'  => trim($row['line_status'] ?? 'Open'),
            'notes'        => trim($row['notes'] ?? ''),
        ];
    }

    if ($lines === []) {
        return ['error' => 'Add at least one line item.'];
    }

    return ['lines' => $lines];
}

function wl_save_order(array $input, ?int $wlpoId = null): array
{
    $externalOrderId = trim($input['external_order_id'] ?? '');
    $externalOrderNumber = trim($input['external_order_number'] ?? '');
    $customerName = trim($input['customer_name'] ?? '');
    $orderDate = trim($input['order_date'] ?? '');
    $status = trim($input['order_status'] ?? 'Received');
    $shipByDate = trim($input['ship_by_date'] ?? '');
    $notes = trim($input['notes'] ?? '');
    $actorId = auth_user()['UserID'] ?? null;

    if ($externalOrderId === '' || $customerName === '' || $orderDate === '') {
        return ['ok' => false, 'error' => 'Adobe Commerce order ID, customer name, and order date are required.'];
    }

    if (!in_array($status, WL_ORDER_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Invalid order status.'];
    }

    $parsedLines = wl_parse_lines($input);
    if (isset($parsedLines['error'])) {
        return ['ok' => false, 'error' => $parsedLines['error']];
    }

    $lines = $parsedLines['lines'];
    $pdo = db();

    try {
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if ($wlpoId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.WhiteLabelProductionOrder (
                    ExternalOrderID, ExternalOrderNumber, CustomerName, OrderDate, OrderStatus,
                    ShipByDate, Notes, CreatedByUser, ModifiedbyUser
                )
                OUTPUT INSERTED.WLPOID AS inserted_id
                VALUES (
                    :external_id, :external_number, :customer, :order_date, :status,
                    :ship_by, :notes, :actor, :actor
                )
            SQL);
            $stmt->execute([
                'external_id'     => $externalOrderId,
                'external_number' => $externalOrderNumber !== '' ? $externalOrderNumber : null,
                'customer'        => $customerName,
                'order_date'      => $orderDate,
                'status'          => $status,
                'ship_by'         => $shipByDate !== '' ? $shipByDate : null,
                'notes'           => $notes !== '' ? $notes : null,
                'actor'           => $actorId,
            ]);
            $wlpoId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            if (wl_get_order($wlpoId) === null) {
                $pdo->rollBack();

                return ['ok' => false, 'error' => 'White label production order not found.'];
            }

            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.WhiteLabelProductionOrder
                SET ExternalOrderID = :external_id,
                    ExternalOrderNumber = :external_number,
                    CustomerName = :customer,
                    OrderDate = :order_date,
                    OrderStatus = :status,
                    ShipByDate = :ship_by,
                    Notes = :notes,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE WLPOID = :id
            SQL);
            $stmt->execute([
                'external_id'     => $externalOrderId,
                'external_number' => $externalOrderNumber !== '' ? $externalOrderNumber : null,
                'customer'        => $customerName,
                'order_date'      => $orderDate,
                'status'          => $status,
                'ship_by'         => $shipByDate !== '' ? $shipByDate : null,
                'notes'           => $notes !== '' ? $notes : null,
                'actor'           => $actorId,
                'id'              => $wlpoId,
            ]);

            $pdo->prepare('DELETE FROM dbo.WhiteLabelProductionOrderLine WHERE WLPOID = :id')->execute(['id' => $wlpoId]);
        }

        $lineStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.WhiteLabelProductionOrderLine (
                WLPOID, LineNumber, SKU, ProductName, Quantity, TemplateID, LineStatus, Notes
            )
            VALUES (:order, :line, :sku, :product, :qty, :template, :status, :notes)
        SQL);

        foreach ($lines as $line) {
            $lineStmt->execute([
                'order'    => $wlpoId,
                'line'     => $line['line_number'],
                'sku'      => $line['sku'],
                'product'  => $line['product_name'],
                'qty'      => $line['quantity'],
                'template' => $line['template_id'],
                'status'   => $line['line_status'],
                'notes'    => $line['notes'] !== '' ? $line['notes'] : null,
            ]);
        }

        $pdo->commit();

        return ['ok' => true, 'error' => null, 'id' => $wlpoId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (stripos($e->getMessage(), 'UQ_WhiteLabelProductionOrder') !== false) {
            return ['ok' => false, 'error' => 'This Adobe Commerce order ID has already been imported.'];
        }

        return ['ok' => false, 'error' => 'Unable to save white label production order.'];
    }
}

function label_template_options(): array
{
    $pdo = db();
    $rows = $pdo->query(<<<SQL
        SELECT TemplateID, LabelScope, CustomerName, SKU, LabelName
        FROM dbo.LabelTemplate
        ORDER BY LabelScope, CustomerName, SKU
    SQL)->fetchAll();

    $options = [];
    foreach ($rows as $row) {
        $label = $row['LabelScope'] === 'Customer'
            ? ($row['CustomerName'] . ' · ' . $row['SKU'] . ' · ' . $row['LabelName'])
            : ('Internal · ' . $row['SKU'] . ' · ' . $row['LabelName']);
        $options[] = [
            'id'    => (int) $row['TemplateID'],
            'label' => $label,
        ];
    }

    return $options;
}
