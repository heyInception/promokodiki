const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

test('expired server window refreshes the shared snapshot with the interaction nonce', async () => {
  const scriptPath = path.join(__dirname, '..', 'js', 'top-promocodes.js');
  const script = fs.existsSync(scriptPath) ? fs.readFileSync(scriptPath, 'utf8') : '';
  const handlers = {};
  const fetchCalls = [];
  const container = {
    classList: { add() {}, remove() {} },
    dataset: { nextUpdate: '100', serverTime: '100' },
    innerHTML: '',
  };
  const nodes = {
    'popular-promocodes-container': container,
    topHours: { textContent: '' },
    topMinutes: { textContent: '' },
    topSeconds: { textContent: '' },
  };
  const context = {
    Date: { now: () => 100000 },
    URLSearchParams,
    document: {
      addEventListener(type, callback) { handlers[type] = callback; },
      getElementById(id) { return nodes[id] || null; },
    },
    fetch(url, options) {
      fetchCalls.push({ url, options });
      return Promise.resolve({ json: async () => ({ success: true, data: { html: '<article>new</article>', next_update: 10900 } }) });
    },
    setInterval() {},
    window: { PromokodikiInteractions: { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: 'filter-nonce' } },
  };

  vm.runInNewContext(script, context);
  assert.equal(typeof handlers.DOMContentLoaded, 'function');
  handlers.DOMContentLoaded();
  await new Promise((resolve) => setImmediate(resolve));

  assert.equal(container.innerHTML, '<article>new</article>');
  assert.match(fetchCalls[0].options.body.toString(), /action=promokodiki_top_snapshot/);
  assert.match(fetchCalls[0].options.body.toString(), /nonce=filter-nonce/);
});
