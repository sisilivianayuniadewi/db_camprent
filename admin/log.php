<?php
session_start();
require_once "../koneksi.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_status']) ||
    (int) $_SESSION['user_status'] !== 1
) {
    header("Location: ../index.php");
    exit;
}

// PENCARIAN LOG
$cari = trim($_GET['cari'] ?? '');
$cari_sql = mysqli_real_escape_string($koneksi, $cari);

$where = "";

if ($cari !== '') {
    $where = "WHERE l.aktivitas LIKE '%$cari_sql%'
              OR u.username LIKE '%$cari_sql%'";
}

// AMBIL LOG AKTIVITAS
$query = mysqli_query($koneksi, "
    SELECT l.*, u.username, u.user_status
    FROM t_log_aktivitas l
    LEFT JOIN t_user u ON l.user_id = u.user_id
    $where
    ORDER BY l.waktu DESC
");

if (!$query) {
    die("Gagal mengambil log aktivitas: " . mysqli_error($koneksi));
}

$total_log = mysqli_num_rows($query);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Log Aktivitas - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f0f5fa;
            font-family: Arial, sans-serif;
            color: #102a43;
            margin: 0;
        }

        .sidebar {
            width: 225px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            padding: 22px 15px;
            background: linear-gradient(180deg, #12304a, #176b78);
            color: white;
            overflow-y: auto;
        }

        .brand {
            text-align: center;
            padding-bottom: 22px;
            margin-bottom: 20px;
            border-bottom: 1px solid #ffffff30;
        }

        .brand i {
            font-size: 35px;
            color: #70e1e8;
        }

        .brand h4 {
            margin: 8px 0 3px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .brand small {
            font-size: 10px;
            color: #c4e7ed;
            letter-spacing: 1px;
        }

        .menu-title {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #b9e5ee;
            margin: 20px 10px 10px;
        }

        .sidebar a {
            display: block;
            text-decoration: none;
            color: white;
            padding: 12px 10px;
            border-radius: 8px;
            margin-bottom: 5px;
            font-size: 13px;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #ffffff30;
            color: white;
        }

        .sidebar a i {
            width: 24px;
            font-size: 16px;
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

        .page-title {
            font-size: 23px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .muted {
            color: #718096;
            font-size: 13px;
        }

        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .form-control {
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
        }

        .btn {
            border-radius: 8px;
            font-size: 13px;
        }

        .btn-cari {
            background: #137f89;
            color: white;
            border: none;
        }

        .btn-cari:hover {
            background: #102a43;
            color: white;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #e9f2f7;
            color: #16324f;
            font-size: 12px;
            white-space: nowrap;
            padding: 13px 10px;
        }

        .table tbody td {
            font-size: 13px;
            vertical-align: middle;
            padding: 12px 10px;
        }

        .table tbody tr:hover {
            background: #f5faff;
        }

        .icon-log {
            width: 38px;
            height: 38px;
            min-width: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e1f5f7;
            color: #137f89;
            font-size: 17px;
        }

        .activity-text {
            min-width: 180px;
            max-width: 420px;
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .footer {
            text-align: center;
            color: #718096;
            font-size: 12px;
            padding: 10px;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
                padding: 15px;
            }

            .topbar,
            .content-card {
                padding: 16px;
            }

            .page-title {
                font-size: 20px;
            }
        }
    </style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="brand">
        <i class="bi bi-tools"></i>
        <h4>CAMPrent</h4>
        <small>SISTEM PEMINJAMAN ALAT</small>
    </div>

    <div class="menu-title">MENU UTAMA</div>

    <a href="dashboard.php">
        <i class="bi bi-grid-1x2-fill"></i> Dashboard
    </a>

    <a href="alat.php">
        <i class="bi bi-tools"></i> Data Alat
    </a>

    <a href="kategori.php">
        <i class="bi bi-tags-fill"></i> Kategori
    </a>

    <a href="user.php">
        <i class="bi bi-people-fill"></i> Kelola User
    </a>

    <a href="log.php" class="active">
        <i class="bi bi-clock-history"></i> Log Aktivitas
    </a>

    <div class="menu-title">AKUN</div>

    <a href="logout.php"
       onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<!-- KONTEN UTAMA -->
<div class="main">

    <div class="topbar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="page-title">
                    <i class="bi bi-clock-history me-2"
                       style="color:#137f89;"></i>
                    Log Aktivitas
                </div>

                <div class="muted">
                    Riwayat kegiatan yang tercatat dalam sistem CampRent.
                </div>
            </div>

            <div class="text-end">
                <div class="fw-bold">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                </div>
                <div class="muted">Administrator</div>
            </div>
        </div>
    </div>

    <!-- RINGKASAN -->
    <div class="row g-3 mb-1">
        <div class="col-md-6 col-xl-4">
            <div class="content-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="icon-log">
                        <i class="bi bi-journal-text"></i>
                    </div>

                    <div>
                        <div class="muted">Total Aktivitas Ditemukan</div>
                        <div class="fs-4 fw-bold"><?= $total_log ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DAFTAR LOG -->
    <div class="content-card">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <div class="section-title">Riwayat Aktivitas</div>
                <div class="muted">
                    Aktivitas terbaru ditampilkan terlebih dahulu.
                </div>
            </div>

            <span class="badge rounded-pill"
                  style="background:#e1f5f7;color:#137f89;padding:8px 12px;">
                <i class="bi bi-clock me-1"></i>
                <?= $total_log ?> aktivitas
            </span>
        </div>

        <!-- PENCARIAN -->
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-6">
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari username atau aktivitas..."
                    value="<?= htmlspecialchars($cari) ?>">
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-cari">
                    <i class="bi bi-search me-1"></i>
                    Cari
                </button>

                <a href="log.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>
                    Reset
                </a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Pengguna</th>
                        <th>Role</th>
                        <th>Aktivitas</th>
                        <th>Waktu</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($total_log > 0): ?>
                    <?php $no = 1; ?>

                    <?php while ($log = mysqli_fetch_assoc($query)): ?>
                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="icon-log">
                                        <i class="bi bi-person-fill"></i>
                                    </div>

                                    <div>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars(
                                                $log['username']
                                                ?? 'Pengguna tidak tersedia'
                                            ) ?>
                                        </div>

                                        <div class="muted">
                                            ID: <?= (int) $log['user_id'] ?>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td>
                                <?php if ((int) ($log['user_status'] ?? 0) === 1): ?>
                                    <span class="badge text-bg-primary">
                                        <i class="bi bi-shield-fill me-1"></i>
                                        Admin
                                    </span>
                                <?php elseif ((int) ($log['user_status'] ?? 0) === 2): ?>
                                    <span class="badge text-bg-secondary">
                                        <i class="bi bi-person-fill me-1"></i>
                                        Petugas
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-dark">
                                        Tidak diketahui
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="activity-text">
                                    <?= htmlspecialchars($log['aktivitas'] ?? '-') ?>
                                </div>
                            </td>

                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($log['waktu'] ?? '-') ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"
                               style="color:#9fb3c8;"></i>

                            <div class="fw-semibold">
                                <?= $cari !== ''
                                    ? 'Aktivitas tidak ditemukan.'
                                    : 'Belum ada aktivitas yang tercatat.' ?>
                            </div>

                            <div class="muted mt-1">
                                <?= $cari !== ''
                                    ? 'Coba gunakan kata kunci lain.'
                                    : 'Aktivitas sistem akan muncul di halaman ini.' ?>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
            <div class="muted">
                Menampilkan <?= $total_log ?> aktivitas
                <?= $cari !== ''
                    ? ' untuk pencarian "' . htmlspecialchars($cari) . '"'
                    : '' ?>.
            </div>

            <?php if ($cari !== ''): ?>
                <a href="log.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle me-1"></i>
                    Hapus Pencarian
                </a>
            <?php endif; ?>
        </div>

    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> CampRent — Sistem Peminjaman Alat
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
