<?php

require_once __DIR__ . '/provider-signup-npi.php';
require_once __DIR__ . '/database.php';

function provider_signup_npi_nullable_string(string $value): ?string
{
    $value = trim($value);

    return $value === '' ? null : $value;
}

/**
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function provider_signup_npi_parse_record(array $record): array
{
    $basic = is_array($record['basic'] ?? null) ? $record['basic'] : [];
    $enumerationType = (string) ($record['enumeration_type'] ?? '');
    $providerName = provider_signup_npi_format_name($record);

    return [
        'enumeration_type'              => $enumerationType,
        'registry_status'               => (string) ($basic['status'] ?? ''),
        'provider_name'                 => $providerName,
        'first_name'                    => trim((string) ($basic['first_name'] ?? '')),
        'middle_name'                   => trim((string) ($basic['middle_name'] ?? '')),
        'last_name'                     => trim((string) ($basic['last_name'] ?? '')),
        'credential'                    => trim((string) ($basic['credential'] ?? '')),
        'organization_name'             => trim((string) ($basic['organization_name'] ?? '')),
        'authorized_official_first_name'=> trim((string) ($basic['authorized_official_first_name'] ?? '')),
        'authorized_official_last_name' => trim((string) ($basic['authorized_official_last_name'] ?? '')),
        'authorized_official_title'     => trim((string) ($basic['authorized_official_title_or_position'] ?? '')),
        'authorized_official_phone'     => trim((string) ($basic['authorized_official_telephone_number'] ?? '')),
        'certification_date'            => provider_signup_npi_parse_date((string) ($basic['certification_date'] ?? '')),
        'enumeration_date'              => provider_signup_npi_parse_date((string) ($basic['enumeration_date'] ?? '')),
        'last_updated_epoch'            => isset($record['last_updated_epoch']) ? (int) $record['last_updated_epoch'] : null,
        'addresses'                     => provider_signup_npi_parse_addresses($record),
        'taxonomies'                    => provider_signup_npi_parse_taxonomies($record),
    ];
}

function provider_signup_npi_parse_date(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

/**
 * @param array<string, mixed> $record
 * @return list<array<string, mixed>>
 */
function provider_signup_npi_parse_addresses(array $record): array
{
    $addresses = [];
    $rawAddresses = is_array($record['addresses'] ?? null) ? $record['addresses'] : [];

    foreach ($rawAddresses as $address) {
        if (!is_array($address)) {
            continue;
        }

        $purpose = strtoupper(trim((string) ($address['address_purpose'] ?? 'OTHER')));
        if (!in_array($purpose, ['MAILING', 'LOCATION'], true)) {
            $purpose = 'OTHER';
        }

        $addresses[] = [
            'address_purpose'  => $purpose,
            'address_type'     => trim((string) ($address['address_type'] ?? '')),
            'address_1'        => trim((string) ($address['address_1'] ?? '')),
            'address_2'        => trim((string) ($address['address_2'] ?? '')),
            'city'             => trim((string) ($address['city'] ?? '')),
            'state_code'       => trim((string) ($address['state'] ?? '')),
            'postal_code'      => trim((string) ($address['postal_code'] ?? '')),
            'country_code'     => trim((string) ($address['country_code'] ?? '')),
            'telephone_number' => trim((string) ($address['telephone_number'] ?? '')),
            'fax_number'       => trim((string) ($address['fax_number'] ?? '')),
        ];
    }

    return $addresses;
}

/**
 * @param array<string, mixed> $record
 * @return list<array<string, mixed>>
 */
function provider_signup_npi_parse_taxonomies(array $record): array
{
    $taxonomies = [];
    $rawTaxonomies = is_array($record['taxonomies'] ?? null) ? $record['taxonomies'] : [];

    foreach ($rawTaxonomies as $taxonomy) {
        if (!is_array($taxonomy)) {
            continue;
        }

        $taxonomies[] = [
            'taxonomy_code'        => trim((string) ($taxonomy['code'] ?? '')),
            'taxonomy_description' => trim((string) ($taxonomy['desc'] ?? '')),
            'license_number'       => trim((string) ($taxonomy['license'] ?? '')),
            'license_state_code'   => trim((string) ($taxonomy['state'] ?? '')),
            'is_primary'           => !empty($taxonomy['primary']),
            'taxonomy_group'       => trim((string) ($taxonomy['taxonomy_group'] ?? '')),
        ];
    }

    return $taxonomies;
}

function provider_signup_npi_normalize_compare_string(string $value): string
{
    $value = strtoupper(trim($value));
    $value = preg_replace('/[^A-Z0-9 ]+/', '', $value) ?? '';
    $value = preg_replace('/\s+/', ' ', $value) ?? '';

    return trim($value);
}

function provider_signup_npi_normalize_postal(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';

    return strlen($digits) >= 5 ? substr($digits, 0, 5) : $digits;
}

function provider_signup_npi_strings_match(string $left, string $right): bool
{
    $left = provider_signup_npi_normalize_compare_string($left);
    $right = provider_signup_npi_normalize_compare_string($right);

    if ($left === '' || $right === '') {
        return false;
    }

    return $left === $right || str_contains($left, $right) || str_contains($right, $left);
}

/**
 * @param array<string, mixed> $application
 * @param array<string, mixed> $parsed
 * @return array{name_match_status: string, address_match_status: string, license_match_status: string, comparison_summary: string}
 */
function provider_signup_npi_compare_application(array $application, array $parsed): array
{
    $registryNames = array_values(array_filter([
        (string) ($parsed['provider_name'] ?? ''),
        (string) ($parsed['organization_name'] ?? ''),
        trim(((string) ($parsed['first_name'] ?? '')) . ' ' . ((string) ($parsed['last_name'] ?? ''))),
    ]));

    $applicationNames = array_values(array_filter([
        (string) ($application['CompanyName'] ?? ''),
        (string) ($application['CompanyLegalName'] ?? ''),
        trim(((string) ($application['AdminFirstName'] ?? '')) . ' ' . ((string) ($application['AdminLastName'] ?? ''))),
    ]));

    $nameMatch = 'Unavailable';
    foreach ($applicationNames as $applicationName) {
        foreach ($registryNames as $registryName) {
            if (provider_signup_npi_strings_match($applicationName, $registryName)) {
                $nameMatch = 'Match';
                break 2;
            }
        }
    }

    if ($nameMatch === 'Unavailable' && $registryNames !== [] && $applicationNames !== []) {
        $nameMatch = 'Mismatch';
        foreach ($applicationNames as $applicationName) {
            foreach ($registryNames as $registryName) {
                $applicationNorm = provider_signup_npi_normalize_compare_string($applicationName);
                $registryNorm = provider_signup_npi_normalize_compare_string($registryName);
                similar_text($applicationNorm, $registryNorm, $percent);
                if ($percent >= 60.0) {
                    $nameMatch = 'Partial';
                    break 2;
                }
            }
        }
    }

    $locationAddress = null;
    foreach ($parsed['addresses'] as $address) {
        if (($address['address_purpose'] ?? '') === 'LOCATION') {
            $locationAddress = $address;
            break;
        }
    }
    if ($locationAddress === null && $parsed['addresses'] !== []) {
        $locationAddress = $parsed['addresses'][0];
    }

    $addressMatch = 'Unavailable';
    if ($locationAddress !== null) {
        $stateMatch = strtoupper((string) ($application['StateCode'] ?? '')) === strtoupper((string) ($locationAddress['state_code'] ?? ''));
        $cityMatch = provider_signup_npi_strings_match(
            (string) ($application['City'] ?? ''),
            (string) ($locationAddress['city'] ?? '')
        );
        $postalMatch = provider_signup_npi_normalize_postal((string) ($application['PostalCode'] ?? ''))
            === provider_signup_npi_normalize_postal((string) ($locationAddress['postal_code'] ?? ''));
        $streetMatch = provider_signup_npi_strings_match(
            (string) ($application['StreetAddress'] ?? ''),
            (string) ($locationAddress['address_1'] ?? '')
        );

        if ($stateMatch && $cityMatch && $postalMatch && $streetMatch) {
            $addressMatch = 'Match';
        } elseif ($stateMatch && ($cityMatch || $postalMatch || $streetMatch)) {
            $addressMatch = 'Partial';
        } else {
            $addressMatch = 'Mismatch';
        }
    }

    $licenseMatch = 'Unavailable';
    $licenseCount = 0;
    foreach ($parsed['taxonomies'] as $taxonomy) {
        if (trim((string) ($taxonomy['license_number'] ?? '')) !== '') {
            $licenseCount++;
        }
    }
    if ($parsed['taxonomies'] !== []) {
        $licenseMatch = $licenseCount > 0 ? 'OnFile' : 'NotOnFile';
    }

    $summaryParts = [];
    $summaryParts[] = 'Name: ' . strtolower($nameMatch);
    $summaryParts[] = 'Address: ' . strtolower($addressMatch);
    $summaryParts[] = 'License: ' . strtolower($licenseMatch);

    return [
        'name_match_status'     => $nameMatch,
        'address_match_status'  => $addressMatch,
        'license_match_status'  => $licenseMatch,
        'comparison_summary'    => ucfirst(implode('; ', $summaryParts)) . '.',
    ];
}

/**
 * @param array{ok: bool, status: string, summary: ?string, payload: ?array, error: ?string} $validationResult
 */
function provider_signup_npi_save_snapshot(int $applicationId, string $npiNumber, array $validationResult, array $application): ?int
{
    if ($applicationId <= 0) {
        return null;
    }

    $payload = is_array($validationResult['payload'] ?? null) ? $validationResult['payload'] : null;
    $record = is_array($payload['results'][0] ?? null) ? $payload['results'][0] : null;
    $parsed = $record !== null ? provider_signup_npi_parse_record($record) : [
        'enumeration_type'              => null,
        'registry_status'               => null,
        'provider_name'                 => null,
        'first_name'                    => null,
        'middle_name'                   => null,
        'last_name'                     => null,
        'credential'                    => null,
        'organization_name'             => null,
        'authorized_official_first_name'=> null,
        'authorized_official_last_name' => null,
        'authorized_official_title'     => null,
        'authorized_official_phone'     => null,
        'certification_date'            => null,
        'enumeration_date'              => null,
        'last_updated_epoch'            => null,
        'addresses'                     => [],
        'taxonomies'                    => [],
    ];

    $comparison = provider_signup_npi_compare_application($application, $parsed);
    $rawJson = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupNpiSnapshot (
                ApplicationID,
                NpiNumber,
                ValidationOk,
                ValidationStatus,
                ValidationSummary,
                RawJson,
                EnumerationType,
                RegistryStatus,
                ProviderName,
                FirstName,
                MiddleName,
                LastName,
                Credential,
                OrganizationName,
                AuthorizedOfficialFirstName,
                AuthorizedOfficialLastName,
                AuthorizedOfficialTitle,
                AuthorizedOfficialPhone,
                CertificationDate,
                EnumerationDate,
                LastUpdatedEpoch,
                NameMatchStatus,
                AddressMatchStatus,
                LicenseMatchStatus,
                ComparisonSummary
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([
            $applicationId,
            preg_replace('/\D+/', '', $npiNumber),
            !empty($validationResult['ok']) ? 1 : 0,
            (string) ($validationResult['status'] ?? 'Error'),
            provider_signup_npi_nullable_string((string) ($validationResult['summary'] ?? '')),
            $rawJson,
            provider_signup_npi_nullable_string((string) ($parsed['enumeration_type'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['registry_status'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['provider_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['first_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['middle_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['last_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['credential'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['organization_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['authorized_official_first_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['authorized_official_last_name'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['authorized_official_title'] ?? '')),
            provider_signup_npi_nullable_string((string) ($parsed['authorized_official_phone'] ?? '')),
            $parsed['certification_date'] ?? null,
            $parsed['enumeration_date'] ?? null,
            $parsed['last_updated_epoch'] ?? null,
            $comparison['name_match_status'],
            $comparison['address_match_status'],
            $comparison['license_match_status'],
            $comparison['comparison_summary'],
        ]);

        $idRow = $pdo->query('SELECT CAST(SCOPE_IDENTITY() AS INT) AS inserted_id')->fetch(PDO::FETCH_ASSOC);
        $snapshotId = (int) ($idRow['inserted_id'] ?? 0);
        if ($snapshotId <= 0) {
            $pdo->rollBack();

            return null;
        }

        $addressStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupNpiAddress (
                SnapshotID,
                AddressPurpose,
                AddressType,
                Address1,
                Address2,
                City,
                StateCode,
                PostalCode,
                CountryCode,
                TelephoneNumber,
                FaxNumber
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        foreach ($parsed['addresses'] as $address) {
            $addressStmt->execute([
                $snapshotId,
                (string) ($address['address_purpose'] ?? 'OTHER'),
                provider_signup_npi_nullable_string((string) ($address['address_type'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['address_1'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['address_2'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['city'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['state_code'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['postal_code'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['country_code'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['telephone_number'] ?? '')),
                provider_signup_npi_nullable_string((string) ($address['fax_number'] ?? '')),
            ]);
        }

        $taxonomyStmt = $pdo->prepare(<<<SQL
            INSERT INTO dbo.ProviderSignupNpiTaxonomy (
                SnapshotID,
                TaxonomyCode,
                TaxonomyDescription,
                LicenseNumber,
                LicenseStateCode,
                IsPrimary,
                TaxonomyGroup
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        SQL);
        foreach ($parsed['taxonomies'] as $taxonomy) {
            $taxonomyStmt->execute([
                $snapshotId,
                provider_signup_npi_nullable_string((string) ($taxonomy['taxonomy_code'] ?? '')),
                provider_signup_npi_nullable_string((string) ($taxonomy['taxonomy_description'] ?? '')),
                provider_signup_npi_nullable_string((string) ($taxonomy['license_number'] ?? '')),
                provider_signup_npi_nullable_string((string) ($taxonomy['license_state_code'] ?? '')),
                !empty($taxonomy['is_primary']) ? 1 : 0,
                provider_signup_npi_nullable_string((string) ($taxonomy['taxonomy_group'] ?? '')),
            ]);
        }

        $pdo->prepare('UPDATE dbo.ProviderSignupApplication SET LatestNpiSnapshotID = ? WHERE ApplicationID = ?')
            ->execute([$snapshotId, $applicationId]);

        $pdo->commit();

        return $snapshotId;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('provider_signup_npi_save_snapshot: ' . $e->getMessage());

        return null;
    }
}

/**
 * @return array{snapshot: array, addresses: list<array>, taxonomies: list<array>}|null
 */
function provider_signup_npi_get_snapshot_bundle(?int $snapshotId): ?array
{
    if ($snapshotId === null || $snapshotId <= 0) {
        return null;
    }

    try {
        $pdo = db();
        $snapshotStmt = $pdo->prepare('SELECT * FROM dbo.ProviderSignupNpiSnapshot WHERE SnapshotID = ?');
        $snapshotStmt->execute([$snapshotId]);
        $snapshot = $snapshotStmt->fetch();
        if (!is_array($snapshot)) {
            return null;
        }

        $addressStmt = $pdo->prepare(<<<SQL
            SELECT *
            FROM dbo.ProviderSignupNpiAddress
            WHERE SnapshotID = ?
            ORDER BY CASE AddressPurpose WHEN N'LOCATION' THEN 0 WHEN N'MAILING' THEN 1 ELSE 2 END, AddressID
        SQL);
        $addressStmt->execute([$snapshotId]);
        $addresses = $addressStmt->fetchAll();

        $taxonomyStmt = $pdo->prepare(<<<SQL
            SELECT *
            FROM dbo.ProviderSignupNpiTaxonomy
            WHERE SnapshotID = ?
            ORDER BY IsPrimary DESC, TaxonomyID
        SQL);
        $taxonomyStmt->execute([$snapshotId]);
        $taxonomies = $taxonomyStmt->fetchAll();

        return [
            'snapshot'   => $snapshot,
            'addresses'  => is_array($addresses) ? $addresses : [],
            'taxonomies' => is_array($taxonomies) ? $taxonomies : [],
        ];
    } catch (Throwable $e) {
        error_log('provider_signup_npi_get_snapshot_bundle: ' . $e->getMessage());

        return null;
    }
}

function provider_signup_npi_match_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'match', 'onfile' => 'status-approved',
        'partial'         => 'status-received',
        'mismatch', 'notonfile' => 'status-cancelled',
        default           => 'status-draft',
    };
}
