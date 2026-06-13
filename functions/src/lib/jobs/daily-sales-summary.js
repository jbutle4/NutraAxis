const { sql, connectPool, getProductionDatabase } = require('../db-config');
const adobeCommerce = require('../adobe-commerce');

const TIMEZONE = 'America/Chicago';

function chicagoDateString(date = new Date()) {
  return new Intl.DateTimeFormat('en-CA', { timeZone: TIMEZONE }).format(date);
}

function addCalendarDays(year, month, day, delta) {
  const cursor = new Date(Date.UTC(year, month - 1, day + delta));

  return {
    year: cursor.getUTCFullYear(),
    month: cursor.getUTCMonth() + 1,
    day: cursor.getUTCDate(),
  };
}

function chicagoParts(date = new Date()) {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: TIMEZONE,
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
  }).formatToParts(date);

  return {
    year: Number(parts.find((part) => part.type === 'year').value),
    month: Number(parts.find((part) => part.type === 'month').value),
    day: Number(parts.find((part) => part.type === 'day').value),
  };
}

function defaultTargetDate(runAt = new Date()) {
  const today = chicagoParts(runAt);
  const yesterday = addCalendarDays(today.year, today.month, today.day, -1);

  return `${String(yesterday.year).padStart(4, '0')}-${String(yesterday.month).padStart(2, '0')}-${String(yesterday.day).padStart(2, '0')}`;
}

function parseTargetDate(value) {
  const trimmed = String(value ?? '').trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
    return null;
  }

  return trimmed;
}

function orderCreatedOnDate(order, summaryDate) {
  const created = String(order.created_at ?? '').trim();
  if (!created) {
    return false;
  }

  if (String(order.status ?? '').trim().toLowerCase() === 'canceled') {
    return false;
  }

  const createdAt = new Date(created);
  if (Number.isNaN(createdAt.getTime())) {
    return false;
  }

  return chicagoDateString(createdAt) === summaryDate;
}

async function fetchOrdersForDate(summaryDate) {
  const result = await adobeCommerce.fetchPaginatedOrders({
    'searchCriteria[sortOrders][0][field]': 'created_at',
    'searchCriteria[sortOrders][0][direction]': 'DESC',
  });

  if (!result.ok) {
    return result;
  }

  const orders = result.rows.filter((order) => orderCreatedOnDate(order, summaryDate));

  return {
    ok: true,
    error: null,
    rows: orders,
    total: orders.length,
  };
}

function itemDescription(item) {
  const direct = String(item.description ?? '').trim();
  if (direct) {
    return direct;
  }

  const extension = item.extension_attributes;
  if (extension && typeof extension === 'object') {
    const extended = String(extension.description ?? '').trim();
    if (extended) {
      return extended;
    }
  }

  return '';
}

function aggregateOrders(orders) {
  const bySku = {};

  for (const order of orders) {
    if (!order || typeof order !== 'object') {
      continue;
    }

    for (const item of order.items ?? []) {
      if (!item || typeof item !== 'object') {
        continue;
      }

      const sku = String(item.sku ?? '').trim();
      if (!sku) {
        continue;
      }

      const qty = Number(item.qty_ordered ?? 0);
      if (qty <= 0) {
        continue;
      }

      if (!bySku[sku]) {
        bySku[sku] = {
          sku,
          sku_name: String(item.name ?? '').trim(),
          sku_desc: itemDescription(item),
          qty_sold: 0,
        };
      }

      if (!bySku[sku].sku_name && item.name) {
        bySku[sku].sku_name = String(item.name).trim();
      }

      if (!bySku[sku].sku_desc) {
        bySku[sku].sku_desc = itemDescription(item);
      }

      bySku[sku].qty_sold += qty;
    }
  }

  return Object.keys(bySku).sort().map((sku) => bySku[sku]);
}

async function run(options = {}, pool = null) {
  const capturedAt = new Date().toISOString().slice(0, 19).replace('T', ' ');
  const summaryDate = parseTargetDate(options.date) || defaultTargetDate();

  const configError = adobeCommerce.configError();
  if (configError) {
    return {
      ok: false,
      error: configError,
      inserted: 0,
      summary_date: summaryDate,
      summary_capture_date: capturedAt,
      orders: 0,
    };
  }

  const ordersResult = await fetchOrdersForDate(summaryDate);
  if (!ordersResult.ok) {
    return {
      ok: false,
      error: adobeCommerce.normalizeError(ordersResult.error, 'Unable to fetch Adobe Commerce orders.'),
      inserted: 0,
      summary_date: summaryDate,
      summary_capture_date: capturedAt,
      orders: 0,
    };
  }

  const orders = ordersResult.rows ?? [];
  const rows = aggregateOrders(orders);

  const owned = pool === null;
  const db = pool || await connectPool(getProductionDatabase());
  const transaction = new sql.Transaction(db);

  try {
    await transaction.begin();

    await new sql.Request(transaction)
      .input('summary_date', sql.Date, summaryDate)
      .query('DELETE FROM dbo.DailySalesSummary WHERE SummaryDate = @summary_date');

    let inserted = 0;

    for (const row of rows) {
      await new sql.Request(transaction)
        .input('summary_date', sql.Date, summaryDate)
        .input('sku', sql.NVarChar(200), row.sku)
        .input('sku_name', sql.NVarChar(500), row.sku_name || null)
        .input('sku_desc', sql.NVarChar(sql.MAX), row.sku_desc || null)
        .input('qty_sold', sql.Decimal(18, 4), row.qty_sold)
        .input('capture_date', sql.DateTime2, capturedAt)
        .query(`
          INSERT INTO dbo.DailySalesSummary (
            SummaryDate, SKU, SKUName, SKUDescription, QtySold, SummaryCaptureDate
          )
          VALUES (
            @summary_date, @sku, @sku_name, @sku_desc, @qty_sold, @capture_date
          )
        `);

      inserted += 1;
    }

    await transaction.commit();

    return {
      ok: true,
      error: null,
      inserted,
      summary_date: summaryDate,
      summary_capture_date: capturedAt,
      orders: orders.length,
      message: inserted === 0 ? 'No SKU sales found for the summary date.' : null,
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
      summary_date: summaryDate,
      summary_capture_date: capturedAt,
      orders: orders.length,
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
