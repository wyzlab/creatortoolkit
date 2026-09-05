/* =====================================================================
   main.js  ·  shared behaviour loaded on every page
   Exposes window.Toolkit: a small helper layer (CSRF-aware fetch, DOM
   utilities) that the auth flow and the tool engine both build on.
   ===================================================================== */
(function () {
  'use strict';

  var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /** CSRF-aware JSON POST. Returns the parsed body; throws on non-2xx. */
  async function apiPost(url, body) {
    var res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrf,
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(body || {})
    });
    var data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON */ }
    if (!res.ok) {
      var msg = (data && data.error) || 'Something went wrong. Please try again.';
      var err = new Error(msg);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  /** CSRF-aware JSON GET. */
  async function apiGet(url) {
    var res = await fetch(url, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    var data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON */ }
    if (!res.ok) {
      var err = new Error((data && data.error) || 'Something went wrong.');
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  function el(sel, root) { return (root || document).querySelector(sel); }
  function els(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /** Show a transient message inside a container that has .notice styling. */
  function setNotice(node, message, kind) {
    if (!node) return;
    node.textContent = message || '';
    node.className = 'notice' + (kind ? ' notice--' + kind : '');
    node.hidden = !message;
  }

  // Wire any [data-action="logout"] button.
  els('[data-action="logout"]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      try {
        var r = await apiPost('/api/logout.php', {});
        window.location.href = (r && r.redirect) || '/index.php';
      } catch (e) {
        window.location.href = '/index.php';
      }
    });
  });

  // Wire any [data-print] button to the browser print dialog (Save as PDF).
  els('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // Editable build title above the gates: show plain text, reveal the input
  // only while editing, and collapse back to text after saving.
  (function () {
    var wrap = el('[data-build-title]');
    if (!wrap) return;
    var display = el('[data-build-display]', wrap);
    var edit    = el('[data-build-edit]', wrap);
    var input   = el('#build-title-input', wrap);
    var nameEl  = el('[data-build-name]', wrap);
    var flag    = el('[data-build-title-flag]', wrap);

    function show(mode) {
      if (display) display.hidden = (mode !== 'display');
      if (edit) edit.hidden = (mode !== 'edit');
    }

    var editBtn = el('[data-edit-build-title]', wrap);
    if (editBtn) editBtn.addEventListener('click', function () {
      if (input && nameEl) input.value = nameEl.textContent.trim();
      if (flag) flag.textContent = '';
      show('edit');
      if (input) { input.focus(); input.select(); }
    });

    var cancelBtn = el('[data-cancel-build-title]', wrap);
    if (cancelBtn) cancelBtn.addEventListener('click', function () { show('display'); });

    var saveBtn = el('[data-save-build-title]', wrap);
    if (saveBtn) saveBtn.addEventListener('click', async function () {
      if (!input) return;
      if (flag) { flag.textContent = 'Saving...'; flag.className = 'autosave-flag'; }
      try {
        var r = await apiPost('/api/save-journey-title.php', { title: input.value });
        var t = (r && r.title) || input.value;
        if (nameEl) nameEl.textContent = t;
        input.value = t;
        if (flag) flag.textContent = '';
        show('display');
      } catch (e) {
        if (flag) { flag.textContent = 'Not saved'; flag.className = 'autosave-flag'; }
      }
    });
  })();

  // Show/hide password: toggle the controlled input between dots and text.
  els('[data-pw-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('aria-controls');
      var input = id ? document.getElementById(id) : null;
      if (!input) return;
      var reveal = input.type === 'password';
      input.type = reveal ? 'text' : 'password';
      btn.textContent = reveal ? 'Hide' : 'Show';
      btn.setAttribute('aria-pressed', reveal ? 'true' : 'false');
      btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
  });

  window.Toolkit = {
    csrf: csrf,
    apiPost: apiPost,
    apiGet: apiGet,
    el: el,
    els: els,
    setNotice: setNotice
  };
})();
