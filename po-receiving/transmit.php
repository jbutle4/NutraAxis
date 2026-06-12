<?php
/**
 * Legacy transmit endpoint — forwards POST to transmit-asn.php handler.
 */
require dirname(__DIR__) . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /po-receiving/', true, 302);
    exit;
}

require __DIR__ . '/transmit-asn.php';
