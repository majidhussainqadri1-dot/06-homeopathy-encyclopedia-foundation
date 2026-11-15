'use strict';
/**
 * Source-executed synthetic browser regression (no network, patients or DOM deps).
 * The slower request must not replace the latest search or reset ARIA busy state.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

class FakeNode {
  constructor(tag = 'div') {
    this.tagName = tag; this.attributes = {}; this.children = [];
    this.dataset = {}; this.listeners = {}; this.hidden = false;
    this.value = ''; this.textContent = '';
  }
  setAttribute(k, v) { this.attributes[k] = String(v); }
  getAttribute(k) { return this.attributes[k]; }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
  addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
  fire(name, event = {}) { (this.listeners[name] || []).forEach(fn => fn(event)); }
  contains(node) { return this === node || this.children.includes(node); }
}
const form = new FakeNode('form'), results = new FakeNode(),
  status = new FakeNode(), more = new FakeNode('button'),
  suggestions = new FakeNode(), q = new FakeNode('input');
form.elements = { type: new FakeNode('input'), body_system: new FakeNode('input') };
form.querySelectorAll = () => [];
form.querySelector = sel => sel === 'input[name="q"]' ? q : null;
const root = new FakeNode('section');
root.dataset = { limit: '20', type: '', system: '' };
root.querySelector = sel => ({
  '[data-he-filters]': form, '[data-he-results]': results,
  '[data-he-status]': status, '[data-he-more]': more,
  '[data-he-suggestions]': suggestions,
})[sel] || null;
root.querySelectorAll = () => [];
const document = {
  querySelectorAll(sel) { return sel === '[data-he-encyclopedia]' ? [root] : []; },
  createElement(tag) { return new FakeNode(tag); },
  createElementNS(_ns, tag) { return new FakeNode(tag); },
  addEventListener() {},
};
const requests = [];
const pendingTimers = [];
const context = {
  document, window: { heV2: { api: 'https://example.test/wp-json/he/v2' } },
  FormData: class { get() { return ''; } }, URLSearchParams, AbortController,
  encodeURIComponent, console,
  setTimeout(fn) { const t = { fn, active: true }; pendingTimers.push(t); return t; },
  clearTimeout(t) { if (t) t.active = false; },
  fetch(url, options = {}) {
    return new Promise((resolve, reject) => {
      // Intentionally permit a late response after abort to prove last-write-wins.
      requests.push({ url, options, resolve, reject });
    });
  },
};
const source = fs.readFileSync(path.join(__dirname, '../homeopathy-encyclopedia/assets/js/encyclopedia-v2.js'), 'utf8');
vm.runInNewContext(source, context, { filename: 'encyclopedia-v2.js' });
const flush = async () => { for(let i=0;i<6;i++) await Promise.resolve(); };
const answer = (request, items) => request.resolve({
  ok: true, json: () => Promise.resolve({ items, next_cursor: 0 }),
});
const collectText = node => [
  node.textContent || '', ...(node.children || []).flatMap(collectText),
].join(' ');
(async () => {
  assert.equal(requests.length, 1, 'Initial search starts exactly once');
  assert.equal(results.getAttribute('aria-busy'), 'true');
  form.fire('submit', { preventDefault() {} });
  assert.equal(requests.length, 2, 'Second search started');
  answer(requests[0], [{ title: 'OLD_STALE_RESULT', type: 'remedy', version: 1, language: 'en-US' }]);
  await flush();
  assert.equal(results.getAttribute('aria-busy'), 'true',
    'Stale request must not reset live-region busy while the latest request is pending');
  assert.equal(collectText(results).includes('OLD_STALE_RESULT'), false,
    'Stale search response must not populate current results');
  answer(requests[1], [{ title: 'NEW_CURRENT_RESULT', type: 'remedy', version: 2, language: 'en-US' }]);
  await flush();
  assert.equal(results.getAttribute('aria-busy'), 'false');
  assert.ok(collectText(results).includes('NEW_CURRENT_RESULT'), 'Latest results visible');

  // A cleared query must stay cleared even when an older autocomplete returns.
  q.value = 'arn'; q.fire('input');
  const timer = pendingTimers.find(t => t.active);
  assert.ok(timer, 'Autocomplete debounce scheduled');
  timer.fn();
  const oldSuggestion = requests[2];
  assert.ok(oldSuggestion.url.includes('/autocomplete?'));
  q.value = ''; q.fire('input');
  assert.equal(suggestions.hidden, true, 'Cleared search hides suggestions immediately');
  oldSuggestion.resolve({ ok: true, json: () => Promise.resolve([
    { label: 'STALE_AUTOCOMPLETE', url: '/old' },
  ]) });
  await flush();
  assert.equal(suggestions.hidden, true,
    'An older in-flight autocomplete cannot re-open suggestions after a query is cleared');
  assert.equal(collectText(suggestions).includes('STALE_AUTOCOMPLETE'), false,
    'An older in-flight autocomplete cannot inject stale suggestions');
  console.log('File06 JS concurrency: 7 source-executed ordering/accessibility assertions PASS.');
})().catch(error => { console.error(error.stack || error); process.exitCode = 1; });
