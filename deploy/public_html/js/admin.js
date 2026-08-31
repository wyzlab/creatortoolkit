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
      g.innerHTML =
        '<div class="stat"><span class="stat__num">' + s.buyers + '</span><span class="stat__label">Buyers</span></div>' +
        '<div class="stat"><span class="stat__num">' + s.codes.unclaimed + '</span><span class="stat__label">Codes unused</span></div>' +
        '<div class="stat"><span class="stat__num">' + s.codes.claimed + '</span><span class="stat__label">Codes used</span></div>' +
        gateBits +
        '<div class="stat"><span class="stat__num">' + s.emails.queued + '</span><span class="stat__label">Emails queued</span></div>';

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
        return '<tr><td>' + esc(c.display) + '</td><td>' + esc(c.batch || '') + '</td><td>' +
          esc(c.issued_to || '') + '</td><td>' + esc(c.status) + '</td><td>' + esc(c.claimed_by || '') + '</td></tr>';
      }).join('');
    } catch (e) {
      tbody.innerHTML = '<tr><td colspan="5" class="notice notice--error">Could not load codes.</td></tr>';
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

  // ── Copy buttons ─────────────────────────────────────────────────────
  T.els('[data-copy]', root).forEach(function (b) {
    b.addEventListener('click', async function () {
      var ta = T.el('[data-gen-codes]', root);
      try { await navigator.clipboard.writeText(ta.value); b.textContent = 'Copied'; }
      catch (e) { ta.select(); }
      setTimeout(function () { b.textContent = 'Copy all'; }, 1500);
    });
  });

  loadStats();
  loadCodes();
})();
