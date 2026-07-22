(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.PromokodikiFilterState = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function normalizeIdentifier(value) {
    const identifier = value ? String(value) : '';
    return identifier === '0' ? '' : identifier;
  }

  function normalizeState(input) {
    const state = {
      category: normalizeIdentifier(input.category),
      brand: normalizeIdentifier(input.brand),
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

  function invalidResponse() {
    throw new TypeError('Invalid filter response');
  }

  function prepareOptions(options) {
    if (!Array.isArray(options)) invalidResponse();

    return options.map((item) => {
      if (!item || typeof item !== 'object' || Array.isArray(item)) invalidResponse();
      const id = Number(item.id);
      if (!Number.isInteger(id) || id <= 0 || typeof item.label !== 'string') invalidResponse();
      return { id: String(id), label: item.label };
    });
  }

  function prepareResultsPayload(data) {
    if (!data || typeof data !== 'object' || Array.isArray(data)) invalidResponse();
    if (typeof data.html !== 'string'
      || !Number.isInteger(data.page) || data.page < 1
      || typeof data.has_more !== 'boolean'
      || !Number.isInteger(data.total) || data.total < 0
      || typeof data.message !== 'string') {
      invalidResponse();
    }

    const responseState = data.state;
    if (!responseState || typeof responseState !== 'object' || Array.isArray(responseState)
      || typeof responseState.category !== 'string'
      || typeof responseState.brand !== 'string'
      || typeof responseState.sort !== 'string'
      || typeof responseState.popular !== 'boolean') {
      invalidResponse();
    }

    return {
      html: data.html,
      page: data.page,
      hasMore: data.has_more,
      total: data.total,
      message: data.message,
      state: normalizeState(responseState),
      categoryOptions: prepareOptions(data.category_options),
      brandOptions: prepareOptions(data.brand_options)
    };
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

  return { normalizeState, stateToSearchParams, fromSearchParams, prepareResultsPayload };
}));
