<?php

declare(strict_types=1);

/**
 * Shared helpers for public COA JSON endpoints (CORS + response shape).
 */

function coa_public_allowed_origins(): array
{
    return [
        'https://www.nutraaxislabs.com',
        'https://nutraaxislabs.com',
        'https://main--nutrasync-eds-staging--capocommerce.aem.live',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
    ];
}

function coa_public_site_base_url(): string
{
    require_once __DIR__ . '/env.php';

    return rtrim((string) env('SITE_URL', 'https://nutraaxisweb.azurewebsites.net'), '/');
}

function coa_public_send_cors_headers(): void
{
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $allowed = coa_public_allowed_origins();

    if ($origin !== '' && in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    } else {
        header('Access-Control-Allow-Origin: *');
    }

    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept');
    header('Access-Control-Max-Age: 86400');
}

function coa_public_handle_preflight(): void
{
    if (strcasecmp((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'), 'OPTIONS') === 0) {
        coa_public_send_cors_headers();
        http_response_code(204);
        exit;
    }
}

function coa_public_json_response(array $payload, int $status = 200): void
{
    coa_public_send_cors_headers();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function coa_public_test_records(): array
{
    $siteBase = coa_public_site_base_url();

    return [
        [
            'id' => 'wilc057-37489',
            'product_name' => 'AdrenaAxis',
            'lot_number' => '37489',
            'expiration_date' => '2026-04-23',
            'expiration_display' => '04/2028',
            'pdf_url' => $siteBase . '/coa-test/files/coa-na-wilc057-adrenaaxis-lot-37489-04-23-26.pdf',
            'source_pdf' => 'COA_NA_WILC057_AdrenaAxis_Lot_37489_ 04-23-26.pdf',
        ],
        [
            'id' => 'wilc058-37340',
            'product_name' => 'IronAxis',
            'lot_number' => '37340',
            'expiration_date' => '2026-03-25',
            'expiration_display' => '02/28',
            'pdf_url' => $siteBase . '/coa-test/files/coa-na-wilc058-ironaxis-lot-37340-03-25-2026.pdf',
            'source_pdf' => 'COA_NA_WILC058_IronAxis_Lot_37340_03-25-2026.pdf',
        ],
    ];
}
