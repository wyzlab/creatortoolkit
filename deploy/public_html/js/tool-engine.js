/* =====================================================================
   tool-engine.js  ·  the shared engine that makes ten tools one build
   ---------------------------------------------------------------------
   Each tool is a config object (js/tools/[slug].js), not a program. The
   engine owns: loading saved state, rendering a step from config, autosave
   (2s debounce + blur), prefill and the stale-field flow, validation,
   scoring, the progress bar, completion, and the result view.

   ES module. A tool page mounts it like:

     <script type="module" nonce="...">
       import cfg from '/js/tools/one-page-offer.js';
       import { mountTool } from '/js/tool-engine.js';
       mountTool(cfg);
     </script>

   Config shape (Technical Spec section 9):
     { slug, gate, title, productId, publishedOn,
       steps: [ { title, help?, stuck?, fields: [ field ] } ],
       scoring: { type: 'checklist'|'banded'|'none', ... },
       writesToProfile: { 'profile.key': 'field_key' },
       result: { template, printable } }

   field:
     { key, type, label, hint?, required?, options?,
       prefillFrom?: 'profile.key' | ['profile.key', ...],
       placeholder? }
   ===================================================================== */

const DEBOUNCE_MS = 2000;

/* ── small local helpers (do not depend on load order of main.js) ────── */
function h(tag, attrs, children) {
  const node = document.createElement(tag);
  if (attrs) {
    for (const k in attrs) {
      if (k === 'class') node.className = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else if (k === 'text') node.textContent = attrs[k];
      else if (k.startsWith('on') && typeof attrs[k] === 'function') {
        node.addEventListener(k.slice(2).toLowerCase(), attrs[k]);
      } else if (attrs[k] === true) node.setAttribute(k, '');
      else if (attrs[k] !== false && attrs[k] != null) node.setAttribute(k, attrs[k]);
    }
  }
  (children || []).forEach(function (c) {
    if (c == null) return;
    node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  });
  return node;
}
function api() { return window.Toolkit; }
function byPath(obj, path) {
  return path.split('.').reduce(function (o, k) { return (o == null ? undefined : o[k]); }, obj);
}

/* ── the engine ──────────────────────────────────────────────────────── */
export function mountTool(config, options) {
  options = options || {};
  const mountSel = options.mount || '[data-tool-root]';

  function boot() {
    const root = document.querySelector(mountSel);
    if (!root) { console.error('tool-engine: mount point not found', mountSel); return; }
    new ToolEngine(config, root).start();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
}

/**
 * Auto-boot: the tool page embeds its definition as
 * <script type="application/json" id="tool-config">{...}</script> next to a
 * [data-tool-root]. The engine reads it and mounts, so no inline JS is needed
 * on the page (CSP stays strict) and PHP is the single source of the config.
 */
function autoBoot() {
  const holder = document.getElementById('tool-config');
  const root = document.querySelector('[data-tool-root]');
  if (!holder || !root) return;
  let config;
  try { config = JSON.parse(holder.textContent); }
  catch (e) { console.error('tool-engine: bad tool-config JSON', e); return; }
  mountTool(config);
}

class ToolEngine {
  constructor(config, root) {
    this.cfg = config;
    this.root = root;
    this.state = {
      answers: {},
      currentStep: 1,
      profileVersion: 1,
      prefill: {},          // { field_key: { value, source_tool, is_stale } }
      completed: false
    };
    this._saveTimer = null;
    this._dismissedStale = {};
  }

  async start() {
    this.renderShell();
    await this.load();
    this.renderStep();
  }

  /* ── load saved progress + prefill from the server ─────────────────── */
  async load() {
    try {
      const data = await api().apiGet('/api/get-profile.php?tool_slug=' + encodeURIComponent(this.cfg.slug));
      this.state.profileVersion = data.profile_version || 1;
      this.state.answers = data.answers || {};
      this.state.prefill = data.prefill || {};
      this.state.currentStep = Math.min(Math.max(1, data.current_step || 1), this.cfg.steps.length);
      // Seed empty answers from prefill so carried values are visible + editable.
      this.applyPrefill();
    } catch (e) {
      // A fresh tool with no saved state simply starts empty.
      if (e.status && e.status !== 404) {
        this.notice(e.message, 'error');
      }
    }
  }

  applyPrefill() {
    const pf = this.state.prefill || {};
    for (const key in pf) {
      const cur = this.state.answers[key];
      if ((cur == null || cur === '') && pf[key] && pf[key].value != null) {
        this.state.answers[key] = pf[key].value;
      }
    }
  }

  /* ── shell: header, progress, step slot, autosave flag ─────────────── */
  renderShell() {
    this.root.innerHTML = '';
    this.elHead = h('div', { class: 'tool-shell__head' }, [
      h('span', { class: 'badge badge--studio', text: 'Studio Original' }),
      h('h1', { text: this.cfg.title }),
      this.elProgress = h('div', { class: 'progress', role: 'progressbar' }, [
        this.elProgressFill = h('div', { class: 'progress__fill' })
      ]),
      this.elProgressLabel = h('p', { class: 'progress__label' })
    ]);
    this.elNotice = h('div', { class: 'notice', hidden: true, 'data-notice': true });
    this.elStep = h('div', { class: 'tool-step' });
    this.elSaveFlag = h('div', { class: 'autosave-flag', 'aria-live': 'polite' });

    this.root.appendChild(h('div', { class: 'tool-shell' }, [
      this.elHead, this.elNotice, this.elStep, this.elSaveFlag,
      this.renderWyzaiHelper()
    ]));
  }

  /** A "Use AI to help" card with the tool's pre-filled prompt and a copy button. */
  renderWyzaiHelper() {
    const prompt = this.cfg.wyzaiPrompt;
    if (!prompt) return document.createComment('no-wyzai');
    const ta = h('textarea', { class: 'textarea wyzai__prompt', readonly: true, rows: 4 });
    ta.value = prompt;
    const copyBtn = h('button', { class: 'btn btn--sm btn--ghost', type: 'button',
      onClick: async () => {
        try { await navigator.clipboard.writeText(prompt); copyBtn.textContent = 'Copied'; }
        catch (e) { ta.select(); }
        setTimeout(() => { copyBtn.textContent = 'Copy this prompt'; }, 1500);
      }
    }, ['Copy this prompt']);
    return h('details', { class: 'wyzai' }, [
      h('summary', {}, ['Want a thinking partner? Use AI to help.']),
      h('p', { class: 'muted', text: 'Paste this into the WyzAI Assistant or any AI you already use. Answer one question at a time.' }),
      ta,
      copyBtn
    ]);
  }

  notice(msg, kind) {
    this.elNotice.textContent = msg || '';
    this.elNotice.className = 'notice' + (kind ? ' notice--' + kind : '');
    this.elNotice.hidden = !msg;
  }

  updateProgress() {
    const total = this.cfg.steps.length;
    const pct = Math.round(((this.state.currentStep - (this.state.completed ? 0 : 1)) / total) * 100);
    const shown = this.state.completed ? 100 : Math.max(0, pct);
    this.elProgressFill.style.width = shown + '%';
    this.elProgress.setAttribute('aria-valuenow', String(shown));
    this.elProgressLabel.textContent = this.state.completed
      ? 'Complete'
      : 'Step ' + this.state.currentStep + ' of ' + total;
  }

  /* ── render the current step ───────────────────────────────────────── */
  renderStep() {
    if (this.state.completed) return;
    const step = this.cfg.steps[this.state.currentStep - 1];
    this.elStep.innerHTML = '';
    this.updateProgress();

    this.elStep.appendChild(h('h2', { class: 'tool-step__title', text: step.title }));
    if (step.help) this.elStep.appendChild(h('p', { class: 'muted', text: step.help }));

    (step.fields || []).forEach((field) => {
      this.elStep.appendChild(this.renderField(field));
    });

    if (step.stuck) {
      this.elStep.appendChild(h('details', { class: 'notice' }, [
        h('summary', { text: 'Stuck on this? Tap for a nudge.' }),
        h('p', { text: step.stuck })
      ]));
    }

    // Navigation
    const isLast = this.state.currentStep === this.cfg.steps.length;
    const nav = h('div', { class: 'tool-nav' }, [
      h('button', {
        class: 'btn btn--ghost', type: 'button',
        hidden: this.state.currentStep === 1,
        onClick: () => this.prev()
      }, ['Back']),
      h('button', {
        class: 'btn ' + (isLast ? 'btn--cta' : 'btn--primary'), type: 'button',
        onClick: () => (isLast ? this.complete() : this.next())
      }, [isLast ? 'Finish and see my result' : 'Next'])
    ]);
    this.elStep.appendChild(nav);
    // Move focus to the step heading for screen readers / keyboard users.
    this.elStep.querySelector('.tool-step__title').setAttribute('tabindex', '-1');
    this.elStep.querySelector('.tool-step__title').focus();
  }

  renderField(field) {
    const wrap = h('div', { class: 'field', 'data-field': field.key });
    const id = 'f_' + field.key;

    if (field.type === 'note' || field.type === 'help') {
      wrap.className = 'notice';
      wrap.appendChild(h('p', { text: field.label }));
      return wrap;
    }

    if (field.label) {
      wrap.appendChild(h('label', { class: 'field__label', for: id }, [
        field.label + (field.required ? '' : ''),
      ]));
    }

    // Stale-field note: user changed an upstream value this depends on.
    const pf = this.state.prefill[field.key];
    if (pf && pf.is_stale && !this._dismissedStale[field.key]) {
      wrap.appendChild(this.staleNote(field, pf));
    } else if (pf && pf.source_tool && !pf.is_stale) {
      wrap.appendChild(h('span', { class: 'field__hint', text: 'Carried from your earlier answers. Edit if it should change.' }));
    }

    const val = this.state.answers[field.key];
    let control;

    switch (field.type) {
      case 'textarea':
        control = h('textarea', {
          id: id, class: 'textarea', name: field.key,
          placeholder: field.placeholder || '', rows: field.rows || 3
        });
        control.value = val || '';
        break;
      case 'select':
        control = h('select', { id: id, class: 'select', name: field.key });
        control.appendChild(h('option', { value: '' }, [field.placeholder || 'Choose one']));
        (field.options || []).forEach((opt) => {
          const o = typeof opt === 'string' ? { value: opt, label: opt } : opt;
          const optEl = h('option', { value: o.value }, [o.label]);
          if (String(val) === String(o.value)) optEl.selected = true;
          control.appendChild(optEl);
        });
        break;
      case 'radio':
        control = h('div', { class: 'radio-group', role: 'radiogroup' });
        (field.options || []).forEach((opt, i) => {
          const o = typeof opt === 'string' ? { value: opt, label: opt } : opt;
          const rid = id + '_' + i;
          const line = h('div', { class: 'checkline' }, [
            h('input', { type: 'radio', id: rid, name: field.key, value: o.value,
              checked: String(val) === String(o.value) }),
            h('label', { for: rid }, [o.label])
          ]);
          control.appendChild(line);
        });
        break;
      case 'checkbox':
        control = h('div', { class: 'checkline' }, [
          h('input', { type: 'checkbox', id: id, name: field.key, checked: !!val }),
          h('label', { for: id }, [field.checkboxLabel || field.label || 'Yes'])
        ]);
        break;
      case 'checklist':
        control = h('div', { class: 'checklist-group' });
        (field.items || []).forEach((item) => {
          const it = typeof item === 'string' ? { key: item, label: item } : item;
          const cid = id + '_' + it.key;
          const checked = val && typeof val === 'object' ? !!val[it.key] : false;
          control.appendChild(h('div', { class: 'checkline' }, [
            h('input', { type: 'checkbox', id: cid, 'data-item': it.key, checked: checked }),
            h('label', { for: cid }, [it.label])
          ]));
        });
        break;
      case 'email':
      case 'text':
      default:
        control = h('input', {
          id: id, class: 'input', type: field.type === 'email' ? 'email' : 'text',
          name: field.key, placeholder: field.placeholder || ''
        });
        control.value = val || '';
        break;
    }

    // Wire autosave on this control.
    this.wireField(wrap, field, control);
    wrap.appendChild(control);

    if (field.hint) wrap.appendChild(h('span', { class: 'field__hint', text: field.hint }));
    wrap.appendChild(h('div', { class: 'field__error', hidden: true }));
    return wrap;
  }

  staleNote(field, pf) {
    const note = h('div', { class: 'notice notice--stale' });
    const label = pf.stale_message ||
      'You changed something earlier that this used. Update this to match?';
    note.appendChild(h('span', { text: label }));
    const actions = h('span', {}, [
      h('button', {
        class: 'btn btn--sm btn--primary', type: 'button',
        onClick: () => {
          if (pf.value != null) {
            this.state.answers[field.key] = pf.value;
          }
          this._dismissedStale[field.key] = true;
          this.renderStep();
          this.scheduleSave();
        }
      }, ['Update it']),
      h('button', {
        class: 'btn btn--sm btn--ghost', type: 'button',
        onClick: () => { this._dismissedStale[field.key] = true; this.renderStep(); }
      }, ['Keep mine'])
    ]);
    note.appendChild(actions);
    return note;
  }

  wireField(wrap, field, control) {
    const read = () => {
      switch (field.type) {
        case 'checkbox':
          return control.querySelector('input').checked;
        case 'checklist': {
          const obj = {};
          control.querySelectorAll('input[type="checkbox"]').forEach((cb) => {
            obj[cb.getAttribute('data-item')] = cb.checked;
          });
          return obj;
        }
        case 'radio': {
          const sel = control.querySelector('input:checked');
          return sel ? sel.value : '';
        }
        default:
          return control.value;
      }
    };
    const onChange = () => {
      this.state.answers[field.key] = read();
      this.clearFieldError(wrap);
      this.scheduleSave();
    };
    const onBlur = () => {
      this.state.answers[field.key] = read();
      this.saveNow();
    };
    ['input', 'change'].forEach((ev) => control.addEventListener(ev, onChange, true));
    control.addEventListener('blur', onBlur, true);
  }

  /* ── autosave ──────────────────────────────────────────────────────── */
  scheduleSave() {
    this.flag('Saving...');
    clearTimeout(this._saveTimer);
    this._saveTimer = setTimeout(() => this.saveNow(), DEBOUNCE_MS);
  }
  async saveNow() {
    clearTimeout(this._saveTimer);
    try {
      const r = await api().apiPost('/api/save-progress.php', {
        tool_slug: this.cfg.slug,
        step: this.state.currentStep,
        answers: this.state.answers,
        profile_version: this.state.profileVersion
      });
      if (r.profile_version) this.state.profileVersion = r.profile_version;
      this.flag('Saved', true);
    } catch (e) {
      if (e.status === 409) {
        // Stale version: another tab wrote first. Reload state, keep the user informed.
        this.notice('This was open in another tab. We refreshed your saved answers.', null);
        await this.load();
        this.renderStep();
      } else {
        this.flag('Not saved. We will retry.', false);
      }
    }
  }
  flag(text, saved) {
    this.elSaveFlag.textContent = text;
    this.elSaveFlag.className = 'autosave-flag' + (saved ? ' autosave-flag--saved' : '');
  }

  /* ── navigation + validation ───────────────────────────────────────── */
  validateStep() {
    const step = this.cfg.steps[this.state.currentStep - 1];
    let firstBad = null;
    (step.fields || []).forEach((field) => {
      if (!field.required) return;
      const v = this.state.answers[field.key];
      const empty = v == null || v === '' ||
        (typeof v === 'object' && Object.keys(v).every((k) => !v[k]));
      const wrap = this.elStep.querySelector('[data-field="' + field.key + '"]');
      if (empty) {
        this.showFieldError(wrap, 'This one is needed to continue.');
        if (!firstBad) firstBad = wrap;
      }
    });
    if (firstBad) {
      firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' });
      const input = firstBad.querySelector('input, textarea, select');
      if (input) input.focus();
      return false;
    }
    return true;
  }
  showFieldError(wrap, msg) {
    if (!wrap) return;
    wrap.classList.add('field--invalid');
    const err = wrap.querySelector('.field__error');
    if (err) { err.textContent = msg; err.hidden = false; }
  }
  clearFieldError(wrap) {
    if (!wrap) return;
    wrap.classList.remove('field--invalid');
    const err = wrap.querySelector('.field__error');
    if (err) { err.hidden = true; }
  }

  next() {
    if (!this.validateStep()) return;
    this.saveNow();
    if (this.state.currentStep < this.cfg.steps.length) {
      this.state.currentStep++;
      window.scrollTo({ top: 0, behavior: 'smooth' });
      this.renderStep();
    }
  }
  prev() {
    if (this.state.currentStep > 1) {
      this.state.currentStep--;
      window.scrollTo({ top: 0, behavior: 'smooth' });
      this.renderStep();
    }
  }

  /* Is a value empty? Matches the server: whitespace-only counts as empty,
     and a checklist with nothing ticked counts as empty. */
  isEmptyValue(v) {
    if (v == null) return true;
    if (typeof v === 'string') return v.trim() === '';
    if (typeof v === 'object') return Object.keys(v).every((k) => !v[k]);
    return false;
  }

  /* Check every required field across all steps. Returns {step, key, label}
     of the first empty one, or null. Lets us point the learner straight at it
     instead of failing on the last step with a vague message. */
  firstMissingRequired() {
    for (let i = 0; i < this.cfg.steps.length; i++) {
      for (const f of (this.cfg.steps[i].fields || [])) {
        if (f.required && this.isEmptyValue(this.state.answers[f.key])) {
          return { step: i + 1, key: f.key, label: f.label || 'This field' };
        }
      }
    }
    return null;
  }

  /* ── completion ────────────────────────────────────────────────────── */
  async complete() {
    // Check ALL required fields, not just the current step, and jump to the
    // first one that is still empty.
    const missing = this.firstMissingRequired();
    if (missing) {
      this.state.currentStep = missing.step;
      this.renderStep();
      const wrap = this.elStep.querySelector('[data-field="' + missing.key + '"]');
      this.showFieldError(wrap, 'This one is needed to finish.');
      if (wrap) {
        wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const input = wrap.querySelector('input, textarea, select');
        if (input) input.focus();
      }
      this.notice('One answer is still needed: "' + missing.label + '". I brought you to it and marked it below.', 'error');
      return;
    }

    await this.saveNow();
    try {
      const r = await api().apiPost('/api/complete-tool.php', {
        tool_slug: this.cfg.slug,
        answers: this.state.answers,
        profile_version: this.state.profileVersion
      });
      this.state.completed = true;
      this.notice('', null);
      this.renderResult(r);
    } catch (e) {
      // Server backstop: if it still reports missing fields, guide there too.
      const miss = e.data && Array.isArray(e.data.missing) ? e.data.missing[0] : null;
      if (miss) {
        const step = this.stepOfField(miss);
        if (step) { this.state.currentStep = step; this.renderStep();
          const wrap = this.elStep.querySelector('[data-field="' + miss + '"]');
          this.showFieldError(wrap, 'This one is needed to finish.');
        }
      }
      this.notice(e.message, 'error');
    }
  }

  stepOfField(key) {
    for (let i = 0; i < this.cfg.steps.length; i++) {
      if ((this.cfg.steps[i].fields || []).some((f) => f.key === key)) return i + 1;
    }
    return null;
  }

  renderResult(r) {
    this.updateProgress();
    this.elStep.innerHTML = '';
    // The server builds result HTML from a whitelist (it also gets emailed).
    const box = h('div', { class: 'tool-result' });
    if (r && r.result && r.result.html) {
      box.innerHTML = r.result.html;
    } else {
      box.appendChild(h('h2', { text: 'Nicely done.' }));
      box.appendChild(h('p', { text: 'Your answers are saved. Your result is on its way to your email too.' }));
    }
    if (r && r.pdf_unlocked) {
      box.appendChild(h('p', {}, [
        h('a', { class: 'btn btn--primary', href: '/api/download-pdf.php?tool_slug=' + encodeURIComponent(this.cfg.slug) },
          ['Download the ' + this.cfg.title + ' PDF'])
      ]));
    }
    if (r && r.gate_complete) {
      const msg = h('div', { class: 'notice notice--success' }, [
        h('p', { text: 'You finished this gate. Your summary, your coach code, and your PDF downloads are ready.' })
      ]);
      if (r.coach_name) {
        msg.appendChild(h('p', { text: 'Say hello to your ' + r.coach_name + '.' }));
      }
      box.appendChild(msg);
      box.appendChild(h('p', {}, [
        h('a', { class: 'btn btn--cta', href: '/results/gate.php?gate=' + this.cfg.gate }, ['See your gate summary'])
      ]));
    }
    box.appendChild(h('p', { class: 'mt-lg' }, [
      h('a', { class: 'btn btn--ghost', href: '/dashboard.php' }, ['Back to dashboard'])
    ]));
    this.elStep.appendChild(box);
    this.elSaveFlag.textContent = '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
}

/* Also expose on window for non-module callers / debugging. */
window.ToolEngine = { mountTool };
export default { mountTool };

/* Boot from the page's embedded config. Placed after the class is defined so
   it is never referenced inside its temporal dead zone. We wait for
   DOMContentLoaded so main.js (a deferred classic script that sets
   window.Toolkit) has run before the engine's first API call. */
if (document.readyState === 'complete') {
  autoBoot();                       // DOMContentLoaded already fired
} else {
  // 'loading' or 'interactive': wait for DOMContentLoaded, by which point the
  // deferred main.js has run and window.Toolkit exists.
  document.addEventListener('DOMContentLoaded', autoBoot, { once: true });
}
