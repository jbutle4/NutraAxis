const { sql } = require('./db-config');
const { mapDetailLines } = require('./accs-order-mapper');

const TERMINAL_ORDER_STATUSES = new Set(['complete', 'closed', 'canceled']);

function detailFingerprint(lines) {
  const normalized = (lines || [])
    .map((line) => ({
      id: Number(line.AccsItemId),
      sku: line.SKU ?? null,
      qty: [
        line.QtyOrdered,
        line.QtyShipped,
        line.QtyInvoiced,
        line.QtyCanceled,
        line.QtyRefunded,
        line.QtyReturned,
      ],
      updated: line.ItemUpdatedAt instanceof Date
        ? line.ItemUpdatedAt.toISOString()
        : (line.ItemUpdatedAt ? String(line.ItemUpdatedAt) : null),
      rowTotal: line.RowTotal,
      unitPrice: line.UnitPrice,
    }))
    .filter((line) => Number.isFinite(line.id) && line.id > 0)
    .sort((left, right) => left.id - right.id);

  return JSON.stringify(normalized);
}

function detailFingerprintFromOrder(order) {
  return detailFingerprint(mapDetailLines(order));
}

function detailsChanged(storedFingerprint, order) {
  if (!storedFingerprint) {
    return true;
  }

  return storedFingerprint !== detailFingerprintFromOrder(order);
}

async function loadDetailFingerprints(pool, sourceEnvironment, headerIds) {
  if (headerIds.length === 0) {
    return new Map();
  }

  const request = pool.request().input('sourceEnvironment', sql.NVarChar, sourceEnvironment);
  const placeholders = headerIds.map((headerId, index) => {
    const key = `header${index}`;
    request.input(key, sql.Int, headerId);
    return `@${key}`;
  });

  const result = await request.query(`
    SELECT
      d.AccsSalesOrderHeaderID,
      d.AccsItemId,
      d.SKU,
      d.QtyOrdered,
      d.QtyShipped,
      d.QtyInvoiced,
      d.QtyCanceled,
      d.QtyRefunded,
      d.QtyReturned,
      d.ItemUpdatedAt,
      d.RowTotal,
      d.UnitPrice
    FROM dbo.AccsSalesOrderDetail d
    WHERE d.SourceEnvironment = @sourceEnvironment
      AND d.AccsSalesOrderHeaderID IN (${placeholders.join(', ')})
    ORDER BY d.AccsSalesOrderHeaderID, d.AccsItemId
  `);

  const grouped = new Map();
  for (const row of result.recordset) {
    const headerId = Number(row.AccsSalesOrderHeaderID);
    if (!grouped.has(headerId)) {
      grouped.set(headerId, []);
    }

    grouped.get(headerId).push({
      AccsItemId: row.AccsItemId,
      SKU: row.SKU,
      QtyOrdered: row.QtyOrdered,
      QtyShipped: row.QtyShipped,
      QtyInvoiced: row.QtyInvoiced,
      QtyCanceled: row.QtyCanceled,
      QtyRefunded: row.QtyRefunded,
      QtyReturned: row.QtyReturned,
      ItemUpdatedAt: row.ItemUpdatedAt,
      RowTotal: row.RowTotal,
      UnitPrice: row.UnitPrice,
    });
  }

  const fingerprints = new Map();
  for (const [headerId, lines] of grouped.entries()) {
    fingerprints.set(headerId, detailFingerprint(lines));
  }

  return fingerprints;
}

async function loadOpenOrdersForDetailReconciliation(pool, sourceEnvironment, batchSize) {
  const result = await pool.request()
    .input('sourceEnvironment', sql.NVarChar, sourceEnvironment)
    .input('batchSize', sql.Int, batchSize)
    .query(`
      SELECT TOP (@batchSize)
        AccsSalesOrderHeaderID,
        AccsEntityId,
        IncrementId,
        OrderStatus,
        LastSyncedAt
      FROM dbo.AccsSalesOrderHeader
      WHERE SourceEnvironment = @sourceEnvironment
        AND OrderStatus NOT IN (N'complete', N'closed', N'canceled')
      ORDER BY LastSyncedAt ASC, AccsSalesOrderHeaderID ASC
    `);

  return result.recordset;
}

async function replaceDetailLines(transaction, headerId, sourceEnvironment, lines) {
  await new sql.Request(transaction)
    .input('headerId', sql.Int, headerId)
    .input('sourceEnvironment', sql.NVarChar(20), sourceEnvironment)
    .query(`
      DELETE FROM dbo.AccsSalesOrderDetail
      WHERE AccsSalesOrderHeaderID = @headerId
        AND SourceEnvironment = @sourceEnvironment
    `);

  for (const line of lines) {
    await new sql.Request(transaction)
      .input('AccsSalesOrderHeaderID', sql.Int, headerId)
      .input('AccsItemId', sql.Int, line.AccsItemId)
      .input('AccsOrderEntityId', sql.Int, line.AccsOrderEntityId)
      .input('LineNumber', sql.Int, line.LineNumber)
      .input('SKU', sql.NVarChar(100), line.SKU)
      .input('ProductName', sql.NVarChar(300), line.ProductName)
      .input('ProductId', sql.Int, line.ProductId)
      .input('ProductType', sql.NVarChar(50), line.ProductType)
      .input('Description', sql.NVarChar(500), line.Description)
      .input('QtyOrdered', sql.Decimal(18, 4), line.QtyOrdered)
      .input('QtyShipped', sql.Decimal(18, 4), line.QtyShipped)
      .input('QtyInvoiced', sql.Decimal(18, 4), line.QtyInvoiced)
      .input('QtyCanceled', sql.Decimal(18, 4), line.QtyCanceled)
      .input('QtyRefunded', sql.Decimal(18, 4), line.QtyRefunded)
      .input('QtyReturned', sql.Decimal(18, 4), line.QtyReturned)
      .input('OriginalPrice', sql.Decimal(18, 4), line.OriginalPrice)
      .input('UnitPrice', sql.Decimal(18, 4), line.UnitPrice)
      .input('UnitPriceInclTax', sql.Decimal(18, 4), line.UnitPriceInclTax)
      .input('RowTotal', sql.Decimal(18, 4), line.RowTotal)
      .input('RowTotalInclTax', sql.Decimal(18, 4), line.RowTotalInclTax)
      .input('RowInvoiced', sql.Decimal(18, 4), line.RowInvoiced)
      .input('DiscountAmount', sql.Decimal(18, 4), line.DiscountAmount)
      .input('DiscountPercent', sql.Decimal(9, 4), line.DiscountPercent)
      .input('TaxAmount', sql.Decimal(18, 4), line.TaxAmount)
      .input('TaxPercent', sql.Decimal(9, 4), line.TaxPercent)
      .input('BaseCost', sql.Decimal(18, 4), line.BaseCost)
      .input('Weight', sql.Decimal(18, 4), line.Weight)
      .input('RowWeight', sql.Decimal(18, 4), line.RowWeight)
      .input('IsVirtual', sql.Bit, line.IsVirtual ? 1 : 0)
      .input('IsQtyDecimal', sql.Bit, line.IsQtyDecimal ? 1 : 0)
      .input('FreeShipping', sql.Bit, line.FreeShipping ? 1 : 0)
      .input('FulfillmentAttr', sql.NVarChar(50), line.FulfillmentAttr)
      .input('SupplierCode', sql.NVarChar(50), line.SupplierCode)
      .input('ParentAccsItemId', sql.Int, line.ParentAccsItemId)
      .input('StoreId', sql.Int, line.StoreId)
      .input('ItemCreatedAt', sql.DateTime2, line.ItemCreatedAt)
      .input('ItemUpdatedAt', sql.DateTime2, line.ItemUpdatedAt)
      .input('SourceEnvironment', sql.NVarChar(20), line.SourceEnvironment)
      .query(`
        INSERT INTO dbo.AccsSalesOrderDetail (
          AccsSalesOrderHeaderID, AccsItemId, AccsOrderEntityId, LineNumber, SKU, ProductName, ProductId, ProductType,
          Description, QtyOrdered, QtyShipped, QtyInvoiced, QtyCanceled, QtyRefunded, QtyReturned,
          OriginalPrice, UnitPrice, UnitPriceInclTax, RowTotal, RowTotalInclTax, RowInvoiced,
          DiscountAmount, DiscountPercent, TaxAmount, TaxPercent, BaseCost, Weight, RowWeight,
          IsVirtual, IsQtyDecimal, FreeShipping, FulfillmentAttr, SupplierCode, ParentAccsItemId,
          StoreId, ItemCreatedAt, ItemUpdatedAt, SourceEnvironment, LastSyncedAt
        )
        VALUES (
          @AccsSalesOrderHeaderID, @AccsItemId, @AccsOrderEntityId, @LineNumber, @SKU, @ProductName, @ProductId, @ProductType,
          @Description, @QtyOrdered, @QtyShipped, @QtyInvoiced, @QtyCanceled, @QtyRefunded, @QtyReturned,
          @OriginalPrice, @UnitPrice, @UnitPriceInclTax, @RowTotal, @RowTotalInclTax, @RowInvoiced,
          @DiscountAmount, @DiscountPercent, @TaxAmount, @TaxPercent, @BaseCost, @Weight, @RowWeight,
          @IsVirtual, @IsQtyDecimal, @FreeShipping, @FulfillmentAttr, @SupplierCode, @ParentAccsItemId,
          @StoreId, @ItemCreatedAt, @ItemUpdatedAt, @SourceEnvironment, SYSUTCDATETIME()
        )
      `);
  }
}

async function syncDetailsOnly(pool, headerId, order, sourceEnvironment) {
  const lines = mapDetailLines(order);
  const transaction = new sql.Transaction(pool);
  await transaction.begin();

  try {
    await replaceDetailLines(transaction, headerId, sourceEnvironment, lines);
    await new sql.Request(transaction)
      .input('headerId', sql.Int, headerId)
      .query(`
        UPDATE dbo.AccsSalesOrderHeader
        SET LastSyncedAt = SYSUTCDATETIME()
        WHERE AccsSalesOrderHeaderID = @headerId
      `);
    await transaction.commit();

    return {
      action: 'details_updated',
      header_id: headerId,
      line_count: lines.length,
    };
  } catch (error) {
    await transaction.rollback();
    throw error;
  }
}

function isTerminalOrderStatus(status) {
  return TERMINAL_ORDER_STATUSES.has(String(status ?? '').trim().toLowerCase());
}

module.exports = {
  TERMINAL_ORDER_STATUSES,
  detailFingerprint,
  detailFingerprintFromOrder,
  detailsChanged,
  isTerminalOrderStatus,
  loadDetailFingerprints,
  loadOpenOrdersForDetailReconciliation,
  replaceDetailLines,
  syncDetailsOnly,
};
