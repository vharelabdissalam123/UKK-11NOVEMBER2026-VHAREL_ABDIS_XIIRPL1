<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
cek_role(['admin']);

// Satu query untuk semua angka ringkasan
$ringkas = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM tb_user) AS jml_user,
            (SELECT COUNT(*) FROM tb_kendaraan) AS jml_kendaraan,
            (SELECT COUNT(*) FROM tb_area_parkir) AS jml_area,
            (SELECT COUNT(*) FROM tb_transaksi WHERE waktu_masuk >= CURDATE()) AS trx_hari_ini,
            (SELECT COUNT(*) FROM tb_transaksi WHERE status = 'masuk') AS sedang_parkir"
)->fetch();

$log = $pdo->query(
    'SELECT l.waktu_aktivitas, u.username, l.aktivitas
     FROM tb_log_aktivitas l JOIN tb_user u ON u.id_user = l.id_user
     ORDER BY l.id_log DESC LIMIT 8'
)->fetchAll();

$kartu = [
    'User' => $ringkas['jml_user'], 'Kendaraan' => $ringkas['jml_kendaraan'],
    'Area Parkir' => $ringkas['jml_area'], 'Transaksi Hari Ini' => $ringkas['trx_hari_ini'],
    'Sedang Parkir' => $ringkas['sedang_parkir'],
];

$judul = 'Dashboard Admin';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid3">
  <?php foreach ($kartu as $nama => $nilai): ?>
    <div class="kartu"><div class="angka"><?= (int) $nilai ?></div><?= e($nama) ?></div>
  <?php endforeach; ?>
</div>
<div class="panel">
  <h3>AKTIVITAS TERAKHIR</h3>
  <div class="tabel-scroll"><table>
    <tr><th>Waktu</th><th>User</th><th>Aktivitas</th></tr>
    <?php foreach ($log as $b): ?>
      <tr><td><?= e($b['waktu_aktivitas']) ?></td><td><?= e($b['username']) ?></td><td><?= e($b['aktivitas']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$log): ?><tr><td colspan="3">Belum ada aktivitas.</td></tr><?php endif; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>