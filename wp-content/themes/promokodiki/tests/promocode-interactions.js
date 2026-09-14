const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

const script = fs.readFileSync(path.join(__dirname, '..', 'js', 'promocode-modal.js'), 'utf8');

function classList() {
  const values = new Set();
  return {
    add: (...names) => names.forEach((name) => values.add(name)),
    remove: (...names) => names.forEach((name) => values.delete(name)),
    contains: (name) => values.has(name),
  };
}

function element(properties = {}) {
  return Object.assign({
    addEventListener(type, handler) { this.handlers[type] = handler; },
    classList: classList(),
    dataset: {},
    handlers: {},
	attributes: {},
	setAttribute(name, value) { this.attributes[name] = String(value); },
	focus() { this.focused = true; },
    style: {},
    textContent: '',
    value: '',
  }, properties);
}

function harness(fetchImplementation = () => new Promise(() => {}), clipboardWrite = async () => {}) {
  const modal = element({
    querySelector(selector) {
      return selector === '.modal-promocode__close' ? closeButton : overlay;
    },
  });
  const closeButton = element();
  const overlay = element();
  const title = element();
  const code = element({ select() {} });
  const link = element();
  const used = element();
  const expiry = element();
  const description = element();
  const copy = element();
	const copyStatus = element();
	const unavailable = element({ hidden: true });
  const logo = element();
  const ids = {
    promocodeModal: modal,
    modalPromoTitle: title,
    modalPromoCode: code,
    modalPromoLink: link,
    modalPromoUsed: used,
    modalPromoExpiry: expiry,
    modalPromoDesc: description,
    copyPromoBtn: copy,
	modalPromoCopyStatus: copyStatus,
	modalPromoLinkUnavailable: unavailable,
    modalPromoLogo: logo,
  };
  const documentHandlers = {};
  const opened = [];
  const fetchCalls = [];
  let cards = [];
  const document = {
    body: { style: {} },
    addEventListener(type, handler) { documentHandlers[type] = handler; },
    execCommand() { return true; },
    getElementById(id) { return ids[id] || null; },
    querySelector() { return cards[0] || null; },
    querySelectorAll() { return cards; },
  };
  const window = {
    PromokodikiInteractions: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'filter-nonce' },
    open(url, target, features) { opened.push({ url, target, features }); return {}; },
  };
  const context = {
    URLSearchParams,
    document,
    fetch(url, options) { fetchCalls.push({ url, options }); return fetchImplementation(url, options); },
    navigator: { clipboard: { writeText: clipboardWrite } },
    setTimeout,
    window,
  };
  vm.runInNewContext(script, context);
  documentHandlers.DOMContentLoaded();

  function card(overrides = {}) {
    return element({
      dataset: Object.assign({
        code: 'SAVE20',
        description: 'Описание',
        expired: 'false',
        expiry: '31.12.2026',
        postId: '42',
        storeUrl: 'https://shop.example/offer',
      }, overrides),
      querySelector(selector) {
        if (selector === '.promocodes__title') return { textContent: 'Скидка 20%' };
        if (selector === '.promocodes__used') return { textContent: '7 Применено' };
        if (selector === '.promocodes__imgs img') return { src: 'https://cdn.example/logo.png', alt: 'Магазин' };
        return null;
      },
    });
  }

  function click(target) {
    const event = { target, preventDefault() { this.defaultPrevented = true; } };
    documentHandlers.click(event);
    return event;
  }

	return { card, click, closeButton, copy, copyStatus, documentHandlers, expiry, fetchCalls, link, modal, opened, title, unavailable, setCards(value) { cards = value; }, window };
}

test('direct store action opens immediately without showing the modal', () => {
  const ui = harness();
  const offer = ui.card({ code: '' });
  const storeLink = element({
    closest(selector) {
      if (selector === '.promocodes__link') return this;
      if (selector === '.promocodes__item, .top__item') return offer;
      return null;
    },
  });

  ui.click(storeLink);

  assert.equal(ui.opened[0].url, 'https://shop.example/offer');
  assert.equal(ui.modal.classList.contains('show'), false);
  assert.equal(ui.fetchCalls.length, 1);
  assert.match(ui.fetchCalls[0].options.body.toString(), /nonce=filter-nonce/);
});

test('modal shows expiry and store action is not blocked by failed tracking', () => {
  const ui = harness(() => Promise.reject(new Error('forbidden')));
  const offer = ui.card();
  const viewButton = element({
    closest(selector) {
      if (selector === '.promocodes__view') return this;
      if (selector === '.promocodes__item, .top__item') return offer;
      return null;
    },
  });

  ui.click(viewButton);
  assert.equal(ui.expiry.textContent, '31.12.2026');
  assert.equal(ui.modal.classList.contains('show'), true);
	assert.equal(ui.modal.attributes['aria-hidden'], 'false');

  ui.link.handlers.click({ preventDefault() {} });
  assert.equal(ui.opened[0].url, 'https://shop.example/offer');
  assert.equal(ui.modal.classList.contains('show'), true);
});

test('opening a modal does not copy until the user requests it', async () => {
	const ui = harness();
	const offer = ui.card();
	const viewButton = element({ closest(selector) { return selector === '.promocodes__view' ? this : selector === '.promocodes__item, .top__item' ? offer : null; } });
	ui.click(viewButton);
	assert.equal(ui.copyStatus.textContent, '');
	ui.copy.handlers.click({ currentTarget: ui.copy, target: ui.copy });
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(ui.copyStatus.textContent, 'Промокод скопирован');
});

test('missing store URL disables the transition in the modal', () => {
	const ui = harness();
	const offer = ui.card({ storeUrl: '' });
	const viewButton = element({ closest(selector) { return selector === '.promocodes__view' ? this : selector === '.promocodes__item, .top__item' ? offer : null; } });
	ui.click(viewButton);
	assert.equal(ui.link.hidden, true);
	assert.equal(ui.unavailable.hidden, false);
});

test('clipboard rejection selects the code and offers manual copying', async () => {
	const ui = harness(undefined, async () => { throw new Error('denied'); });
	const offer = ui.card();
	const viewButton = element({ closest(selector) { return selector === '.promocodes__view' ? this : selector === '.promocodes__item, .top__item' ? offer : null; } });
	ui.click(viewButton);
	ui.copy.handlers.click({ currentTarget: ui.copy, target: ui.copy });
	await new Promise((resolve) => setImmediate(resolve));
	assert.equal(ui.copyStatus.textContent, 'Не удалось скопировать. Нажмите Ctrl+C');
});

test('expired offer cannot open and Escape closes an open modal and returns focus', () => {
	const ui = harness();
	ui.window.openPromoModal = ui.window.openPromoModal;
	const expired = ui.card({ expired: 'true' });
	ui.setCards([expired]);
	ui.window.openPromoModal('42');
	assert.equal(ui.modal.classList.contains('show'), false);

	const current = ui.card();
	const viewButton = element({ closest(selector) { return selector === '.promocodes__view' ? this : selector === '.promocodes__item, .top__item' ? current : null; } });
	ui.click(viewButton);
	ui.documentHandlers.keydown({ key: 'Escape' });
	assert.equal(ui.modal.attributes['aria-hidden'], 'true');
	assert.equal(viewButton.focused, true);
});

test('long modal title is preserved in full', () => {
	const ui = harness();
	const longTitle = 'Очень длинный заголовок '.repeat(20).trim();
	const offer = ui.card();
	offer.querySelector = (selector) => selector === '.promocodes__title, .top__head' ? { textContent: longTitle } : selector === '.promocodes__used, .top__quantity' ? { textContent: '0' } : null;
	ui.setCards([offer]);
	ui.window.openPromoModal('42');
	assert.equal(ui.title.textContent, longTitle);
});

test('vote response synchronizes counts and active reaction across card instances', async () => {
  const ui = harness(() => Promise.resolve({
    json: async () => ({ success: true, data: { likes: 9, dislikes: 2, reaction: 'like' } }),
  }));
  const yesOne = element();
  const noOne = element();
  const yesTwo = element();
  const noTwo = element();
  const first = ui.card();
  const second = ui.card();
  first.querySelector = (selector) => selector === '.promocodes__like_yes' ? yesOne : selector === '.promocodes__like_no' ? noOne : null;
  second.querySelector = (selector) => selector === '.promocodes__like_yes' ? yesTwo : selector === '.promocodes__like_no' ? noTwo : null;
  ui.setCards([first, second]);
  const like = element({
    dataset: { action: 'like', postId: '42' },
    closest(selector) {
      if (selector === '.promocodes__like') return this;
      if (selector === '.promocodes__item, .top__item') return first;
      return null;
    },
  });

  ui.click(like);
  await new Promise((resolve) => setImmediate(resolve));

  assert.equal(yesOne.textContent, '9');
  assert.equal(noOne.textContent, '2');
  assert.equal(yesTwo.classList.contains('is-active'), true);
  assert.equal(noTwo.classList.contains('is-active'), false);
  assert.match(ui.fetchCalls[0].options.body.toString(), /action=promokodiki_promo_vote/);
});
