function envFlag(name, defaultValue = true) {
  const raw = process.env[name];
  if (raw === undefined || String(raw).trim() === '') {
    return defaultValue;
  }

  const normalized = String(raw).trim().toLowerCase();
  return normalized === '1' || normalized === 'true' || normalized === 'yes';
}

function shouldStartInactive() {
  return envFlag('ACCS_EMPLOYEE_CUSTOMER_START_INACTIVE', true);
}

function shouldStartLocked() {
  return envFlag('ACCS_EMPLOYEE_CUSTOMER_START_LOCKED', true);
}

function buildCustomerUpdateRecord(customer, expectedGroupId = null) {
  const record = {
    id: customer.id,
    email: customer.email,
    firstname: customer.firstname,
    lastname: customer.lastname,
    group_id: expectedGroupId ?? customer.group_id,
    website_id: customer.website_id,
    store_id: customer.store_id,
    disable_auto_group_change: customer.disable_auto_group_change ?? 0,
  };

  if (Array.isArray(customer.addresses) && customer.addresses.length > 0) {
    record.addresses = customer.addresses;
  }

  if (customer.extension_attributes && typeof customer.extension_attributes === 'object') {
    record.extension_attributes = customer.extension_attributes;
  }

  if (Array.isArray(customer.custom_attributes) && customer.custom_attributes.length > 0) {
    record.custom_attributes = customer.custom_attributes;
  }

  return record;
}

function applyCreateRestrictions(customerPayload) {
  // ACCS rejects restriction fields on POST /customers.
  return { ...customerPayload };
}

module.exports = {
  applyCreateRestrictions,
  buildCustomerUpdateRecord,
  shouldStartInactive,
  shouldStartLocked,
};
