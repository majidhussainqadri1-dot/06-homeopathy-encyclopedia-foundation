'use strict';
/**
 * Actual File06 browser script, exercised with a minimal deterministic DOM
 * and deferred fetches. No network, data, or production browser involved.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(
  path.join(__dirname, '..', 'homeopathy-encyclopedia', 'assets', 'js', 'encyclopedia-v2.js'),
  'utf8'
);

function build() {
  class Element {
    constructor(tag = 'div') {
      this.tagName = tag;
      this.attrs = {};
      this.focused = false;
      this.children = [];
      this.hidden = false;
      this.value = '';
      this.dataset = {};
      this.listeners = {};
      this.textContent = '';
    }
    setAttribute(key, value) { this.attrs[key] = String(value); }
    getAttribute(key) { return this.attrs[key] ?? null; }
    replaceChildren(...nodes) { this.children = nodes; }
    append(...nodes) { this.children.push(...nodes); }
    addEventListener(type, callback) { this.listeners[type] = callback; }
    dispatch(type, event = {}) { if (this.listeners[type]) this.listeners[type](event); }
    contains(element) { return element === this || this.children.includes(element); }
    focus() { this.focused = true; }
    querySelector(selector) { return this.querySelectorAll(selector)[0] || null; }
    querySelectorAll(selector) { return selector === 'a' ? this.children.filter(el => el.tagName === 'a') : []; }
  }
  const q = new Element();
  const form = new Element();
  form.querySelector = selector => selector.includes('input') ? q : null;
  form.elements = {type: new Element(), body_system: new Element()};
  const results = new Element();
  const status = new Element();
  const more = new Element();
  const suggestions = new Element();
  const root = new Element();
  root.dataset = {limit: '20'};
  const selectorMap = {
    '[data-he-filters]': form,
    '[data-he-results]': results,
    '[data-he-status]': status,
    '[data-he-more]': more,
    '[data-he-suggestions]': suggestions
  };
  root.querySelector = selector => selectorMap[selector] || null;
  const documentEvents = {};
  const document = {
    querySelectorAll(selector) {
      return selector === '[data-he-encyclopedia]' ? [root] : [];
    },
    createElement(tag) { return new Element(tag); },
    createElementNS(ns, tag) { return new Element(tag); },
    addEventListener(type, callback) { documentEvents[type] = callback; }
  };

  const requests = [];
  const fetch = (url, options = {}) => new Promise((resolve, reject) => {
    const request = {
      url,
      options,
      resolve(body) { resolve({ok: true, json: async () => body}); },
      reject(error) { reject(error); }
    };
    requests.push(request);
    if (options.signal) {
      options.signal.addEventListener('abort', () => {
        const error = new Error('aborted');
        error.name = 'AbortError';
        reject(error);
      });
    }
  });
  const timers = [];
  const setTimeout = callback => {
    const id = timers.length;
    timers.push({callback, active: true});
    return id;
  };
  const clearTimeout = id => { if (timers[id]) timers[id].active = false; };
  const runTimers = () => {
    for (const timer of timers) {
      if (timer.active) { timer.active = false; timer.callback(); }
    }
  };
  class MockFormData {
    get(name) { return name === 'q' ? q.value : ''; }
  }
  vm.runInNewContext(source, {
    window: {heV2: {api: '/wp-json/sabri/v2/file-06'}},
    document,
    fetch,
    AbortController,
    URLSearchParams,
    FormData: MockFormData,
    setTimeout,
    clearTimeout,
    console
  }, {filename: 'encyclopedia-v2.js'});
  return {q, form, root, results, status, more, suggestions, requests, runTimers, documentEvents};
}
const drain = () => new Promise(resolve => setImmediate(resolve));
async function tests() {
  let count = 0;
  const check = (condition, label) => {
    assert.ok(condition, label);
    count++;
  };
  const h = build();
  check(h.requests.length === 1, 'Initial list request exists');
  const oldList = h.requests[0];
  h.q.value = 'new search';
  h.form.dispatch('submit', {preventDefault() {}});
  check(h.requests.length === 2, 'New search starts a second request');
  await drain();
  check(h.results.getAttribute('aria-busy') === 'true',
    'Aborted old request cannot clear the new request busy indicator');
  h.requests[1].resolve({items: [], next_cursor: null});
  await drain();
  check(h.results.getAttribute('aria-busy') === 'false',
    'Current completed request clears busy indicator once');
  check(oldList.options.signal.aborted === true, 'Old list request was aborted');

  const a = build();
  a.requests[0].resolve({items: [], next_cursor: null});
  await drain();
  a.q.value = 'vita';
  a.q.dispatch('input');
  a.runTimers();
  check(a.requests.length === 2, 'First autocomplete request started');
  const stale = a.requests[1];
  a.q.value = 'vitamin';
  a.q.dispatch('input');
  a.runTimers();
  check(a.requests.length === 3, 'Second autocomplete request started');
  a.requests[2].resolve([{label: 'Vitamin fresh', url: '/fresh'}]);
  await drain();
  check(a.suggestions.children[0]?.textContent === 'Vitamin fresh',
    'New autocomplete result is shown');
  stale.resolve([{label: 'Vital stale', url: '/stale'}]);
  await drain();
  check(a.suggestions.children[0]?.textContent === 'Vitamin fresh',
    'Late response from earlier query cannot overwrite latest suggestions');
  check(a.suggestions.hidden === false, 'Fresh suggestion remains available');
  check(a.q.getAttribute('aria-expanded') === 'true',
    'Autocomplete announces open suggestions to assistive technology');
  a.q.dispatch('keydown', {key: 'ArrowDown', preventDefault() {}});
  check(a.suggestions.children[0].focused === true,
    'Keyboard ArrowDown focuses the first real suggestion link');
  a.suggestions.dispatch('keydown', {key: 'Escape', preventDefault() {}});
  check(a.suggestions.hidden === true &&
    a.q.getAttribute('aria-expanded') === 'false' && a.q.focused === true,
    'Escape from a suggestion closes the popup and restores search input focus');

  const php = fs.readFileSync(path.join(__dirname, '..',
    'homeopathy-encyclopedia', 'includes', 'class-he-v2-public.php'), 'utf8');
  check(!/data-he-suggestions hidden><\\/div><\\/label>/.test(php),
    'Suggestion links are not nested inside a form label');
  check(/aria-expanded="false"/.test(php),
    'Initial HTML search field describes collapsed suggestion state');
  check(/role="group"[^>]*data-he-suggestions/.test(php),
    'Suggestion links have an accessible named grouping');

  console.log('PASS File06 browser search race checks: ' + count + ' assertions.');
}
tests().catch(error => {
  console.error('FAIL File06 browser race:', error.message);
  process.exitCode = 1;
});
