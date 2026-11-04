"use strict";
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', event => {
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    // Disabled submit buttons are omitted from the request, including the language choice.
    if (event.submitter?.name) {
      const submittedValue = document.createElement('input');
      submittedValue.type = 'hidden';
      submittedValue.name = event.submitter.name;
      submittedValue.value = event.submitter.value;
      submittedValue.dataset.submittedValue = "true";
      form.appendChild(submittedValue);
    }
    const buttons = form.querySelectorAll('button[type="submit"], button:not([type])');
    buttons.forEach(button => { if (!button.disabled) { button.dataset.submissionDisabled = "true"; button.disabled = true; } });
  });
});
const room = document.getElementById('room-select');
const checkIn = document.getElementById('check-in');
const checkOut = document.getElementById('check-out');
const charge = document.getElementById('room-charge');
function updateCheckoutMinimum() {
  if (!checkIn || !checkOut) return;
  if (!checkIn.value || !checkIn.validity.valid) {
    checkOut.removeAttribute('min');
    return;
  }
  // Treat datetime-local as wall time, independent of the browser time zone/DST.
  const nextMinute = new Date(`${checkIn.value}Z`);
  nextMinute.setUTCMinutes(nextMinute.getUTCMinutes() + 1);
  checkOut.min = nextMinute.toISOString().slice(0, 16);
}
checkIn?.addEventListener('input', updateCheckoutMinimum);
checkIn?.addEventListener('change', updateCheckoutMinimum);
updateCheckoutMinimum();

const priceSummary = document.getElementById('price-summary');
const deposit = document.querySelector('input[name="deposit"]');
const paymentTerm = document.querySelector('select[name="payment_term"]');
let manualDeposit = deposit?.dataset?.manual === 'true';
let manualCharge = charge?.dataset.manual === 'true';
const extraBeds = document.getElementById('extra-beds');
const extraBedNights = document.getElementById('extra-bed-nights');
const extraBedRate = document.getElementById('extra-bed-rate');
let manualBedNights = extraBedNights?.dataset.manual === 'true';
let manualBedRate = extraBedRate?.dataset.manual === 'true';
function roomQuote() {
  const rate = Number(room?.selectedOptions?.[0]?.dataset.rate);
  const arrival = checkIn?.value;
  const departure = checkOut?.value;
  if (!Number.isFinite(rate) || !arrival || !departure || !checkIn.validity.valid || !checkOut.validity.valid) return null;
  if (Date.parse(`${departure}Z`) <= Date.parse(`${arrival}Z`)) return null;
  const nights = Math.max(1, Math.round((Date.parse(departure.slice(0, 10)) - Date.parse(arrival.slice(0, 10))) / 86400000));
  return { rate, nights, total: rate * nights };
}
function refreshPricing(recalculate = false) {
  if (!charge) return;
  const quote = roomQuote();
  if (quote && recalculate && !manualCharge) charge.value = quote.total;
  if (!priceSummary) return;
  const number = new Intl.NumberFormat(document.documentElement.lang === 'my' ? 'my-MM' : 'en');
  const write = (id, value) => { const output = document.getElementById(id); if (output) output.textContent = value; };
  const selected = room?.selectedOptions?.[0]?.dataset;
  if (extraBedNights && quote && !manualBedNights) extraBedNights.value = quote.nights;
  if (extraBedRate && selected?.bedRate !== undefined && !manualBedRate) extraBedRate.value = selected.bedRate;
  if (extraBeds && selected?.maxBeds !== undefined) extraBeds.max = selected.maxBeds;
  const bedAmount = extraBeds && extraBedNights && extraBedRate
    ? extraBeds.valueAsNumber * extraBedNights.valueAsNumber * extraBedRate.valueAsNumber : 0;
  const roomAmount = charge.valueAsNumber;
  const amount = roomAmount + bedAmount;
  write('quote-beds', Number.isFinite(bedAmount) ? number.format(bedAmount) : '—');
  write('quote-grand-total', Number.isFinite(amount) ? number.format(amount) : '—');
  const hint = document.getElementById('room-capacity-hint');
  if (hint && selected?.capacity) hint.textContent = hint.dataset.template.replace('{capacity}', selected.capacity).replace('{beds}', selected.maxBeds);
  if (deposit && paymentTerm?.value === 'immediate' && !manualDeposit && Number.isFinite(amount)) deposit.value = amount;
  const paid = deposit ? deposit.valueAsNumber : 0;
  write('quote-rate', quote ? number.format(quote.rate) : '—');
  write('quote-nights', quote ? number.format(quote.nights) : '—');
  write('quote-total', quote ? number.format(quote.total) : '—');
  write('quote-charge', Number.isFinite(roomAmount) ? number.format(roomAmount) : '—');
  write('quote-paid', Number.isFinite(paid) ? number.format(paid) : '—');
  write('quote-balance', Number.isFinite(amount) && Number.isFinite(paid) ? number.format(amount - paid) : '—');
  document.getElementById('quote-override').hidden = !manualCharge;
  document.getElementById('quote-invalid').hidden = !!quote;
  if (deposit && Number.isFinite(amount)) deposit.max = Math.max(0, amount);
}
function calculateRate() {
  manualCharge = false;
  refreshPricing(true);
}
room?.addEventListener('change', () => { manualBedRate = false; refreshPricing(true); });
checkIn?.addEventListener('input', () => refreshPricing(true));
checkIn?.addEventListener('change', () => refreshPricing(true));
checkOut?.addEventListener('input', () => refreshPricing(true));
checkOut?.addEventListener('change', () => refreshPricing(true));
charge?.addEventListener('input', () => { manualCharge = true; refreshPricing(); });
deposit?.addEventListener('input', () => { manualDeposit = true; refreshPricing(); });
paymentTerm?.addEventListener('change', () => { manualDeposit = false; if (deposit && paymentTerm.value === 'credit') deposit.value = 0; refreshPricing(); });
document.getElementById('calculate-rate')?.addEventListener('click', calculateRate);
extraBeds?.addEventListener('input', () => refreshPricing());
extraBedNights?.addEventListener('input', () => { manualBedNights = true; refreshPricing(); });
extraBedRate?.addEventListener('input', () => { manualBedRate = true; refreshPricing(); });
refreshPricing(!manualCharge);

document.querySelectorAll('[data-companions]').forEach(section => {
  const rows = section.querySelector('[data-companion-rows]');
  const template = section.querySelector('[data-companion-template]');
  let nextIndex = Math.max(-1, ...Array.from(rows.querySelectorAll('input[name]')).map(input => Number(input.name.match(/companions\[(\d+)\]/)?.[1] ?? -1))) + 1;
  const refreshCount = () => {
    const count = rows.querySelectorAll('[data-companion-row]').length + 1;
    section.querySelector('[data-guest-count]').textContent = `${count} ${document.documentElement.lang === 'my' ? 'ဦး (အဓိကဆက်သွယ်သူအပါအဝင်)' : 'guests including the primary contact'}`;
  };
  section.querySelector('[data-add-companion]').addEventListener('click', () => {
    if (rows.querySelectorAll('[data-companion-row]').length >= 49) return;
    rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
    refreshCount();
  });
  rows.addEventListener('click', event => {
    const button = event.target.closest('[data-remove-companion]');
    if (!button) return;
    button.closest('[data-companion-row]').remove();
    refreshCount();
  });
  refreshCount();
});

// Dashboard filters only affect the visible view; records stay on the server.
const roomMapFilter = document.getElementById('room-map-filter');
const roomNumberSearch = document.getElementById('room-number-search');
const floorChoices = Array.from(document.querySelectorAll('[data-floor-filter]'));
function filterRoomMap() {
  let visibleRooms = 0;
  const selectedFloor = floorChoices.find(choice => choice.getAttribute('aria-pressed') === 'true')?.dataset.floorFilter ?? 'all';
  document.querySelectorAll('[data-floor-group]').forEach(group => {
    let visibleOnFloor = 0;
    group.querySelectorAll('[data-room-status]').forEach(tile => {
      tile.hidden = (roomMapFilter.value !== 'all' && tile.dataset.roomStatus !== roomMapFilter.value)
        || (roomNumberSearch && !tile.dataset.roomNumber.toLocaleLowerCase().includes(roomNumberSearch.value.trim().toLocaleLowerCase()));
      if (!tile.hidden) visibleOnFloor += 1;
    });
    group.hidden = (selectedFloor !== 'all' && group.dataset.floorGroup !== selectedFloor) || visibleOnFloor === 0;
    if (!group.hidden) visibleRooms += visibleOnFloor;
  });
  document.getElementById('room-map-empty').hidden = visibleRooms > 0;
}
roomMapFilter?.addEventListener('change', filterRoomMap);
roomNumberSearch?.addEventListener('input', filterRoomMap);
floorChoices.forEach(choice => choice.addEventListener('click', () => {
  floorChoices.forEach(item => {
    const active = item === choice;
    item.classList.toggle('active', active);
    item.setAttribute('aria-pressed', String(active));
  });
  filterRoomMap();
}));
const guestSearch = document.getElementById('guest-search');
const guestRows = Array.from(document.querySelectorAll('[data-guest-row]'));
guestSearch?.addEventListener('input', () => {
  const query = guestSearch.value.trim().toLocaleLowerCase();
  let visibleGuests = 0;
  guestRows.forEach(row => {
    const searchable = [row.dataset.name, row.dataset.room, row.dataset.phone].join(' ').toLocaleLowerCase();
    row.hidden = !searchable.includes(query);
    if (!row.hidden) visibleGuests += 1;
  });
  document.getElementById('guest-result-count').textContent = visibleGuests;
  document.getElementById('guest-empty-row').hidden = visibleGuests > 0;
});
document.getElementById('guest-sort')?.addEventListener('change', event => {
  const sort = event.target.value;
  const collator = new Intl.Collator(document.documentElement.lang, {numeric:true, sensitivity:'base'});
  guestRows.sort((a,b) => sort === 'checkout'
    ? Number(a.dataset.checkout) - Number(b.dataset.checkout)
    : sort === 'latest' ? Number(b.dataset.checkin) - Number(a.dataset.checkin)
    : collator.compare(a.dataset[sort], b.dataset[sort]));
  const body = document.getElementById('guest-table-body');
  const emptyRow = document.getElementById('guest-empty-row');
  guestRows.forEach(row => body.insertBefore(row, emptyRow));
});

function bindRowLinks(scope = document) {
  scope.querySelectorAll('[data-row-link]').forEach(row => {
    row.addEventListener('click', event => {
      if (!event.target.closest('a, button, input, select, textarea, form, label')) {
        window.location.assign(row.dataset.rowLink);
      }
    });
    row.addEventListener('keydown', event => {
      if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        window.location.assign(row.dataset.rowLink);
      }
    });
  });
}
bindRowLinks();

document.querySelectorAll('.room-floor').forEach(floor => {
  const rail = floor.querySelector('.room-rail');
  const buttons = floor.querySelectorAll('[data-rail-scroll]');
  if (!rail) return;
  const updateButtons = () => {
    const maxScroll = rail.scrollWidth - rail.clientWidth;
    buttons.forEach(button => {
      button.disabled = button.dataset.railScroll === '-1'
        ? rail.scrollLeft <= 8
        : rail.scrollLeft >= maxScroll - 8;
    });
  };
  buttons.forEach(button => button.addEventListener('click', () => {
    rail.scrollBy({left: Number(button.dataset.railScroll) * Math.max(280, rail.clientWidth * .75), behavior: 'smooth'});
  }));
  rail.addEventListener('scroll', updateButtons, {passive: true});
  window.addEventListener('resize', updateButtons);
  updateButtons();
});

// GET filters update on selection. Text searches fetch the matching page without
// taking focus from the field or hiding results on later pagination pages.
document.querySelectorAll('form[data-auto-filter]').forEach(form => {
  form.querySelectorAll('select, input[type="month"]').forEach(control => {
    control.addEventListener('change', () => {
      if (form.reportValidity()) form.requestSubmit();
    });
  });
});
document.querySelectorAll('form[data-live-filter]').forEach(form => {
  const results = document.getElementById(form.dataset.resultsTarget);
  if (!results) return;
  let timer;
  let activeRequest;
  const refresh = async () => {
    activeRequest?.abort();
    const requestController = new AbortController();
    activeRequest = requestController;
    const url = new URL(form.action, window.location.href);
    const params = new URLSearchParams(new FormData(form));
    params.delete('page');
    url.search = params.toString();
    results.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {signal: requestController.signal, headers: {'X-Requested-With': 'XMLHttpRequest'}});
      if (!response.ok || response.redirected) throw new Error('Search unavailable');
      const documentNext = new DOMParser().parseFromString(await response.text(), 'text/html');
      const nextResults = documentNext.getElementById(form.dataset.resultsTarget);
      if (!nextResults) throw new Error('Search results missing');
      if (requestController.signal.aborted) return;
      results.innerHTML = nextResults.innerHTML;
      bindRowLinks(results);
      history.replaceState(null, '', url);
    } catch (error) {
      if (error.name !== 'AbortError') form.requestSubmit();
    } finally {
      if (!requestController.signal.aborted) results.removeAttribute('aria-busy');
    }
  };
  form.querySelectorAll('input[name="q"]').forEach(input => {
    input.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(refresh, 260);
    });
  });
  form.querySelectorAll('select').forEach(select => select.addEventListener('change', () => {
    clearTimeout(timer);
    refresh();
  }));
});

document.querySelectorAll('input[data-max-files]').forEach(input => {
  input.addEventListener('change', () => {
    const remaining = Number(input.dataset.maxFiles);
    if (input.files.length > remaining) {
      input.value = '';
      window.alert(document.documentElement.lang === 'en'
        ? `You can upload up to ${remaining} more files.`
        : `နောက်ထပ် ${remaining} ဖိုင်အထိသာ တင်နိုင်ပါသည်။`);
    }
  });
});

document.querySelectorAll('[data-payment-method]').forEach(select => {
  const form = select.closest('form');
  const receipt = form?.querySelector('[data-receipt-field]');
  if (!receipt) return;
  const amount = form.querySelector('input[name="deposit"], input[name="amount"]');
  const refresh = () => {
    receipt.hidden = select.value !== 'transfer' || Number(amount?.value) <= 0;
    if (receipt.hidden) receipt.querySelector('input').value = '';
  };
  select.addEventListener('change', refresh);
  amount?.addEventListener('input', refresh);
  refresh();
});

document.querySelectorAll('[data-theme-toggle]').forEach(button => {
  const refresh = () => {
    const dark = document.documentElement.dataset.theme === 'dark';
    button.setAttribute('aria-pressed', String(dark));
    button.querySelector('span').textContent = dark ? button.dataset.labelLight : button.dataset.labelDark;
  };
  button.addEventListener('click', () => {
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try { localStorage.setItem('motel-theme', next); } catch (_) { /* Keep the current page theme. */ }
    refresh();
  });
  refresh();
});

const identityDialog = document.getElementById('identity-preview-dialog');
if (identityDialog && typeof identityDialog.showModal === 'function') {
  const image = identityDialog.querySelector('img');
  const frame = identityDialog.querySelector('iframe');
  document.querySelectorAll('[data-identity-preview]').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      image.hidden = link.dataset.kind !== 'image';
      frame.hidden = link.dataset.kind !== 'pdf';
      if (image.hidden) frame.src = link.href;
      else image.src = link.href;
      identityDialog.showModal();
    });
  });
  identityDialog.querySelector('.identity-close').addEventListener('click', () => identityDialog.close());
  identityDialog.addEventListener('click', event => { if (event.target === identityDialog) identityDialog.close(); });
  identityDialog.addEventListener('close', () => { image.removeAttribute('src'); frame.removeAttribute('src'); });
}

const mobileMenu = document.getElementById('mobile-menu');
const mobileMenuTrigger = document.querySelector('[data-mobile-menu-open]');
if (mobileMenu && mobileMenuTrigger) {
  const compactViewport = window.matchMedia('(max-width: 1199px), (hover: none) and (pointer: coarse) and (max-width: 1400px)');
  mobileMenuTrigger.addEventListener('click', () => {
    if (mobileMenu.open || !compactViewport.matches) return;
    mobileMenu.showModal();
    mobileMenuTrigger.setAttribute('aria-expanded', 'true');
    document.body.classList.add('mobile-menu-open');
  });
  mobileMenu.querySelector('[data-mobile-menu-close]').addEventListener('click', () => mobileMenu.close());
  mobileMenu.addEventListener('click', event => {
    if (event.target !== mobileMenu) return;
    const bounds = mobileMenu.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) mobileMenu.close();
  });
  mobileMenu.addEventListener('close', () => {
    mobileMenuTrigger.setAttribute('aria-expanded', 'false');
    document.body.classList.remove('mobile-menu-open');
  });
  mobileMenu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => mobileMenu.close()));
  compactViewport.addEventListener('change', () => {
    if (!compactViewport.matches && mobileMenu.open) mobileMenu.close();
  });
  window.addEventListener('pagehide', () => { if (mobileMenu.open) mobileMenu.close(); });
}

// Safari and Chrome can restore disabled controls when returning from a submitted page.
globalThis.addEventListener?.('pageshow', () => {
  document.querySelectorAll('[data-submission-disabled]').forEach(button => {
    button.disabled = false;
    delete button.dataset.submissionDisabled;
  });
  document.querySelectorAll('[data-submitted-value]').forEach(input => input.remove());
});
const checkinForm = document.getElementById('check-in-form');
function refreshCheckinReview() {
  if (!checkinForm) return;
  const guest = checkinForm.querySelector('[name="guest_name"]');
  const fields = { guest: guest?.value.trim() || '—', room: room?.selectedOptions[0]?.textContent || '—', dates: `${checkIn?.value.replace('T', ' ') || '—'} → ${checkOut?.value.replace('T', ' ') || '—'}` };
  Object.entries(fields).forEach(([key, value]) => { const output = checkinForm.querySelector(`[data-review-${key}]`); if (output) output.textContent = value; });
}
checkinForm?.addEventListener('input', refreshCheckinReview);
checkinForm?.addEventListener('change', refreshCheckinReview);
refreshCheckinReview();

document.querySelectorAll('[data-payment-balance]').forEach(form => {
  const amount = form.querySelector('[name="amount"]');
  const output = form.querySelector('[data-payment-remaining]');
  const update = () => { if (output) output.textContent = Number.isInteger(amount.valueAsNumber) ? new Intl.NumberFormat(document.documentElement.lang === 'my' ? 'my-MM' : 'en').format(Number(form.dataset.paymentBalance) - amount.valueAsNumber) : '—'; };
  amount.addEventListener('input', update);
  update();
});
