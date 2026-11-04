const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function worker(network) {
  const handlers = {};
  const saved = [];
  const deleted = [];
  const fallback = { offline: true };
  const cache = { addAll: async requests => saved.push(...requests), match: async path => path === '/offline.html' ? fallback : undefined };
  const self = { location: { origin: 'https://motel.test' }, addEventListener: (name, callback) => handlers[name] = callback, skipWaiting: async () => {}, clients: { claim: async () => {} } };
  class PublicRequest { constructor(path, options) { this.path = path; Object.assign(this, options); } }
  const context = { self, URL, Request: PublicRequest, Response: { error: () => ({ error: true }) }, fetch: network, caches: { open: async () => cache, keys: async () => ['motel-offline-v0', 'motel-offline-v1', 'other-app-cache'], delete: async key => deleted.push(key) } };
  vm.runInNewContext(fs.readFileSync('public/sw.js', 'utf8'), context);
  return { handlers, saved, deleted, fallback };
}
function fetchEvent(worker, options = {}) {
  let response;
  worker.handlers.fetch({ request: { method: 'GET', mode: 'navigate', url: 'https://motel.test/rooms', ...options }, respondWith: value => response = value });
  return response;
}
test('precache includes only public files, without session credentials', async () => {
  const w = worker();
  let pending;
  w.handlers.install({ waitUntil: value => pending = value });
  await pending;
  assert.deepEqual(w.saved.map(item => item.path), ['/offline.html', '/icons/motel-192.png', '/icons/motel-512.png']);
  assert.ok(w.saved.every(item => item.credentials === 'omit'));
});
test('private pages stay on network; only a network failure shows offline fallback', async () => {
  const live = { status: 200, private: true };
  const w = worker(async () => live);
  assert.equal(await fetchEvent(w), live);
  assert.equal(w.saved.length, 0);
  const failed = worker(async () => { throw new Error('offline'); });
  assert.equal(await fetchEvent(failed), failed.fallback);
  const forbidden = { status: 403 };
  assert.equal(await fetchEvent(worker(async () => forbidden)), forbidden);
});
test('submissions, downloads, photos and external requests are never intercepted', () => {
  const w = worker(() => { throw new Error('must not fetch'); });
  for (const request of [{ method: 'POST' }, { method: 'PUT' }, { method: 'PATCH' }, { method: 'DELETE' }, { mode: 'cors', url: 'https://motel.test/users/1/photo' }, { mode: 'same-origin', url: 'https://motel.test/reports/monthly/statements' }, { url: 'https://other.test/' }]) {
    assert.equal(fetchEvent(w, request), undefined);
  }
});
test('activation deletes only obsolete motel offline caches', async () => {
  const w = worker();
  let pending;
  w.handlers.activate({ waitUntil: value => pending = value });
  await pending;
  assert.deepEqual(w.deleted, ['motel-offline-v0']);
});
