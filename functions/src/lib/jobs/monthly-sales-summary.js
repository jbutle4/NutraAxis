const { sql, connectPool, getProductionDatabase } = require('../db-config');

async function run(pool = null) {
  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const updatedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

  const transaction = new sql.Transaction(db);

  try {
    await transaction.begin();
    const request = new sql.Request(transaction);

    await request.query('DELETE FROM dbo.MonthlySalesSummary');

    const insertResult = await request
      .input('updated_at', sql.DateTime2, updatedAt)
      .query(`
        INSERT INTO dbo.MonthlySalesSummary (
          SKU, SaleYear, SaleMonth, MonthStart, TotalQty, LastUpdatedAt
        )
        SELECT
          dss.SKU,
          YEAR(dss.SummaryDate) AS SaleYear,
          MONTH(dss.SummaryDate) AS SaleMonth,
          DATEFROMPARTS(YEAR(dss.SummaryDate), MONTH(dss.SummaryDate), 1) AS MonthStart,
          SUM(dss.QtySold) AS TotalQty,
          @updated_at AS LastUpdatedAt
        FROM dbo.DailySalesSummary dss
        GROUP BY
          dss.SKU,
          YEAR(dss.SummaryDate),
          MONTH(dss.SummaryDate),
          DATEFROMPARTS(YEAR(dss.SummaryDate), MONTH(dss.SummaryDate), 1)
      `);

    await transaction.commit();

    const inserted = insertResult.rowsAffected.reduce((sum, count) => sum + count, 0);

    return {
      ok: true,
      error: null,
      inserted,
      updated_at: updatedAt,
      message: inserted === 0 ? 'No daily sales rows found to roll up.' : null,
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
      inserted: 0,
      updated_at: updatedAt,
    };
  } finally {
    if (owned) {
      await db.close();
    }
  }
}

module.exports = {
  run,
};
