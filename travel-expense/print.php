<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/admin.php';
require dirname(__DIR__) . '/includes/te.php';
require dirname(__DIR__) . '/includes/te-approval.php';

te_require_read();

$reportId = (int) ($_GET['id'] ?? 0);
$report = te_get_report($reportId);

if ($report === null) {
    http_response_code(404);
    $pageTitle = 'Report Not Found';
    $bodyClass = 'te-print-page';
    require dirname(__DIR__) . '/includes/head.php';
    echo '<main class="te-print-shell po-print-shell"><p>Expense report not found.</p><a href="/travel-expense/">Back to Expense Reports</a></main></body></html>';
    exit;
}

$totals = te_calculate_totals($reportId, (float) ($report['MileageRate'] ?? 0.70));
$expenseLines = te_get_expense_lines($reportId);
$mileageLines = te_get_mileage_lines($reportId);
$entertainmentLines = te_get_entertainment_lines($reportId);
$miscLines = te_get_misc_lines($reportId);
$approvalLog = te_list_approval_log($reportId);

$pageTitle = $report['ReportNumber'] . ' | Print';
$pageDescription = 'Printable travel and expense report.';
$bodyClass = 'te-print-page';

require dirname(__DIR__) . '/includes/head.php';
?>
  <div class="te-print-toolbar po-print-toolbar no-print">
    <a class="btn-secondary btn-small" href="/travel-expense/view.php?id=<?= $reportId ?>">Back to Report</a>
    <button type="button" class="btn-primary btn-small" onclick="window.print()">Print</button>
  </div>

  <main class="te-print-shell po-print-shell">
    <?php require dirname(__DIR__) . '/includes/te-print.php'; ?>
  </main>
</body>
</html>
