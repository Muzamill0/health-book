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
    } catch (e) {
      setStatus(e.message, true);
    } finally {
      busy = false;
      if (btn) { btn.disabled = false; btn.innerHTML = label; }
    }
  }

  document.querySelectorAll('[data-download]').forEach(function (btn) {
    btn.addEventListener('click', function () { download(btn.dataset.download, btn); });
  });
  if (cfg.auto) download(cfg.auto, document.querySelector('[data-download="' + cfg.auto + '"]'));
})();
