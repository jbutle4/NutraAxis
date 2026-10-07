/**
 * NutraAxis blog loader for nutraaxislabs.com/our-blog (Adobe DA html-loader block).
 *
 * The block provides:
 *   <div id="na-blog" class="na-blog" data-api="https://nutraaxisweb.azurewebsites.net/api/public/blog.php">
 *     <article id="na-blog-post" class="na-blog-post"></article>
 *     <aside class="na-blog-history"><h2 class="na-blog-history-title">Posting history</h2><ol id="na-blog-list" class="na-blog-list"></ol></aside>
 *   </div>
 *   <script src="https://nutraaxisweb.azurewebsites.net/blog/blog-dynamic.js"></script>
 *
 * Shows ?post=<slug> (or the newest post) in the content pane and the posting history newest to oldest.
 * Styles come from the page's <style> block (blog/blog.css).
 */
(function nutraAxisBlog(global) {
  'use strict';

  var DEFAULT_API_URL = 'https://nutraaxisweb.azurewebsites.net/api/public/blog.php';
  var PAGE_SIZE = 8;

  var root = document.getElementById('na-blog');
  var pane = document.getElementById('na-blog-post');
  var list = document.getElementById('na-blog-list');
  if (!root || !pane || !list) {
    return;
  }

  var apiUrl = root.getAttribute('data-api') || DEFAULT_API_URL;
  var baseTitle = document.title;
  var metaDescription = document.querySelector('meta[name="description"]');
  var baseDescription = metaDescription ? metaDescription.getAttribute('content') : '';
  var items = [];
  var shown = PAGE_SIZE;
  var currentSlug = '';
  var requestId = 0;

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function slugFromLocation() {
    try {
      return (new URLSearchParams(global.location.search).get('post') || '').trim().toLowerCase();
    } catch (e) {
      return '';
    }
  }

  function postHref(slug) {
    var url = new URL(global.location.href);
    url.searchParams.set('post', slug);
    url.hash = '';
    return url.pathname + url.search;
  }

  function status(message) {
    pane.innerHTML = '<p class="na-blog-status">' + escapeHtml(message) + '</p>';
  }

  function renderPost(post, notFound) {
    if (!post) {
      status('New posts are on the way. Check back soon.');
      return;
    }
    var meta = '<span><time datetime="' + escapeHtml(post.published_at) + '">' + escapeHtml(post.published_display) + '</time></span>';
    if (post.updated_display) {
      meta += '<span>Updated <time datetime="' + escapeHtml(post.updated_at) + '">' + escapeHtml(post.updated_display) + '</time></span>';
    }
    if (post.author) {
      meta += '<span>By ' + escapeHtml(post.author) + '</span>';
    }
    meta += '<span>' + escapeHtml(post.reading_minutes) + ' min read</span>';

    pane.innerHTML = ''
      + (notFound ? '<p class="na-blog-notice">That post is no longer available. Here is our latest post instead.</p>' : '')
      + (post.hero_image_url ? '<img class="na-blog-hero-img" src="' + escapeHtml(post.hero_image_url) + '" alt="' + escapeHtml(post.hero_image_alt) + '" loading="lazy" />' : '')
      + '<h2 class="na-blog-post-title">' + escapeHtml(post.title) + '</h2>'
      + '<div class="na-blog-meta">' + meta + '</div>'
      // body_html is rendered server-side from approved Markdown with all text escaped.
      + '<div class="na-blog-article">' + (post.body_html || '') + '</div>';
  }

  function renderList() {
    if (items.length === 0) {
      list.innerHTML = '<li class="na-blog-list-date">No posts yet.</li>';
      return;
    }
    list.innerHTML = items.slice(0, shown).map(function (item) {
      return ''
        + '<li><a href="' + escapeHtml(postHref(item.slug)) + '" data-slug="' + escapeHtml(item.slug) + '"'
        + (item.slug === currentSlug ? ' aria-current="true"' : '') + '>'
        + '<span class="na-blog-list-title">' + escapeHtml(item.title) + '</span>'
        + '<span class="na-blog-list-date">' + escapeHtml(item.published_display) + '</span>'
        + (item.excerpt ? '<span class="na-blog-list-excerpt">' + escapeHtml(item.excerpt) + '</span>' : '')
        + '</a></li>';
    }).join('');

    var more = root.querySelector('.na-blog-more');
    var remaining = items.length - shown;
    if (remaining > 0) {
      if (!more) {
        more = document.createElement('button');
        more.type = 'button';
        more.className = 'na-blog-more';
        more.addEventListener('click', function () {
          shown += PAGE_SIZE;
          renderList();
        });
        list.parentNode.appendChild(more);
      }
      more.textContent = 'Show older posts (' + remaining + ')';
    } else if (more) {
      more.remove();
    }
  }

  function applyHead(post, fromUrl) {
    if (!fromUrl || !post) {
      document.title = baseTitle;
      if (metaDescription) metaDescription.setAttribute('content', baseDescription);
      return;
    }
    document.title = (post.meta_title || post.title) + ' | NutraAxis Blog';
    if (metaDescription) metaDescription.setAttribute('content', post.meta_description || post.excerpt || baseDescription);
  }

  function load(slug, options) {
    var opts = options || {};
    var id = ++requestId;
    if (opts.scroll || !items.length) status('Loading…');

    return fetch(apiUrl + (slug ? '?post=' + encodeURIComponent(slug) : ''), { headers: { Accept: 'application/json' } })
      .then(function (response) {
        return response.json().then(function (data) {
          if (!response.ok || !data || data.ok !== true) {
            throw new Error((data && data.error) || ('HTTP ' + response.status));
          }
          return data;
        });
      })
      .then(function (data) {
        if (id !== requestId) return;
        items = Array.isArray(data.items) ? data.items : [];
        currentSlug = data.post ? data.post.slug : '';
        var matched = Boolean(slug) && !data.not_found;
        while (shown < items.length && items.findIndex(function (i) { return i.slug === currentSlug; }) >= shown) {
          shown += PAGE_SIZE;
        }
        renderPost(data.post, data.not_found);
        renderList();
        applyHead(data.post, matched);
        if (opts.push && matched) {
          global.history.pushState({ naBlog: currentSlug }, '', postHref(currentSlug));
        }
        if (opts.scroll) {
          pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      })
      .catch(function (error) {
        if (id !== requestId) return;
        status('The blog could not load right now. Please refresh the page or try again later.');
        if (global.console) global.console.warn('NutraAxis blog:', error);
      });
  }

  list.addEventListener('click', function (event) {
    var link = event.target.closest('a[data-slug]');
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) {
      return;
    }
    event.preventDefault();
    if (link.getAttribute('data-slug') !== currentSlug) {
      load(link.getAttribute('data-slug'), { push: true, scroll: true });
    } else {
      pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  global.addEventListener('popstate', function () {
    load(slugFromLocation(), { scroll: true });
  });

  global.NutraAxisBlog = { reload: function () { return load(currentSlug); } };

  load(slugFromLocation());
})(window);
