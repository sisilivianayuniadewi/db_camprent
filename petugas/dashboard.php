<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user_id']) || (int)($_SESSION['user_status'] ?? 0) !== 2) {
    header("Location: ../index.php");
    exit();
}

// Total pengajuan
$q_total = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_peminjaman
");
$total_pengajuan = $q_total ? (int)mysqli_fetch_assoc($q_total)['total'] : 0;

// Menunggu persetujuan
$q_menunggu = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_peminjaman
    WHERE status = 'Menunggu Persetujuan'
");
$total_menunggu = $q_menunggu ? (int)mysqli_fetch_assoc($q_menunggu)['total'] : 0;

// Sedang dipinjam
$q_dipinjam = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_peminjaman
    WHERE status = 'Dipinjam'
");
$total_dipinjam = $q_dipinjam ? (int)mysqli_fetch_assoc($q_dipinjam)['total'] : 0;

// Selesai
$q_selesai = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_peminjaman
    WHERE status = 'Selesai'
");
$total_selesai = $q_selesai ? (int)mysqli_fetch_assoc($q_selesai)['total'] : 0;

// Total denda keterlambatan dari pengembalian yang tercatat
$q_denda = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(total_denda), 0) AS total
    FROM t_pengembalian
");
$total_denda = $q_denda
    ? (float)mysqli_fetch_assoc($q_denda)['total']
    : 0;

// Total biaya kerusakan
$q_kerusakan = mysqli_query($koneksi, "
    SELECT COALESCE(SUM(biaya_kerusakan), 0) AS total
    FROM t_detail_peminjaman
");
$total_kerusakan = $q_kerusakan
    ? (float)mysqli_fetch_assoc($q_kerusakan)['total']
    : 0;

// Pengajuan terbaru
$q_terbaru = mysqli_query($koneksi, "
    SELECT
        p.kode_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        pm.nama_lengkap
    FROM t_peminjaman p
    JOIN t_peminjam pm
        ON p.peminjam_id = pm.peminjam_id
    ORDER BY p.peminjaman_id DESC
    LIMIT 5
");

if (!$q_terbaru) {
    die("Gagal mengambil data pengajuan: " . mysqli_error($koneksi));
}

function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Petugas | CampRent</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

<style>
body {
    background: #f1f5f9;
    font-family: Arial, sans-serif;
    color: #102a43;
}

.sidebar {
    width: 250px;
    height: 100vh;
    background: linear-gradient(180deg, #102a43, #176b78);
    position: fixed;
    top: 0;
    left: 0;
    padding: 25px 18px;
    color: white;
    overflow-y: auto;
}

.brand {
    text-align: center;
    padding-bottom: 25px;
    border-bottom: 1px solid #ffffff30;
    margin-bottom: 25px;
}

.brand i {
    font-size: 35px;
    margin-bottom: 10px;
}

.brand h4 {
    font-weight: bold;
    letter-spacing: 2px;
    margin: 0;
}

.brand small {
    color: #c5e9ed;
}

.menu-title {
    font-size: 11px;
    color: #c5e9ed;
    margin: 20px 10px 10px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.sidebar .nav-link {
    color: #e6f4f5;
    padding: 12px 14px;
    margin-bottom: 7px;
    border-radius: 9px;
    text-decoration: none;
    display: block;
    font-size: 13px;
}

.sidebar .nav-link i {
    width: 25px;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: #ffffff2b;
    color: white;
}

.main-content {
    margin-left: 250px;
    padding: 30px;
}

.topbar {
    background: white;
    padding: 18px 22px;
    border-radius: 14px;
    box-shadow: 0 4px 15px #102a430d;
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}

.topbar h4 {
    font-weight: bold;
    margin: 0;
}

.welcome {
    background: linear-gradient(135deg, #102a43, #176b78);
    color: white;
    border-radius: 16px;
    padding: 28px;
    margin-bottom: 25px;
}

.welcome h3 {
    font-weight: bold;
}

.welcome p {
    color: #d8edf0;
    margin-bottom: 0;
}

.stat-card {
    border: none;
    border-radius: 15px;
    background: white;
    padding: 23px;
    height: 100%;
    box-shadow: 0 4px 15px #102a430d;
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    margin-bottom: 16px;
}

.icon-blue {
    background: #dbeafe;
    color: #1d4ed8;
}

.icon-orange {
    background: #fef3c7;
    color: #b45309;
}

.icon-cyan {
    background: #cffafe;
    color: #0e7490;
}

.icon-green {
    background: #dcfce7;
    color: #15803d;
}

.icon-red {
    background: #fee2e2;
    color: #b91c1c;
}

.icon-purple {
    background: #ede9fe;
    color: #6d28d9;
}

.stat-card h3 {
    font-size: 29px;
    font-weight: bold;
    margin-bottom: 5px;
    overflow-wrap: anywhere;
}

.stat-card p {
    color: #64748b;
    margin: 0;
    font-size: 13px;
}

.section-card {
    background: white;
    border: none;
    border-radius: 15px;
    box-shadow: 0 4px 15px #102a430d;
    overflow: hidden;
}

.section-header {
    padding: 20px 22px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.section-header h5 {
    margin: 0;
    font-weight: bold;
}

.table th {
    background: #eaf2f7;
    color: #102a43;
    white-space: nowrap;
    font-size: 13px;
}

.table td {
    vertical-align: middle;
    font-size: 13px;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    background: #e2e8f0;
    color: #334155;
}

.status-menunggu {
    background: #fef3c7;
    color: #92400e;
}

.status-dipinjam {
    background: #dbeafe;
    color: #1d4ed8;
}

.status-selesai {
    background: #dcfce7;
    color: #166534;
}

.status-ditolak,
.status-batal {
    background: #fee2e2;
    color: #b91c1c;
}

.btn-camp {
    background: #176b78;
    color: white;
    border-radius: 8px;
}

.btn-camp:hover {
    background: #102a43;
    color: white;
}

@media(max-width: 991px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }

    .main-content {
        margin-left: 0;
        padding: 18px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>

<body>

<div class="sidebar">
    <div class="brand">
        <i class="fa-solid fa-campground"></i>
        <h4>CAMPrent</h4>
        <small>Sistem Peminjaman Alat</small>
    </div>

    <div class="menu-title">Menu Petugas</div>

    <a href="dashboard.php" class="nav-link active">
        <i class="fa-solid fa-house"></i> Dashboard
    </a>

    <a href="peminjaman.php" class="nav-link">
        <i class="fa-solid fa-clipboard-list"></i> Data Peminjaman
    </a>

    <a href="pengembalian.php" class="nav-link">
        <i class="fa-solid fa-rotate-left"></i> Pengembalian
    </a>

    <a href="laporan.php" class="nav-link">
        <i class="fa-solid fa-file-lines"></i> Laporan
    </a>

    <div class="menu-title">Akun</div>

    <a href="logout.php" class="nav-link"
       onclick="return confirm('Yakin ingin logout?')">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
    </a>
</div>

<div class="main-content">

    <div class="topbar">
        <div>
            <h4>Dashboard Petugas</h4>
            <small class="text-secondary">
                Kelola pengajuan dan pengembalian alat.
            </small>
        </div>

        <div>
            <i class="fa-solid fa-user-shield me-2"></i>
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Petugas') ?></strong>
        </div>
    </div>

    <div class="welcome">
        <h3>
            Halo, <?= htmlspecialchars($_SESSION['username'] ?? 'Petugas') ?>!
        </h3>
        <p>
            Selamat datang di halaman petugas CampRent.
            Pantau pengajuan, pengembalian, dan denda di sini.
        </p>
    </div>

    <!-- Statistik pengajuan -->
    <div class="row g-4 mb-4">

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <h3><?= $total_pengajuan ?></h3>
                <p>Total Pengajuan</p>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h3><?= $total_menunggu ?></h3>
                <p>Menunggu Persetujuan</p>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon icon-cyan">
                    <i class="fa-solid fa-campground"></i>
                </div>
                <h3><?= $total_dipinjam ?></h3>
                <p>Sedang Dipinjam</p>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h3><?= $total_selesai ?></h3>
                <p>Peminjaman Selesai</p>
            </div>
        </div>

    </div>

    <!-- Ringkasan denda -->
    <div class="mb-3">
        <h5 class="fw-bold">Ringkasan Denda & Kerusakan</h5>
        <p class="text-secondary small">
            Jumlah berdasarkan data denda dan biaya kerusakan yang tercatat.
        </p>
    </div>

    <div class="row g-4 mb-4">

        <div class="col-sm-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <i class="fa-solid fa-clock"></i>
                </div>
                <h3 style="font-size:24px"><?= rupiah($total_denda) ?></h3>
                <p>Total Denda Keterlambatan</p>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-icon icon-purple">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
                <h3 style="font-size:24px"><?= rupiah($total_kerusakan) ?></h3>
                <p>Total Biaya Kerusakan</p>
            </div>
        </div>

        <div class="col-sm-6 col-xl-4">
            <div class="stat-card">
                <div class="stat-icon icon-orange">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>
                <h3 style="font-size:24px"><?= rupiah($total_denda + $total_kerusakan) ?></h3>
                <p>Total Denda & Kerusakan</p>
            </div>
        </div>

    </div>

    <!-- Pengajuan terbaru -->
    <div class="section-card">

        <div class="section-header">
            <h5>
                <i class="fa-solid fa-clock-rotate-left me-2"></i>
                Pengajuan Terbaru
            </h5>

            <a href="peminjaman.php" class="btn btn-camp btn-sm">
                Lihat Semua <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">No.</th>
                        <th>Kode Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Rencana Kembali</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (mysqli_num_rows($q_terbaru) > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($row = mysqli_fetch_assoc($q_terbaru)): ?>
                        <?php
                        $status_row = trim($row['status'] ?? '');
                        $status_lower = strtolower($status_row);
                        $class_status = '';

                        if (strpos($status_lower, 'menunggu') !== false) {
                            $class_status = 'status-menunggu';
                        } elseif (strpos($status_lower, 'dipinjam') !== false) {
                            $class_status = 'status-dipinjam';
                        } elseif (strpos($status_lower, 'selesai') !== false) {
                            $class_status = 'status-selesai';
                        } elseif (
                            strpos($status_lower, 'ditolak') !== false ||
                            strpos($status_lower, 'batal') !== false
                        ) {
                            $class_status = 'status-ditolak';
                        }
                        ?>

                        <tr>
                            <td class="ps-3"><?= $no++ ?></td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars($row['kode_peminjaman'] ?? '-') ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['nama_lengkap'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['tanggal_pinjam'] ?? '-') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($row['tanggal_rencana_kembali'] ?? '-') ?>
                            </td>

                            <td>
                                <span class="status <?= $class_status ?>">
                                    <?= htmlspecialchars($status_row ?: 'Belum ada status') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-secondary">
                            <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                            Belum ada pengajuan peminjaman.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center text-secondary mt-4 small">
        &copy; <?= date('Y') ?> CampRent - Sistem Peminjaman Alat RPL
    </div>

</div>

</body>
</html>