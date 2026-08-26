(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    root.PromokodikiFilterView = api;
  }
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  function syncSortLinks(links, sort) {
    links.forEach((link) => {
      const selected = link.dataset.filterSort === sort;
      link.classList.toggle('tabs__nav-btn--active', selected);
      if (selected) {
        link.setAttribute('aria-current', 'true');
      } else {
        link.removeAttribute('aria-current');
      }
    });
  }

  function setSortLinksDisabled(links, disabled) {
    links.forEach((link) => {
      if (disabled) {
        link.setAttribute('aria-disabled', 'true');
      } else {
        link.removeAttribute('aria-disabled');
      }
    });
  }

  return { syncSortLinks, setSortLinksDisabled };
}));
