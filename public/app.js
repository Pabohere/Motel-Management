"use strict";
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', event => {
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) { event.preventDefault(); return; }
    const buttons = form.querySelectorAll('button[type="submit"], button:not([type])');
    buttons.forEach(button => { button.disabled = true; });
  });
});
const room = document.getElementById('room-select');
const checkIn = document.getElementById('check-in');
const checkOut = document.getElementById('check-out');
const charge = document.getElementById('room-charge');
function calculateRate() {
  const rate = Number(room?.selectedOptions[0]?.dataset.rate);
  const arrival = checkIn?.value.slice(0,10);
  const departure = checkOut?.value.slice(0,10);
  if (!Number.isFinite(rate) || !arrival || !departure) return;
  const nights = Math.max(1, Math.round((Date.parse(departure)-Date.parse(arrival))/86400000));
  charge.value = rate * nights;
}
room?.addEventListener('change', calculateRate);
document.getElementById('calculate-rate')?.addEventListener('click', calculateRate);

// Dashboard filters only affect the visible view; records stay on the server.
const roomMapFilter = document.getElementById('room-map-filter');
roomMapFilter?.addEventListener('change', () => {
  let visibleRooms = 0;
  document.querySelectorAll('[data-room-status]').forEach(tile => {
    tile.hidden = roomMapFilter.value !== 'all' && tile.dataset.roomStatus !== roomMapFilter.value;
    if (!tile.hidden) visibleRooms += 1;
  });
  document.getElementById('room-map-empty').hidden = visibleRooms > 0;
});
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
  const collator = new Intl.Collator('my', {numeric:true, sensitivity:'base'});
  guestRows.sort((a,b) => sort === 'checkout'
    ? Number(a.dataset.checkout) - Number(b.dataset.checkout)
    : collator.compare(a.dataset[sort], b.dataset[sort]));
  const body = document.getElementById('guest-table-body');
  const emptyRow = document.getElementById('guest-empty-row');
  guestRows.forEach(row => body.insertBefore(row, emptyRow));
});

document.querySelectorAll('input[data-max-files]').forEach(input => {
  input.addEventListener('change', () => {
    const remaining = Number(input.dataset.maxFiles);
    if (input.files.length > remaining) {
      input.value = '';
      window.alert(`နောက်ထပ် ${remaining} ဖိုင်အထိသာ တင်နိုင်ပါသည်။`);
    }
  });
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
