<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/po-receiving-attachments.php';

por_require_update();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-receiving/', true, 302);
    exit;
}

$porId = (int) ($_POST['por_id'] ?? 0);
$kind = trim($_POST['attachment_kind'] ?? 'Other');
$result = por_save_attachment($porId, $_FILES['attachment'] ?? [], $kind);

if ($result['ok']) {
    header('Location: /po-receiving/view.php?id=' . $porId . '&notice=attachment', true, 302);
    exit;
}

header('Location: /po-receiving/view.php?id=' . $porId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
