const { sql, connectPool, getSyncSettings, getProductionDatabase } = require('./db-config');
const qboConfig = require('./qbo-config');

function stagingDatabase() {
  // Portal stores sandbox QBO tokens on nutraaxis. Prefer that over nutraaxis_test
  // unless an explicit staging DB override is configured and reachable.
  const explicit = process.env.DB_NAME_STAGING
    || process.env.DB_NAME_STAGE
    || process.env.DB_NAME_QBO
    || process.env.DB_NAME_INVENTORY_SYNC
    || '';
  if (String(explicit).trim() !== '') {
    return String(explicit).trim();
  }

  return getProductionDatabase() || 'nutraaxis';
}

function createConnectionStore(resolveDatabase) {
  const resolve = typeof resolveDatabase === 'function'
    ? resolveDatabase
    : () => resolveDatabase;

  async function getConnection(pool = null) {
    const owned = pool === null;
    const database = resolve();
    const db = pool || await connectPool(database);

    try {
      const result = await db.request()
        .input('environment', sql.NVarChar(20), qboConfig.environment())
        .query(`
          SELECT TOP (1)
            ConnectionID,
            RealmID,
            CompanyName,
            AccessToken,
            RefreshToken,
            AccessTokenExpiresAt,
            Environment
          FROM dbo.QBOConnection
          WHERE Environment = @environment
          ORDER BY ConnectionID DESC
        `);

      return result.recordset[0] ?? null;
    } finally {
      if (owned) {
        await db.close();
      }
    }
  }

  async function saveConnection(data, pool = null) {
    const owned = pool === null;
    const database = resolve();
    const db = pool || await connectPool(database);
    const environment = qboConfig.environment();
    const connectedBy = Number(process.env.QBO_CONNECTED_BY_USER_ID || 1);

    try {
      await db.request()
        .input('environment', sql.NVarChar(20), environment)
        .query('DELETE FROM dbo.QBOConnection WHERE Environment = @environment');

      await db.request()
        .input('realm', sql.NVarChar(32), data.realm_id)
        .input('company', sql.NVarChar(255), data.company_name ?? null)
        .input('access', sql.NVarChar(sql.MAX), data.access_token)
        .input('refresh', sql.NVarChar(sql.MAX), data.refresh_token)
        .input('expires', sql.DateTime2, data.access_token_expires_at)
        .input('environment', sql.NVarChar(20), environment)
        .input('user', sql.Int, connectedBy)
        .query(`
          INSERT INTO dbo.QBOConnection (
            RealmID, CompanyName, AccessToken, RefreshToken, AccessTokenExpiresAt,
            Environment, ConnectedByUser, ConnectedAt, UpdatedAt
          )
          VALUES (
            @realm, @company, @access, @refresh, @expires,
            @environment, @user, SYSUTCDATETIME(), SYSUTCDATETIME()
          )
        `);
    } finally {
      if (owned) {
        await db.close();
      }
    }
  }

  async function disconnect(pool = null) {
    const owned = pool === null;
    const database = resolve();
    const db = pool || await connectPool(database);

    try {
      await db.request()
        .input('environment', sql.NVarChar(20), qboConfig.environment())
        .query('DELETE FROM dbo.QBOConnection WHERE Environment = @environment');
    } finally {
      if (owned) {
        await db.close();
      }
    }
  }

  return {
    get database() {
      return resolve();
    },
    getConnection,
    saveConnection,
    disconnect,
  };
}

const stagingStore = createConnectionStore(stagingDatabase);
const productionStore = createConnectionStore(getProductionDatabase);

module.exports = {
  getConnection: (pool) => stagingStore.getConnection(pool),
  saveConnection: (data) => stagingStore.saveConnection(data),
  disconnect: () => stagingStore.disconnect(),
  staging: stagingStore,
  production: productionStore,
};
