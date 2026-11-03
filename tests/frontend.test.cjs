const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function checkoutForm(arrival) {
  const listeners = {};
  const checkIn = { value: arrival, validity: { valid: true }, addEventListener: (name, callback) => listeners[name] = callback };
  const checkOut = { value: '2026-11-01T10:00', removeAttribute: name => delete checkOut[name] };
  const elements = { 'check-in': checkIn, 'check-out': checkOut };
  const document = { querySelector: () => null, querySelectorAll: () => [], getElementById: id => elements[id] ?? null };
  vm.runInNewContext(fs.readFileSync('public/app.js', 'utf8'), { document });
  return { checkIn, checkOut, listeners };
}

test('checkout minimum follows arrival on load and on edits without changing checkout', () => {
  const { checkIn, checkOut, listeners } = checkoutForm('2026-11-03T23:59');
  assert.equal(checkOut.min, '2026-11-04T00:00');
  assert.equal(checkOut.value, '2026-11-01T10:00');
  checkIn.value = '2026-12-31T23:59';
  listeners.input();
  assert.equal(checkOut.min, '2027-01-01T00:00');
  checkIn.value = '2026-03-08T01:59';
  listeners.change();
  assert.equal(checkOut.min, '2026-03-08T02:00');
});

test('empty or invalid arrival clears the minimum without a script error', () => {
  const { checkIn, checkOut, listeners } = checkoutForm('');
  assert.equal(checkOut.min, undefined);
  checkIn.value = '2026-11-03T10:00';
  listeners.input();
  assert.equal(checkOut.min, '2026-11-03T10:01');
  checkIn.validity.valid = false;
  listeners.change();
  assert.equal(checkOut.min, undefined);
});
