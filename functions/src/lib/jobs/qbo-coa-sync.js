const { sql, connectPool, getProductionDatabase } = require('../db-config');
const qboClient = require('../qbo-client');
const qboConnection = require('../qbo-connection');

const PAGE_SIZE = 500;
const productionStore = qboConnection.production;

function formatDateTime(value) {
  if (!value) {
    return null;
  }

  return String(value).replace('T', ' ').slice(0, 19);
}

function normalizeAccount(account, realmId, syncedAt) {
  const accountId = String(account?.Id ?? '').trim();
  if (!accountId) {
    return null;
  }

  const parentRef = account.ParentRef ?? {};
  const currencyRef = account.CurrencyRef ?? {};
  const meta = account.MetaData ?? {};

  return {
    realm_id: realmId,
    qbo_account_id: accountId,
    qbo_sync_token: account.SyncToken != null ? String(account.SyncToken) : null,
    name: String(account.Name ?? '').trim() || 'Unnamed account',
    acct_num: account.AcctNum != null && String(account.AcctNum).trim() !== ''
      ? String(account.AcctNum).trim()
      : null,
    fully_qualified_name: account.FullyQualifiedName != null ? String(account.FullyQualifiedName) : null,
    account_type: account.AccountType != null ? String(account.AccountType) : null,
    account_sub_type: account.AccountSubType != null ? String(account.AccountSubType) : null,
    classification: account.Classification != null ? String(account.Classification) : null,
    current_balance: account.CurrentBalance != null ? Number(account.CurrentBalance) : null,
    current_balance_with_sub: account.CurrentBalanceWithSubAccounts != null
      ? Number(account.CurrentBalanceWithSubAccounts)
      : null,
    active: account.Active === false ? 0 : 1,
    description: account.Description != null ? String(account.Description) : null,
    currency_ref_value: currencyRef.value != null ? String(currencyRef.value) : null,
    currency_ref_name: currencyRef.name != null ? String(currencyRef.name) : null,
    parent_ref_value: parentRef.value != null ? String(parentRef.value) : null,
    parent_ref_name: parentRef.name != null ? String(parentRef.name) : null,
    qbo_last_updated_at: formatDateTime(meta.LastUpdatedTime),
    synced_at: syncedAt,
  };
}

async function listAllAccounts() {
  const rows = [];
  let start = 1;

  while (true) {
    const result = await qboClient.query(
      `SELECT * FROM Account ORDERBY Name STARTPOSITION ${start} MAXRESULTS ${PAGE_SIZE}`,
      PAGE_SIZE,
      productionStore
    );

    if (!result.ok) {
      return result;
    }

    const batch = qboClient.extractQueryRows(result.data, ['Account']);
    if (batch.length === 0) {
      break;
    }

    rows.push(...batch);
    if (batch.length < PAGE_SIZE) {
      break;
    }

    start += PAGE_SIZE;
  }

  return { ok: true, error: null, rows };
}

async function upsertAccount(transaction, row) {
  await new sql.Request(transaction)
    .input('realm_id', sql.NVarChar(32), row.realm_id)
    .input('qbo_account_id', sql.NVarChar(32), row.qbo_account_id)
    .input('qbo_sync_token', sql.NVarChar(32), row.qbo_sync_token)
    .input('name', sql.NVarChar(255), row.name)
    .input('acct_num', sql.NVarChar(50), row.acct_num)
    .input('fully_qualified_name', sql.NVarChar(500), row.fully_qualified_name)
    .input('account_type', sql.NVarChar(50), row.account_type)
    .input('account_sub_type', sql.NVarChar(100), row.account_sub_type)
    .input('classification', sql.NVarChar(50), row.classification)
    .input('current_balance', sql.Decimal(18, 2), row.current_balance)
    .input('current_balance_with_sub', sql.Decimal(18, 2), row.current_balance_with_sub)
    .input('active', sql.Bit, row.active)
    .input('description', sql.NVarChar(1000), row.description)
    .input('currency_ref_value', sql.NVarChar(10), row.currency_ref_value)
    .input('currency_ref_name', sql.NVarChar(50), row.currency_ref_name)
    .input('parent_ref_value', sql.NVarChar(32), row.parent_ref_value)
    .input('parent_ref_name', sql.NVarChar(255), row.parent_ref_name)
    .input('qbo_last_updated_at', sql.DateTime2, row.qbo_last_updated_at)
    .input('synced_at', sql.DateTime2, row.synced_at)
    .query(`
      MERGE dbo.QBO_COA AS target
      USING (
        SELECT
          @realm_id AS RealmID,
          @qbo_account_id AS QBO_AccountId
      ) AS source
      ON target.RealmID = source.RealmID
     AND target.QBO_AccountId = source.QBO_AccountId
      WHEN MATCHED THEN
        UPDATE SET
          QBO_SyncToken = @qbo_sync_token,
          Name = @name,
          AcctNum = @acct_num,
          FullyQualifiedName = @fully_qualified_name,
          AccountType = @account_type,
          AccountSubType = @account_sub_type,
          Classification = @classification,
          CurrentBalance = @current_balance,
          CurrentBalanceWithSubAccounts = @current_balance_with_sub,
          Active = @active,
          Description = @description,
          CurrencyRefValue = @currency_ref_value,
          CurrencyRefName = @currency_ref_name,
          ParentRefValue = @parent_ref_value,
          ParentRefName = @parent_ref_name,
          QBO_LastUpdatedAt = @qbo_last_updated_at,
          SyncedAt = @synced_at,
          ModifiedDate = SYSUTCDATETIME()
      WHEN NOT MATCHED THEN
        INSERT (
          RealmID, QBO_AccountId, QBO_SyncToken, Name, AcctNum, FullyQualifiedName,
          AccountType, AccountSubType, Classification,
          CurrentBalance, CurrentBalanceWithSubAccounts, Active, Description,
          CurrencyRefValue, CurrencyRefName, ParentRefValue, ParentRefName,
          QBO_LastUpdatedAt, SyncedAt
        )
        VALUES (
          @realm_id, @qbo_account_id, @qbo_sync_token, @name, @acct_num, @fully_qualified_name,
          @account_type, @account_sub_type, @classification,
          @current_balance, @current_balance_with_sub, @active, @description,
          @currency_ref_value, @currency_ref_name, @parent_ref_value, @parent_ref_name,
          @qbo_last_updated_at, @synced_at
        );
    `);
}

async function run(pool = null) {
  const syncedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

  const connection = await productionStore.getConnection();
  if (!connection) {
    return {
      ok: false,
      error: 'QuickBooks is not connected. Connect QuickBooks in Accounting before syncing the chart of accounts.',
      synced: 0,
      deactivated: 0,
      synced_at: syncedAt,
    };
  }

  const realmId = String(connection.RealmID);
  const listResult = await listAllAccounts();
  if (!listResult.ok) {
    return {
      ok: false,
      error: listResult.error,
      synced: 0,
      deactivated: 0,
      synced_at: syncedAt,
      realm_id: realmId,
    };
  }

  const accounts = listResult.rows ?? [];
  if (accounts.length === 0) {
    return {
      ok: true,
      error: null,
      synced: 0,
      deactivated: 0,
      synced_at: syncedAt,
      realm_id: realmId,
      message: 'QuickBooks returned no chart of accounts rows.',
    };
  }

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const transaction = new sql.Transaction(db);

  try {
    await transaction.begin();

    let synced = 0;
    for (const account of accounts) {
      const normalized = normalizeAccount(account, realmId, syncedAt);
      if (!normalized) {
        continue;
      }

      await upsertAccount(transaction, normalized);
      synced += 1;
    }

    const deactivateResult = await new sql.Request(transaction)
      .input('realm_id', sql.NVarChar(32), realmId)
      .input('synced_at', sql.DateTime2, syncedAt)
      .query(`
        UPDATE dbo.QBO_COA
        SET Active = 0,
            ModifiedDate = SYSUTCDATETIME()
        WHERE RealmID = @realm_id
          AND SyncedAt < @synced_at
      `);

    await transaction.commit();

    return {
      ok: true,
      error: null,
      synced,
      deactivated: deactivateResult.rowsAffected?.[0] ?? 0,
      synced_at: syncedAt,
      realm_id: realmId,
    };
  } catch (error) {
    try {
      await transaction.rollback();
    } catch {
      // Ignore rollback errors after a failed begin/query.
    }

    return {
      ok: false,
      error: error.message,
      synced: 0,
      deactivated: 0,
      synced_at: syncedAt,
      realm_id: realmId,
    };
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

module.exports = {
  run,
  listAllAccounts,
  normalizeAccount,
};
