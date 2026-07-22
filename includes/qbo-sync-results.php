<?php

/**
 * @param array{summary: array<string, int>, rows: list<array<string, mixed>>} $result
 */
function qbo_sync_render_results(array $result, string $title): void
{
    $summary = $result['summary'] ?? [];
    $rows = $result['rows'] ?? [];
    ?>
    <section class="detail-card">
      <h2><?= htmlspecialchars($title) ?></h2>
      <?php if ($summary !== []): ?>
      <p class="page-lead">
        <?php foreach ($summary as $key => $count): ?>
          <?php if ((int) $count > 0): ?>
            <span class="status-badge status-draft"><?= htmlspecialchars(str_replace('_', ' ', $key)) ?>: <?= (int) $count ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </p>
      <?php endif; ?>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Action</th>
              <th>Name</th>
              <th>Detail</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
            <tr><td colspan="3">No changes.</td></tr>
            <?php else: ?>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= htmlspecialchars((string) ($row['action'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['name'] ?? '')) ?></td>
              <td><?= htmlspecialchars((string) ($row['detail'] ?? '')) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php
}
