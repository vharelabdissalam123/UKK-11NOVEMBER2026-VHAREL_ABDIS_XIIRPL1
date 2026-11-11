<?php
// Logika transaksi parkir: tiket masuk, hitung tarif, kendaraan keluar.
// Dipakai oleh admin (tarif.php, kendaraan.php) dan petugas/kasir.

if (!defined('JENIS_KENDARAAN')) {
    define('JENIS_KENDARAAN', ['motor', 'mobil', 'lainnya']);
}

// Rapikan plat: huruf besar, spasi ganda jadi satu
function bersihkan_plat(string $plat): string
{
    return strtoupper(trim(preg_replace('/\s+/', ' ', $plat)));
}

// Hitung durasi (jam, dibulatkan ke atas, minimal 1 jam) dan biaya
function hitung_biaya(string $waktu_masuk, string $waktu_keluar, int $tarif_per_jam): array
{
    $detik = max(0, strtotime($waktu_keluar) - strtotime($waktu_masuk));
    $jam   = max(1, (int) ceil($detik / 3600));
    return ['jam' => $jam, 'menit' => intdiv($detik, 60), 'biaya' => $jam * $tarif_per_jam];
}

// Cari tiket yang masih aktif (status 'masuk'): kode bisa No. Transaksi atau No. Polisi
function cari_tiket_aktif(PDO $pdo, string $kode, bool $kunci = false): ?array
{
    $kode = trim($kode);
    if ($kode === '') {
        return null;
    }
    $sql = "SELECT x.id_parkir, x.id_area, x.id_tarif, x.waktu_masuk, k.plat_nomor, k.jenis_kendaraan,
                   a.nama_area, tr.tarif_per_jam, NOW() AS sekarang
            FROM tb_transaksi x
            JOIN tb_kendaraan k   ON k.id_kendaraan = x.id_kendaraan
            JOIN tb_area_parkir a ON a.id_area = x.id_area
            JOIN tb_tarif tr      ON tr.id_tarif = x.id_tarif
            WHERE x.status = 'masuk' AND %s
            ORDER BY x.id_parkir DESC LIMIT 1" . ($kunci ? ' FOR UPDATE' : '');

    if (ctype_digit($kode)) {
        $st = $pdo->prepare(sprintf($sql, 'x.id_parkir = ?'));
        $st->execute([(int) $kode]);
        $row = $st->fetch();
        if ($row) {
            return $row;
        }
    }
    $st = $pdo->prepare(sprintf($sql, 'k.plat_nomor = ?'));
    $st->execute([bersihkan_plat($kode)]);
    return $st->fetch() ?: null;
}

// Kendaraan masuk -> buat tiket. Return [ok, pesan, id_parkir]
function proses_masuk(PDO $pdo, string $plat, string $jenis, int $id_area, int $id_user): array
{
    $plat = bersihkan_plat($plat);
    if ($plat === '' || mb_strlen($plat) > 15) {
        return [false, 'No. polisi wajib diisi (maksimal 15 karakter).', 0];
    }
    if (!in_array($jenis, JENIS_KENDARAAN, true)) {
        return [false, 'Jenis kendaraan tidak valid.', 0];
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT id_tarif FROM tb_tarif WHERE jenis_kendaraan = ? LIMIT 1');
        $st->execute([$jenis]);
        $id_tarif = (int) $st->fetchColumn();
        if ($id_tarif === 0) {
            $pdo->rollBack();
            return [false, 'Tarif untuk jenis ' . $jenis . ' belum diatur oleh admin.', 0];
        }

        // Kendaraan lama dipakai ulang, kendaraan baru didaftarkan otomatis
        $st = $pdo->prepare('SELECT id_kendaraan FROM tb_kendaraan WHERE plat_nomor = ? LIMIT 1 FOR UPDATE');
        $st->execute([$plat]);
        $id_kendaraan = (int) $st->fetchColumn();

        if ($id_kendaraan > 0) {
            $st = $pdo->prepare("SELECT 1 FROM tb_transaksi WHERE id_kendaraan = ? AND status = 'masuk' LIMIT 1");
            $st->execute([$id_kendaraan]);
            if ($st->fetchColumn()) {
                $pdo->rollBack();
                return [false, 'Kendaraan ' . $plat . ' masih tercatat parkir (belum keluar).', 0];
            }
            $pdo->prepare('UPDATE tb_kendaraan SET jenis_kendaraan = ? WHERE id_kendaraan = ?')
                ->execute([$jenis, $id_kendaraan]);
        } else {
            $pdo->prepare('INSERT INTO tb_kendaraan (plat_nomor, jenis_kendaraan, warna, pemilik, id_user) VALUES (?, ?, ?, ?, ?)')
                ->execute([$plat, $jenis, '', '', $id_user]);
            $id_kendaraan = (int) $pdo->lastInsertId();
        }

        // Ambil slot hanya jika area belum penuh (atomik)
        $st = $pdo->prepare('UPDATE tb_area_parkir SET terisi = terisi + 1 WHERE id_area = ? AND terisi < kapasitas');
        $st->execute([$id_area]);
        if ($st->rowCount() === 0) {
            $pdo->rollBack();
            return [false, 'Area parkir penuh atau tidak ditemukan.', 0];
        }

        $pdo->prepare(
            "INSERT INTO tb_transaksi (id_kendaraan, id_area, id_tarif, id_user, waktu_masuk, status)
             VALUES (?, ?, ?, ?, NOW(), 'masuk')"
        )->execute([$id_kendaraan, $id_area, $id_tarif, $id_user]);
        $id_parkir = (int) $pdo->lastInsertId();

        catat_log($pdo, $id_user, 'Kendaraan masuk ' . $plat . ' (tiket #' . $id_parkir . ')');
        $pdo->commit();
        return [true, 'Tiket #' . $id_parkir . ' dibuat untuk ' . $plat . '.', $id_parkir];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return [false, 'Gagal menyimpan transaksi masuk.', 0];
    }
}

// Kendaraan keluar -> hitung biaya dari waktu masuk di tiket. Return [ok, pesan, id_parkir]
function proses_keluar(PDO $pdo, string $kode, int $id_user): array
{
    try {
        $pdo->beginTransaction();
        $t = cari_tiket_aktif($pdo, $kode, true);
        if (!$t) {
            $pdo->rollBack();
            return [false, 'Tiket aktif tidak ditemukan. Periksa No. Transaksi / No. Polisi.', 0];
        }

        $h = hitung_biaya($t['waktu_masuk'], $t['sekarang'], (int) $t['tarif_per_jam']);

        $st = $pdo->prepare(
            "UPDATE tb_transaksi SET waktu_keluar = ?, durasi_jam = ?, biaya_total = ?, status = 'keluar'
             WHERE id_parkir = ? AND status = 'masuk'"
        );
        $st->execute([$t['sekarang'], $h['jam'], $h['biaya'], $t['id_parkir']]);
        if ($st->rowCount() === 0) {
            $pdo->rollBack();
            return [false, 'Tiket sudah diproses oleh kasir lain.', 0];
        }

        $pdo->prepare('UPDATE tb_area_parkir SET terisi = GREATEST(terisi - 1, 0) WHERE id_area = ?')
            ->execute([$t['id_area']]);

        catat_log($pdo, $id_user, 'Kendaraan keluar ' . $t['plat_nomor'] . ' (tiket #' . $t['id_parkir'] . ', ' . rupiah($h['biaya']) . ')');
        $pdo->commit();
        return [true, 'Pembayaran ' . rupiah($h['biaya']) . ' diterima.', (int) $t['id_parkir']];
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($ex->getMessage());
        return [false, 'Gagal memproses kendaraan keluar.', 0];
    }
}