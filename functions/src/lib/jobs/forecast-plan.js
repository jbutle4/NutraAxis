const { sql, connectPool, getProductionDatabase } = require('../db-config');

const TIMEZONE = 'America/Chicago';
const HORIZON_MONTHS = 12;
const TREND_MIN = 0.7;
const TREND_MAX = 1.3;

function monthKey(year, month) {
  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}`;
}

function chicagoYearMonth(date = new Date()) {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TIMEZONE,
    year: 'numeric',
    month: 'numeric',
  }).formatToParts(date);

  return {
    year: Number(parts.find((part) => part.type === 'year').value),
    month: Number(parts.find((part) => part.type === 'month').value),
  };
}

function addMonths(year, month, delta) {
  const cursor = new Date(Date.UTC(year, month - 1 + delta, 1));

  return {
    year: cursor.getUTCFullYear(),
    month: cursor.getUTCMonth() + 1,
  };
}

function monthStartDate(year, month) {
  return new Date(Date.UTC(year, month - 1, 1));
}

function currentMonthStart(runAt = new Date()) {
  return chicagoYearMonth(runAt);
}

function horizonMonths(startMonth) {
  const months = [];
  let { year, month } = startMonth;

  for (let i = 0; i < HORIZON_MONTHS; i += 1) {
    months.push({
      year,
      month,
      start: monthStartDate(year, month),
    });

    ({ year, month } = addMonths(year, month, 1));
  }

  return months;
}

function monthWeight(monthRank) {
  if (monthRank === 1) return 3.0;
  if (monthRank === 2) return 2.5;
  if (monthRank === 3) return 2.0;
  if (monthRank <= 6) return 1.5;
  return 1.0;
}

function shortageFlag(endOh) {
  return endOh < 0 ? 1 : 0;
}

async function runStep(step, callback) {
  try {
    return await callback();
  } catch (error) {
    throw new Error(`Forecast plan step "${step}" failed (mssql): ${error.message}`);
  }
}

async function loadMonthlySales(request) {
  const result = await request.query(`
    SELECT
      CAST(SKU AS NVARCHAR(200)) AS SKU,
      SaleYear,
      SaleMonth,
      MonthStart,
      TotalQty
    FROM dbo.MonthlySalesSummary
    ORDER BY CAST(SKU AS NVARCHAR(200)), MonthStart DESC
  `);

  const bySku = {};

  for (const row of result.recordset) {
    const sku = String(row.SKU ?? '').trim();
    if (!sku) {
      continue;
    }

    if (!bySku[sku]) {
      bySku[sku] = [];
    }

    bySku[sku].push({
      year: Number(row.SaleYear),
      month: Number(row.SaleMonth),
      month_key: monthKey(Number(row.SaleYear), Number(row.SaleMonth)),
      qty: Number(row.TotalQty ?? 0),
    });
  }

  return bySku;
}

function computeModel(history, currentMonthStartValue) {
  const cutoffMonth = addMonths(currentMonthStartValue.year, currentMonthStartValue.month, -12);
  const cutoff = monthStartDate(cutoffMonth.year, cutoffMonth.month);
  const currentStart = monthStartDate(currentMonthStartValue.year, currentMonthStartValue.month);

  const filtered = history.filter((row) => {
    const monthStart = monthStartDate(row.year, row.month);
    return monthStart >= cutoff && monthStart < currentStart;
  });

  filtered.sort((a, b) => (a.month_key < b.month_key ? 1 : a.month_key > b.month_key ? -1 : 0));

  if (filtered.length === 0) {
    return {
      baseline_avg: 0,
      trend_factor: 1,
      monthly_sales: 0,
    };
  }

  let weightedSum = 0;
  let weightTotal = 0;
  const recent = [];
  const prior = [];

  filtered.forEach((row, index) => {
    const rank = index + 1;
    const weight = monthWeight(rank);
    weightedSum += row.qty * weight;
    weightTotal += weight;

    if (rank <= 3) {
      recent.push(row.qty);
    } else if (rank <= 6) {
      prior.push(row.qty);
    }
  });

  const baseline = weightTotal > 0 ? weightedSum / weightTotal : 0;
  const recentAvg = recent.length > 0 ? recent.reduce((sum, qty) => sum + qty, 0) / recent.length : 0;
  const priorAvg = prior.length > 0 ? prior.reduce((sum, qty) => sum + qty, 0) / prior.length : 0;

  let trend = 1;
  if (priorAvg > 0 && recentAvg > 0) {
    trend = recentAvg / priorAvg;
    trend = Math.max(TREND_MIN, Math.min(TREND_MAX, trend));
  }

  const monthlySales = baseline * trend;

  return {
    baseline_avg: Math.round(baseline * 10000) / 10000,
    trend_factor: Math.round(trend * 10000) / 10000,
    monthly_sales: Math.round(monthlySales * 10000) / 10000,
  };
}

async function loadPlannedReceipts(request) {
  const result = await request.query(`
    SELECT
      CAST(li.ItemSKU AS NVARCHAR(200)) AS SKU,
      YEAR(po.ExpectedDeliveryDate) AS ReceiptYear,
      MONTH(po.ExpectedDeliveryDate) AS ReceiptMonth,
      SUM(li.Quantity - li.QuantityReceived) AS PlannedQty
    FROM dbo.POLineItem li
    INNER JOIN dbo.PurchaseOrder po ON po.POID = li.POID
    WHERE CAST(po.POStatus AS NVARCHAR(100)) = N'Approved'
      AND po.ExpectedDeliveryDate IS NOT NULL
      AND li.ItemSKU IS NOT NULL
      AND LEN(LTRIM(RTRIM(CAST(li.ItemSKU AS NVARCHAR(200))))) > 0
      AND li.Quantity > li.QuantityReceived
    GROUP BY CAST(li.ItemSKU AS NVARCHAR(200)), YEAR(po.ExpectedDeliveryDate), MONTH(po.ExpectedDeliveryDate)
  `);

  const bySku = {};

  for (const row of result.recordset) {
    const sku = String(row.SKU ?? '').trim();
    if (!sku) {
      continue;
    }

    if (!bySku[sku]) {
      bySku[sku] = {};
    }

    bySku[sku][monthKey(Number(row.ReceiptYear), Number(row.ReceiptMonth))] = Number(row.PlannedQty ?? 0);
  }

  return bySku;
}

async function loadBeginningInventory(request) {
  const result = await request.query(`
    WITH LatestSnapshot AS (
      SELECT MAX(SnapshotDateTime) AS SnapshotDateTime
      FROM dbo.InventoryBalance
    )
    SELECT
      CAST(ib.SKU AS NVARCHAR(200)) AS SKU,
      SUM(ib.AvailableQuantity) AS BeginOH
    FROM dbo.InventoryBalance ib
    INNER JOIN LatestSnapshot ls ON ls.SnapshotDateTime = ib.SnapshotDateTime
    GROUP BY CAST(ib.SKU AS NVARCHAR(200))
  `);

  const inventory = {};

  for (const row of result.recordset) {
    const sku = String(row.SKU ?? '').trim();
    if (!sku) {
      continue;
    }

    inventory[sku] = Number(row.BeginOH ?? 0);
  }

  return inventory;
}

async function loadLockedKeys(request) {
  const result = await request.query(`
    SELECT
      CAST(SKU AS NVARCHAR(200)) AS SKU,
      PlanYear,
      PlanMonth
    FROM dbo.ForecastPlan
    WHERE IsLocked = 1
  `);

  const locked = {};

  for (const row of result.recordset) {
    const sku = String(row.SKU ?? '').trim();
    if (!sku) {
      continue;
    }

    if (!locked[sku]) {
      locked[sku] = {};
    }

    locked[sku][monthKey(Number(row.PlanYear), Number(row.PlanMonth))] = true;
  }

  return locked;
}

async function lockCompletedMonths(transaction, currentMonthStartValue) {
  const priorMonth = addMonths(currentMonthStartValue.year, currentMonthStartValue.month, -1);
  const { year, month } = priorMonth;

  const salesResult = await new sql.Request(transaction)
    .input('year', sql.Int, year)
    .input('month', sql.Int, month)
    .query(`
      SELECT CAST(SKU AS NVARCHAR(200)) AS SKU, TotalQty
      FROM dbo.MonthlySalesSummary
      WHERE SaleYear = @year AND SaleMonth = @month
    `);

  const salesBySku = {};
  for (const row of salesResult.recordset) {
    const sku = String(row.SKU ?? '').trim();
    if (sku) {
      salesBySku[sku] = Number(row.TotalQty ?? 0);
    }
  }

  const receiptsResult = await new sql.Request(transaction)
    .input('year', sql.Int, year)
    .input('month', sql.Int, month)
    .query(`
      SELECT
        CAST(pd.ItemSKU AS NVARCHAR(200)) AS SKU,
        SUM(pd.QuantityReceived) AS ActualReceipts
      FROM dbo.PORDetail pd
      INNER JOIN dbo.POReceipt pr ON pr.PORID = pd.PORID
      WHERE CAST(pr.PORStatus AS NVARCHAR(60)) = N'Complete'
        AND pr.ActualReceiptDate IS NOT NULL
        AND YEAR(pr.ActualReceiptDate) = @year
        AND MONTH(pr.ActualReceiptDate) = @month
        AND pd.ItemSKU IS NOT NULL
      GROUP BY CAST(pd.ItemSKU AS NVARCHAR(200))
    `);

  const receiptsBySku = {};
  for (const row of receiptsResult.recordset) {
    const sku = String(row.SKU ?? '').trim();
    if (sku) {
      receiptsBySku[sku] = Number(row.ActualReceipts ?? 0);
    }
  }

  const inventory = await loadBeginningInventory(new sql.Request(transaction));
  const allSkus = [...new Set([
    ...Object.keys(salesBySku),
    ...Object.keys(receiptsBySku),
    ...Object.keys(inventory),
  ])];

  let locked = 0;

  for (const sku of allSkus) {
    const actualSales = salesBySku[sku] ?? 0;
    const actualReceipts = receiptsBySku[sku] ?? 0;
    const actualBegin = inventory[sku] ?? 0;
    const actualEnd = actualBegin + actualReceipts - actualSales;
    const flag = shortageFlag(actualEnd);

    const updateResult = await new sql.Request(transaction)
      .input('actual_sales', sql.Decimal(18, 4), actualSales)
      .input('actual_receipts', sql.Decimal(18, 4), actualReceipts)
      .input('actual_begin_oh', sql.Decimal(18, 4), actualBegin)
      .input('actual_end_oh', sql.Decimal(18, 4), actualEnd)
      .input('shortage_flag', sql.Bit, flag)
      .input('sku', sql.NVarChar(200), sku)
      .input('year', sql.Int, year)
      .input('month', sql.Int, month)
      .query(`
        UPDATE dbo.ForecastPlan
        SET
          ActualSales = @actual_sales,
          ActualReceipts = @actual_receipts,
          ActualBeginOH = @actual_begin_oh,
          ActualEndOH = @actual_end_oh,
          ShortageFlag = @shortage_flag,
          IsLocked = 1,
          GeneratedAt = SYSUTCDATETIME()
        WHERE CAST(SKU AS NVARCHAR(200)) = CAST(@sku AS NVARCHAR(200))
          AND PlanYear = @year
          AND PlanMonth = @month
          AND IsLocked = 0
      `);

    locked += updateResult.rowsAffected.reduce((sum, count) => sum + count, 0);

    if (updateResult.rowsAffected.reduce((sum, count) => sum + count, 0) === 0) {
      const insertResult = await new sql.Request(transaction)
        .input('sku', sql.NVarChar(200), sku)
        .input('year', sql.Int, year)
        .input('month', sql.Int, month)
        .input('actual_begin_oh', sql.Decimal(18, 4), actualBegin)
        .input('actual_receipts', sql.Decimal(18, 4), actualReceipts)
        .input('actual_sales', sql.Decimal(18, 4), actualSales)
        .input('actual_end_oh', sql.Decimal(18, 4), actualEnd)
        .input('shortage_flag', sql.Bit, flag)
        .query(`
          INSERT INTO dbo.ForecastPlan (
            SKU, PlanYear, PlanMonth,
            ActualBeginOH, ActualReceipts, ActualSales, ActualEndOH,
            ShortageFlag, IsLocked, GeneratedAt
          )
          SELECT
            CAST(@sku AS NVARCHAR(200)), @year, @month,
            @actual_begin_oh, @actual_receipts, @actual_sales, @actual_end_oh,
            @shortage_flag, 1, SYSUTCDATETIME()
          WHERE NOT EXISTS (
            SELECT 1
            FROM dbo.ForecastPlan
            WHERE CAST(SKU AS NVARCHAR(200)) = CAST(@sku AS NVARCHAR(200))
              AND PlanYear = @year
              AND PlanMonth = @month
          )
        `);

      locked += insertResult.rowsAffected.reduce((sum, count) => sum + count, 0);
    }
  }

  return locked;
}

async function run(pool = null) {
  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const runAt = new Date();
  const currentMonthStartValue = currentMonthStart(runAt);
  const horizon = horizonMonths(currentMonthStartValue);
  const generatedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');

  const transaction = new sql.Transaction(db);

  try {
    await transaction.begin();
    const request = new sql.Request(transaction);

    const lockedMonths = await runStep('lock_completed_months', () => lockCompletedMonths(transaction, currentMonthStartValue));
    const monthlySales = await runStep('load_monthly_sales', () => loadMonthlySales(request));
    const plannedReceipts = await runStep('load_planned_receipts', () => loadPlannedReceipts(request));
    const beginInventory = await runStep('load_beginning_inventory', () => loadBeginningInventory(request));
    const lockedKeys = await runStep('load_locked_keys', () => loadLockedKeys(request));

    const skuSet = [...new Set([
      ...Object.keys(monthlySales),
      ...Object.keys(plannedReceipts),
      ...Object.keys(beginInventory),
    ])].sort();

    let inserted = 0;

    for (const sku of skuSet) {
      const history = monthlySales[sku] ?? [];
      const model = computeModel(history, currentMonthStartValue);
      let endingOh = beginInventory[sku] ?? 0;

      for (const monthInfo of horizon) {
        const { year, month } = monthInfo;
        const key = monthKey(year, month);

        if (lockedKeys[sku]?.[key]) {
          continue;
        }

        await runStep('delete_forecast_row', async () => {
          await new sql.Request(transaction)
            .input('sku', sql.NVarChar(200), sku)
            .input('year', sql.Int, year)
            .input('month', sql.Int, month)
            .query(`
              DELETE FROM dbo.ForecastPlan
              WHERE CAST(SKU AS NVARCHAR(200)) = CAST(@sku AS NVARCHAR(200))
                AND PlanYear = @year
                AND PlanMonth = @month
                AND IsLocked = 0
            `);
        });

        const forecastSales = model.monthly_sales;
        const forecastReceipts = plannedReceipts[sku]?.[key] ?? 0;
        const forecastBegin = endingOh;
        const forecastEnd = forecastBegin + forecastReceipts - forecastSales;

        await runStep('insert_forecast_row', async () => {
          await new sql.Request(transaction)
            .input('sku', sql.NVarChar(200), sku)
            .input('year', sql.Int, year)
            .input('month', sql.Int, month)
            .input('forecast_begin_oh', sql.Decimal(18, 4), forecastBegin)
            .input('forecast_receipts', sql.Decimal(18, 4), forecastReceipts)
            .input('forecast_sales', sql.Decimal(18, 4), forecastSales)
            .input('forecast_end_oh', sql.Decimal(18, 4), forecastEnd)
            .input('baseline_avg', sql.Decimal(18, 4), model.baseline_avg)
            .input('trend_factor', sql.Decimal(18, 4), model.trend_factor)
            .input('shortage_flag', sql.Bit, shortageFlag(forecastEnd))
            .input('generated_at', sql.DateTime2, generatedAt)
            .query(`
              INSERT INTO dbo.ForecastPlan (
                SKU, PlanYear, PlanMonth,
                ForecastBeginOH, ForecastReceipts, ForecastSales, ForecastEndOH,
                BaselineAvg, TrendFactor, ShortageFlag, GeneratedAt, IsLocked
              )
              VALUES (
                CAST(@sku AS NVARCHAR(200)), @year, @month,
                @forecast_begin_oh, @forecast_receipts, @forecast_sales, @forecast_end_oh,
                @baseline_avg, @trend_factor, @shortage_flag, @generated_at, 0
              )
            `);
        });

        inserted += 1;
        endingOh = forecastEnd;
      }
    }

    await transaction.commit();

    return {
      ok: true,
      error: null,
      inserted,
      skus: skuSet.length,
      locked_months: lockedMonths,
      generated_at: generatedAt,
      horizon_start: monthKey(currentMonthStartValue.year, currentMonthStartValue.month) + '-01',
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
      skus: 0,
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
