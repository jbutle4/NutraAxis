const { sql, connectPool, getProductionDatabase } = require('../db-config');
const jazzOms = require('../jazz-oms');

function normalizeRow(row) {
  const sku = String(row.sku_code ?? '').trim();
  if (!sku) {
    return null;
  }

  const facility = String(row.facility_code ?? '').trim();

  return {
    sku,
    facility: facility || null,
    available: Number(row.available_quantity ?? 0),
    on_hand: Number(row.on_hand_quantity ?? 0),
    ordered: Number(row.qty_ordered ?? 0),
    total: Number(row.total_quantity ?? 0),
  };
}

async function run(pool = null) {
  const snapshotAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

  const listResult = await jazzOms.listInventory();
  if (!listResult.ok) {
    return {
      ok: false,
      error: listResult.error,
      inserted: 0,
      snapshot_at: snapshotAt,
    };
  }

  const rows = listResult.rows ?? [];
  if (rows.length === 0) {
    return {
      ok: true,
      error: null,
      inserted: 0,
      snapshot_at: snapshotAt,
      message: 'No inventory rows returned from Jazz OMS.',
    };
  }

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const transaction = new sql.Transaction(db);

  try {
    await transaction.begin();

    let inserted = 0;

    for (const row of rows) {
      const normalized = normalizeRow(row);
      if (!normalized) {
        continue;
      }

      await new sql.Request(transaction)
        .input('snapshot_at', sql.DateTime2, snapshotAt)
        .input('sku', sql.NVarChar(200), normalized.sku)
        .input('facility', sql.NVarChar(100), normalized.facility)
        .input('available', sql.Decimal(18, 4), normalized.available)
        .input('on_hand', sql.Decimal(18, 4), normalized.on_hand)
        .input('ordered', sql.Decimal(18, 4), normalized.ordered)
        .input('total', sql.Decimal(18, 4), normalized.total)
        .query(`
          INSERT INTO dbo.InventoryBalance (
            SnapshotDateTime, SKU, FacilityCode,
            AvailableQuantity, OnHandQuantity, QtyOrdered, TotalQuantity
          )
          VALUES (
            @snapshot_at, @sku, @facility,
            @available, @on_hand, @ordered, @total
          )
        `);

      inserted += 1;
    }

    await transaction.commit();

    return {
      ok: true,
      error: null,
      inserted,
      snapshot_at: snapshotAt,
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
      snapshot_at: snapshotAt,
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
