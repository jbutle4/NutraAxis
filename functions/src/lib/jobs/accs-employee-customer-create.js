const { sql, connectPool, getProductionDatabase } = require('../db-config');
const adobeCommerce = require('../adobe-commerce');
const { resolveGroupIdFromEmployee, loadGroupMap } = require('../accs-customer-groups');
const {
  applyCreateRestrictions,
  buildCustomerUpdateRecord,
  shouldStartInactive,
  shouldStartLocked,
} = require('../accs-customer-restrictions');

const DEFAULT_TARGET_ENV = 'stage';
const ALLOWED_TARGET_ENVS = new Set(['stage', 'production']);

function envInt(name, fallback) {
  const value = Number(process.env[name]);
  return Number.isFinite(value) && value >= 0 ? value : fallback;
}

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function targetEnvironment() {
  return adobeCommerce.resolveEnvironmentName(
    process.env.ACCS_EMPLOYEE_CUSTOMER_TARGET_ENV || DEFAULT_TARGET_ENV
  );
}

function trackingColumns(envName) {
  if (envName === 'production') {
    return {
      customerId: 'AccsProdCustomerId',
      createdAt: 'AccsProdCustomerCreatedAt',
      lastError: 'AccsProdCustomerLastError',
    };
  }

  return {
    customerId: 'AccsStageCustomerId',
    createdAt: 'AccsStageCustomerCreatedAt',
    lastError: 'AccsStageCustomerLastError',
  };
}

async function loadEmployees(pool, envName, options = {}) {
  const includeExisting = Boolean(options.includeExisting);
  const retryFailed = Boolean(options.retryFailed);
  const fixGroupsOnly = Boolean(options.fixGroupsOnly);
  const cols = trackingColumns(envName);

  if (fixGroupsOnly) {
    const result = await pool.request().query(`
      SELECT
        EmployeeListID,
        Company,
        LastName,
        FirstName,
        Email,
        Department,
        JobTitle,
        Group1,
        Group2,
        FirstEmail,
        ${cols.customerId} AS AccsCustomerId,
        ${cols.lastError} AS AccsCustomerLastError
      FROM dbo.EmployeeList
      WHERE FirstEmail = 1
        AND ${cols.customerId} IS NOT NULL
      ORDER BY EmployeeListID ASC
    `);
    return result.recordset;
  }

  let whereClause = 'FirstEmail = 1';
  if (!includeExisting) {
    whereClause += retryFailed
      ? ` AND ${cols.customerId} IS NULL`
      : ` AND ${cols.customerId} IS NULL AND ${cols.lastError} IS NULL`;
  }

  const result = await pool.request().query(`
    SELECT
      EmployeeListID,
      Company,
      LastName,
      FirstName,
      Email,
      Department,
      JobTitle,
      Group1,
      Group2,
      FirstEmail,
      ${cols.customerId} AS AccsCustomerId,
      ${cols.lastError} AS AccsCustomerLastError
    FROM dbo.EmployeeList
    WHERE ${whereClause}
    ORDER BY EmployeeListID ASC
  `);

  return result.recordset;
}

async function markEmployeeSuccess(pool, envName, employeeListId, accsCustomerId) {
  const cols = trackingColumns(envName);
  await pool.request()
    .input('employeeListId', sql.Int, employeeListId)
    .input('accsCustomerId', sql.Int, accsCustomerId)
    .query(`
      UPDATE dbo.EmployeeList
      SET ${cols.customerId} = @accsCustomerId,
          ${cols.createdAt} = SYSUTCDATETIME(),
          ${cols.lastError} = NULL
      WHERE EmployeeListID = @employeeListId
    `);
}

async function markEmployeeFailure(pool, envName, employeeListId, errorMessage) {
  const cols = trackingColumns(envName);
  const message = String(errorMessage ?? 'Unknown error').slice(0, 500);
  await pool.request()
    .input('employeeListId', sql.Int, employeeListId)
    .input('errorMessage', sql.NVarChar(500), message)
    .query(`
      UPDATE dbo.EmployeeList
      SET ${cols.lastError} = @errorMessage
      WHERE EmployeeListID = @employeeListId
    `);
}

function buildCustomerPayload(employee, groupId) {
  const email = String(employee.Email ?? '').trim();
  const firstname = String(employee.FirstName ?? '').trim();
  const lastname = String(employee.LastName ?? '').trim();

  if (!email || !firstname || !lastname) {
    return { ok: false, error: 'Employee row is missing email, first name, or last name.' };
  }

  return {
    ok: true,
    customer: applyCreateRestrictions({
      email,
      firstname,
      lastname,
      group_id: groupId,
    }),
  };
}

async function syncCustomerAccount(customerId, expectedGroupId, apiOptions) {
  const fetchResult = await adobeCommerce.fetchCustomerById(customerId, apiOptions);
  if (!fetchResult.ok) {
    return { ok: false, error: fetchResult.error, group_updated: false };
  }

  const currentGroupId = Number(fetchResult.customer.group_id);
  const needsGroupUpdate = currentGroupId !== expectedGroupId;

  const updatePayload = buildCustomerUpdateRecord(fetchResult.customer, expectedGroupId);
  const updateResult = await adobeCommerce.updateCustomer(updatePayload, apiOptions);

  if (!updateResult.ok) {
    return { ok: false, error: updateResult.error, group_updated: false };
  }

  return {
    ok: true,
    error: null,
    group_updated: needsGroupUpdate,
    inactive: shouldStartInactive(),
    locked: shouldStartLocked(),
  };
}

async function ensureAccsCustomer(employee, apiOptions, options = {}) {
  const groupId = resolveGroupIdFromEmployee(employee, apiOptions.environment);
  if (!groupId) {
    return {
      ok: false,
      action: 'failed',
      error: `Unsupported group value Group1="${employee.Group1 ?? ''}" Group2="${employee.Group2 ?? ''}". Update ACCS_EMPLOYEE_CUSTOMER_GROUP_MAP.`,
    };
  }

  const linkedCustomerId = Number(employee.AccsCustomerId);
  if (options.fixGroupsOnly && Number.isFinite(linkedCustomerId) && linkedCustomerId > 0) {
    const syncResult = await syncCustomerAccount(linkedCustomerId, groupId, apiOptions);
    if (!syncResult.ok) {
      return { ok: false, action: 'failed', error: syncResult.error };
    }

    return {
      ok: true,
      action: syncResult.group_updated ? 'group_updated' : 'unchanged',
      customer_id: linkedCustomerId,
      group_id: groupId,
      inactive: syncResult.inactive,
      locked: syncResult.locked,
    };
  }

  const payloadResult = buildCustomerPayload(employee, groupId);
  if (!payloadResult.ok) {
    return { ok: false, action: 'failed', error: payloadResult.error };
  }

  const existingResult = await adobeCommerce.searchCustomersByEmail(payloadResult.customer.email, apiOptions);
  if (!existingResult.ok) {
    return { ok: false, action: 'failed', error: existingResult.error };
  }

  if (existingResult.customer?.id) {
    const customerId = Number(existingResult.customer.id);
    const syncResult = await syncCustomerAccount(customerId, groupId, apiOptions);
    if (!syncResult.ok) {
      return { ok: false, action: 'failed', error: syncResult.error };
    }

    return {
      ok: true,
      action: 'existing',
      customer_id: customerId,
      group_id: groupId,
      inactive: syncResult.inactive,
      locked: syncResult.locked,
    };
  }

  const createResult = await adobeCommerce.createCustomer(payloadResult.customer, apiOptions);
  if (!createResult.ok) {
    return { ok: false, action: 'failed', error: createResult.error };
  }

  const customerId = Number(createResult.customer?.id ?? 0);
  if (!customerId) {
    return { ok: false, action: 'failed', error: 'ACCS did not return a customer id.' };
  }

  const syncResult = await syncCustomerAccount(customerId, groupId, apiOptions);
  if (!syncResult.ok) {
    return { ok: false, action: 'failed', error: syncResult.error };
  }

  return {
    ok: true,
    action: 'created',
    customer_id: customerId,
    group_id: groupId,
    inactive: syncResult.inactive,
    locked: syncResult.locked,
  };
}

async function run(options = {}) {
  const configError = adobeCommerce.configError();
  if (configError) {
    return { ok: false, error: configError };
  }

  const apiEnvironment = targetEnvironment();
  if (!ALLOWED_TARGET_ENVS.has(apiEnvironment)) {
    return {
      ok: false,
      error: `Employee ACCS customer provisioning supports stage or production. Got "${apiEnvironment}".`,
    };
  }

  const dryRun = Boolean(options.dryRun);
  const delayMs = envInt('ACCS_EMPLOYEE_CUSTOMER_DELAY_MS', 200);
  const apiOptions = { environment: apiEnvironment };

  let pool;
  try {
    pool = await connectPool(getProductionDatabase());
  } catch (error) {
    return { ok: false, error: error.message };
  }

  try {
    const employees = await loadEmployees(pool, apiEnvironment, options);
    let created = 0;
    let existing = 0;
    let groupUpdated = 0;
    let failed = 0;
    let skipped = 0;
    const failures = [];

    for (const employee of employees) {
      if (employee.AccsCustomerId && !options.includeExisting && !options.fixGroupsOnly) {
        skipped += 1;
        continue;
      }

      const groupId = resolveGroupIdFromEmployee(employee, apiEnvironment);
      if (!groupId) {
        failed += 1;
        const error = `Unsupported group Group1="${employee.Group1 ?? ''}" Group2="${employee.Group2 ?? ''}".`;
        failures.push({
          employee_list_id: employee.EmployeeListID,
          email: employee.Email,
          error,
        });
        if (!dryRun) {
          await markEmployeeFailure(pool, apiEnvironment, employee.EmployeeListID, error);
        }
        continue;
      }

      if (dryRun) {
        skipped += 1;
        continue;
      }

      const result = await ensureAccsCustomer(employee, apiOptions, options);
      if (!result.ok) {
        failed += 1;
        failures.push({
          employee_list_id: employee.EmployeeListID,
          email: employee.Email,
          error: result.error,
        });
        await markEmployeeFailure(pool, apiEnvironment, employee.EmployeeListID, result.error);
        if (delayMs > 0) {
          await sleep(delayMs);
        }
        continue;
      }

      await markEmployeeSuccess(pool, apiEnvironment, employee.EmployeeListID, result.customer_id);
      if (result.action === 'created') {
        created += 1;
      } else if (result.action === 'group_updated') {
        groupUpdated += 1;
      } else if (result.action === 'unchanged') {
        skipped += 1;
      } else {
        existing += 1;
      }

      if (delayMs > 0) {
        await sleep(delayMs);
      }
    }

    if (failed > 0 && created === 0 && existing === 0 && groupUpdated === 0 && !dryRun) {
      return {
        ok: false,
        error: failures[0]?.error || 'ACCS employee customer create failed.',
        target_environment: apiEnvironment,
        candidates: employees.length,
        created,
        existing,
        group_updated: groupUpdated,
        failed,
        skipped,
        group_map: loadGroupMap(apiEnvironment),
        start_inactive: shouldStartInactive(),
        start_locked: shouldStartLocked(),
        failures,
      };
    }

    return {
      ok: true,
      error: null,
      target_environment: apiEnvironment,
      dry_run: dryRun,
      candidates: employees.length,
      created,
      existing,
      group_updated: groupUpdated,
      failed,
      skipped,
      group_map: loadGroupMap(apiEnvironment),
      start_inactive: shouldStartInactive(),
      start_locked: shouldStartLocked(),
      failures,
    };
  } finally {
    await pool.close();
  }
}

module.exports = {
  run,
};
