<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';
cek_role(['admin']);

$per    = 25;
$hal    = ambil_halaman();
$offset = ($hal - 1) * $per;

$total = (int) $pdo->query('SELECT COUNT(*) FROM tb_log_aktivitas')->fetchColumn();

$st = $pdo->prepare(
    'SELECT l.id_log, l.waktu_aktivitas, u.username, u.role, l.aktivitas
     FROM tb_log_aktivitas l JOIN tb_user u ON u.id_user = l.id_user
     ORDER BY l.id_log DESC LIMIT ? OFFSET ?'
);
$st->bindValue(1, $per, PDO::PARAM_INT);
$st->bindValue(2, $offset, PDO::PARAM_INT);
$st->execute();
$daftar = $st->fetchAll();

$judul = 'Log Aktivitas';
require __DIR__ . '/../includes/header.php';
?>
<div class="panel">
  <h3>LOG AKTIVITAS (<?= $total ?> catatan)</h3>
  <div class="tabel-scroll"><table>
    <tr><th>ID</th><th>Waktu</th><th>User</th><th>Role</th><th>Aktivitas</th></tr>
    <?php foreach ($daftar as $b): ?>
      <tr>
        <td><?= (int) $b['id_log'] ?></td><td><?= e($b['waktu_aktivitas']) ?></td>
        <td><?= e($b['username']) ?></td><td><?= e($b['role']) ?></td><td><?= e($b['aktivitas']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$daftar): ?><tr><td colspan="5">Belum ada log.</td></tr><?php endif; ?>
  </table></div>
  <?= paging_html($total, $per, $hal) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>