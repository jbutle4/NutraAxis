<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-reports.php';

auth_require_module_read('marketing-reports');

$period = mkt_report_period((string) ($_GET['month'] ?? ''));
if ($period === null) {
    marketing_redirect('/marketing/reports/', ['error' => 'Choose a month from the list.']);
}
['data' => $data, 'report' => $report] = mkt_report_load($period);
$doc = mkt_report_doc($period, $data, $report);
$href = '/marketing/reports/view.php?month=' . $period['ym'];
$format = ($_GET['format'] ?? '') === 'docx' ? 'docx' : 'print';
mkt_output_log('monthly-report', $format, (string) $doc['title'], $href);
if ($format === 'docx') {
    mkt_doc_send_docx($doc);
}
mkt_doc_render_print_page($doc, '/marketing/reports/', 'Back to Reports', $href . '&format=docx');
