
<?php
session_start();
include "../koneksi.php";

// PROTEKSI HALAMAN PEMINJAM
if (
    !isset($_SESSION['user_id'], $_SESSION['user_status']) ||
    (int)$_SESSION['user_status'] !== 3
) {
    header("Location: ../index.php");
    exit();
}

// DATA STATISTIK ALAT
$q_total = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM t_alat");
$total_alat = mysqli_fetch_assoc($q_total)['total'] ?? 0;

$q_kategori = mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM t_kategori");
$total_kategori = mysqli_fetch_assoc($q_kategori)['total'] ?? 0;

// AMBIL DATA ALAT DARI TABEL t_alat
$query_alat = mysqli_query($koneksi, "
    SELECT a.*, k.nama_kategori
    FROM t_alat a
    LEFT JOIN t_kategori k ON a.kategori_id = k.kategori_id
    ORDER BY a.alat_id DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Peminjam - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        body {
            background: #f4f7fb;
            font-family: Arial, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, #102a43, #176b78);
        }

        .hero {
            background: linear-gradient(135deg, #102a43, #176b78);
            color: white;
            padding: 35px;
            border-radius: 18px;
        }

        .stat-card {
            border: none;
            border-radius: 15px;
        }

        .alat-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            height: 100%;
            transition: .2s;
        }

        .alat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px rgba(0, 0, 0, .1);
        }

        .alat-icon {
            height: 150px;
            background: #e1f3f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #176b78;
            font-size: 55px;
            overflow: hidden;
        }

        .alat-icon img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .btn-camp {
            background: #176b78;
            color: white;
        }

        .btn-camp:hover {
            background: #102a43;
            color: white;
        }

        .harga {
            color: #176b78;
            font-weight: bold;
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">
            <i class="fa-solid fa-campground"></i> CampRent
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarPeminjam">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarPeminjam">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fa-solid fa-house"></i> Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="status.php">
                        <i class="fa-solid fa-clipboard-check"></i>
                        Status Peminjaman
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <span class="text-white">
                    Halo,
                    <strong>
                        <?= htmlspecialchars($_SESSION['username'] ?? 'Peminjam', ENT_QUOTES, 'UTF-8') ?>
                    </strong>
                </span>

                <a href="logout.php"
                   class="btn btn-outline-light btn-sm"
                   onclick="return confirm('Yakin ingin keluar?')">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="container my-4">

    <!-- SAMBUTAN -->
    <div class="hero mb-4">
        <h2 class="fw-bold">Selamat Datang di CampRent!</h2>
        <p class="mb-0">
            Temukan dan ajukan peminjaman alat camping dengan mudah.
        </p>
    </div>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="card stat-card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1">Total Alat</p>
                        <h3 class="fw-bold mb-0"><?= $total_alat ?></h3>
                    </div>
                    <i class="fa-solid fa-boxes-stacked fs-1 text-primary"></i>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card stat-card shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-secondary mb-1">Kategori Alat</p>
                        <h3 class="fw-bold mb-0"><?= $total_kategori ?></h3>
                    </div>
                    <i class="fa-solid fa-tags fs-1 text-success"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- DAFTAR ALAT -->
    <div class="mb-3">
        <h4 class="fw-bold mb-1">Daftar Alat Camping</h4>
        <p class="text-secondary mb-0">
            Pilih alat yang ingin kamu pinjam.
        </p>
    </div>

    <div class="row g-4">

        <?php if ($query_alat && mysqli_num_rows($query_alat) > 0) { ?>

            <?php while ($alat = mysqli_fetch_assoc($query_alat)) { ?>

                <?php
                // AMBIL FOTO DARI TABEL t_alat
                $foto = basename($alat['foto'] ?? '');
                $lokasi_foto = "../uploads/alat/" . $foto;
                ?>

                <div class="col-md-4 col-lg-3">
                    <div class="card alat-card shadow-sm">

                        <!-- FOTO ALAT -->
                        <div class="alat-icon">
                            <?php if ($foto != '' && file_exists($lokasi_foto)) { ?>

                                <img
                                    src="<?= htmlspecialchars($lokasi_foto, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($alat['nama_alat'] ?? 'Foto alat', ENT_QUOTES, 'UTF-8') ?>"
                                >

                            <?php } else { ?>

                                <i class="fa-solid fa-campground"></i>

                            <?php } ?>
                        </div>

                        <!-- INFORMASI ALAT -->
                        <div class="card-body d-flex flex-column">

                            <h5 class="fw-bold">
                                <?= htmlspecialchars($alat['nama_alat'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </h5>

                            <p class="text-secondary small mb-2">
                                <i class="fa-solid fa-tag"></i>
                                <?= htmlspecialchars($alat['nama_kategori'] ?? 'Tanpa kategori', ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <p class="small mb-2">
                                Kode:
                                <strong>
                                    <?= htmlspecialchars($alat['kode_alat'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                </strong>
                            </p>

                            <p class="harga mb-2">
                                Rp<?= number_format((float)($alat['harga_sewa'] ?? 0), 0, ',', '.') ?>
                                <small class="text-secondary fw-normal">/ hari</small>
                            </p>

                            <p class="mb-3">
                                Stok:
                                <strong>
                                    <?= (int)($alat['stok'] ?? 0) ?> unit
                                </strong>
                            </p>

                            <div class="mt-auto">

                                <?php if (
                                    (int)($alat['stok'] ?? 0) > 0 &&
                                    ($alat['kondisi'] ?? '') == 'Baik'
                                ) { ?>

                                    <a href="ajukan.php?alat_id=<?= (int)$alat['alat_id'] ?>"
                                       class="btn btn-camp w-100">
                                        <i class="fa-solid fa-hand-holding"></i>
                                        Ajukan Peminjaman
                                    </a>

                                <?php } else { ?>

                                    <button class="btn btn-secondary w-100" disabled>
                                        <?= (int)($alat['stok'] ?? 0) <= 0
                                            ? 'Stok Habis'
                                            : 'Alat Tidak Tersedia' ?>
                                    </button>

                                <?php } ?>

                            </div>
                        </div>
                    </div>
                </div>

            <?php } ?>

        <?php } else { ?>

            <div class="col-12">
                <div class="alert alert-info text-center">
                    Belum ada alat camping yang tersedia.
                </div>
            </div>

        <?php } ?>

    </div>

    <footer class="text-center text-secondary py-4">
        <small>
            &copy; <?= date('Y') ?> CampRent - Sistem Peminjaman Alat Camping
        </small>
    </footer>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
