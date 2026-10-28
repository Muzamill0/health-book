(function () {
  // Fetches purchased files through download.php (POST) and saves them from a temporary blob,
  // so the buyer never sees where the files are stored.
  var cfg = window.DOWNLOAD;
  var status = document.querySelector('[data-download-status]');
  if (!cfg) return;
  var busy = false;

  function setStatus(msg, isError) {
    status.textContent = msg;
    status.classList.toggle('is-error', !!isError);
  }

  // Count a finished download and update the buttons: "(N left)", "Download limit reached", hide the ZIP button.
  function used(file) {
    if (!cfg.left) return;
    var keys = file === 'all' ? Object.keys(cfg.left).filter(function (k) { return cfg.left[k] === null || cfg.left[k] > 0; }) : [file];
    keys.forEach(function (k) {
      if (cfg.left[k] === null) return;
      cfg.left[k] = Math.max(0, cfg.left[k] - 1);
      var row = document.querySelector('[data-file="' + k + '"]');
      var b = row && row.querySelector('[data-download]');
      if (!b) return;
      if (cfg.left[k] === 0) {
        var note = document.createElement('small');
        note.className = 'muted';
        note.textContent = 'Download limit reached';
        b.replaceWith(note);
      } else {
        b.textContent = '⬇ Download (' + cfg.left[k] + ' left)';
      }
    });
    var zip = document.querySelector('[data-zip]');
    var stillAvailable = Object.keys(cfg.left).filter(function (k) { return cfg.left[k] === null || cfg.left[k] > 0; }).length;
    if (zip && stillAvailable < 2) zip.remove();
  }

  async function download(file, btn) {
    if (busy) return;
    busy = true;
    var label = btn ? btn.innerHTML : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Preparing…'; }
    setStatus(file === 'all' ? 'Preparing your files… this can take a moment.' : '');
    try {
      var body = new FormData();
      body.append('order', cfg.order);
      body.append('t', cfg.t);
      body.append('csrf', cfg.csrf);
      body.append('file', file);
      var res = await fetch(cfg.url, { method: 'POST', body: body, credentials: 'same-origin' });
      if (!res.ok) {
        var err = {};
        try { err = await res.json(); } catch (e) {}
        throw new Error(err.error || 'Download failed. Please try again.');
      }
      var blob = await res.blob();
      var tmp = URL.createObjectURL(blob);
      var a = document.createElement('a');
      a.href = tmp;
      a.download = file === 'all' ? cfg.zipName : (cfg.names[file] || 'download.pdf');
      document.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () { URL.revokeObjectURL(tmp); }, 10000);
      setStatus('✓ Your download has started. Check your Downloads folder.');
      used(file);
    } catch (e) {
      setStatus(e.message, true);
    } finally {
      busy = false;
      if (btn && btn.isConnected) {
        btn.disabled = false;
        // used() may have changed the label ("N left"); keep it, otherwise restore the original.
        if (btn.textContent === 'Preparing…') btn.innerHTML = label;
      }
    }
  }

  document.querySelectorAll('[data-download]').forEach(function (btn) {
    btn.addEventListener('click', function () { download(btn.dataset.download, btn); });
  });
  if (cfg.auto) download(cfg.auto, document.querySelector('[data-download="' + cfg.auto + '"]'));
})();
