<?php
require_once __DIR__ . '/config/fungsi.php';
if (!empty($_SESSION['id_user'])) {
    redirect(halaman_utama($_SESSION['role']));
}
redirect(BASE_URL . '/auth/login.php');