const test = require('node:test');
const assert = require('node:assert/strict');
const view = require('../../assets/js/filter-view.js');

function sortLink(sort) {
  const attributes = {};
  const classes = new Set();

  return {
    dataset: { filterSort: sort },
    attributes,
    classes,
    setAttribute(name, value) {
      attributes[name] = value;
    },
    removeAttribute(name) {
      delete attributes[name];
    },
    classList: {
      toggle(name, force) {
        if (force) {
          classes.add(name);
        } else {
          classes.delete(name);
        }
      }
    }
  };
}

test('selected sort link receives current and active states exclusively', () => {
  const links = ['popular', 'newest', 'discussed'].map(sortLink);

  view.syncSortLinks(links, 'discussed');

  assert.equal(links[2].attributes['aria-current'], 'true');
  assert.equal(links[2].classes.has('tabs__nav-btn--active'), true);
  assert.equal('aria-current' in links[0].attributes, false);
  assert.equal(links[0].classes.has('tabs__nav-btn--active'), false);
});

test('disabled sort-link state can be applied and removed', () => {
  const links = ['popular', 'newest', 'discussed'].map(sortLink);

  view.setSortLinksDisabled(links, true);
  assert.equal(links.every((link) => link.attributes['aria-disabled'] === 'true'), true);

  view.setSortLinksDisabled(links, false);
  assert.equal(links.every((link) => !('aria-disabled' in link.attributes)), true);
});
