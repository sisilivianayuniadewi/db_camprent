<?php
session_start();
include "../koneksi.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_status'] != 3) {
    header("Location: ../index.php");
    exit();
}

$kode_peminjaman = "";
$data = [];
$error = "";

if (isset($_GET['kode_peminjaman'])) {
    $kode_peminjaman = trim($_GET['kode_peminjaman']);

    if ($kode_peminjaman == "") {
        $error = "Masukkan kode peminjaman terlebih dahulu.";
    } else {
        $stmt = mysqli_prepare($koneksi, "
            SELECT
                p.peminjaman_id,
                p.kode_peminjaman,
                p.tanggal_pinjam,
                p.tanggal_rencana_kembali,
                p.status,
                p.catatan,
                pm.nama_lengkap,
                GROUP_CONCAT(
                    CONCAT(a.nama_alat, ' (', d.jumlah, ')')
                    SEPARATOR ', '
                ) AS daftar_alat
            FROM t_peminjaman p
            JOIN t_peminjam pm
                ON p.peminjam_id = pm.peminjam_id
            LEFT JOIN t_detail_peminjaman d
                ON p.peminjaman_id = d.peminjaman_id
            LEFT JOIN t_alat a
                ON d.alat_id = a.alat_id
            WHERE p.kode_peminjaman = ?
            GROUP BY
                p.peminjaman_id,
                p.kode_peminjaman,
                p.tanggal_pinjam,
                p.tanggal_rencana_kembali,
                p.status,
                p.catatan,
                pm.nama_lengkap
            ORDER BY p.peminjaman_id DESC
        ");

        mysqli_stmt_bind_param($stmt, "s", $kode_peminjaman);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($hasil)) {
            $data[] = $row;
        }

        if (count($data) == 0) {
            $error = "Kode peminjaman tidak ditemukan. Periksa kembali kode kamu.";
        }

        mysqli_stmt_close($stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status Peminjaman | CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f1f5f9;
            font-family: Arial, sans-serif;
            color: #102a43;
        }

        .navbar {
            background: linear-gradient(135deg, #102a43, #176b78);
        }

        .navbar-brand {
            color: white;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .navbar-brand:hover {
            color: #d8f3f5;
        }

        .main-content {
            padding: 35px 0;
        }

        .page-title {
            font-weight: bold;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(16, 42, 67, 0.07);
            overflow: hidden;
        }

        .card-header-custom {
            background: linear-gradient(135deg, #102a43, #176b78);
            color: white;
            padding: 18px 22px;
        }

        .form-control {
            padding: 12px;
            border-radius: 9px;
        }

        .btn-camp {
            background: #176b78;
            color: white;
            border-radius: 9px;
            padding: 11px 18px;
        }

        .btn-camp:hover {
            background: #102a43;
            color: white;
        }

        .table th {
            background: #eaf2f7;
            color: #102a43;
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .status {
            display: inline-block;
            padding: 6px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            background: #e2e8f0;
            color: #334155;
        }

        .status-menunggu {
            background: #fef3c7;
            color: #92400e;
        }

        .status-disetujui,
        .status-dipinjam {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-ditolak,
        .status-batal {
            background: #fee2e2;
            color: #b91c1c;
        }

        .status-selesai {
            background: #dcfce7;
            color: #166534;
        }

        footer {
            color: #64748b;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>

<body>

<nav class="navbar navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fa-solid fa-campground me-2"></i>CAMPRent
        </a>

        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-sm btn-outline-light">
                <i class="fa-solid fa-house me-1"></i> Dashboard
            </a>

            <a href="logout.php"
               class="btn btn-sm btn-light"
               onclick="return confirm('Yakin ingin logout?')">
                <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
            </a>
        </div>
    </div>
</nav>

<div class="container main-content">

    <div class="mb-4">
        <h3 class="page-title">
            <i class="fa-solid fa-clipboard-check me-2"></i>
            Status Peminjaman
        </h3>

        <p class="text-secondary">
            Cek status pengajuan peminjaman alat camping menggunakan kode peminjaman.
        </p>
    </div>

    <div class="card mb-4">
        <div class="card-header-custom">
            <h5 class="mb-1">
                <i class="fa-solid fa-magnifying-glass me-2"></i>
                Cari Peminjaman
            </h5>
            <small>Masukkan kode peminjaman yang kamu dapatkan setelah mengajukan peminjaman.</small>
        </div>

        <div class="card-body p-4">
            <form method="GET">
                <label class="form-label fw-semibold">
                    Kode Peminjaman
                </label>

                <div class="row g-2">
                    <div class="col-md-9">
                        <input
                            type="text"
                            name="kode_peminjaman"
                            class="form-control"
                            placeholder="Contoh: PJM123456"
                            value="<?= htmlspecialchars($kode_peminjaman) ?>"
                            required
                        >
                    </div>

                    <div class="col-md-3">
                        <button type="submit" class="btn btn-camp w-100">
                            <i class="fa-solid fa-search me-1"></i>
                            Cari Status
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error != ""): ?>
        <div class="alert alert-warning">
            <i class="fa-solid fa-circle-info me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (count($data) > 0): ?>

        <div class="card">
            <div class="card-header-custom">
                <h5 class="mb-1">
                    <i class="fa-solid fa-clock-rotate-left me-2"></i>
                    Hasil Pencarian
                </h5>

                <small>
                    Nama Peminjam:
                    <?= htmlspecialchars($data[0]['nama_lengkap']) ?>
                </small>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">No.</th>
                                <th>Kode Peminjaman</th>
                                <th>Daftar Alat</th>
                                <th>Tanggal Pinjam</th>
                                <th>Rencana Kembali</th>
                                <th>Status</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($data as $i => $row): ?>

                            <?php
                            $status = trim($row['status'] ?? '');
                            $statusLower = strtolower($status);
                            $classStatus = "";

                            if (strpos($statusLower, 'menunggu') !== false) {
                                $classStatus = "status-menunggu";
                            } elseif (strpos($statusLower, 'disetujui') !== false) {
                                $classStatus = "status-disetujui";
                            } elseif (strpos($statusLower, 'dipinjam') !== false) {
                                $classStatus = "status-dipinjam";
                            } elseif (strpos($statusLower, 'ditolak') !== false) {
                                $classStatus = "status-ditolak";
                            } elseif (strpos($statusLower, 'batal') !== false) {
                                $classStatus = "status-batal";
                            } elseif (strpos($statusLower, 'selesai') !== false) {
                                $classStatus = "status-selesai";
                            }
                            ?>

                            <tr>
                                <td class="ps-3"><?= $i + 1 ?></td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($row['kode_peminjaman'] ?? '-') ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['daftar_alat'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['tanggal_pinjam'] ?? '-') ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['tanggal_rencana_kembali'] ?? '-') ?>
                                </td>

                                <td>
                                    <span class="status <?= $classStatus ?>">
                                        <?= htmlspecialchars($status ?: 'Belum ada status') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars($row['catatan'] ?? '-') ?>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<footer>
    &copy; <?= date('Y') ?> CampRent - Sistem Peminjaman Alat Camping
</footer>

</body>
</html>