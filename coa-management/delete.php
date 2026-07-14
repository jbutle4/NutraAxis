<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/coa.php';

coa_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /coa-management/', true, 302);
    exit;
}

$id = (int) ($_POST['id'] ?? 0);
$result = coa_delete($id);

if ($result['ok']) {
    header('Location: /coa-management/?notice=deleted', true, 302);
    exit;
}

http_response_code(400);
exit($result['error'] ?? 'Unable to delete COA document.');
