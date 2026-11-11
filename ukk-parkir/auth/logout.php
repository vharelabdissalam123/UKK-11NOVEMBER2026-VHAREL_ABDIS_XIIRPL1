<?php
require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/fungsi.php';

if (!empty($_SESSION['id_user'])) {
    catat_log($pdo, (int) $_SESSION['id_user'], 'Logout');
}
$_SESSION = [];
session_destroy();
redirect(BASE_URL . '/auth/login.php');