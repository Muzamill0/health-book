(function () {
  // Homepage cart: toggles books in the server-side cart and keeps the sticky cart bar in sync.
  var cfg = window.CART;
  if (!cfg) return;
  var bar = document.querySelector('[data-cart-bar]');
  var headerCount = document.querySelector('[data-header-cart-count]');

  function render(cart) {
    var ids = cart.ids || [];
    document.querySelectorAll('[data-book]').forEach(function (card) {
      var on = ids.indexOf(parseInt(card.dataset.book, 10)) !== -1;
      card.classList.toggle('is-added', on);
      var btn = card.querySelector('[data-cart-toggle]');
      if (btn) btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });

    if (headerCount) {
      var n = cart.count;
      headerCount.textContent = n;
      headerCount.hidden = !n;
    }

    if (!bar) return;
    bar.classList.toggle('is-visible', cart.type === 'books');
    bar.querySelector('[data-cart-count]').textContent = cart.count + (cart.count === 1 ? ' book' : ' books');
    bar.querySelector('[data-cart-total]').textContent = cart.total;

    var hint = bar.querySelector('[data-cart-hint]');
    hint.textContent = '';
    if (cart.bundle_better) {
      hint.innerHTML = '';
      hint.append('Better deal: the ' + cart.bundle_name + ' has ' + cart.bundle_count + ' books for ' + cart.bundle_price + ' — ');
      var a = document.createElement('a');
      a.href = cfg.bundleUrl;
      a.textContent = 'switch to the bundle';
      hint.append(a);
    }
  }

  function send(action, id) {
    var body = new FormData();
    body.append('action', action);
    body.append('csrf', cfg.csrf);
    if (id) body.append('id', id);
    return fetch(cfg.url, { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (cart) {
        if (cart.error) throw new Error(cart.error);
        render(cart);
      })
      .catch(function (e) { alert(e.message || 'Could not update your cart. Please refresh the page.'); });
  }

  document.querySelectorAll('[data-cart-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      send('toggle', btn.dataset.cartToggle).finally(function () { btn.disabled = false; });
    });
  });

  var clear = document.querySelector('[data-cart-clear]');
  if (clear) clear.addEventListener('click', function () { send('clear'); });

  render(cfg);
})();
