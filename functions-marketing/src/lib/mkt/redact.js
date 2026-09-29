// Order matters: emails contain "@", so they are replaced before handles.
const PATTERNS = [
  [/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/g, '[email]'],
  [/(?:\+?1[\s.-]?)?\(?\b\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}\b/g, '[phone]'],
  [/(^|[^\w[])@[A-Za-z0-9_.]{2,30}/g, '$1[handle]'],
];

/** Replace emails, phone numbers and @handles. @returns {{text: string, count: number}} */
function redact(text) {
  let out = String(text || '');
  let count = 0;
  for (const [pattern, replacement] of PATTERNS) {
    out = out.replace(pattern, (...args) => {
      count += 1;
      return replacement.replace('$1', typeof args[1] === 'string' ? args[1] : '');
    });
  }
  return { text: out, count };
}

module.exports = { redact };
