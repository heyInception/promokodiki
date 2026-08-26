const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const stateApi = require('../../assets/js/filter-state.js');
const viewApi = require('../../assets/js/filter-view.js');

class FakeClassList {
  constructor() {
    this.values = new Set();
  }

  add(name) {
    this.values.add(name);
  }

  remove(name) {
    this.values.delete(name);
  }

  contains(name) {
    return this.values.has(name);
  }

  toggle(name, force) {
    const enabled = force === undefined ? !this.values.has(name) : Boolean(force);
    if (enabled) this.values.add(name);
    else this.values.delete(name);
    return enabled;
  }
}

class FakeElement {
  constructor(tagName = 'div') {
    this.tagName = tagName.toUpperCase();
    this.dataset = {};
    this.attributes = {};
    this.children = [];
    this.listeners = new Map();
    this.classList = new FakeClassList();
    this.parentNode = null;
    this.hidden = false;
    this.disabled = false;
    this.value = '';
    this.checked = false;
    this.href = '';
    this._textContent = '';
  }

  get options() {
    return this.tagName === 'SELECT' ? this.children : [];
  }

  get textContent() {
    if (this.children.length > 0) {
      return this.children.map((child) => child.textContent).join('');
    }
    return this._textContent;
  }

  set textContent(value) {
    this._textContent = String(value);
    this.children = [];
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
  }

  removeAttribute(name) {
    delete this.attributes[name];
  }

  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) || [];
    listeners.push(listener);
    this.listeners.set(type, listeners);
  }

  dispatch(type, details = {}) {
    if (type === 'click' && this.disabled) return;
    const event = {
      type,
      target: this,
      defaultPrevented: false,
      preventDefault() {
        this.defaultPrevented = true;
      },
      ...details
    };
    for (const listener of this.listeners.get(type) || []) listener.call(this, event);
  }

  click() {
    this.dispatch('click');
  }

  append(...nodes) {
    for (const node of nodes) {
      if (node && node.isFragment) {
        this.append(...node.children);
        node.children = [];
      } else if (node) {
        node.parentNode = this;
        this.children.push(node);
      }
    }
    this._textContent = '';
  }

  replaceChildren(...nodes) {
    this.children = [];
    this._textContent = '';
    this.append(...nodes);
  }
}

class FakeFragment extends FakeElement {
  constructor() {
    super('#fragment');
    this.isFragment = true;
  }
}

class FakeTemplate extends FakeElement {
  constructor() {
    super('template');
    this.content = new FakeFragment();
  }

  set innerHTML(html) {
    const cardMatch = String(html).match(/data-card="([^"]+)"[^>]*>([^<]*)/);
    const card = new FakeElement('article');
    card.dataset.card = cardMatch ? cardMatch[1] : '';
    card.textContent = cardMatch ? cardMatch[2] : String(html);
    this.content = new FakeFragment();
    this.content.append(card);
  }
}

function FakeOption(text, value) {
  const option = new FakeElement('option');
  option.text = String(text);
  option.textContent = String(text);
  option.value = String(value);
  return option;
}

function createHarness() {
  const root = new FakeElement('section');
  root.dataset.context = 'discounts';
  root.dataset.objectId = '0';
  root.dataset.contextToken = 'context-token';

  const form = new FakeElement('form');
  const category = new FakeElement('select');
  const brand = new FakeElement('select');
  const sort = new FakeElement('input');
  const popular = new FakeElement('input');
  category.append(new FakeOption('All categories', '0'));
  brand.append(new FakeOption('All brands', '0'));
  category.value = '0';
  brand.value = '0';
  sort.value = 'popular';
  popular.checked = false;

  const fields = [category, brand, sort, popular];
  const namedFields = { paf_category: category, paf_brand: brand, paf_sort: sort, paf_popular: popular };
  fields.namedItem = (name) => namedFields[name] || null;
  form.elements = fields;

  const results = new FakeElement('div');
  const initialCard = new FakeElement('article');
  initialCard.dataset.card = 'popular-1';
  initialCard.textContent = 'popular-1';
  results.append(initialCard);

  const more = new FakeElement('button');
  const status = new FakeElement('div');
  const loader = new FakeElement('span');
  const sortLinks = ['popular', 'newest', 'discussed'].map((sortName) => {
    const link = new FakeElement('a');
    link.dataset.filterSort = sortName;
    link.href = `https://example.test/discounts/?paf_sort=${sortName}`;
    return link;
  });

  const elements = {
    '[data-filter-form]': form,
    '[data-filter-results]': results,
    '[data-filter-more]': more,
    '[data-filter-status]': status,
    '[data-filter-loader]': loader
  };
  root.querySelector = (selector) => elements[selector] || null;
  root.querySelectorAll = (selector) => (selector === '[data-filter-sort]' ? sortLinks : []);

  const documentListeners = new Map();
  const document = {
    querySelectorAll(selector) {
      return selector === '[data-promokodiki-filter]' ? [root] : [];
    },
    createDocumentFragment() {
      return new FakeFragment();
    },
    createElement(tagName) {
      return tagName === 'template' ? new FakeTemplate() : new FakeElement(tagName);
    },
    createTextNode(text) {
      const node = new FakeElement('#text');
      node.textContent = text;
      return node;
    },
    addEventListener(type, listener) {
      documentListeners.set(type, listener);
    }
  };

  const windowListeners = new Map();
  const historyCalls = [];
  const window = {
    PromokodikiFilterConfig: {
      ajaxUrl: 'https://example.test/wp-admin/admin-ajax.php',
      nonce: 'nonce',
      retryLabel: 'Retry',
      loadingLabel: 'Loading',
      genericError: 'Request failed'
    },
    PromokodikiFilterState: stateApi,
    PromokodikiFilterView: viewApi,
    location: { href: 'https://example.test/discounts/?paf_sort=popular' },
    history: {
      pushState(state, title, url) {
        historyCalls.push({ mode: 'push', url: String(url) });
        window.location.href = String(url);
      },
      replaceState(state, title, url) {
        historyCalls.push({ mode: 'replace', url: String(url) });
        window.location.href = String(url);
      }
    },
    addEventListener(type, listener) {
      windowListeners.set(type, listener);
    },
    dispatch(type) {
      const listener = windowListeners.get(type);
      if (listener) listener({ type });
    }
  };

  const fetchCalls = [];
  function fetch(url, options) {
    let resolve;
    const promise = new Promise((resolvePromise) => {
      resolve = resolvePromise;
    });
    fetchCalls.push({ url, options, resolve });
    return promise;
  }

  const script = fs.readFileSync(path.resolve(__dirname, '../../assets/js/filter.js'), 'utf8');
  vm.runInNewContext(script, {
    window,
    document,
    fetch,
    Option: FakeOption,
    URL,
    URLSearchParams,
    AbortController,
    CSS: { escape: String },
    Set,
    Array,
    Error,
    TypeError
  });

  return { window, form, sort, results, more, status, sortLinks, fetchCalls, historyCalls };
}

function response(success, data) {
  return {
    ok: success,
    async json() {
      return success ? { success: true, data } : { success: false, data: { message: data } };
    }
  };
}

function payload(sort, page, card, hasMore) {
  return {
    html: `<article data-card="${card}">${card}</article>`,
    page,
    has_more: hasMore,
    total: 2,
    message: `${sort} page ${page}`,
    state: { category: '', brand: '', sort, popular: false },
    category_options: [],
    brand_options: []
  };
}

function requestKey(call) {
  const body = new URLSearchParams(call.options.body);
  return `${body.get('paf_sort')}:${body.get('paf_page')}`;
}

async function settle() {
  await Promise.resolve();
  await Promise.resolve();
  await new Promise((resolve) => setImmediate(resolve));
}

test('failed popstate cannot leak into Load More or overwrite its retry', async () => {
  const harness = createHarness();

  harness.window.location.href = 'https://example.test/discounts/?paf_sort=newest';
  harness.window.dispatch('popstate');
  assert.equal(requestKey(harness.fetchCalls[0]), 'newest:1');
  harness.fetchCalls[0].resolve(response(false, 'History failed'));
  await settle();

  const retryButton = harness.status.children.find((child) => child.tagName === 'BUTTON');
  assert.ok(retryButton, 'Failure did not render a retry button');
  harness.more.click();
  retryButton.click();

  assert.deepEqual(
    {
      cards: harness.results.children.map((card) => card.dataset.card),
      sort: harness.sort.value,
      selectedSort: harness.sortLinks.find((link) => link.getAttribute('aria-current') === 'true')?.dataset.filterSort,
      loadMoreDisabled: harness.more.disabled,
      followupRequests: harness.fetchCalls.slice(1).map(requestKey)
    },
    {
      cards: ['popular-1'],
      sort: 'popular',
      selectedSort: 'popular',
      loadMoreDisabled: true,
      followupRequests: ['newest:1']
    }
  );
  harness.fetchCalls[1].resolve(response(true, payload('newest', 1, 'newest-1', true)));
  await settle();

  assert.deepEqual(harness.results.children.map((card) => card.dataset.card), ['newest-1']);
  assert.equal(harness.more.disabled, false);
  assert.equal(harness.historyCalls.at(-1).mode, 'replace');

  harness.more.click();
  assert.equal(requestKey(harness.fetchCalls[2]), 'newest:2');
  harness.fetchCalls[2].resolve(response(true, payload('newest', 2, 'newest-2', false)));
  await settle();

  assert.deepEqual(harness.results.children.map((card) => card.dataset.card), ['newest-1', 'newest-2']);
});
