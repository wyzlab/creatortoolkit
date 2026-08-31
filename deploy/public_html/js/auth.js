/* =====================================================================
   auth.js  ·  login, code claim, set password, reset
   Loaded on index.php, set-password.php, and reset.php. Each page exposes
   its own [data-auth] root; this script wires whichever is present.
   ===================================================================== */
(function () {
  'use strict';
  var T = window.Toolkit;
  if (!T) return;

  // ── index.php: tabs, code claim, login ───────────────────────────────
  var root = T.el('[data-auth="index"]');
  if (root) {
    var notice   = T.el('[data-notice]', root);
    var tabClaim = T.el('[data-tab="claim"]', root);
    var tabLogin = T.el('[data-tab="login"]', root);
    var panelClaim = T.el('[data-panel="claim"]', root);
    var panelLogin = T.el('[data-panel="login"]', root);

    function selectTab(which) {
      var claim = which === 'claim';
      tabClaim.setAttribute('aria-selected', String(claim));
      tabLogin.setAttribute('aria-selected', String(!claim));
      panelClaim.hidden = !claim;
      panelLogin.hidden = claim;
      T.setNotice(notice, '');
    }
    tabClaim.addEventListener('click', function () { selectTab('claim'); });
    tabLogin.addEventListener('click', function () { selectTab('login'); });

    // Claim is one form with two stages: verify the code, then set a password.
    var claimForm = T.el('[data-form="claim"]', root);
    var pwStep = T.el('[data-step="password"]', root);
    var claimSubmit = claimForm.querySelector('button[type="submit"]');
    var stage = 'verify';

    claimForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      T.setNotice(notice, '');
      var email = claimForm.email.value.trim();
      var code = claimForm.code.value.trim();
      claimSubmit.disabled = true;

      try {
        if (stage === 'verify') {
          var r = await T.apiPost('/api/verify-code.php', { email: email, code: code });
          if (r.valid && r.needs_password) {
            // Reveal the password field and switch this form to stage two.
            pwStep.hidden = false;
            claimForm.email.readOnly = true;
            claimForm.code.readOnly = true;
            claimSubmit.textContent = 'Set password and start';
            stage = 'setpw';
            T.el('[data-newpw]', root).focus();
          } else if (r.valid && !r.needs_password) {
            T.setNotice(notice, r.message || 'Please log in with your email and password.', null);
            selectTab('login');
            T.el('input[name="email"]', panelLogin).value = email;
          } else {
            T.setNotice(notice, r.error || 'That email and code did not match.', 'error');
          }
        } else {
          var pw = claimForm.password.value;
          if (pw.length < 10) {
            T.setNotice(notice, 'Please choose a password of at least 10 characters.', 'error');
            claimSubmit.disabled = false;
            return;
          }
          var r2 = await T.apiPost('/api/set-password.php', { email: email, code: code, password: pw });
          window.location.href = r2.redirect || '/dashboard.php';
          return;
        }
      } catch (e) {
        T.setNotice(notice, e.message, 'error');
      } finally {
        claimSubmit.disabled = false;
      }
    });

    // Login.
    var loginForm = T.el('[data-form="login"]', root);
    loginForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      T.setNotice(notice, '');
      var btn = loginForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        var r = await T.apiPost('/api/login.php', {
          email: loginForm.email.value.trim(),
          password: loginForm.password.value
        });
        window.location.href = r.redirect || '/dashboard.php';
      } catch (e) {
        T.setNotice(notice, e.message, 'error');
        btn.disabled = false;
      }
    });

    // Forgot password link -> request reset inline.
    var forgot = T.el('[data-action="forgot"]', root);
    if (forgot) {
      forgot.addEventListener('click', async function (ev) {
        ev.preventDefault();
        var email = (loginForm.email.value || '').trim();
        if (!email) { T.setNotice(notice, 'Enter your email above first, then tap reset.', 'error'); return; }
        try {
          var r = await T.apiPost('/api/request-reset.php', { email: email });
          T.setNotice(notice, r.message || 'If that email has an account, a reset link is on its way.', 'success');
        } catch (e) {
          T.setNotice(notice, e.message, 'error');
        }
      });
    }
  }

  // ── set-password.php: standalone claim (email + code + password) ─────
  var spRoot = T.el('[data-auth="setpw"]');
  if (spRoot) {
    var spNotice = T.el('[data-notice]', spRoot);
    var spForm = T.el('[data-form="setpw-standalone"]', spRoot);
    spForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      T.setNotice(spNotice, '');
      var pw = spForm.password.value;
      if (pw.length < 10) {
        T.setNotice(spNotice, 'Please choose a password of at least 10 characters.', 'error');
        return;
      }
      var btn = spForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        var r = await T.apiPost('/api/set-password.php', {
          email: spForm.email.value.trim(),
          code: spForm.code.value.trim(),
          password: pw
        });
        window.location.href = r.redirect || '/dashboard.php';
      } catch (e) {
        T.setNotice(spNotice, e.message, 'error');
        btn.disabled = false;
      }
    });
  }

  // ── reset.php: choose a new password from a token ────────────────────
  var resetRoot = T.el('[data-auth="reset"]');
  if (resetRoot) {
    var rNotice = T.el('[data-notice]', resetRoot);
    var rForm = T.el('[data-form="reset"]', resetRoot);
    rForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      T.setNotice(rNotice, '');
      var pw = rForm.password.value;
      if (pw.length < 10) {
        T.setNotice(rNotice, 'Please choose a password of at least 10 characters.', 'error');
        return;
      }
      var btn = rForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      try {
        var r = await T.apiPost('/api/reset-password.php', {
          token: rForm.token.value,
          password: pw
        });
        window.location.href = r.redirect || '/index.php?reset=1';
      } catch (e) {
        T.setNotice(rNotice, e.message, 'error');
        btn.disabled = false;
      }
    });
  }
})();
