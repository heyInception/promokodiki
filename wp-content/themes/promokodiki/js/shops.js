(function () {
	'use strict';

	function normalize(value) {
		return String(value || '').trim().toLocaleLowerCase('ru-RU');
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
		module.exports = { initShopSearch: initShopSearch, normalize: normalize };
	}

	if (typeof document !== 'undefined') {
		document.addEventListener('DOMContentLoaded', function () { initShopSearch(document); });
	}
}());
