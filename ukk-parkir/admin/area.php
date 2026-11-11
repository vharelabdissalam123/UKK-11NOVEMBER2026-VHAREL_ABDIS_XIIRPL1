<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
cek_role(['admin']);
$self = (int) $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'simpan') {
            $id        = (int) ($_POST['id_area'] ?? 0);
            $nama      = trim($_POST['nama_area'] ?? '');
            $kapasitas = (int) ($_POST['kapasitas'] ?? 0);

            if ($nama === '' || $kapasitas < 1) {
                set_flash('error', 'Nama area dan kapasitas (minimal 1) wajib diisi.');
                redirect($id ? '?edit=' . $id : 'area.php');
            }

            if ($id > 0) {
                $st = $pdo->prepare('SELECT terisi FROM tb_area_parkir WHERE id_area = ?');
                $st->execute([$id]);
                $terisi = (int) $st->fetchColumn();
                if ($kapasitas < $terisi) {
                    set_flash('error', 'Kapasitas tidak boleh lebih kecil dari jumlah terisi (' . $terisi . ').');
                    redirect('?edit=' . $id);
                }
                $pdo->prepare('UPDATE tb_area_parkir SET nama_area=?, kapasitas=? WHERE id_area=?')->execute([$nama, $kapasitas, $id]);
                catat_log($pdo, $self, 'Ubah area ' . $nama);
            } else {
                $pdo->prepare('INSERT INTO tb_area_parkir (nama_area, kapasitas, terisi) VALUES (?, ?, 0)')->execute([$nama, $kapasitas]);
                catat_log($pdo, $self, 'Tambah area ' . $nama);
            }
            set_flash('ok', 'Area parkir disimpan.');
        } elseif ($aksi === 'hapus') {
            $id = (int) ($_POST['id_area'] ?? 0);
            $pdo->prepare('DELETE FROM tb_area_parkir WHERE id_area = ?')->execute([$id]);
            catat_log($pdo, $self, 'Hapus area #' . $id);
            set_flash('ok', 'Area parkir dihapus.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            set_flash('error', 'Area tidak bisa dihapus karena sudah dipakai transaksi.');
        } else {
            error_log($ex->getMessage());
            set_flash('error', 'Terjadi kesalahan saat menyimpan data.');
        }
    }
    redirect('area.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id_area, nama_area, kapasitas, terisi FROM tb_area_parkir WHERE id_area = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$daftar = $pdo->query('SELECT id_area, nama_area, kapasitas, terisi FROM tb_area_parkir ORDER BY nama_area LIMIT 100')->fetchAll();

$judul = 'Area Parkir';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
  <h3><?= $edit ? 'UBAH AREA' : 'TAMBAH AREA' ?></h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_area" value="<?= (int) ($edit['id_area'] ?? 0) ?>">
    <div class="baris"><label>Nama Area</label><input type="text" name="nama_area" maxlength="50" value="<?= e($edit['nama_area'] ?? '') ?>" required></div>
    <div class="baris"><label>Kapasitas</label><input type="number" name="kapasitas" min="1" max="99999" value="<?= e((string) ($edit['kapasitas'] ?? '')) ?>" required></div>
    <button class="btn" type="submit">Simpan</button>
    <?php if ($edit): ?><a class="btn btn-merah" href="area.php">Batal</a><?php endif; ?>
  </form>
</div>
<div class="panel">
  <h3>DAFTAR AREA PARKIR</h3>
  <div class="tabel-scroll"><table>
    <tr><th>ID</th><th>Nama Area</th><th>Kapasitas</th><th>Terisi</th><th>Sisa</th><th>Aksi</th></tr>
    <?php foreach ($daftar as $a): ?>
      <tr>
        <td><?= (int) $a['id_area'] ?></td><td><?= e($a['nama_area']) ?></td>
        <td><?= (int) $a['kapasitas'] ?></td><td><?= (int) $a['terisi'] ?></td>
        <td><?= (int) $a['kapasitas'] - (int) $a['terisi'] ?></td>
        <td>
          <a class="btn btn-kecil" href="?edit=<?= (int) $a['id_area'] ?>">Ubah</a>
          <form class="inline" method="post" onsubmit="return confirm('Hapus area ini?')">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id_area" value="<?= (int) $a['id_area'] ?>">
            <button class="btn btn-merah btn-kecil" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>