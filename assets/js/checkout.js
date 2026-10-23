(function () {
  // Step 1: collect name + email and create the Paddle transaction on our server.
  // Step 2: open Paddle's inline checkout for that transaction inside #paddle-checkout.
  var cfg = window.CHECKOUT;
  var form = document.getElementById('checkout-form');
  var btn = document.getElementById('pay-btn');
  var errBox = document.getElementById('pay-error');
  var step = document.getElementById('paddle-step');
  var who = document.getElementById('paddle-who');
  var btnLabel = btn.innerHTML;
  var successUrl = null;
  var loadTimer = null;

  function showError(msg) {
    errBox.textContent = msg;
    errBox.classList.add('is-visible');
    btn.disabled = false;
    btn.innerHTML = btnLabel;
  }

  // The payment form couldn't open: explain why and let the buyer try again.
  function checkoutFailed(msg) {
    clearTimeout(loadTimer);
    showForm();
    showError(msg);
  }

  if (!window.Paddle) {
    showError('The payment system could not be loaded. Please disable any ad/script blocker for this site and refresh the page.');
    btn.disabled = true;
    return;
  }

  if (cfg.sandbox) Paddle.Environment.set('sandbox');
  Paddle.Initialize({
    token: cfg.clientToken,
    eventCallback: function (ev) {
      if (window.console) console.log('[Paddle]', ev.name, ev);
      if (ev.name === 'checkout.loaded') {
        clearTimeout(loadTimer);
      } else if (ev.name === 'checkout.completed' && successUrl) {
        window.location.href = successUrl;
      } else if (ev.name === 'checkout.error') {
        var d = ev.data || ev.error || {};
        checkoutFailed('Payment form error: ' + ((d.error && d.error.detail) || d.detail || d.message || 'the checkout could not be opened.') + ' Please try again.');
      }
    }
  });

  function showForm() {
    clearTimeout(loadTimer);
    try { Paddle.Checkout.close(); } catch (e) {}
    step.hidden = true;
    form.hidden = false;
    btn.disabled = false;
    btn.innerHTML = btnLabel;
  }

  document.getElementById('edit-details').addEventListener('click', showForm);

  form.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    errBox.classList.remove('is-visible');

    var first = form.first_name.value.trim();
    var last = form.last_name.value.trim();
    var email = form.email.value.trim();
    if (!first || !last) return showError('Please enter your first and last name.');
    if (!email) { form.email.focus(); return showError('Email address is required.'); }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { form.email.focus(); return showError('Please enter a valid email address.'); }

    btn.disabled = true;
    btn.textContent = 'Preparing secure payment…';

    var res, data;
    try {
      var body = new FormData();
      body.append('name', first + ' ' + last);
      body.append('email', email);
      body.append('csrf', cfg.csrf);
      body.append('expected', cfg.amount);
      res = await fetch(cfg.createUrl, { method: 'POST', body: body, credentials: 'same-origin' });
      data = await res.json();
    } catch (e) {
      return showError('Could not start the payment. Please try again.');
    }
    if (!res.ok || !data.transactionId) return showError(data.error || 'Could not start the payment.');

    successUrl = data.successUrl;
    form.hidden = true;
    step.hidden = false;
    who.textContent = first + ' ' + last + ' · ' + email;

    loadTimer = setTimeout(function () {
      checkoutFailed('The payment form did not load. Please check your connection, disable any ad/script blocker and try again.');
    }, 15000);

    Paddle.Checkout.open({
      transactionId: data.transactionId,
      settings: {
        displayMode: 'inline',
        frameTarget: 'paddle-frame',
        frameInitialHeight: 450,
        frameStyle: 'width: 100%; min-width: 286px; background-color: transparent; border: none;',
        variant: 'one-page',
        allowedPaymentMethods: ['card'],
        allowLogout: false,
        showAddDiscounts: false,
        successUrl: data.successUrl
      }
    });
  });
})();
