<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
cek_role(['petugas']);

$id = (int) ($_GET['id'] ?? 0);
if ($id === 0) {
    // Tanpa id: ambil transaksi terakhir milik petugas ini
    $st = $pdo->prepare('SELECT MAX(id_parkir) FROM tb_transaksi WHERE id_user = ?');
    $st->execute([(int) $_SESSION['id_user']]);
    $id = (int) $st->fetchColumn();
}

$st = $pdo->prepare(
    'SELECT x.id_parkir, x.waktu_masuk, x.waktu_keluar, x.durasi_jam, x.biaya_total, x.status,
            k.plat_nomor, k.jenis_kendaraan, a.nama_area, tr.tarif_per_jam, u.nama_lengkap
     FROM tb_transaksi x
     JOIN tb_kendaraan k ON k.id_kendaraan = x.id_kendaraan
     JOIN tb_area_parkir a ON a.id_area = x.id_area
     JOIN tb_tarif tr ON tr.id_tarif = x.id_tarif
     JOIN tb_user u ON u.id_user = x.id_user
     WHERE x.id_parkir = ? LIMIT 1'
);
$st->execute([$id]);
$d = $st->fetch();

if ($d) {
    catat_log($pdo, (int) $_SESSION['id_user'], 'Cetak struk #' . $d['id_parkir']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Struk Parkir</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<?php if (!$d): ?>
  <div class="container"><div class="alert alert-error">Transaksi tidak ditemukan.</div>
  <a class="btn" href="index.php">Kembali</a></div>
<?php else: ?>
  <div class="struk">
    <h4>STRUK PARKIR</h4>
    <div class="tengah">Aplikasi Software Parkir</div>
    <hr>
    <div class="row"><span>No. Trx</span><span>#<?= (int) $d['id_parkir'] ?></span></div>
    <div class="row"><span>No. Polisi</span><span><?= e($d['plat_nomor']) ?></span></div>
    <div class="row"><span>Jenis</span><span><?= e(ucfirst($d['jenis_kendaraan'])) ?></span></div>
    <div class="row"><span>Area</span><span><?= e($d['nama_area']) ?></span></div>
    <hr>
    <div class="row"><span>Masuk</span><span><?= e($d['waktu_masuk']) ?></span></div>
    <?php if ($d['status'] === 'keluar'): ?>
      <div class="row"><span>Keluar</span><span><?= e($d['waktu_keluar']) ?></span></div>
      <div class="row"><span>Durasi</span><span><?= (int) $d['durasi_jam'] ?> jam</span></div>
      <div class="row"><span>Tarif/jam</span><span><?= rupiah($d['tarif_per_jam']) ?></span></div>
      <hr>
      <div class="row total"><span>TOTAL</span><span><?= rupiah($d['biaya_total']) ?></span></div>
    <?php else: ?>
      <hr>
      <div class="tengah">Karcis masuk - simpan struk ini</div>
    <?php endif; ?>
    <hr>
    <div class="row"><span>Petugas</span><span><?= e($d['nama_lengkap']) ?></span></div>
    <div class="tengah" style="margin-top:8px">Terima kasih</div>
  </div>
  <div class="tengah no-print">
    <button class="btn" onclick="window.print()">Cetak</button>
    <a class="btn btn-merah" href="index.php">Kembali</a>
  </div>
<?php endif; ?>
</body>
</html>