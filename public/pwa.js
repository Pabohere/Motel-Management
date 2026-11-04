'use strict';
(() => {
  const buttons = [...document.querySelectorAll('[data-pwa-install]')];
  const dialog = document.getElementById('pwa-install-dialog');
  const standalone = window.matchMedia('(display-mode: standalone)');
  let installPrompt = null;
  let installed = false;
  let opener = null;
  const updateButtons = () => buttons.forEach(button => {
    button.hidden = installed || standalone.matches || navigator.standalone === true;
  });
  if (!window.isSecureContext || !('serviceWorker' in navigator)) return;
  updateButtons();
  standalone.addEventListener('change', updateButtons);
  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    installPrompt = event;
    updateButtons();
  });
  window.addEventListener('appinstalled', () => {
    installed = true;
    installPrompt = null;
    if (dialog?.open) dialog.close();
    updateButtons();
  });
  buttons.forEach(button => button.addEventListener('click', async () => {
    if (installPrompt) {
      const prompt = installPrompt;
      installPrompt = null;
      try { await prompt.prompt(); } catch (_) { dialog?.showModal(); }
    } else if (dialog && !dialog.open) {
      opener = button;
      dialog.showModal();
    }
  }));
  dialog?.querySelector('[data-pwa-close]')?.addEventListener('click', () => dialog.close());
  dialog?.addEventListener('close', () => opener?.focus());
  dialog?.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
  });
  const updateConnection = () => {
    const notice = document.querySelector('[data-pwa-offline]');
    if (notice) notice.hidden = navigator.onLine;
  };
  window.addEventListener('online', updateConnection);
  window.addEventListener('offline', updateConnection);
  updateConnection();
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js', { scope: '/', updateViaCache: 'none' }).catch(() => {
      // The online app remains usable if installation support is unavailable.
    });
  });
})();
