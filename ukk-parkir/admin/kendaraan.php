<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
require_once __DIR__ . '/../config/transaksi.php';
cek_role(['admin']);
$self = (int) $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'simpan') {
            $id      = (int) ($_POST['id_kendaraan'] ?? 0);
            $plat    = bersihkan_plat($_POST['plat_nomor'] ?? '');
            $jenis   = $_POST['jenis_kendaraan'] ?? '';
            $warna   = trim($_POST['warna'] ?? '');
            $pemilik = trim($_POST['pemilik'] ?? '');

            if ($plat === '' || mb_strlen($plat) > 15 || !in_array($jenis, JENIS_KENDARAAN, true)) {
                set_flash('error', 'No. polisi (maks 15 karakter) dan jenis kendaraan wajib diisi.');
                redirect($id ? '?edit=' . $id : 'kendaraan.php');
            }
            $st = $pdo->prepare('SELECT 1 FROM tb_kendaraan WHERE plat_nomor = ? AND id_kendaraan <> ? LIMIT 1');
            $st->execute([$plat, $id]);
            if ($st->fetchColumn()) {
                set_flash('error', 'No. polisi ' . $plat . ' sudah terdaftar.');
                redirect('kendaraan.php');
            }

            if ($id > 0) {
                $pdo->prepare('UPDATE tb_kendaraan SET plat_nomor=?, jenis_kendaraan=?, warna=?, pemilik=? WHERE id_kendaraan=?')
                    ->execute([$plat, $jenis, $warna, $pemilik, $id]);
                catat_log($pdo, $self, 'Ubah kendaraan ' . $plat);
            } else {
                $pdo->prepare('INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?,?,?,?,?)')
                    ->execute([$plat, $jenis, $warna, $pemilik, $self]);
                catat_log($pdo, $self, 'Tambah kendaraan ' . $plat);
            }
            set_flash('ok', 'Data kendaraan disimpan.');
        } elseif ($aksi === 'hapus') {
            $id = (int) ($_POST['id_kendaraan'] ?? 0);
            $pdo->prepare('DELETE FROM tb_kendaraan WHERE id_kendaraan = ?')->execute([$id]);
            catat_log($pdo, $self, 'Hapus kendaraan #' . $id);
            set_flash('ok', 'Kendaraan dihapus.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            set_flash('error', 'Kendaraan tidak bisa dihapus karena sudah punya riwayat transaksi.');
        } else {
            error_log($ex->getMessage());
            set_flash('error', 'Terjadi kesalahan saat menyimpan data.');
        }
    }
    redirect('kendaraan.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id_kendaraan, plat_nomor, jenis_kendaraan, warna, pemilik FROM tb_kendaraan WHERE id_kendaraan = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

// Pencarian awalan plat (memakai index) + paging dengan LIMIT
$cari   = bersihkan_plat($_GET['cari'] ?? '');
$per    = 15;
$hal    = ambil_halaman();
$offset = ($hal - 1) * $per;
$like   = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $cari) . '%';

$total  = 0;
$daftar = [];
try {
    $st = $pdo->prepare('SELECT COUNT(*) FROM tb_kendaraan WHERE plat_nomor LIKE ?');
    $st->execute([$like]);
    $total = (int) $st->fetchColumn();

    $st = $pdo->prepare(
        'SELECT id_kendaraan, plat_nomor, jenis_kendaraan, warna, pemilik FROM tb_kendaraan
         WHERE plat_nomor LIKE ? ORDER BY id_kendaraan DESC LIMIT ? OFFSET ?'
    );
    $st->bindValue(1, $like);
    $st->bindValue(2, $per, PDO::PARAM_INT);
    $st->bindValue(3, $offset, PDO::PARAM_INT);
    $st->execute();
    $daftar = $st->fetchAll();
} catch (PDOException $ex) {
    error_log($ex->getMessage());
    set_flash('error', 'Data kendaraan gagal dimuat: struktur tabel tb_kendaraan tidak sesuai. Import database/parkir.sql.');
}

$judul = 'Data Kendaraan';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
  <h3><?= $edit ? 'UBAH KENDARAAN' : 'TAMBAH KENDARAAN' ?></h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_kendaraan" value="<?= (int) ($edit['id_kendaraan'] ?? 0) ?>">
    <div class="baris"><label>No. Polisi</label><input type="text" name="plat_nomor" maxlength="15" value="<?= e($edit['plat_nomor'] ?? '') ?>" required></div>
    <div class="baris"><label>Jenis</label>
      <select name="jenis_kendaraan">
        <?php foreach (JENIS_KENDARAAN as $j): ?>
          <option value="<?= $j ?>" <?= ($edit['jenis_kendaraan'] ?? '') === $j ? 'selected' : '' ?>><?= ucfirst($j) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="baris"><label>Warna</label><input type="text" name="warna" maxlength="20" value="<?= e($edit['warna'] ?? '') ?>"></div>
    <div class="baris"><label>Pemilik</label><input type="text" name="pemilik" maxlength="100" value="<?= e($edit['pemilik'] ?? '') ?>"></div>
    <button class="btn" type="submit">Simpan</button>
    <?php if ($edit): ?><a class="btn btn-merah" href="kendaraan.php">Batal</a><?php endif; ?>
  </form>
</div>

<div class="panel">
  <h3>DAFTAR KENDARAAN</h3>
  <form method="get" class="baris">
    <input type="text" name="cari" placeholder="Cari No. Polisi (awalan)" value="<?= e($cari) ?>">
    <button class="btn" type="submit">Cari</button>
  </form>
  <div class="tabel-scroll"><table>
    <tr><th>ID</th><th>No. Polisi</th><th>Jenis</th><th>Warna</th><th>Pemilik</th><th>Aksi</th></tr>
    <?php foreach ($daftar as $k): ?>
      <tr>
        <td><?= (int) $k['id_kendaraan'] ?></td><td><?= e($k['plat_nomor']) ?></td>
        <td><?= e($k['jenis_kendaraan']) ?></td><td><?= e($k['warna']) ?></td><td><?= e($k['pemilik']) ?></td>
        <td>
          <a class="btn btn-kecil" href="?edit=<?= (int) $k['id_kendaraan'] ?>">Ubah</a>
          <form class="inline" method="post" onsubmit="return confirm('Hapus kendaraan ini?')">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id_kendaraan" value="<?= (int) $k['id_kendaraan'] ?>">
            <button class="btn btn-merah btn-kecil" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$daftar): ?><tr><td colspan="6">Data tidak ditemukan.</td></tr><?php endif; ?>
  </table></div>
  <?= paging_html($total, $per, $hal, ['cari' => $cari]) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>