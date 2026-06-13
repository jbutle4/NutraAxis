<?php

function table_sort_state(
    array $columns,
    string $defaultSort,
    string $defaultDir = 'asc',
    array $input = [],
    string $sortKey = 'sort',
    string $dirKey = 'dir'
): array {
    $source = $input !== [] ? $input : $_GET;
    $sort = strtolower(trim((string) ($source[$sortKey] ?? $defaultSort)));
    $dir = strtolower(trim((string) ($source[$dirKey] ?? $defaultDir)));

    if (!array_key_exists($sort, $columns)) {
        $sort = $defaultSort;
    }

    if ($dir !== 'desc') {
        $dir = 'asc';
    }

    return ['sort' => $sort, 'dir' => $dir];
}

function table_sort_is_active(string $column, array $sortState, string $defaultSort): bool
{
    return ($sortState['sort'] ?? $defaultSort) === $column;
}

function table_sort_direction(string $column, array $sortState, string $defaultSort): string
{
    if (!table_sort_is_active($column, $sortState, $defaultSort)) {
        return '';
    }

    return ($sortState['dir'] ?? 'asc') === 'asc' ? 'asc' : 'desc';
}

function table_sort_default_dir(string $column, array $numericColumns, string $defaultDescColumn = ''): string
{
    if (in_array($column, $numericColumns, true)) {
        return 'desc';
    }

    if ($column === $defaultDescColumn) {
        return 'desc';
    }

    return 'asc';
}

function table_sort_href(
    string $basePath,
    string $column,
    array $columns,
    array $filters,
    array $queryKeys,
    array $numericColumns = [],
    string $defaultSort = '',
    string $defaultDir = 'asc',
    string $defaultDescColumn = '',
    string $sortKey = 'sort',
    string $dirKey = 'dir'
): string {
    $sortState = table_sort_state($columns, $defaultSort, $defaultDir, $filters, $sortKey, $dirKey);
    $currentSort = $sortState['sort'];
    $currentDir = $sortState['dir'];

    if ($currentSort === $column) {
        $nextDir = $currentDir === 'asc' ? 'desc' : 'asc';
    } else {
        $nextDir = table_sort_default_dir($column, $numericColumns, $defaultDescColumn);
    }

    $query = [];
    foreach ($queryKeys as $key) {
        $value = $filters[$key] ?? null;
        if ($value !== null && $value !== '') {
            $query[$key] = $value;
        }
    }
    $query[$sortKey] = $column;
    $query[$dirKey] = $nextDir;

    return rtrim($basePath, '/') . '/?' . http_build_query($query);
}

function table_sort_render_th(
    string $column,
    string $label,
    string $basePath,
    array $columns,
    array $filters,
    array $queryKeys,
    array $numericColumns = [],
    string $defaultSort = '',
    string $defaultDir = 'asc',
    string $defaultDescColumn = '',
    string $sortKey = 'sort',
    string $dirKey = 'dir'
): void {
    $sortState = table_sort_state($columns, $defaultSort, $defaultDir, $filters, $sortKey, $dirKey);
    $active = table_sort_is_active($column, $sortState, $defaultSort);
    $direction = table_sort_direction($column, $sortState, $defaultSort);
    $href = table_sort_href(
        $basePath,
        $column,
        $columns,
        $filters,
        $queryKeys,
        $numericColumns,
        $defaultSort,
        $defaultDir,
        $defaultDescColumn,
        $sortKey,
        $dirKey
    );

    echo '<th class="admin-table-sort">';
    echo '<a class="admin-table-sort-link' . ($active ? ' is-active' : '') . '" href="' . htmlspecialchars($href) . '">';
    echo '<span>' . htmlspecialchars($label) . '</span>';
    echo '<span class="admin-table-sort-indicator" aria-hidden="true">';
    if ($active) {
        echo $direction === 'asc' ? '▲' : '▼';
    } else {
        echo '↕';
    }
    echo '</span></a></th>';
}

function table_sort_render_head_row(
    array $columns,
    string $basePath,
    array $filters,
    array $queryKeys,
    array $numericColumns = [],
    string $defaultSort = '',
    string $defaultDir = 'asc',
    string $defaultDescColumn = '',
    ?string $actionsHeader = null,
    string $sortKey = 'sort',
    string $dirKey = 'dir'
): void {
    echo '<tr>';
    foreach ($columns as $column => $label) {
        table_sort_render_th(
            $column,
            $label,
            $basePath,
            $columns,
            $filters,
            $queryKeys,
            $numericColumns,
            $defaultSort,
            $defaultDir,
            $defaultDescColumn,
            $sortKey,
            $dirKey
        );
    }
    if ($actionsHeader !== null) {
        echo '<th>' . htmlspecialchars($actionsHeader) . '</th>';
    }
    echo '</tr>';
}

function table_sort_hidden_inputs(
    array $filters,
    string $defaultSort,
    string $defaultDir = 'asc',
    string $sortKey = 'sort',
    string $dirKey = 'dir'
): void {
    $sort = (string) ($filters[$sortKey] ?? $defaultSort);
    $dir = (string) ($filters[$dirKey] ?? $defaultDir);
    if ($sort !== '') {
        echo '<input type="hidden" name="' . htmlspecialchars($sortKey) . '" value="' . htmlspecialchars($sort) . '" />';
    }
    if ($dir !== '') {
        echo '<input type="hidden" name="' . htmlspecialchars($dirKey) . '" value="' . htmlspecialchars($dir) . '" />';
    }
}

function table_sort_sql_clause(
    array $sqlMap,
    array $sortState,
    string $defaultSort,
    ?string $tiebreakerSortKey = null
): string {
    $sort = $sortState['sort'] ?? $defaultSort;
    if (!isset($sqlMap[$sort])) {
        $sort = $defaultSort;
    }

    $dir = ($sortState['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    $order = $sqlMap[$sort] . ' ' . $dir;

    if ($tiebreakerSortKey !== null && $sort !== $tiebreakerSortKey && isset($sqlMap[$tiebreakerSortKey])) {
        $order .= ', ' . $sqlMap[$tiebreakerSortKey] . ' ASC';
    }

    return $order;
}

function table_sort_rows(
    array $rows,
    array $sortState,
    array $accessors,
    array $numericColumns = [],
    string $defaultSort = '',
    string $defaultDir = 'asc'
): array {
    if ($rows === []) {
        return $rows;
    }

    $sort = $sortState['sort'] ?? $defaultSort;
    if (!isset($accessors[$sort])) {
        $sort = $defaultSort;
    }

    if (!isset($accessors[$sort])) {
        return $rows;
    }

    $dir = $sortState['dir'] ?? $defaultDir;
    $mult = $dir === 'desc' ? -1 : 1;
    $isNumeric = in_array($sort, $numericColumns, true);
    $accessor = $accessors[$sort];

    usort($rows, static function (array $a, array $b) use ($accessor, $isNumeric, $mult): int {
        $left = $accessor($a);
        $right = $accessor($b);

        if ($isNumeric) {
            $cmp = ((float) $left) <=> ((float) $right);
        } else {
            $cmp = strcasecmp((string) $left, (string) $right);
        }

        return $cmp * $mult;
    });

    return $rows;
}
