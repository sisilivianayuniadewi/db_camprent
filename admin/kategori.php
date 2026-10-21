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

$error = "";
$pesan = "";

// TAMBAH KATEGORI
if (isset($_POST['tambah'])) {
    $nama = trim($_POST['nama_kategori'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($nama === '') {
        $error = "Nama kategori wajib diisi.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "INSERT INTO t_kategori (nama_kategori, keterangan)
             VALUES (?, ?)"
        );

        mysqli_stmt_bind_param($stmt, "ss", $nama, $keterangan);

        if (mysqli_stmt_execute($stmt)) {
            $user_id = (int) $_SESSION['user_id'];
            $aktivitas = "Menambahkan kategori: " . $nama;

            $log = mysqli_prepare(
                $koneksi,
                "INSERT INTO t_log_aktivitas (user_id, aktivitas)
                 VALUES (?, ?)"
            );

            if ($log) {
                mysqli_stmt_bind_param($log, "is", $user_id, $aktivitas);
                mysqli_stmt_execute($log);
                mysqli_stmt_close($log);
            }

            mysqli_stmt_close($stmt);
            header("Location: kategori.php?pesan=tambah");
            exit;
        } else {
            $error = "Kategori gagal ditambahkan.";
            mysqli_stmt_close($stmt);
        }
    }
}

// EDIT KATEGORI
if (isset($_POST['edit'])) {
    $id = (int) ($_POST['kategori_id'] ?? 0);
    $nama = trim($_POST['nama_kategori'] ?? '');
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($id <= 0 || $nama === '') {
        $error = "Data kategori tidak valid.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE t_kategori
             SET nama_kategori = ?, keterangan = ?
             WHERE kategori_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "ssi", $nama, $keterangan, $id);

        if (mysqli_stmt_execute($stmt)) {
            $user_id = (int) $_SESSION['user_id'];
            $aktivitas = "Mengedit kategori: " . $nama;

            $log = mysqli_prepare(
                $koneksi,
                "INSERT INTO t_log_aktivitas (user_id, aktivitas)
                 VALUES (?, ?)"
            );

            if ($log) {
                mysqli_stmt_bind_param($log, "is", $user_id, $aktivitas);
                mysqli_stmt_execute($log);
                mysqli_stmt_close($log);
            }

            mysqli_stmt_close($stmt);
            header("Location: kategori.php?pesan=edit");
            exit;
        } else {
            $error = "Kategori gagal diperbarui.";
            mysqli_stmt_close($stmt);
        }
    }
}

// HAPUS KATEGORI
if (isset($_POST['hapus'])) {
    $id = (int) ($_POST['kategori_id'] ?? 0);

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT nama_kategori
         FROM t_kategori
         WHERE kategori_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);

    if (!$data) {
        $error = "Kategori tidak ditemukan.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "DELETE FROM t_kategori WHERE kategori_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $id);

        if (mysqli_stmt_execute($stmt)) {
            $user_id = (int) $_SESSION['user_id'];
            $aktivitas = "Menghapus kategori: " . $data['nama_kategori'];

            $log = mysqli_prepare(
                $koneksi,
                "INSERT INTO t_log_aktivitas (user_id, aktivitas)
                 VALUES (?, ?)"
            );

            if ($log) {
                mysqli_stmt_bind_param($log, "is", $user_id, $aktivitas);
                mysqli_stmt_execute($log);
                mysqli_stmt_close($log);
            }

            mysqli_stmt_close($stmt);
            header("Location: kategori.php?pesan=hapus");
            exit;
        } else {
            $error = "Kategori tidak bisa dihapus karena masih digunakan oleh data alat.";
            mysqli_stmt_close($stmt);
        }
    }
}

// PESAN NOTIFIKASI
if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah') {
        $pesan = "Kategori berhasil ditambahkan.";
    } elseif ($_GET['pesan'] === 'edit') {
        $pesan = "Kategori berhasil diperbarui.";
    } elseif ($_GET['pesan'] === 'hapus') {
        $pesan = "Kategori berhasil dihapus.";
    }
}

// MODE EDIT
$edit_data = null;

if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT * FROM t_kategori WHERE kategori_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);
    $hasil_edit = mysqli_stmt_get_result($stmt);
    $edit_data = mysqli_fetch_assoc($hasil_edit);
    mysqli_stmt_close($stmt);
}

// DATA KATEGORI
$query = mysqli_query($koneksi, "
    SELECT k.*, COUNT(a.alat_id) AS jumlah_alat
    FROM t_kategori k
    LEFT JOIN t_alat a ON k.kategori_id = a.kategori_id
    GROUP BY k.kategori_id
    ORDER BY k.kategori_id DESC
");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kategori Alat - CampRent</title>

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

        .page-title {
            font-weight: bold;
            color: #102a43;
        }

        .form-label {
            color: #16324f;
            font-size: 13px;
            font-weight: bold;
        }

        .form-control {
            border: 1px solid #d9e3ed;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 13px;
        }

        .form-control:focus {
            border-color: #176b78;
            box-shadow: 0 0 0 3px #176b781c;
        }

        .btn-simpan {
            background: #176b78;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13px;
        }

        .btn-simpan:hover {
            background: #12304a;
            color: white;
        }

        .btn-batal {
            border-radius: 8px;
            font-size: 13px;
            padding: 10px 14px;
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

        .badge-jumlah {
            background: #e4efff;
            color: #2764bd;
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
            border: none;
            border-radius: 7px;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-edit {
            background: #fff0d8;
            color: #a76508;
        }

        .btn-edit:hover {
            background: #c47a12;
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
            padding: 35px 15px !important;
            color: #718096;
        }

        @media (max-width: 991px) {
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

    <a href="alat.php">
        <i class="bi bi-box-seam"></i> Data Alat
    </a>

    <a href="kategori.php" class="active">
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

<!-- KONTEN -->
<div class="main">

    <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5>Kategori Alat</h5>
            <div class="muted">Kelola kategori perlengkapan CampRent.</div>
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
        <h4 class="page-title mb-1">Kategori Alat Camping</h4>
        <div class="muted">
            Kelola pengelompokan alat dan perlengkapan camping.
        </div>
    </div>

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            <?= htmlspecialchars($pesan) ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle me-2"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="row g-3">

        <!-- FORM TAMBAH / EDIT -->
        <div class="col-12 col-lg-4">
            <div class="content-card h-100">

                <div class="mb-4">
                    <h5 class="fw-bold mb-1">
                        <i class="bi bi-tags me-2"></i>
                        <?= $edit_data ? 'Edit Kategori' : 'Tambah Kategori' ?>
                    </h5>
                    <div class="muted">
                        Isi informasi kategori alat.
                    </div>
                </div>

                <form method="POST">

                    <?php if ($edit_data): ?>
                        <input
                            type="hidden"
                            name="kategori_id"
                            value="<?= (int)$edit_data['kategori_id'] ?>"
                        >
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Nama Kategori</label>
                        <input
                            type="text"
                            name="nama_kategori"
                            class="form-control"
                            placeholder="Masukkan nama kategori"
                            required
                            value="<?= htmlspecialchars(
                                $_POST['nama_kategori']
                                ?? $edit_data['nama_kategori']
                                ?? ''
                            ) ?>"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Keterangan</label>
                        <textarea
                            name="keterangan"
                            class="form-control"
                            rows="4"
                            placeholder="Masukkan keterangan kategori"
                        ><?= htmlspecialchars(
                            $_POST['keterangan']
                            ?? $edit_data['keterangan']
                            ?? ''
                        ) ?></textarea>
                    </div>

                    <?php if ($edit_data): ?>
                        <button type="submit" name="edit" class="btn btn-simpan">
                            <i class="bi bi-check-circle me-1"></i>
                            Simpan Perubahan
                        </button>

                        <a href="kategori.php" class="btn btn-light border btn-batal">
                            Batal
                        </a>
                    <?php else: ?>
                        <button type="submit" name="tambah" class="btn btn-simpan">
                            <i class="bi bi-plus-circle me-1"></i>
                            Tambah Kategori
                        </button>
                    <?php endif; ?>

                </form>
            </div>
        </div>

        <!-- TABEL KATEGORI -->
        <div class="col-12 col-lg-8">
            <div class="content-card">

                <div class="mb-3">
                    <h5 class="fw-bold mb-1">Daftar Kategori</h5>
                    <div class="muted">
                        Data kategori dan jumlah alat yang terdaftar.
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Kategori</th>
                                <th>Keterangan</th>
                                <th>Jumlah Alat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($query && mysqli_num_rows($query) > 0): ?>
                            <?php $no = 1; ?>

                            <?php while ($k = mysqli_fetch_assoc($query)): ?>
                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td class="fw-semibold">
                                        <?= htmlspecialchars($k['nama_kategori']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($k['keterangan'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <span class="badge-jumlah">
                                            <?= (int)$k['jumlah_alat'] ?> alat
                                        </span>
                                    </td>

                                    <td>
                                        <div class="d-flex gap-2">
                                            <a
                                                href="kategori.php?edit=<?= (int)$k['kategori_id'] ?>"
                                                class="btn-aksi btn-edit"
                                                title="Edit kategori"
                                            >
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <form
                                                method="POST"
                                                onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="kategori_id"
                                                    value="<?= (int)$k['kategori_id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="hapus"
                                                    class="btn-aksi btn-hapus"
                                                    title="Hapus kategori"
                                                >
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center empty-state">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                    <div class="fw-bold">Belum ada kategori</div>
                                    <div class="muted">
                                        Tambahkan kategori menggunakan formulir di samping.
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <div class="text-center muted mt-4">
        &copy; <?= date('Y') ?> CampRent - Sistem Peminjaman Alat
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
