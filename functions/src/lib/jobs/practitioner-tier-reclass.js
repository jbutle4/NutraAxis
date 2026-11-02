const { sql, connectPool, getProductionDatabase } = require('../db-config');
const adobeCommerce = require('../adobe-commerce');

const EXCLUDED_COMPANY_NAMES = new Set(['Clinic_Template', 'Clinic Template']);
const DEFAULT_ENVS = ['production', 'stage'];

function resolveEnvs(options = {}) {
  if (options.environment) {
    return [adobeCommerce.resolveEnvironmentName(options.environment)];
  }
  const configured = String(process.env.PRACTITIONER_TIER_ENVIRONMENTS || '')
    .split(',')
    .map((v) => v.trim().toLowerCase())
    .filter(Boolean);
  if (configured.length > 0) {
    return configured.map((env) => adobeCommerce.resolveEnvironmentName(env));
  }
  return DEFAULT_ENVS.map((env) => adobeCommerce.resolveEnvironmentName(env));
}

function ytdBounds(now = new Date()) {
  // Approximate America/Chicago YTD using locale offset; ACCS OrderCreatedAt is store-local.
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'America/Chicago',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  });
  const parts = Object.fromEntries(formatter.formatToParts(now).map((p) => [p.type, p.value]));
  const year = Number(parts.year);
  const end = `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`;
  return {
    year,
    start: `${year}-01-01 00:00:00`,
    end,
  };
}

async function loadThresholds(pool) {
  const result = await pool.request().query(`
    SELECT TierCode, DisplayName, MinRevenue, MaxRevenue, StageGroupId, ProdGroupId, SortOrder
    FROM dbo.PractitionerTierThreshold
    WHERE IsActive = 1
    ORDER BY SortOrder ASC
  `);
  return result.recordset || [];
}

function groupIdFor(threshold, envName) {
  return envName === 'stage' ? Number(threshold.StageGroupId) : Number(threshold.ProdGroupId);
}

function resolveTier(thresholds, revenue, envName) {
  const amount = Number(revenue) || 0;
  for (const row of thresholds) {
    const min = Number(row.MinRevenue) || 0;
    const max = row.MaxRevenue == null || row.MaxRevenue === '' ? null : Number(row.MaxRevenue);
    if (amount + 0.00001 < min) continue;
    if (max != null && amount - 0.00001 > max) continue;
    return { ...row, ResolvedGroupId: groupIdFor(row, envName) };
  }
  return null;
}

async function getOverride(pool, companyId, envName) {
  const result = await pool.request()
    .input('companyId', sql.Int, companyId)
    .input('env', sql.NVarChar(20), envName)
    .query(`
      SELECT TOP 1 *
      FROM dbo.PractitionerTierOverride
      WHERE AccsCompanyId = @companyId AND SourceEnvironment = @env
    `);
  return result.recordset[0] || null;
}

async function sumRevenue(pool, customerIds, envName) {
  const ids = [...new Set(customerIds.map(Number).filter((id) => id > 0))];
  if (ids.length === 0) {
    return 0;
  }
  const bounds = ytdBounds();
  const request = pool.request()
    .input('env', sql.NVarChar(20), envName)
    .input('start', sql.NVarChar(30), bounds.start)
    .input('end', sql.NVarChar(30), bounds.end);

  const placeholders = ids.map((_, i) => {
    const name = `c${i}`;
    request.input(name, sql.Int, ids[i]);
    return `@${name}`;
  });

  const result = await request.query(`
    SELECT CAST(SUM(ISNULL(GrandTotal, 0)) AS DECIMAL(18,2)) AS Revenue
    FROM dbo.AccsSalesOrderHeader
    WHERE SourceEnvironment = @env
      AND CustomerId IN (${placeholders.join(', ')})
      AND OrderCreatedAt >= @start
      AND OrderCreatedAt <= @end
      AND LOWER(ISNULL(OrderStatus, N'')) NOT IN (N'canceled', N'cancelled', N'fraud', N'paypal_canceled_reversal')
  `);
  return Number(result.recordset[0]?.Revenue || 0);
}

async function listCompanies(envName) {
  const companies = [];
  let page = 1;
  while (page <= 40) {
    const result = await adobeCommerce.apiRequest('GET', '/company/', {
      'searchCriteria[pageSize]': '50',
      'searchCriteria[currentPage]': String(page),
    }, null, { environment: envName });
    if (!result.ok) {
      return { ok: false, error: result.error, companies: [] };
    }
    const items = Array.isArray(result.data?.items) ? result.data.items : [];
    if (items.length === 0) break;
    for (const item of items) {
      const name = String(item.company_name || '').trim();
      if (EXCLUDED_COMPANY_NAMES.has(name)) continue;
      companies.push(item);
    }
    if (items.length < 50) break;
    page += 1;
  }
  return { ok: true, error: null, companies };
}

async function listHierarchyCustomers(companyId, envName) {
  const hierarchy = await adobeCommerce.apiRequest('GET', `/hierarchy/${companyId}`, null, null, { environment: envName });
  if (!hierarchy.ok) {
    return { ok: false, error: hierarchy.error, customers: [] };
  }
  const rows = Array.isArray(hierarchy.data) ? hierarchy.data : [];
  const customers = [];
  for (const row of rows) {
    if (!row || row.entity_type !== 'customer') continue;
    const customerId = Number(row.entity_id || 0);
    if (!customerId) continue;
    const customer = await adobeCommerce.apiRequest('GET', `/customers/${customerId}`, null, null, { environment: envName });
    if (customer.ok && customer.data) customers.push(customer.data);
  }
  return { ok: true, error: null, customers };
}

async function listClinicCustomers(companyId, envName) {
  const customers = [];
  let page = 1;
  while (page <= 40) {
    const result = await adobeCommerce.apiRequest('GET', '/customers/search', {
      'searchCriteria[filterGroups][0][filters][0][field]': 'clinic_id',
      'searchCriteria[filterGroups][0][filters][0][value]': String(companyId),
      'searchCriteria[filterGroups][0][filters][0][condition_type]': 'eq',
      'searchCriteria[pageSize]': '50',
      'searchCriteria[currentPage]': String(page),
    }, null, { environment: envName });
    if (!result.ok) {
      return { ok: false, error: result.error, customers: [] };
    }
    const items = Array.isArray(result.data?.items) ? result.data.items : [];
    if (items.length === 0) break;
    customers.push(...items);
    if (items.length < 50) break;
    page += 1;
  }
  return { ok: true, error: null, customers };
}

async function setCompanyGroup(company, groupId, envName) {
  const companyId = Number(company.id);
  if (Number(company.customer_group_id) === groupId) {
    return { ok: true, updated: false };
  }
  const payload = { ...company, customer_group_id: groupId };
  const result = await adobeCommerce.apiRequest('PUT', `/company/${companyId}`, null, payload, { environment: envName });
  return { ok: result.ok, updated: Boolean(result.ok), error: result.error };
}

async function setCustomerGroup(customer, groupId, envName) {
  const customerId = Number(customer.id);
  if (Number(customer.group_id) === groupId) {
    return { ok: true, updated: false };
  }
  const payload = {
    customer: {
      id: customerId,
      email: customer.email,
      firstname: customer.firstname,
      lastname: customer.lastname,
      website_id: customer.website_id || 1,
      group_id: groupId,
    },
  };
  const result = await adobeCommerce.apiRequest('PUT', `/customers/${customerId}`, null, payload, { environment: envName });
  return { ok: result.ok, updated: Boolean(result.ok), error: result.error };
}

async function startRunLog(pool, envName, triggerType, triggeredBy) {
  const result = await pool.request()
    .input('env', sql.NVarChar(20), envName)
    .input('triggerType', sql.NVarChar(30), triggerType)
    .input('triggeredBy', sql.NVarChar(200), triggeredBy || null)
    .query(`
      INSERT INTO dbo.PractitionerTierRunLog (SourceEnvironment, TriggerType, TriggeredBy, Status)
      OUTPUT INSERTED.RunLogID AS RunLogID
      VALUES (@env, @triggerType, @triggeredBy, N'running')
    `);
  return Number(result.recordset[0].RunLogID);
}

async function finishRunLog(pool, runLogId, stats) {
  await pool.request()
    .input('id', sql.Int, runLogId)
    .input('status', sql.NVarChar(20), stats.status)
    .input('scanned', sql.Int, stats.scanned)
    .input('updated', sql.Int, stats.updated)
    .input('skipped', sql.Int, stats.skipped)
    .input('failed', sql.Int, stats.failed)
    .input('message', sql.NVarChar(1000), stats.message)
    .query(`
      UPDATE dbo.PractitionerTierRunLog
      SET FinishedAt = SYSUTCDATETIME(),
          Status = @status,
          CompaniesScanned = @scanned,
          CompaniesUpdated = @updated,
          CompaniesSkipped = @skipped,
          CompaniesFailed = @failed,
          Message = @message
      WHERE RunLogID = @id
    `);
}

async function insertDetail(pool, runLogId, detail) {
  await pool.request()
    .input('runLogId', sql.Int, runLogId)
    .input('companyId', sql.Int, detail.companyId)
    .input('companyName', sql.NVarChar(255), detail.companyName)
    .input('ytd', sql.Decimal(18, 2), detail.ytd)
    .input('providerRev', sql.Decimal(18, 2), detail.providerRev)
    .input('patientRev', sql.Decimal(18, 2), detail.patientRev)
    .input('fromGroup', sql.Int, detail.fromGroup)
    .input('toGroup', sql.Int, detail.toGroup)
    .input('tierCode', sql.NVarChar(40), detail.tierCode)
    .input('actionStatus', sql.NVarChar(30), detail.actionStatus)
    .input('skipReason', sql.NVarChar(100), detail.skipReason)
    .input('detailMessage', sql.NVarChar(1000), detail.detailMessage)
    .query(`
      INSERT INTO dbo.PractitionerTierRunDetail (
        RunLogID, AccsCompanyId, CompanyName, YtdRevenue, ProviderRevenue, PatientRevenue,
        FromGroupId, ToGroupId, SuggestedTierCode, ActionStatus, SkipReason, DetailMessage
      ) VALUES (
        @runLogId, @companyId, @companyName, @ytd, @providerRev, @patientRev,
        @fromGroup, @toGroup, @tierCode, @actionStatus, @skipReason, @detailMessage
      )
    `);
}

async function runEnvironment(pool, thresholds, envName, options = {}) {
  const runLogId = await startRunLog(pool, envName, options.triggerType || 'monthly', options.triggeredBy || 'azure-function');
  const stats = { scanned: 0, updated: 0, skipped: 0, failed: 0 };

  const companiesResult = await listCompanies(envName);
  if (!companiesResult.ok) {
    await finishRunLog(pool, runLogId, {
      status: 'failed',
      ...stats,
      message: companiesResult.error || 'Unable to list companies.',
    });
    return { ok: false, error: companiesResult.error, run_log_id: runLogId, ...stats };
  }

  for (const company of companiesResult.companies) {
    const companyId = Number(company.id || 0);
    if (!companyId) continue;
    if (options.onlyCompanyId && companyId !== Number(options.onlyCompanyId)) continue;

    stats.scanned += 1;
    const providersResult = await listHierarchyCustomers(companyId, envName);
    const clinicResult = await listClinicCustomers(companyId, envName);
    const providers = providersResult.ok ? providersResult.customers : [];
    const clinicCustomers = clinicResult.ok ? clinicResult.customers : [];
    const providerIds = providers.map((c) => Number(c.id)).filter(Boolean);
    const providerIdSet = new Set(providerIds);
    const patientIds = clinicCustomers
      .map((c) => Number(c.id))
      .filter((id) => id > 0 && !providerIdSet.has(id));

    const providerRev = await sumRevenue(pool, providerIds, envName);
    const patientRev = await sumRevenue(pool, patientIds, envName);
    const ytd = Math.round((providerRev + patientRev) * 100) / 100;
    const suggested = resolveTier(thresholds, ytd, envName);
    const fromGroup = Number(company.customer_group_id || 0);
    const override = await getOverride(pool, companyId, envName);

    let actionStatus = 'skipped';
    let skipReason = null;
    let detailMessage = null;
    let toGroup = suggested ? Number(suggested.ResolvedGroupId) : 0;
    let tierCode = suggested ? String(suggested.TierCode) : null;

    if (override && Number(override.IsLocked) === 1) {
      stats.skipped += 1;
      skipReason = 'override';
      detailMessage = 'Manual override lock is set.';
      toGroup = Number(override.LockedGroupId || fromGroup);
      tierCode = override.LockedTierCode || tierCode;
    } else if (!toGroup) {
      stats.failed += 1;
      actionStatus = 'failed';
      detailMessage = 'Unable to resolve tier for revenue.';
    } else {
      const companyUpdate = await setCompanyGroup(company, toGroup, envName);
      let providersUpdated = 0;
      let providersFailed = 0;
      for (const customer of providers) {
        const update = await setCustomerGroup(customer, toGroup, envName);
        if (!update.ok) {
          providersFailed += 1;
        } else if (update.updated) {
          providersUpdated += 1;
        }
      }
      if (!companyUpdate.ok || providersFailed > 0) {
        stats.failed += 1;
        actionStatus = 'failed';
        detailMessage = companyUpdate.error || 'One or more provider updates failed.';
      } else if (companyUpdate.updated || providersUpdated > 0) {
        stats.updated += 1;
        actionStatus = 'updated';
        detailMessage = fromGroup === toGroup
          ? 'Aligned providers to current company tier.'
          : `Reclassified to ${tierCode}.`;
      } else {
        stats.skipped += 1;
        skipReason = 'already_current';
        detailMessage = 'Company and providers already on suggested tier.';
      }
    }

    await insertDetail(pool, runLogId, {
      companyId,
      companyName: String(company.company_name || ''),
      ytd,
      providerRev,
      patientRev,
      fromGroup,
      toGroup: toGroup || null,
      tierCode,
      actionStatus,
      skipReason,
      detailMessage,
    });
  }

  const status = stats.failed > 0 && stats.updated > 0
    ? 'partial'
    : (stats.failed > 0 && stats.updated === 0 ? 'failed' : 'success');
  const message = `Scanned ${stats.scanned} — updated ${stats.updated}, skipped ${stats.skipped}, failed ${stats.failed}.`;
  await finishRunLog(pool, runLogId, { status, ...stats, message });

  return {
    ok: status !== 'failed',
    error: status === 'failed' ? 'Run completed with failures.' : null,
    run_log_id: runLogId,
    environment: envName,
    ...stats,
    message,
  };
}

async function run(options = {}) {
  const pool = await connectPool(getProductionDatabase());
  const thresholds = await loadThresholds(pool);
  if (!thresholds.length) {
    return { ok: false, error: 'No active practitioner tier thresholds found. Apply sql/148_create_practitioner_tier.sql.' };
  }

  const envs = resolveEnvs(options);
  const results = [];
  for (const envName of envs) {
    results.push(await runEnvironment(pool, thresholds, envName, options));
  }

  const failed = results.filter((r) => !r.ok);
  return {
    ok: failed.length === 0,
    error: failed.length ? failed.map((r) => `${r.environment}: ${r.error}`).join('; ') : null,
    environments: results,
    scanned: results.reduce((sum, r) => sum + (r.scanned || 0), 0),
    updated: results.reduce((sum, r) => sum + (r.updated || 0), 0),
    skipped: results.reduce((sum, r) => sum + (r.skipped || 0), 0),
    failed: results.reduce((sum, r) => sum + (r.failed || 0), 0),
  };
}

module.exports = {
  run,
};
