<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
require_once __DIR__ . '/../config/transaksi.php';
cek_role(['petugas']);
$id_user = (int) $_SESSION['id_user'];

// Konfirmasi bayar -> tutup tiket, lalu cetak struk
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    [$ok, $pesan, $id_parkir] = proses_keluar($pdo, (string) (int) ($_POST['id_parkir'] ?? 0), $id_user);
    if ($ok) {
        set_flash('ok', $pesan);
        redirect('struk.php?id=' . $id_parkir);
    }
    set_flash('error', $pesan);
    redirect('keluar.php');
}

// Cek tiket (hanya menghitung, belum menyimpan apa pun)
$kode   = trim($_GET['kode'] ?? '');
$tiket  = null;
$hitung = null;
if ($kode !== '') {
    $tiket = cari_tiket_aktif($pdo, $kode);
    if ($tiket) {
        $hitung = hitung_biaya($tiket['waktu_masuk'], $tiket['sekarang'], (int) $tiket['tarif_per_jam']);
    } else {
        set_flash('error', 'Tiket aktif untuk "' . mb_substr($kode, 0, 15) . '" tidak ditemukan.');
    }
}

$parkir = $pdo->query(
    "SELECT x.id_parkir, k.plat_nomor, k.jenis_kendaraan, a.nama_area, x.waktu_masuk
     FROM tb_transaksi x
     JOIN tb_kendaraan k ON k.id_kendaraan = x.id_kendaraan
     JOIN tb_area_parkir a ON a.id_area = x.id_area
     WHERE x.status = 'masuk' ORDER BY x.id_parkir DESC LIMIT 50"
)->fetchAll();

$judul = 'Kasir Keluar - Hitung Tarif';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid2">
  <div class="panel">
    <h3>CARI TIKET</h3>
    <form method="get" autocomplete="off">
      <div class="baris"><label>No. Tiket / Polisi</label>
        <input type="text" name="kode" maxlength="15" value="<?= e($kode) ?>" required autofocus></div>
      <button class="btn" type="submit">Hitung Tarif</button>
    </form>
  </div>

  <?php if ($tiket): ?>
  <div class="panel">
    <h3>RINCIAN PEMBAYARAN</h3>
    <table>
      <tr><td>No. Tiket</td><td class="kanan-teks">#<?= (int) $tiket['id_parkir'] ?></td></tr>
      <tr><td>No. Polisi</td><td class="kanan-teks"><?= e($tiket['plat_nomor']) ?></td></tr>
      <tr><td>Jenis</td><td class="kanan-teks"><?= e(ucfirst($tiket['jenis_kendaraan'])) ?></td></tr>
      <tr><td>Area</td><td class="kanan-teks"><?= e($tiket['nama_area']) ?></td></tr>
      <tr><td>Masuk</td><td class="kanan-teks"><?= e($tiket['waktu_masuk']) ?></td></tr>
      <tr><td>Sekarang</td><td class="kanan-teks"><?= e($tiket['sekarang']) ?></td></tr>
      <tr><td>Lama parkir</td><td class="kanan-teks"><?= intdiv($hitung['menit'], 60) ?> jam <?= $hitung['menit'] % 60 ?> menit</td></tr>
      <tr><td>Ditagih</td><td class="kanan-teks"><?= (int) $hitung['jam'] ?> jam x <?= rupiah($tiket['tarif_per_jam']) ?></td></tr>
      <tr><th>TOTAL BAYAR</th><th class="kanan-teks"><?= rupiah($hitung['biaya']) ?></th></tr>
    </table>
    <form method="post" style="margin-top:10px">
      <?= csrf_field() ?>
      <input type="hidden" name="id_parkir" value="<?= (int) $tiket['id_parkir'] ?>">
      <button class="btn btn-hijau" type="submit">Bayar &amp; Cetak Struk</button>
    </form>
    <p>Total final dihitung ulang saat tombol ditekan, berdasarkan jam keluar sebenarnya.</p>
  </div>
  <?php endif; ?>
</div>

<div class="panel">
  <h3>KENDARAAN SEDANG PARKIR (<?= count($parkir) ?>)</h3>
  <div class="tabel-scroll"><table>
    <tr><th>No. Tiket</th><th>No. Polisi</th><th>Jenis</th><th>Area</th><th>Masuk</th><th>Aksi</th></tr>
    <?php foreach ($parkir as $p): ?>
      <tr>
        <td><?= (int) $p['id_parkir'] ?></td><td><?= e($p['plat_nomor']) ?></td><td><?= e($p['jenis_kendaraan']) ?></td>
        <td><?= e($p['nama_area']) ?></td><td><?= e($p['waktu_masuk']) ?></td>
        <td><a class="btn btn-kecil" href="?kode=<?= (int) $p['id_parkir'] ?>">Proses Keluar</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$parkir): ?><tr><td colspan="6">Tidak ada kendaraan yang sedang parkir.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>