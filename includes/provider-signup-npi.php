<?php

/**
 * Validate an NPI against the CMS NPPES Read API (version 2.1).
 *
 * @return array{ok: bool, status: string, summary: ?string, payload: ?array, error: ?string}
 */
function provider_signup_npi_validate(string $npiNumber): array
{
    $npiNumber = preg_replace('/\D+/', '', $npiNumber) ?? '';
    if (strlen($npiNumber) !== 10) {
        return [
            'ok'      => false,
            'status'  => 'Invalid',
            'summary' => 'NPI must be exactly 10 digits.',
            'payload' => null,
            'error'   => 'NPI must be exactly 10 digits.',
        ];
    }

    $url = 'https://npiregistry.cms.hhs.gov/api/?' . http_build_query([
        'version' => '2.1',
        'number'  => $npiNumber,
    ]);

    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => 15,
            'ignore_errors' => true,
            'header'        => "Accept: application/json\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false || trim($body) === '') {
        return [
            'ok'      => false,
            'status'  => 'Error',
            'summary' => 'Unable to reach the NPI Registry.',
            'payload' => null,
            'error'   => 'Unable to reach the NPI Registry.',
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            'ok'      => false,
            'status'  => 'Error',
            'summary' => 'NPI Registry returned an invalid response.',
            'payload' => null,
            'error'   => 'NPI Registry returned an invalid response.',
        ];
    }

    $results = $decoded['results'] ?? [];
    if (!is_array($results) || $results === []) {
        return [
            'ok'      => false,
            'status'  => 'NotFound',
            'summary' => 'No provider record found for this NPI.',
            'payload' => $decoded,
            'error'   => 'No provider record found for this NPI.',
        ];
    }

    $record = $results[0];
    $basic = is_array($record['basic'] ?? null) ? $record['basic'] : [];
    $status = strtolower((string) ($basic['status'] ?? 'active'));
    $name = provider_signup_npi_format_name($record);

    if ($status !== '' && $status !== 'a' && $status !== 'active') {
        return [
            'ok'      => false,
            'status'  => 'Inactive',
            'summary' => 'NPI record is not active (' . ($basic['status'] ?? 'unknown') . ').',
            'payload' => $decoded,
            'error'   => 'NPI record is not active.',
        ];
    }

    return [
        'ok'      => true,
        'status'  => 'Validated',
        'summary' => $name !== '' ? 'Matched NPI record: ' . $name : 'NPI record found in NPPES registry.',
        'payload' => $decoded,
        'error'   => null,
    ];
}

/**
 * @param array<string, mixed> $record
 */
function provider_signup_npi_format_name(array $record): string
{
    $basic = is_array($record['basic'] ?? null) ? $record['basic'] : [];

    if (($record['enumeration_type'] ?? '') === 'NPI-2') {
        return trim((string) ($basic['organization_name'] ?? ''));
    }

    $first = trim((string) ($basic['first_name'] ?? ''));
    $last = trim((string) ($basic['last_name'] ?? ''));

    return trim($first . ' ' . $last);
}
