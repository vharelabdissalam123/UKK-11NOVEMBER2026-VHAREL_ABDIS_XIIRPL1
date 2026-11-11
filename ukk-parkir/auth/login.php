<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';

if (!empty($_SESSION['id_user'])) {
    redirect(halaman_utama($_SESSION['role']));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $st = $pdo->prepare(
            'SELECT id_user, nama_lengkap, username, role FROM tb_user
             WHERE username = ? AND password = ? AND status_aktif = 1 LIMIT 1'
        );
        $st->execute([$username, md5($password)]);
        $user = $st->fetch();

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['id_user']      = (int) $user['id_user'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['username']     = $user['username'];
            $_SESSION['role']         = $user['role'];
            catat_log($pdo, (int) $user['id_user'], 'Login');
            redirect(halaman_utama($user['role']));
        }
        $error = 'Username atau password salah, atau akun tidak aktif.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Aplikasi Parkir</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/style.css">
</head>
<body>
<div class="topbar"><div>APLIKASI POS PARKIR<small>Silakan login untuk melanjutkan</small></div></div>
<div class="banner">APLIKASI SOFTWARE PARKIR</div>
<div class="login-box">
  <div class="panel">
    <h3>LOGIN</h3>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="baris"><label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" autofocus required></div>
      <div class="baris"><label for="password">Password</label>
        <input type="password" id="password" name="password" required></div>
      <button class="btn" type="submit">Login</button>
    </form>
  </div>
</div>
</body>
</html>