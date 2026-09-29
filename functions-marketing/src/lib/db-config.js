const sql = require('mssql');

function envValue(...keys) {
  for (const key of keys) {
    const value = process.env[key];
    if (value !== undefined && value !== '') {
      return value;
    }
  }
  return null;
}

function getSqlConfig(database) {
  const server = envValue('DB_SERVER', 'DB_HOST');
  const user = envValue('DB_USER');
  const password = envValue('DB_PASSWORD', 'DB_PASS');
  if (!server || !user || !password || !database) {
    throw new Error('Database settings are incomplete. Set DB_SERVER, DB_USER, DB_PASSWORD, and DB_NAME_PRODUCTION.');
  }

  return {
    server,
    database,
    user,
    password,
    port: Number(envValue('DB_PORT') || 1433),
    options: {
      encrypt: true,
      trustServerCertificate: false,
      connectTimeout: 15000,
      requestTimeout: 600000,
    },
    pool: { max: 5, min: 0, idleTimeoutMillis: 30000 },
  };
}

async function connectPool(database) {
  return new sql.ConnectionPool(getSqlConfig(database)).connect();
}

function getProductionDatabase() {
  return envValue('DB_NAME_PRODUCTION', 'DB_NAME_PROD', 'DB_NAME') || 'nutraaxis';
}

module.exports = {
  sql,
  connectPool,
  getProductionDatabase,
};
