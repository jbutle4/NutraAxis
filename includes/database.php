<?php

require_once __DIR__ . '/env.php';

function db_fetch_inserted_int(PDOStatement $stmt, string $column): int
{
    // sqlsrv often returns OUTPUT rows on a later result set, and column aliases may differ by case.
    do {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            continue;
        }

        if (array_key_exists($column, $row)) {
            return (int) $row[$column];
        }

        foreach ($row as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return (int) $value;
            }
        }
    } while ($stmt->nextRowset());

    throw new RuntimeException("Insert did not return {$column}.");
}

/**
 * Build OR'd LIKE predicates with unique parameter names.
 * PDO ODBC rejects reused names like multiple ":q" placeholders (COUNT field incorrect).
 *
 * @param list<string> $columns SQL column/expression list
 * @return array{0: string, 1: array<string, string>}
 */
function db_like_or(array $columns, string $term, string $paramPrefix = 'q'): array
{
    $parts = [];
    $params = [];
    $index = 0;

    foreach ($columns as $column) {
        $index++;
        $name = $paramPrefix . $index;
        $parts[] = "{$column} LIKE :{$name}";
        $params[$name] = '%' . $term . '%';
    }

    if ($parts === []) {
        return ['(1 = 0)', []];
    }

    return ['(' . implode(' OR ', $parts) . ')', $params];
}

function db_bind_value(PDOStatement $stmt, string $param, mixed $value, ?int $type = null): void
{
    if ($value === null) {
        $stmt->bindValue($param, null, PDO::PARAM_NULL);

        return;
    }

    if ($type !== null) {
        $stmt->bindValue($param, $value, $type);

        return;
    }

    if (is_int($value)) {
        $stmt->bindValue($param, $value, PDO::PARAM_INT);

        return;
    }

    if (is_bool($value)) {
        $stmt->bindValue($param, $value, PDO::PARAM_BOOL);

        return;
    }

    $stmt->bindValue($param, $value);
}

function db_apply_sql_server_options(PDO $pdo): void
{
    foreach ([
        'SET ANSI_NULLS ON',
        'SET ANSI_PADDING ON',
        'SET ANSI_WARNINGS ON',
        'SET CONCAT_NULL_YIELDS_NULL ON',
    ] as $sql) {
        $pdo->exec($sql);
    }
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_first(['DB_HOST', 'DB_SERVER'], '');
    $name = env('DB_NAME', '');
    $user = env('DB_USER', '');
    $pass = env_first(['DB_PASS', 'DB_PASSWORD'], '');
    $port = env('DB_PORT', '1433');

    if ($host === '' || $name === '' || $user === '' || $pass === '') {
        throw new RuntimeException('Database credentials are not configured.');
    }

    $connectionTimeout = 5;
    $drivers = [
        'sqlsrv' => "sqlsrv:Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;LoginTimeout={$connectionTimeout}",
        'dblib'  => "dblib:host={$host}:{$port};dbname={$name};charset=UTF-8",
        'odbc18' => "odbc:Driver={ODBC Driver 18 for SQL Server};Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;Connection Timeout={$connectionTimeout};",
        'odbc17' => "odbc:Driver={ODBC Driver 17 for SQL Server};Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;Connection Timeout={$connectionTimeout};",
    ];

    $attempts = [];
    if (in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        $attempts[] = $drivers['sqlsrv'];
    }
    if (in_array('dblib', PDO::getAvailableDrivers(), true)) {
        $attempts[] = $drivers['dblib'];
    }
    if (in_array('odbc', PDO::getAvailableDrivers(), true)) {
        $attempts[] = $drivers['odbc18'];
        $attempts[] = $drivers['odbc17'];
    }

    if ($attempts === []) {
        throw new RuntimeException('No SQL Server PDO driver is available.');
    }

    $lastError = null;
    foreach ($attempts as $dsn) {
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'odbc') {
                // ODBC on Azure compares bound params strictly; emulate prepares avoids type mismatches.
                $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            }

            db_apply_sql_server_options($pdo);

            return $pdo;
        } catch (Throwable $e) {
            $lastError = $e;
        }
    }

    throw new RuntimeException('Database connection failed: ' . $lastError?->getMessage());
}
