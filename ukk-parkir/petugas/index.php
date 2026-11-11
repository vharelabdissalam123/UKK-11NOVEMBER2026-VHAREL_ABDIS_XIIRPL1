<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
require_once __DIR__ . '/../config/transaksi.php';
cek_role(['petugas']);

$ringkas = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk') AS sedang_parkir,
            (SELECT COUNT(*) FROM tb_transaksi WHERE waktu_masuk >= CURDATE()) AS masuk_hari_ini,
            (SELECT COUNT(*) FROM tb_transaksi WHERE status = 'keluar' AND waktu_keluar >= CURDATE()) AS keluar_hari_ini,
            (SELECT COALESCE(SUM(biaya_total), 0) FROM tb_transaksi WHERE status = 'keluar' AND waktu_keluar >= CURDATE()) AS pendapatan"
)->fetch();

$area  = $pdo->query('SELECT nama_area, kapasitas, terisi FROM tb_area_parkir ORDER BY nama_area LIMIT 100')->fetchAll();
$tarif = $pdo->query('SELECT jenis_kendaraan, tarif_per_jam FROM tb_tarif ORDER BY id_tarif LIMIT 20')->fetchAll();

$terakhir = $pdo->query(
    'SELECT x.id_parkir, k.plat_nomor, k.jenis_kendaraan, x.waktu_masuk, x.waktu_keluar, x.biaya_total, x.status
     FROM tb_transaksi x JOIN tb_kendaraan k ON k.id_kendaraan = x.id_kendaraan
     ORDER BY x.id_parkir DESC LIMIT 10'
)->fetchAll();

$judul = 'Dashboard Kasir';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid3">
  <div class="kartu"><div class="angka"><?= (int) $ringkas['sedang_parkir'] ?></div>Sedang Parkir</div>
  <div class="kartu"><div class="angka"><?= (int) $ringkas['masuk_hari_ini'] ?></div>Masuk Hari Ini</div>
  <div class="kartu"><div class="angka"><?= (int) $ringkas['keluar_hari_ini'] ?></div>Keluar Hari Ini</div>
  <div class="kartu"><div class="angka"><?= rupiah($ringkas['pendapatan']) ?></div>Pendapatan Hari Ini</div>
</div>

<div class="grid2">
  <div class="panel">
    <h3>KASIR MASUK</h3>
    <p>Catat kendaraan yang masuk dan cetak tiket parkir.</p>
    <a class="btn btn-hijau" href="masuk.php">Buka Kasir Masuk</a>
  </div>
  <div class="panel">
    <h3>KASIR KELUAR</h3>
    <p>Scan / ketik nomor tiket, hitung tarif berdasarkan lama parkir, lalu cetak struk.</p>
    <a class="btn" href="keluar.php">Buka Kasir Keluar</a>
  </div>
</div>

<div class="grid2">
  <div class="panel">
    <h3>KAPASITAS AREA</h3>
    <table>
      <tr><th>Area</th><th class="kanan-teks">Terisi</th><th class="kanan-teks">Sisa</th></tr>
      <?php foreach ($area as $a): ?>
        <tr><td><?= e($a['nama_area']) ?></td><td class="kanan-teks"><?= (int) $a['terisi'] ?> / <?= (int) $a['kapasitas'] ?></td>
            <td class="kanan-teks"><?= (int) $a['kapasitas'] - (int) $a['terisi'] ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$area): ?><tr><td colspan="3">Belum ada area parkir.</td></tr><?php endif; ?>
    </table>
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
    <p>Lama parkir dibulatkan ke atas per jam (minimal 1 jam).</p>
  </div>
</div>

<div class="panel">
  <h3>DAFTAR TRANSAKSI TERAKHIR</h3>
  <div class="tabel-scroll"><table>
    <tr><th>No. Tiket</th><th>No. Polisi</th><th>Jenis</th><th>Masuk</th><th>Keluar</th><th class="kanan-teks">Bayar</th><th>Status</th><th>Struk</th></tr>
    <?php foreach ($terakhir as $t): ?>
      <tr>
        <td><?= (int) $t['id_parkir'] ?></td><td><?= e($t['plat_nomor']) ?></td><td><?= e($t['jenis_kendaraan']) ?></td>
        <td><?= e($t['waktu_masuk']) ?></td><td><?= e($t['waktu_keluar'] ?? '-') ?></td>
        <td class="kanan-teks"><?= $t['biaya_total'] !== null ? rupiah($t['biaya_total']) : '-' ?></td>
        <td><?= e($t['status']) ?></td>
        <td><a class="btn btn-kecil" target="_blank" href="struk.php?id=<?= (int) $t['id_parkir'] ?>">Cetak</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$terakhir): ?><tr><td colspan="8">Belum ada transaksi.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>