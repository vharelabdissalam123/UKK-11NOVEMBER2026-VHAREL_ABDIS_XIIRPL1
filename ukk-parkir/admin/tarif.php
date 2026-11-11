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
            $id    = (int) ($_POST['id_tarif'] ?? 0);
            $jenis = $_POST['jenis_kendaraan'] ?? '';
            $tarif = (int) ($_POST['tarif_per_jam'] ?? -1);

            if (!in_array($jenis, JENIS_KENDARAAN, true) || $tarif < 0) {
                set_flash('error', 'Jenis kendaraan dan tarif per jam harus valid.');
                redirect($id ? '?edit=' . $id : 'tarif.php');
            }
            // Satu jenis kendaraan hanya boleh punya satu tarif
            $st = $pdo->prepare('SELECT 1 FROM tb_tarif WHERE jenis_kendaraan = ? AND id_tarif <> ? LIMIT 1');
            $st->execute([$jenis, $id]);
            if ($st->fetchColumn()) {
                set_flash('error', 'Tarif untuk jenis ' . $jenis . ' sudah ada. Ubah tarif yang sudah ada.');
                redirect('tarif.php');
            }

            if ($id > 0) {
                $pdo->prepare('UPDATE tb_tarif SET jenis_kendaraan=?, tarif_per_jam=? WHERE id_tarif=?')->execute([$jenis, $tarif, $id]);
                catat_log($pdo, $self, 'Ubah tarif ' . $jenis);
            } else {
                $pdo->prepare('INSERT INTO tb_tarif (jenis_kendaraan, tarif_per_jam) VALUES (?, ?)')->execute([$jenis, $tarif]);
                catat_log($pdo, $self, 'Tambah tarif ' . $jenis);
            }
            set_flash('ok', 'Tarif disimpan.');
        } elseif ($aksi === 'hapus') {
            $id = (int) ($_POST['id_tarif'] ?? 0);
            $pdo->prepare('DELETE FROM tb_tarif WHERE id_tarif = ?')->execute([$id]);
            catat_log($pdo, $self, 'Hapus tarif #' . $id);
            set_flash('ok', 'Tarif dihapus.');
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            set_flash('error', 'Tarif tidak bisa dihapus karena sudah dipakai transaksi.');
        } else {
            error_log($ex->getMessage());
            set_flash('error', 'Terjadi kesalahan saat menyimpan data.');
        }
    }
    redirect('tarif.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id_tarif, jenis_kendaraan, tarif_per_jam FROM tb_tarif WHERE id_tarif = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$daftar = $pdo->query('SELECT id_tarif, jenis_kendaraan, tarif_per_jam FROM tb_tarif ORDER BY id_tarif LIMIT 100')->fetchAll();

$judul = 'Tarif Parkir';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
  <h3><?= $edit ? 'UBAH TARIF' : 'TAMBAH TARIF' ?></h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_tarif" value="<?= (int) ($edit['id_tarif'] ?? 0) ?>">
    <div class="baris"><label>Jenis Kendaraan</label>
      <select name="jenis_kendaraan">
        <?php foreach (JENIS_KENDARAAN as $j): ?>
          <option value="<?= $j ?>" <?= ($edit['jenis_kendaraan'] ?? '') === $j ? 'selected' : '' ?>><?= ucfirst($j) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="baris"><label>Tarif per Jam (Rp)</label>
      <input type="number" name="tarif_per_jam" min="0" value="<?= e((string) ($edit['tarif_per_jam'] ?? '')) ?>" required></div>
    <button class="btn" type="submit">Simpan</button>
    <?php if ($edit): ?><a class="btn btn-merah" href="tarif.php">Batal</a><?php endif; ?>
  </form>
</div>
<div class="panel">
  <h3>DAFTAR TARIF</h3>
  <div class="tabel-scroll"><table>
    <tr><th>ID</th><th>Jenis Kendaraan</th><th class="kanan-teks">Tarif per Jam</th><th>Aksi</th></tr>
    <?php foreach ($daftar as $t): ?>
      <tr>
        <td><?= (int) $t['id_tarif'] ?></td><td><?= e(ucfirst($t['jenis_kendaraan'])) ?></td>
        <td class="kanan-teks"><?= rupiah($t['tarif_per_jam']) ?></td>
        <td>
          <a class="btn btn-kecil" href="?edit=<?= (int) $t['id_tarif'] ?>">Ubah</a>
          <form class="inline" method="post" onsubmit="return confirm('Hapus tarif ini?')">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id_tarif" value="<?= (int) $t['id_tarif'] ?>">
            <button class="btn btn-merah btn-kecil" type="submit">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>