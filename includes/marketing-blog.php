<?php

declare(strict_types=1);

/**
 * Publishing approved content pieces to the NutraAxis blog (dbo.MktBlogPost → api/public/blog.php → /our-blog).
 * Publishing is always a person's click on an approved piece; the blog keeps the approved copy until the next publish.
 */

require_once __DIR__ . '/marketing-content.php';
require_once __DIR__ . '/blog-public.php';

const MKT_BLOG_EXCERPT_MAX = 300;

/** Publishing puts copy on the public site, so it takes the same full Marketing access as editorial approval. */
function mkt_blog_can_publish(): bool
{
    return marketing_can_admin();
}

function mkt_blog_for_content(int $contentId): ?array
{
    $stmt = db()->prepare('SELECT b.*, v.VersionNo FROM dbo.MktBlogPost b LEFT JOIN dbo.MktContentVersion v ON v.VersionID = b.VersionID WHERE b.ContentID = :id');
    $stmt->execute(['id' => $contentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function mkt_blog_is_live(?array $post): bool
{
    return $post !== null && $post['Status'] === 'published';
}

function mkt_blog_slugify(string $title): string
{
    $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) : $title;
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii)), '-');
    if (strlen($slug) > 80) {
        $slug = substr($slug, 0, 80);
        $slug = str_contains($slug, '-') ? substr($slug, 0, (int) strrpos($slug, '-')) : $slug;
    }

    return strlen($slug) >= 3 ? $slug : 'post-' . date('Ymd');
}

function mkt_blog_slug_taken(string $slug, int $contentId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM dbo.MktBlogPost WHERE Slug = :slug AND ContentID <> :id');
    $stmt->execute(['slug' => $slug, 'id' => $contentId]);

    return (int) $stmt->fetchColumn() > 0;
}

/** A free slug for the title: the plain slug, else -2, -3 … */
function mkt_blog_suggest_slug(string $title, int $contentId): string
{
    $base = mkt_blog_slugify($title);
    $slug = $base;
    for ($n = 2; mkt_blog_slug_taken($slug, $contentId) && $n < 100; $n++) {
        $slug = $base . '-' . $n;
    }

    return $slug;
}

/** Plain-text summary for the history list: the meta description, else the opening paragraph. */
function mkt_blog_default_excerpt(array $version): string
{
    $text = trim((string) ($version['MetaDescription'] ?? ''));
    if ($text === '') {
        foreach (preg_split('/\n\s*\n/', str_replace("\r\n", "\n", (string) $version['Body'])) as $block) {
            $block = trim($block);
            if ($block !== '' && preg_match('/^(#|[-*+]\s|\d+[.)]\s|>|\|)/', $block) !== 1) {
                $text = $block;
                break;
            }
        }
    }
    $text = (string) preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', $text);
    $text = trim((string) preg_replace('/\s+/', ' ', str_replace(['**', '*', '`', '_'], '', $text)));

    return mb_strimwidth($text, 0, MKT_BLOG_EXCERPT_MAX - 1, '…');
}

/**
 * The blog body: the same safe Markdown subset as the portal preview, with headings stepped down one level
 * (the post title is the page's H2) and plain table markup for the blog stylesheet.
 */
function mkt_blog_html(string $markdown): string
{
    $html = mkt_markdown_html($markdown);
    $html = (string) preg_replace_callback('/<(\/?)h([2-4])>/', static fn(array $m): string => '<' . $m[1] . 'h' . ((int) $m[2] + 1) . '>', $html);

    return str_replace('<div class="admin-table-wrap"><table class="admin-table">', '<div class="na-blog-table"><table>', $html);
}

/**
 * Publish (or re-publish) the piece's approved current version to the blog.
 *
 * @param array{slug?: string, excerpt?: string, author?: string, hero_image_url?: string, hero_image_alt?: string} $input
 */
function mkt_blog_publish(int $contentId, array $input): array
{
    if (!mkt_blog_can_publish()) {
        return ['ok' => false, 'error' => 'Publishing to the blog needs full Marketing access (the same as editorial approval).'];
    }
    $content = mkt_content_get($contentId);
    if ($content === null) {
        return ['ok' => false, 'error' => 'Content not found.'];
    }
    if ($content['Stage'] !== 'approved') {
        return ['ok' => false, 'error' => 'Only approved content can be published to the blog. Edits since approval must be reviewed again first.'];
    }
    $version = $content['CurrentVersionID'] ? mkt_content_version_get((int) $content['CurrentVersionID'], $contentId) : null;
    if ($version === null) {
        return ['ok' => false, 'error' => 'This piece has no approved version to publish.'];
    }

    $slug = strtolower(trim((string) ($input['slug'] ?? '')));
    if (!blog_valid_slug($slug)) {
        return ['ok' => false, 'error' => 'The post address must be 3–160 characters: lowercase letters, numbers and single hyphens (e.g. glp1-nutrient-gaps).'];
    }
    if (mkt_blog_slug_taken($slug, $contentId)) {
        return ['ok' => false, 'error' => "Another blog post already uses the address \"{$slug}\"."];
    }
    $excerpt = trim((string) preg_replace('/\s+/', ' ', (string) ($input['excerpt'] ?? '')));
    $excerpt = $excerpt !== '' ? mb_substr($excerpt, 0, MKT_BLOG_EXCERPT_MAX) : mkt_blog_default_excerpt($version);
    $author = mb_substr(trim((string) ($input['author'] ?? '')), 0, 150) ?: (string) marketing_setting('blog.author', 'NutraAxis Team');
    $heroUrl = trim((string) ($input['hero_image_url'] ?? ''));
    if ($heroUrl !== '' && (!str_starts_with($heroUrl, 'https://') || !mkt_cal_valid_url($heroUrl))) {
        return ['ok' => false, 'error' => 'The header image must be an https:// URL.'];
    }
    $heroAlt = $heroUrl === '' ? null : (mb_substr(trim((string) ($input['hero_image_alt'] ?? '')), 0, 300) ?: (string) $version['Title']);

    $existing = mkt_blog_for_content($contentId);
    $values = [
        'vid'      => (int) $version['VersionID'],
        'slug'     => $slug,
        'title'    => (string) $version['Title'],
        'mtitle'   => $version['MetaTitle'] !== null && $version['MetaTitle'] !== '' ? (string) $version['MetaTitle'] : null,
        'mdesc'    => $version['MetaDescription'] !== null && $version['MetaDescription'] !== '' ? (string) $version['MetaDescription'] : null,
        'excerpt'  => $excerpt,
        'body'     => mkt_blog_html((string) $version['Body']),
        'words'    => (int) $version['WordCount'],
        'author'   => $author,
        'hero'     => $heroUrl !== '' ? mb_substr($heroUrl, 0, 1000) : null,
        'hero_alt' => $heroAlt,
        'u'        => marketing_user_id(),
        'cid'      => $contentId,
    ];
    $url = blog_post_url($slug);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($existing === null) {
            $pdo->prepare("INSERT INTO dbo.MktBlogPost (ContentID, VersionID, Slug, Title, MetaTitle, MetaDescription, Excerpt, BodyHtml, WordCount, AuthorName, HeroImageUrl, HeroImageAlt, Status, PublishedBy)
                VALUES (:cid, :vid, :slug, :title, :mtitle, :mdesc, :excerpt, :body, :words, :author, :hero, :hero_alt, N'published', :u)")
                ->execute($values);
        } else {
            $pdo->prepare("UPDATE dbo.MktBlogPost SET VersionID = :vid, Slug = :slug, Title = :title, MetaTitle = :mtitle, MetaDescription = :mdesc, Excerpt = :excerpt,
                    BodyHtml = :body, WordCount = :words, AuthorName = :author, HeroImageUrl = :hero, HeroImageAlt = :hero_alt, Status = N'published',
                    PublishedAt = SYSUTCDATETIME(), PublishedBy = :u, UnpublishedAt = NULL, UnpublishedBy = NULL, UpdatedAt = SYSUTCDATETIME()
                WHERE ContentID = :cid")
                ->execute($values);
        }
        $pdo->prepare("UPDATE dbo.MktContent SET Stage = N'published', PublishedUrl = :url, PublishedAt = SYSUTCDATETIME(), PublishedBy = :u, UpdatedBy = :u2, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id")
            ->execute(['url' => $url, 'u' => marketing_user_id(), 'u2' => marketing_user_id(), 'id' => $contentId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    $decision = $existing === null ? 'blog_published' : (mkt_blog_is_live($existing) ? 'blog_updated' : 'blog_republished');
    mkt_content_log($contentId, (int) $version['VersionID'], 'publish', $decision, $url);

    return ['ok' => true, 'url' => $url, 'decision' => $decision];
}

/** Take the post off the blog. The piece returns to Approved so it can be re-published or published elsewhere. */
function mkt_blog_unpublish(int $contentId, string $note = ''): array
{
    if (!marketing_can_update()) {
        return ['ok' => false, 'error' => 'Unpublishing needs Marketing update access.'];
    }
    $post = mkt_blog_for_content($contentId);
    if (!mkt_blog_is_live($post)) {
        return ['ok' => false, 'error' => 'This piece is not live on the blog.'];
    }
    $content = mkt_content_get($contentId);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE dbo.MktBlogPost SET Status = N'unpublished', UnpublishedAt = SYSUTCDATETIME(), UnpublishedBy = :u, UpdatedAt = SYSUTCDATETIME() WHERE ContentID = :id")
            ->execute(['u' => marketing_user_id(), 'id' => $contentId]);
        $pdo->prepare("UPDATE dbo.MktContent SET PublishedUrl = NULL,
                Stage = CASE WHEN Stage IN (N'published', N'monitoring') THEN N'approved' ELSE Stage END,
                UpdatedBy = :u, UpdatedAt = SYSUTCDATETIME()
            WHERE ContentID = :id")
            ->execute(['u' => marketing_user_id(), 'id' => $contentId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $note = trim($note);
    mkt_content_log($contentId, $content !== null && $content['CurrentVersionID'] ? (int) $content['CurrentVersionID'] : null, 'publish', 'blog_unpublished', $note !== '' ? mb_substr($note, 0, 2000) : null);

    return ['ok' => true];
}
