<?php
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/marketing-outputs.php';

auth_require_module_read('research-output');

$type = (string) ($_GET['type'] ?? '');
['doc' => $doc, 'error' => $error] = mkt_out_build($type, $_GET);
if ($doc === null) {
    marketing_redirect('/marketing/output-generator/', ['error' => $error]);
}
$query = array_diff_key($_GET, ['format' => true]);
$href = '/marketing/output-generator/document.php?' . http_build_query($query);
$format = ($_GET['format'] ?? '') === 'docx' ? 'docx' : 'print';
mkt_output_log($type, $format, (string) $doc['title'], $href);
if ($format === 'docx') {
    mkt_doc_send_docx($doc);
}
mkt_doc_render_print_page($doc, '/marketing/output-generator/', 'Back to Output Generator', $href . '&format=docx');
