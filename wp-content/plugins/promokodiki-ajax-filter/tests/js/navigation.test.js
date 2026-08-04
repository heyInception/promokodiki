const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

class FakeClassList {
  constructor(names = []) {
    this.names = new Set(names);
  }

  add(name) {
    this.names.add(name);
  }

  remove(name) {
    this.names.delete(name);
  }

  contains(name) {
    return this.names.has(name);
  }

  toggle(name, force) {
    const enabled = force === undefined ? !this.names.has(name) : Boolean(force);
    if (enabled) this.names.add(name);
    else this.names.delete(name);
    return enabled;
  }
}

class FakeNode {
  constructor(document, tagName, classes = []) {
    this.document = document;
    this.tagName = tagName.toUpperCase();
    this.classList = new FakeClassList(classes);
    this.children = [];
    this.parentNode = null;
    this.attributes = {};
    this.listeners = new Map();
    this.style = {};
  }

  append(...children) {
    children.forEach((child) => {
      child.parentNode = this;
      this.children.push(child);
    });
    return this;
  }

  setAttribute(name, value) {
    this.attributes[name] = String(value);
  }

  getAttribute(name) {
    return Object.prototype.hasOwnProperty.call(this.attributes, name) ? this.attributes[name] : null;
  }

  addEventListener(type, listener) {
    const listeners = this.listeners.get(type) || [];
    listeners.push(listener);
    this.listeners.set(type, listeners);
  }

  dispatch(type, details = {}) {
    const event = {
      type,
      target: details.target || this,
      relatedTarget: details.relatedTarget || null,
      key: details.key || '',
      defaultPrevented: false,
      preventDefault() {
        this.defaultPrevented = true;
      }
    };
    for (const listener of this.listeners.get(type) || []) listener.call(this, event);
    return event;
  }

  focus() {
    this.document.activeElement = this;
  }

  contains(node) {
    if (!node) return false;
    if (node === this) return true;
    return this.children.some((child) => child.contains(node));
  }

  getElementsByTagName(tagName) {
    const wanted = tagName.toUpperCase();
    const descendants = [];
    for (const child of this.children) {
      if (child.tagName === wanted) descendants.push(child);
      descendants.push(...child.getElementsByTagName(tagName));
    }
    return descendants;
  }

  querySelector(selector) {
    if (selector === '.menu-toggle') return this.find((node) => node.classList.contains('menu-toggle'));
    if (selector === '.nav-menu') return this.find((node) => node.classList.contains('nav-menu'));
    if (selector === 'a') return this.find((node) => node.tagName === 'A');
    return null;
  }

  querySelectorAll(selector) {
    if (selector === '.menu-item-has-children, .page_item_has_children') {
      return this.findAll((node) => node.classList.contains('menu-item-has-children') || node.classList.contains('page_item_has_children'));
    }
    if (selector === '.menu-item-has-children > a, .page_item_has_children > a') {
      return this.findAll((node) => node.classList.contains('menu-item-has-children') || node.classList.contains('page_item_has_children'))
        .map((item) => item.children.find((child) => child.tagName === 'A'))
        .filter(Boolean);
    }
    return [];
  }

  find(predicate) {
    for (const child of this.children) {
      if (predicate(child)) return child;
      const nested = child.find(predicate);
      if (nested) return nested;
    }
    return null;
  }

  findAll(predicate) {
    const matches = [];
    for (const child of this.children) {
      if (predicate(child)) matches.push(child);
      matches.push(...child.findAll(predicate));
    }
    return matches;
  }
}

function createHarness() {
  const documentListeners = new Map();
  const document = {
    activeElement: null,
    addEventListener(type, listener) {
      const listeners = documentListeners.get(type) || [];
      listeners.push(listener);
      documentListeners.set(type, listeners);
    },
    dispatch(type, target) {
      for (const listener of documentListeners.get(type) || []) listener({ type, target });
    },
    getElementById(id) {
      return id === 'site-navigation' ? navigation : null;
    },
    querySelector() {
      return navigation;
    }
  };

  const navigation = new FakeNode(document, 'nav', ['main-navigation']);
  const toggle = new FakeNode(document, 'button', ['menu-toggle']);
  toggle.setAttribute('aria-expanded', 'false');
  const menu = new FakeNode(document, 'ul', ['nav-menu']);

  function parentItem() {
    const item = new FakeNode(document, 'li', ['menu-item-has-children']);
    const link = new FakeNode(document, 'a');
    const submenu = new FakeNode(document, 'ul', ['sub-menu']);
    const childItem = new FakeNode(document, 'li');
    const childLink = new FakeNode(document, 'a');
    childItem.append(childLink);
    submenu.append(childItem);
    item.append(link, submenu);
    return { item, link, childLink };
  }

  const first = parentItem();
  const second = parentItem();
  menu.append(first.item, second.item);
  navigation.append(toggle, menu);
  const outside = new FakeNode(document, 'div');
  document.activeElement = outside;

  const script = fs.readFileSync(path.resolve(__dirname, '../../../../themes/promokodiki/js/navigation.js'), 'utf8');
  vm.runInNewContext(script, { document, Array });

  return { document, navigation, toggle, first, second, outside };
}

test('dropdown navigation exposes mouse focus keyboard and touch state', () => {
  const harness = createHarness();
  const observed = {};

  observed.hasPopup = harness.first.link.getAttribute('aria-haspopup');
  observed.initialExpanded = harness.first.link.getAttribute('aria-expanded');

  harness.first.item.dispatch('mouseenter');
  observed.mouseOpened = harness.first.item.classList.contains('focus');
  harness.document.activeElement = harness.outside;
  harness.first.item.dispatch('mouseleave');
  observed.mouseClosed = !harness.first.item.classList.contains('focus');

  harness.first.item.dispatch('focusin', { target: harness.first.link });
  observed.focusOpened = harness.first.item.classList.contains('focus');
  harness.first.item.dispatch('focusout', { relatedTarget: harness.outside });
  observed.focusClosed = !harness.first.item.classList.contains('focus');

  const arrow = harness.first.link.dispatch('keydown', { key: 'ArrowDown' });
  observed.arrowPrevented = arrow.defaultPrevented;
  observed.childFocused = harness.document.activeElement === harness.first.childLink;
  const escape = harness.first.item.dispatch('keydown', { key: 'Escape', target: harness.first.childLink });
  observed.escapePrevented = escape.defaultPrevented;
  observed.escapeClosed = !harness.first.item.classList.contains('focus');
  observed.parentRefocused = harness.document.activeElement === harness.first.link;

  let touchError = '';
  let firstTouch;
  let secondTouch;
  try {
    firstTouch = harness.first.link.dispatch('touchstart');
    secondTouch = harness.first.link.dispatch('touchstart');
  } catch (error) {
    touchError = error.message;
  }
  observed.touchError = touchError;
  observed.firstTouchPrevented = firstTouch ? firstTouch.defaultPrevented : false;
  observed.secondTouchFollowsLink = secondTouch ? !secondTouch.defaultPrevented : false;
  observed.touchOpened = harness.first.item.classList.contains('focus');

  assert.deepEqual(observed, {
    hasPopup: 'true',
    initialExpanded: 'false',
    mouseOpened: true,
    mouseClosed: true,
    focusOpened: true,
    focusClosed: true,
    arrowPrevented: true,
    childFocused: true,
    escapePrevented: true,
    escapeClosed: true,
    parentRefocused: true,
    touchError: '',
    firstTouchPrevented: true,
    secondTouchFollowsLink: true,
    touchOpened: true
  });
});

test('small-screen menu toggle opens and closes outside the live navigation', () => {
  const harness = createHarness();

  harness.toggle.dispatch('click');
  assert.equal(harness.navigation.classList.contains('toggled'), true);
  assert.equal(harness.toggle.getAttribute('aria-expanded'), 'true');

  harness.document.dispatch('click', harness.outside);
  assert.equal(harness.navigation.classList.contains('toggled'), false);
  assert.equal(harness.toggle.getAttribute('aria-expanded'), 'false');
});
