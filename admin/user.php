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

// FUNGSI CATAT AKTIVITAS
function catatAktivitas($koneksi, $user_id, $aktivitas) {
    $stmt = mysqli_prepare(
        $koneksi,
        "INSERT INTO t_log_aktivitas (user_id, aktivitas) VALUES (?, ?)"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "is", $user_id, $aktivitas);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

// TAMBAH USER
if (isset($_POST['tambah'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_status = (int) ($_POST['user_status'] ?? 0);

    if (
        $username === '' ||
        $password === '' ||
        !in_array($user_status, [1, 2], true)
    ) {
        $error = "Semua data wajib diisi dengan benar.";
    } elseif (strlen($password) < 6) {
        $error = "Password minimal 6 karakter.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT user_id FROM t_user WHERE username = ?"
        );
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        $ada = mysqli_num_rows($hasil) > 0;
        mysqli_stmt_close($stmt);

        if ($ada) {
            $error = "Username sudah digunakan.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = mysqli_prepare(
                $koneksi,
                "INSERT INTO t_user
                (username, password, user_status, status)
                VALUES (?, ?, ?, 'aktif')"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssi",
                $username,
                $password_hash,
                $user_status
            );

            if (mysqli_stmt_execute($stmt)) {
                catatAktivitas(
                    $koneksi,
                    (int) $_SESSION['user_id'],
                    "Menambahkan akun: " . $username
                );

                mysqli_stmt_close($stmt);
                header("Location: user.php?pesan=tambah");
                exit;
            } else {
                $error = "Akun gagal ditambahkan.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// EDIT USER
if (isset($_POST['edit'])) {
    $id = (int) ($_POST['user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $user_status = (int) ($_POST['user_status'] ?? 0);

    if (
        $id <= 0 ||
        $username === '' ||
        !in_array($user_status, [1, 2], true)
    ) {
        $error = "Data akun tidak valid.";
    } elseif (
        $id === (int) $_SESSION['user_id'] &&
        $user_status !== 1
    ) {
        $error = "Kamu tidak dapat mengubah akun sendiri menjadi petugas.";
    } elseif ($password !== '' && strlen($password) < 6) {
        $error = "Password baru minimal 6 karakter.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT user_id FROM t_user
             WHERE username = ? AND user_id != ?"
        );

        mysqli_stmt_bind_param($stmt, "si", $username, $id);
        mysqli_stmt_execute($stmt);
        $hasil = mysqli_stmt_get_result($stmt);
        $nama_dipakai = mysqli_num_rows($hasil) > 0;
        mysqli_stmt_close($stmt);

        if ($nama_dipakai) {
            $error = "Username sudah digunakan akun lain.";
        } else {
            if ($password !== '') {
                $password_hash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = mysqli_prepare(
                    $koneksi,
                    "UPDATE t_user
                     SET username = ?, password = ?, user_status = ?
                     WHERE user_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "ssii",
                    $username,
                    $password_hash,
                    $user_status,
                    $id
                );
            } else {
                $stmt = mysqli_prepare(
                    $koneksi,
                    "UPDATE t_user
                     SET username = ?, user_status = ?
                     WHERE user_id = ?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sii",
                    $username,
                    $user_status,
                    $id
                );
            }

            if (mysqli_stmt_execute($stmt)) {
                catatAktivitas(
                    $koneksi,
                    (int) $_SESSION['user_id'],
                    "Mengedit akun: " . $username
                );

                if ($id === (int) $_SESSION['user_id']) {
                    $_SESSION['username'] = $username;
                }

                mysqli_stmt_close($stmt);
                header("Location: user.php?pesan=edit");
                exit;
            } else {
                $error = "Akun gagal diperbarui.";
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// UBAH STATUS AKUN
if (isset($_POST['ubah_status'])) {
    $id = (int) ($_POST['user_id'] ?? 0);
    $status_baru = $_POST['status_baru'] ?? '';

    if ($id <= 0) {
        $error = "Akun tidak valid.";
    } elseif ($id === (int) $_SESSION['user_id']) {
        $error = "Kamu tidak dapat menonaktifkan akun sendiri.";
    } elseif (!in_array($status_baru, ['aktif', 'nonaktif'], true)) {
        $error = "Status akun tidak valid.";
    } else {
        $stmt = mysqli_prepare(
            $koneksi,
            "UPDATE t_user SET status = ? WHERE user_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "si", $status_baru, $id);

        if (mysqli_stmt_execute($stmt)) {
            catatAktivitas(
                $koneksi,
                (int) $_SESSION['user_id'],
                "Mengubah status akun ID " . $id . " menjadi " . $status_baru
            );

            mysqli_stmt_close($stmt);
            header("Location: user.php?pesan=status");
            exit;
        } else {
            $error = "Status akun gagal diubah.";
            mysqli_stmt_close($stmt);
        }
    }
}

// DATA UNTUK EDIT
$edit_data = null;

if (isset($_GET['edit'])) {
    $id_edit = (int) $_GET['edit'];

    $stmt = mysqli_prepare(
        $koneksi,
        "SELECT user_id, username, user_status, status
         FROM t_user WHERE user_id = ?"
    );

    mysqli_stmt_bind_param($stmt, "i", $id_edit);
    mysqli_stmt_execute($stmt);
    $hasil = mysqli_stmt_get_result($stmt);
    $edit_data = mysqli_fetch_assoc($hasil);
    mysqli_stmt_close($stmt);
}

// DAFTAR USER
$query = mysqli_query(
    $koneksi,
    "SELECT user_id, username, user_status, status, created_at
     FROM t_user
     ORDER BY user_id DESC"
);

$pesan = "";

if (isset($_GET['pesan'])) {
    if ($_GET['pesan'] === 'tambah') {
        $pesan = "Akun berhasil ditambahkan.";
    } elseif ($_GET['pesan'] === 'edit') {
        $pesan = "Akun berhasil diperbarui.";
    } elseif ($_GET['pesan'] === 'status') {
        $pesan = "Status akun berhasil diubah.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Kelola User - CampRent</title>

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

        .form-label {
            font-size: 13px;
            font-weight: bold;
            color: #334e68;
        }

        .form-control,
        .form-select {
            font-size: 13px;
            padding: 10px 12px;
            border-radius: 8px;
        }

        .btn {
            border-radius: 8px;
            font-size: 13px;
        }

        .btn-tambah {
            background: #137f89;
            color: white;
            border: none;
        }

        .btn-tambah:hover {
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

        .badge {
            font-weight: 500;
            padding: 6px 9px;
        }

        .alert {
            border-radius: 10px;
            font-size: 13px;
        }

        .footer {
            text-align: center;
            color: #718096;
            font-size: 12px;
            padding: 10px;
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

            .topbar {
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

    <a href="user.php" class="active">
        <i class="bi bi-people-fill"></i> Kelola User
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

    <div class="topbar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="page-title">Kelola User</div>
                <div class="muted">
                    Kelola akun admin dan petugas CampRent.
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

    <?php if ($pesan !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-2"></i>
            <?= htmlspecialchars($pesan) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close"
                    data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- FORM TAMBAH / EDIT -->
        <div class="col-lg-4">
            <div class="content-card">

                <div class="d-flex align-items-center gap-2 mb-4">
                    <i class="bi <?= $edit_data ? 'bi-pencil-square' : 'bi-person-plus-fill' ?>"
                       style="font-size:25px;color:#137f89;"></i>

                    <div>
                        <div class="section-title">
                            <?= $edit_data ? 'Edit Akun' : 'Tambah Akun' ?>
                        </div>
                        <div class="muted">
                            Isi informasi akun dengan benar.
                        </div>
                    </div>
                </div>

                <form method="POST">

                    <?php if ($edit_data): ?>
                        <input type="hidden" name="user_id"
                               value="<?= (int) $edit_data['user_id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text"
                               name="username"
                               class="form-control"
                               maxlength="50"
                               required
                               value="<?= htmlspecialchars(
                                   $_POST['username']
                                   ?? $edit_data['username']
                                   ?? ''
                               ) ?>"
                               placeholder="Masukkan username">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>

                        <input type="password"
                               name="password"
                               class="form-control"
                               <?= $edit_data ? '' : 'required' ?>
                               minlength="6"
                               autocomplete="new-password"
                               placeholder="<?= $edit_data
                                   ? 'Kosongkan jika tidak diubah'
                                   : 'Minimal 6 karakter' ?>">

                        <?php if ($edit_data): ?>
                            <small class="muted">
                                Kosongkan jika tidak ingin mengganti password.
                            </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Role</label>

                        <?php
                        $role = $_POST['user_status']
                            ?? $edit_data['user_status']
                            ?? 2;
                        ?>

                        <select name="user_status"
                                class="form-select"
                                required>
                            <option value="1"
                                <?= (int) $role === 1 ? 'selected' : '' ?>>
                                Admin
                            </option>

                            <option value="2"
                                <?= (int) $role === 2 ? 'selected' : '' ?>>
                                Petugas
                            </option>
                        </select>
                    </div>

                    <?php if ($edit_data): ?>
                        <button type="submit"
                                name="edit"
                                class="btn btn-warning">
                            <i class="bi bi-save me-1"></i>
                            Simpan Perubahan
                        </button>

                        <a href="user.php" class="btn btn-secondary">
                            Batal
                        </a>
                    <?php else: ?>
                        <button type="submit"
                                name="tambah"
                                class="btn btn-tambah">
                            <i class="bi bi-plus-circle me-1"></i>
                            Tambah Akun
                        </button>
                    <?php endif; ?>

                </form>
            </div>
        </div>

        <!-- TABEL DAFTAR USER -->
        <div class="col-lg-8">
            <div class="content-card">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div>
                        <div class="section-title">Daftar Akun</div>
                        <div class="muted">
                            Data akun administrator dan petugas.
                        </div>
                    </div>

                    <span class="badge rounded-pill"
                          style="background:#e1f4f5;color:#137f89;">
                        <i class="bi bi-people-fill me-1"></i>
                        <?= mysqli_num_rows($query) ?> akun
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($query && mysqli_num_rows($query) > 0): ?>
                            <?php $no = 1; ?>

                            <?php while ($u = mysqli_fetch_assoc($query)): ?>
                                <tr>
                                    <td><?= $no++ ?></td>

                                    <td>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($u['username']) ?>
                                        </div>

                                        <?php if (
                                            (int) $u['user_id'] ===
                                            (int) $_SESSION['user_id']
                                        ): ?>
                                            <span class="badge text-bg-info">
                                                Akunmu
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ((int) $u['user_status'] === 1): ?>
                                            <span class="badge text-bg-primary">
                                                <i class="bi bi-shield-fill me-1"></i>
                                                Admin
                                            </span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary">
                                                <i class="bi bi-person-fill me-1"></i>
                                                Petugas
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?php if ($u['status'] === 'aktif'): ?>
                                            <span class="badge text-bg-success">
                                                <i class="bi bi-check-circle me-1"></i>
                                                Aktif
                                            </span>
                                        <?php else: ?>
                                            <span class="badge text-bg-danger">
                                                <i class="bi bi-x-circle me-1"></i>
                                                Nonaktif
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($u['created_at'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <div class="d-flex gap-1">

                                            <a href="user.php?edit=<?= (int) $u['user_id'] ?>"
                                               class="btn btn-warning btn-sm"
                                               title="Edit akun">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>

                                            <?php if (
                                                (int) $u['user_id'] !==
                                                (int) $_SESSION['user_id']
                                            ): ?>

                                                <form method="POST"
                                                      onsubmit="return confirm('Yakin ingin mengubah status akun ini?')">

                                                    <input type="hidden"
                                                           name="user_id"
                                                           value="<?= (int) $u['user_id'] ?>">

                                                    <input type="hidden"
                                                           name="status_baru"
                                                           value="<?= $u['status'] === 'aktif'
                                                               ? 'nonaktif'
                                                               : 'aktif' ?>">

                                                    <button type="submit"
                                                            name="ubah_status"
                                                            class="btn <?= $u['status'] === 'aktif'
                                                                ? 'btn-danger'
                                                                : 'btn-success' ?> btn-sm"
                                                            title="<?= $u['status'] === 'aktif'
                                                                ? 'Nonaktifkan akun'
                                                                : 'Aktifkan akun' ?>">

                                                        <i class="bi <?= $u['status'] === 'aktif'
                                                            ? 'bi-person-x-fill'
                                                            : 'bi-person-check-fill' ?>"></i>
                                                    </button>
                                                </form>

                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="6"
                                    class="text-center text-secondary py-4">
                                    <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                                    Belum ada data akun.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>

    <div class="footer">
        &copy; <?= date('Y') ?> CampRent — Sistem Peminjaman Alat
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>