<?php

declare(strict_types=1);

/**
 * Read side of the NutraAxis blog: live posts from dbo.MktBlogPost shaped for api/public/blog.php.
 * No auth or session — only published rows and public fields leave this file.
 */

require_once __DIR__ . '/database.php';

const BLOG_SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
const BLOG_DISPLAY_TIMEZONE = 'America/Chicago';
const BLOG_WORDS_PER_MINUTE = 225;

function blog_page_url(): string
{
    static $url = null;
    if ($url === null) {
        $stmt = db()->prepare('SELECT SettingValue FROM dbo.MktSetting WHERE SettingKey = :k');
        $stmt->execute(['k' => 'blog.page_url']);
        $url = rtrim(trim((string) ($stmt->fetchColumn() ?: '')), '/') ?: 'https://nutraaxislabs.com/our-blog';
    }

    return $url;
}

function blog_post_url(string $slug): string
{
    return blog_page_url() . '?post=' . rawurlencode($slug);
}

function blog_valid_slug(string $slug): bool
{
    return strlen($slug) >= 3 && strlen($slug) <= 160 && preg_match(BLOG_SLUG_PATTERN, $slug) === 1;
}

function blog_select_columns(bool $withBody): string
{
    return 'b.BlogPostID, b.Slug, b.Title, b.MetaTitle, b.MetaDescription, b.Excerpt, b.WordCount, b.AuthorName,
            b.HeroImageUrl, b.HeroImageAlt, b.FirstPublishedAt, b.PublishedAt' . ($withBody ? ', b.BodyHtml' : '');
}

/** Live posts, newest first by first publication. */
function blog_list_live(int $limit = 50): array
{
    $limit = max(1, min(200, $limit));

    return db()->query("SELECT TOP ({$limit}) " . blog_select_columns(false) . "
        FROM dbo.MktBlogPost b WHERE b.Status = N'published'
        ORDER BY b.FirstPublishedAt DESC, b.BlogPostID DESC")->fetchAll(PDO::FETCH_ASSOC);
}

function blog_get_live(string $slug): ?array
{
    if (!blog_valid_slug($slug)) {
        return null;
    }
    $stmt = db()->prepare('SELECT ' . blog_select_columns(true) . " FROM dbo.MktBlogPost b WHERE b.Slug = :slug AND b.Status = N'published'");
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function blog_format_date(?string $utc): ?string
{
    if ($utc === null || trim($utc) === '') {
        return null;
    }

    return (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(BLOG_DISPLAY_TIMEZONE))->format('F j, Y');
}

function blog_iso(?string $utc): ?string
{
    return $utc === null || trim($utc) === '' ? null : (new DateTimeImmutable($utc, new DateTimeZone('UTC')))->format('c');
}

function blog_to_api_item(array $row): array
{
    $published = blog_format_date($row['FirstPublishedAt'] ?? null);
    $updated = blog_format_date($row['PublishedAt'] ?? null);
    $item = [
        'slug'              => (string) $row['Slug'],
        'url'               => blog_post_url((string) $row['Slug']),
        'title'             => (string) $row['Title'],
        'meta_title'        => (string) ($row['MetaTitle'] ?? ''),
        'meta_description'  => (string) ($row['MetaDescription'] ?? ''),
        'excerpt'           => (string) ($row['Excerpt'] ?? ''),
        'author'            => (string) ($row['AuthorName'] ?? ''),
        'published_at'      => blog_iso($row['FirstPublishedAt'] ?? null),
        'published_display' => $published,
        'updated_at'        => $updated !== $published ? blog_iso($row['PublishedAt'] ?? null) : null,
        'updated_display'   => $updated !== $published ? $updated : null,
        'reading_minutes'   => max(1, (int) round(((int) ($row['WordCount'] ?? 0)) / BLOG_WORDS_PER_MINUTE)),
        'hero_image_url'    => (string) ($row['HeroImageUrl'] ?? ''),
        'hero_image_alt'    => (string) ($row['HeroImageAlt'] ?? ''),
    ];
    if (array_key_exists('BodyHtml', $row)) {
        $item['body_html'] = (string) $row['BodyHtml'];
    }

    return $item;
}
