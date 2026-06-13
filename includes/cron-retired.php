<?php

function cron_retired_respond(string $successor): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(410);

    echo json_encode([
        'ok'        => false,
        'error'     => 'This cron endpoint is retired. Scheduled jobs run on Azure Function App Nutra-forecast-tool.',
        'successor' => $successor,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    exit;
}
