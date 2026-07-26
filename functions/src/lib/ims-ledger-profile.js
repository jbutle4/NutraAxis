function imsLedgerProfile() {
  const explicit = String(process.env.IMS_LEDGER_PROFILE || '').trim().toLowerCase();
  if (explicit === 'uat' || explicit === 'production') {
    return explicit;
  }

  const qboEnv = String(process.env.QBO_ENVIRONMENT || '').trim().toLowerCase();
  if (qboEnv === 'sandbox') {
    return 'uat';
  }
  if (qboEnv === 'production') {
    return 'production';
  }

  const accsEnv = String(process.env.ADOBE_COMMERCE_ENVIRONMENT || '').trim().toLowerCase();
  if (accsEnv === 'production') {
    return 'production';
  }
  if (accsEnv === 'stage' || accsEnv === 'dev') {
    return 'uat';
  }

  return 'production';
}

/** AccsSalesOrderHeader.SourceEnvironment for the active IMS ledger profile. */
function accsSourceEnvironment() {
  return imsLedgerProfile() === 'production' ? 'production' : 'stage';
}

function imsEnvironmentSummary() {
  return {
    ims_ledger_profile: imsLedgerProfile(),
    qbo_environment: String(process.env.QBO_ENVIRONMENT || '').trim().toLowerCase() || '(unset)',
    accs_environment: String(process.env.ADOBE_COMMERCE_ENVIRONMENT || '').trim().toLowerCase() || '(unset)',
    accs_source_environment: accsSourceEnvironment(),
  };
}

module.exports = {
  imsLedgerProfile,
  accsSourceEnvironment,
  imsEnvironmentSummary,
};
