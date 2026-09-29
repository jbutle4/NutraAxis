# NutraAxis blog (/our-blog)

Approved Content Pipeline pieces are published to the blog from the portal. The blog page in Adobe DA loads them from the operations web app, the same way the COA page loads certificates.

```
Content Pipeline piece (approved) ──Publish to blog──▶ dbo.MktBlogPost
                                                          │
nutraaxislabs.com/our-blog ◀── blog/blog-dynamic.js ◀── api/public/blog.php (public, read-only, 60 s cache)
```

| Piece | Where |
|---|---|
| Table | `dbo.MktBlogPost` — `sql/164_create_marketing_blog.sql` |
| Publish / update / unpublish | Content Pipeline piece → Publish (`includes/marketing-blog.php`) |
| Public feed | `https://nutraaxisweb.azurewebsites.net/api/public/blog.php` (`?post=<address>`) |
| Page script | `https://nutraaxisweb.azurewebsites.net/blog/blog-dynamic.js` |
| Styles | `blog/blog.css` (portal preview) — copied into the DA page by the build script |
| DA page block | `docs/seo-ops/our-blog-da-page.html` |

## Set up the page in Adobe DA (one time)

1. Create the page **/our-blog**.
2. Add a **metadata** block:

   | metadata | |
   |---|---|
   | Title | Our Blog \| NutraAxis |
   | Description | NutraAxis is a practitioner-focused supplement brand dedicated to developing high-quality nutritional formulations grounded in scientific research and human physiology. |

3. Add an **html-loader** block and paste the entire contents of `docs/seo-ops/our-blog-da-page.html`.
4. Preview, then publish. With no posts yet, the page says "New posts are on the way."
5. Add **Blog** to the site navigation and footer where wanted.

If the page's address is ever not `/our-blog`, change `blog.page_url` in Marketing Admin → Settings so the live links recorded on each piece match.

## Changing the page design

- Page text, hero and layout: edit `docs/seo-ops/our-blog-da-page.template.html`.
- Post and history styles: edit `blog/blog.css` (this also changes the portal's blog preview).
- Then run `node scripts/build-blog-da-page.js` and paste the new `our-blog-da-page.html` into the html-loader block.

The script and feed deploy with the portal; changes to `blog/blog-dynamic.js` reach the live page without touching DA.

## How publishing behaves

- Only **approved** pieces can be published, and only by someone with full Marketing access. The blog stores the rendered HTML of the approved version, so later edits never reach the site until they are approved and **Update blog post** is pressed.
- Headings in the body are stepped down one level (the post title is the H2 under the page's H1).
- **Unpublish from blog** hides the post immediately (within the 60-second cache) and returns the piece to Approved.
- Each post's live URL is `blog.page_url` + `?post=<address>`. Changing an address later breaks links already shared.
- Blog posts are skipped by the Page Inventory's per-piece live check (every post shares the `/our-blog` address and is drawn by script); the `/our-blog` page itself is crawled like any other page.
