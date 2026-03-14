(() => {
  const root = document.documentElement;
  const loading = document.getElementById('loading');
  const loadingText = document.querySelector('[data-loading-text]');
  const attrMessages = loadingText?.getAttribute?.('data-loading-messages');
  let loadingMessages = [
    'جارٍ تجهيز الواجهة...',
    'لحظات من فضلك...',
    'نقوم بتحميل البيانات الآن...'
  ];
  if (attrMessages) {
    try {
      const parsed = JSON.parse(attrMessages);
      if (Array.isArray(parsed) && parsed.length) {
        loadingMessages = parsed.filter((x) => typeof x === 'string' && x.trim() !== '');
      }
    } catch {
      // ignore
    }
  }
  let loadingTimer = null;

  function getPreferredTheme(){
    const stored = window.localStorage.getItem('theme');
    if (stored === 'dark' || stored === 'light') return stored;
    return 'light';
  }

  function getPreferredDensity(){
    const stored = window.localStorage.getItem('density');
    if (stored === 'compact' || stored === 'comfortable') return stored;
    return 'compact';
  }

  function updateThemeButtons(theme){
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
      const icon = button.querySelector('[data-theme-icon]');
      button.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
      button.setAttribute('title', theme === 'light' ? 'تفعيل الوضع الداكن' : 'تفعيل الوضع الفاتح');
      if (icon) icon.textContent = theme === 'light' ? '☾' : '☀';
    });
  }

  function applyTheme(theme, persist){
    root.dataset.theme = theme;
    if (persist !== false) window.localStorage.setItem('theme', theme);
    updateThemeButtons(theme);
  }

  function updateDensityButtons(density){
    document.querySelectorAll('[data-density-toggle]').forEach((button) => {
      const icon = button.querySelector('[data-density-icon]');
      const text = button.querySelector('[data-density-text]');
      button.setAttribute('aria-pressed', density === 'compact' ? 'true' : 'false');
      button.setAttribute('title', density === 'compact' ? 'تفعيل العرض المريح' : 'تفعيل العرض المضغوط');
      if (icon) icon.textContent = density === 'compact' ? '▦' : '▤';
      if (text) text.textContent = density === 'compact' ? 'مضغوط' : 'مريح';
    });
  }

  function applyDensity(density, persist){
    root.dataset.density = density;
    if (persist !== false) window.localStorage.setItem('density', density);
    updateDensityButtons(density);
  }

  applyTheme(getPreferredTheme(), false);
  applyDensity(getPreferredDensity(), false);

  document.addEventListener('click', (e) => {
    const toggle = e.target?.closest?.('[data-theme-toggle]');
    if (!toggle) return;
    const nextTheme = root.dataset.theme === 'light' ? 'dark' : 'light';
    applyTheme(nextTheme, true);
  });

  document.addEventListener('click', (e) => {
    const toggle = e.target?.closest?.('[data-density-toggle]');
    if (!toggle) return;
    const nextDensity = root.dataset.density === 'compact' ? 'comfortable' : 'compact';
    applyDensity(nextDensity, true);
  });

  function showLoading(){
    if (loading) {
      loading.hidden = false;
      if (loadingText) {
        let index = 0;
        loadingText.textContent = loadingMessages[index];
        window.clearInterval(loadingTimer);
        loadingTimer = window.setInterval(() => {
          index = (index + 1) % loadingMessages.length;
          loadingText.textContent = loadingMessages[index];
        }, 1100);
      }
    }
  }
  function hideLoading(){
    if (loading) loading.hidden = true;
    window.clearInterval(loadingTimer);
    loadingTimer = null;
  }

  function prefersReducedMotion(){
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function startPageTransition(navigate){
    if (typeof navigate !== 'function') return;
    if (prefersReducedMotion()) {
      navigate();
      return;
    }
    root.classList.add('is-transitioning');
    window.setTimeout(() => navigate(), 140);
  }

  function shouldTransitionLink(link, event){
    if (!link) return false;
    if (event.defaultPrevented) return false;
    if (event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.hasAttribute('download')) return false;
    const target = link.getAttribute('target');
    if (target && target !== '' && target !== '_self') return false;

    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#')) return false;
    if (href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) return false;
    if (link.hasAttribute('data-no-transition')) return false;

    let url;
    try {
      url = new URL(href, window.location.href);
    } catch {
      return false;
    }
    if (url.origin !== window.location.origin) return false;
    return true;
  }

  function confirmWithDialog(message, onResult){
    const dialog = document.getElementById('confirmDialog');
    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== 'function') {
      onResult(window.confirm(message));
      return;
    }

    const messageEl = dialog.querySelector('[data-confirm-message]');
    if (messageEl) messageEl.textContent = message;

    const onClose = () => {
      dialog.removeEventListener('close', onClose);
      onResult(dialog.returnValue === 'ok');
    };

    dialog.addEventListener('close', onClose);
    try {
      dialog.showModal();
    } catch {
      onResult(window.confirm(message));
    }
  }

  document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    const confirmMessage = form.getAttribute('data-confirm');
    if (confirmMessage) {
      e.preventDefault();
      confirmWithDialog(confirmMessage, (ok) => {
        if (!ok) return;
        if (form.hasAttribute('data-loading')) {
          showLoading();
        }
        form.submit();
      });
      return;
    }

    if (form.hasAttribute('data-loading')) showLoading();
  });

  document.addEventListener('click', (e) => {
    const btn = e.target?.closest?.('[data-print]');
    if (!btn) return;
    window.print();
  });

  // Basic safety: hide loading + clear transitions if page restored from bfcache
  window.addEventListener('pageshow', () => {
    hideLoading();
    root.classList.remove('is-transitioning');
  });

  // Professional transition for language switch + internal navigation
  document.addEventListener('click', (e) => {
    const link = e.target?.closest?.('a');
    if (!shouldTransitionLink(link, e)) return;

    const href = link.getAttribute('href');
    if (!href) return;

    // Only transition GET navigations (avoid interfering with special routes that rely on immediate JS)
    e.preventDefault();
    startPageTransition(() => {
      window.location.href = href;
    });
  }, true);
  // Theme default is explicitly light; do not auto-switch.

  // Mobile hamburger sidebar toggle
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  const topbar = document.querySelector('.topbar');
  const appShell = document.getElementById('appShell');
  const sidebarToggle = document.querySelector('[data-sidebar-toggle]');

  function isMobile(){ return window.innerWidth <= 920; }

  function applySidebarCollapsed(collapsed, persist){
    if (!appShell) return;
    appShell.classList.toggle('sidebar-collapsed', !!collapsed);

    if (sidebarToggle) {
      const textNode = sidebarToggle.querySelector('[data-sidebar-text]');
      const collapseText = sidebarToggle.getAttribute('data-collapse-text') || '';
      const expandText = sidebarToggle.getAttribute('data-expand-text') || '';
      if (textNode) {
        textNode.textContent = collapsed ? (expandText || collapseText) : (collapseText || expandText);
      }
    }

    if (persist) {
      try {
        window.localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
      } catch {
        // ignore
      }
    }
  }

  function getSidebarCollapsed(){
    try {
      return window.localStorage.getItem('sidebar_collapsed') === '1';
    } catch {
      return false;
    }
  }

  function openSidebar(){
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('show');
  }
  function closeSidebar(){
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
  }

  if (menuToggle) menuToggle.addEventListener('click', openSidebar);
  if (overlay) overlay.addEventListener('click', closeSidebar);

  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      if (isMobile()) {
        openSidebar();
        return;
      }
      const next = !(appShell && appShell.classList.contains('sidebar-collapsed'));
      applySidebarCollapsed(next, true);
    });
  }

  // Show/hide topbar based on viewport
  function updateTopbar(){
    if (topbar) topbar.style.display = isMobile() ? 'flex' : 'none';
    if (!isMobile()) closeSidebar();

    // On mobile we always show full sidebar when opened (no desktop collapsing)
    if (isMobile()) {
      applySidebarCollapsed(false, false);
    } else {
      applySidebarCollapsed(getSidebarCollapsed(), false);
    }
  }
  updateTopbar();
  window.addEventListener('resize', updateTopbar);

  // Assets: section/subsection dependent selects
  function wireSectionSubsectionSelect(sectionSelect){
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

    function render(){
      const selectedSectionId = String(sectionSelect.value || '');
      const currentSub = String(subsectionSelect.value || '');
      const items = Array.isArray(map?.[selectedSectionId]) ? map[selectedSectionId] : [];

      // Keep first option as "بدون/اختياري" placeholder
      const first = subsectionSelect.querySelector('option[value=""]');
      const firstText = first ? first.textContent : 'بدون';

      subsectionSelect.innerHTML = '';
      const opt0 = document.createElement('option');
      opt0.value = '';
      opt0.textContent = firstText || 'بدون';
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

    async function startCamera(){
      if (!video) return;
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      video.srcObject = stream;
    }

    function stopCamera(){
      if (stream) {
        stream.getTracks().forEach(t => t.stop());
      }
      stream = null;
      if (video) video.srcObject = null;
    }

    function openPanel(){
      if (panel) panel.hidden = false;
      if (cleaningGrid) cleaningGrid.classList.add('has-camera');
      if (canvas) canvas.hidden = true;
      if (video) video.hidden = false;
      if (retakeBtn) retakeBtn.hidden = true;
      if (sendBtn) sendBtn.hidden = true;
      if (shotBtn) shotBtn.hidden = false;
      lastBlob = null;
    }

    function closePanel(){
      stopCamera();
      if (panel) panel.hidden = true;
      if (cleaningGrid) cleaningGrid.classList.remove('has-camera');
      currentPlaceId = null;
      currentCsrf = null;
      currentEndpoint = null;
      lastBlob = null;
    }

    async function takeShot(){
      if (!video || !canvas) return;
      const w = video.videoWidth || 720;
      const h = video.videoHeight || 1280;
      canvas.width = w;
      canvas.height = h;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      ctx.drawImage(video, 0, 0, w, h);

      // Show captured frame
      canvas.hidden = false;
      video.hidden = true;
      if (shotBtn) shotBtn.hidden = true;
      if (retakeBtn) retakeBtn.hidden = false;
      if (sendBtn) sendBtn.hidden = false;

      await new Promise((resolve) => {
        canvas.toBlob((b) => {
          lastBlob = b;
          resolve();
        }, 'image/jpeg', 0.85);
      });
    }

    async function sendShot(){
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
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin'
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
})();

// ========== NOTIFICATIONS MODULE ==========
(function() {
  'use strict';

  const dropdown = document.getElementById('notificationsDropdown');
  if (!dropdown) return;

  const badge = document.getElementById('notifBadge');
  const list = document.getElementById('notificationsList');
  const loading = document.getElementById('notifLoading');
  const empty = document.getElementById('notifEmpty');
  const markAllBtn = document.getElementById('markAllReadBtn');

  const csrfToken = document.querySelector('input[name="_csrf"]')?.value || 
                    document.querySelector('meta[name="csrf-token"]')?.content || '';

  let notificationsLoaded = false;
  let refreshTimer = null;

  // Get base URL
  function getBaseUrl() {
    const base = document.querySelector('base')?.href || window.location.origin;
    return base.replace(/\/$/, '');
  }

  // Fetch unread notifications
  async function fetchNotifications() {
    try {
      const res = await fetch(getBaseUrl() + '/notifications/unread', {
        headers: { 'Accept': 'application/json' }
      });
      if (!res.ok) return null;
      return await res.json();
    } catch {
      return null;
    }
  }

  // Update badge count
  function updateBadge(count) {
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : count;
      badge.classList.remove('d-none');
    } else {
      badge.classList.add('d-none');
    }
  }

  // Render notification item
  function renderNotification(n) {
    const div = document.createElement('a');
    div.href = n.url || '#';
    div.className = 'dropdown-item d-flex align-items-start gap-2 py-2 px-3 border-bottom notif-item';
    div.setAttribute('data-notif-id', n.id);
    
    div.innerHTML = `
      <div class="flex-shrink-0">
        <i class="bi ${n.icon} ${n.color}" style="font-size: 1.2rem"></i>
      </div>
      <div class="flex-grow-1 overflow-hidden">
        <div class="small text-truncate fw-medium">${escapeHtml(n.message)}</div>
        <div class="text-muted" style="font-size: 0.75rem">
          <span>${escapeHtml(n.actor_name)}</span>
          <span class="mx-1">·</span>
          <span>${escapeHtml(n.time_ago)}</span>
        </div>
      </div>
    `;

    // Mark as read on click
    div.addEventListener('click', () => markRead(n.id));
    
    return div;
  }

  // Escape HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
  }

  // Render notifications list
  function renderNotifications(data) {
    if (!list) return;

    // Clear existing items (except loading and empty placeholders)
    list.querySelectorAll('.notif-item').forEach(el => el.remove());

    if (loading) loading.classList.add('d-none');
    
    if (!data || !data.notifications || data.notifications.length === 0) {
      if (empty) empty.classList.remove('d-none');
      return;
    }

    if (empty) empty.classList.add('d-none');

    data.notifications.forEach(n => {
      list.appendChild(renderNotification(n));
    });

    updateBadge(data.count || 0);
  }

  // Mark single notification as read
  async function markRead(id) {
    try {
      const formData = new FormData();
      formData.append('id', id);
      formData.append('_csrf', csrfToken);

      await fetch(getBaseUrl() + '/notifications/mark-read', {
        method: 'POST',
        body: formData
      });
    } catch {
      // Ignore errors
    }
  }

  // Mark all as read
  async function markAllRead() {
    try {
      const formData = new FormData();
      formData.append('_csrf', csrfToken);

      const res = await fetch(getBaseUrl() + '/notifications/mark-all-read', {
        method: 'POST',
        body: formData
      });

      if (res.ok) {
        // Clear the list visually
        list.querySelectorAll('.notif-item').forEach(el => el.remove());
        if (empty) empty.classList.remove('d-none');
        updateBadge(0);
      }
    } catch {
      // Ignore errors
    }
  }

  // Load notifications on dropdown open
  dropdown.addEventListener('shown.bs.dropdown', async () => {
    if (!notificationsLoaded) {
      const data = await fetchNotifications();
      renderNotifications(data);
      notificationsLoaded = true;
    }
  });

  // Mark all read button
  if (markAllBtn) {
    markAllBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      markAllRead();
    });
  }

  // Initial badge count fetch
  async function initBadge() {
    const data = await fetchNotifications();
    if (data) {
      updateBadge(data.count || 0);
    }
  }

  // Refresh notifications periodically (every 60 seconds)
  function startRefreshTimer() {
    refreshTimer = setInterval(async () => {
      const data = await fetchNotifications();
      if (data) {
        updateBadge(data.count || 0);
        // If dropdown is open, refresh the list
        if (dropdown.classList.contains('show')) {
          renderNotifications(data);
        } else {
          notificationsLoaded = false; // Force reload on next open
        }
      }
    }, 60000);
  }

  // Init
  initBadge();
  startRefreshTimer();
})();

/* ========================
   SELECT-ALL CHECKBOX
======================== */
(() => {
  document.addEventListener('DOMContentLoaded', () => {
    // Select-all for assets table
    const selectAllAssets = document.getElementById('selectAllAssets');
    if (selectAllAssets) {
      selectAllAssets.addEventListener('change', () => {
        const checkboxes = document.querySelectorAll('.asset-checkbox');
        checkboxes.forEach((cb) => {
          cb.checked = selectAllAssets.checked;
        });
        updateMoveButtonState();
      });

      // Update select-all state when individual checkboxes change
      document.addEventListener('change', (e) => {
        if (e.target.classList.contains('asset-checkbox')) {
          const checkboxes = document.querySelectorAll('.asset-checkbox');
          const checked = document.querySelectorAll('.asset-checkbox:checked');
          selectAllAssets.checked = checkboxes.length === checked.length && checkboxes.length > 0;
          selectAllAssets.indeterminate = checked.length > 0 && checked.length < checkboxes.length;
          updateMoveButtonState();
        }
      });
    }

    // Update move button state based on selection
    function updateMoveButtonState() {
      const checked = document.querySelectorAll('.asset-checkbox:checked');
      const moveBtn = document.querySelector('[data-bs-target="#moveModal"]');
      if (moveBtn) {
        moveBtn.disabled = checked.length === 0;
        const badge = moveBtn.querySelector('.badge');
        if (badge) {
          badge.textContent = checked.length;
          badge.hidden = checked.length === 0;
        }
      }
    }

    // Initialize move button state on page load
    updateMoveButtonState();
  });
})();
