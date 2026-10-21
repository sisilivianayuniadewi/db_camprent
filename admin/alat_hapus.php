<?php
session_start();
require_once "../koneksi.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_status'] != 1) {
    header("Location: ../index.php");
    exit;
}

if (!isset($_POST['hapus'])) {
    header("Location: alat.php");
    exit;
}

$alat_id = (int)($_POST['alat_id'] ?? 0);

if ($alat_id <= 0) {
    header("Location: alat.php?pesan=gagal");
    exit;
}

// Cek apakah alat masih memiliki riwayat peminjaman
$cek = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_detail_peminjaman
    WHERE alat_id = $alat_id
");

if (!$cek) {
    die("Gagal memeriksa data: " . mysqli_error($koneksi));
}

$data = mysqli_fetch_assoc($cek);

if ((int)$data['total'] > 0) {
    echo "<script>
        alert('Alat tidak bisa dihapus karena masih memiliki riwayat peminjaman.');
        window.location='alat.php';
    </script>";
    exit;
}

// Hapus data dari t_alat
$hapus = mysqli_query($koneksi, "
    DELETE FROM t_alat
    WHERE alat_id = $alat_id
");

if ($hapus && mysqli_affected_rows($koneksi) > 0) {
    header("Location: alat.php?pesan=hapus");
    exit;
}

if (!$hapus) {
    die("Gagal menghapus alat: " . mysqli_error($koneksi));
}

header("Location: alat.php?pesan=gagal");
exit;
