const { connectPool, getProductionDatabase } = require('../db-config');
const { loadSettings } = require('../mkt/settings');

async function run() {
  const pool = await connectPool(getProductionDatabase());
  try {
    const settings = await loadSettings(pool);
    return {
      ok: true,
      error: null,
      settings: Object.keys(settings).length,
    };
  } finally {
    await pool.close();
  }
}

module.exports = { run };
