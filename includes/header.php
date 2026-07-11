  <div class="site-top-chrome">
  <header class="site-header">
    <div class="container<?= !empty($pageContainerClass) ? ' ' . htmlspecialchars((string) $pageContainerClass) : '' ?>">
      <div class="header-left">
        <a class="logo-link" href="/" aria-label="NutraAxis Operations home">
          <img src="/assets/logos/nutraaxis-logo.svg" alt="NutraAxis" width="200" height="36" />
        </a>
        <span class="header-dashboard-title">Operations Portal</span>
      </div>

      <?php if (auth_is_logged_in()): ?>
      <div class="portal-search" data-portal-search>
        <label class="visually-hidden" for="portal-search-input">Search portal links</label>
        <input
          class="portal-search-input"
          type="search"
          id="portal-search-input"
          placeholder="Search portal links..."
          autocomplete="off"
          aria-autocomplete="list"
          aria-expanded="false"
          aria-controls="portal-search-results"
        />
        <div class="portal-search-results" id="portal-search-results" role="listbox" hidden></div>
      </div>
      <?php endif; ?>

      <div class="header-right">
        <?php if (auth_is_logged_in()): ?>
        <a class="header-user" href="/my-account/"><?= htmlspecialchars(auth_user()['UserName']) ?></a>
        <?php else: ?>
        <a class="header-login-link" href="<?= htmlspecialchars(auth_login_url()) ?>">Log In</a>
        <?php endif; ?>

        <button
        type="button"
        class="nav-toggle"
        id="nav-toggle"
        aria-expanded="false"
        aria-controls="nav-panel"
        aria-label="Open navigation menu"
      >
        <svg class="icon-menu" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg class="icon-close" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
      </div>
    </div>
  </header>
<?php if (function_exists('data_profile_is_uat') && data_profile_is_uat()): ?>
  <div class="uat-environment-banner-wrap">
    <div class="container">
      <?php require __DIR__ . '/uat-banner.php'; ?>
    </div>
  </div>
<?php endif; ?>
  </div>
  <div class="site-top-chrome-spacer" aria-hidden="true"></div>

  <div class="nav-overlay" id="nav-overlay" hidden></div>

  <nav class="nav-panel" id="nav-panel" aria-label="Main navigation" hidden>
    <div class="nav-panel-header">
      <span class="nav-panel-title">Menu</span>
      <button type="button" class="nav-toggle" id="nav-close" aria-label="Close navigation menu">
        <svg class="icon-close" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M6 6l12 12M18 6L6 18"/>
        </svg>
      </button>
    </div>

    <div class="nav-section-label">Applications</div>
    <ul class="nav-list">
      <?php
        require_once __DIR__ . '/nav.php';
        foreach (auth_filter_modules(app_functions()) as $item):
            $navHref = auth_is_logged_in()
                ? $item['href']
                : auth_login_url($item['href']);
            $parentSlug = (string) ($item['slug'] ?? '');
            $parentActive = nav_parent_is_active($parentSlug, $activeSlug ?? null);
            $navChildren = nav_children_for_parent($parentSlug);
            $hasChildren = $navChildren !== [];
            $groupId = 'nav-group-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower($parentSlug));
            $isExpanded = $hasChildren && nav_group_is_expanded($parentSlug, $activeSlug ?? null);
      ?>
      <li class="nav-group<?= $hasChildren ? ' has-children' : '' ?><?= $isExpanded ? ' is-expanded' : '' ?><?= $parentActive ? ' is-active' : '' ?>">
        <?php if ($hasChildren): ?>
        <div class="nav-parent-row">
          <a href="<?= htmlspecialchars($navHref) ?>" class="nav-parent-link<?= $parentActive ? ' is-active' : '' ?>">
            <span class="nav-icon"><?= icon_svg($item['icon'], 16) ?></span>
            <?= htmlspecialchars($item['title']) ?>
          </a>
          <button
            type="button"
            class="nav-parent-toggle"
            aria-expanded="<?= $isExpanded ? 'true' : 'false' ?>"
            aria-controls="<?= htmlspecialchars($groupId) ?>"
            aria-label="<?= $isExpanded ? 'Collapse' : 'Expand' ?> <?= htmlspecialchars($item['title']) ?>"
          >
            <svg class="nav-parent-chevron" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
              <path d="M6 9l6 6 6-6"/>
            </svg>
          </button>
        </div>
        <ul class="nav-sublist" id="<?= htmlspecialchars($groupId) ?>">
          <?php nav_render_submenu_items($navChildren, $parentSlug, $activeSlug ?? null); ?>
        </ul>
        <?php else: ?>
        <a href="<?= htmlspecialchars($navHref) ?>" class="<?= $parentActive ? 'is-active' : '' ?>">
          <span class="nav-icon"><?= icon_svg($item['icon'], 16) ?></span>
          <?= htmlspecialchars($item['title']) ?>
        </a>
        <?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="nav-divider"></div>
    <div class="nav-section-label">Account</div>
    <ul class="nav-list">
      <?php if (!auth_is_logged_in()): ?>
      <li>
        <a href="<?= htmlspecialchars(auth_login_url()) ?>">
          <span class="nav-icon">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
          </span>
          Log In
        </a>
      </li>
      <?php endif; ?>
      <?php foreach (auth_filter_account_links(account_links()) as $accountLink): ?>
      <?php
        $accountHref = auth_is_logged_in()
            ? $accountLink['href']
            : auth_login_url($accountLink['href']);
      ?>
      <li>
        <a href="<?= htmlspecialchars($accountHref) ?>" class="<?= htmlspecialchars($accountLink['class'] ?? '') ?>">
          <span class="nav-icon">
            <?php if (($accountLink['class'] ?? '') === 'nav-logout'): ?>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
            <?php elseif ($accountLink['title'] === 'Site Admin'): ?>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            <?php else: ?>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?php endif; ?>
          </span>
          <?= htmlspecialchars($accountLink['title']) ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>
  </nav>
