<?php
// Wajib: panggil cek_role() dan set $judul sebelum include file ini
$menu_role = [
    'admin' => [
        ['Dashboard', '/admin/index.php'], ['User', '/admin/user.php'],
        ['Tarif', '/admin/tarif.php'], ['Area Parkir', '/admin/area.php'],
        ['Kendaraan', '/admin/kendaraan.php'], ['Log Aktivitas', '/admin/log.php'],
    ],
    'petugas' => [
        ['Dashboard Kasir', '/petugas/index.php'], ['Kendaraan Masuk', '/petugas/masuk.php'],
        ['Kendaraan Keluar', '/petugas/keluar.php'],
    ],
    'owner'   => [['Rekap Transaksi', '/owner/index.php']],
];
$menu = $menu_role[$_SESSION['role']] ?? [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul ?? 'Aplikasi Parkir') ?> - Aplikasi Parkir</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<div class="topbar no-print">
  <div>
    APLIKASI POS PARKIR
    <small>Operator : <?= e($_SESSION['nama_lengkap']) ?></small>
    <small>Level : <?= e(ucfirst($_SESSION['role'])) ?></small>
  </div>
  <div class="kanan">
    Tgl: <?= date('d/m/Y') ?>
    <div class="jam" id="jam"><?= date('H:i:s') ?></div>
  </div>
</div>
<div class="banner no-print">APLIKASI SOFTWARE PARKIR</div>
<div class="menu no-print">
  <?php foreach ($menu as [$nama, $url]): ?>
    <a href="<?= BASE_URL . $url ?>"><?= e($nama) ?></a>
  <?php endforeach; ?>
</div>
<div class="container">
<h2 class="judul-halaman"><?= e($judul ?? '') ?></h2>
<?= tampil_flash() ?>