(function () {
	'use strict';

	function normalize(value) {
		return String(value || '').trim().toLocaleLowerCase('ru-RU');
	}

	function highlightParts(label, query) {
		var original = String(label || '');
		var normalizedQuery = normalize(query);
		var index = normalizedQuery ? original.toLocaleLowerCase('ru-RU').indexOf(normalizedQuery) : -1;
		if (index < 0) {
			return [{ text: original, matched: false }];
		}
		return [
			{ text: original.slice(0, index), matched: false },
			{ text: original.slice(index, index + normalizedQuery.length), matched: true },
			{ text: original.slice(index + normalizedQuery.length), matched: false }
		].filter(function (part) { return part.text.length > 0; });
	}

	function renderHighlight(item, query, documentObject) {
		var label = item.getAttribute('data-shop-label') || item.textContent || '';
		if (!item.getAttribute('data-shop-label')) {
			item.setAttribute('data-shop-label', label);
		}
		while (item.firstChild) {
			item.removeChild(item.firstChild);
		}
		highlightParts(label, query).forEach(function (part) {
			var node = documentObject.createTextNode(part.text);
			if (!part.matched) {
				item.appendChild(node);
				return;
			}
			var mark = documentObject.createElement('mark');
			mark.className = 'alphabetical__match';
			mark.appendChild(node);
			item.appendChild(mark);
		});
	}

	function initShopSearch(documentObject) {
		var input = documentObject.getElementById('shops-search-input');
		var form = documentObject.getElementById('shops-search-form');
		var container = documentObject.getElementById('shops-list-container');
		if (!input || !form || !container) {
			return;
		}

		var items = Array.prototype.slice.call(container.querySelectorAll('[data-shop-name]'));
		var groups = Array.prototype.slice.call(container.querySelectorAll('[data-letter-group]'));
		var empty = container.querySelector('.alphabetical__empty');

		function filter() {
			var query = normalize(input.value);
			var visible = 0;
			items.forEach(function (item) {
				var matches = !query || normalize(item.getAttribute('data-shop-name')).indexOf(query) !== -1;
				item.hidden = !matches;
				if (matches && typeof documentObject.createTextNode === 'function') {
					renderHighlight(item, query, documentObject);
				}
				visible += matches ? 1 : 0;
			});
			groups.forEach(function (group) {
				group.hidden = !group.querySelector('[data-shop-name]:not([hidden])');
			});
			if (empty) {
				empty.hidden = visible > 0;
			}
		}

		input.addEventListener('input', filter);
		form.addEventListener('submit', function (event) {
			if (normalize(input.value).length > 0) {
				event.preventDefault();
				filter();
			}
		});
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { initShopSearch: initShopSearch, normalize: normalize, highlightParts: highlightParts };
	}

	if (typeof document !== 'undefined') {
		document.addEventListener('DOMContentLoaded', function () { initShopSearch(document); });
	}
}());
