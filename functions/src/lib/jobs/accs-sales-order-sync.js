const { sql, connectPool, getProductionDatabase } = require('../db-config');
const adobeCommerce = require('../adobe-commerce');
const {
  sourceEnvironmentLabel,
  mapHeader,
  mapDetailLines,
  parseDateTime,
} = require('../accs-order-mapper');
const {
  detailsChanged,
  loadDetailFingerprints,
  loadOpenOrdersForDetailReconciliation,
  replaceDetailLines,
  syncDetailsOnly,
} = require('../accs-order-detail-sync');

function envInt(name, fallback) {
  const value = Number(process.env[name]);
  return Number.isFinite(value) && value > 0 ? value : fallback;
}

function formatAccsDateTime(date) {
  const pad = (num) => String(num).padStart(2, '0');
  return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())} `
    + `${pad(date.getUTCHours())}:${pad(date.getUTCMinutes())}:${pad(date.getUTCSeconds())}`;
}

function overlapDate(date, minutes) {
  return new Date(date.getTime() - minutes * 60 * 1000);
}

async function loadExistingSyncState(pool, sourceEnvironment) {
  const result = await pool.request()
    .input('sourceEnvironment', sql.NVarChar, sourceEnvironment)
    .query(`
      SELECT TOP (1)
        AccsSalesOrderHeaderID,
        AccsEntityId,
        OrderUpdatedAt,
        LastSyncedAt
      FROM dbo.AccsSalesOrderHeader
      WHERE SourceEnvironment = @sourceEnvironment
      ORDER BY ISNULL(OrderUpdatedAt, OrderCreatedAt) DESC, AccsSalesOrderHeaderID DESC
    `);

  return result.recordset[0] ?? null;
}

async function loadExistingOrderMap(pool, sourceEnvironment, entityIds) {
  if (entityIds.length === 0) {
    return new Map();
  }

  const request = pool.request().input('sourceEnvironment', sql.NVarChar, sourceEnvironment);
  const placeholders = entityIds.map((entityId, index) => {
    const key = `entity${index}`;
    request.input(key, sql.Int, entityId);
    return `@${key}`;
  });

  const result = await request.query(`
    SELECT AccsSalesOrderHeaderID, AccsEntityId, OrderUpdatedAt
    FROM dbo.AccsSalesOrderHeader
    WHERE SourceEnvironment = @sourceEnvironment
      AND AccsEntityId IN (${placeholders.join(', ')})
  `);

  return new Map(result.recordset.map((row) => [Number(row.AccsEntityId), row]));
}

function headerIsUnchanged(existingRow, order) {
  if (!existingRow) {
    return false;
  }

  const apiUpdated = parseDateTime(order.updated_at);
  const storedUpdated = existingRow.OrderUpdatedAt ? new Date(existingRow.OrderUpdatedAt) : null;

  if (apiUpdated && storedUpdated && apiUpdated.getTime() === storedUpdated.getTime()) {
    return true;
  }

  return false;
}

async function fetchOrdersUpdatedSince(updatedSince, maxPages) {
  const query = {
    'searchCriteria[filter_groups][0][filters][0][field]': 'updated_at',
    'searchCriteria[filter_groups][0][filters][0][value]': formatAccsDateTime(updatedSince),
    'searchCriteria[filter_groups][0][filters][0][condition_type]': 'gte',
    'searchCriteria[sortOrders][0][field]': 'updated_at',
    'searchCriteria[sortOrders][0][direction]': 'ASC',
  };

  return adobeCommerce.fetchPaginatedOrders(query, maxPages);
}

async function upsertHeader(transaction, header) {
  const existing = await new sql.Request(transaction)
    .input('AccsEntityId', sql.Int, header.AccsEntityId)
    .input('SourceEnvironment', sql.NVarChar(20), header.SourceEnvironment)
    .query(`
      SELECT AccsSalesOrderHeaderID
      FROM dbo.AccsSalesOrderHeader
      WHERE AccsEntityId = @AccsEntityId
        AND SourceEnvironment = @SourceEnvironment
    `);

  const request = new sql.Request(transaction)
    .input('AccsEntityId', sql.Int, header.AccsEntityId)
    .input('IncrementId', sql.NVarChar(50), header.IncrementId)
    .input('AccsState', sql.NVarChar(50), header.AccsState)
    .input('OrderStatus', sql.NVarChar(50), header.OrderStatus)
    .input('OrderCreatedAt', sql.DateTime2, header.OrderCreatedAt)
    .input('OrderUpdatedAt', sql.DateTime2, header.OrderUpdatedAt)
    .input('CustomerId', sql.Int, header.CustomerId)
    .input('CustomerEmail', sql.NVarChar(254), header.CustomerEmail)
    .input('CustomerFirstName', sql.NVarChar(100), header.CustomerFirstName)
    .input('CustomerLastName', sql.NVarChar(100), header.CustomerLastName)
    .input('CustomerGroupId', sql.Int, header.CustomerGroupId)
    .input('CustomerIsGuest', sql.Bit, header.CustomerIsGuest ? 1 : 0)
    .input('StoreId', sql.Int, header.StoreId)
    .input('StoreName', sql.NVarChar(300), header.StoreName)
    .input('OrderCurrencyCode', sql.NVarChar(3), header.OrderCurrencyCode)
    .input('BaseCurrencyCode', sql.NVarChar(3), header.BaseCurrencyCode)
    .input('Subtotal', sql.Decimal(18, 4), header.Subtotal)
    .input('SubtotalInclTax', sql.Decimal(18, 4), header.SubtotalInclTax)
    .input('ShippingAmount', sql.Decimal(18, 4), header.ShippingAmount)
    .input('ShippingInclTax', sql.Decimal(18, 4), header.ShippingInclTax)
    .input('ShippingDescription', sql.NVarChar(500), header.ShippingDescription)
    .input('ShippingTaxAmount', sql.Decimal(18, 4), header.ShippingTaxAmount)
    .input('TaxAmount', sql.Decimal(18, 4), header.TaxAmount)
    .input('DiscountAmount', sql.Decimal(18, 4), header.DiscountAmount)
    .input('GrandTotal', sql.Decimal(18, 4), header.GrandTotal)
    .input('TotalDue', sql.Decimal(18, 4), header.TotalDue)
    .input('TotalPaid', sql.Decimal(18, 4), header.TotalPaid)
    .input('TotalInvoiced', sql.Decimal(18, 4), header.TotalInvoiced)
    .input('TotalRefunded', sql.Decimal(18, 4), header.TotalRefunded)
    .input('TotalOnlineRefunded', sql.Decimal(18, 4), header.TotalOnlineRefunded)
    .input('Weight', sql.Decimal(18, 4), header.Weight)
    .input('TotalQtyOrdered', sql.Decimal(18, 4), header.TotalQtyOrdered)
    .input('TotalItemCount', sql.Int, header.TotalItemCount)
    .input('PaymentMethod', sql.NVarChar(100), header.PaymentMethod)
    .input('BillingAddressId', sql.Int, header.BillingAddressId)
    .input('BillFirstName', sql.NVarChar(100), header.BillFirstName)
    .input('BillLastName', sql.NVarChar(100), header.BillLastName)
    .input('BillCompany', sql.NVarChar(200), header.BillCompany)
    .input('BillStreet1', sql.NVarChar(200), header.BillStreet1)
    .input('BillStreet2', sql.NVarChar(200), header.BillStreet2)
    .input('BillCity', sql.NVarChar(100), header.BillCity)
    .input('BillRegion', sql.NVarChar(100), header.BillRegion)
    .input('BillRegionCode', sql.NVarChar(20), header.BillRegionCode)
    .input('BillPostcode', sql.NVarChar(20), header.BillPostcode)
    .input('BillCountryId', sql.NVarChar(10), header.BillCountryId)
    .input('BillTelephone', sql.NVarChar(50), header.BillTelephone)
    .input('BillEmail', sql.NVarChar(254), header.BillEmail)
    .input('ShippingAddressId', sql.Int, header.ShippingAddressId)
    .input('ShipFirstName', sql.NVarChar(100), header.ShipFirstName)
    .input('ShipLastName', sql.NVarChar(100), header.ShipLastName)
    .input('ShipCompany', sql.NVarChar(200), header.ShipCompany)
    .input('ShipStreet1', sql.NVarChar(200), header.ShipStreet1)
    .input('ShipStreet2', sql.NVarChar(200), header.ShipStreet2)
    .input('ShipCity', sql.NVarChar(100), header.ShipCity)
    .input('ShipRegion', sql.NVarChar(100), header.ShipRegion)
    .input('ShipRegionCode', sql.NVarChar(20), header.ShipRegionCode)
    .input('ShipPostcode', sql.NVarChar(20), header.ShipPostcode)
    .input('ShipCountryId', sql.NVarChar(10), header.ShipCountryId)
    .input('ShipTelephone', sql.NVarChar(50), header.ShipTelephone)
    .input('ShipEmail', sql.NVarChar(254), header.ShipEmail)
    .input('QuoteId', sql.Int, header.QuoteId)
    .input('RemoteIp', sql.NVarChar(45), header.RemoteIp)
    .input('IsVirtual', sql.Bit, header.IsVirtual ? 1 : 0)
    .input('EmailSent', sql.Bit, header.EmailSent ? 1 : 0)
    .input('SourceEnvironment', sql.NVarChar(20), header.SourceEnvironment)
    .input('RawPayloadJson', sql.NVarChar(sql.MAX), header.RawPayloadJson);

  if (existing.recordset[0]) {
    const headerId = Number(existing.recordset[0].AccsSalesOrderHeaderID);
    await request
      .input('AccsSalesOrderHeaderID', sql.Int, headerId)
      .query(`
        UPDATE dbo.AccsSalesOrderHeader
        SET IncrementId = @IncrementId,
            AccsState = @AccsState,
            OrderStatus = @OrderStatus,
            OrderCreatedAt = @OrderCreatedAt,
            OrderUpdatedAt = @OrderUpdatedAt,
            CustomerId = @CustomerId,
            CustomerEmail = @CustomerEmail,
            CustomerFirstName = @CustomerFirstName,
            CustomerLastName = @CustomerLastName,
            CustomerGroupId = @CustomerGroupId,
            CustomerIsGuest = @CustomerIsGuest,
            StoreId = @StoreId,
            StoreName = @StoreName,
            OrderCurrencyCode = @OrderCurrencyCode,
            BaseCurrencyCode = @BaseCurrencyCode,
            Subtotal = @Subtotal,
            SubtotalInclTax = @SubtotalInclTax,
            ShippingAmount = @ShippingAmount,
            ShippingInclTax = @ShippingInclTax,
            ShippingDescription = @ShippingDescription,
            ShippingTaxAmount = @ShippingTaxAmount,
            TaxAmount = @TaxAmount,
            DiscountAmount = @DiscountAmount,
            GrandTotal = @GrandTotal,
            TotalDue = @TotalDue,
            TotalPaid = @TotalPaid,
            TotalInvoiced = @TotalInvoiced,
            TotalRefunded = @TotalRefunded,
            TotalOnlineRefunded = @TotalOnlineRefunded,
            Weight = @Weight,
            TotalQtyOrdered = @TotalQtyOrdered,
            TotalItemCount = @TotalItemCount,
            PaymentMethod = @PaymentMethod,
            BillingAddressId = @BillingAddressId,
            BillFirstName = @BillFirstName,
            BillLastName = @BillLastName,
            BillCompany = @BillCompany,
            BillStreet1 = @BillStreet1,
            BillStreet2 = @BillStreet2,
            BillCity = @BillCity,
            BillRegion = @BillRegion,
            BillRegionCode = @BillRegionCode,
            BillPostcode = @BillPostcode,
            BillCountryId = @BillCountryId,
            BillTelephone = @BillTelephone,
            BillEmail = @BillEmail,
            ShippingAddressId = @ShippingAddressId,
            ShipFirstName = @ShipFirstName,
            ShipLastName = @ShipLastName,
            ShipCompany = @ShipCompany,
            ShipStreet1 = @ShipStreet1,
            ShipStreet2 = @ShipStreet2,
            ShipCity = @ShipCity,
            ShipRegion = @ShipRegion,
            ShipRegionCode = @ShipRegionCode,
            ShipPostcode = @ShipPostcode,
            ShipCountryId = @ShipCountryId,
            ShipTelephone = @ShipTelephone,
            ShipEmail = @ShipEmail,
            QuoteId = @QuoteId,
            RemoteIp = @RemoteIp,
            IsVirtual = @IsVirtual,
            EmailSent = @EmailSent,
            RawPayloadJson = @RawPayloadJson,
            LastSyncedAt = SYSUTCDATETIME()
        WHERE AccsSalesOrderHeaderID = @AccsSalesOrderHeaderID
      `);

    return headerId;
  }

  const insertResult = await request.query(`
    INSERT INTO dbo.AccsSalesOrderHeader (
      AccsEntityId, IncrementId, AccsState, OrderStatus, OrderCreatedAt, OrderUpdatedAt,
      CustomerId, CustomerEmail, CustomerFirstName, CustomerLastName, CustomerGroupId, CustomerIsGuest,
      StoreId, StoreName, OrderCurrencyCode, BaseCurrencyCode,
      Subtotal, SubtotalInclTax, ShippingAmount, ShippingInclTax, ShippingDescription, ShippingTaxAmount,
      TaxAmount, DiscountAmount, GrandTotal, TotalDue, TotalPaid, TotalInvoiced, TotalRefunded, TotalOnlineRefunded,
      Weight, TotalQtyOrdered, TotalItemCount, PaymentMethod,
      BillingAddressId, BillFirstName, BillLastName, BillCompany, BillStreet1, BillStreet2, BillCity, BillRegion,
      BillRegionCode, BillPostcode, BillCountryId, BillTelephone, BillEmail,
      ShippingAddressId, ShipFirstName, ShipLastName, ShipCompany, ShipStreet1, ShipStreet2, ShipCity, ShipRegion,
      ShipRegionCode, ShipPostcode, ShipCountryId, ShipTelephone, ShipEmail,
      QuoteId, RemoteIp, IsVirtual, EmailSent, SourceEnvironment, RawPayloadJson, ImportedAt, LastSyncedAt
    )
    OUTPUT INSERTED.AccsSalesOrderHeaderID AS header_id
    VALUES (
      @AccsEntityId, @IncrementId, @AccsState, @OrderStatus, @OrderCreatedAt, @OrderUpdatedAt,
      @CustomerId, @CustomerEmail, @CustomerFirstName, @CustomerLastName, @CustomerGroupId, @CustomerIsGuest,
      @StoreId, @StoreName, @OrderCurrencyCode, @BaseCurrencyCode,
      @Subtotal, @SubtotalInclTax, @ShippingAmount, @ShippingInclTax, @ShippingDescription, @ShippingTaxAmount,
      @TaxAmount, @DiscountAmount, @GrandTotal, @TotalDue, @TotalPaid, @TotalInvoiced, @TotalRefunded, @TotalOnlineRefunded,
      @Weight, @TotalQtyOrdered, @TotalItemCount, @PaymentMethod,
      @BillingAddressId, @BillFirstName, @BillLastName, @BillCompany, @BillStreet1, @BillStreet2, @BillCity, @BillRegion,
      @BillRegionCode, @BillPostcode, @BillCountryId, @BillTelephone, @BillEmail,
      @ShippingAddressId, @ShipFirstName, @ShipLastName, @ShipCompany, @ShipStreet1, @ShipStreet2, @ShipCity, @ShipRegion,
      @ShipRegionCode, @ShipPostcode, @ShipCountryId, @ShipTelephone, @ShipEmail,
      @QuoteId, @RemoteIp, @IsVirtual, @EmailSent, @SourceEnvironment, @RawPayloadJson, SYSUTCDATETIME(), SYSUTCDATETIME()
    )
  `);

  const headerId = Number(insertResult.recordset[0]?.header_id ?? 0);
  if (!headerId) {
    throw new Error(`Unable to insert ACCS order header ${header.IncrementId}.`);
  }

  return headerId;
}

async function upsertOrder(pool, order) {
  const header = mapHeader(order);
  if (!header || !header.OrderCreatedAt) {
    return { action: 'skipped', reason: 'invalid_header' };
  }

  const lines = mapDetailLines(order);
  const transaction = new sql.Transaction(pool);
  await transaction.begin();

  try {
    const headerId = await upsertHeader(transaction, header);
    await replaceDetailLines(transaction, headerId, header.SourceEnvironment, lines);
    await transaction.commit();

    return { action: 'upserted', header_id: headerId, increment_id: header.IncrementId };
  } catch (error) {
    await transaction.rollback();
    throw error;
  }
}

async function reconcileOpenOrderDetails(pool, sourceEnvironment, batchSize) {
  if (batchSize <= 0) {
    return {
      candidates: 0,
      detail_updated: 0,
      detail_unchanged: 0,
      detail_failed: 0,
      failures: [],
    };
  }

  const candidates = await loadOpenOrdersForDetailReconciliation(pool, sourceEnvironment, batchSize);
  const headerIds = candidates.map((row) => Number(row.AccsSalesOrderHeaderID));
  const storedFingerprints = await loadDetailFingerprints(pool, sourceEnvironment, headerIds);

  let detailUpdated = 0;
  let detailUnchanged = 0;
  let detailFailed = 0;
  const failures = [];

  for (const candidate of candidates) {
    const entityId = Number(candidate.AccsEntityId);
    const headerId = Number(candidate.AccsSalesOrderHeaderID);
    const fetchResult = await adobeCommerce.fetchOrderByEntityId(entityId);

    if (!fetchResult.ok) {
      detailFailed += 1;
      failures.push({
        entity_id: entityId,
        increment_id: candidate.IncrementId ?? null,
        phase: 'detail_reconcile',
        error: fetchResult.error,
      });
      continue;
    }

    const storedFingerprint = storedFingerprints.get(headerId) ?? null;
    if (!detailsChanged(storedFingerprint, fetchResult.order)) {
      detailUnchanged += 1;
      continue;
    }

    try {
      await syncDetailsOnly(pool, headerId, fetchResult.order, sourceEnvironment);
      detailUpdated += 1;
    } catch (error) {
      detailFailed += 1;
      failures.push({
        entity_id: entityId,
        increment_id: candidate.IncrementId ?? null,
        phase: 'detail_reconcile',
        error: error.message,
      });
    }
  }

  return {
    candidates: candidates.length,
    detail_updated: detailUpdated,
    detail_unchanged: detailUnchanged,
    detail_failed: detailFailed,
    failures,
  };
}

async function run(options = {}) {
  const configError = adobeCommerce.configError();
  if (configError) {
    return { ok: false, error: configError };
  }

  if (adobeCommerce.environment() !== 'production') {
    return {
      ok: true,
      skipped: true,
      fetched: 0,
      inserted: 0,
      updated: 0,
      detail_updated: 0,
      message: 'Skipped — ACCS sales order sync runs on Nutra-forecast-tool-prod only (ADOBE_COMMERCE_ENVIRONMENT=production).',
    };
  }

  const sourceEnvironment = sourceEnvironmentLabel();
  const overlapMinutes = envInt('ACCS_SALES_ORDER_SYNC_OVERLAP_MINUTES', 15);
  const lookbackDays = envInt('ACCS_SALES_ORDER_SYNC_LOOKBACK_DAYS', 365);
  const maxPages = envInt('ACCS_SALES_ORDER_SYNC_MAX_PAGES', 200);
  const detailReconcileBatch = envInt('ACCS_SALES_ORDER_DETAIL_RECONCILE_BATCH', 200);
  const forceFull = Boolean(options.force);

  let pool;
  try {
    pool = await connectPool(getProductionDatabase());
  } catch (error) {
    return { ok: false, error: error.message };
  }

  try {
    const latest = await loadExistingSyncState(pool, sourceEnvironment);
    let updatedSince;

    if (forceFull || !latest?.OrderUpdatedAt) {
      updatedSince = new Date(Date.now() - lookbackDays * 24 * 60 * 60 * 1000);
    } else {
      updatedSince = overlapDate(new Date(latest.OrderUpdatedAt), overlapMinutes);
    }

    const fetchResult = await fetchOrdersUpdatedSince(updatedSince, maxPages);
    if (!fetchResult.ok) {
      return { ok: false, error: fetchResult.error };
    }

    const entityIds = fetchResult.rows
      .map((order) => Number(order.entity_id))
      .filter((entityId) => Number.isFinite(entityId) && entityId > 0);

    const existingMap = await loadExistingOrderMap(pool, sourceEnvironment, entityIds);
    const headerIds = [...existingMap.values()].map((row) => Number(row.AccsSalesOrderHeaderID));
    const detailFingerprints = await loadDetailFingerprints(pool, sourceEnvironment, headerIds);

    let inserted = 0;
    let updated = 0;
    let detailUpdated = 0;
    let skipped = 0;
    let failed = 0;
    const failures = [];

    for (const order of fetchResult.rows) {
      const entityId = Number(order.entity_id);
      const existing = existingMap.get(entityId);
      const headerId = existing ? Number(existing.AccsSalesOrderHeaderID) : null;
      const storedFingerprint = headerId ? (detailFingerprints.get(headerId) ?? null) : null;
      const headerUnchanged = !forceFull && headerIsUnchanged(existing, order);
      const detailUnchanged = headerUnchanged && !detailsChanged(storedFingerprint, order);

      if (detailUnchanged) {
        skipped += 1;
        continue;
      }

      try {
        if (headerUnchanged && existing) {
          await syncDetailsOnly(pool, headerId, order, sourceEnvironment);
          detailUpdated += 1;
          continue;
        }

        const result = await upsertOrder(pool, order);
        if (result.action === 'upserted') {
          if (existing) {
            updated += 1;
          } else {
            inserted += 1;
          }
        } else {
          skipped += 1;
        }
      } catch (error) {
        failed += 1;
        failures.push({
          entity_id: entityId,
          increment_id: order.increment_id ?? null,
          phase: 'header_sync',
          error: error.message,
        });
      }
    }

    const reconcileResult = await reconcileOpenOrderDetails(pool, sourceEnvironment, detailReconcileBatch);
    failures.push(...reconcileResult.failures);

    const totalFailed = failed + reconcileResult.detail_failed;
    if (totalFailed > 0 && inserted === 0 && updated === 0 && detailUpdated === 0 && reconcileResult.detail_updated === 0) {
      return {
        ok: false,
        error: failures[0]?.error || 'ACCS sales order sync failed.',
        source_environment: sourceEnvironment,
        fetched: fetchResult.rows.length,
        failures,
      };
    }

    return {
      ok: true,
      error: null,
      source_environment: sourceEnvironment,
      api_environment: adobeCommerce.environment(),
      updated_since: updatedSince.toISOString(),
      fetched: fetchResult.rows.length,
      inserted,
      updated,
      detail_updated: detailUpdated + reconcileResult.detail_updated,
      detail_reconcile_candidates: reconcileResult.candidates,
      detail_reconcile_unchanged: reconcileResult.detail_unchanged,
      skipped,
      failed: totalFailed,
      failures,
    };
  } finally {
    await pool.close();
  }
}

module.exports = {
  run,
  upsertOrder,
};
