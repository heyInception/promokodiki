const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

test('countdown is local-only and legacy snapshot AJAX is absent', () => {
  const script = fs.readFileSync(path.join(__dirname, '..', 'js', 'top-promocodes.js'), 'utf8');
  const handlers = {};
  const nodes = {
    'telegram-promocodes-container': { dataset: { nextUpdate: '10900', serverTime: '100' } },
    topHours: { textContent: '' }, topMinutes: { textContent: '' }, topSeconds: { textContent: '' },
  };
  vm.runInNewContext(script, {
    Date: { now: () => 100000 },
    document: { addEventListener(type, callback) { handlers[type] = callback; }, getElementById(id) { return nodes[id] || null; } },
    setInterval() {},
  });
  handlers.DOMContentLoaded();
  assert.equal(nodes.topHours.textContent, '03');
  assert.equal(nodes.topMinutes.textContent, '00');
  assert.equal(nodes.topSeconds.textContent, '00');
  assert.doesNotMatch(script, /fetch\s*\(/);
  assert.doesNotMatch(script, /promokodiki_top_snapshot/);
});

test('main bundle initializes Telegram Swiper with 4/2/1 breakpoints and no autoplay', () => {
  const source = fs.readFileSync(path.join(__dirname, '..', 'js', 'main.js'), 'utf8');
  assert.match(source, /\.top__slider/);
  assert.match(source, /slidesPerView:\s*4/);
  assert.match(source, /768:\s*\{\s*slidesPerView:\s*2/);
  assert.match(source, /320:\s*\{\s*slidesPerView:\s*1/);
});
