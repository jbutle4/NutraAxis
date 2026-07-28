<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/page-data-profile.php';
require dirname(__DIR__) . '/includes/po-receiving.php';

por_bind_page_environments();
por_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . por_page_path('/po-receiving/'), true, 302);
    exit;
}

$porId = (int) ($_POST['por_id'] ?? 0);
$result = por_delete($porId);

if ($result['ok']) {
    header('Location: ' . por_page_path('/po-receiving/') . '?notice=deleted', true, 302);
    exit;
}

header('Location: ' . por_page_path('/po-receiving/view.php') . '?id=' . $porId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
