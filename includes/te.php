<?php

require_once __DIR__ . '/auth.php';

const TE_PERMISSION_COLUMN = 'TEManagement';
const TE_APPROVAL_PERMISSION_COLUMN = 'TEApproval';

const TE_STATUSES = [
    'Created',
    'Submitted for Approval',
    'Rejected',
    'Approved',
    'Sent Back for Comment',
    'Viewed by Approver',
];

const TE_EDITABLE_STATUSES = ['Created', 'Sent Back for Comment', 'Rejected'];

const TE_MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

const TE_EXPENSE_CATEGORIES = [
    'air'              => 'Air',
    'hotel'            => 'Hotel',
    'home_office'      => 'Home Office',
    'cell'             => 'Cell',
    'rental_car_fuel'  => 'Rental Car & Fuel',
    'taxi'             => 'Taxi',
    'parking_tolls'    => 'Parking / Tolls',
    'mileage'          => 'Total Mileage',
    'entertainment'    => 'Entertainment',
    'travel_meals'     => 'Travel Meals',
    'shipping_postage' => 'Shipping / Postage',
    'office_supplies'  => 'Office Supplies',
    'misc'             => 'Misc.',
];

const TE_LIST_SORT_COLUMNS = [
    'report_number' => 'Report #',
    'employee'      => 'Employee',
    'status'        => 'Status',
    'period'        => 'Period',
    'total'         => 'Total Due',
    'submitted'     => 'Submitted',
];

const TE_LIST_SORT_SQL = [
    'report_number' => 'r.ReportNumber',
    'employee'      => 'eu.UserName',
    'status'        => 'r.ReportStatus',
    'period'        => 'r.PeriodStart',
    'total'         => 'r.TotalReimbursementDue',
    'submitted'     => 'r.SubmittedAt',
];

const TE_LIST_SORT_NUMERIC = ['total'];

function te_permission_value(): ?string
{
    return auth_permission_value(TE_PERMISSION_COLUMN);
}

function te_approval_permission_value(): ?string
{
    return auth_permission_value(TE_APPROVAL_PERMISSION_COLUMN);
}

function te_can_read(): bool
{
    return permission_can_read(te_permission_value()) || te_can_read_approval_queue();
}

function te_can_create(): bool
{
    return permission_can_create(te_permission_value());
}

function te_can_update(): bool
{
    return permission_can_update(te_permission_value());
}

function te_can_delete(): bool
{
    return permission_can_delete(te_permission_value());
}

function te_can_read_approval_queue(): bool
{
    return permission_can_read(te_approval_permission_value());
}

function te_can_take_approval_action(): bool
{
    return permission_can_update(te_approval_permission_value());
}

function te_can_access_pages(): bool
{
    return te_can_read();
}

function te_require_read(): void
{
    auth_require_login();
    if (te_can_access_pages()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Travel & Expense reports.');
}

function te_require_create(): void
{
    te_require_read();
    if (te_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create Travel & Expense reports.');
}

function te_require_update(): void
{
    te_require_read();
    if (te_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update Travel & Expense reports.');
}

function te_can_edit_report(array $report): bool
{
    if (!te_can_update()) {
        return false;
    }

    $status = (string) ($report['ReportStatus'] ?? '');
    if (!in_array($status, TE_EDITABLE_STATUSES, true)) {
        return false;
    }

    $userId = (int) (auth_user()['UserID'] ?? 0);
    if ((int) ($report['EmployeeUserID'] ?? 0) === $userId) {
        return true;
    }

    return te_can_delete();
}

function te_format_date(?string $value): string
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

function te_format_money($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    return '$' . number_format((float) $value, 2);
}

function te_status_class(string $status): string
{
    return match ($status) {
        'Approved'                 => 'is-success',
        'Submitted for Approval'   => 'is-warning',
        'Rejected'                 => 'is-error',
        'Sent Back for Comment'    => 'is-warning',
        default                    => '',
    };
}

function te_format_exception_message(Throwable $e, string $action): string
{
    error_log('te: ' . $e->getMessage());

    return 'Unable to ' . $action . '. Please try again or contact support.';
}

function te_next_report_number(): string
{
    $pdo = db();
    $year = date('Y');
    $prefix = 'TE-' . $year . '-';
    $stmt = $pdo->prepare(<<<SQL
        SELECT TOP 1 ReportNumber
        FROM dbo.TEReport
        WHERE ReportNumber LIKE :prefix
        ORDER BY ReportNumber DESC
    SQL);
    $stmt->execute(['prefix' => $prefix . '%']);
    $row = $stmt->fetch();

    $seq = 1;
    if ($row !== false) {
        $last = (string) $row['ReportNumber'];
        $suffix = substr($last, strlen($prefix));
        if (ctype_digit($suffix)) {
            $seq = (int) $suffix + 1;
        }
    }

    return sprintf('%s%06d', $prefix, $seq);
}

function te_list_reports(array $filters = []): array
{
    $pdo = db();
    $sql = <<<SQL
        SELECT
            r.ReportID,
            r.ReportNumber,
            r.ReportStatus,
            r.PeriodStart,
            r.PeriodEnd,
            r.TotalReimbursementDue,
            r.SubmittedAt,
            r.CreateDate,
            eu.UserName AS EmployeeName,
            eu.UserLogin AS EmployeeEmail
        FROM dbo.TEReport r
        INNER JOIN dbo.[User] eu ON eu.UserID = r.EmployeeUserID
    SQL;

    $params = [];
    $clauses = [];

    $status = $filters['status'] ?? null;
    if ($status !== null && $status !== '' && in_array($status, TE_STATUSES, true)) {
        $clauses[] = 'r.ReportStatus = :status';
        $params['status'] = $status;
    }

    $employeeId = $filters['employee_user_id'] ?? null;
    if ($employeeId !== null && (int) $employeeId > 0) {
        $clauses[] = 'r.EmployeeUserID = :employee';
        $params['employee'] = (int) $employeeId;
    } elseif (!te_can_delete() && !te_can_read_approval_queue()) {
        $userId = (int) (auth_user()['UserID'] ?? 0);
        if ($userId > 0) {
            $clauses[] = 'r.EmployeeUserID = :self';
            $params['self'] = $userId;
        }
    }

    if ($clauses !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $clauses);
    }

    $sortState = table_sort_state(TE_LIST_SORT_COLUMNS, 'submitted', 'desc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(TE_LIST_SORT_SQL, $sortState, 'submitted', 'report_number');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function te_get_report(int $reportId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            r.*,
            eu.UserName AS EmployeeName,
            eu.UserLogin AS EmployeeEmail,
            cu.UserName AS CreatedByName,
            mu.UserName AS ModifiedByName
        FROM dbo.TEReport r
        INNER JOIN dbo.[User] eu ON eu.UserID = r.EmployeeUserID
        LEFT JOIN dbo.[User] cu ON cu.UserID = r.CreatedByUser
        LEFT JOIN dbo.[User] mu ON mu.UserID = r.ModifiedByUser
        WHERE r.ReportID = :id
    SQL);
    $stmt->execute(['id' => $reportId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function te_get_expense_lines(int $reportId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.TEExpenseLine
        WHERE ReportID = :id
        ORDER BY SortOrder, LineID
    SQL);
    $stmt->execute(['id' => $reportId]);

    return $stmt->fetchAll();
}

function te_get_mileage_lines(int $reportId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.TEMileageLine
        WHERE ReportID = :id
        ORDER BY SortOrder, LineID
    SQL);
    $stmt->execute(['id' => $reportId]);

    return $stmt->fetchAll();
}

function te_get_entertainment_lines(int $reportId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.TEEntertainmentLine
        WHERE ReportID = :id
        ORDER BY SortOrder, LineID
    SQL);
    $stmt->execute(['id' => $reportId]);

    return $stmt->fetchAll();
}

function te_get_misc_lines(int $reportId): array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT *
        FROM dbo.TEMiscLine
        WHERE ReportID = :id
        ORDER BY SortOrder, LineID
    SQL);
    $stmt->execute(['id' => $reportId]);

    return $stmt->fetchAll();
}

function te_parse_amount($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);

    return round((float) $clean, 2);
}

function te_expense_line_total(array $line): float
{
    return round(
        te_parse_amount($line['AmountAir'] ?? 0)
        + te_parse_amount($line['AmountHotel'] ?? 0)
        + te_parse_amount($line['AmountHomeOffice'] ?? 0)
        + te_parse_amount($line['AmountCell'] ?? 0)
        + te_parse_amount($line['AmountRentalCarFuel'] ?? 0)
        + te_parse_amount($line['AmountTaxi'] ?? 0)
        + te_parse_amount($line['AmountParkingTolls'] ?? 0)
        + te_parse_amount($line['AmountMileage'] ?? 0)
        + te_parse_amount($line['AmountEntertainment'] ?? 0)
        + te_parse_amount($line['AmountTravelMeals'] ?? 0)
        + te_parse_amount($line['AmountShippingPostage'] ?? 0)
        + te_parse_amount($line['AmountOfficeSupplies'] ?? 0)
        + te_parse_amount($line['AmountMisc'] ?? 0),
        2
    );
}

function te_calculate_totals(int $reportId, float $mileageRate): array
{
    $expenseTotal = 0.0;
    foreach (te_get_expense_lines($reportId) as $line) {
        $expenseTotal += te_parse_amount($line['LineTotal'] ?? te_expense_line_total($line));
    }

    $mileageMiles = 0.0;
    foreach (te_get_mileage_lines($reportId) as $line) {
        $mileageMiles += te_parse_amount($line['Miles'] ?? 0);
    }
    $mileageReimbursement = round($mileageMiles * $mileageRate, 2);

    $entertainmentTotal = 0.0;
    foreach (te_get_entertainment_lines($reportId) as $line) {
        $entertainmentTotal += te_parse_amount($line['Amount'] ?? 0);
    }

    $miscTotal = 0.0;
    foreach (te_get_misc_lines($reportId) as $line) {
        $miscTotal += te_parse_amount($line['Amount'] ?? 0);
    }

    $total = round($expenseTotal + $mileageReimbursement + $entertainmentTotal + $miscTotal, 2);

    return [
        'expense_total'         => $expenseTotal,
        'mileage_miles'         => $mileageMiles,
        'mileage_reimbursement' => $mileageReimbursement,
        'entertainment_total'   => $entertainmentTotal,
        'misc_total'            => $miscTotal,
        'total_due'             => $total,
    ];
}

function te_recalculate_report_total(int $reportId): float
{
    $report = te_get_report($reportId);
    if ($report === null) {
        return 0.0;
    }

    $totals = te_calculate_totals($reportId, (float) ($report['MileageRate'] ?? 0.70));
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        UPDATE dbo.TEReport
        SET TotalReimbursementDue = :total,
            ModifiedDate = SYSUTCDATETIME()
        WHERE ReportID = :id
    SQL);
    $stmt->execute([
        'total' => $totals['total_due'],
        'id'    => $reportId,
    ]);

    return $totals['total_due'];
}

function te_save_report(array $input, ?int $reportId = null): array
{
    $userId = (int) (auth_user()['UserID'] ?? 0);
    $periodStart = trim((string) ($input['period_start'] ?? ''));
    $periodEnd = trim((string) ($input['period_end'] ?? ''));
    $mileageRate = te_parse_amount($input['mileage_rate'] ?? 0.70);
    if ($mileageRate <= 0) {
        $mileageRate = 0.70;
    }
    $businessPurpose = trim((string) ($input['business_purpose'] ?? ''));
    $certificationAccepted = !empty($input['certification_accepted']);
    $employeeSignedDate = trim((string) ($input['employee_signed_date'] ?? ''));

    if ($reportId !== null) {
        $existing = te_get_report($reportId);
        if ($existing === null) {
            return ['ok' => false, 'error' => 'Expense report not found.'];
        }
        if (!te_can_edit_report($existing)) {
            return ['ok' => false, 'error' => 'This expense report cannot be edited in its current status.'];
        }
    } elseif (!te_can_create()) {
        return ['ok' => false, 'error' => 'You do not have permission to create expense reports.'];
    }

    if (!$certificationAccepted) {
        return ['ok' => false, 'error' => 'You must certify that expenditures are for legitimate company business only.'];
    }

    try {
        $pdo = db();
        db_apply_sql_server_options($pdo);
        $pdo->beginTransaction();

        if ($reportId === null) {
            $reportNumber = te_next_report_number();
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.TEReport (
                    ReportNumber, ReportStatus, EmployeeUserID,
                    PeriodStart, PeriodEnd, MileageRate,
                    BusinessPurpose, CertificationAccepted, EmployeeSignedDate,
                    CreatedByUser, ModifiedByUser
                )
                OUTPUT INSERTED.ReportID AS inserted_id
                VALUES (
                    :number, N'Created', :employee,
                    :period_start, :period_end, :mileage_rate,
                    :purpose, :cert, :signed,
                    :created_by, :modified_by
                )
            SQL);
            $stmt->execute([
                'number'       => $reportNumber,
                'employee'     => $userId,
                'period_start' => $periodStart !== '' ? $periodStart : null,
                'period_end'   => $periodEnd !== '' ? $periodEnd : null,
                'mileage_rate' => $mileageRate,
                'purpose'      => $businessPurpose !== '' ? $businessPurpose : null,
                'cert'         => 1,
                'signed'       => $employeeSignedDate !== '' ? $employeeSignedDate : null,
                'created_by'   => $userId,
                'modified_by'  => $userId,
            ]);
            $reportId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.TEReport
                SET PeriodStart = :period_start,
                    PeriodEnd = :period_end,
                    MileageRate = :mileage_rate,
                    BusinessPurpose = :purpose,
                    CertificationAccepted = :cert,
                    EmployeeSignedDate = :signed,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedByUser = :modified_by
                WHERE ReportID = :id
            SQL);
            $stmt->execute([
                'period_start' => $periodStart !== '' ? $periodStart : null,
                'period_end'   => $periodEnd !== '' ? $periodEnd : null,
                'mileage_rate' => $mileageRate,
                'purpose'      => $businessPurpose !== '' ? $businessPurpose : null,
                'cert'         => 1,
                'signed'       => $employeeSignedDate !== '' ? $employeeSignedDate : null,
                'modified_by'  => $userId,
                'id'           => $reportId,
            ]);
        }

        te_save_line_sets($pdo, $reportId, $input);
        $pdo->commit();

        te_recalculate_report_total($reportId);

        return ['ok' => true, 'error' => null, 'id' => $reportId];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => te_format_exception_message($e, 'save this expense report')];
    }
}

function te_save_line_sets(PDO $pdo, int $reportId, array $input): void
{
    $pdo->prepare('DELETE FROM dbo.TEExpenseLine WHERE ReportID = :id')->execute(['id' => $reportId]);
    $pdo->prepare('DELETE FROM dbo.TEMileageLine WHERE ReportID = :id')->execute(['id' => $reportId]);
    $pdo->prepare('DELETE FROM dbo.TEEntertainmentLine WHERE ReportID = :id')->execute(['id' => $reportId]);
    $pdo->prepare('DELETE FROM dbo.TEMiscLine WHERE ReportID = :id')->execute(['id' => $reportId]);

    $expenseStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.TEExpenseLine (
            ReportID, SortOrder, LineDate, Description,
            AmountAir, AmountHotel, AmountHomeOffice, AmountCell,
            AmountRentalCarFuel, AmountTaxi, AmountParkingTolls, AmountMileage,
            AmountEntertainment, AmountTravelMeals, AmountShippingPostage,
            AmountOfficeSupplies, AmountMisc, LineTotal
        ) VALUES (
            :report, :sort, :line_date, :description,
            :air, :hotel, :home, :cell,
            :rental, :taxi, :parking, :mileage,
            :ent, :meals, :shipping,
            :supplies, :misc, :total
        )
    SQL);

    foreach (($input['expense_lines'] ?? []) as $index => $line) {
        if (!is_array($line)) {
            continue;
        }
        $desc = trim((string) ($line['description'] ?? ''));
        $lineDate = trim((string) ($line['line_date'] ?? ''));
        $amounts = [
            'air' => te_parse_amount($line['air'] ?? 0),
            'hotel' => te_parse_amount($line['hotel'] ?? 0),
            'home_office' => te_parse_amount($line['home_office'] ?? 0),
            'cell' => te_parse_amount($line['cell'] ?? 0),
            'rental_car_fuel' => te_parse_amount($line['rental_car_fuel'] ?? 0),
            'taxi' => te_parse_amount($line['taxi'] ?? 0),
            'parking_tolls' => te_parse_amount($line['parking_tolls'] ?? 0),
            'mileage' => te_parse_amount($line['mileage'] ?? 0),
            'entertainment' => te_parse_amount($line['entertainment'] ?? 0),
            'travel_meals' => te_parse_amount($line['travel_meals'] ?? 0),
            'shipping_postage' => te_parse_amount($line['shipping_postage'] ?? 0),
            'office_supplies' => te_parse_amount($line['office_supplies'] ?? 0),
            'misc' => te_parse_amount($line['misc'] ?? 0),
        ];
        $lineTotal = array_sum($amounts);
        if ($desc === '' && $lineDate === '' && $lineTotal <= 0) {
            continue;
        }
        $expenseStmt->execute([
            'report'      => $reportId,
            'sort'        => $index,
            'line_date'   => $lineDate !== '' ? $lineDate : null,
            'description' => $desc !== '' ? $desc : null,
            'air'         => $amounts['air'],
            'hotel'       => $amounts['hotel'],
            'home'        => $amounts['home_office'],
            'cell'        => $amounts['cell'],
            'rental'      => $amounts['rental_car_fuel'],
            'taxi'        => $amounts['taxi'],
            'parking'     => $amounts['parking_tolls'],
            'mileage'     => $amounts['mileage'],
            'ent'         => $amounts['entertainment'],
            'meals'       => $amounts['travel_meals'],
            'shipping'    => $amounts['shipping_postage'],
            'supplies'    => $amounts['office_supplies'],
            'misc'        => $amounts['misc'],
            'total'       => $lineTotal,
        ]);
    }

    $mileageStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.TEMileageLine (ReportID, SortOrder, LineDate, FromLocation, ToLocation, BusinessPurpose, Miles)
        VALUES (:report, :sort, :line_date, :from_loc, :to_loc, :purpose, :miles)
    SQL);
    foreach (($input['mileage_lines'] ?? []) as $index => $line) {
        if (!is_array($line)) {
            continue;
        }
        $miles = te_parse_amount($line['miles'] ?? 0);
        $from = trim((string) ($line['from_location'] ?? ''));
        $to = trim((string) ($line['to_location'] ?? ''));
        $purpose = trim((string) ($line['business_purpose'] ?? ''));
        $lineDate = trim((string) ($line['line_date'] ?? ''));
        if ($miles <= 0 && $from === '' && $to === '' && $purpose === '' && $lineDate === '') {
            continue;
        }
        $mileageStmt->execute([
            'report'    => $reportId,
            'sort'      => $index,
            'line_date' => $lineDate !== '' ? $lineDate : null,
            'from_loc'  => $from !== '' ? $from : null,
            'to_loc'    => $to !== '' ? $to : null,
            'purpose'   => $purpose !== '' ? $purpose : null,
            'miles'     => $miles,
        ]);
    }

    $entStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.TEEntertainmentLine (ReportID, SortOrder, LineDate, PersonsEntertained, Place, NaturePurpose, Amount)
        VALUES (:report, :sort, :line_date, :persons, :place, :purpose, :amount)
    SQL);
    foreach (($input['entertainment_lines'] ?? []) as $index => $line) {
        if (!is_array($line)) {
            continue;
        }
        $amount = te_parse_amount($line['amount'] ?? 0);
        $persons = trim((string) ($line['persons'] ?? ''));
        $place = trim((string) ($line['place'] ?? ''));
        $purpose = trim((string) ($line['nature_purpose'] ?? ''));
        $lineDate = trim((string) ($line['line_date'] ?? ''));
        if ($amount <= 0 && $persons === '' && $place === '' && $purpose === '' && $lineDate === '') {
            continue;
        }
        $entStmt->execute([
            'report'    => $reportId,
            'sort'      => $index,
            'line_date' => $lineDate !== '' ? $lineDate : null,
            'persons'   => $persons !== '' ? $persons : null,
            'place'     => $place !== '' ? $place : null,
            'purpose'   => $purpose !== '' ? $purpose : null,
            'amount'    => $amount,
        ]);
    }

    $miscStmt = $pdo->prepare(<<<SQL
        INSERT INTO dbo.TEMiscLine (ReportID, SortOrder, LineDate, Description, NaturePurpose, Amount)
        VALUES (:report, :sort, :line_date, :description, :purpose, :amount)
    SQL);
    foreach (($input['misc_lines'] ?? []) as $index => $line) {
        if (!is_array($line)) {
            continue;
        }
        $amount = te_parse_amount($line['amount'] ?? 0);
        $desc = trim((string) ($line['description'] ?? ''));
        $purpose = trim((string) ($line['nature_purpose'] ?? ''));
        $lineDate = trim((string) ($line['line_date'] ?? ''));
        if ($amount <= 0 && $desc === '' && $purpose === '' && $lineDate === '') {
            continue;
        }
        $miscStmt->execute([
            'report'      => $reportId,
            'sort'        => $index,
            'line_date'   => $lineDate !== '' ? $lineDate : null,
            'description' => $desc !== '' ? $desc : null,
            'purpose'     => $purpose !== '' ? $purpose : null,
            'amount'      => $amount,
        ]);
    }
}

function te_delete_report(int $reportId): array
{
    if (!te_can_delete()) {
        return ['ok' => false, 'error' => 'You do not have permission to delete expense reports.'];
    }

    $report = te_get_report($reportId);
    if ($report === null) {
        return ['ok' => false, 'error' => 'Expense report not found.'];
    }

    if (($report['ReportStatus'] ?? '') !== 'Created') {
        return ['ok' => false, 'error' => 'Only draft expense reports can be deleted.'];
    }

    try {
        $pdo = db();
        $pdo->prepare('DELETE FROM dbo.TEReport WHERE ReportID = :id')->execute(['id' => $reportId]);

        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => te_format_exception_message($e, 'delete this expense report')];
    }
}

function te_default_form(?array $report = null): array
{
    $user = auth_user();

    return [
        'period_start'           => (string) ($report['PeriodStart'] ?? ''),
        'period_end'             => (string) ($report['PeriodEnd'] ?? ''),
        'mileage_rate'           => (string) ($report['MileageRate'] ?? '0.70'),
        'business_purpose'       => (string) ($report['BusinessPurpose'] ?? ''),
        'certification_accepted' => !empty($report['CertificationAccepted']) ? '1' : '',
        'employee_signed_date'   => (string) ($report['EmployeeSignedDate'] ?? date('Y-m-d')),
        'employee_name'          => (string) ($report['EmployeeName'] ?? ($user['UserName'] ?? '')),
        'expense_lines'          => te_get_expense_lines((int) ($report['ReportID'] ?? 0)),
        'mileage_lines'          => te_get_mileage_lines((int) ($report['ReportID'] ?? 0)),
        'entertainment_lines'    => te_get_entertainment_lines((int) ($report['ReportID'] ?? 0)),
        'misc_lines'             => te_get_misc_lines((int) ($report['ReportID'] ?? 0)),
    ];
}

function te_form_from_post(array $post): array
{
    return [
        'period_start'           => $post['period_start'] ?? '',
        'period_end'             => $post['period_end'] ?? '',
        'mileage_rate'           => $post['mileage_rate'] ?? '0.70',
        'business_purpose'       => $post['business_purpose'] ?? '',
        'certification_accepted' => $post['certification_accepted'] ?? '',
        'employee_signed_date'   => $post['employee_signed_date'] ?? '',
        'expense_lines'          => is_array($post['expense_lines'] ?? null) ? $post['expense_lines'] : [],
        'mileage_lines'          => is_array($post['mileage_lines'] ?? null) ? $post['mileage_lines'] : [],
        'entertainment_lines'    => is_array($post['entertainment_lines'] ?? null) ? $post['entertainment_lines'] : [],
        'misc_lines'             => is_array($post['misc_lines'] ?? null) ? $post['misc_lines'] : [],
    ];
}

function te_period_label(array $report): string
{
    $start = te_format_date($report['PeriodStart'] ?? null);
    $end = te_format_date($report['PeriodEnd'] ?? null);
    if ($start === '—' && $end === '—') {
        return '—';
    }
    if ($start !== '—' && $end !== '—' && $start !== $end) {
        return $start . ' – ' . $end;
    }

    return $start !== '—' ? $start : $end;
}

function te_build_summary_text(array $report, array $totals): string
{
    $lines = [
        'Travel & Expense Report ' . ($report['ReportNumber'] ?? ''),
        'Employee: ' . ($report['EmployeeName'] ?? ''),
        'Period: ' . te_period_label($report),
        'Status: ' . ($report['ReportStatus'] ?? ''),
        'Mileage rate: ' . number_format((float) ($report['MileageRate'] ?? 0), 2) . ' per mile',
        'Total reimbursement due: ' . te_format_money($totals['total_due'] ?? $report['TotalReimbursementDue'] ?? 0),
        '',
        'Business purpose:',
        (string) ($report['BusinessPurpose'] ?? ''),
        '',
        'Expense subtotal: ' . te_format_money($totals['expense_total'] ?? 0),
        'Mileage: ' . number_format((float) ($totals['mileage_miles'] ?? 0), 1) . ' miles @ '
            . te_format_money($totals['mileage_reimbursement'] ?? 0),
        'Entertainment subtotal: ' . te_format_money($totals['entertainment_total'] ?? 0),
        'Miscellaneous subtotal: ' . te_format_money($totals['misc_total'] ?? 0),
    ];

    return implode("\n", $lines);
}
