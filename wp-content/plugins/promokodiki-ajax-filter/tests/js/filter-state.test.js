const test = require('node:test');
const assert = require('node:assert/strict');
const state = require('../../assets/js/filter-state.js');

function resultsPayload(overrides = {}) {
  return {
    html: '<article>Promo</article>',
    page: 1,
    has_more: true,
    total: 1,
    message: 'Found one',
    state: { category: '4', brand: '', sort: 'newest', popular: false },
    category_options: [{ id: 4, label: 'Category' }],
    brand_options: [{ id: 8, label: 'Brand' }],
    ...overrides
  };
}

test('weekly popularity clears category brand and sort', () => {
  assert.deepEqual(
    state.normalizeState({ category: '4', brand: '8', sort: 'newest', popular: true, page: 3 }),
    { category: '', brand: '', sort: '', popular: true, page: 1 }
  );
});

test('query string contains only non-empty paf keys', () => {
  assert.equal(
    state.stateToSearchParams({ category: '4', brand: '', sort: 'oldest', popular: false, page: 1 }).toString(),
    'paf_category=4&paf_sort=oldest'
  );
});

test('URL state parser restores filter controls', () => {
  assert.deepEqual(
    state.fromSearchParams(new URLSearchParams('paf_category=9&paf_brand=2&paf_sort=popular')),
    { category: '9', brand: '2', sort: 'popular', popular: false, page: 1 }
  );
});

test('zero category and brand identifiers are omitted from query strings', () => {
  assert.deepEqual(
    state.normalizeState({ category: '0', brand: 0, sort: 'newest', popular: false, page: 1 }),
    { category: '', brand: '', sort: 'newest', popular: false, page: 1 }
  );
  assert.equal(
    state.stateToSearchParams({ category: '0', brand: '0', sort: '', popular: false }).toString(),
    ''
  );
});

test('results payload is validated and prepared before DOM use', () => {
  assert.deepEqual(
    state.prepareResultsPayload(resultsPayload()),
    {
      html: '<article>Promo</article>',
      page: 1,
      hasMore: true,
      total: 1,
      message: 'Found one',
      state: { category: '4', brand: '', sort: 'newest', popular: false, page: 1 },
      categoryOptions: [{ id: '4', label: 'Category' }],
      brandOptions: [{ id: '8', label: 'Brand' }]
    }
  );
});

test('malformed results state and options are rejected', () => {
  assert.throws(
    () => state.prepareResultsPayload(resultsPayload({ state: { category: '4' } })),
    /Invalid filter response/
  );
  assert.throws(
    () => state.prepareResultsPayload(resultsPayload({ brand_options: [{ id: 0, label: 'Broken' }] })),
    /Invalid filter response/
  );
});
