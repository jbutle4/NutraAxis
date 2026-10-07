<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-issues.php';

auth_require_module_read('marketing-issues');

$filters = [
    'tab'      => isset(MKT_ISSUE_TABS[$_GET['tab'] ?? '']) ? (string) $_GET['tab'] : 'open',
    'status'   => (string) ($_GET['status'] ?? ''),
    'severity' => (string) ($_GET['severity'] ?? ''),
    'owner'    => (string) ($_GET['owner'] ?? ''),
    'q'        => trim((string) ($_GET['q'] ?? '')),
];
$stamp = gmdate('Y-m-d');

if (($_GET['format'] ?? '') === 'md') {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="site-issues-developer-packet-' . $stamp . '.md"');
    echo mkt_issue_packet_markdown($filters);
    exit;
}

$rows = mkt_issue_export_rows($filters, $filters['tab'] !== 'open');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="site-issues-' . $filters['tab'] . '-' . $stamp . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
$headers = $rows !== [] ? array_keys($rows[0]) : ['Issue ID', 'Issue', 'Code', 'Severity', 'Owner', 'Status', 'URL', 'URL status', 'Detail', 'Found by', 'First seen', 'Last seen', 'What to do'];
fputcsv($out, $headers, ',', '"', '');
foreach ($rows as $row) {
    fputcsv($out, array_map(static fn($v): string => preg_match('/^[=+\-@]/', (string) $v) ? "'" . $v : (string) $v, $row), ',', '"', '');
}
fclose($out);
