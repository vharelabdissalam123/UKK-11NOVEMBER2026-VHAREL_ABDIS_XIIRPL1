<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
cek_role(['admin']);

const ROLE_USER = ['admin', 'petugas', 'owner'];
$self = (int) $_SESSION['id_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'simpan') {
            $id       = (int) ($_POST['id_user'] ?? 0);
            $nama     = trim($_POST['nama_lengkap'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role     = $_POST['role'] ?? '';
            $aktif    = isset($_POST['status_aktif']) ? 1 : 0;
            if ($id === $self) {
                $aktif = 1; // admin tidak boleh menonaktifkan dirinya sendiri
            }

            if ($nama === '' || $username === '' || !in_array($role, ROLE_USER, true)
                || ($id === 0 && $password === '')) {
                set_flash('error', 'Nama, username, role, dan password (untuk user baru) wajib diisi.');
                redirect($id ? '?edit=' . $id : 'user.php');
            }

            if ($id > 0) {
                if ($password !== '') {
                    $pdo->prepare('UPDATE tb_user SET nama_lengkap=?, username=?, password=?, role=?, status_aktif=? WHERE id_user=?')
                        ->execute([$nama, $username, md5($password), $role, $aktif, $id]);
                } else {
                    $pdo->prepare('UPDATE tb_user SET nama_lengkap=?, username=?, role=?, status_aktif=? WHERE id_user=?')
                        ->execute([$nama, $username, $role, $aktif, $id]);
                }
                catat_log($pdo, $self, 'Ubah user ' . $username);
            } else {
                $pdo->prepare('INSERT INTO tb_user (nama_lengkap, username, password, role, status_aktif) VALUES (?,?,?,?,?)')
                    ->execute([$nama, $username, md5($password), $role, $aktif]);
                catat_log($pdo, $self, 'Tambah user ' . $username);
            }
            set_flash('ok', 'Data user disimpan.');
        } elseif ($aksi === 'hapus') {
            $id = (int) ($_POST['id_user'] ?? 0);
            if ($id === $self) {
                set_flash('error', 'Anda tidak bisa menghapus akun sendiri.');
            } else {
                $pdo->prepare('DELETE FROM tb_user WHERE id_user = ?')->execute([$id]);
                catat_log($pdo, $self, 'Hapus user #' . $id);
                set_flash('ok', 'User dihapus.');
            }
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            set_flash('error', 'Gagal: username sudah dipakai, atau user sudah punya transaksi (nonaktifkan saja).');
        } else {
            error_log($ex->getMessage());
            set_flash('error', 'Terjadi kesalahan saat menyimpan data.');
        }
    }
    redirect('user.php');
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id_user, nama_lengkap, username, role, status_aktif FROM tb_user WHERE id_user = ?');
    $st->execute([(int) $_GET['edit']]);
    $edit = $st->fetch() ?: null;
}

$daftar = $pdo->query(
    'SELECT id_user, nama_lengkap, username, role, status_aktif FROM tb_user ORDER BY id_user LIMIT 100'
)->fetchAll();

$judul = 'Data User';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
  <h3><?= $edit ? 'UBAH USER' : 'TAMBAH USER' ?></h3>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="aksi" value="simpan">
    <input type="hidden" name="id_user" value="<?= (int) ($edit['id_user'] ?? 0) ?>">
    <div class="baris"><label>Nama Lengkap</label><input type="text" name="nama_lengkap" maxlength="50" value="<?= e($edit['nama_lengkap'] ?? '') ?>" required></div>
    <div class="baris"><label>Username</label><input type="text" name="username" maxlength="50" value="<?= e($edit['username'] ?? '') ?>" required></div>
    <div class="baris"><label>Password</label><input type="password" name="password" placeholder="<?= $edit ? 'Kosongkan jika tidak diubah' : '' ?>"></div>
    <div class="baris"><label>Role</label>
      <select name="role">
        <?php foreach (ROLE_USER as $r): ?>
          <option value="<?= $r ?>" <?= ($edit['role'] ?? '') === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="baris"><label>Aktif</label>
      <input type="checkbox" name="status_aktif" value="1" style="flex:none" <?= ($edit['status_aktif'] ?? 1) ? 'checked' : '' ?>></div>
    <button class="btn" type="submit">Simpan</button>
    <?php if ($edit): ?><a class="btn btn-merah" href="user.php">Batal</a><?php endif; ?>
  </form>
</div>

<div class="panel">
  <h3>DAFTAR USER</h3>
  <div class="tabel-scroll"><table>
    <tr><th>ID</th><th>Nama</th><th>Username</th><th>Role</th><th>Status</th><th>Aksi</th></tr>
    <?php foreach ($daftar as $u): ?>
      <tr>
        <td><?= (int) $u['id_user'] ?></td><td><?= e($u['nama_lengkap']) ?></td>
        <td><?= e($u['username']) ?></td><td><?= e($u['role']) ?></td>
        <td><?= $u['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></td>
        <td>
          <a class="btn btn-kecil" href="?edit=<?= (int) $u['id_user'] ?>">Ubah</a>
          <?php if ((int) $u['id_user'] !== $self): ?>
          <form class="inline" method="post" onsubmit="return confirm('Hapus user ini?')">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus">
            <input type="hidden" name="id_user" value="<?= (int) $u['id_user'] ?>">
            <button class="btn btn-merah btn-kecil" type="submit">Hapus</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>