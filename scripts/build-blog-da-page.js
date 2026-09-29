#!/usr/bin/env node
/**
 * Build the html-loader block for nutraaxislabs.com/our-blog (Adobe DA) from the page template and blog/blog.css,
 * so the page's in-page styles always match the portal's blog preview.
 *
 * Usage: node scripts/build-blog-da-page.js
 * Output: docs/seo-ops/our-blog-da-page.html — paste its full contents into the page's html-loader block.
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const TEMPLATE = path.join(ROOT, 'docs/seo-ops/our-blog-da-page.template.html');
const CSS = path.join(ROOT, 'blog/blog.css');
const OUTPUT = path.join(ROOT, 'docs/seo-ops/our-blog-da-page.html');
const MARKER = '/* {{BLOG_CSS}} */';

const template = fs.readFileSync(TEMPLATE, 'utf8');
if (!template.includes(MARKER)) {
  throw new Error(`Template is missing the ${MARKER} marker.`);
}
const css = fs.readFileSync(CSS, 'utf8').trim().split('\n').map((line) => (line ? `    ${line}` : line)).join('\n');

fs.writeFileSync(OUTPUT, template.replace(MARKER, `/* ===== BLOG (blog/blog.css) ===== */\n${css}`));
console.log(`Wrote ${path.relative(ROOT, OUTPUT)}`);
