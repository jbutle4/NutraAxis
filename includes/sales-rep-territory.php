<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/sales-reporting.php';
require_once __DIR__ . '/contacts.php';

const SALES_REP_TERRITORY_PERMISSION_COLUMN = 'SalesReporting';

const SALES_REP_TERRITORY_SORT_COLUMNS = [
    'state'      => 'State',
    'zip'        => 'Zip Code',
    'county'     => 'County',
    'rep'        => 'Rep',
    'previous'   => 'Previous Rep',
    'modified'   => 'Date Modified',
];

const SALES_REP_TERRITORY_SORT_SQL = [
    'state'    => 'State',
    'zip'      => 'ZipCode',
    'county'   => 'County',
    'rep'      => 'Rep',
    'previous' => 'PreviousRepAssigned',
    'modified' => 'DateModified',
];

function sales_rep_territory_permission_value(): ?string
{
    return auth_permission_value(SALES_REP_TERRITORY_PERMISSION_COLUMN);
}

function sales_rep_territory_can_read(): bool
{
    return auth_can_read(SALES_REP_TERRITORY_PERMISSION_COLUMN);
}

function sales_rep_territory_can_create(): bool
{
    return auth_can_create(SALES_REP_TERRITORY_PERMISSION_COLUMN);
}

function sales_rep_territory_can_update(): bool
{
    return auth_can_update(SALES_REP_TERRITORY_PERMISSION_COLUMN);
}

function sales_rep_territory_can_delete(): bool
{
    return auth_can_delete(SALES_REP_TERRITORY_PERMISSION_COLUMN);
}

function sales_rep_territory_require_read(): void
{
    auth_require_login();
    if (sales_rep_territory_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Sales Rep Territory Assignments.');
}

function sales_rep_territory_require_create(): void
{
    sales_rep_territory_require_read();
    if (sales_rep_territory_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create Sales Rep Territory Assignments.');
}

function sales_rep_territory_require_update(): void
{
    sales_rep_territory_require_read();
    if (sales_rep_territory_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update Sales Rep Territory Assignments.');
}

function sales_rep_territory_require_delete(): void
{
    sales_rep_territory_require_read();
    if (sales_rep_territory_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete Sales Rep Territory Assignments.');
}

function sales_rep_territory_normalize_state(string $state): string
{
    return strtoupper(trim($state));
}

function sales_rep_territory_normalize_zip(string $zip): string
{
    $zip = trim($zip);
    if ($zip === '') {
        return '';
    }
    if (strcasecmp($zip, 'All') === 0) {
        return 'All';
    }
    if (preg_match('/^\d+(\.0+)?$/', $zip) === 1) {
        $digits = (string) (int) $zip;
        return strlen($digits) < 5 ? str_pad($digits, 5, '0', STR_PAD_LEFT) : $digits;
    }

    return $zip;
}

/**
 * @param array<string, mixed> $filters
 * @return list<array<string, mixed>>
 */
function sales_rep_territory_list(array $filters = []): array
{
    $sortKey = (string) ($filters['sort'] ?? 'state');
    $sortDir = strtolower((string) ($filters['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
    $orderBy = SALES_REP_TERRITORY_SORT_SQL[$sortKey] ?? 'State';

    $sql = <<<SQL
        SELECT
            SalesTeamTerritoryAssignmentID,
            State,
            ZipCode,
            County,
            Rep,
            PreviousRepAssigned,
            DateAdded,
            DateModified
        FROM dbo.SalesTeamTerritoryAssignments
        WHERE 1 = 1
    SQL;
    $params = [];

    $state = sales_rep_territory_normalize_state((string) ($filters['state'] ?? ''));
    if ($state !== '') {
        $sql .= ' AND State = :state';
        $params['state'] = $state;
    }

    $rep = trim((string) ($filters['rep'] ?? ''));
    if ($rep !== '') {
        $sql .= ' AND Rep = :rep';
        $params['rep'] = $rep;
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $sql .= <<<SQL
          AND (
            State LIKE :q
            OR ZipCode LIKE :q
            OR County LIKE :q
            OR Rep LIKE :q
            OR PreviousRepAssigned LIKE :q
          )
        SQL;
        $params['q'] = '%' . $q . '%';
    }

    $orderParts = ["{$orderBy} {$sortDir}"];
    foreach (['State', 'ZipCode', 'County'] as $tiebreaker) {
        if (strcasecmp($tiebreaker, $orderBy) !== 0) {
            $orderParts[] = "{$tiebreaker} ASC";
        }
    }
    $sql .= ' ORDER BY ' . implode(', ', $orderParts);

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll() ?: [];
}

function sales_rep_territory_get(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = db()->prepare(<<<SQL
        SELECT
            SalesTeamTerritoryAssignmentID,
            State,
            ZipCode,
            County,
            Rep,
            PreviousRepAssigned,
            DateAdded,
            DateModified
        FROM dbo.SalesTeamTerritoryAssignments
        WHERE SalesTeamTerritoryAssignmentID = :id
    SQL);
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return list<string>
 */
function sales_rep_territory_distinct_reps(): array
{
    $stmt = db()->query(<<<SQL
        SELECT DISTINCT Rep
        FROM dbo.SalesTeamTerritoryAssignments
        WHERE Rep IS NOT NULL AND Rep <> N''
        ORDER BY Rep
    SQL);

    return array_values(array_filter(array_map(
        static fn($row): string => trim((string) ($row['Rep'] ?? '')),
        $stmt->fetchAll() ?: []
    )));
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: bool, error: ?string, id: ?int}
 */
function sales_rep_territory_save(array $input, ?int $id = null): array
{
    $state = sales_rep_territory_normalize_state((string) ($input['State'] ?? ''));
    $zip = sales_rep_territory_normalize_zip((string) ($input['ZipCode'] ?? ''));
    $county = trim((string) ($input['County'] ?? ''));
    $rep = trim((string) ($input['Rep'] ?? ''));

    if ($state === '' || strlen($state) !== 2) {
        return ['ok' => false, 'error' => 'State is required (2-letter code).', 'id' => null];
    }
    if ($zip === '') {
        return ['ok' => false, 'error' => 'Zip Code is required (use All for statewide).', 'id' => null];
    }
    if ($county === '') {
        return ['ok' => false, 'error' => 'County is required (use All for statewide).', 'id' => null];
    }
    if ($rep === '') {
        return ['ok' => false, 'error' => 'Rep is required.', 'id' => null];
    }
    if (!isset(CONTACT_US_STATES[$state])) {
        return ['ok' => false, 'error' => 'State must be a valid US state code.', 'id' => null];
    }

    $dup = db()->prepare(<<<SQL
        SELECT SalesTeamTerritoryAssignmentID
        FROM dbo.SalesTeamTerritoryAssignments
        WHERE State = :state AND ZipCode = :zip AND County = :county
          AND (:id IS NULL OR SalesTeamTerritoryAssignmentID <> :id)
    SQL);
    $dup->execute([
        'state' => $state,
        'zip'   => $zip,
        'county'=> $county,
        'id'    => $id,
    ]);
    if ($dup->fetch() !== false) {
        return ['ok' => false, 'error' => 'An assignment already exists for this State + Zip + County.', 'id' => null];
    }

    if ($id === null) {
        $stmt = db()->prepare(<<<SQL
            INSERT INTO dbo.SalesTeamTerritoryAssignments (
                State, ZipCode, County, Rep, PreviousRepAssigned
            )
            OUTPUT INSERTED.SalesTeamTerritoryAssignmentID
            VALUES (:state, :zip, :county, :rep, NULL)
        SQL);
        $stmt->execute([
            'state'  => $state,
            'zip'    => $zip,
            'county' => $county,
            'rep'    => $rep,
        ]);
        $newId = (int) $stmt->fetchColumn();

        return ['ok' => true, 'error' => null, 'id' => $newId];
    }

    $existing = sales_rep_territory_get($id);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Assignment not found.', 'id' => null];
    }

    $previousRep = $existing['PreviousRepAssigned'] ?? null;
    $currentRep = trim((string) ($existing['Rep'] ?? ''));
    if ($currentRep !== '' && $currentRep !== $rep) {
        $previousRep = $currentRep;
    }

    $stmt = db()->prepare(<<<SQL
        UPDATE dbo.SalesTeamTerritoryAssignments
        SET State = :state,
            ZipCode = :zip,
            County = :county,
            Rep = :rep,
            PreviousRepAssigned = :previous,
            DateModified = SYSUTCDATETIME()
        WHERE SalesTeamTerritoryAssignmentID = :id
    SQL);
    $stmt->execute([
        'state'    => $state,
        'zip'      => $zip,
        'county'   => $county,
        'rep'      => $rep,
        'previous' => $previousRep,
        'id'       => $id,
    ]);

    return ['ok' => true, 'error' => null, 'id' => $id];
}

/**
 * @return array{ok: bool, error: ?string}
 */
function sales_rep_territory_delete(int $id): array
{
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'Invalid assignment ID.'];
    }
    $stmt = db()->prepare(
        'DELETE FROM dbo.SalesTeamTerritoryAssignments WHERE SalesTeamTerritoryAssignmentID = :id'
    );
    $stmt->execute(['id' => $id]);

    return ['ok' => true, 'error' => null];
}

/**
 * @param array<string, mixed>|null $row
 * @return array{State: string, ZipCode: string, County: string, Rep: string}
 */
function sales_rep_territory_to_form(?array $row = null): array
{
    return [
        'State'   => (string) ($row['State'] ?? ''),
        'ZipCode' => (string) ($row['ZipCode'] ?? ''),
        'County'  => (string) ($row['County'] ?? ''),
        'Rep'     => (string) ($row['Rep'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{State: string, ZipCode: string, County: string, Rep: string}
 */
function sales_rep_territory_from_input(array $input): array
{
    return [
        'State'   => sales_rep_territory_normalize_state((string) ($input['State'] ?? '')),
        'ZipCode' => trim((string) ($input['ZipCode'] ?? '')),
        'County'  => trim((string) ($input['County'] ?? '')),
        'Rep'     => trim((string) ($input['Rep'] ?? '')),
    ];
}
