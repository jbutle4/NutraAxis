<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/support.php';

function zendesk_is_configured(): bool
{
    return trim((string) env('ZENDESK_SUBDOMAIN', '')) !== ''
        && trim((string) env('ZENDESK_EMAIL', '')) !== ''
        && trim((string) env('ZENDESK_API_TOKEN', '')) !== '';
}

function zendesk_config_error(): ?string
{
    if (zendesk_is_configured()) {
        return null;
    }

    return 'Zendesk is not configured. Set ZENDESK_SUBDOMAIN, ZENDESK_EMAIL, and ZENDESK_API_TOKEN in application settings.';
}

function zendesk_request(string $method, string $path, ?array $body = null): array
{
    $configError = zendesk_config_error();
    if ($configError !== null) {
        return ['ok' => false, 'error' => $configError, 'data' => null, 'status' => 0];
    }

    $subdomain = trim((string) env('ZENDESK_SUBDOMAIN', ''));
    $email = trim((string) env('ZENDESK_EMAIL', ''));
    $token = (string) env('ZENDESK_API_TOKEN', '');
    $url = 'https://' . $subdomain . '.zendesk.com/api/v2' . $path;

    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'cURL is required to connect to Zendesk.', 'data' => null, 'status' => 0];
    }

    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $email . '/token:' . $token,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 30,
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_THROW_ON_ERROR);
    } elseif ($method === 'PUT') {
        $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
        $options[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_THROW_ON_ERROR);
    }

    curl_setopt_array($ch, $options);
    $responseBody = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (is_resource($ch)) {
        curl_close($ch);
    }

    if ($responseBody === false) {
        return ['ok' => false, 'error' => 'Unable to reach Zendesk: ' . $curlError, 'data' => null, 'status' => $status];
    }

    try {
        $data = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable) {
        return ['ok' => false, 'error' => 'Zendesk returned an unexpected response.', 'data' => null, 'status' => $status];
    }

    if ($status >= 400) {
        $message = $data['error'] ?? $data['description'] ?? ('Zendesk request failed (HTTP ' . $status . ').');

        return ['ok' => false, 'error' => is_string($message) ? $message : 'Zendesk request failed.', 'data' => $data, 'status' => $status];
    }

    return ['ok' => true, 'error' => null, 'data' => $data, 'status' => $status];
}

function zendesk_users_index(array $users): array
{
    $index = [];
    foreach ($users as $user) {
        if (!is_array($user) || !isset($user['id'])) {
            continue;
        }
        $index[(int) $user['id']] = $user;
    }

    return $index;
}

function zendesk_build_search_query(array $filters): string
{
    $parts = ['type:ticket'];

    $status = trim((string) ($filters['status'] ?? ''));
    if ($status !== '') {
        $parts[] = 'status:' . $status;
    }

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $escaped = str_replace('"', '', $search);
        $parts[] = $escaped;
    }

    if (!support_can_update()) {
        $email = support_user_email();
        if ($email !== '') {
            $parts[] = 'requester:' . $email;
        }
    }

    return implode(' ', $parts);
}

function zendesk_list_sort_meta(array $filters): array
{
    $sortState = table_sort_state(SUPPORT_LIST_SORT_COLUMNS, 'updated', 'desc', $filters);
    $sort = $sortState['sort'];
    $dir = $sortState['dir'];

    $apiSortMap = [
        'updated'  => 'updated_at',
        'status'   => 'status',
        'priority' => 'priority',
        'id'       => 'created_at',
    ];

    return [
        'sort'      => $sort,
        'dir'       => $dir,
        'local'     => in_array($sort, ['id', 'subject', 'requester'], true),
        'api_field' => $apiSortMap[$sort] ?? 'updated_at',
    ];
}

function zendesk_search_ticket_page(array $filters, int $page, int $perPage, ?array $sortMeta = null): array
{
    $sortMeta ??= zendesk_list_sort_meta($filters);
    $query = zendesk_build_search_query($filters);
    $path = '/search.json?query=' . rawurlencode($query)
        . '&sort_by=' . rawurlencode($sortMeta['api_field'])
        . '&sort_order=' . rawurlencode($sortMeta['dir'])
        . '&page=' . $page
        . '&per_page=' . $perPage;

    $result = zendesk_request('GET', $path);
    if (!$result['ok']) {
        return $result;
    }

    $data = $result['data'];
    $tickets = [];
    foreach ($data['results'] ?? [] as $row) {
        if (!is_array($row) || ($row['result_type'] ?? '') !== 'ticket') {
            continue;
        }
        $tickets[] = $row;
    }

    return [
        'ok'       => true,
        'error'    => null,
        'tickets'  => $tickets,
        'count'    => (int) ($data['count'] ?? count($tickets)),
        'has_next' => !empty($data['next_page']),
    ];
}

function zendesk_fetch_all_search_tickets(array $filters, ?array $sortMeta = null): array
{
    $sortMeta ??= zendesk_list_sort_meta($filters);
    $tickets = [];
    $page = 1;
    $total = 0;
    $hasMore = true;

    while ($hasMore && $page <= 10) {
        $result = zendesk_search_ticket_page($filters, $page, 100, $sortMeta);
        if (!$result['ok']) {
            return $result;
        }

        $tickets = array_merge($tickets, $result['tickets']);
        $total = max($total, (int) ($result['count'] ?? 0));
        $hasMore = !empty($result['has_next']);
        $page++;
    }

    return [
        'ok'      => true,
        'error'   => null,
        'tickets' => $tickets,
        'count'   => $total > 0 ? $total : count($tickets),
    ];
}

function zendesk_priority_rank(string $priority): int
{
    return match (strtolower($priority)) {
        'urgent' => 4,
        'high'   => 3,
        'normal' => 2,
        'low'    => 1,
        default  => 0,
    };
}

function zendesk_status_rank(string $status): int
{
    return match (strtolower($status)) {
        'new'     => 1,
        'open'    => 2,
        'pending' => 3,
        'hold'    => 4,
        'solved'  => 5,
        'closed'  => 6,
        default   => 0,
    };
}

function zendesk_sort_tickets(array $tickets, array $users, array $sortMeta): array
{
    $sort = $sortMeta['sort'];
    $dir = $sortMeta['dir'];
    $mult = $dir === 'asc' ? 1 : -1;

    usort($tickets, function (array $a, array $b) use ($sort, $mult, $users): int {
        $left = 0;
        $right = 0;

        switch ($sort) {
            case 'id':
                $left = (int) ($a['id'] ?? 0);
                $right = (int) ($b['id'] ?? 0);
                break;
            case 'subject':
                $left = strtolower(zendesk_ticket_subject($a));
                $right = strtolower(zendesk_ticket_subject($b));
                return $mult * ($left <=> $right);
            case 'requester':
                $left = strtolower(zendesk_ticket_requester_label($a, $users));
                $right = strtolower(zendesk_ticket_requester_label($b, $users));
                return $mult * ($left <=> $right);
            case 'status':
                $left = zendesk_status_rank((string) ($a['status'] ?? ''));
                $right = zendesk_status_rank((string) ($b['status'] ?? ''));
                break;
            case 'priority':
                $left = zendesk_priority_rank((string) ($a['priority'] ?? ''));
                $right = zendesk_priority_rank((string) ($b['priority'] ?? ''));
                break;
            case 'updated':
            default:
                $left = strtotime((string) ($a['updated_at'] ?? '')) ?: 0;
                $right = strtotime((string) ($b['updated_at'] ?? '')) ?: 0;
                break;
        }

        return $mult * ($left <=> $right);
    });

    return $tickets;
}

function zendesk_list_tickets(array $filters = []): array
{
    $page = max(1, (int) ($filters['page'] ?? 1));
    $perPage = 25;
    $sortMeta = zendesk_list_sort_meta($filters);

    if ($sortMeta['local']) {
        $result = zendesk_fetch_all_search_tickets($filters, $sortMeta);
        if (!$result['ok']) {
            return $result;
        }

        $tickets = zendesk_enrich_tickets($result['tickets']);
        $users = zendesk_fetch_users(array_map(
            fn(array $ticket): int => (int) ($ticket['requester_id'] ?? 0),
            $tickets
        ));
        $tickets = zendesk_sort_tickets($tickets, $users, $sortMeta);
        $total = count($tickets);
        $offset = ($page - 1) * $perPage;

        return [
            'ok'       => true,
            'error'    => null,
            'tickets'  => array_slice($tickets, $offset, $perPage),
            'users'    => $users,
            'count'    => (int) ($result['count'] ?? $total),
            'page'     => $page,
            'has_next' => ($offset + $perPage) < $total,
            'has_prev' => $page > 1,
            'sort'     => $sortMeta['sort'],
            'dir'      => $sortMeta['dir'],
        ];
    }

    $result = zendesk_search_ticket_page($filters, $page, $perPage, $sortMeta);
    if (!$result['ok']) {
        return $result;
    }

    $tickets = zendesk_enrich_tickets($result['tickets']);
    $users = zendesk_fetch_users(array_map(
        fn(array $ticket): int => (int) ($ticket['requester_id'] ?? 0),
        $tickets
    ));

    if (in_array($sortMeta['sort'], ['status', 'priority'], true)) {
        $tickets = zendesk_sort_tickets($tickets, $users, $sortMeta);
    }

    return [
        'ok'       => true,
        'error'    => null,
        'tickets'  => $tickets,
        'users'    => $users,
        'count'    => (int) ($result['count'] ?? count($tickets)),
        'page'     => $page,
        'has_next' => !empty($result['has_next']),
        'has_prev' => $page > 1,
        'sort'     => $sortMeta['sort'],
        'dir'      => $sortMeta['dir'],
    ];
}

function zendesk_get_ticket(int $ticketId): array
{
    $result = zendesk_request('GET', '/tickets/' . $ticketId . '.json?include=users');
    if (!$result['ok']) {
        return $result;
    }

    $ticket = $result['data']['ticket'] ?? null;
    if (!is_array($ticket)) {
        return ['ok' => false, 'error' => 'Ticket not found.', 'ticket' => null, 'users' => []];
    }

    $users = zendesk_users_index($result['data']['users'] ?? []);

    if (!support_can_update()) {
        $requester = $users[(int) ($ticket['requester_id'] ?? 0)] ?? null;
        $requesterEmail = strtolower((string) ($requester['email'] ?? ''));
        if ($requesterEmail === '' || $requesterEmail !== strtolower(support_user_email())) {
            return ['ok' => false, 'error' => 'You do not have permission to view this ticket.', 'ticket' => null, 'users' => []];
        }
    }

    $commentsResult = zendesk_request('GET', '/tickets/' . $ticketId . '/comments.json');
    if (!$commentsResult['ok']) {
        return $commentsResult;
    }

    $comments = $commentsResult['data']['comments'] ?? [];

    return [
        'ok'       => true,
        'error'    => null,
        'ticket'   => $ticket,
        'users'    => $users,
        'comments' => is_array($comments) ? $comments : [],
    ];
}

function zendesk_create_ticket(array $input): array
{
    $subject = trim((string) ($input['subject'] ?? ''));
    $body = trim((string) ($input['body'] ?? ''));
    $priority = strtolower(trim((string) ($input['priority'] ?? 'normal')));

    if ($subject === '' || $body === '') {
        return ['ok' => false, 'error' => 'Subject and description are required.'];
    }

    if (!array_key_exists($priority, SUPPORT_TICKET_PRIORITIES)) {
        $priority = 'normal';
    }

    $payload = [
        'ticket' => [
            'subject'  => $subject,
            'priority' => $priority,
            'comment'  => ['body' => $body],
            'requester' => [
                'name'  => support_user_name(),
                'email' => support_user_email(),
            ],
        ],
    ];

    $tags = trim((string) ($input['tags'] ?? ''));
    if ($tags !== '') {
        $payload['ticket']['tags'] = array_values(array_filter(array_map('trim', explode(',', $tags))));
    }

    $result = zendesk_request('POST', '/tickets.json', $payload);
    if (!$result['ok']) {
        return $result;
    }

    $ticket = $result['data']['ticket'] ?? null;
    if (!is_array($ticket) || empty($ticket['id'])) {
        return ['ok' => false, 'error' => 'Zendesk did not return the created ticket.'];
    }

    return ['ok' => true, 'error' => null, 'id' => (int) $ticket['id']];
}

function zendesk_add_comment(int $ticketId, array $input): array
{
    $body = trim((string) ($input['body'] ?? ''));
    if ($body === '') {
        return ['ok' => false, 'error' => 'Comment cannot be empty.'];
    }

    $ticketResult = zendesk_get_ticket($ticketId);
    if (!$ticketResult['ok']) {
        return $ticketResult;
    }

    $ticket = $ticketResult['ticket'];
    $requester = $ticketResult['users'][(int) ($ticket['requester_id'] ?? 0)] ?? null;
    if (!support_can_comment_on_ticket($ticket, $requester)) {
        return ['ok' => false, 'error' => 'You do not have permission to comment on this ticket.'];
    }

    $public = !support_can_update() || !empty($input['public']);

    $payload = [
        'ticket' => [
            'comment' => [
                'html_body' => support_build_comment_html_body($body),
                'public'    => $public,
            ],
        ],
    ];

    $status = strtolower(trim((string) ($input['status'] ?? '')));
    if ($status !== '' && support_can_update() && array_key_exists($status, SUPPORT_TICKET_STATUSES)) {
        $payload['ticket']['status'] = $status;
    } elseif (!support_can_update() && support_ticket_is_open((string) ($ticket['status'] ?? ''))) {
        $payload['ticket']['status'] = 'open';
    }

    $result = zendesk_request('PUT', '/tickets/' . $ticketId . '.json', $payload);
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null];
}

function zendesk_update_ticket(int $ticketId, array $input): array
{
    $ticketResult = zendesk_get_ticket($ticketId);
    if (!$ticketResult['ok']) {
        return $ticketResult;
    }

    $ticket = $ticketResult['ticket'];
    $payload = ['ticket' => []];
    $changes = [];

    $status = strtolower(trim((string) ($input['status'] ?? '')));
    if ($status !== '' && array_key_exists($status, SUPPORT_TICKET_STATUSES)) {
        $currentStatus = strtolower((string) ($ticket['status'] ?? ''));
        if ($status !== $currentStatus) {
            $payload['ticket']['status'] = $status;
            $changes[] = 'Status: ' . support_status_label($currentStatus) . ' → ' . support_status_label($status);
        }
    }

    $priority = strtolower(trim((string) ($input['priority'] ?? '')));
    if ($priority !== '' && array_key_exists($priority, SUPPORT_TICKET_PRIORITIES)) {
        $currentPriority = strtolower((string) ($ticket['priority'] ?? 'normal'));
        if ($priority !== $currentPriority) {
            $payload['ticket']['priority'] = $priority;
            $changes[] = 'Priority: ' . support_priority_label($currentPriority) . ' → ' . support_priority_label($priority);
        }
    }

    if ($changes === []) {
        return ['ok' => false, 'error' => 'No changes to save.'];
    }

    $payload['ticket']['comment'] = [
        'html_body' => support_build_comment_html_body(implode("\n", $changes)),
        'public'    => false,
    ];

    $result = zendesk_request('PUT', '/tickets/' . $ticketId . '.json', $payload);
    if (!$result['ok']) {
        return $result;
    }

    return ['ok' => true, 'error' => null];
}

function zendesk_fetch_users(array $userIds): array
{
    $ids = array_values(array_unique(array_filter(array_map(
        fn($id): int => (int) $id,
        $userIds
    ))));

    if ($ids === []) {
        return [];
    }

    $result = zendesk_request('GET', '/users/show_many.json?ids=' . implode(',', $ids));
    if (!$result['ok']) {
        return [];
    }

    return zendesk_users_index($result['data']['users'] ?? []);
}

function zendesk_ticket_requester_label(array $ticket, array $users): string
{
    $user = $users[(int) ($ticket['requester_id'] ?? 0)] ?? null;
    if ($user === null) {
        return '—';
    }

    $name = trim((string) ($user['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $email = trim((string) ($user['email'] ?? ''));

    return $email !== '' ? $email : '—';
}

function zendesk_ticket_subject(array $ticket): string
{
    foreach (['subject', 'raw_subject'] as $field) {
        $value = trim((string) ($ticket[$field] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    $description = trim((string) ($ticket['description'] ?? ''));
    if ($description !== '') {
        $collapsed = trim((string) (preg_replace('/\s+/u', ' ', $description) ?? $description));
        if (strlen($collapsed) > 120) {
            return substr($collapsed, 0, 117) . '...';
        }

        return $collapsed;
    }

    $id = (int) ($ticket['id'] ?? 0);

    return $id > 0 ? 'Ticket #' . $id : 'Untitled ticket';
}

function zendesk_enrich_tickets(array $tickets): array
{
    $missingIds = [];
    foreach ($tickets as $index => $ticket) {
        if (trim((string) ($ticket['subject'] ?? '')) !== '' && trim((string) ($ticket['description'] ?? '')) !== '') {
            continue;
        }
        $id = (int) ($ticket['id'] ?? 0);
        if ($id > 0) {
            $missingIds[$index] = $id;
        }
    }

    if ($missingIds === []) {
        return $tickets;
    }

    $result = zendesk_request('GET', '/tickets/show_many.json?ids=' . implode(',', array_values($missingIds)));
    if (!$result['ok']) {
        return $tickets;
    }

    $byId = [];
    foreach ($result['data']['tickets'] ?? [] as $ticket) {
        if (!is_array($ticket) || empty($ticket['id'])) {
            continue;
        }
        $byId[(int) $ticket['id']] = $ticket;
    }

    foreach ($missingIds as $index => $id) {
        if (!isset($byId[$id])) {
            continue;
        }
        $tickets[$index] = array_merge($tickets[$index], $byId[$id]);
    }

    return $tickets;
}

function zendesk_format_comment_body(array|string $comment): string
{
    $allowedTags = '<p><hr><br><strong><a><em><ul><ol><li>';

    if (is_array($comment)) {
        $html = trim((string) ($comment['html_body'] ?? ''));
        if ($html !== '') {
            return strip_tags($html, $allowedTags);
        }

        $body = (string) ($comment['body'] ?? '');
    } else {
        $body = $comment;
    }

    if (str_contains($body, '<hr') || str_contains($body, '<p>')) {
        return strip_tags($body, $allowedTags);
    }

    return nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
}

function zendesk_format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($value);

        return $dt->format('M j, Y g:i A T');
    } catch (Throwable) {
        return $value;
    }
}

function zendesk_user_label(?array $user): string
{
    if ($user === null) {
        return '—';
    }

    $name = trim((string) ($user['name'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));

    if ($name !== '' && $email !== '') {
        return $name . ' <' . $email . '>';
    }

    return $name !== '' ? $name : ($email !== '' ? $email : '—');
}
