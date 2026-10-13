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
