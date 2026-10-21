<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_status'] != 1) {
    header("Location: ../index.php");
    exit;
}

// Menghitung total alat
$q_alat = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM t_alat");
$d_alat = mysqli_fetch_assoc($q_alat);
$total_alat = $d_alat['total'] ?? 0;

// Menghitung total pengguna
$q_user = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM t_user");
$d_user = mysqli_fetch_assoc($q_user);
$total_user = $d_user['total'] ?? 0;

// Menghitung peminjaman menunggu persetujuan
$q_menunggu = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_peminjaman
    WHERE status = 'Menunggu Persetujuan'
");
$d_menunggu = mysqli_fetch_assoc($q_menunggu);
$total_menunggu = $d_menunggu['total'] ?? 0;

// Menghitung alat yang sedang dipinjam
$q_dipinjam = mysqli_query($koneksi, "
    SELECT COUNT(*) AS total
    FROM t_peminjaman
    WHERE status = 'Dipinjam'
");
$d_dipinjam = mysqli_fetch_assoc($q_dipinjam);
$total_dipinjam = $d_dipinjam['total'] ?? 0;

// Mengambil data peminjaman terbaru
$q_terbaru = mysqli_query($koneksi, "
    SELECT
        p.peminjaman_id,
        p.kode_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        pm.nama_lengkap
    FROM t_peminjaman p
    LEFT JOIN t_peminjam pm
        ON p.peminjam_id = pm.peminjam_id
    ORDER BY p.peminjaman_id DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            margin: 0;
            background: #f0f5fa;
            font-family: Arial, sans-serif;
            color: #102a43;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 225px;
            height: 100vh;
            padding: 22px 15px;
            color: white;
            background: linear-gradient(180deg, #12304a, #176b78);
            overflow-y: auto;
        }

        .brand {
            text-align: center;
            padding-bottom: 22px;
            border-bottom: 1px solid #ffffff35;
            margin-bottom: 20px;
        }

        .brand i {
            font-size: 38px;
        }

        .brand h4 {
            font-weight: bold;
            margin: 8px 0 2px;
        }

        .brand small {
            font-size: 12px;
            color: #d2edf1;
        }

        .menu-title {
            color: #b9e5ee;
            font-size: 10px;
            font-weight: bold;
            margin: 18px 8px 10px;
            letter-spacing: 1px;
        }

        .sidebar a {
            display: block;
            padding: 12px 10px;
            margin-bottom: 5px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #ffffff30;
        }

        .sidebar a i {
            margin-right: 9px;
        }

        .main {
            margin-left: 225px;
            padding: 25px;
        }

        .topbar,
        .content-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 5px 18px #102a430b;
            margin-bottom: 22px;
        }

        .topbar h5 {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .muted {
            color: #718096;
            font-size: 13px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border-radius: 14px;
            box-shadow: 0 5px 18px #102a430b;
            height: 100%;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 23px;
            margin-bottom: 17px;
        }

        .blue {
            background: #e4efff;
            color: #2764bd;
        }

        .green {
            background: #dcf8eb;
            color: #168052;
        }

        .orange {
            background: #fff0d8;
            color: #c47a12;
        }

        .purple {
            background: #eee5ff;
            color: #7542bd;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            margin: 5px 0;
        }

        .table thead th {
            background: #e9f2f7;
            color: #16324f;
            font-size: 12px;
            white-space: nowrap;
        }

        .table tbody td {
            font-size: 13px;
            vertical-align: middle;
        }

        .status {
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            display: inline-block;
        }

        .status-menunggu {
            background: #fff0d8;
            color: #a76508;
        }

        .status-dipinjam {
            background: #e4efff;
            color: #2764bd;
        }

        .status-selesai {
            background: #dcf8eb;
            color: #168052;
        }

        .status-ditolak {
            background: #ffe4e4;
            color: #b42318;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: static;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }
        }
    </style>
</head>

<body>

<div class="sidebar">
    <div class="brand">
        <i class="bi bi-tools"></i>
        <h4>CAMPrent</h4>
        <small>SISTEM PEMINJAMAN ALAT</small>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <a href="dashboard.php" class="active">
        <i class="bi bi-grid"></i> Dashboard
    </a>

    <a href="alat.php">
        <i class="bi bi-box-seam"></i> Data Alat
    </a>

    <a href="kategori.php">
        <i class="bi bi-tags"></i> Kategori
    </a>

    <a href="user.php">
        <i class="bi bi-people"></i> Kelola Pengguna
    </a>

    <a href="log.php">
        <i class="bi bi-clock-history"></i> Log Aktivitas
    </a>

    <div class="menu-title">AKUN</div>

    <a href="logout.php" onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<div class="main">

    <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5>Dashboard Admin</h5>
            <div class="muted">Selamat datang di sistem CampRent.</div>
        </div>

        <div class="text-end">
            <div class="fw-bold">
                <i class="bi bi-person-circle me-1"></i>
                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
            </div>
            <div class="muted">Administrator</div>
        </div>
    </div>

    <div class="mb-4">
        <h4 class="fw-bold">Ringkasan Sistem</h4>
        <div class="muted">Informasi alat, pengguna, dan aktivitas peminjaman.</div>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="bi bi-box-seam"></i>
                </div>
                <div class="muted">Total Jenis Alat</div>
                <div class="stat-number"><?= $total_alat ?></div>
                <div class="muted">Data alat terdaftar</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="bi bi-people"></i>
                </div>
                <div class="muted">Total Pengguna</div>
                <div class="stat-number"><?= $total_user ?></div>
                <div class="muted">Semua akun sistem</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="muted">Menunggu Persetujuan</div>
                <div class="stat-number"><?= $total_menunggu ?></div>
                <div class="muted">Pengajuan peminjaman</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="bi bi-arrow-left-right"></i>
                </div>
                <div class="muted">Sedang Dipinjam</div>
                <div class="stat-number"><?= $total_dipinjam ?></div>
                <div class="muted">Peminjaman aktif</div>
            </div>
        </div>

    </div>

    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Peminjaman Terbaru</h5>
                <div class="muted">Lima data peminjaman terakhir.</div>
            </div>

            <a href="log.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-clock-history me-1"></i> Log Aktivitas
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Tanggal Pinjam</th>
                        <th>Rencana Kembali</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                if ($q_terbaru && mysqli_num_rows($q_terbaru) > 0) {
                    $no = 1;

                    while ($row = mysqli_fetch_assoc($q_terbaru)) {
                        $status = $row['status'] ?? '';

                        $class_status = 'status-menunggu';

                        if ($status == 'Dipinjam') {
                            $class_status = 'status-dipinjam';
                        } elseif ($status == 'Selesai') {
                            $class_status = 'status-selesai';
                        } elseif ($status == 'Ditolak') {
                            $class_status = 'status-ditolak';
                        }
                ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td class="fw-bold">
                            <?= htmlspecialchars($row['kode_peminjaman'] ?? '-') ?>
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
                                <?= htmlspecialchars($status ?: '-') ?>
                            </span>
                        </td>
                    </tr>
                <?php
                        $no++;
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Belum ada data peminjaman.
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center muted mt-4">
        &copy; <?= date('Y') ?> CampRent - Sistem Peminjaman Alat
    </div>

</div>

</body>
</html>