const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function checkoutForm(arrival) {
  const listeners = {};
  const checkIn = { value: arrival, validity: { valid: true }, addEventListener: (name, callback) => { (listeners[name] ??= []).push(callback); } };
  const checkOut = { value: '2026-11-01T10:00', addEventListener: () => {}, removeAttribute: name => delete checkOut[name] };
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
  listeners.input.forEach(callback => callback());
  assert.equal(checkOut.min, '2027-01-01T00:00');
  checkIn.value = '2026-03-08T01:59';
  listeners.change.forEach(callback => callback());
  assert.equal(checkOut.min, '2026-03-08T02:00');
});

test('empty or invalid arrival clears the minimum without a script error', () => {
  const { checkIn, checkOut, listeners } = checkoutForm('');
  assert.equal(checkOut.min, undefined);
  checkIn.value = '2026-11-03T10:00';
  listeners.input.forEach(callback => callback());
  assert.equal(checkOut.min, '2026-11-03T10:01');
  checkIn.validity.valid = false;
  listeners.change.forEach(callback => callback());
  assert.equal(checkOut.min, undefined);
});

function pricingForm(manual = false, term = null, beds = null) {
  const controls = {};
  function input(id, value = '') {
    const handlers = {};
    const control = { value, validity: { valid: true }, dataset: {}, hidden: false,
      get valueAsNumber() { return this.value === '' ? NaN : Number(this.value); },
      addEventListener: (name, callback) => { (handlers[name] ??= []).push(callback); },
      removeAttribute: name => delete control[name],
      fire: name => (handlers[name] ?? []).forEach(callback => callback()) };
    controls[id] = control;
    return control;
  }
  input('room-select').selectedOptions = [{ dataset: { rate: '35000' } }];
  input('check-in', '2026-11-03T10:00');
  input('check-out', '2026-11-04T12:00');
  input('room-charge', manual ? '42000' : '0').dataset.manual = String(manual);
  input('deposit', '10000');
  if (beds) {
    input('extra-beds', String(beds.quantity));
    input('extra-bed-nights', String(beds.nights ?? 1)).dataset.manual = String(beds.manualNights ?? false);
    input('extra-bed-rate', String(beds.rate ?? 5000)).dataset.manual = String(beds.manualRate ?? false);
    controls['room-select'].selectedOptions[0].dataset.bedRate = '5000';
    controls['room-select'].selectedOptions[0].dataset.maxBeds = '2';
  }
  if (term) input('payment-term', term);
  for (const id of ['price-summary', 'quote-rate', 'quote-nights', 'quote-total', 'quote-charge', 'quote-paid', 'quote-balance', 'quote-override', 'quote-invalid', 'quote-beds', 'quote-grand-total', 'calculate-rate']) input(id);
  const document = { documentElement: { lang: 'en' }, querySelectorAll: () => [],
    querySelector: selector => selector === 'input[name="deposit"]' ? controls.deposit : selector === 'select[name="payment_term"]' ? controls['payment-term'] ?? null : null,
    getElementById: id => controls[id] ?? null };
  vm.runInNewContext(fs.readFileSync('public/app.js', 'utf8'), { document });
  return controls;
}

test('nightly charge and outstanding balance follow room, dates and payment edits', () => {
  const c = pricingForm();
  assert.equal(Number(c['room-charge'].value), 35000);
  assert.equal(c['quote-balance'].textContent, '25,000');
  c['check-out'].value = '2026-11-06T12:00';
  c['check-out'].fire('input');
  assert.equal(Number(c['room-charge'].value), 105000);
  assert.equal(c['quote-nights'].textContent, '3');
  assert.equal(c['quote-balance'].textContent, '95,000');
  c['room-select'].selectedOptions[0].dataset.rate = '50000';
  c['room-select'].fire('change');
  assert.equal(Number(c['room-charge'].value), 150000);
  c.deposit.value = '150000';
  c.deposit.fire('input');
  assert.equal(c['quote-balance'].textContent, '0');
  assert.equal(c.deposit.max, 150000);
});

test('manual and restored charges survive date edits until nightly pricing is explicitly restored', () => {
  const c = pricingForm(true);
  assert.equal(c['room-charge'].value, '42000');
  c['check-out'].value = '2026-11-06T12:00';
  c['check-out'].fire('change');
  assert.equal(c['room-charge'].value, '42000');
  assert.equal(c['quote-override'].hidden, false);
  c['calculate-rate'].fire('click');
  assert.equal(Number(c['room-charge'].value), 105000);
  assert.equal(c['quote-override'].hidden, true);
  c['room-charge'].value = '90000';
  c['room-charge'].fire('input');
  c['room-select'].fire('change');
  assert.equal(c['room-charge'].value, '90000');
});

test('invalid or incomplete dates never replace the entered charge with a misleading quote', () => {
  const c = pricingForm();
  c['check-out'].value = '2026-11-02T12:00';
  c['check-out'].fire('input');
  assert.equal(Number(c['room-charge'].value), 35000);
  assert.equal(c['quote-total'].textContent, '—');
  assert.equal(c['quote-invalid'].hidden, false);
  c['check-in'].value = '';
  c['check-in'].fire('input');
  assert.equal(c['quote-total'].textContent, '—');
});

test('pay-now fills the full amount while credit and manual payment remain explicit', () => {
  const c = pricingForm(false, 'immediate');
  assert.equal(Number(c.deposit.value), 35000);
  c['check-out'].value = '2026-11-06T12:00';
  c['check-out'].fire('input');
  assert.equal(Number(c.deposit.value), 105000);
  c['payment-term'].value = 'credit';
  c['payment-term'].fire('change');
  assert.equal(Number(c.deposit.value), 0);
  c.deposit.value = '10000';
  c.deposit.fire('input');
  assert.equal(c['quote-balance'].textContent, '95,000');
  c['payment-term'].value = 'immediate';
  c['payment-term'].fire('change');
  assert.equal(Number(c.deposit.value), 105000);
});
test('returning with browser history restores only buttons disabled by submission', () => {
  const handlers = {};
  const changed = {disabled:true,dataset:{submissionDisabled:'true'}};
  const temporary = {remove:()=>temporary.removed=true};
  const document = {querySelector:()=>null,getElementById:()=>null,querySelectorAll:selector=>selector==='[data-submission-disabled]'?[changed]:selector==='[data-submitted-value]'?[temporary]:[]};
  vm.runInNewContext(fs.readFileSync('public/app.js','utf8'),{document,addEventListener:(event,fn)=>handlers[event]=fn});
  handlers.pageshow();
  assert.equal(changed.disabled,false);
  assert.equal(changed.dataset.submissionDisabled,undefined);
  assert.equal(temporary.removed,true);
});


test('extra-bed quantity, nights and rate are included in full payment and balance', () => {
  const c = pricingForm(false, 'immediate', {quantity: 1});
  assert.equal(c['quote-beds'].textContent, '5,000');
  assert.equal(c['quote-grand-total'].textContent, '40,000');
  assert.equal(Number(c.deposit.value), 40000);
  assert.equal(c.deposit.max, 40000);
  c['check-out'].value = '2026-11-06T12:00';
  c['check-out'].fire('input');
  assert.equal(Number(c['extra-bed-nights'].value), 3);
  assert.equal(c['quote-beds'].textContent, '15,000');
  assert.equal(Number(c.deposit.value), 120000);
  c['extra-beds'].value = '2';
  c['extra-beds'].fire('input');
  assert.equal(c['quote-grand-total'].textContent, '135,000');
  c['extra-bed-rate'].value = '0';
  c['extra-bed-rate'].fire('input');
  assert.equal(Number(c.deposit.value), 105000);
});

test('manual bed quotes and partial payments survive date changes', () => {
  const c = pricingForm(false, 'credit', {quantity: 1, nights: 2, rate: 7000, manualNights: true, manualRate: true});
  c.deposit.value = '20000';
  c.deposit.fire('input');
  c['check-out'].value = '2026-11-06T12:00';
  c['check-out'].fire('input');
  assert.equal(Number(c['extra-bed-nights'].value), 2);
  assert.equal(Number(c['extra-bed-rate'].value), 7000);
  assert.equal(c['quote-grand-total'].textContent, '119,000');
  assert.equal(c['quote-balance'].textContent, '99,000');
});
