#!/usr/bin/env node
/**
 * Import PDP enrichment HTML (+ optional PDFs) from nutraaxislabs.com into ProductEnrichment.
 *
 * Usage:
 *   node scripts/import-pdp-enrichment-from-site.js
 *   node scripts/import-pdp-enrichment-from-site.js --sku=na-gw-002
 *   node scripts/import-pdp-enrichment-from-site.js --dry-run
 *   node scripts/import-pdp-enrichment-from-site.js --no-pdf
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const sql = require('mssql');
const { BlobServiceClient } = require('@azure/storage-blob');

const DEFAULT_SKUS = [
  'na-gt-008',
  'na-gw-002',
  'na-gw-007',
  'na-gw-011',
  'na-gw-014',
  'na-hr-005',
  'na-hr-006',
  'na-hr-009',
  'na-if-015',
  'na-if-016',
  'na-lv-010',
  'na-lv-012',
  'na-mt-001',
  'na-mt-003',
  'na-mt-004',
  'na-ss-013',
];

const SITE_ORIGIN = 'https://www.nutraaxislabs.com';

function loadEnv(filePath) {
  const vars = {};
  for (const line of fs.readFileSync(filePath, 'utf8').split('\n')) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const idx = trimmed.indexOf('=');
    if (idx === -1) continue;
    let value = trimmed.slice(idx + 1).trim();
    if ((value.startsWith('"') && value.endsWith('"')) || (value.startsWith("'") && value.endsWith("'"))) {
      value = value.slice(1, -1);
    }
    vars[trimmed.slice(0, idx).trim()] = value;
  }
  return vars;
}

function envFirst(env, keys) {
  for (const key of keys) {
    const value = env[key];
    if (value !== undefined && value !== '') return value;
  }
  return undefined;
}

function parseArgs(argv) {
  const options = {
    dryRun: false,
    noPdf: false,
    publish: false,
    skus: [],
  };

  for (const arg of argv) {
    if (arg === '--dry-run') options.dryRun = true;
    else if (arg === '--no-pdf') options.noPdf = true;
    else if (arg === '--publish') options.publish = true;
    else if (arg.startsWith('--sku=')) options.skus.push(arg.slice('--sku='.length).toLowerCase());
  }

  return options;
}

function fetchBuffer(url) {
  return new Promise((resolve, reject) => {
    https.get(url, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        resolve({
          status: res.statusCode || 0,
          headers: res.headers,
          body: Buffer.concat(chunks),
        });
      });
    }).on('error', reject);
  });
}

function decodeHtmlEntities(str) {
  return String(str)
    .replace(/&#x3C;/g, '<')
    .replace(/&#x3E;/g, '>')
    .replace(/&#x26;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&')
    .replace(/&#39;/g, "'")
    .replace(/&quot;/g, '"');
}

function extractEnrichmentHtml(pageHtml) {
  const match = String(pageHtml).match(/<pre><code>([\s\S]*?)<\/code><\/pre>/i);
  if (!match) return null;
  const decoded = decodeHtmlEntities(match[1]).trim();
  return decoded.startsWith('<') ? decoded : null;
}

function buildFileName(productName) {
  const product = String(productName).replace(/[^A-Za-z0-9]+/g, '');
  return product ? `${product}InfoSheet.pdf` : 'InfoSheet.pdf';
}

function transformEnrichmentHtml(html) {
  let pdfPath = null;
  let pdfLinkText = null;

  const transformed = String(html).replace(
    /<a\s+[^>]*href=['"]([^'"]+)['"][^>]*>([\s\S]*?)<\/a>/gi,
    (full, href, text) => {
      if (!/\/pdf\//i.test(href) && !/\.pdf(?:\?|#|$)/i.test(href)) {
        return full;
      }

      pdfPath = href.startsWith('http') ? href : href;
      pdfLinkText = String(text).replace(/<[^>]+>/g, '').trim();
      return `<a href="{{PDF_URL}}" target="_blank" rel="noopener noreferrer">${String(text).trim()}</a>`;
    }
  );

  return {
    html: transformed,
    pdfPath,
    pdfLinkText,
  };
}

function sanitizeFilename(fileName) {
  const base = path.basename(String(fileName).replace(/\\/g, '/'));
  const safe = base.replace(/[^\w.\- ()]+/gu, '_').replace(/^[._]+|[._]+$/g, '');
  return safe || 'InfoSheet.pdf';
}

function buildBlobPath(domain, entityId, attachmentId, fileName) {
  const safeDomain = String(domain)
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9-]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'files';
  const safeName = sanitizeFilename(fileName);
  return `${safeDomain}/${entityId}/${attachmentId}-${safeName}`;
}

function createBlobConfig(env) {
  const connectionString = envFirst(env, ['AZURE_STORAGE_CONNECTION_STRING']);
  const container = env.AZURE_STORAGE_CONTAINER || 'portal-attachments';
  if (!connectionString || !container) return null;

  return {
    client: BlobServiceClient.fromConnectionString(connectionString),
    container,
  };
}

async function uploadBlob(blobConfig, blobPath, content, contentType) {
  const normalizedPath = String(blobPath).replace(/\\/g, '/').replace(/^\/+/, '');
  const containerClient = blobConfig.client.getContainerClient(blobConfig.container);
  const blockBlob = containerClient.getBlockBlobClient(normalizedPath);
  await blockBlob.uploadData(content, {
    blobHTTPHeaders: { blobContentType: contentType || 'application/pdf' },
  });
  return normalizedPath;
}

async function fetchEnrichmentForSku(sku) {
  const url = `${SITE_ORIGIN}/enrichment/pdp/${sku}.plain.html`;
  const response = await fetchBuffer(url);
  if (response.status !== 200) {
    return { ok: false, error: `HTTP ${response.status} for ${url}` };
  }

  const rawHtml = extractEnrichmentHtml(response.body.toString('utf8'));
  if (!rawHtml) {
    return { ok: false, error: `No html-loader content found for ${sku}` };
  }

  const transformed = transformEnrichmentHtml(rawHtml);
  if (!transformed.pdfPath) {
    return { ok: false, error: `No /pdf/ link found in enrichment HTML for ${sku}` };
  }

  const pdfUrl = transformed.pdfPath.startsWith('http')
    ? transformed.pdfPath
    : `${SITE_ORIGIN}${transformed.pdfPath.startsWith('/') ? '' : '/'}${transformed.pdfPath}`;

  return {
    ok: true,
    sku,
    html: transformed.html,
    pdfLinkText: transformed.pdfLinkText,
    pdfUrl,
  };
}

async function main() {
  const root = path.join(__dirname, '..');
  const options = parseArgs(process.argv.slice(2));
  const env = {
    ...loadEnv(path.join(root, '.env')),
    ...Object.fromEntries(
      ['DB_HOST', 'DB_SERVER', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_PORT', 'AZURE_STORAGE_CONNECTION_STRING', 'AZURE_STORAGE_CONTAINER']
        .filter((key) => process.env[key])
        .map((key) => [key, process.env[key]])
    ),
  };

  const pool = await sql.connect({
    server: env.DB_HOST || env.DB_SERVER,
    database: env.DB_NAME,
    user: env.DB_USER,
    password: env.DB_PASS || env.DB_PASSWORD,
    port: Number(env.DB_PORT || 1433),
    options: { encrypt: true, trustServerCertificate: false },
  });

  let skus = options.skus.length > 0 ? options.skus : DEFAULT_SKUS;
  if (options.skus.length === 0) {
    const master = await pool.request().query(`
      SELECT LOWER(SKUCode) AS sku
      FROM dbo.SKUMaster
      WHERE LOWER(SKUCode) LIKE 'na-%'
      ORDER BY SKUCode
    `);
    const masterSkus = master.recordset.map((row) => row.sku);
    skus = DEFAULT_SKUS.filter((sku) => masterSkus.includes(sku));
  }

  const skuMaster = await pool.request().query(`
    SELECT LOWER(SKUCode) AS sku, ProductName
    FROM dbo.SKUMaster
  `);
  const productNames = new Map(
    skuMaster.recordset.map((row) => [row.sku, row.ProductName])
  );

  const blobConfig = options.noPdf ? null : createBlobConfig(env);
  if (!options.noPdf && !blobConfig) {
    console.warn('Azure Blob Storage not configured; importing HTML only.');
  }

  for (const sku of skus) {
    console.log(`Importing ${sku}...`);
    const fetched = await fetchEnrichmentForSku(sku);
    if (!fetched.ok) {
      console.warn(`  skipped: ${fetched.error}`);
      continue;
    }

    const productName = productNames.get(sku) || fetched.pdfLinkText?.replace(/\s+Information Sheet$/i, '') || sku;
    const fileName = buildFileName(productName);
    const publish = options.publish ? 1 : 0;

    if (options.dryRun) {
      console.log(`  product: ${productName}`);
      console.log(`  link text: ${fetched.pdfLinkText}`);
      console.log(`  pdf: ${fetched.pdfUrl}`);
      console.log(`  file name: ${fileName}`);
      console.log(`  html length: ${fetched.html.length}`);
      continue;
    }

    const existing = await pool.request()
      .input('sku', sql.NVarChar(100), sku)
      .query('SELECT ProductEnrichmentID, BlobPath FROM dbo.ProductEnrichment WHERE SKUCode = @sku');

    let recordId = Number(existing.recordset[0]?.ProductEnrichmentID || 0);
    let blobPath = existing.recordset[0]?.BlobPath || null;
    let fileSize = null;
    let contentType = 'application/pdf';

    if (!recordId) {
      const insert = await pool.request()
        .input('sku_code', sql.NVarChar(100), sku)
        .input('product_name', sql.NVarChar(200), productName)
        .input('enrichment_html', sql.NVarChar(sql.MAX), fetched.html)
        .input('pdf_link_text', sql.NVarChar(200), fetched.pdfLinkText)
        .input('publish', sql.Bit, 0)
        .query(`
          INSERT INTO dbo.ProductEnrichment (
            SKUCode, ProductName, EnrichmentHtml, PdfLinkText, Publish
          )
          OUTPUT INSERTED.ProductEnrichmentID
          VALUES (@sku_code, @product_name, @enrichment_html, @pdf_link_text, @publish)
        `);
      recordId = Number(insert.recordset[0].ProductEnrichmentID);
    }

    if (!options.noPdf && blobConfig) {
      const pdfResponse = await fetchBuffer(fetched.pdfUrl);
      if (pdfResponse.status !== 200) {
        console.warn(`  PDF download failed (${pdfResponse.status}) for ${fetched.pdfUrl}`);
      } else {
        fileSize = pdfResponse.body.length;
        contentType = pdfResponse.headers['content-type'] || 'application/pdf';
        blobPath = buildBlobPath('product-enrichment', recordId, recordId, fileName);
        await uploadBlob(blobConfig, blobPath, pdfResponse.body, contentType);
      }
    }

    await pool.request()
      .input('id', sql.Int, recordId)
      .input('product_name', sql.NVarChar(200), productName)
      .input('enrichment_html', sql.NVarChar(sql.MAX), fetched.html)
      .input('pdf_link_text', sql.NVarChar(200), fetched.pdfLinkText)
      .input('file_name', sql.NVarChar(255), blobPath ? fileName : null)
      .input('content_type', sql.NVarChar(100), blobPath ? contentType : null)
      .input('file_size', sql.Int, fileSize)
      .input('blob_path', sql.NVarChar(512), blobPath)
      .input('publish', sql.Bit, blobPath ? publish : 0)
      .query(`
        UPDATE dbo.ProductEnrichment
        SET
          ProductName = @product_name,
          EnrichmentHtml = @enrichment_html,
          PdfLinkText = @pdf_link_text,
          FileName = COALESCE(@file_name, FileName),
          ContentType = COALESCE(@content_type, ContentType),
          FileSizeBytes = COALESCE(@file_size, FileSizeBytes),
          BlobPath = COALESCE(@blob_path, BlobPath),
          Publish = CASE WHEN @blob_path IS NOT NULL THEN @publish ELSE Publish END,
          ModifiedDate = sysutcdatetime()
        WHERE ProductEnrichmentID = @id
      `);

    console.log(`  saved #${recordId}${blobPath ? ' + PDF' : ' (HTML only)'}`);
  }

  await pool.close();
  console.log('Done.');
}

main().catch((err) => {
  console.error(err.message || err);
  process.exit(1);
});
