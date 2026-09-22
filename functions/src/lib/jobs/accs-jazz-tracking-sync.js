/**
 * Sync Jazz OMS shipment tracking numbers into ACCS Magento shipments.
 *
 * For complete/closed/shipped ACCS orders missing track_number:
 *   1. Resolve Jazz order NA-{increment_id} (configurable prefix)
 *   2. Read Jazz /api/v1/shipping/shipment tracking_number(s)
 *   3. If ACCS shipment exists → POST /V1/shipment/track
 *   4. If no ACCS shipment → POST /V1/order/{id}/ship with tracks (historical POD)
 */

const adobeCommerce = require('../adobe-commerce');
const jazzOms = require('../jazz-oms');

const DEFAULT_STATUSES = ['complete', 'closed', 'shipped'];
const DEFAULT_PREFIX = 'NA-';

function envValue(key, fallback = '') {
  const value = process.env[key];
  if (value !== undefined && String(value).trim() !== '') {
    return String(value).trim();
  }
  return fallback;
}

function envBool(key, fallback = false) {
  const raw = envValue(key);
  if (!raw) return fallback;
  return ['1', 'true', 'yes', 'on'].includes(raw.toLowerCase());
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function jazzOrderNumberFor(incrementId) {
  const prefix = envValue('JAZZ_ACCS_ORDER_NUMBER_PREFIX', DEFAULT_PREFIX);
  const id = String(incrementId ?? '').trim();
  if (!id) return null;
  if (id.toUpperCase().startsWith(String(prefix).toUpperCase())) {
    return id;
  }
  return `${prefix}${id}`;
}

function resolveCarrier(trackNumber, jazzShipment = {}) {
  const tn = String(trackNumber || '').trim();
  if (/^TBA/i.test(tn)) {
    return { carrier_code: 'amazon_shipping', title: 'Amazon Shipping' };
  }

  const shipCode = String(jazzShipment.ship_code || '').toUpperCase();
  const description = String(jazzShipment.ship_code_description || jazzShipment.ship_code || '').trim();

  if (shipCode.includes('FEDEX') || /fedex/i.test(description)) {
    return { carrier_code: 'fedex', title: description || 'FedEx' };
  }
  if (shipCode.includes('UPS') || /\bups\b/i.test(description)) {
    return { carrier_code: 'ups', title: description || 'UPS' };
  }
  if (shipCode.includes('USPS') || /usps|postal/i.test(description)) {
    return { carrier_code: 'usps', title: description || 'USPS' };
  }

  return {
    carrier_code: 'custom',
    title: description || 'Carrier',
  };
}

function tracksOnShipment(shipment) {
  const tracks = Array.isArray(shipment?.tracks) ? shipment.tracks : [];
  return tracks
    .map((t) => String(t.track_number || '').trim())
    .filter(Boolean);
}

function collectJazzTracking(shipments) {
  const out = [];
  for (const shipment of shipments || []) {
    const trackNumber = String(shipment.tracking_number || '').trim();
    if (!trackNumber) continue;
    out.push({
      track_number: trackNumber,
      ship_date: shipment.ship_date || null,
      ship_code: shipment.ship_code || null,
      shipment_number: shipment.shipment_number || null,
      jazz_shipment: shipment,
    });
  }
  return out;
}

function shippableLineItems(order) {
  const items = Array.isArray(order?.items) ? order.items : [];
  const lines = [];

  for (const item of items) {
    const productType = String(item.product_type || '').toLowerCase();
    if (productType === 'configurable' || productType === 'bundle' || productType === 'grouped') {
      continue;
    }

    const ordered = Number(item.qty_ordered) || 0;
    const shipped = Number(item.qty_shipped) || 0;
    const canceled = Number(item.qty_canceled) || 0;
    const refunded = Number(item.qty_refunded) || 0;
    const remaining = ordered - shipped - canceled - refunded;
    if (remaining <= 0.0001) {
      continue;
    }

    const itemId = Number(item.item_id);
    if (!Number.isFinite(itemId) || itemId <= 0) {
      continue;
    }

    lines.push({
      order_item_id: itemId,
      qty: remaining,
    });
  }

  return lines;
}

async function fetchShipmentsForOrder(orderEntityId, accsOptions) {
  const query = {
    'searchCriteria[filter_groups][0][filters][0][field]': 'order_id',
    'searchCriteria[filter_groups][0][filters][0][value]': String(orderEntityId),
    'searchCriteria[filter_groups][0][filters][0][condition_type]': 'eq',
    'searchCriteria[pageSize]': '50',
    'searchCriteria[currentPage]': '1',
  };

  const result = await adobeCommerce.apiRequest('GET', '/shipments', query, null, accsOptions);
  if (!result.ok) {
    return { ok: false, error: result.error, shipments: [] };
  }

  const shipments = Array.isArray(result.data?.items) ? result.data.items : [];
  return { ok: true, error: null, shipments };
}

async function addTrackToShipment(shipmentEntityId, orderEntityId, track, dryRun, accsOptions) {
  const carrier = resolveCarrier(track.track_number, track.jazz_shipment);
  const payload = {
    entity: {
      parent_id: shipmentEntityId,
      order_id: orderEntityId,
      track_number: track.track_number,
      title: carrier.title,
      carrier_code: carrier.carrier_code,
    },
  };

  if (dryRun) {
    return { ok: true, dry_run: true, action: 'add_track', payload };
  }

  const result = await adobeCommerce.apiRequest('POST', '/shipment/track', null, payload, accsOptions);
  if (!result.ok) {
    return { ok: false, error: result.error, action: 'add_track' };
  }

  return {
    ok: true,
    action: 'add_track',
    track_id: result.data?.entity_id ?? null,
    track_number: track.track_number,
    carrier_code: carrier.carrier_code,
  };
}

async function createShipmentWithTracks(order, tracks, dryRun, accsOptions) {
  const items = shippableLineItems(order);
  if (items.length === 0) {
    return {
      ok: false,
      error: 'No shippable qty remaining and no ACCS shipment exists (cannot attach POD track).',
      action: 'create_shipment',
    };
  }

  const tracksPayload = tracks.map((track) => {
    const carrier = resolveCarrier(track.track_number, track.jazz_shipment);
    return {
      track_number: track.track_number,
      title: carrier.title,
      carrier_code: carrier.carrier_code,
    };
  });

  const payload = {
    items,
    notify: false,
    appendComment: false,
    tracks: tracksPayload,
  };

  if (dryRun) {
    return { ok: true, dry_run: true, action: 'create_shipment', payload };
  }

  const result = await adobeCommerce.apiRequest(
    'POST',
    `/order/${order.entity_id}/ship`,
    null,
    payload,
    accsOptions
  );

  if (!result.ok) {
    return { ok: false, error: result.error, action: 'create_shipment' };
  }

  return {
    ok: true,
    action: 'create_shipment',
    shipment_id: result.data ?? null,
    track_numbers: tracksPayload.map((t) => t.track_number),
  };
}

async function processOrder(order, options = {}) {
  const dryRun = Boolean(options.dryRun);
  const delayMs = Number(options.delayMs) || 0;
  const accsOptions = { environment: options.environment || 'production' };
  const jazzOptions = { uat: false };

  const incrementId = String(order.increment_id || '').trim();
  const entityId = Number(order.entity_id);
  const base = {
    increment_id: incrementId,
    entity_id: entityId,
    status: order.status,
  };

  const shipResult = await fetchShipmentsForOrder(entityId, accsOptions);
  if (!shipResult.ok) {
    return { ...base, ok: false, skipped: false, error: shipResult.error, reason: 'accs_shipment_lookup_failed' };
  }

  const shipments = shipResult.shipments;
  const existingTracks = new Set();
  for (const shipment of shipments) {
    for (const tn of tracksOnShipment(shipment)) {
      existingTracks.add(tn);
    }
  }

  if (existingTracks.size > 0) {
    return {
      ...base,
      ok: true,
      skipped: true,
      reason: 'already_has_tracking',
      track_numbers: [...existingTracks],
    };
  }

  const jazzOrderNumber = jazzOrderNumberFor(incrementId);
  const jazzResult = await jazzOms.listShipmentsByOrderNumber(jazzOrderNumber, jazzOptions);
  if (!jazzResult.ok) {
    return { ...base, ok: false, skipped: false, error: jazzResult.error, reason: 'jazz_lookup_failed', jazz_order_number: jazzOrderNumber };
  }

  const jazzTracks = collectJazzTracking(jazzResult.shipments);
  if (jazzTracks.length === 0) {
    return {
      ...base,
      ok: true,
      skipped: true,
      reason: jazzResult.shipments.length === 0 ? 'not_in_jazz_or_no_shipment' : 'jazz_shipment_without_tracking',
      jazz_order_number: jazzOrderNumber,
      jazz_shipment_count: jazzResult.shipments.length,
    };
  }

  if (delayMs > 0) {
    await sleep(delayMs);
  }

  if (shipments.length > 0) {
    const shipment = shipments[0];
    const updates = [];
    for (const track of jazzTracks) {
      if (existingTracks.has(track.track_number)) continue;
      const added = await addTrackToShipment(shipment.entity_id, entityId, track, dryRun, accsOptions);
      updates.push(added);
      if (!added.ok) {
        return {
          ...base,
          ok: false,
          skipped: false,
          reason: 'add_track_failed',
          jazz_order_number: jazzOrderNumber,
          shipment_increment_id: shipment.increment_id,
          error: added.error,
          updates,
        };
      }
      existingTracks.add(track.track_number);
      if (delayMs > 0) await sleep(delayMs);
    }

    return {
      ...base,
      ok: true,
      skipped: false,
      reason: dryRun ? 'dry_run_add_track' : 'track_added',
      jazz_order_number: jazzOrderNumber,
      shipment_increment_id: shipment.increment_id,
      track_numbers: jazzTracks.map((t) => t.track_number),
      updates,
    };
  }

  const created = await createShipmentWithTracks(order, jazzTracks, dryRun, accsOptions);
  if (!created.ok) {
    return {
      ...base,
      ok: false,
      skipped: false,
      reason: 'create_shipment_failed',
      jazz_order_number: jazzOrderNumber,
      error: created.error,
      track_numbers: jazzTracks.map((t) => t.track_number),
    };
  }

  return {
    ...base,
    ok: true,
    skipped: false,
    reason: dryRun ? 'dry_run_create_shipment' : 'shipment_created_with_tracks',
    jazz_order_number: jazzOrderNumber,
    track_numbers: jazzTracks.map((t) => t.track_number),
    result: created,
  };
}

async function run(options = {}) {
  const environment = adobeCommerce.resolveEnvironmentName(
    options.environment || envValue('ACCS_JAZZ_TRACKING_SYNC_ENVIRONMENT', 'production')
  );
  const dryRun = Boolean(options.dryRun ?? options.dry_run ?? envBool('ACCS_JAZZ_TRACKING_SYNC_DRY_RUN', false));
  const delayMs = Number(options.delayMs ?? options.delay_ms ?? envValue('ACCS_JAZZ_TRACKING_SYNC_DELAY_MS', '200')) || 0;
  const onlyIncrementId = String(options.increment_id || options.incrementId || '').trim();

  const statuses = String(envValue('ACCS_JAZZ_TRACKING_SYNC_STATUSES', DEFAULT_STATUSES.join(',')))
    .split(',')
    .map((s) => s.trim().toLowerCase())
    .filter(Boolean);

  const statusFilter = onlyIncrementId
    ? {
        'searchCriteria[filter_groups][0][filters][0][field]': 'increment_id',
        'searchCriteria[filter_groups][0][filters][0][value]': onlyIncrementId,
        'searchCriteria[filter_groups][0][filters][0][condition_type]': 'eq',
      }
    : {
        'searchCriteria[filter_groups][0][filters][0][field]': 'status',
        'searchCriteria[filter_groups][0][filters][0][value]': statuses.join(','),
        'searchCriteria[filter_groups][0][filters][0][condition_type]': 'in',
      };

  const ordersResult = await adobeCommerce.fetchPaginatedOrders(statusFilter, 200, { environment });
  if (!ordersResult.ok) {
    return {
      ok: false,
      error: ordersResult.error || 'Failed to list ACCS orders.',
      environment,
      dry_run: dryRun,
    };
  }

  const details = [];
  let updated = 0;
  let skipped = 0;
  let failed = 0;
  let missingCandidates = 0;

  for (const order of ordersResult.rows) {
    const shipResult = await fetchShipmentsForOrder(order.entity_id, { environment });
    if (!shipResult.ok) {
      failed += 1;
      details.push({
        increment_id: order.increment_id,
        entity_id: order.entity_id,
        ok: false,
        reason: 'accs_shipment_lookup_failed',
        error: shipResult.error,
      });
      continue;
    }

    const hasTrack = shipResult.shipments.some((s) => tracksOnShipment(s).length > 0);
    if (hasTrack && !onlyIncrementId) {
      continue;
    }

    missingCandidates += 1;
    const result = await processOrder(order, { environment, dryRun, delayMs });
    details.push(result);

    if (!result.ok) {
      failed += 1;
    } else if (result.skipped) {
      skipped += 1;
    } else {
      updated += 1;
    }
  }

  return {
    ok: true,
    error: null,
    warning: failed > 0 ? `${failed} order(s) failed tracking sync.` : null,
    environment,
    dry_run: dryRun,
    scanned: ordersResult.rows.length,
    missing_candidates: missingCandidates,
    updated,
    skipped,
    failed,
    details,
  };
}

module.exports = {
  run,
  processOrder,
  jazzOrderNumberFor,
  resolveCarrier,
};
