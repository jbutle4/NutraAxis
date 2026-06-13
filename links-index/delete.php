<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/links.php';

links_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /links-index/', true, 302);
    exit;
}

$linkId = (int) ($_POST['link_id'] ?? 0);
$result = links_delete($linkId);

if ($result['ok']) {
    header('Location: /links-index/?notice=deleted', true, 302);
    exit;
}

header('Location: /links-index/view.php?id=' . $linkId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
