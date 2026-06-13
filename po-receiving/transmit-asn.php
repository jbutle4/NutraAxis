<?php
/**
 * Legacy transmit endpoint — redirect to ASN page (POST is broken on Azure nginx).
 */
$porId = (int) ($_GET['id'] ?? $_POST['por_id'] ?? 0);
$query = $porId > 0 ? '?id=' . $porId . '&v=20260611' : '';
header('Location: /po-receiving/asn.php' . $query, true, 302);
exit;
