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

  // Mobile nav: toggle the account menu open/closed under the hamburger.
  els('[data-nav-toggle]').forEach(function (btn) {
    var navId = btn.getAttribute('aria-controls');
    var nav = navId ? document.getElementById(navId) : null;
    if (!nav) return;
    btn.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

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

  // Post-checkout thank-you page: a real buyer sets a password right here and is
  // taken straight into the toolkit (no email). If the checkout redirect carried
  // ?email=..., we claim automatically; otherwise they type the email once.
  (function () {
    var root = el('[data-claim]');
    if (!root) return;
    var notice   = el('[data-claim-notice]', root);
    var stepEmail = el('[data-claim-step="email"]', root);
    var stepPw    = el('[data-claim-step="password"]', root);
    var stepNobuy = el('[data-claim-step="nobuy"]', root);
    var checkForm = el('form[data-form="claim-check"]', root);
    var pwForm    = el('form[data-form="claim-set-password"]', root);

    function show(which) {
      if (stepEmail) stepEmail.hidden = which !== 'email';
      if (stepPw) stepPw.hidden = which !== 'password';
      if (stepNobuy) stepNobuy.hidden = which !== 'nobuy';
    }

    async function runCheck(email) {
      setNotice(notice, 'Checking your purchase...', null);
      var r = await apiPost('/api/claim-check.php', { email: email });
      if (r && r.ready) {
        setNotice(notice, '');
        var nameEl = el('[data-claim-email]', root);
        if (nameEl) nameEl.textContent = r.email || email;
        show('password');
        var pw = el('#claim-newpw', root); if (pw) pw.focus();
      } else if (r && r.already) {
        show('email');
        setNotice(notice, r.message || 'You already set your password. Please log in.', null);
      } else {
        // No matching purchase — invite them to buy (or try another email).
        show('nobuy');
        setNotice(notice, '');
      }
    }

    if (checkForm) checkForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      var btn = checkForm.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      try { await runCheck(checkForm.email.value.trim()); }
      catch (e) { setNotice(notice, e.message || 'Something went wrong. Please try again.', 'error'); }
      finally { if (btn) btn.disabled = false; }
    });

    // "Try another email" from the no-purchase step.
    var retry = el('[data-claim-retry]', root);
    if (retry) retry.addEventListener('click', function () {
      setNotice(notice, '');
      show('email');
      var em = el('#claim-email', root); if (em) { em.value = ''; em.focus(); }
    });

    // Auto-claim when the checkout redirect carried the buyer's email.
    var autoEmail = root.getAttribute('data-claim-auto');
    if (autoEmail) {
      runCheck(autoEmail).catch(function (e) {
        setNotice(notice, e.message || 'Something went wrong. Please enter your email.', 'error');
        show('email');
      });
    }

    if (pwForm) pwForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      var btn = pwForm.querySelector('button[type="submit"]');
      if (btn) btn.disabled = true;
      setNotice(notice, 'Setting up your toolkit...', null);
      try {
        var r = await apiPost('/api/claim-set-password.php', { password: pwForm.password.value });
        window.location.href = (r && r.redirect) || '/dashboard.php';
      } catch (e) {
        if (btn) btn.disabled = false;
        // Session expired mid-claim: send them back to step 1.
        if (e.status === 440) {
          if (stepPw) stepPw.hidden = true;
          if (stepEmail) stepEmail.hidden = false;
        }
        setNotice(notice, e.message || 'Something went wrong. Please try again.', 'error');
      }
    });
  })();

  // Wire any [data-print] button to the browser print dialog (Save as PDF).
  els('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // Email a saved offer to the logged-in learner with one click.
  els('[data-email-offer]').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      var id = parseInt(btn.getAttribute('data-email-offer'), 10);
      var notice = el('[data-email-offer-notice]');
      var label = btn.textContent;
      btn.disabled = true;
      setNotice(notice, 'Sending...', null);
      try {
        var r = await apiPost('/api/email-offer.php', { id: id });
        setNotice(notice, r.message || 'Done.', r.status === 'sent' ? 'success' : null);
      } catch (e) {
        setNotice(notice, e.message || 'Could not send just now. Please try again.', 'error');
      } finally { btn.disabled = false; btn.textContent = label; }
    });
  });

  // Ask before an irreversible form submit (e.g. "Delete forever").
  els('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.getAttribute('data-confirm'))) { ev.preventDefault(); }
    });
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
