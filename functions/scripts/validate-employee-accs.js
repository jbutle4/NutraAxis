/**
 * Validate EmployeeList ACCS provisioning in stage and production.
 *
 * Usage (from functions/): node scripts/validate-employee-accs.js
 */
const fs = require('fs');
const path = require('path');

function loadEnvFile(envPath) {
  if (!fs.existsSync(envPath)) {
    throw new Error(`Missing env file: ${envPath}`);
  }

  const content = fs.readFileSync(envPath, 'utf8');
  for (const rawLine of content.split('\n')) {
    const line = rawLine.trim();
    if (!line || line.startsWith('#')) {
      continue;
    }

    const eq = line.indexOf('=');
    if (eq <= 0) {
      continue;
    }

    const key = line.slice(0, eq).trim();
    let value = line.slice(eq + 1).trim();
    if (
      (value.startsWith('"') && value.endsWith('"'))
      || (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    if (process.env[key] === undefined) {
      process.env[key] = value;
    }
  }
}

loadEnvFile(path.resolve(__dirname, '../../.env'));

const { connectPool, getProductionDatabase } = require('../src/lib/db-config');
const adobeCommerce = require('../src/lib/adobe-commerce');
const {
  defaultGroupMapForEnvironment,
  resolveGroupIdFromEmployee,
} = require('../src/lib/accs-customer-groups');

const ENVIRONMENTS = ['stage', 'production'];
const DELAY_MS = Number(process.env.ACCS_VALIDATE_DELAY_MS || 100);

function sleep(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, ms);
  });
}

function normalizeEmail(email) {
  return String(email ?? '').trim().toLowerCase();
}

function employeeName(employee) {
  return `${String(employee.FirstName ?? '').trim()} ${String(employee.LastName ?? '').trim()}`.trim();
}

async function querySqlSummary(pool) {
  const result = await pool.request().query(`
    SELECT
      COUNT(*) AS total_rows,
      SUM(CASE WHEN FirstEmail = 1 THEN 1 ELSE 0 END) AS first_email_rows,
      SUM(CASE WHEN FirstEmail IS NULL OR FirstEmail <> 1 THEN 1 ELSE 0 END) AS secondary_email_rows,
      SUM(CASE WHEN FirstEmail = 1 AND AccsStageCustomerId IS NOT NULL THEN 1 ELSE 0 END) AS stage_id_set,
      SUM(CASE WHEN FirstEmail = 1 AND AccsStageCustomerId IS NULL THEN 1 ELSE 0 END) AS stage_id_missing,
      SUM(CASE WHEN FirstEmail = 1 AND AccsStageCustomerLastError IS NOT NULL THEN 1 ELSE 0 END) AS stage_errors,
      SUM(CASE WHEN FirstEmail = 1 AND AccsProdCustomerId IS NOT NULL THEN 1 ELSE 0 END) AS prod_id_set,
      SUM(CASE WHEN FirstEmail = 1 AND AccsProdCustomerId IS NULL THEN 1 ELSE 0 END) AS prod_id_missing,
      SUM(CASE WHEN FirstEmail = 1 AND AccsProdCustomerLastError IS NOT NULL THEN 1 ELSE 0 END) AS prod_errors,
      SUM(CASE WHEN (FirstEmail IS NULL OR FirstEmail <> 1) AND AccsStageCustomerId IS NOT NULL THEN 1 ELSE 0 END) AS secondary_stage_id_set,
      SUM(CASE WHEN (FirstEmail IS NULL OR FirstEmail <> 1) AND AccsProdCustomerId IS NOT NULL THEN 1 ELSE 0 END) AS secondary_prod_id_set
    FROM dbo.EmployeeList
  `);

  return result.recordset[0];
}

async function loadEmployees(pool) {
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
      AccsStageCustomerId,
      AccsStageCustomerCreatedAt,
      AccsStageCustomerLastError,
      AccsProdCustomerId,
      AccsProdCustomerCreatedAt,
      AccsProdCustomerLastError
    FROM dbo.EmployeeList
    ORDER BY EmployeeListID ASC
  `);

  return result.recordset;
}

function trackingForEnv(employee, envName) {
  if (envName === 'production') {
    return {
      customerId: employee.AccsProdCustomerId,
      createdAt: employee.AccsProdCustomerCreatedAt,
      lastError: employee.AccsProdCustomerLastError,
    };
  }

  return {
    customerId: employee.AccsStageCustomerId,
    createdAt: employee.AccsStageCustomerCreatedAt,
    lastError: employee.AccsStageCustomerLastError,
  };
}

async function verifyEmployeeInEnv(employee, envName) {
  const apiOptions = { environment: envName };
  const tracking = trackingForEnv(employee, envName);
  const sqlCustomerId = Number(tracking.customerId);
  const expectedGroupId = resolveGroupIdFromEmployee(employee, envName);
  const email = normalizeEmail(employee.Email);

  const issues = [];
  let byIdCustomer = null;
  let byEmailCustomer = null;

  if (!expectedGroupId) {
    issues.push({
      type: 'unsupported_group',
      detail: `Group1="${employee.Group1 ?? ''}" Group2="${employee.Group2 ?? ''}"`,
    });
  }

  if (Number.isFinite(sqlCustomerId) && sqlCustomerId > 0) {
    const byIdResult = await adobeCommerce.fetchCustomerById(sqlCustomerId, apiOptions);
    if (DELAY_MS > 0) {
      await sleep(DELAY_MS);
    }

    if (!byIdResult.ok) {
      issues.push({
        type: 'sql_id_not_found',
        detail: byIdResult.error,
        sql_customer_id: sqlCustomerId,
      });
    } else {
      byIdCustomer = byIdResult.customer;
      if (normalizeEmail(byIdCustomer.email) !== email) {
        issues.push({
          type: 'sql_id_email_mismatch',
          detail: `SQL id ${sqlCustomerId} maps to ${byIdCustomer.email}, expected ${employee.Email}`,
          sql_customer_id: sqlCustomerId,
          accs_email: byIdCustomer.email,
        });
      }
    }
  } else {
    issues.push({
      type: 'sql_id_missing',
      detail: 'No customer id stored in SQL for this environment.',
    });
  }

  const byEmailResult = await adobeCommerce.searchCustomersByEmail(employee.Email, apiOptions);
  if (DELAY_MS > 0) {
    await sleep(DELAY_MS);
  }

  if (!byEmailResult.ok) {
    issues.push({
      type: 'email_lookup_failed',
      detail: byEmailResult.error,
    });
  } else if (!byEmailResult.customer?.id) {
    issues.push({
      type: 'email_not_found',
      detail: `No ACCS customer found for ${employee.Email}`,
    });
  } else {
    byEmailCustomer = byEmailResult.customer;
    const emailCustomerId = Number(byEmailCustomer.id);

    if (Number.isFinite(sqlCustomerId) && sqlCustomerId > 0 && emailCustomerId !== sqlCustomerId) {
      issues.push({
        type: 'id_mismatch',
        detail: `SQL id ${sqlCustomerId} does not match email lookup id ${emailCustomerId}`,
        sql_customer_id: sqlCustomerId,
        email_customer_id: emailCustomerId,
      });
    }
  }

  const accsCustomer = byEmailCustomer || byIdCustomer;
  if (accsCustomer && expectedGroupId) {
    const actualGroupId = Number(accsCustomer.group_id);
    if (actualGroupId !== expectedGroupId) {
      issues.push({
        type: 'wrong_group',
        detail: `Expected group ${expectedGroupId}, found ${actualGroupId}`,
        expected_group_id: expectedGroupId,
        actual_group_id: actualGroupId,
        group1: employee.Group1,
        group2: employee.Group2,
      });
    }
  }

  const fatalTypes = new Set([
    'unsupported_group',
    'sql_id_not_found',
    'sql_id_missing',
    'email_not_found',
    'email_lookup_failed',
    'id_mismatch',
    'sql_id_email_mismatch',
    'wrong_group',
  ]);

  const hasFatalIssue = issues.some((issue) => fatalTypes.has(issue.type));
  const accountExists = Boolean(byEmailCustomer?.id || byIdCustomer?.id);

  return {
    environment: envName,
    sql_customer_id: Number.isFinite(sqlCustomerId) && sqlCustomerId > 0 ? sqlCustomerId : null,
    sql_last_error: tracking.lastError || null,
    expected_group_id: expectedGroupId,
    accs_customer_id: accsCustomer ? Number(accsCustomer.id) : null,
    accs_group_id: accsCustomer ? Number(accsCustomer.group_id) : null,
    account_exists: accountExists,
    ok: accountExists && !hasFatalIssue,
    issues,
  };
}

async function verifyEmployees(primaryEmployees) {
  const envResults = {
    stage: {
      checked: 0,
      ok: 0,
      missing_accounts: [],
      wrong_groups: [],
      id_mismatches: [],
      other_issues: [],
    },
    production: {
      checked: 0,
      ok: 0,
      missing_accounts: [],
      wrong_groups: [],
      id_mismatches: [],
      other_issues: [],
    },
  };

  for (let index = 0; index < primaryEmployees.length; index += 1) {
    const employee = primaryEmployees[index];
    const label = `${employee.EmployeeListID} ${employeeName(employee)} <${employee.Email}>`;

    for (const envName of ENVIRONMENTS) {
      const result = await verifyEmployeeInEnv(employee, envName);
      const bucket = envResults[envName];
      bucket.checked += 1;

      if (result.ok) {
        bucket.ok += 1;
      }

      const entry = {
        employee_list_id: employee.EmployeeListID,
        name: employeeName(employee),
        email: employee.Email,
        company: employee.Company,
        group1: employee.Group1,
        group2: employee.Group2,
        sql_customer_id: result.sql_customer_id,
        sql_last_error: result.sql_last_error,
        accs_customer_id: result.accs_customer_id,
        expected_group_id: result.expected_group_id,
        accs_group_id: result.accs_group_id,
        issues: result.issues,
      };

      if (!result.account_exists) {
        bucket.missing_accounts.push(entry);
      }

      for (const issue of result.issues) {
        if (issue.type === 'wrong_group') {
          bucket.wrong_groups.push({ ...entry, issue });
        } else if (issue.type === 'id_mismatch' || issue.type === 'sql_id_email_mismatch') {
          bucket.id_mismatches.push({ ...entry, issue });
        } else if (!result.ok) {
          bucket.other_issues.push({ ...entry, issue });
        }
      }
    }

    if ((index + 1) % 25 === 0 || index + 1 === primaryEmployees.length) {
      process.stderr.write(`Checked ${index + 1}/${primaryEmployees.length} primary employees...\n`);
    }
  }

  return envResults;
}

function summarizeSecondaryEmployees(employees) {
  const secondary = employees.filter((row) => row.FirstEmail !== 1);
  const withStageId = secondary.filter((row) => row.AccsStageCustomerId);
  const withProdId = secondary.filter((row) => row.AccsProdCustomerId);
  const withGroup = secondary.filter((row) => row.Group1 || row.Group2);

  return {
    count: secondary.length,
    with_group_values: withGroup.length,
    with_stage_customer_id: withStageId.length,
    with_prod_customer_id: withProdId.length,
    expected_to_skip: true,
    rationale: 'Provisioning job filters WHERE FirstEmail = 1; secondary emails are alternate addresses for the same person.',
    sample: secondary.slice(0, 10).map((row) => ({
      employee_list_id: row.EmployeeListID,
      name: employeeName(row),
      email: row.Email,
      first_email: row.FirstEmail,
      group1: row.Group1,
      group2: row.Group2,
    })),
  };
}

async function main() {
  const configError = adobeCommerce.configError();
  if (configError) {
    throw new Error(configError);
  }

  const pool = await connectPool(getProductionDatabase());
  try {
    const sqlSummary = await querySqlSummary(pool);
    const employees = await loadEmployees(pool);
    const primaryEmployees = employees.filter((row) => row.FirstEmail === 1);
    const secondarySummary = summarizeSecondaryEmployees(employees);

    process.stderr.write(`SQL loaded: ${employees.length} rows, verifying ${primaryEmployees.length} primary employees in ACCS...\n`);
    const accsResults = await verifyEmployees(primaryEmployees);

    const report = {
      generated_at: new Date().toISOString(),
      sql: {
        total_rows: Number(sqlSummary.total_rows),
        first_email_rows: Number(sqlSummary.first_email_rows),
        secondary_email_rows: Number(sqlSummary.secondary_email_rows),
        stage: {
          customer_id_set: Number(sqlSummary.stage_id_set),
          customer_id_missing: Number(sqlSummary.stage_id_missing),
          rows_with_last_error: Number(sqlSummary.stage_errors),
        },
        production: {
          customer_id_set: Number(sqlSummary.prod_id_set),
          customer_id_missing: Number(sqlSummary.prod_id_missing),
          rows_with_last_error: Number(sqlSummary.prod_errors),
        },
      },
      secondary_emails: secondarySummary,
      group_maps: {
        stage: defaultGroupMapForEnvironment('stage'),
        production: defaultGroupMapForEnvironment('production'),
      },
      accs_validation: accsResults,
      conclusion: {
        stage_pass: accsResults.stage.missing_accounts.length === 0
          && accsResults.stage.wrong_groups.length === 0
          && accsResults.stage.id_mismatches.length === 0
          && accsResults.stage.other_issues.length === 0,
        production_pass: accsResults.production.missing_accounts.length === 0
          && accsResults.production.wrong_groups.length === 0
          && accsResults.production.id_mismatches.length === 0
          && accsResults.production.other_issues.length === 0,
      },
    };

    report.conclusion.all_accounts_created = report.conclusion.stage_pass && report.conclusion.production_pass;

    console.log(JSON.stringify(report, null, 2));
  } finally {
    await pool.close();
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
