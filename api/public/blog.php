<?php

declare(strict_types=1);

/**
 * Public blog feed for nutraaxislabs.com/our-blog (loaded by blog/blog-dynamic.js).
 *
 * GET               → { items: newest-first history (no bodies), post: newest post with body }
 * GET ?post=<slug>  → same, with post = that post; unknown or unpublished slugs fall back to the newest post with not_found = true
 * GET ?limit=<n>    → history length, 1–200 (default 100)
 */

require_once dirname(__DIR__, 2) . '/includes/coa-public-api.php';

coa_public_handle_preflight();

require_once dirname(__DIR__, 2) . '/includes/blog-public.php';

try {
    $items = blog_list_live((int) ($_GET['limit'] ?? 100));
    $slug = strtolower(trim((string) ($_GET['post'] ?? '')));
    $post = $slug !== '' ? blog_get_live($slug) : null;
    $notFound = $slug !== '' && $post === null;
    if ($post === null && $items !== []) {
        $post = blog_get_live((string) $items[0]['Slug']);
    }
} catch (Throwable $e) {
    error_log('blog.php: ' . $e->getMessage());
    coa_public_json_response(['ok' => false, 'error' => 'The blog is temporarily unavailable.'], 503);
}

coa_public_json_response([
    'ok'           => true,
    'generated_at' => gmdate('c'),
    'page_url'     => blog_page_url(),
    'not_found'    => $notFound,
    'items'        => array_map('blog_to_api_item', $items),
    'post'         => $post !== null ? blog_to_api_item($post) : null,
]);
