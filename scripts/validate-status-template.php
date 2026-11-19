#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Validate a status-deck .pptx locally (same rules as portal template upload).
 *
 * Usage: php scripts/validate-status-template.php path/to/deck.pptx
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/validate-status-template.php <file.pptx>\n");
    exit(1);
}

$path = $argv[1];
if (!is_readable($path)) {
    fwrite(STDERR, "File not found or not readable: {$path}\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/status-update-services.php';

$service = new StatusUpdateTemplateService();
$result = $service->validateUpload($path, basename($path));

if ($result['ok']) {
    $summary = $result['summary'] ?? [];
    echo 'OK: ' . (int) ($summary['field_count'] ?? 0) . " editable fields on " . (int) ($summary['slides'] ?? 0) . " slides.\n";
    exit(0);
}

echo 'FAIL: ' . ($result['error'] ?? 'Unknown error') . "\n";
$problems = $result['summary']['problems'] ?? [];
foreach ($problems as $problem) {
    echo "  - {$problem}\n";
}
exit(1);