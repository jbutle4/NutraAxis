const { parseCanonicalBody } = require('../canonical-order');
const { mapCanonicalToSalesReceipt, buildQboDocNumber } = require('../qbo-order-mapper');
const { createSalesReceipt } = require('../qbo-sales-receipt');
const qboOrderLog = require('../qbo-order-log');
const qboConfig = require('../qbo-config');
const qboConnection = require('../qbo-connection');

async function insertCanonicalOrderToQbo(canonical, context, options = {}) {
  const parsed = parseCanonicalBody(canonical);
  if (!parsed.ok) {
    throw new Error(parsed.error);
  }

  const incrementId = parsed.canonical.incrementId;
  const sourceEnvironment = String(parsed.canonical.sourceEnvironment || 'stage').trim();
  const entityId = Number(parsed.canonical.orderId || 0);

  const configError = qboConfig.configError();
  if (configError) {
    throw new Error(configError);
  }

  const connection = await qboConnection.getConnection();
  if (!connection) {
    throw new Error(
      'QuickBooks sandbox is not connected. Visit /api/qbo-sandbox-oauth-start on Nutra-forecast-tool and authorize Sandbox Company US 7988.'
    );
  }

  const docSuffix = String(options.docSuffix ?? qboConfig.orderTestSuffix()).trim();
  const docNumber = buildQboDocNumber(incrementId, docSuffix);

  const existingLog = await qboOrderLog.loadEntry(incrementId, sourceEnvironment);
  if (
    existingLog
    && String(existingLog.Status).toLowerCase() === 'posted'
    && String(existingLog.QBODocNumber || '') === docNumber
  ) {
    context.log('QBO order already posted increment_id=%s doc=%s txn_id=%s', incrementId, docNumber, existingLog.QBOTransactionId);
    return {
      ok: true,
      duplicate: true,
      skipped: false,
      increment_id: incrementId,
      external_ref: existingLog.QBODocNumber,
      qbo_transaction_id: existingLog.QBOTransactionId,
      doc_number: existingLog.QBODocNumber,
      result_message: `QBO sales receipt ${existingLog.QBODocNumber} already posted for order ${incrementId}.`,
    };
  }

  const mapped = mapCanonicalToSalesReceipt(parsed.canonical, { docSuffix });
  if (!mapped.ok) {
    throw new Error(mapped.error);
  }

  try {
    const result = await createSalesReceipt(mapped.payload, context);

    await qboOrderLog.markPosted({
      accs_entity_id: entityId,
      increment_id: incrementId,
      source_environment: sourceEnvironment,
      doc_number: result.doc_number,
      transaction_id: result.transaction_id,
      realm_id: connection.RealmID,
      status: 'posted',
    });

    context.log(
      'QBO sandbox sales receipt order=%s doc=%s txn_id=%s duplicate=%s',
      incrementId,
      result.doc_number,
      result.transaction_id,
      Boolean(result.duplicate)
    );

    return {
      ok: true,
      duplicate: Boolean(result.duplicate),
      increment_id: incrementId,
      external_ref: result.doc_number,
      realm_id: connection.RealmID,
      company_name: connection.CompanyName,
      doc_number: result.doc_number,
      qbo_transaction_id: result.transaction_id,
      total: result.total ?? null,
      result_message: result.duplicate
        ? `QBO sales receipt ${result.doc_number} already exists for order ${incrementId}.`
        : `QBO sales receipt ${result.doc_number} posted for order ${incrementId}.`,
    };
  } catch (error) {
    await qboOrderLog.markFailed(incrementId, sourceEnvironment, error.message);
    throw error;
  }
}

module.exports = {
  insertCanonicalOrderToQbo,
};
