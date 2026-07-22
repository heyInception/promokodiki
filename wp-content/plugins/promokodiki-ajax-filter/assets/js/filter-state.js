(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.PromokodikiFilterState = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function normalizeState(input) {
    const state = {
      category: input.category ? String(input.category) : '',
      brand: input.brand ? String(input.brand) : '',
      sort: input.sort ? String(input.sort) : '',
      popular: Boolean(input.popular),
      page: Math.max(1, Number.parseInt(input.page, 10) || 1)
    };

    if (state.popular) {
      state.category = '';
      state.brand = '';
      state.sort = '';
      state.page = 1;
    }

    return state;
  }

  function stateToSearchParams(input) {
    const state = normalizeState(input);
    const params = new URLSearchParams();
    if (state.category) params.set('paf_category', state.category);
    if (state.brand) params.set('paf_brand', state.brand);
    if (state.sort) params.set('paf_sort', state.sort);
    if (state.popular) params.set('paf_popular', '1');
    return params;
  }

  function fromSearchParams(params) {
    return normalizeState({
      category: params.get('paf_category') || '',
      brand: params.get('paf_brand') || '',
      sort: params.get('paf_sort') || '',
      popular: params.get('paf_popular') === '1',
      page: 1
    });
  }

  return { normalizeState, stateToSearchParams, fromSearchParams };
}));
