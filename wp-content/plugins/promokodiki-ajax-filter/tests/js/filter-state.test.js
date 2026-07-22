const test = require('node:test');
const assert = require('node:assert/strict');
const state = require('../../assets/js/filter-state.js');

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
