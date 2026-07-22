(function () {
  'use strict';

  const config = window.PromokodikiFilterConfig;
  const stateApi = window.PromokodikiFilterState;
  const roots = Array.from(document.querySelectorAll('[data-promokodiki-filter]'));
  if (!config || !stateApi || roots.length === 0) return;

  function value(form, name) {
    const field = form.elements.namedItem(name);
    return field ? field.value : '';
  }

  function readState(form) {
    const popular = form.elements.namedItem('paf_popular');
    return stateApi.normalizeState({
      category: value(form, 'paf_category'),
      brand: value(form, 'paf_brand'),
      sort: value(form, 'paf_sort'),
      popular: popular ? popular.checked : false,
      page: 1
    });
  }

  function applyState(form, state) {
    const category = form.elements.namedItem('paf_category');
    const brand = form.elements.namedItem('paf_brand');
    const sort = form.elements.namedItem('paf_sort');
    const popular = form.elements.namedItem('paf_popular');
    if (category) category.value = state.category || '0';
    if (brand) brand.value = state.brand || '0';
    if (sort) sort.value = state.sort || '';
    if (popular) popular.checked = state.popular;
  }

  function replaceSelectOptions(select, placeholder, options, selected) {
    if (!select) return;
    const fragment = document.createDocumentFragment();
    if (placeholder) fragment.append(new Option(placeholder, '0'));
    options.forEach((item) => fragment.append(new Option(item.label, String(item.id))));
    select.replaceChildren(fragment);
    select.value = String(selected || 0);
  }

  function updateUrl(state) {
    const url = new URL(window.location.href);
    ['paf_category', 'paf_brand', 'paf_sort', 'paf_popular', 'paf_page'].forEach((key) => url.searchParams.delete(key));
    stateApi.stateToSearchParams(state).forEach((item, key) => url.searchParams.set(key, item));
    window.history.pushState({ promokodikiFilter: true }, '', url);
  }

  roots.forEach((root) => {
    const form = root.querySelector('[data-filter-form]');
    const results = root.querySelector('[data-filter-results]');
    const more = root.querySelector('[data-filter-more]');
    const status = root.querySelector('[data-filter-status]');
    const loader = root.querySelector('[data-filter-loader]');
    if (!form || !results || !more || !status || !loader) return;

    const category = form.elements.namedItem('paf_category');
    const brand = form.elements.namedItem('paf_brand');
    const categoryPlaceholder = category && category.options[0]?.value === '0' ? category.options[0].text : '';
    const brandPlaceholder = brand && brand.options[0]?.value === '0' ? brand.options[0].text : '';

    let page = 1;
    let controller = null;
    let retry = null;

    function setLoading(loading) {
      root.classList.toggle('is-loading', loading);
      results.setAttribute('aria-busy', loading ? 'true' : 'false');
      loader.hidden = !loading;
      loader.setAttribute('aria-hidden', loading ? 'false' : 'true');
      Array.from(form.elements).forEach((element) => { element.disabled = loading; });
      more.disabled = loading;
      if (loading) status.textContent = config.loadingLabel;
    }

    function showError(error) {
      status.textContent = '';
      const message = document.createElement('span');
      message.textContent = error.message || config.genericError;
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'promokodiki-filter__retry';
      button.textContent = config.retryLabel;
      button.addEventListener('click', () => { if (retry) retry(); });
      status.replaceChildren(message, document.createTextNode(' '), button);
    }

    async function request(requestedPage, append, pushUrl) {
      if (controller) controller.abort();
      const currentController = new AbortController();
      controller = currentController;
      const state = readState(form);
      const body = new URLSearchParams();
      body.set('action', 'promokodiki_filter_results');
      body.set('nonce', config.nonce);
      body.set('context', root.dataset.context || '');
      body.set('object_id', root.dataset.objectId || '0');
      body.set('context_nonce', root.dataset.contextToken || '');
      body.set('paf_category', state.category);
      body.set('paf_brand', state.brand);
      body.set('paf_sort', state.sort);
      if (state.popular) body.set('paf_popular', '1');
      body.set('paf_page', String(requestedPage));

      retry = () => request(requestedPage, append, pushUrl);
      setLoading(true);
      try {
        const response = await fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: body.toString(),
          signal: currentController.signal
        });
        const json = await response.json();
        if (!response.ok || !json.success) {
          throw new Error(json.data && json.data.message ? json.data.message : config.genericError);
        }

        if (append) {
          results.insertAdjacentHTML('beforeend', json.data.html);
        } else {
          results.innerHTML = json.data.html;
          replaceSelectOptions(category, categoryPlaceholder, json.data.category_options, json.data.state.category);
          replaceSelectOptions(brand, brandPlaceholder, json.data.brand_options, json.data.state.brand);
          applyState(form, json.data.state);
          if (pushUrl) updateUrl(json.data.state);
        }
        page = requestedPage;
        more.hidden = !json.data.has_more;
        status.textContent = json.data.message;
      } catch (error) {
        if (error.name !== 'AbortError') showError(error);
      } finally {
        if (controller === currentController) setLoading(false);
      }
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      request(1, false, true);
    });

    form.addEventListener('change', (event) => {
      const popular = form.elements.namedItem('paf_popular');
      if (event.target === popular && popular.checked) {
        applyState(form, { category: '', brand: '', sort: '', popular: true });
      } else if (popular && event.target !== popular) {
        popular.checked = false;
      }
      request(1, false, true);
    });

    more.addEventListener('click', () => request(page + 1, true, false));

    window.addEventListener('popstate', () => {
      const state = stateApi.fromSearchParams(new URL(window.location.href).searchParams);
      applyState(form, state);
      request(1, false, false);
    });
  });

  document.addEventListener('click', (event) => {
    const button = event.target.closest('.promocodes__view, .promocodes__link, .top__button');
    if (!button) return;
    const postId = button.dataset.postId || button.closest('[data-post-id]')?.dataset.postId;
    if (!postId) return;

    const body = new URLSearchParams({
      action: 'promokodiki_filter_track_click',
      nonce: config.nonce,
      post_id: postId
    });
    fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      keepalive: true
    })
      .then((response) => response.json())
      .then((json) => {
        if (!json.success) return;
        document.querySelectorAll(`[data-post-id="${CSS.escape(String(postId))}"] .promocodes__used, [data-post-id="${CSS.escape(String(postId))}"] .top__quantity`)
          .forEach((counter) => { counter.textContent = `${json.data.new_count} Применено`; });
      })
      .catch(() => {});
  });
}());
