<?php

declare(strict_types=1);

require_once __DIR__ . '/marketing-manual.php';

/**
 * Field reference for every Marketing & Research form, shown in the User Manual.
 * Each form: page (hub slug), form (name as shown), open (manual link to the form), who, purpose, optional note,
 * and fields — each with field, req (true or an either/or hint), enter, now, later, watch.
 */
const MKT_FIELD_REFERENCE_GROUPS = ['research', 'content', 'campaigns', 'performance', 'seo', 'admin'];

function mkt_field_reference(): array
{
    static $forms = null;
    if ($forms === null) {
        $forms = [];
        foreach (MKT_FIELD_REFERENCE_GROUPS as $group) {
            $groupForms = require __DIR__ . '/marketing-field-reference/' . $group . '.php';
            $clash = array_intersect_key($groupForms, $forms);
            if ($clash !== []) {
                throw new LogicException('Duplicate field reference form key(s): ' . implode(', ', array_keys($clash)));
            }
            $forms += $groupForms;
        }
    }

    return $forms;
}

/** Forms grouped by page, in hub order: slug => [key => form]. */
function mkt_field_reference_by_page(): array
{
    $byPage = array_fill_keys(array_keys(mkt_manual_page_index()), []);
    foreach (mkt_field_reference() as $key => $form) {
        $byPage[$form['page']][$key] = $form;
    }

    return array_filter($byPage);
}

function mkt_field_reference_render_form(string $key, array $form): string
{
    $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES);
    $open = mkt_manual_link($form['open']);
    $cell = static fn(?string $text): string => $text === null || $text === '' || $text === '—'
        ? '<span class="form-hint">—</span>'
        : mkt_manual_text($text);

    $rows = '';
    foreach ($form['fields'] as $field) {
        $req = $field['req'] ?? false;
        $badge = $req === false ? '' : '<span class="mkt-field-req">' . ($req === true ? 'Required' : $e($req)) . '</span>';
        $rows .= '<tr>'
            . '<th scope="row">' . $e($field['field']) . $badge . '</th>'
            . '<td>' . $cell($field['enter']) . '</td>'
            . '<td>' . $cell($field['now']) . '</td>'
            . '<td>' . $cell($field['later'] ?? null) . '</td>'
            . '<td>' . $cell($field['watch'] ?? null) . '</td>'
            . '</tr>';
    }

    return '<article class="mkt-field-form" id="field-' . $e($key) . '">'
        . '<h4>' . $e($form['form'])
        . ($open !== null ? ' <a class="mkt-field-open" href="' . $e($open['href']) . '" target="_blank" rel="noopener">Open this form ↗</a>' : '')
        . '</h4>'
        . '<p class="mkt-field-meta"><strong>Who:</strong> ' . $e($form['who']) . '</p>'
        . '<p>' . mkt_manual_text($form['purpose']) . '</p>'
        . '<div class="admin-table-wrap"><table class="admin-table mkt-field-table">'
        . '<thead><tr><th>Field</th><th>What to enter</th><th>How it is used now</th><th>How it is used later</th><th>Watch out</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table></div>'
        . (!empty($form['note']) ? '<p class="mkt-field-note">' . mkt_manual_text($form['note']) . '</p>' : '')
        . '</article>';
}
