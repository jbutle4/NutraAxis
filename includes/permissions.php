<?php

/**
 * Role permission values use CRUD letter combinations (canonical order: C, R, U, D).
 * NULL or empty = no access.
 */
const PERMISSION_CRUD_VALUES = [
    'C', 'R', 'U', 'D',
    'CR', 'CU', 'CD', 'RU', 'RD', 'UD',
    'CRU', 'CRD', 'CUD', 'RUD', 'CRUD',
];

const PERMISSION_ACTIONS = [
    'C' => 'Create',
    'R' => 'Read',
    'U' => 'Update',
    'D' => 'Delete',
];

function permission_is_valid(?string $value): bool
{
    if ($value === null || $value === '') {
        return true;
    }

    return in_array($value, PERMISSION_CRUD_VALUES, true);
}

function permission_has(?string $value, string $action): bool
{
    $action = strtoupper($action);
    if (!isset(PERMISSION_ACTIONS[$action])) {
        return false;
    }
    if ($value === null || $value === '') {
        return false;
    }

    return str_contains(strtoupper($value), $action);
}

function permission_can_create(?string $value): bool
{
    return permission_has($value, 'C');
}

function permission_can_read(?string $value): bool
{
    return permission_has($value, 'R');
}

function permission_can_update(?string $value): bool
{
    return permission_has($value, 'U');
}

function permission_can_delete(?string $value): bool
{
    return permission_has($value, 'D');
}

function permission_label(?string $value): string
{
    if ($value === null || $value === '') {
        return 'No access';
    }

    $letters = str_split(strtoupper($value));
    $labels = array_map(
        fn(string $letter) => PERMISSION_ACTIONS[$letter] ?? $letter,
        $letters
    );

    return implode(', ', $labels);
}

function permission_flags(?string $value): array
{
    $value = strtoupper($value ?? '');

    return [
        'C' => str_contains($value, 'C'),
        'R' => str_contains($value, 'R'),
        'U' => str_contains($value, 'U'),
        'D' => str_contains($value, 'D'),
    ];
}

function permission_from_flags(bool $create, bool $read, bool $update, bool $delete): ?string
{
    $value = '';
    if ($create) {
        $value .= 'C';
    }
    if ($read) {
        $value .= 'R';
    }
    if ($update) {
        $value .= 'U';
    }
    if ($delete) {
        $value .= 'D';
    }

    return $value === '' ? null : $value;
}
