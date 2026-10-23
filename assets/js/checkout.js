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

  if (cfg.sandbox) Paddle.Environment.set('sandbox');
  Paddle.Initialize({
    token: cfg.clientToken,
    eventCallback: function (ev) {
      if (ev.name === 'checkout.completed' && successUrl) {
        window.location.href = successUrl;
      } else if (ev.name === 'checkout.error') {
        showError((ev.data && ev.data.error && ev.data.error.detail) || 'The payment form could not be loaded. Please try again.');
      }
    }
  });

  function showError(msg) {
    errBox.textContent = msg;
    errBox.classList.add('is-visible');
    btn.disabled = false;
    btn.innerHTML = btnLabel;
  }

  function showForm() {
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
