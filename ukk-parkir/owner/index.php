<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
cek_role(['owner']);

// Validasi tanggal Y-m-d, default: awal bulan sampai hari ini
function ambil_tanggal(string $kunci, string $default): string
{
    $nilai = $_GET[$kunci] ?? '';
    $d = DateTime::createFromFormat('Y-m-d', $nilai);
    return ($d && $d->format('Y-m-d') === $nilai) ? $nilai : $default;
}

$dari   = ambil_tanggal('dari', date('Y-m-01'));
$sampai = ambil_tanggal('sampai', date('Y-m-d'));
if ($dari > $sampai) {
    [$dari, $sampai] = [$sampai, $dari];
}
$batas_awal  = $dari . ' 00:00:00';
$batas_akhir = date('Y-m-d', strtotime($sampai . ' +1 day')) . ' 00:00:00'; // rentang setengah-terbuka, memakai index

// Ringkasan
$st = $pdo->prepare(
    "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'keluar'), 0) AS selesai,
            COALESCE(SUM(biaya_total), 0) AS pendapatan
     FROM tb_transaksi WHERE waktu_masuk >= ? AND waktu_masuk < ?"
);
$st->execute([$batas_awal, $batas_akhir]);
$ringkas = $st->fetch();

// Per jenis kendaraan
$st = $pdo->prepare(
    'SELECT tr.jenis_kendaraan, COUNT(*) AS jumlah, COALESCE(SUM(x.biaya_total), 0) AS total
     FROM tb_transaksi x JOIN tb_tarif tr ON tr.id_tarif = x.id_tarif
     WHERE x.waktu_masuk >= ? AND x.waktu_masuk < ?
     GROUP BY tr.jenis_kendaraan'
);
$st->execute([$batas_awal, $batas_akhir]);
$per_jenis = $st->fetchAll();

// Detail dengan paging (LIMIT)
$per    = 25;
$hal    = ambil_halaman();
$offset = ($hal - 1) * $per;
$st = $pdo->prepare(
    'SELECT x.id_parkir, k.plat_nomor, k.jenis_kendaraan, a.nama_area, x.waktu_masuk, x.waktu_keluar,
            x.durasi_jam, x.biaya_total, x.status
     FROM tb_transaksi x
     JOIN tb_kendaraan k ON k.id_kendaraan = x.id_kendaraan
     JOIN tb_area_parkir a ON a.id_area = x.id_area
     WHERE x.waktu_masuk >= ? AND x.waktu_masuk < ?
     ORDER BY x.id_parkir DESC LIMIT ? OFFSET ?'
);
$st->bindValue(1, $batas_awal);
$st->bindValue(2, $batas_akhir);
$st->bindValue(3, $per, PDO::PARAM_INT);
$st->bindValue(4, $offset, PDO::PARAM_INT);
$st->execute();
$detail = $st->fetchAll();

$judul = 'Rekap Transaksi';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel no-print">
  <h3>PILIH PERIODE</h3>
  <form method="get" class="baris">
    <label>Dari</label><input type="date" name="dari" value="<?= e($dari) ?>">
    <label>Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>">
    <button class="btn" type="submit">Tampilkan</button>
    <button class="btn btn-hijau" type="button" onclick="window.print()">Cetak</button>
  </form>
</div>

<p>Periode: <b><?= e(date('d/m/Y', strtotime($dari))) ?></b> s/d <b><?= e(date('d/m/Y', strtotime($sampai))) ?></b></p>

<div class="grid3">
  <div class="kartu"><div class="angka"><?= (int) $ringkas['total'] ?></div>Total Transaksi</div>
  <div class="kartu"><div class="angka"><?= (int) $ringkas['selesai'] ?></div>Sudah Keluar</div>
  <div class="kartu"><div class="angka"><?= rupiah($ringkas['pendapatan']) ?></div>Total Pendapatan</div>
</div>

<div class="panel">
  <h3>REKAP PER JENIS KENDARAAN</h3>
  <table>
    <tr><th>Jenis</th><th class="kanan-teks">Jumlah</th><th class="kanan-teks">Pendapatan</th></tr>
    <?php foreach ($per_jenis as $j): ?>
      <tr><td><?= e(ucfirst($j['jenis_kendaraan'])) ?></td><td class="kanan-teks"><?= (int) $j['jumlah'] ?></td><td class="kanan-teks"><?= rupiah($j['total']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$per_jenis): ?><tr><td colspan="3">Tidak ada transaksi pada periode ini.</td></tr><?php endif; ?>
  </table>
</div>

<div class="panel">
  <h3>DETAIL TRANSAKSI</h3>
  <div class="tabel-scroll"><table>
    <tr><th>No. Trx</th><th>No. Polisi</th><th>Jenis</th><th>Area</th><th>Masuk</th><th>Keluar</th><th>Durasi</th><th class="kanan-teks">Biaya</th><th>Status</th></tr>
    <?php foreach ($detail as $d): ?>
      <tr>
        <td><?= (int) $d['id_parkir'] ?></td><td><?= e($d['plat_nomor']) ?></td><td><?= e($d['jenis_kendaraan']) ?></td>
        <td><?= e($d['nama_area']) ?></td><td><?= e($d['waktu_masuk']) ?></td><td><?= e($d['waktu_keluar'] ?? '-') ?></td>
        <td><?= $d['durasi_jam'] !== null ? (int) $d['durasi_jam'] . ' jam' : '-' ?></td>
        <td class="kanan-teks"><?= $d['biaya_total'] !== null ? rupiah($d['biaya_total']) : '-' ?></td>
        <td><?= e($d['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$detail): ?><tr><td colspan="9">Tidak ada data.</td></tr><?php endif; ?>
  </table></div>
  <?= paging_html((int) $ringkas['total'], $per, $hal, ['dari' => $dari, 'sampai' => $sampai]) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>