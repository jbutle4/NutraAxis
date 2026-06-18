<?php
require dirname(__DIR__) . '/includes/init.php';
require dirname(__DIR__) . '/includes/contacts.php';

contacts_require_delete();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contacts-list/', true, 302);
    exit;
}

$contactId = (int) ($_POST['contact_id'] ?? 0);
$result = contacts_delete($contactId);

if ($result['ok']) {
    header('Location: /contacts-list/?notice=deleted', true, 302);
    exit;
}

header('Location: /contacts-list/view.php?id=' . $contactId . '&error=' . rawurlencode((string) $result['error']), true, 302);
exit;
