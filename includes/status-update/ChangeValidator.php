<?php

declare(strict_types=1);

require_once __DIR__ . '/StatusPalette.php';

final class StatusUpdateChangeValidator
{
    /**
     * @param array<string, array<string, mixed>> $deckMap
     * @param list<string> $newValue
     * @return array{ok: bool, error: ?string, value: list<string>}
     */
    public function validate(string $fieldKey, array $deckMap, array $newValue): array
    {
        if (!isset($deckMap[$fieldKey]) || empty($deckMap[$fieldKey]['editable'])) {
            return ['ok' => false, 'error' => 'Unknown or read-only field.', 'value' => []];
        }

        $field = $deckMap[$fieldKey];
        $kind = (string) ($field['kind'] ?? 'text');
        $clean = [];
        foreach ($newValue as $line) {
            $line = $this->sanitizeLine((string) $line);
            if ($line === '' && $kind !== 'text') {
                continue;
            }
            if (str_contains($line, '<') || str_contains($line, '>')) {
                return ['ok' => false, 'error' => 'Angle brackets are not allowed in field values.', 'value' => []];
            }
            $clean[] = $line;
        }

        if ($kind === 'chip') {
            if (count($clean) !== 1) {
                return ['ok' => false, 'error' => 'Chip fields must have exactly one line.', 'value' => []];
            }
            $normalized = StatusPalette::normalizeChip($clean[0]);
            if ($normalized === null) {
                return ['ok' => false, 'error' => 'Invalid chip status.', 'value' => []];
            }

            return ['ok' => true, 'error' => null, 'value' => [$normalized]];
        }

        if ($kind === 'rag') {
            if (count($clean) !== 1) {
                return ['ok' => false, 'error' => 'RAG cells must have exactly one line.', 'value' => []];
            }
            $normalized = StatusPalette::normalizeRag($clean[0]);
            if ($normalized === null) {
                return ['ok' => false, 'error' => 'Invalid RAG value.', 'value' => []];
            }

            return ['ok' => true, 'error' => null, 'value' => [$normalized]];
        }

        $limits = $this->limitsFor($fieldKey, $kind);
        if (count($clean) > $limits['max_paragraphs']) {
            return ['ok' => false, 'error' => 'Too many paragraphs for this field.', 'value' => []];
        }

        foreach ($clean as $line) {
            if (strlen($line) > $limits['max_chars']) {
                return ['ok' => false, 'error' => 'Text exceeds the maximum length for this field.', 'value' => []];
            }
        }

        if ($clean === []) {
            $clean = [''];
        }

        return ['ok' => true, 'error' => null, 'value' => $clean];
    }

    private function sanitizeLine(string $line): string
    {
        $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $line) ?? $line;

        return trim($line);
    }

    /**
     * @return array{max_paragraphs: int, max_chars: int}
     */
    private function limitsFor(string $fieldKey, string $kind): array
    {
        if (str_contains($fieldKey, 'Card title')) {
            return ['max_paragraphs' => 1, 'max_chars' => 45];
        }
        if (str_contains($fieldKey, 'Key date label')) {
            return ['max_paragraphs' => 1, 'max_chars' => 32];
        }
        if (preg_match('/Key date \d/', $fieldKey) === 1) {
            return ['max_paragraphs' => 1, 'max_chars' => 10];
        }
        if (str_contains($fieldKey, 'Card body')) {
            return ['max_paragraphs' => 4, 'max_chars' => 110];
        }
        if (str_contains($fieldKey, 'AP body')) {
            return ['max_paragraphs' => 2, 'max_chars' => 90];
        }
        if (str_contains($fieldKey, 'RAG Matrix:') && str_ends_with($fieldKey, 'c5')) {
            return ['max_paragraphs' => 1, 'max_chars' => 95];
        }
        if (str_contains($fieldKey, 'RAG Matrix:')) {
            return ['max_paragraphs' => 4, 'max_chars' => 28];
        }

        return ['max_paragraphs' => 6, 'max_chars' => 200];
    }
}