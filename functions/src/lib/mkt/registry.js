const seoNoop = require('../jobs/seo-noop');

const JOBS = {
  'seo-noop': {
    name: 'Marketing Chassis Check',
    run: () => seoNoop.run(),
    message: (r) => `Marketing chassis OK — ${r.settings ?? 0} settings loaded.`,
  },
};

const REGISTRY = Object.fromEntries(
  Object.entries(JOBS).map(([code, job]) => [code, { code, name: job.name }])
);

function has(code) {
  return Object.prototype.hasOwnProperty.call(JOBS, code);
}

function invoke(code, params = {}) {
  return JOBS[code].run(params);
}

function buildResultMessage(code, result) {
  if (result.skipped && result.message) {
    return result.message;
  }
  return JOBS[code].message(result);
}

module.exports = {
  REGISTRY,
  has,
  invoke,
  buildResultMessage,
};
