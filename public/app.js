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
      form.appendChild(submittedValue);
    }
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
const floorChoices = Array.from(document.querySelectorAll('[data-floor-filter]'));
function filterRoomMap() {
  let visibleRooms = 0;
  const selectedFloor = floorChoices.find(choice => choice.getAttribute('aria-pressed') === 'true')?.dataset.floorFilter ?? 'all';
  document.querySelectorAll('[data-floor-group]').forEach(group => {
    let visibleOnFloor = 0;
    group.querySelectorAll('[data-room-status]').forEach(tile => {
      tile.hidden = roomMapFilter.value !== 'all' && tile.dataset.roomStatus !== roomMapFilter.value;
      if (!tile.hidden) visibleOnFloor += 1;
    });
    group.hidden = (selectedFloor !== 'all' && group.dataset.floorGroup !== selectedFloor) || visibleOnFloor === 0;
    if (!group.hidden) visibleRooms += visibleOnFloor;
  });
  document.getElementById('room-map-empty').hidden = visibleRooms > 0;
}
roomMapFilter?.addEventListener('change', filterRoomMap);
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
