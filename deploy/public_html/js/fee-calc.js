/* =====================================================================
   fee-calc.js  ·  the checkout fee calculator (Pricing Confidence tool)
   ---------------------------------------------------------------------
   ONE formula, one source: this mirrors inc/fees.php, and both read the same
   fee table (payment_fees) delivered to the page. Never write the maths twice
   by hand from different tables.

   fee = max(amount * rate%, min_fee) + fixed_fee ;  take_home = amount - fee
   Worked example: PHP 500 via GCash (3% + 11) => 26 fee, 474 take-home.
   ===================================================================== */
(function () {
  'use strict';

  function round2(n) { return Math.round(n * 100) / 100; }

  function feeForward(amount, m) {
    var v = amount * m.rate_percent / 100;
    if (m.min_fee != null) v = Math.max(v, m.min_fee);
    var fee = round2(v + m.fixed_fee);
    return { fee: fee, take_home: round2(amount - fee) };
  }

  function solveHeadline(target, m) {
    var r = m.rate_percent / 100, f = m.fixed_fee, min = m.min_fee;
    var ha = r < 1 ? (target + f) / (1 - r) : target + f;
    var h = (min == null || ha * r >= min) ? ha : target + min + f;
    h = Math.ceil(h);
    while (feeForward(h, m).take_home < target) h += 1;
    return h;
  }

  function peso(n) {
    return 'PHP ' + round2(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    if (attrs) for (var k in attrs) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'html') n.innerHTML = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else n.setAttribute(k, attrs[k]);
    }
    (kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }

  /**
   * Mount the calculator into container.
   * fees: array of method objects (rate_percent, min_fee, fixed_fee, label, method_key).
   * initial: {headline, method_key, take_home} or null.
   * onValue: called with {headline, method_key, take_home} whenever it changes.
   */
  function mount(container, fees, initial, onValue) {
    container.innerHTML = '';
    var methods = fees.slice().sort(function (a, b) { return a.sort_order - b.sort_order; });

    var mode = 'forward';
    var amountInput = el('input', { class: 'input', type: 'number', min: '0', step: '1',
      value: (initial && initial.headline) || '500' });
    var methodSel = el('select', { class: 'select' });
    methods.forEach(function (m) {
      var o = el('option', { value: m.method_key }, [m.label]);
      if (initial && initial.method_key === m.method_key) o.selected = true;
      methodSel.appendChild(o);
    });

    var modeWrap = el('div', { class: 'radio-group' }, [
      radio('feemode', 'forward', 'I know my headline price', true),
      radio('feemode', 'backward', 'I know my target take-home', false)
    ]);
    function radio(name, val, label, checked) {
      var id = 'fm_' + val;
      var input = el('input', { type: 'radio', name: name, id: id, value: val });
      if (checked) input.checked = true;
      input.addEventListener('change', function () { mode = val; recompute(); });
      return el('div', { class: 'checkline' }, [input, el('label', { for: id }, [label])]);
    }

    var outFee = el('span', { class: 'result-line__value' });
    var outTake = el('span', { class: 'result-line__value' });
    var outHeadline = el('span', { class: 'result-line__value' });
    var headlineRow = el('div', { class: 'result-line' }, [el('span', { class: 'result-line__label' }, ['Set your headline to']), outHeadline]);
    headlineRow.hidden = true;

    var tableBody = el('tbody');
    var table = el('table', { class: 'admin-table' }, [
      el('thead', {}, [el('tr', {}, [
        el('th', {}, ['Method']), el('th', {}, ['Fee']), el('th', {}, ['Take-home'])
      ])]),
      tableBody
    ]);

    function methodByKey(k) { for (var i = 0; i < methods.length; i++) if (methods[i].method_key === k) return methods[i]; return methods[0]; }

    function recompute() {
      var m = methodByKey(methodSel.value);
      var amount = parseFloat(amountInput.value || '0');
      var headline = amount;
      if (mode === 'backward' && amount > 0) {
        headline = solveHeadline(amount, m);
        outHeadline.textContent = peso(headline);
        headlineRow.hidden = false;
      } else {
        headlineRow.hidden = true;
      }
      var r = feeForward(headline, m);
      outFee.textContent = peso(r.fee);
      outTake.textContent = peso(r.take_home);

      tableBody.innerHTML = '';
      methods.forEach(function (mm) {
        var rr = feeForward(headline, mm);
        tableBody.appendChild(el('tr', {}, [
          el('td', {}, [mm.label]), el('td', {}, [peso(rr.fee)]), el('td', {}, [peso(rr.take_home)])
        ]));
      });

      if (typeof onValue === 'function') {
        onValue({ headline: headline, method_key: m.method_key, take_home: r.take_home });
      }
    }

    amountInput.addEventListener('input', recompute);
    methodSel.addEventListener('change', recompute);

    container.appendChild(el('div', { class: 'fee-calc' }, [
      modeWrap,
      el('div', { class: 'field' }, [el('label', { class: 'field__label' }, ['Amount (PHP)']), amountInput]),
      el('div', { class: 'field' }, [el('label', { class: 'field__label' }, ['Payment method']), methodSel]),
      el('div', { class: 'result-highlight' }, [
        headlineRow,
        el('div', { class: 'result-line' }, [el('span', { class: 'result-line__label' }, ['Estimated fee']), outFee]),
        el('div', { class: 'result-line' }, [el('span', { class: 'result-line__label' }, ['Your take-home']), outTake])
      ]),
      el('h4', { class: 'field__label', style: 'margin-top:16px;' }, ['Compare all methods']),
      el('div', { class: 'scroll-x' }, [table])
    ]));

    recompute();
  }

  window.FeeCalc = { feeForward: feeForward, solveHeadline: solveHeadline, peso: peso, mount: mount };
})();
