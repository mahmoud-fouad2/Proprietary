(() => {
  const loading = document.getElementById('zacoLoading');

  function showLoading() {
    if (loading) loading.hidden = false;
  }

  function hideLoading() {
    if (loading) loading.hidden = true;
  }

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    const confirmMessage = form.getAttribute('data-confirm');
    if (confirmMessage && !window.confirm(confirmMessage)) {
      e.preventDefault();
      return;
    }

    if (form.hasAttribute('data-loading')) showLoading();
  });

  document.addEventListener('click', (e) => {
    const btn = e.target?.closest?.('[data-print]');
    if (!btn) return;
    window.print();
  });

  window.addEventListener('pageshow', () => {
    hideLoading();
  });

  // Dependent selects (sections -> subsections)
  function wireSectionSubsectionSelect(sectionSelect) {
    if (!(sectionSelect instanceof HTMLSelectElement)) return;
    const subId = sectionSelect.getAttribute('data-subsection-id');
    if (!subId) return;
    const subsectionSelect = document.getElementById(subId);
    if (!(subsectionSelect instanceof HTMLSelectElement)) return;

    const json = subsectionSelect.getAttribute('data-subsections-json') || '{}';
    let map = {};
    try {
      map = JSON.parse(json) || {};
    } catch {
      map = {};
    }

    function render() {
      const selectedSectionId = String(sectionSelect.value || '');
      const currentSub = String(subsectionSelect.value || '');
      const items = Array.isArray(map?.[selectedSectionId]) ? map[selectedSectionId] : [];

      const first = subsectionSelect.querySelector('option[value=""]');
      const firstText = first ? first.textContent : '';

      subsectionSelect.innerHTML = '';
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = firstText || '—';
      subsectionSelect.appendChild(opt0);

      for (const it of items) {
        if (!it || typeof it !== 'object') continue;
        const id = String(it.id ?? '');
        const name = String(it.name ?? '');
        if (!id || !name) continue;
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = name;
        subsectionSelect.appendChild(opt);
      }

      if (currentSub && items.some((x) => String(x?.id ?? '') === currentSub)) {
        subsectionSelect.value = currentSub;
      } else {
        subsectionSelect.value = '';
      }
    }

    sectionSelect.addEventListener('change', render);
    render();
  }

  document.querySelectorAll('select[data-section-select]').forEach(wireSectionSubsectionSelect);

  // Cleaning camera capture
  const cleaningRoot = document.querySelector('[data-cleaning]');
  if (cleaningRoot) {
    const cleaningGrid = cleaningRoot.querySelector('.cleaning');
    const panel = document.getElementById('cameraPanel');
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const closeBtn = document.getElementById('cameraClose');
    const shotBtn = document.getElementById('cameraShot');
    const retakeBtn = document.getElementById('cameraRetake');
    const sendBtn = document.getElementById('cameraSend');

    let stream = null;
    let currentPlaceId = null;
    let currentCsrf = null;
    let currentEndpoint = null;
    let lastBlob = null;

    async function startCamera() {
      if (!video) return;
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false,
      });
      video.srcObject = stream;
    }

    function stopCamera() {
      if (stream) {
        stream.getTracks().forEach((t) => t.stop());
      }
      stream = null;
      if (video) video.srcObject = null;
    }

    function openPanel() {
      if (panel) panel.hidden = false;
      if (cleaningGrid) cleaningGrid.classList.add('has-camera');
      if (canvas) canvas.hidden = true;
      if (video) video.hidden = false;
      if (retakeBtn) retakeBtn.hidden = true;
      if (sendBtn) sendBtn.hidden = true;
      if (shotBtn) shotBtn.hidden = false;
      lastBlob = null;
    }

    function closePanel() {
      stopCamera();
      if (panel) panel.hidden = true;
      if (cleaningGrid) cleaningGrid.classList.remove('has-camera');
      currentPlaceId = null;
      currentCsrf = null;
      currentEndpoint = null;
      lastBlob = null;
    }

    async function takeShot() {
      if (!video || !canvas) return;
      const w = video.videoWidth || 720;
      const h = video.videoHeight || 1280;
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      ctx.drawImage(video, 0, 0, w, h);

      canvas.hidden = false;
      video.hidden = true;
      if (shotBtn) shotBtn.hidden = true;
      if (retakeBtn) retakeBtn.hidden = false;
      if (sendBtn) sendBtn.hidden = false;

      await new Promise((resolve) => {
        canvas.toBlob(
          (b) => {
            lastBlob = b;
            resolve();
          },
          'image/jpeg',
          0.85,
        );
      });
    }

    async function sendShot() {
      if (!lastBlob || !currentEndpoint || !currentCsrf || !currentPlaceId) return;
      showLoading();
      try {
        const fd = new FormData();
        fd.append('_csrf', currentCsrf);
        fd.append('place_id', String(currentPlaceId));
        fd.append('photo', lastBlob, 'capture.jpg');

        const res = await fetch(currentEndpoint, {
          method: 'POST',
          body: fd,
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (!res.ok) {
          throw new Error('فشل إرسال الصورة');
        }
        closePanel();
        window.location.reload();
      } catch (err) {
        hideLoading();
        alert(err?.message || 'حدث خطأ');
      }
    }

    document.addEventListener('click', async (e) => {
      const btn = e.target?.closest?.('[data-capture]');
      if (!btn) return;

      currentPlaceId = btn.getAttribute('data-place-id');
      currentCsrf = btn.getAttribute('data-csrf');
      currentEndpoint = btn.getAttribute('data-endpoint');

      openPanel();
      try {
        await startCamera();
      } catch {
        closePanel();
        alert('تعذر فتح الكاميرا. تأكد من إعطاء الإذن والمتصفح يدعم الكاميرا.');
      }
    });

    closeBtn?.addEventListener('click', () => closePanel());
    shotBtn?.addEventListener('click', () => takeShot());
    retakeBtn?.addEventListener('click', () => {
      if (canvas) canvas.hidden = true;
      if (video) video.hidden = false;
      if (retakeBtn) retakeBtn.hidden = true;
      if (sendBtn) sendBtn.hidden = true;
      if (shotBtn) shotBtn.hidden = false;
      lastBlob = null;
    });
    sendBtn?.addEventListener('click', () => sendShot());
  }

  // OverlayScrollbars (sidebar) - optional.
  document.addEventListener('DOMContentLoaded', () => {
    try {
      const sidebarWrapper = document.querySelector('.sidebar-wrapper');
      const isMobile = window.innerWidth <= 992;

      if (
        sidebarWrapper &&
        !isMobile &&
        typeof window.OverlayScrollbarsGlobal?.OverlayScrollbars === 'function'
      ) {
        window.OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
          scrollbars: {
            theme: 'os-theme-light',
            autoHide: 'leave',
            clickScroll: true,
          },
        });
      }
    } catch {
      // ignore
    }
  });

  // Navbar search (routes to existing index pages with q=...)
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zacoNavSearchForm');
    if (!(form instanceof HTMLFormElement)) return;
    const moduleSel = document.getElementById('zacoNavSearchModule');
    if (!(moduleSel instanceof HTMLSelectElement)) return;

    function moduleToPath(mod) {
      switch (String(mod || '')) {
        case 'employees':
          return '/employees';
        case 'custody':
          return '/custody';
        case 'software':
          return '/software';
        case 'inventory':
        default:
          return '/inventory';
      }
    }

    function updateAction() {
      const path = moduleToPath(moduleSel.value);
      form.setAttribute('action', path);
    }

    moduleSel.addEventListener('change', updateAction);
    updateAction();
  });

  // Toast helper
  function ensureToastContainer() {
    const el = document.getElementById('zacoToasts');
    if (el) return el;
    const c = document.createElement('div');
    c.id = 'zacoToasts';
    c.className = 'toast-container position-fixed top-0 end-0 p-3';
    c.style.zIndex = '2050';
    document.body.appendChild(c);
    return c;
  }

  function toastVariantFromAlertClass(alertEl) {
    const cls = alertEl?.classList;
    if (!cls) return 'secondary';
    if (cls.contains('alert-success')) return 'success';
    if (cls.contains('alert-danger')) return 'danger';
    if (cls.contains('alert-warning')) return 'warning';
    if (cls.contains('alert-info')) return 'info';
    return 'secondary';
  }

  function showToast(message, variant = 'secondary') {
    const container = ensureToastContainer();
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-bg-${variant} border-0`;
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.setAttribute('aria-atomic', 'true');
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body"></div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;
    toast.querySelector('.toast-body').textContent = String(message || '');
    container.appendChild(toast);

    try {
      if (window.bootstrap?.Toast) {
        const t = new window.bootstrap.Toast(toast, { delay: 3500 });
        t.show();
        toast.addEventListener('hidden.bs.toast', () => toast.remove());
        return;
      }
    } catch {
      // ignore
    }

    // Fallback
    setTimeout(() => toast.remove(), 3500);
  }

  window.ZacoToast = {
    show: showToast,
    success: (m) => showToast(m, 'success'),
    danger: (m) => showToast(m, 'danger'),
    warning: (m) => showToast(m, 'warning'),
    info: (m) => showToast(m, 'info'),
  };

  // Convert alerts marked with data-toast into toasts
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.alert[data-toast]').forEach((alertEl) => {
      const msg = (alertEl.textContent || '').trim();
      if (!msg) return;
      const variant = toastVariantFromAlertClass(alertEl);
      showToast(msg, variant);
      alertEl.remove();
    });
  });
})();
