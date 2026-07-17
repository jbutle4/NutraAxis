<?php

require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/coa.php';

$id = (int) ($_GET['id'] ?? 0);
$row = coa_get_published($id);

if ($row === null) {
    http_response_code(404);
    exit('COA not found.');
}

coa_stream_document($row, true);
