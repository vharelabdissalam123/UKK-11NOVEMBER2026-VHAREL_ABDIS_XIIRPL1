</div>
<div class="footbar no-print">
  <?php if ($_SESSION['role'] === 'petugas'): ?>
    <a href="<?= BASE_URL ?>/petugas/struk.php" target="_blank"><b>F1</b>Cetak Struk</a>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>/auth/logout.php"><b>F2</b>Logout</a>
</div>
<script>
// Jam digital
(function () {
  var el = document.getElementById('jam');
  if (!el) return;
  setInterval(function () {
    var d = new Date(), p = function (n) { return String(n).padStart(2, '0'); };
    el.textContent = p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }, 1000);
})();
</script>
</body>
</html>