<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/legal.php';

legal_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /legal-agreements/', true, 302);
    exit;
}

$contractId = (int) ($_POST['contract_id'] ?? 0);
$result = legal_delete_contract($contractId);

if ($result['ok']) {
    header('Location: /legal-agreements/?notice=deleted', true, 302);
    exit;
}

header('Location: /legal-agreements/view.php?id=' . $contractId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
