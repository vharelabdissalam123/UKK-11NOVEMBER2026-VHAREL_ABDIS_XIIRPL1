<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
require_once __DIR__ . '/../config/transaksi.php';
cek_role(['petugas']);
$id_user = (int) $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    [$ok, $pesan, $id_parkir] = proses_masuk(
        $pdo,
        $_POST['plat_nomor'] ?? '',
        $_POST['jenis_kendaraan'] ?? '',
        (int) ($_POST['id_area'] ?? 0),
        $id_user
    );
    if ($ok) {
        set_flash('ok', $pesan);
        redirect('struk.php?id=' . $id_parkir); // langsung cetak tiket
    }
    set_flash('error', $pesan);
    redirect('masuk.php');
}

$area  = $pdo->query('SELECT id_area, nama_area, kapasitas, terisi FROM tb_area_parkir ORDER BY nama_area LIMIT 100')->fetchAll();
$tarif = $pdo->query('SELECT jenis_kendaraan, tarif_per_jam FROM tb_tarif ORDER BY id_tarif LIMIT 20')->fetchAll();

$judul = 'Kasir Masuk - Beri Tiket';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid2">
  <div class="panel">
    <h3>KENDARAAN MASUK</h3>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="baris"><label>No. Polisi</label><input type="text" name="plat_nomor" maxlength="15" required autofocus></div>
      <div class="baris"><label>Jenis</label>
        <select name="jenis_kendaraan">
          <?php foreach (JENIS_KENDARAAN as $j): ?><option value="<?= $j ?>"><?= ucfirst($j) ?></option><?php endforeach; ?>
        </select></div>
      <div class="baris"><label>Area Parkir</label>
        <select name="id_area">
          <?php foreach ($area as $a): $sisa = (int) $a['kapasitas'] - (int) $a['terisi']; ?>
            <option value="<?= (int) $a['id_area'] ?>" <?= $sisa <= 0 ? 'disabled' : '' ?>><?= e($a['nama_area']) ?> (sisa <?= $sisa ?>)</option>
          <?php endforeach; ?>
        </select></div>
      <div class="baris"><label>Catatan</label><div class="status-ok">WAKTU MASUK DICATAT OTOMATIS</div></div>
      <button class="btn btn-hijau" type="submit">Simpan &amp; Cetak Tiket</button>
    </form>
  </div>

  <div class="panel">
    <h3>TARIF PARKIR</h3>
    <table>
      <tr><th>Jenis Kendaraan</th><th class="kanan-teks">Tarif per Jam</th></tr>
      <?php foreach ($tarif as $t): ?>
        <tr><td><?= e(ucfirst($t['jenis_kendaraan'])) ?></td><td class="kanan-teks"><?= rupiah($t['tarif_per_jam']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$tarif): ?><tr><td colspan="2">Tarif belum diatur admin.</td></tr><?php endif; ?>
    </table>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>