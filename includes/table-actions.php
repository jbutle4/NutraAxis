<?php

function table_actions_header(array $labels = ['View', 'Edit']): string
{
    return implode(' | ', $labels);
}

/**
 * @param array<int, array{href?: string, label?: string, html?: string}> $actions
 */
function table_actions_cell(array $actions): void
{
    echo '<td class="table-actions">';
    $parts = [];
    foreach ($actions as $action) {
        if (!empty($action['html'])) {
            $parts[] = $action['html'];
        } elseif (!empty($action['href'])) {
            $label = $action['label'] ?? 'View';
            $parts[] = '<a href="' . htmlspecialchars($action['href']) . '">' . htmlspecialchars($label) . '</a>';
        }
    }
    echo implode(' | ', $parts);
    echo '</td>';
}

function table_view_edit_cell(string $viewHref, ?string $editHref = null, bool $showEdit = true): void
{
    $actions = [['href' => $viewHref, 'label' => 'View']];
    if ($editHref !== null && $showEdit) {
        $actions[] = ['href' => $editHref, 'label' => 'Edit'];
    }
    table_actions_cell($actions);
}

function table_action_delete_form(string $action, array $hiddenFields, string $confirmMessage, string $label = 'Delete'): string
{
    $fields = '';
    foreach ($hiddenFields as $name => $value) {
        $fields .= '<input type="hidden" name="' . htmlspecialchars((string) $name) . '" value="' . htmlspecialchars((string) $value) . '" />';
    }

    $confirm = htmlspecialchars($confirmMessage, ENT_QUOTES);

    return '<form method="post" action="' . htmlspecialchars($action) . '" class="inline-form table-action-form" onsubmit="return confirm(\'' . $confirm . '\');">'
        . $fields
        . '<button type="submit" class="table-action-btn table-action-btn-danger">' . htmlspecialchars($label) . '</button>'
        . '</form>';
}
