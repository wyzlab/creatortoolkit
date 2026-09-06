/* =====================================================================
   admin.js  ·  the admin console behaviour
   ===================================================================== */
(function () {
  'use strict';
  var T = window.Toolkit;
  var root = T && T.el('[data-admin]');
  if (!root) return;

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }

  // ── Overview ─────────────────────────────────────────────────────────
  async function loadStats() {
    try {
      var s = await T.apiGet('/api/admin/stats.php');
      var g = T.el('[data-stats]', root);
      var gateBits = s.gates.map(function (x) {
        return '<div class="stat"><span class="stat__num">' + x.completed + '</span><span class="stat__label">' + esc(x.label) + ' done</span></div>';
      }).join('');
      var ai = s.ai || {};
      var em = s.emails || {};
      var emFailBit = em.failed ? '<div class="stat stat--warn"><span class="stat__num">' + em.failed + '</span><span class="stat__label">Emails failed</span></div>' : '';
      var aiFailBit = ai.failed ? '<div class="stat stat--warn"><span class="stat__num">' + ai.failed + '</span><span class="stat__label">AI notes failed</span></div>' : '';
      g.innerHTML =
        '<div class="stat"><span class="stat__num">' + s.buyers + '</span><span class="stat__label">Buyers</span></div>' +
        '<div class="stat"><span class="stat__num">' + s.codes.unclaimed + '</span><span class="stat__label">Codes unused</span></div>' +
        '<div class="stat"><span class="stat__num">' + s.codes.claimed + '</span><span class="stat__label">Codes used</span></div>' +
        gateBits +
        '<div class="stat"><span class="stat__num">' + (em.sent || 0) + '</span><span class="stat__label">Emails sent</span></div>' +
        '<div class="stat"><span class="stat__num">' + (em.queued || 0) + '</span><span class="stat__label">Emails queued</span></div>' +
        '<div class="stat"><span class="stat__num">' + (ai.ok || 0) + '</span><span class="stat__label">AI notes written</span></div>' +
        emFailBit + aiFailBit;

      var tbl = T.el('[data-dropoff]', root);
      tbl.querySelector('tbody').innerHTML = s.dropoff.map(function (d) {
        return '<tr><td>' + esc(d.title) + '</td><td>' + d.gate + '</td><td>' + d.started + '</td><td>' + d.completed + '</td></tr>';
      }).join('');
      tbl.hidden = false;
    } catch (e) { /* leave the placeholder */ }
  }

  // ── Recent codes ─────────────────────────────────────────────────────
  async function loadCodes() {
    var tbody = T.el('[data-codes] tbody', root);
    try {
      var r = await T.apiGet('/api/admin/list-codes.php?limit=50');
      if (!r.codes.length) { tbody.innerHTML = '<tr><td colspan="5" class="muted">No codes yet.</td></tr>'; return; }
      tbody.innerHTML = r.codes.map(function (c) {
        // A universal code stays shared (never flips to "claimed" in the DB), so
        // show its real state plus how many sign-ups used it, rather than a
        // misleading "unclaimed".
        var status = c.status, claimedBy = c.claimed_by || '';
        if (c.is_universal) {
          status = c.status === 'revoked' ? 'revoked' : (c.uses > 0 ? 'claimed' : 'active');
          claimedBy = c.uses + (c.uses === 1 ? ' sign-up' : ' sign-ups');
        }
        return '<tr><td>' + esc(c.display) + '</td><td>' + esc(c.batch || '') + '</td><td>' +
          esc(c.issued_to || '') + '</td><td>' + esc(status) + '</td><td>' + esc(claimedBy) + '</td></tr>';
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="5" class="notice notice--error">Could not load codes.</td></tr>';
    }
  }

  // ── Universal code uses (reconcile against purchases), grouped per code ─
  function csvFor(uses) {
    return 'email,date_used\n' + uses.map(function (u) {
      return '"' + String(u.email).replace(/"/g, '""') + '","' + u.redeemed_at + '"';
    }).join('\n');
  }
  function copyText(btn, text) {
    var label = btn.textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject())
      .then(function () { btn.textContent = 'Copied'; })
      .catch(function () { btn.textContent = 'Copy failed'; });
    setTimeout(function () { btn.textContent = label; }, 1500);
  }
  // One sign-up row with its current access state and a revoke/restore button.
  function accessRow(u) {
    var cell;
    if (u.status === 'gone') {
      cell = '<span class="muted">removed</span>';
    } else {
      var revoked = u.status === 'suspended';
      var label = revoked ? '<span class="access-tag access-tag--off">Revoked</span>' : '<span class="access-tag">Active</span>';
      var btn = '<button type="button" class="btn btn--sm ' + (revoked ? 'btn--ghost' : 'btn--danger') +
        '" data-set-access data-user-id="' + u.user_id + '" data-action="' + (revoked ? 'restore' : 'revoke') + '">' +
        (revoked ? 'Restore' : 'Revoke') + '</button>';
      cell = label + ' ' + btn;
    }
    return '<tr data-uid="' + u.user_id + '"><td>' + esc(u.email) + '</td><td>' + esc(u.redeemed_at) + '</td><td class="access-cell">' + cell + '</td></tr>';
  }
  // Revoke/restore a sign-up's access (event-delegated, one listener for all groups).
  var usesHostForClicks = T.el('[data-universal-uses-groups]', root);
  if (usesHostForClicks) {
    usesHostForClicks.addEventListener('click', async function (ev) {
      var btn = ev.target.closest ? ev.target.closest('[data-set-access]') : null;
      if (!btn) return;
      var uid = btn.getAttribute('data-user-id');
      var action = btn.getAttribute('data-action');
      if (action === 'revoke' && !window.confirm('Revoke access for this account? They will be signed out and cannot log in until you restore them.')) return;
      btn.disabled = true;
      try {
        var r = await T.apiPost('/api/admin/set-access.php', { user_id: parseInt(uid, 10), action: action });
        var cell = btn.closest('.access-cell');
        var nowRevoked = r.status === 'suspended';
        cell.innerHTML = (nowRevoked ? '<span class="access-tag access-tag--off">Revoked</span>' : '<span class="access-tag">Active</span>') +
          ' <button type="button" class="btn btn--sm ' + (nowRevoked ? 'btn--ghost' : 'btn--danger') +
          '" data-set-access data-user-id="' + uid + '" data-action="' + (nowRevoked ? 'restore' : 'revoke') + '">' +
          (nowRevoked ? 'Restore' : 'Revoke') + '</button>';
      } catch (e) {
        btn.disabled = false;
        alert(e.message || 'Could not update access. Please try again.');
      }
    });
  }
  async function loadUniversalUses() {
    var host = T.el('[data-universal-uses-groups]', root);
    var note = T.el('[data-universal-uses-note]', root);
    if (!host) return;
    try {
      var r = await T.apiGet('/api/admin/universal-uses.php');
      if (!r.tracked) {
        T.setNotice(note, 'Tracking is not switched on yet. Run the code_redemptions migration on the database to start recording uses.', null);
      }
      var groups = r.groups || [];
      if (!groups.length) { host.innerHTML = '<p class="muted">No universal code yet. Create one above.</p>'; return; }

      host.innerHTML = '';
      groups.forEach(function (g) {
        var wrap = document.createElement('div');
        wrap.className = 'uni-code';
        var stateLabel = g.status === 'revoked' ? 'rotated out' : 'active';
        var head = document.createElement('div');
        head.className = 'admin-result-head';
        head.innerHTML = '<strong>' + esc(g.code) + ' <span class="muted">(' + stateLabel + ')</span> — ' +
          g.count + (g.count === 1 ? ' sign-up' : ' sign-ups') + '</strong>';
        if (g.uses.length) {
          var copy = document.createElement('button');
          copy.type = 'button'; copy.className = 'btn btn--sm btn--ghost'; copy.textContent = 'Copy as CSV';
          copy.addEventListener('click', function () { copyText(copy, csvFor(g.uses)); });
          head.appendChild(copy);
        }
        wrap.appendChild(head);

        if (g.uses.length) {
          var sx = document.createElement('div'); sx.className = 'scroll-x';
          sx.innerHTML = '<table class="admin-table"><thead><tr><th>Email</th><th>Date used</th><th>Access</th></tr></thead><tbody>' +
            g.uses.map(function (u) { return accessRow(u); }).join('') +
            '</tbody></table>';
          wrap.appendChild(sx);
        } else {
          var p = document.createElement('p'); p.className = 'muted'; p.textContent = 'No sign-ups with this code yet.';
          wrap.appendChild(p);
        }
        host.appendChild(wrap);
      });
    } catch (e) {
      host.innerHTML = '<p class="notice notice--error">Could not load universal code uses.</p>';
    }
  }

  // ── Generate a batch ─────────────────────────────────────────────────
  var genForm = T.el('[data-form="gen"]', root);
  genForm.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    var notice = T.el('[data-gen-notice]', root);
    var btn = genForm.querySelector('button[type="submit"]');
    btn.disabled = true;
    T.setNotice(notice, '');
    try {
      var r = await T.apiPost('/api/admin/gen-codes.php', {
        count: parseInt(genForm.count.value, 10),
        batch_label: genForm.batch_label.value.trim()
      });
      T.el('[data-gen-count]', root).textContent = r.count + ' codes created (batch: ' + r.batch_label + '). Copy them now.';
      T.el('[data-gen-codes]', root).value = r.codes.join('\n');
      T.el('[data-gen-result]', root).hidden = false;
      loadCodes(); loadStats();
    } catch (e) {
      T.setNotice(notice, e.message, 'error');
    } finally { btn.disabled = false; }
  });

  // ── Issue to buyers ──────────────────────────────────────────────────
  var issueForm = T.el('[data-form="issue"]', root);
  issueForm.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    var notice = T.el('[data-issue-notice]', root);
    var btn = issueForm.querySelector('button[type="submit"]');
    btn.disabled = true;
    T.setNotice(notice, '');
    try {
      var r = await T.apiPost('/api/admin/issue-codes.php', {
        emails: issueForm.emails.value,
        batch_label: issueForm.batch_label.value.trim()
      });
      var rows = [];
      r.issued.forEach(function (x) { rows.push('<tr><td>' + esc(x.email) + '</td><td>' + esc(x.code) + '</td><td>issued</td></tr>'); });
      r.skipped.forEach(function (x) { rows.push('<tr><td>' + esc(x.email) + '</td><td>-</td><td class="muted">skipped: ' + esc(x.reason) + '</td></tr>'); });
      var tbl = T.el('[data-issue-result]', root);
      tbl.querySelector('tbody').innerHTML = rows.join('');
      tbl.hidden = false;
      T.setNotice(notice, r.issued.length + ' issued, ' + r.skipped.length + ' skipped. Codes are shown below so you can share them until email is live.', 'success');
      loadCodes(); loadStats();
    } catch (e) {
      T.setNotice(notice, e.message, 'error');
    } finally { btn.disabled = false; }
  });

  // ── Universal code ───────────────────────────────────────────────────
  var uniBtn = T.el('[data-action="gen-universal"]', root);
  if (uniBtn) {
    uniBtn.addEventListener('click', async function () {
      var notice = T.el('[data-universal-notice]', root);
      T.setNotice(notice, 'Creating your universal code...', null);
      uniBtn.disabled = true;
      try {
        var r = await T.apiPost('/api/admin/gen-universal.php', {});
        T.el('[data-universal-code]', root).value = r.code;
        T.el('[data-universal-result]', root).hidden = false;
        T.setNotice(notice, 'Done. Paste this code into your WyzCore automation email.', 'success');
        loadCodes();
        loadUniversalUses();
      } catch (e) {
        T.setNotice(notice, e.message, 'error');
      } finally { uniBtn.disabled = false; }
    });
  }

  // ── Email test ───────────────────────────────────────────────────────
  var testForm = T.el('[data-form="testmail"]', root);
  if (testForm) {
    testForm.addEventListener('submit', async function (ev) {
      ev.preventDefault();
      var notice = T.el('[data-testmail-notice]', root);
      var btn = testForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      T.setNotice(notice, 'Sending...', null);
      try {
        var r = await T.apiPost('/api/admin/send-test-email.php', { to: testForm.to.value.trim() });
        var kind = r.status === 'sent' ? 'success' : (r.status === 'failed' ? 'error' : null);
        T.setNotice(notice, r.message + (r.error ? ' (' + r.error + ')' : ''), kind);
      } catch (e) {
        T.setNotice(notice, e.message, 'error');
      } finally { btn.disabled = false; }
    });
  }

  // ── Copy buttons ─────────────────────────────────────────────────────
  T.els('[data-copy]', root).forEach(function (b) {
    var which = b.getAttribute('data-copy');
    var label = b.textContent;
    b.addEventListener('click', async function () {
      var el = which === 'universal' ? T.el('[data-universal-code]', root) : T.el('[data-gen-codes]', root);
      if (!el) return;
      try { await navigator.clipboard.writeText(el.value); b.textContent = 'Copied'; }
      catch (e) { el.select(); }
      setTimeout(function () { b.textContent = label; }, 1500);
    });
  });

  loadStats();
  loadCodes();
  loadUniversalUses();
})();
