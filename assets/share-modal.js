(() => {
  'use strict';

  // This file is intentionally defensive.
  // Some deployments reference share-modal.js globally; on pages without the expected DOM
  // elements, older scripts used to throw. Here we no-op safely.

  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn, { once: true });
    } else {
      fn();
    }
  }

  onReady(() => {
    const trigger = document.querySelector('[data-share-trigger]')
      || document.getElementById('shareBtn')
      || document.getElementById('shareButton');

    const modal = document.querySelector('[data-share-modal]')
      || document.getElementById('shareModal');

    if (!trigger || !modal || typeof trigger.addEventListener !== 'function') return;

    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      // If a real modal implementation exists elsewhere, it should hook into its own selectors.
      // We keep this handler intentionally minimal.
      if (modal instanceof HTMLDialogElement && typeof modal.showModal === 'function') {
        try { modal.showModal(); } catch { /* ignore */ }
      }
    });
  });
})();
