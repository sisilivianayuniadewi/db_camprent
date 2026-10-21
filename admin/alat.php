<?php
session_start();
require_once "../koneksi.php";

if (!isset($_SESSION['user_id']) || $_SESSION['user_status'] != 1) {
    header("Location: ../index.php");
    exit;
}

// PESAN NOTIFIKASI
$pesan = $_GET['pesan'] ?? '';

// PENCARIAN
$cari = trim($_GET['cari'] ?? '');
$cari_sql = mysqli_real_escape_string($koneksi, $cari);

$where = "";

if ($cari !== '') {
    $where = "WHERE a.nama_alat LIKE '%$cari_sql%'
        OR a.kode_alat LIKE '%$cari_sql%'
        OR k.nama_kategori LIKE '%$cari_sql%'";
}

// AMBIL DATA ALAT
$query = mysqli_query($koneksi, "
    SELECT a.*, k.nama_kategori
    FROM t_alat a
    LEFT JOIN t_kategori k
        ON a.kategori_id = k.kategori_id
    $where
    ORDER BY a.alat_id DESC
");

// FUNGSI FORMAT RUPIAH
function rupiah($angka) {
    return 'Rp' . number_format((float)$angka, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Data Alat - CampRent</title>

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
            z-index: 1000;
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

        .page-title {
            font-weight: bold;
            color: #102a43;
        }

        .btn-tambah {
            background: #176b78;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 13px;
        }

        .btn-tambah:hover {
            background: #12304a;
            color: white;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            border-color: #d9e3ed;
            font-size: 13px;
            padding: 10px 12px;
        }

        .form-control:focus {
            border-color: #176b78;
            box-shadow: 0 0 0 3px #176b781c;
        }

        .table thead th {
            background: #e9f2f7;
            color: #16324f;
            font-size: 12px;
            white-space: nowrap;
            padding: 14px 12px;
        }

        .table tbody td {
            font-size: 13px;
            vertical-align: middle;
            padding: 12px;
        }

        .foto-alat {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid #e3eaf0;
        }

        .foto-kosong {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9f2f7;
            color: #718096;
            border-radius: 9px;
            font-size: 22px;
        }

        .badge-stok {
            background: #dcf8eb;
            color: #168052;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .badge-habis {
            background: #ffe4e4;
            color: #b42318;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .badge-baik {
            background: #dcf8eb;
            color: #168052;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .badge-rusak {
            background: #ffe4e4;
            color: #b42318;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 11px;
        }

        .btn-aksi {
            width: 33px;
            height: 33px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 7px;
            border: none;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-edit {
            background: #e4efff;
            color: #2764bd;
        }

        .btn-edit:hover {
            background: #2764bd;
            color: white;
        }

        .btn-hapus {
            background: #ffe4e4;
            color: #b42318;
        }

        .btn-hapus:hover {
            background: #b42318;
            color: white;
        }

        .alert {
            border: none;
            border-radius: 10px;
            font-size: 13px;
        }

        .empty-state {
            padding: 40px 15px !important;
            color: #718096;
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

            .topbar {
                align-items: flex-start !important;
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
        <i class="bi bi-grid"></i> Dashboard
    </a>

    <a href="alat.php" class="active">
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

    <a href="logout.php"
       onclick="return confirm('Yakin ingin keluar?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<!-- KONTEN UTAMA -->
<div class="main">

    <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5>Data Alat</h5>
            <div class="muted">
                Kelola data alat pada sistem CampRent.
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

    <!-- NOTIFIKASI -->
    <?php if ($pesan == 'tambah') { ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            Data alat berhasil ditambahkan.
        </div>
    <?php } elseif ($pesan == 'edit') { ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            Data alat berhasil diperbarui.
        </div>
    <?php } elseif ($pesan == 'hapus') { ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            Data alat berhasil dihapus.
        </div>
    <?php } elseif ($pesan == 'gagal') { ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            Data alat gagal dihapus. Alat mungkin masih digunakan dalam transaksi.
        </div>
    <?php } ?>

    <!-- JUDUL DAN TOMBOL TAMBAH -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
            <h4 class="page-title mb-1">Daftar Data Alat</h4>
            <div class="muted">
                Informasi alat, kategori, harga sewa, stok, dan kondisi alat.
            </div>
        </div>

        <a href="alat_tambah.php" class="btn btn-tambah">
            <i class="bi bi-plus-circle me-1"></i>
            Tambah Alat
        </a>
    </div>

    <!-- TABEL DATA ALAT -->
    <div class="content-card">

        <!-- PENCARIAN -->
        <form method="GET" action="alat.php" class="row g-2 mb-4">
            <div class="col-12 col-md-9">
                <input
                    type="text"
                    name="cari"
                    class="form-control"
                    placeholder="Cari nama alat, kode alat, atau kategori..."
                    value="<?= htmlspecialchars($cari) ?>"
                >
            </div>

            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-search me-1"></i>
                    Cari
                </button>
            </div>

            <div class="col-6 col-md-1">
                <a href="alat.php"
                   class="btn btn-light border w-100"
                   title="Reset pencarian">
                    <i class="bi bi-arrow-clockwise"></i>
                </a>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1">Data Alat CampRent</h5>
                <div class="muted">
                    <?php
                    if ($query) {
                        echo mysqli_num_rows($query);
                    } else {
                        echo 0;
                    }
                    ?>
                    data ditemukan
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Foto</th>
                        <th>Kode Alat</th>
                        <th>Nama Alat</th>
                        <th>Kategori</th>
                        <th>Merk</th>
                        <th>Harga Sewa / Hari</th>
                        <th>Stok</th>
                        <th>Kondisi</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php
                if ($query && mysqli_num_rows($query) > 0) {
                    $no = 1;

                    while ($row = mysqli_fetch_assoc($query)) {
                        $foto = basename($row['foto'] ?? '');
                        $lokasi_foto = "../uploads/alat/" . $foto;
                ?>
                    <tr>
                        <td><?= $no++ ?></td>

                        <td>
                            <?php if (
                                $foto != '' &&
                                file_exists($lokasi_foto)
                            ) { ?>
                                <img
                                    src="<?= htmlspecialchars($lokasi_foto) ?>"
                                    class="foto-alat"
                                    alt="Foto alat"
                                >
                            <?php } else { ?>
                                <div class="foto-kosong">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php } ?>
                        </td>

                        <td class="fw-bold">
                            <?= htmlspecialchars($row['kode_alat'] ?? '-') ?>
                        </td>

                        <td class="fw-semibold">
                            <?= htmlspecialchars($row['nama_alat'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['nama_kategori'] ?? '-') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['merk'] ?? '-') ?>
                        </td>

                        <td class="fw-bold text-success">
                            <?= rupiah($row['harga_sewa'] ?? 0) ?>
                        </td>

                        <td>
                            <?php if ((int)($row['stok'] ?? 0) > 0) { ?>
                                <span class="badge-stok">
                                    <?= (int)$row['stok'] ?> unit
                                </span>
                            <?php } else { ?>
                                <span class="badge-habis">
                                    Stok habis
                                </span>
                            <?php } ?>
                        </td>

                        <td>
                            <?php if (($row['kondisi'] ?? '') == 'Baik') { ?>
                                <span class="badge-baik">Baik</span>
                            <?php } else { ?>
                                <span class="badge-rusak">
                                    <?= htmlspecialchars(
                                        $row['kondisi'] ?? 'Tidak diketahui'
                                    ) ?>
                                </span>
                            <?php } ?>
                        </td>

                        <td class="text-center">
                            <div class="d-flex justify-content-center gap-2">

                                <!-- TOMBOL EDIT -->
                                <a
                                    href="alat_edit.php?id=<?= (int)$row['alat_id'] ?>"
                                    class="btn-aksi btn-edit"
                                    title="Edit alat"
                                >
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                <!-- TOMBOL HAPUS MENUJU alat_hapus.php -->
                                <form
                                    action="alat_hapus.php"
                                    method="POST"
                                    onsubmit="return confirm('Yakin ingin menghapus alat ini?')"
                                >
                                    <input
                                        type="hidden"
                                        name="alat_id"
                                        value="<?= (int)$row['alat_id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="hapus"
                                        class="btn-aksi btn-hapus"
                                        title="Hapus alat"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                ?>
                    <tr>
                        <td colspan="10" class="text-center empty-state">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>

                            <div class="fw-bold">
                                Data alat belum tersedia
                            </div>

                            <div class="muted">
                                <?php if ($cari != '') { ?>
                                    Data yang kamu cari tidak ditemukan.
                                <?php } else { ?>
                                    Silakan tambahkan data alat terlebih dahulu.
                                <?php } ?>
                            </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>