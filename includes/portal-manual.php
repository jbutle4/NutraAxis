<?php

/**
 * Shared User Manual helpers (Marketing pattern).
 * Content lives in includes/manuals/*.php; pages call portal_manual_render().
 */

function portal_manual_text(string $text): string
{
    return preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', htmlspecialchars($text));
}

/**
 * @param list<string> $items
 */
function portal_manual_list_html(array $items): string
{
    if ($items === []) {
        return '';
    }

    return '<ul>' . implode('', array_map(
        static fn ($item) => '<li>' . portal_manual_text((string) $item) . '</li>',
        $items
    )) . '</ul>';
}

/**
 * Resolve a page-guide link: [key, suffix?, labelSuffix?].
 *
 * @param array{0: string, 1?: string, 2?: ?string}|null $link
 * @param array<string, array{title: string, href: string, desc?: string, placeholder?: bool, external?: bool}> $pageIndex
 * @return array{href: string, label: string}|null
 */
function portal_manual_resolve_link(?array $link, array $pageIndex): ?array
{
    if ($link === null) {
        return null;
    }
    $page = $pageIndex[$link[0]] ?? null;
    if ($page === null) {
        return null;
    }
    $suffix = (string) ($link[1] ?? '');
    $label = $page['title'] . (isset($link[2]) && $link[2] !== null && $link[2] !== '' ? ' → ' . $link[2] : '');

    return ['href' => $page['href'] . $suffix, 'label' => $label];
}

/**
 * Build page index entries from hub / app-function style module arrays.
 *
 * @param list<array<string, mixed>> $modules
 * @return array<string, array{title: string, href: string, desc: string, placeholder: bool, external: bool}>
 */
function portal_manual_modules_to_index(array $modules): array
{
    $pages = [];
    foreach ($modules as $module) {
        $slug = (string) ($module['slug'] ?? '');
        if ($slug === '') {
            continue;
        }
        $desc = (string) ($module['desc'] ?? '');
        $pages[$slug] = [
            'title'       => (string) ($module['title'] ?? $slug),
            'href'        => (string) ($module['href'] ?? '#'),
            'desc'        => $desc,
            'placeholder' => str_contains($desc, 'Placeholder') || str_contains($desc, 'coming soon'),
            'external'    => !empty($module['external']) || str_starts_with((string) ($module['href'] ?? ''), 'http'),
        ];
    }

    return $pages;
}

/**
 * Build page index from Operations / IT dashboard link arrays.
 *
 * @param list<array<string, mixed>> $links
 * @return array<string, array{title: string, href: string, desc: string, placeholder: bool, external: bool}>
 */
function portal_manual_dashboard_links_to_index(array $links): array
{
    $pages = [];
    foreach ($links as $i => $link) {
        $title = (string) ($link['title'] ?? '');
        if ($title === '') {
            continue;
        }
        $href = (string) ($link['href'] ?? '#');
        $key = (string) ($link['module'] ?? '');
        if ($key === '' && !empty($link['internal']) && preg_match('#^/([a-z0-9-]+)/#', $href, $m)) {
            $key = $m[1];
        }
        if ($key === '') {
            $key = 'link-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
            $key = trim($key, '-') ?: ('link-' . $i);
        }
        $baseKey = $key;
        $n = 2;
        while (isset($pages[$key])) {
            $key = $baseKey . '-' . $n;
            $n++;
        }
        $desc = (string) ($link['desc'] ?? '');
        $external = empty($link['internal']) && str_starts_with($href, 'http');
        $pages[$key] = [
            'title'       => $title,
            'href'        => $href,
            'desc'        => $desc,
            'placeholder' => false,
            'external'    => $external,
        ];
    }

    return $pages;
}

/**
 * Render a full User Manual page body (inside .page-inner.mkt-manual).
 *
 * Expected $manual keys:
 * - active_slug, category, title, lead, intro
 * - golden_rules (list), access_html (optional string)
 * - roles, rhythm, workflows, page_index, page_guide
 * - other_instructions, troubleshooting
 * - optional: statuses, skip_page_slugs
 *
 * @param array<string, mixed> $manual
 */
function portal_manual_render(array $manual): void
{
    $activeSlug = (string) ($manual['active_slug'] ?? '');
    $pageIndex = $manual['page_index'] ?? [];
    $pageGuide = $manual['page_guide'] ?? [];
    $workflows = $manual['workflows'] ?? [];
    $skipSlugs = $manual['skip_page_slugs'] ?? [$activeSlug];

    $linkHtml = static function (?array $link) use ($pageIndex): string {
        $resolved = portal_manual_resolve_link($link, $pageIndex);
        if ($resolved === null) {
            return '—';
        }
        $attrs = '';
        $page = $pageIndex[$link[0]] ?? null;
        if (!empty($page['external'])) {
            $attrs = ' target="_blank" rel="noopener noreferrer"';
        }

        return '<a href="' . htmlspecialchars($resolved['href']) . '"' . $attrs . '>'
            . htmlspecialchars($resolved['label']) . '</a>';
    };

    $toc = [
        'start'     => 'Start here',
        'roles'     => 'Roles and access',
        'rhythm'    => 'Operating rhythm',
        'workflows' => 'Step-by-step workflows',
        'pages'     => 'Page guide',
        'other'     => 'Other instructions',
        'help'      => 'Troubleshooting and help',
    ];
    if (!empty($manual['statuses'])) {
        $toc = array_merge(
            array_slice($toc, 0, 5, true),
            ['statuses' => 'Status glossary'],
            array_slice($toc, 5, null, true)
        );
    }

    $back = app_module_hub_back_link($activeSlug);
    // Manuals that are not hub leaves breadcrumb to home.
    if ($back['href'] === '/' || str_contains($back['label'], 'Applications')) {
        $back = ['href' => '/', 'label' => 'Back to Operations Home'];
    }
    ?>
      <?php
      render_list_page_header([
          'back_href'  => $back['href'],
          'back_label' => $back['label'],
          'category'   => (string) ($manual['category'] ?? ''),
          'title'      => (string) ($manual['title'] ?? 'User Manual'),
          'lead'       => (string) ($manual['lead'] ?? ''),
          'permission' => auth_module_permission_label($activeSlug),
      ]);
      ?>

      <nav class="mkt-manual-toc" aria-label="Contents">
        <strong>Contents</strong>
        <ol>
          <?php foreach ($toc as $anchor => $label): ?>
          <li><a href="#<?= htmlspecialchars($anchor) ?>"><?= htmlspecialchars($label) ?></a><?php if ($anchor === 'workflows' && $workflows !== []): ?>
            <ol>
              <?php foreach ($workflows as $key => $wf): ?>
              <li><a href="#wf-<?= htmlspecialchars((string) $key) ?>"><?= htmlspecialchars((string) ($wf['title'] ?? $key)) ?></a></li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?></li>
          <?php endforeach; ?>
        </ol>
        <button type="button" class="btn-secondary mkt-manual-print" onclick="window.print()">Print / save as PDF</button>
      </nav>

      <section id="start" class="mkt-manual-section">
        <h2 class="hub-section-title">Start here</h2>
        <p><?= portal_manual_text((string) ($manual['intro'] ?? '')) ?></p>
        <?php if (!empty($manual['golden_rules'])): ?>
        <h3>Golden rules</h3>
        <?= portal_manual_list_html($manual['golden_rules']) ?>
        <?php endif; ?>
        <?php if (!empty($manual['access_html'])): ?>
        <div class="status-banner">
          <div>
            <strong>Your access</strong>
            <p><?= $manual['access_html'] ?></p>
          </div>
        </div>
        <?php endif; ?>
      </section>

      <section id="roles" class="mkt-manual-section">
        <h2 class="hub-section-title">Roles and access</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>Role</th><th>Access</th><th>What they do</th></tr></thead>
            <tbody>
              <?php foreach (($manual['roles'] ?? []) as $role): ?>
              <tr>
                <td><strong><?= htmlspecialchars((string) ($role['role'] ?? '')) ?></strong></td>
                <td><?= htmlspecialchars((string) ($role['access'] ?? '')) ?></td>
                <td><?= portal_manual_text((string) ($role['does'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="rhythm" class="mkt-manual-section">
        <h2 class="hub-section-title">Operating rhythm</h2>
        <?php if (!empty($manual['rhythm_intro'])): ?>
        <p><?= portal_manual_text((string) $manual['rhythm_intro']) ?></p>
        <?php endif; ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>When</th><th>Who</th><th>What to do</th><th>Where</th></tr></thead>
            <tbody>
              <?php foreach (($manual['rhythm'] ?? []) as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($row['when'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($row['who'] ?? '')) ?></td>
                <td><?= portal_manual_text((string) ($row['what'] ?? '')) ?></td>
                <td><?= $linkHtml($row['link'] ?? null) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <section id="workflows" class="mkt-manual-section">
        <h2 class="hub-section-title">Step-by-step workflows</h2>
        <?php foreach ($workflows as $key => $wf): ?>
        <div id="wf-<?= htmlspecialchars((string) $key) ?>" class="mkt-manual-workflow">
          <h3><?= htmlspecialchars((string) ($wf['title'] ?? $key)) ?></h3>
          <p><?= portal_manual_text((string) ($wf['summary'] ?? '')) ?>
            <?php if (!empty($wf['cadence'])): ?>
            <span class="form-hint">Timing: <?= htmlspecialchars((string) $wf['cadence']) ?></span>
            <?php endif; ?>
          </p>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>#</th><th>Who</th><th>When</th><th>What to do</th><th>Page</th></tr></thead>
              <tbody>
                <?php foreach (($wf['steps'] ?? []) as $i => $step): ?>
                <tr>
                  <td><?= (int) $i + 1 ?></td>
                  <td><?= htmlspecialchars((string) ($step['who'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string) ($step['when'] ?? '')) ?></td>
                  <td><?= portal_manual_text((string) ($step['what'] ?? '')) ?></td>
                  <td><?= $linkHtml($step['link'] ?? null) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endforeach; ?>
      </section>

      <section id="pages" class="mkt-manual-section">
        <h2 class="hub-section-title">Page guide</h2>
        <p class="form-hint">Every card and shortcut in this area — including external tools opened in a new tab.</p>
        <?php foreach ($pageIndex as $slug => $page): ?>
        <?php if (in_array($slug, $skipSlugs, true)) { continue; } ?>
        <?php $guide = $pageGuide[$slug] ?? null; ?>
        <article id="page-<?= htmlspecialchars((string) $slug) ?>" class="mkt-manual-page">
          <h3>
            <a href="<?= htmlspecialchars((string) $page['href']) ?>"<?= !empty($page['external']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>>
              <?= htmlspecialchars((string) $page['title']) ?>
            </a>
            <?= !empty($page['placeholder']) ? ' <span class="status-badge status-draft">Not built yet</span>' : '' ?>
            <?= !empty($page['external']) ? ' <span class="form-hint">(external)</span>' : '' ?>
          </h3>
          <?php if ($guide === null): ?>
          <p><?= htmlspecialchars(trim(preg_replace('/\s*Placeholder.*$/i', '', (string) ($page['desc'] ?? '')))) ?></p>
          <p class="form-hint"><?= !empty($page['placeholder']) ? 'Planned, not built. Don’t record work here yet.' : 'Open the page for live data and actions.' ?></p>
          <?php else: ?>
          <dl class="detail-list detail-list-inline">
            <div><dt>Purpose</dt><dd><?= portal_manual_text((string) ($guide['purpose'] ?? '')) ?></dd></div>
            <?php if (!empty($guide['who'])): ?>
            <div><dt>Who uses it</dt><dd><?= htmlspecialchars((string) $guide['who']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($guide['screens'])): ?>
            <div><dt>What you see</dt><dd><?= portal_manual_list_html($guide['screens']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($guide['analyze'])): ?>
            <div><dt>How to use / analyze</dt><dd><?= portal_manual_list_html($guide['analyze']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($guide['actions'])): ?>
            <div><dt>Actions</dt><dd><?= portal_manual_list_html($guide['actions']) ?></dd></div>
            <?php endif; ?>
          </dl>
          <?php endif; ?>
        </article>
        <?php endforeach; ?>
      </section>

      <?php if (!empty($manual['statuses'])): ?>
      <section id="statuses" class="mkt-manual-section">
        <h2 class="hub-section-title">Status glossary</h2>
        <div class="mkt-manual-glossary">
          <?php foreach ($manual['statuses'] as $group => $statuses): ?>
          <div>
            <h3><?= htmlspecialchars((string) $group) ?></h3>
            <p><?= htmlspecialchars(is_array($statuses) ? implode(' → ', array_values($statuses)) : (string) $statuses) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <section id="other" class="mkt-manual-section">
        <h2 class="hub-section-title">Other instructions</h2>
        <dl class="detail-list detail-list-inline">
          <?php foreach (($manual['other_instructions'] ?? []) as $title => $text): ?>
          <div><dt><?= htmlspecialchars((string) $title) ?></dt><dd><?= portal_manual_text((string) $text) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </section>

      <section id="help" class="mkt-manual-section">
        <h2 class="hub-section-title">Troubleshooting and help</h2>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead><tr><th>If you see</th><th>Do this</th></tr></thead>
            <tbody>
              <?php foreach (($manual['troubleshooting'] ?? []) as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string) ($row[0] ?? '')) ?></td>
                <td><?= portal_manual_text((string) ($row[1] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p><a href="#start">Back to top</a></p>
      </section>
    <?php
}
