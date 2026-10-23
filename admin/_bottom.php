        </div>
    </div>
</div>
<script>
document.querySelectorAll('[data-confirm]').forEach(function (el) {
  el.addEventListener('submit', function (e) { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});
document.querySelectorAll('input[type=file][data-preview]').forEach(function (inp) {
  inp.addEventListener('change', function () {
    var img = document.getElementById(inp.dataset.preview);
    if (img && inp.files[0]) { img.src = URL.createObjectURL(inp.files[0]); img.hidden = false; }
  });
});
</script>
</body>
</html>
