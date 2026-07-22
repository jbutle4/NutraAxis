<?php

require_once __DIR__ . '/auth.php';

const LINKS_PERMISSION_COLUMN = 'LinksIndex';

const LINK_CATEGORIES = [
    'Accounting',
    'eCommerce',
    'IT',
    'Marketing',
    'Marketing-IT',
    'NA Operational',
    'Reference',
    'Support',
    'Web application',
    'MS365 Application',
    'Document',
    'External Website - Reference',
    'Other',
];

const LINK_STATUSES = [
    'active',
    'not active',
];

const LINKS_LIST_SORT_COLUMNS = [
    'name'         => 'Name (click to open link)',
    'category'     => 'Category',
    'status'       => 'Status',
    'registration' => 'Registration',
    'description'  => 'Description',
];

const LINKS_LIST_SORT_SQL = [
    'name'         => 'LinkName',
    'category'     => 'LinkCategory',
    'status'       => 'LinkStatus',
    'registration' => 'UserRegistrationRequired',
    'description'  => 'LinkDescription',
];

function links_permission_value(): ?string
{
    return auth_permission_value(LINKS_PERMISSION_COLUMN);
}

function links_can_read(): bool
{
    return auth_can_read(LINKS_PERMISSION_COLUMN);
}

function links_can_create(): bool
{
    return auth_can_create(LINKS_PERMISSION_COLUMN);
}

function links_can_update(): bool
{
    return auth_can_update(LINKS_PERMISSION_COLUMN);
}

function links_can_delete(): bool
{
    return auth_can_delete(LINKS_PERMISSION_COLUMN);
}

function links_require_read(): void
{
    auth_require_login();
    if (links_can_read()) {
        return;
    }
    auth_render_access_denied('You do not have permission to view the Links Index.');
}

function links_require_create(): void
{
    links_require_read();
    if (links_can_create()) {
        return;
    }
    auth_render_access_denied('You do not have permission to create links.');
}

function links_require_update(): void
{
    links_require_read();
    if (links_can_update()) {
        return;
    }
    auth_render_access_denied('You do not have permission to update links.');
}

function links_require_delete(): void
{
    links_require_read();
    if (links_can_delete()) {
        return;
    }
    auth_render_access_denied('You do not have permission to delete links.');
}

function links_status_class(string $status): string
{
    return $status === 'active' ? 'status-received' : 'status-cancelled';
}

function links_status_label(string $status): string
{
    return $status === 'active' ? 'Active' : 'Not active';
}

function links_external_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    if (!preg_match('#^https?://#i', $url)) {
        return 'https://' . $url;
    }

    return $url;
}

function links_external_target_attrs(): string
{
    return 'target="_blank" rel="noopener noreferrer"';
}

function links_external_name_attrs(): string
{
    return links_external_target_attrs() . ' class="table-name-link"';
}

function links_link_to_form(array $link): array
{
    return [
        'link_id'                    => (int) $link['LinkID'],
        'link_name'                  => (string) $link['LinkName'],
        'link_description'           => (string) ($link['LinkDescription'] ?? ''),
        'link_category'              => (string) $link['LinkCategory'],
        'link_status'                => (string) $link['LinkStatus'],
        'user_registration_required' => !empty($link['UserRegistrationRequired']),
        'link_url'                   => (string) $link['LinkURL'],
    ];
}

function links_from_input(array $input): array
{
    return [
        'link_name'                  => trim($input['link_name'] ?? ''),
        'link_description'           => trim($input['link_description'] ?? ''),
        'link_category'              => trim($input['link_category'] ?? ''),
        'link_status'                => trim($input['link_status'] ?? 'active'),
        'user_registration_required' => (string) ($input['user_registration_required'] ?? '0') === '1',
        'link_url'                   => trim($input['link_url'] ?? ''),
    ];
}

function links_list(array $filters = []): array
{
    $pdo = db();
    $sql = 'SELECT * FROM dbo.LinksIndex WHERE 1 = 1';
    $params = [];

    if (!empty($filters['status'])) {
        $sql .= ' AND LinkStatus = :status';
        $params['status'] = $filters['status'];
    }

    if (!empty($filters['category'])) {
        $sql .= ' AND LinkCategory = :category';
        $params['category'] = $filters['category'];
    }

    if (!empty($filters['q'])) {
        [$likeSql, $likeParams] = db_like_or([
            'LinkName',
            'LinkDescription',
            'LinkURL'
        ], (string) $filters['q']);
        $sql .= ' AND ' . $likeSql;
        $params = array_merge($params, $likeParams);
    }

    $sortState = table_sort_state(LINKS_LIST_SORT_COLUMNS, 'category', 'asc', $filters);
    $sql .= ' ORDER BY ' . table_sort_sql_clause(LINKS_LIST_SORT_SQL, $sortState, 'category', 'name');

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function links_group_by_category(array $links): array
{
    $grouped = [];
    foreach ($links as $link) {
        $category = (string) $link['LinkCategory'];
        $grouped[$category][] = $link;
    }

    $ordered = [];
    foreach (LINK_CATEGORIES as $category) {
        if (!empty($grouped[$category])) {
            $ordered[$category] = $grouped[$category];
        }
    }

    foreach ($grouped as $category => $items) {
        if (!isset($ordered[$category])) {
            $ordered[$category] = $items;
        }
    }

    return $ordered;
}

function links_get(int $linkId): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare(<<<SQL
        SELECT
            l.LinkID,
            l.LinkName,
            l.LinkDescription,
            l.LinkCategory,
            l.LinkStatus,
            l.UserRegistrationRequired,
            l.LinkURL,
            l.CreateDate,
            l.ModifiedDate,
            l.ModifiedbyUser,
            u.UserName AS ModifiedByName
        FROM dbo.LinksIndex l
        LEFT JOIN dbo.[User] u ON u.UserID = l.ModifiedbyUser
        WHERE l.LinkID = :id
    SQL);
    $stmt->execute(['id' => $linkId]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function links_save(array $input, ?int $linkId = null): array
{
    $data = links_from_input($input);
    $actorId = auth_user()['UserID'] ?? null;

    if ($data['link_name'] === '') {
        return ['ok' => false, 'error' => 'Link name is required.'];
    }

    if ($data['link_url'] === '') {
        return ['ok' => false, 'error' => 'Link URL is required.'];
    }

    if (!in_array($data['link_category'], LINK_CATEGORIES, true)) {
        return ['ok' => false, 'error' => 'Select a valid category.'];
    }

    if (!in_array($data['link_status'], LINK_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Select a valid status.'];
    }

    $params = [
        'name'         => $data['link_name'],
        'description'  => $data['link_description'] !== '' ? $data['link_description'] : null,
        'category'     => $data['link_category'],
        'status'       => $data['link_status'],
        'registration' => $data['user_registration_required'] ? 1 : 0,
        'url'          => links_external_url($data['link_url']),
        'actor'        => $actorId,
    ];

    $pdo = db();

    try {
        if ($linkId === null) {
            $stmt = $pdo->prepare(<<<SQL
                INSERT INTO dbo.LinksIndex (
                    LinkName, LinkDescription, LinkCategory, LinkStatus,
                    UserRegistrationRequired, LinkURL, ModifiedbyUser
                )
                OUTPUT INSERTED.LinkID AS inserted_id
                VALUES (
                    :name, :description, :category, :status,
                    :registration, :url, :actor
                )
            SQL);
            $stmt->execute($params);
            $linkId = db_fetch_inserted_int($stmt, 'inserted_id');
        } else {
            $existing = links_get($linkId);
            if ($existing === null) {
                return ['ok' => false, 'error' => 'Link not found.'];
            }

            $params['id'] = $linkId;
            $stmt = $pdo->prepare(<<<SQL
                UPDATE dbo.LinksIndex
                SET LinkName = :name,
                    LinkDescription = :description,
                    LinkCategory = :category,
                    LinkStatus = :status,
                    UserRegistrationRequired = :registration,
                    LinkURL = :url,
                    ModifiedDate = SYSUTCDATETIME(),
                    ModifiedbyUser = :actor
                WHERE LinkID = :id
            SQL);
            $stmt->execute($params);
        }

        return ['ok' => true, 'error' => null, 'id' => $linkId];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unable to save link. Please try again.'];
    }
}

function links_delete(int $linkId): array
{
    $existing = links_get($linkId);
    if ($existing === null) {
        return ['ok' => false, 'error' => 'Link not found.'];
    }

    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM dbo.LinksIndex WHERE LinkID = :id');
    $stmt->execute(['id' => $linkId]);

    return ['ok' => true, 'error' => null];
}
