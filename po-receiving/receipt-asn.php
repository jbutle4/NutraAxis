<?php
/**
 * Legacy alias — redirect to asn.php.
 */
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /po-receiving/asn.php' . ($query !== '' ? '?' . $query : ''), true, 301);
exit;
