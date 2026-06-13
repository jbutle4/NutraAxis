<?php
/**
 * Test Azure SQL connection using .env credentials.
 * Usage: php scripts/test-db-connection.php
 */

function loadEnv(string $path): array
{
    $vars = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $vars[trim($key)] = trim($value);
    }
    return $vars;
}

$env = loadEnv(dirname(__DIR__) . '/.env');

$host = $env['DB_HOST'] ?? '';
$name = $env['DB_NAME'] ?? '';
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASS'] ?? '';
$port = $env['DB_PORT'] ?? '1433';

if ($host === '' || $name === '' || $user === '' || $pass === '') {
    fwrite(STDERR, "Missing required DB_* values in .env\n");
    exit(1);
}

echo "Host: {$host}\n";
echo "Database: {$name}\n";
echo "User: {$user}\n";
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . "\n\n";

$attempts = [];

if (in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
    $attempts[] = [
        'label' => 'PDO sqlsrv',
        'dsn' => "sqlsrv:Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;Connection Timeout=15",
    ];
}

if (in_array('dblib', PDO::getAvailableDrivers(), true)) {
    $attempts[] = [
        'label' => 'PDO dblib',
        'dsn' => "dblib:host={$host}:{$port};dbname={$name};charset=UTF-8",
    ];
}

if (in_array('odbc', PDO::getAvailableDrivers(), true)) {
    $attempts[] = [
        'label' => 'PDO ODBC (Driver 18)',
        'dsn' => "odbc:Driver={ODBC Driver 18 for SQL Server};Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;Connection Timeout=15;",
    ];
    $attempts[] = [
        'label' => 'PDO ODBC (Driver 17)',
        'dsn' => "odbc:Driver={ODBC Driver 17 for SQL Server};Server=tcp:{$host},{$port};Database={$name};Encrypt=yes;TrustServerCertificate=no;Connection Timeout=15;",
    ];
}

if ($attempts === []) {
    fwrite(STDERR, "No usable SQL Server PDO drivers found.\n");
    exit(1);
}

foreach ($attempts as $attempt) {
    echo "Trying {$attempt['label']}...\n";
    try {
        $pdo = new PDO($attempt['dsn'], $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 15,
        ]);
        $version = $pdo->query('SELECT @@VERSION AS version')->fetch(PDO::FETCH_ASSOC);
        $dbName = $pdo->query('SELECT DB_NAME() AS db_name')->fetch(PDO::FETCH_ASSOC);
        echo "SUCCESS via {$attempt['label']}\n";
        echo "Connected database: {$dbName['db_name']}\n";
        echo "Server version: " . substr($version['version'], 0, 80) . "...\n";
        exit(0);
    } catch (Throwable $e) {
        echo "FAILED: {$e->getMessage()}\n\n";
    }
}

fwrite(STDERR, "All connection attempts failed.\n");
exit(1);
