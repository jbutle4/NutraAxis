const US_ZIP_PATTERN = /^\d{5}(-\d{4})?$/;

function normalizeZip(value) {
  return String(value ?? '').trim();
}

function isValidUsZip(value) {
  const zip = normalizeZip(value);
  return zip !== '' && zip !== '00000' && US_ZIP_PATTERN.test(zip);
}

function validateAddressBlock(label, address) {
  const errors = [];

  if (!address || typeof address !== 'object') {
    errors.push(`${label}: address is missing.`);
    return errors;
  }

  const line1 = String(address.address1 ?? '').trim();
  const city = String(address.city ?? '').trim();
  const state = String(address.state ?? '').trim();
  const zipcode = normalizeZip(address.zipcode ?? address.zip ?? address.postcode);
  const country = String(address.country ?? address.country_id ?? 'US').trim() || 'US';

  if (!line1 || line1 === 'Unknown') {
    errors.push(`${label}: street address is missing.`);
  }

  if (!city || city === 'Unknown') {
    errors.push(`${label}: city is missing.`);
  }

  if (!state || state === 'NA') {
    errors.push(`${label}: state is missing.`);
  }

  if (country.toUpperCase() === 'US' && !isValidUsZip(zipcode)) {
    errors.push(`${label}: zip code is missing or invalid (${zipcode || 'empty'}).`);
  } else if (!zipcode) {
    errors.push(`${label}: postal code is missing.`);
  }

  return errors;
}

function validateJazzImportPayload(payload) {
  const errors = [];

  if (!payload || typeof payload !== 'object') {
    return { ok: false, errors: ['Jazz import payload is missing.'] };
  }

  if (!String(payload.order_number ?? '').trim()) {
    errors.push('Jazz order_number is missing.');
  }

  errors.push(...validateAddressBlock('Customer address', payload.customer));

  const shipTos = Array.isArray(payload.shipto) ? payload.shipto : [];
  if (shipTos.length === 0) {
    errors.push('At least one ship-to address is required.');
  }

  shipTos.forEach((shipTo, index) => {
    errors.push(...validateAddressBlock(`Ship-to ${index + 1}`, shipTo?.address));
  });

  const lines = shipTos.flatMap((shipTo) => shipTo?.detail_set || []);
  if (lines.length === 0) {
    errors.push('At least one Cart line item is required for Jazz import.');
  }

  return {
    ok: errors.length === 0,
    errors,
  };
}

function collectJazzHolds(jazzData) {
  if (!jazzData || typeof jazzData !== 'object') {
    return [];
  }

  const holds = [];

  if (Array.isArray(jazzData.holds)) {
    holds.push(...jazzData.holds);
  }

  for (const shipTo of jazzData.shipto || []) {
    if (Array.isArray(shipTo.holds)) {
      holds.push(...shipTo.holds);
    }
  }

  return holds.map((hold) => ({
    reason: String(hold?.reason ?? hold?.hold_type ?? 'Hold').trim(),
    explanation: String(hold?.explanation ?? '').trim(),
    order_number: String(hold?.order_number ?? jazzData.order_number ?? '').trim(),
  })).filter((hold) => hold.reason !== '');
}

module.exports = {
  isValidUsZip,
  validateJazzImportPayload,
  collectJazzHolds,
};
