<?php
// Fungsi bantu yang dipakai di semua halaman
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const BASE_URL = '/ukk-parkir';

// Escape output agar aman dari XSS
function e(?string $teks): string
{
    return htmlspecialchars($teks ?? '', ENT_QUOTES, 'UTF-8');
}

function rupiah($angka): string
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function cek_login(): void
{
    if (empty($_SESSION['id_user'])) {
        redirect(BASE_URL . '/auth/login.php');
    }
}

// Pastikan role user termasuk dalam daftar role yang diizinkan
function cek_role(array $role_diizinkan): void
{
    cek_login();
    if (!in_array($_SESSION['role'], $role_diizinkan, true)) {
        http_response_code(403);
        die('Akses ditolak.');
    }
}

function halaman_utama(string $role): string
{
    $tujuan = [
        'admin'   => '/admin/index.php',
        'petugas' => '/petugas/index.php',
        'owner'   => '/owner/index.php',
    ];
    return BASE_URL . ($tujuan[$role] ?? '/auth/login.php');
}

// Catat aktivitas user ke tb_log_aktivitas
function catat_log(PDO $pdo, int $id_user, string $aktivitas): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO tb_log_aktivitas (id_user, aktivitas, waktu_aktivitas) VALUES (?, ?, NOW())'
    );
    $stmt->execute([$id_user, mb_substr($aktivitas, 0, 100)]);
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function cek_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        die('Token keamanan tidak valid. Muat ulang halaman.');
    }
}

// ---------- Pesan flash ----------
function set_flash(string $tipe, string $pesan): void
{
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan];
}

function tampil_flash(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $kelas = $f['tipe'] === 'ok' ? 'alert-ok' : 'alert-error';
    return '<div class="alert ' . $kelas . '">' . e($f['pesan']) . '</div>';
}

// ---------- Paging (selalu pakai LIMIT untuk data besar) ----------
function ambil_halaman(): int
{
    return max(1, (int) ($_GET['hal'] ?? 1));
}

function paging_html(int $total, int $per_halaman, int $hal, array $query = []): string
{
    $jumlah = (int) ceil($total / $per_halaman);
    if ($jumlah <= 1) {
        return '';
    }
    $out = '<div class="paging">';
    for ($i = max(1, $hal - 3); $i <= min($jumlah, $hal + 3); $i++) {
        $q     = http_build_query(array_merge($query, ['hal' => $i]));
        $kelas = $i === $hal ? ' class="aktif"' : '';
        $out  .= '<a' . $kelas . ' href="?' . e($q) . '">' . $i . '</a>';
    }
    return $out . '</div>';
}