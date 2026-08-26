'use strict';

const path = require('node:path');
const shops = require(path.resolve(__dirname, '../../../../themes/promokodiki/js/shops.js'));

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
}

function item(name) {
	return {
		hidden: false,
		getAttribute(attribute) { return attribute === 'data-shop-name' ? name : ''; },
	};
}

const alpha = item('альфа');
const beta = item('beta');
const group = {
	hidden: false,
	querySelector() { return [alpha, beta].find((entry) => !entry.hidden) || null; },
};
const empty = { hidden: true };
const handlers = {};
const input = {
	value: '',
	addEventListener(event, callback) { handlers[event] = callback; },
};
const form = { addEventListener(event, callback) { handlers[event] = callback; } };
const container = {
	querySelectorAll(selector) { return selector === '[data-shop-name]' ? [alpha, beta] : [group]; },
	querySelector() { return empty; },
};
const documentMock = {
	getElementById(id) { return { 'shops-search-input': input, 'shops-search-form': form, 'shops-list-container': container }[id]; },
};

shops.initShopSearch(documentMock);
input.value = 'АЛЬ';
handlers.input();
assert(alpha.hidden === false, 'matching Cyrillic shop should stay visible');
assert(beta.hidden === true, 'non-matching shop should be hidden');
assert(group.hidden === false && empty.hidden === true, 'matching group should stay visible');

input.value = 'нет';
handlers.input();
assert(group.hidden === true && empty.hidden === false, 'empty state should be shown');
assert(shops.normalize(' ТЕСТ ') === 'тест', 'search normalization should trim and lowercase');

const parts = shops.highlightParts('Shop Alpha', 'alpha');
assert(parts.length === 2 && parts[1].text === 'Alpha' && parts[1].matched === true, 'matched substring should be isolated for highlighting');
assert(shops.highlightParts('Shop Alpha', '').length === 1, 'clearing search should restore the original label');

console.log('PASS shop catalogue client search');
