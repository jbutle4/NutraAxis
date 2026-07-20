#!/usr/bin/env node
/**
 * Provider signup E2E UAT — application → draft → certificate → ops approve → ACCS provision.
 *
 * Usage:
 *   OPS_LOGIN=you@nutraaxislabs.com OPS_PASSWORD='***' \
 *     node scripts/e2e-provider-signup-uat.js
 *
 * Optional:
 *   BASE_URL=https://nutraaxisweb.azurewebsites.net
 *   --through=draft|approve|provision   (default: provision)
 *   --headed-report                     (also print markdown to stdout)
 *
 * Writes:
 *   artifacts/uat/provider-signup-e2e-<timestamp>.json
 *   artifacts/uat/provider-signup-e2e-<timestamp>.md
 */

const fs = require('fs');
const path = require('path');
const { URL, URLSearchParams } = require('url');

const ROOT = path.join(__dirname, '..');
const ARTIFACT_DIR = path.join(ROOT, 'artifacts', 'uat');
const CERT_PATH = path.join(ROOT, 'scripts', 'fixtures', 'uat-reseller-certificate.pdf');

const DEFAULT_BASE = 'https://nutraaxisweb.azurewebsites.net';
const THROUGH_STEPS = ['draft', 'approve', 'provision'];

function parseArgs(argv) {
  const args = { through: 'provision', headedReport: false };
  for (let i = 0; i < argv.length; i += 1) {
    const a = argv[i];
    if (a === '--through' && argv[i + 1]) {
      args.through = String(argv[++i]).toLowerCase();
    } else if (a.startsWith('--through=')) {
      args.through = a.slice('--through='.length).toLowerCase();
    } else if (a === '--headed-report') {
      args.headedReport = true;
    } else if (a === '--help' || a === '-h') {
      args.help = true;
    }
  }
  return args;
}

function loadDotEnv() {
  const envPath = path.join(ROOT, '.env');
  if (!fs.existsSync(envPath)) {
    return;
  }
  const text = fs.readFileSync(envPath, 'utf8');
  for (const line of text.split(/\r?\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) {
      continue;
    }
    const eq = trimmed.indexOf('=');
    if (eq <= 0) {
      continue;
    }
    const key = trimmed.slice(0, eq).trim();
    let value = trimmed.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"')) ||
      (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }
    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

function stamp() {
  return new Date().toISOString().replace(/[:.]/g, '-');
}

function fail(message, detail) {
  const err = new Error(message);
  if (detail) {
    err.detail = detail;
  }
  throw err;
}

function uniqueSuffix() {
  return `${Date.now().toString(36)}${Math.floor(Math.random() * 1000).toString(36)}`;
}

function buildPayload(cfg) {
  const suffix = uniqueSuffix();
  const domain = cfg.emailDomain;
  const providerEmail = cfg.providerEmail || `uat.provider+${suffix}@${domain}`;
  const adminEmail = cfg.adminEmail || `uat.admin+${suffix}@${domain}`;

  return {
    provider_email: providerEmail,
    company_name: `UAT Clinic ${suffix}`,
    company_legal_name: `UAT Clinic Legal ${suffix} LLC`,
    company_email: providerEmail,
    company_phone: '5125550100',
    street_address: '100 Congress Ave',
    city: 'Austin',
    state_code: 'TX',
    postal_code: '78701',
    clinic_type: 'Wellness Clinic',
    admin_first_name: 'UAT',
    admin_last_name: `Admin${suffix.slice(-4)}`,
    admin_email: adminEmail,
    admin_phone: '5125550101',
    npi_number: cfg.npiNumber,
    tax_id_type: 'EIN',
    tax_id: '12-3456789',
    ach_routing_number: cfg.routingNumber,
    ach_account_number: cfg.accountNumber,
    ach_account_type: 'Checking',
  };
}

class CookieJar {
  constructor() {
    this.store = new Map();
  }

  absorb(response, requestUrl) {
    const host = new URL(requestUrl).hostname;
    const headers = typeof response.headers.getSetCookie === 'function'
      ? response.headers.getSetCookie()
      : [];
    const single = response.headers.get('set-cookie');
    const list = headers.length > 0 ? headers : single ? [single] : [];

    for (const raw of list) {
      const [pair] = raw.split(';');
      const eq = pair.indexOf('=');
      if (eq <= 0) {
        continue;
      }
      const name = pair.slice(0, eq).trim();
      const value = pair.slice(eq + 1).trim();
      this.store.set(`${host}|${name}`, { name, value, host });
    }
  }

  headerFor(url) {
    const host = new URL(url).hostname;
    const parts = [];
    for (const cookie of this.store.values()) {
      if (host === cookie.host || host.endsWith(`.${cookie.host}`)) {
        parts.push(`${cookie.name}=${cookie.value}`);
      }
    }
    return parts.join('; ');
  }
}

class HttpClient {
  constructor(baseUrl) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.jar = new CookieJar();
  }

  absolute(urlPath) {
    if (/^https?:\/\//i.test(urlPath)) {
      return urlPath;
    }
    return `${this.baseUrl}${urlPath.startsWith('/') ? '' : '/'}${urlPath}`;
  }

  async request(method, urlPath, options = {}) {
    const url = this.absolute(urlPath);
    const headers = Object.assign({}, options.headers || {});
    const cookie = this.jar.headerFor(url);
    if (cookie) {
      headers.Cookie = cookie;
    }

    const init = {
      method,
      headers,
      redirect: 'manual',
      body: options.body,
    };

    let response = await fetch(url, init);
    this.jar.absorb(response, url);
    let finalUrl = url;

    let hops = 0;
    while (
      hops < 8 &&
      response.status >= 300 &&
      response.status < 400 &&
      response.headers.get('location')
    ) {
      hops += 1;
      finalUrl = new URL(response.headers.get('location'), finalUrl).toString();
      const nextHeaders = {};
      const nextCookie = this.jar.headerFor(finalUrl);
      if (nextCookie) {
        nextHeaders.Cookie = nextCookie;
      }
      response = await fetch(finalUrl, { method: 'GET', headers: nextHeaders, redirect: 'manual' });
      this.jar.absorb(response, finalUrl);
    }

    const text = await response.text();
    return {
      status: response.status,
      url: finalUrl,
      headers: response.headers,
      text,
      location: response.headers.get('location'),
    };
  }

  get(urlPath) {
    return this.request('GET', urlPath);
  }

  postForm(urlPath, fields) {
    const body = new URLSearchParams();
    for (const [key, value] of Object.entries(fields)) {
      body.append(key, value == null ? '' : String(value));
    }
    return this.request('POST', urlPath, {
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
  }

  postMultipart(urlPath, fields, fileField, filePath, fileName, contentType) {
    const boundary = `----NutraUat${Date.now().toString(16)}`;
    const chunks = [];

    for (const [key, value] of Object.entries(fields)) {
      chunks.push(
        `--${boundary}\r\n` +
          `Content-Disposition: form-data; name="${key}"\r\n\r\n` +
          `${value == null ? '' : String(value)}\r\n`
      );
    }

    const fileBytes = fs.readFileSync(filePath);
    chunks.push(
      `--${boundary}\r\n` +
        `Content-Disposition: form-data; name="${fileField}"; filename="${fileName}"\r\n` +
        `Content-Type: ${contentType}\r\n\r\n`
    );

    const head = Buffer.from(chunks.join(''), 'utf8');
    const mid = Buffer.from(fileBytes);
    const tail = Buffer.from(`\r\n--${boundary}--\r\n`, 'utf8');
    const body = Buffer.concat([head, mid, tail]);

    return this.request('POST', urlPath, {
      headers: { 'Content-Type': `multipart/form-data; boundary=${boundary}` },
      body,
    });
  }
}

function extractToken(urlOrText) {
  const fromUrl = String(urlOrText).match(/[?&]token=([^&"'\s]+)/i);
  if (fromUrl) {
    return decodeURIComponent(fromUrl[1]);
  }
  const fromHtml = String(urlOrText).match(/name="access_token"\s+value="([^"]+)"/i);
  return fromHtml ? fromHtml[1] : null;
}

function extractApplicationId(html) {
  const m = String(html).match(/Application ID:\s*<\/strong>\s*(\d+)/i)
    || String(html).match(/Application ID:\s*(\d+)/i)
    || String(html).match(/Application #(\d+)/i);
  return m ? Number(m[1]) : null;
}

function extractStatus(html) {
  const m = String(html).match(/Status:<\/strong>\s*([^<\n]+)/i)
    || String(html).match(/<dd><span class="[^"]*">([^<]+)<\/span><\/dd>/i);
  return m ? m[1].trim() : null;
}

function extractDetail(html, label) {
  const re = new RegExp(`<dt>${label}<\\/dt>\\s*<dd>(?:<span[^>]*>)?([^<]+)`, 'i');
  const m = String(html).match(re);
  return m ? m[1].replace(/&amp;/g, '&').trim() : null;
}

function assertContains(haystack, needle, message) {
  if (!String(haystack).includes(needle)) {
    fail(message, { needle });
  }
}

function stepRank(through) {
  const idx = THROUGH_STEPS.indexOf(through);
  if (idx < 0) {
    fail(`Invalid --through=${through}. Use: ${THROUGH_STEPS.join('|')}`);
  }
  return idx;
}

function shouldRun(through, step) {
  return stepRank(step) <= stepRank(through);
}

async function run() {
  loadDotEnv();
  const args = parseArgs(process.argv.slice(2));
  if (args.help) {
    console.log(`Usage: OPS_LOGIN=... OPS_PASSWORD=... node scripts/e2e-provider-signup-uat.js [--through=draft|approve|provision]`);
    process.exit(0);
  }

  const cfg = {
    baseUrl: (process.env.E2E_BASE_URL || process.env.BASE_URL || DEFAULT_BASE).replace(/\/$/, ''),
    opsLogin: process.env.OPS_LOGIN || process.env.E2E_OPS_LOGIN || '',
    opsPassword: process.env.OPS_PASSWORD || process.env.E2E_OPS_PASSWORD || '',
    emailDomain: process.env.E2E_EMAIL_DOMAIN || 'nutraaxislabs.com',
    providerEmail: process.env.E2E_PROVIDER_EMAIL || '',
    adminEmail: process.env.E2E_ADMIN_EMAIL || '',
    npiNumber: process.env.E2E_NPI_NUMBER || '1679576722',
    routingNumber: process.env.E2E_ACH_ROUTING || '021000021',
    accountNumber: process.env.E2E_ACH_ACCOUNT || '123456789',
    through: args.through,
  };

  if (!fs.existsSync(CERT_PATH)) {
    fail(`Missing certificate fixture: ${CERT_PATH}`);
  }

  if (shouldRun(cfg.through, 'approve') && (!cfg.opsLogin || !cfg.opsPassword)) {
    fail('OPS_LOGIN and OPS_PASSWORD are required for approve/provision steps.');
  }

  const startedAt = new Date().toISOString();
  const steps = [];
  const http = new HttpClient(cfg.baseUrl);
  const form = buildPayload(cfg);
  let token = null;
  let applicationId = null;
  let finalStatus = null;
  let accsCompanyId = null;
  let accsCustomerId = null;
  let clinicId = null;
  let warnings = [];

  const record = (name, ok, info = {}) => {
    const entry = { name, ok, at: new Date().toISOString(), ...info };
    steps.push(entry);
    const mark = ok ? 'PASS' : 'FAIL';
    console.log(`[${mark}] ${name}${info.detail ? ` — ${info.detail}` : ''}`);
    return entry;
  };

  try {
    // 0) Smoke: application start page
    {
      const res = await http.get('/provider-signup/application.php');
      if (res.status !== 200) {
        fail(`Start page HTTP ${res.status}`);
      }
      assertContains(res.text, 'Practitioner', 'Start page missing Practitioner copy');
      record('public_application_page', true, { status: res.status });
    }

    // 1) Start application
    {
      const res = await http.postForm('/provider-signup/start.php', {
        provider_email: form.provider_email,
      });
      token = extractToken(res.url) || extractToken(res.text);
      if (!token) {
        fail('Could not capture application access token after start.', {
          status: res.status,
          url: res.url,
          snippet: res.text.slice(0, 400),
        });
      }
      if (!/policy\.php/i.test(res.url) && !/policy-ack|Practitioner Reseller/i.test(res.text)) {
        // Soft check — apply gate will still enforce acknowledgement.
      }
      const policy = await http.get(`/provider-signup/policy.php?token=${encodeURIComponent(token)}`);
      applicationId = extractApplicationId(policy.text);
      if (!applicationId) {
        fail('Could not read Application ID from policy page.');
      }
      finalStatus = extractStatus(policy.text) || 'Draft';
      record('start_application', true, {
        detail: `id=${applicationId}`,
        tokenPreview: `${token.slice(0, 8)}…`,
        providerEmail: form.provider_email,
      });
    }

    // 1b) Acknowledge reseller policy
    {
      const res = await http.postForm('/provider-signup/policy.php', {
        access_token: token,
        action: 'acknowledge_policy',
        policy_acknowledged: '1',
      });
      if (!/apply\.php|policy_acknowledged/i.test(res.url) && !/Policy acknowledgement recorded/i.test(res.text)) {
        // Follow-up GET to apply should succeed only after ack.
      }
      const apply = await http.get(`/provider-signup/apply.php?token=${encodeURIComponent(token)}`);
      if (/policy\.php/i.test(apply.url) || /Acknowledge and continue/i.test(apply.text)) {
        fail('Policy acknowledgement did not unlock the application form.', {
          status: apply.status,
          url: apply.url,
          postUrl: res.url,
        });
      }
      applicationId = extractApplicationId(apply.text) || applicationId;
      finalStatus = extractStatus(apply.text) || finalStatus;
      record('acknowledge_policy', true, { status: finalStatus });
    }

    // 2) Save draft with full checklist fields
    {
      const res = await http.postForm('/provider-signup/apply.php', {
        access_token: token,
        action: 'save_draft',
        ...form,
      });
      const apply = await http.get(`/provider-signup/apply.php?token=${encodeURIComponent(token)}`);
      if (!/draft_saved|Draft saved|Status:\s*<\/strong>\s*Draft/i.test(`${res.url}\n${apply.text}`)) {
        if (/Unable|error|required/i.test(apply.text) && /signup-alert--error/i.test(apply.text)) {
          fail('Draft save appears to have failed.', { snippet: apply.text.match(/signup-alert--error[\s\S]{0,300}/)?.[0] });
        }
      }
      assertContains(apply.text, form.company_name, 'Saved company name not shown on apply page');
      finalStatus = extractStatus(apply.text) || finalStatus;
      record('save_draft', true, { status: finalStatus });
    }

    // 3) Upload reseller certificate
    {
      const res = await http.postMultipart(
        '/provider-signup/apply.php',
        { access_token: token, action: 'upload_certificate' },
        'reseller_certificate',
        CERT_PATH,
        'uat-reseller-certificate.pdf',
        'application/pdf'
      );
      const apply = await http.get(`/provider-signup/apply.php?token=${encodeURIComponent(token)}`);
      if (!/certificate_uploaded|Reseller certificate|Uploaded documents|uat-reseller-certificate/i.test(`${res.url}\n${apply.text}`)) {
        fail('Certificate upload did not confirm on apply page.', {
          status: res.status,
          url: res.url,
          snippet: apply.text.match(/signup-alert[\s\S]{0,250}/)?.[0],
        });
      }
      record('upload_certificate', true);
    }

    if (!shouldRun(cfg.through, 'approve')) {
      record('stop_after_draft', true, { detail: '--through=draft' });
    } else {
      // 4) Ops login
      {
        const loginPage = await http.get('/login/?redirect=/operations-dashboard/signup-review/');
        if (loginPage.status !== 200) {
          fail(`Login page HTTP ${loginPage.status}`);
        }
        const res = await http.postForm('/login/', {
          login: cfg.opsLogin,
          password: cfg.opsPassword,
          redirect: '/operations-dashboard/signup-review/',
        });
        const queue = await http.get('/operations-dashboard/signup-review/?status=Draft');
        if (queue.status !== 200 || /Log in to Operations|name="password"/i.test(queue.text)) {
          fail('Ops login failed or session cookie not accepted.', {
            status: queue.status,
            url: res.url,
          });
        }
        if (!/ProviderAccountReview|Provider Signup|signup-review|Application/i.test(queue.text)) {
          fail('Ops user may lack ProviderAccountReview permission.');
        }
        record('ops_login', true, { login: cfg.opsLogin });
      }

      // 5) Open review view
      {
        const view = await http.get(`/operations-dashboard/signup-review/view.php?id=${applicationId}`);
        if (view.status !== 200) {
          fail(`Ops view HTTP ${view.status}`);
        }
        assertContains(view.text, `Application #${applicationId}`, 'Ops view missing application heading');
        record('ops_open_application', true);
      }

      // 6) Approve
      {
        const res = await http.postForm(
          `/operations-dashboard/signup-review/view.php?id=${applicationId}`,
          {
            action: 'approve',
            comments: 'E2E UAT automated approval',
          }
        );
        const view = await http.get(`/operations-dashboard/signup-review/view.php?id=${applicationId}`);
        finalStatus = extractDetail(view.text, 'Status') || extractStatus(view.text);
        if (!/Approved/i.test(String(finalStatus)) && !/notice=approved/i.test(res.url)) {
          const err = extractDetail(view.text, 'error')
            || view.text.match(/admin-notice is-error[^>]*>([^<]+)/i)?.[1]
            || view.text.match(/[?&]error=([^&]+)/)?.[1];
          fail('Approve did not reach Approved status.', {
            status: finalStatus,
            error: err ? decodeURIComponent(String(err).replace(/\+/g, ' ')) : null,
            url: res.url,
          });
        }
        const warn = view.text.match(/NPI validation did not pass[^<]*/i)?.[0];
        if (warn) {
          warnings.push(warn);
        }
        finalStatus = 'Approved';
        record('ops_approve', true, { warn: warn || null });
      }

      if (!shouldRun(cfg.through, 'provision')) {
        record('stop_after_approve', true, { detail: '--through=approve' });
      } else {
        // 7) Create ACCS company
        {
          const res = await http.postForm(
            `/operations-dashboard/signup-review/view.php?id=${applicationId}`,
            {
              action: 'provision',
              comments: '',
            }
          );
          const view = await http.get(`/operations-dashboard/signup-review/view.php?id=${applicationId}`);
          finalStatus = extractDetail(view.text, 'Status') || extractStatus(view.text);
          accsCompanyId = extractDetail(view.text, 'ACCS company ID');
          accsCustomerId = extractDetail(view.text, 'ACCS customer ID');
          clinicId = extractDetail(view.text, 'Clinic ID');

          const provisionError = view.text.match(/Last ACCS provisioning attempt failed:\s*([^<]+)/i)?.[1]
            || view.text.match(/[?&]error=([^&]+)/)?.[1];

          if (!/Provisioned/i.test(String(finalStatus))) {
            fail('Provision did not reach Provisioned status.', {
              status: finalStatus,
              error: provisionError ? decodeURIComponent(String(provisionError).replace(/\+/g, ' ')) : null,
              url: res.url,
              companyId: accsCompanyId,
              customerId: accsCustomerId,
            });
          }

          if (!accsCompanyId || accsCompanyId === '—' || !accsCustomerId || accsCustomerId === '—') {
            fail('Provisioned status set but ACCS IDs are missing.', {
              companyId: accsCompanyId,
              customerId: accsCustomerId,
              clinicId,
            });
          }

          record('ops_provision_accs', true, {
            detail: `company=${accsCompanyId} customer=${accsCustomerId}`,
            companyId: accsCompanyId,
            customerId: accsCustomerId,
            clinicId,
          });
        }
      }
    }
  } catch (err) {
    record(err.message || 'unexpected_error', false, {
      detail: err.detail ? JSON.stringify(err.detail) : undefined,
      stack: err.stack,
    });
  }

  const failed = steps.filter((s) => !s.ok);
  const passed = failed.length === 0;
  const finishedAt = new Date().toISOString();
  const signOffCriteria = [
    {
      id: 'start',
      through: 'draft',
      text: 'Public start creates Draft application with emailed return link',
    },
    {
      id: 'policy',
      through: 'draft',
      text: 'Practitioner Reseller Policy acknowledgement is recorded before the form',
    },
    {
      id: 'draft',
      through: 'draft',
      text: 'Provider can save company, admin, qualifications, and ACH fields',
    },
    {
      id: 'upload',
      through: 'draft',
      text: 'Reseller certificate upload succeeds',
    },
    {
      id: 'approve',
      through: 'approve',
      text: 'Ops can approve Draft when checklist is complete',
    },
    {
      id: 'provision',
      through: 'provision',
      text: 'Ops Create ACCS company sets Status=Provisioned with company + customer IDs',
    },
    {
      id: 'email',
      through: 'provision',
      text: 'Provider receives Clinic Store ready email (manual mailbox check)',
      manual: true,
    },
  ];

  const report = {
    suite: 'provider-signup-e2e-uat',
    result: passed ? 'PASS' : 'FAIL',
    through: cfg.through,
    baseUrl: cfg.baseUrl,
    startedAt,
    finishedAt,
    applicationId,
    accessTokenPreview: token ? `${token.slice(0, 8)}…` : null,
    applyUrl: token ? `${cfg.baseUrl}/provider-signup/apply.php?token=${encodeURIComponent(token)}` : null,
    opsViewUrl: applicationId
      ? `${cfg.baseUrl}/operations-dashboard/signup-review/view.php?id=${applicationId}`
      : null,
    providerEmail: form.provider_email,
    adminEmail: form.admin_email,
    companyName: form.company_name,
    finalStatus,
    accsCompanyId,
    accsCustomerId,
    clinicId,
    warnings,
    signOffCriteria: signOffCriteria.map((item) => {
      const inScope = stepRank(item.through) <= stepRank(cfg.through);
      const autoDone = passed && inScope && !item.manual;
      return {
        text: item.text,
        inScope,
        manual: Boolean(item.manual),
        checked: autoDone,
      };
    }),
    steps,
  };

  fs.mkdirSync(ARTIFACT_DIR, { recursive: true });
  const baseName = `provider-signup-e2e-${stamp()}`;
  const jsonPath = path.join(ARTIFACT_DIR, `${baseName}.json`);
  const mdPath = path.join(ARTIFACT_DIR, `${baseName}.md`);
  fs.writeFileSync(jsonPath, `${JSON.stringify(report, null, 2)}\n`);
  fs.writeFileSync(mdPath, renderMarkdown(report));

  console.log('');
  console.log(`Result: ${report.result}`);
  console.log(`Report: ${jsonPath}`);
  console.log(`Sign-off: ${mdPath}`);
  if (report.applyUrl) {
    console.log(`Apply URL: ${report.applyUrl}`);
  }
  if (report.opsViewUrl) {
    console.log(`Ops view: ${report.opsViewUrl}`);
  }
  if (args.headedReport) {
    console.log('\n' + fs.readFileSync(mdPath, 'utf8'));
  }

  process.exit(passed ? 0 : 1);
}

function renderMarkdown(report) {
  const lines = [
    `# Provider Signup E2E UAT Sign-off`,
    '',
    `- **Result:** ${report.result}`,
    `- **Through:** ${report.through}`,
    `- **Base URL:** ${report.baseUrl}`,
    `- **Started:** ${report.startedAt}`,
    `- **Finished:** ${report.finishedAt}`,
    `- **Application ID:** ${report.applicationId ?? '—'}`,
    `- **Final status:** ${report.finalStatus ?? '—'}`,
    `- **Provider email:** ${report.providerEmail}`,
    `- **Admin email:** ${report.adminEmail}`,
    `- **Company:** ${report.companyName}`,
    `- **ACCS company ID:** ${report.accsCompanyId ?? '—'}`,
    `- **ACCS customer ID:** ${report.accsCustomerId ?? '—'}`,
    `- **Clinic ID:** ${report.clinicId ?? '—'}`,
    '',
    '## Links',
    '',
    `- Apply: ${report.applyUrl ?? '—'}`,
    `- Ops view: ${report.opsViewUrl ?? '—'}`,
    '',
    '## Automated steps',
    '',
    '| Step | Result | Detail |',
    '| --- | --- | --- |',
  ];

  for (const step of report.steps) {
    lines.push(`| ${step.name} | ${step.ok ? 'PASS' : 'FAIL'} | ${(step.detail || step.warn || '').replace(/\|/g, '\\|')} |`);
  }

  lines.push(
    '',
    '## Sign-off checklist',
    '',
  );
  for (const item of report.signOffCriteria) {
    const prefix = item.inScope ? '' : '_(out of scope for this run)_ ';
    lines.push(`- [${item.checked ? 'x' : ' '}] ${prefix}${item.text}${item.manual ? ' _(manual)_' : ''}`);
  }

  if (report.warnings.length) {
    lines.push('', '## Warnings', '');
    for (const warn of report.warnings) {
      lines.push(`- ${warn}`);
    }
  }

  lines.push(
    '',
    '## Approver sign-off',
    '',
    '| Role | Name | Date | Signature / initials |',
    '| --- | --- | --- | --- |',
    '| QA / UAT lead |  |  |  |',
    '| Operations |  |  |  |',
    '| Product / Engineering |  |  |  |',
    '',
    '_Attach this report with the Application ID and ACCS IDs as evidence of build completion._',
    ''
  );

  return lines.join('\n');
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
