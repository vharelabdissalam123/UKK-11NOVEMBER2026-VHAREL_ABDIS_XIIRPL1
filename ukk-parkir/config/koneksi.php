<?php
// Koneksi database memakai PDO + prepared statement (aman dari SQL injection)
date_default_timezone_set('Asia/Jakarta');

$host = 'localhost';
$db   = 'parkir';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+07:00'",
    ]);
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}