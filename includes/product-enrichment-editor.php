<?php

require_once __DIR__ . '/assets.php';

function product_enrichment_editor_asset_version(string $relativePath): string
{
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');

    return is_readable($path) ? (string) filemtime($path) : asset_css_version();
}

function product_enrichment_editor_assets(): void
{
    $cssVersion = product_enrichment_editor_asset_version('assets/css/product-enrichment-editor.css');
    $jsVersion = product_enrichment_editor_asset_version('assets/js/product-enrichment-editor.js');
    ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" />
  <link rel="stylesheet" href="/assets/css/product-enrichment-editor.css?v=<?= htmlspecialchars($cssVersion) ?>" />
  <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
  <script src="/assets/js/product-enrichment-editor.js?v=<?= htmlspecialchars($jsVersion) ?>"></script>
    <?php
}
