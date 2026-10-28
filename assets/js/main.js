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

  // Product image sliders: auto-advance every 4 seconds, dots, desktop thumbnails and touch swipe.
  document.querySelectorAll('[data-slider]').forEach(function (slider) {
    var track = slider.querySelector('.hero-slider__track');
    var count = track.children.length;
    if (count < 2) return;
    var dots = slider.querySelectorAll('[data-dot]');
    var thumbs = slider.parentElement.querySelectorAll('[data-slide-to]');
    var index = 0, timer = null, paused = false;

    function go(i) {
      index = (i + count) % count;
      track.style.transform = 'translateX(' + (-100 * index) + '%)';
      dots.forEach(function (d, n) { d.classList.toggle('is-active', n === index); });
      thumbs.forEach(function (t, n) { t.classList.toggle('is-active', n === index); });
    }
    function restart() {
      clearInterval(timer);
      timer = setInterval(function () {
        // Only move when visible and not being touched/hovered.
        if (!paused && !document.hidden && slider.offsetParent !== null) go(index + 1);
      }, 4000);
    }

    dots.forEach(function (d) { d.addEventListener('click', function () { go(+d.dataset.dot); restart(); }); });
    thumbs.forEach(function (t) { t.addEventListener('click', function () { go(+t.dataset.slideTo); restart(); }); });
    slider.addEventListener('mouseenter', function () { paused = true; });
    slider.addEventListener('mouseleave', function () { paused = false; });

    var startX = null;
    slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; paused = true; }, { passive: true });
    slider.addEventListener('touchend', function (e) {
      paused = false;
      if (startX === null) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 40) { go(index + (dx < 0 ? 1 : -1)); restart(); }
      startX = null;
    });

    restart();
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
