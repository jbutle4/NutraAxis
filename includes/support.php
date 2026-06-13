<?php

require_once __DIR__ . '/auth.php';

const SUPPORT_PERMISSION_COLUMN = 'Support';

const SUPPORT_TICKET_STATUSES = [
    'new'     => 'New',
    'open'    => 'Open',
    'pending' => 'Pending',
    'hold'    => 'On Hold',
    'solved'  => 'Solved',
    'closed'  => 'Closed',
];

const SUPPORT_TICKET_PRIORITIES = [
    'low'    => 'Low',
    'normal' => 'Normal',
    'high'   => 'High',
    'urgent' => 'Urgent',
];

function support_permission_value(): ?string
{
    return auth_permission_value(SUPPORT_PERMISSION_COLUMN);
}

function support_can_read(): bool
{
    return auth_can_read(SUPPORT_PERMISSION_COLUMN);
}

function support_can_create(): bool
{
    return auth_can_create(SUPPORT_PERMISSION_COLUMN);
}

function support_can_update(): bool
{
    return auth_can_update(SUPPORT_PERMISSION_COLUMN);
}

function support_require_read(): void
{
    auth_require_login();
    if (support_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view Support.');
}

function support_require_create(): void
{
    support_require_read();
    if (support_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create support tickets.');
}

function support_require_update(): void
{
    support_require_read();
    if (support_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update support tickets.');
}

function support_user_email(): string
{
    $user = auth_user();

    return trim((string) ($user['UserLogin'] ?? ''));
}

function support_user_name(): string
{
    $user = auth_user();

    return trim((string) ($user['UserName'] ?? support_user_email()));
}

function support_status_class(string $status): string
{
    return match (strtolower($status)) {
        'new'     => 'status-submitted',
        'open'    => 'status-received',
        'pending' => 'status-draft',
        'hold'    => 'status-draft',
        'solved'  => 'status-approved',
        'closed'  => 'status-cancelled',
        default   => 'status-draft',
    };
}

function support_status_label(string $status): string
{
    return SUPPORT_TICKET_STATUSES[strtolower($status)] ?? ucfirst($status);
}

function support_priority_label(string $priority): string
{
    return SUPPORT_TICKET_PRIORITIES[strtolower($priority)] ?? ucfirst($priority);
}

function support_ticket_is_open(string $status): bool
{
    return !in_array(strtolower($status), ['solved', 'closed'], true);
}

function support_is_agent(): bool
{
    return support_can_update();
}

function support_can_comment_on_ticket(array $ticket, ?array $requester = null): bool
{
    if (!support_can_update()) {
        return false;
    }

    return support_ticket_is_open((string) ($ticket['status'] ?? ''));
}

function support_access_mode_label(): string
{
    if (support_can_update()) {
        return 'Zendesk agent — all tickets, replies, and status changes';
    }

    if (support_can_create()) {
        return 'Requester — your tickets, create new requests, view only';
    }

    return 'View only — your tickets, no edits or replies';
}

const SUPPORT_LIST_SORT_COLUMNS = [
    'id'        => 'ID',
    'subject'   => 'Subject',
    'requester' => 'Requester',
    'status'    => 'Status',
    'priority'  => 'Priority',
    'updated'   => 'Updated',
];

const SUPPORT_LIST_SORT_NUMERIC = ['id'];

function support_list_filters(): array
{
    return [
        'status' => strtolower(trim($_GET['status'] ?? '')),
        'q'      => trim($_GET['q'] ?? ''),
        'page'   => max(1, (int) ($_GET['page'] ?? 1)),
    ] + table_sort_state(SUPPORT_LIST_SORT_COLUMNS, 'updated', 'desc', $_GET);
}

function support_list_page_href(array $filters, int $page): string
{
    $query = array_filter([
        'status' => ($filters['status'] ?? '') !== '' ? $filters['status'] : null,
        'q'      => ($filters['q'] ?? '') !== '' ? $filters['q'] : null,
        'sort'   => $filters['sort'] ?? null,
        'dir'    => $filters['dir'] ?? null,
        'page'   => $page > 1 ? $page : null,
    ], fn($value) => $value !== null && $value !== '');

    return '/support/?' . http_build_query($query);
}

function support_plain_text_to_html_paragraphs(string $text): string
{
    $parts = preg_split('/\R+/u', trim($text)) ?: [];
    $parts = array_values(array_filter($parts, fn(string $part): bool => trim($part) !== ''));

    if ($parts === []) {
        return '';
    }

    $html = '';
    foreach ($parts as $part) {
        $html .= '<p>' . htmlspecialchars(trim($part), ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return $html;
}

function support_ops_attribution_footer(): string
{
    $name = htmlspecialchars(support_user_name(), ENT_QUOTES, 'UTF-8');
    $email = trim(support_user_email());
    $when = htmlspecialchars(
        (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('M j, Y g:i A T'),
        ENT_QUOTES,
        'UTF-8'
    );

    $userPart = $name;
    if ($email !== '') {
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $userPart = $name . ' (<a href="mailto:' . $safeEmail . '">' . $safeEmail . '</a>)';
    }

    return '<hr><p><strong>Ops user:</strong> ' . $userPart
        . '<br><strong>Date & time:</strong> ' . $when . '</p>';
}

function support_build_comment_html_body(string $message): string
{
    return support_plain_text_to_html_paragraphs($message) . support_ops_attribution_footer();
}
