<?php
/**
 * Public PDF download for Education Resources (no Operations login required).
 * Used by nutraaxislabs.com education page / AEM block.
 */
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/education-resources.php';

$id = (int) ($_GET['id'] ?? 0);
$row = education_resources_get_public($id);

if ($row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('PDF not found.');
}

education_resources_stream_document($row, true);
