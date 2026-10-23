(function () {
  // Rotating announcement bar
  var bar = document.querySelector('[data-rotate]');
  if (bar) {
    var msgs = bar.querySelectorAll('.announcement__msg');
    var idx = 0;
    if (msgs.length > 1) {
      setInterval(function () {
        msgs[idx].classList.remove('is-active');
        idx = (idx + 1) % msgs.length;
        msgs[idx].classList.add('is-active');
      }, 4000);
    }
  }

  // Evergreen top-bar timer: always between 1 and 2 hours left; adds an hour whenever it drops below one.
  var topTimer = document.querySelector('[data-topbar-timer]');
  if (topTimer) {
    var HOUR = 3600 * 1000;
    var KEY = 'hb_topbar_timer_end';
    var timerEnd = 0;
    try { timerEnd = parseInt(localStorage.getItem(KEY), 10) || 0; } catch (e) {}
    var pad2 = function (n) { return String(n).padStart(2, '0'); };
    var tickTop = function () {
      var now = Date.now();
      if (!timerEnd || timerEnd - now > 2 * HOUR) timerEnd = now + 2 * HOUR;
      while (timerEnd - now < HOUR) timerEnd += HOUR;
      try { localStorage.setItem(KEY, String(timerEnd)); } catch (e) {}
      var left = Math.floor((timerEnd - now) / 1000);
      topTimer.querySelector('[data-t-h]').textContent = pad2(Math.floor(left / 3600));
      topTimer.querySelector('[data-t-m]').textContent = pad2(Math.floor(left % 3600 / 60));
      topTimer.querySelector('[data-t-s]').textContent = pad2(left % 60);
    };
    tickTop();
    setInterval(tickTop, 1000);
  }

  // Mobile nav
  var toggle = document.querySelector('[data-nav-toggle]');
  var nav = document.querySelector('[data-nav]');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open);
    });
    nav.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', function () { nav.classList.remove('is-open'); });
    });
  }

  // Product gallery thumbnails
  var main = document.querySelector('[data-main-image]');
  document.querySelectorAll('[data-thumb]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      main.src = btn.dataset.thumb;
      document.querySelectorAll('[data-thumb]').forEach(function (b) { b.classList.remove('is-active'); });
      btn.classList.add('is-active');
    });
  });

  // Countdown ("daily" resets at local midnight, otherwise an ISO end date)
  var cd = document.querySelector('[data-countdown]');
  if (cd) {
    var target = cd.dataset.countdown;
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var tick = function () {
      var now = new Date();
      var end;
      if (target === 'daily') {
        end = new Date(now); end.setHours(24, 0, 0, 0);
      } else {
        end = new Date(target);
      }
      var diff = Math.max(0, Math.floor((end - now) / 1000));
      if (diff === 0 && target !== 'daily') { cd.style.display = 'none'; return; }
      cd.querySelector('[data-h]').textContent = pad(Math.floor(diff / 3600));
      cd.querySelector('[data-m]').textContent = pad(Math.floor(diff % 3600 / 60));
      cd.querySelector('[data-s]').textContent = pad(diff % 60);
    };
    tick();
    setInterval(tick, 1000);
  }

  // Newsletter (AJAX with normal-form fallback)
  var form = document.querySelector('[data-subscribe]');
  if (form) {
    var out = document.querySelector('[data-subscribe-msg]');
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var btn = form.querySelector('button');
      btn.disabled = true;
      fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) { out.textContent = d.message; if (d.ok) form.reset(); })
        .catch(function () { out.textContent = 'Something went wrong. Please try again.'; })
        .finally(function () { btn.disabled = false; });
    });
  }
})();
