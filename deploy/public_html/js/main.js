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
