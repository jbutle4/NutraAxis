const { settingLines } = require('./settings');

const MAX_TERMS_PER_INTEREST = 12;

function oneLine(text, max) {
  const flat = String(text || '').replace(/\s+/g, ' ').trim();
  return flat.length > max ? `${flat.slice(0, max - 1)}…` : flat;
}

/**
 * Active interests (with include terms), therapeutic areas and marketing products — the shared vocabulary
 * the scoring and clustering prompts must answer in.
 */
async function loadTaxonomy(pool, settings) {
  const interests = (await pool.request().query(`
    SELECT InterestID, Name, Description, TherapeuticArea
    FROM dbo.MktInterest
    WHERE Status = N'active'
    ORDER BY Priority DESC, Name
  `)).recordset;
  const terms = (await pool.request().query(`
    SELECT t.InterestID, t.Term
    FROM dbo.MktInterestTerm t
    INNER JOIN dbo.MktInterest i ON i.InterestID = t.InterestID AND i.Status = N'active'
    WHERE t.TermType = N'include'
    ORDER BY t.TermID
  `)).recordset;
  const termsByInterest = new Map();
  for (const row of terms) {
    const list = termsByInterest.get(row.InterestID) || [];
    if (list.length < MAX_TERMS_PER_INTEREST) list.push(row.Term);
    termsByInterest.set(row.InterestID, list);
  }
  const products = (await pool.request().query(`
    SELECT ProductID, Name, TherapeuticArea, Formula
    FROM dbo.MktProduct
    WHERE Status = N'active'
    ORDER BY Name
  `)).recordset;

  const areas = settingLines(settings, 'taxonomy.therapeutic_areas');
  return {
    interests,
    areas,
    products,
    interestIds: new Set(interests.map((row) => row.InterestID)),
    interestLines: interests.map((row) => {
      const include = termsByInterest.get(row.InterestID) || [];
      return `${row.InterestID} | ${row.Name} | ${oneLine(row.Description, 300)}`
        + (include.length ? ` Terms: ${include.join(', ')}` : '');
    }).join('\n'),
    interestNameLines: interests.map((row) => `${row.InterestID} | ${row.Name}`).join('\n'),
    productLines: products.map((row) => `${row.Name} | ${row.TherapeuticArea || '-'} | ${oneLine(row.Formula, 250)}`).join('\n'),
  };
}

/** Case-insensitive match of model output to a controlled vocabulary; returns canonical spellings. */
function matchVocabulary(values, vocabulary) {
  const canonical = new Map(vocabulary.map((entry) => [String(entry).toLowerCase(), entry]));
  const matched = [];
  for (const value of Array.isArray(values) ? values : []) {
    const hit = canonical.get(String(value || '').trim().toLowerCase());
    if (hit && !matched.includes(hit)) matched.push(hit);
  }
  return matched;
}

module.exports = { loadTaxonomy, matchVocabulary, oneLine };
