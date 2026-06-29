<?php

function capture_form_actions(callable $renderer): string
{
    ob_start();
    $renderer();

    return trim(ob_get_clean());
}

function render_form_actions(string $html, string $position = 'bottom'): void
{
    if ($html === '') {
        return;
    }

    $positionClass = $position === 'top' ? 'form-actions-bar--top' : 'form-actions-bar--bottom';
    echo '<div class="module-actions form-actions-bar ' . $positionClass . '">' . $html . '</div>';
}
