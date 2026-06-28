function parseGroupMapJson(raw) {
  if (!raw || String(raw).trim() === '') {
    return null;
  }

  try {
    const parsed = JSON.parse(String(raw));
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return null;
    }

    const normalized = {};
    for (const [key, value] of Object.entries(parsed)) {
      const groupId = Number.parseInt(String(value), 10);
      if (Number.isFinite(groupId) && groupId > 0) {
        normalized[String(key).trim().toLowerCase()] = groupId;
      }
    }

    return Object.keys(normalized).length > 0 ? normalized : null;
  } catch {
    return null;
  }
}

function defaultGroupMapForEnvironment(envName = 'stage') {
  if (String(envName).toLowerCase() === 'production') {
    return {
      employee: 8,
      sales: 9,
    };
  }

  // ACCS Stage tenant group IDs differ from production.
  return {
    employee: 9,
    sales: 11,
  };
}

function loadGroupMap(envName = 'stage') {
  return parseGroupMapJson(process.env.ACCS_EMPLOYEE_CUSTOMER_GROUP_MAP)
    || defaultGroupMapForEnvironment(envName);
}

function normalizeGroupKey(value) {
  return String(value ?? '').trim().toLowerCase();
}

function resolveGroupId(groupValue, envName = 'stage') {
  const key = normalizeGroupKey(groupValue);
  if (!key) {
    return null;
  }

  const map = loadGroupMap(envName);
  return map[key] ?? null;
}

function resolveGroupIdFromEmployee(employee, envName = 'stage') {
  return resolveGroupId(employee?.Group1, envName)
    ?? resolveGroupId(employee?.Group2, envName);
}

module.exports = {
  defaultGroupMapForEnvironment,
  loadGroupMap,
  resolveGroupId,
  resolveGroupIdFromEmployee,
};
