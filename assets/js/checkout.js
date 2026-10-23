(function () {
  var cfg = window.CHECKOUT;
  var stripe = Stripe(cfg.publishableKey);
  var elements = stripe.elements({
    mode: 'payment',
    amount: cfg.amount,
    currency: cfg.currency,
    // Card only — must match the invoice's payment_settings in create-payment.php.
    paymentMethodTypes: ['card'],
    appearance: {
      theme: 'stripe',
      variables: { colorPrimary: cfg.primary, borderRadius: '10px', fontFamily: 'Inter, system-ui, sans-serif' }
    }
  });
  elements.create('payment', {
    layout: 'tabs',
    // Hide Apple Pay, Google Pay and Link so only the card form is shown.
    wallets: { applePay: 'never', googlePay: 'never', link: 'never' }
  }).mount('#payment-element');

  var form = document.getElementById('checkout-form');
  var btn = document.getElementById('pay-btn');
  var errBox = document.getElementById('pay-error');
  var btnLabel = btn.innerHTML;

  function showError(msg) {
    errBox.textContent = msg;
    errBox.classList.add('is-visible');
    btn.disabled = false;
    btn.innerHTML = btnLabel;
  }

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
    btn.textContent = 'Processing…';

    var submitted = await elements.submit();
    if (submitted.error) return showError(submitted.error.message);

    var res, data;
    try {
      var body = new FormData();
      body.append('name', first + ' ' + last);
      body.append('email', email);
      body.append('csrf', cfg.csrf);
      body.append('expected', cfg.amount);
      res = await fetch(cfg.createUrl, { method: 'POST', body: body });
      data = await res.json();
    } catch (e) {
      return showError('Could not start the payment. Please try again.');
    }
    if (!res.ok || !data.clientSecret) return showError(data.error || 'Could not start the payment.');

    var result = await stripe.confirmPayment({
      elements: elements,
      clientSecret: data.clientSecret,
      confirmParams: {
        return_url: data.returnUrl,
        payment_method_data: { billing_details: { name: first + ' ' + last, email: email } }
      }
    });
    // Only reached on an immediate error; otherwise Stripe redirects to return_url.
    if (result.error) showError(result.error.message);
  });
})();
