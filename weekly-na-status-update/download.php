<?php

require dirname(__DIR__) . '/includes/init.php';
require_once dirname(__DIR__) . '/includes/status-update-portal.php';
require_once dirname(__DIR__) . '/includes/status-update-services.php';

status_update_require_read();

$date = status_update_parse_date($_GET['date'] ?? '');
if ($date === null) {
    http_response_code(400);
    exit('Invalid date.');
}

$repo = new StatusUpdateRepository();
$week = $repo->weekByDate($date);
if ($week === null) {
    http_response_code(404);
    exit('Week not found.');
}

$blob = new StatusUpdateBlobStore();
$isFinal = !empty($_GET['final']);
$path = $isFinal ? (string) ($week['final_blob'] ?? '') : (string) $week['base_blob'];

if (!$isFinal) {
    $draft = (new StatusUpdateDraftService())->buildDraftValues($week);
    if (!$draft['ok']) {
        http_response_code(500);
        exit('Unable to build draft.');
    }
    $base = $blob->get((string) $week['base_blob']);
    if (!$base['ok']) {
        http_response_code(404);
        exit('Base deck missing.');
    }
    $render = (new DeckWriter())->render((string) $base['content'], $draft['map'], $date);
    if (!$render['ok'] || !is_string($render['bytes'])) {
        http_response_code(500);
        exit('Render failed.');
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
    header('Content-Disposition: attachment; filename="NutraAxis_Status_Draft_' . $date . '.pptx"');
    echo $render['bytes'];
    exit;
}

if ($path === '') {
    http_response_code(404);
    exit('Final deck not available.');
}

$file = $blob->get($path);
if (!$file['ok']) {
    http_response_code(404);
    exit('Final deck missing.');
}

header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
header('Content-Disposition: attachment; filename="NutraAxis_Status_' . $date . '.pptx"');
echo (string) $file['content'];