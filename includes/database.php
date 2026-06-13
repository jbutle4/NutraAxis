<?php

require_once __DIR__ . '/env.php';

function db_fetch_inserted_int(PDOStatement $stmt, string $column): int
{
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row) || !array_key_exists($column, $row)) {
        throw new RuntimeException("Insert did not return {$column}.");
    }

    return (int) $row[$column];
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
        'sqlsrv' => "sqlsrv:Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;Connection Timeout={$connectionTimeout}",
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
