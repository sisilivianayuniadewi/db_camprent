<?php
session_start();
require_once "../koneksi.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_status']) ||
    (int)$_SESSION['user_status'] !== 1
) {
    header("Location: ../index.php");
    exit;
}

$error = "";

// GENERATE KODE ALAT OTOMATIS
$q_kode = mysqli_query(
    $koneksi,
    "SELECT kode_alat FROM t_alat ORDER BY alat_id DESC LIMIT 1"
);

$auto_kode = "ALT001";

if ($q_kode && mysqli_num_rows($q_kode) > 0) {
    $d_kode = mysqli_fetch_assoc($q_kode);
    $last_kode = $d_kode['kode_alat'];

    $number = (int)substr($last_kode, 3);
    $next_number = $number + 1;

    $auto_kode = "ALT" . str_pad(
        $next_number,
        3,
        "0",
        STR_PAD_LEFT
    );
}

// PROSES SIMPAN
if (isset($_POST['simpan'])) {
    $kode_alat = trim($_POST['kode_alat'] ?? '');
    $nama_alat = trim($_POST['nama_alat'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $merk = trim($_POST['merk'] ?? '');
    $harga_sewa = (float)($_POST['harga_sewa'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $stok = (int)($_POST['stok'] ?? 0);
    $kondisi = trim($_POST['kondisi'] ?? '');

    // VALIDASI DATA
    if (
        $kode_alat === '' ||
        $nama_alat === '' ||
        $kategori_id <= 0 ||
        $harga_sewa < 0 ||
        $stok < 0 ||
        !in_array($kondisi, ['Baik', 'Rusak'], true)
    ) {
        $error = "Lengkapi data dengan benar.";
    } else {
        // CEK KATEGORI
        $stmt = mysqli_prepare(
            $koneksi,
            "SELECT kategori_id FROM t_kategori WHERE kategori_id = ?"
        );

        mysqli_stmt_bind_param($stmt, "i", $kategori_id);
        mysqli_stmt_execute($stmt);

        $hasil_kategori = mysqli_stmt_get_result($stmt);
        $kategori_ada = mysqli_num_rows($hasil_kategori) > 0;

        mysqli_stmt_close($stmt);

        if (!$kategori_ada) {
            $error = "Kategori tidak ditemukan.";
        } else {
            // CEK KODE ALAT
            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT alat_id FROM t_alat WHERE kode_alat = ?"
            );

            mysqli_stmt_bind_param($stmt, "s", $kode_alat);
            mysqli_stmt_execute($stmt);

            $hasil_kode = mysqli_stmt_get_result($stmt);
            $kode_ada = mysqli_num_rows($hasil_kode) > 0;

            mysqli_stmt_close($stmt);

            if ($kode_ada) {
                $error = "Kode alat sudah digunakan.";
            } else {
                $foto = null;
                $folder = "../uploads/alat/";

                // UPLOAD FOTO
                if (
                    isset($_FILES['foto']) &&
                    $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                        $error = "Foto gagal diunggah.";
                    } elseif (
                        $_FILES['foto']['size'] > 2 * 1024 * 1024
                    ) {
                        $error = "Ukuran foto maksimal 2 MB.";
                    } else {
                        $info = getimagesize(
                            $_FILES['foto']['tmp_name']
                        );

                        $mime = $info['mime'] ?? '';

                        $jenis = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/webp' => 'webp'
                        ];

                        if (!$info || !isset($jenis[$mime])) {
                            $error = "Foto harus JPG, PNG, atau WEBP.";
                        } else {
                            if (!is_dir($folder)) {
                                mkdir($folder, 0755, true);
                            }

                            $nama_file = uniqid('alat_', true)
                                . '.' . $jenis[$mime];

                            if (
                                move_uploaded_file(
                                    $_FILES['foto']['tmp_name'],
                                    $folder . $nama_file
                                )
                            ) {
                                $foto = $nama_file;
                            } else {
                                $error = "Foto gagal disimpan.";
                            }
                        }
                    }
                }

                // SIMPAN DATA ALAT
                if ($error === '') {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "INSERT INTO t_alat
                        (
                            kategori_id,
                            kode_alat,
                            nama_alat,
                            merk,
                            harga_sewa,
                            deskripsi,
                            stok,
                            kondisi,
                            foto
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        $error = "Query penyimpanan gagal dibuat.";
                    } else {
                        // TIPE DATA SUDAH DIPERBAIKI
                        mysqli_stmt_bind_param(
                            $stmt,
                            "isssdsiss",
                            $kategori_id,
                            $kode_alat,
                            $nama_alat,
                            $merk,
                            $harga_sewa,
                            $deskripsi,
                            $stok,
                            $kondisi,
                            $foto
                        );

                        if (mysqli_stmt_execute($stmt)) {
                            // CATAT AKTIVITAS
                            $user_id = (int)$_SESSION['user_id'];
                            $aktivitas = "Menambahkan alat: " . $nama_alat;

                            $log = mysqli_prepare(
                                $koneksi,
                                "INSERT INTO t_log_aktivitas
                                (user_id, aktivitas) VALUES (?, ?)"
                            );

                            if ($log) {
                                mysqli_stmt_bind_param(
                                    $log,
                                    "is",
                                    $user_id,
                                    $aktivitas
                                );

                                mysqli_stmt_execute($log);
                                mysqli_stmt_close($log);
                            }

                            mysqli_stmt_close($stmt);

                            header("Location: alat.php?pesan=tambah");
                            exit;
                        } else {
                            $error = "Data gagal disimpan: "
                                . mysqli_stmt_error($stmt);
                        }

                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
}

// AMBIL DATA KATEGORI
$kategori = mysqli_query(
    $koneksi,
    "SELECT * FROM t_kategori ORDER BY nama_kategori ASC"
);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Tambah Alat - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
</head>

<body class="bg-light">

<div class="container py-4">
    <div class="card border-0 shadow-sm mx-auto"
         style="max-width: 800px;">

        <div class="card-header text-white p-3"
             style="background:#102a43;">
            <h4 class="mb-0">Tambah Alat Camping</h4>
        </div>

        <div class="card-body p-4">

            <?php if ($error !== '') { ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php } ?>

            <?php if (
                !$kategori ||
                mysqli_num_rows($kategori) === 0
            ) { ?>

                <div class="alert alert-warning">
                    Belum ada kategori. Tambahkan kategori terlebih dahulu.
                    <a href="kategori.php">Kelola kategori</a>.
                </div>

            <?php } else { ?>

            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">Kode Alat</label>

                    <input
                        type="text"
                        name="kode_alat"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $_POST['kode_alat'] ?? $auto_kode
                        ) ?>"
                        required
                        readonly
                    >

                    <small class="text-secondary">
                        Kode alat dibuat otomatis oleh sistem.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama Alat</label>

                    <input
                        type="text"
                        name="nama_alat"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $_POST['nama_alat'] ?? ''
                        ) ?>"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Kategori</label>

                    <select name="kategori_id"
                            class="form-select" required>

                        <option value="">-- Pilih Kategori --</option>

                        <?php while ($k = mysqli_fetch_assoc($kategori)) { ?>
                            <option
                                value="<?= (int)$k['kategori_id'] ?>"
                                <?= (
                                    ($_POST['kategori_id'] ?? '') ==
                                    $k['kategori_id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($k['nama_kategori']) ?>
                            </option>
                        <?php } ?>

                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Merek</label>

                    <input
                        type="text"
                        name="merk"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $_POST['merk'] ?? ''
                        ) ?>"
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Harga Sewa per Hari</label>

                    <div class="input-group">
                        <span class="input-group-text">Rp</span>

                        <input
                            type="number"
                            name="harga_sewa"
                            class="form-control"
                            min="0"
                            step="500"
                            placeholder="Contoh: 15000"
                            value="<?= htmlspecialchars(
                                $_POST['harga_sewa'] ?? '0'
                            ) ?>"
                            required
                        >
                    </div>

                    <small class="text-secondary">
                        Tarif sewa alat untuk durasi satu hari.
                    </small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>

                    <textarea
                        name="deskripsi"
                        class="form-control"
                        rows="3"
                    ><?= htmlspecialchars(
                        $_POST['deskripsi'] ?? ''
                    ) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Stok</label>

                    <input
                        type="number"
                        name="stok"
                        class="form-control"
                        min="0"
                        value="<?= htmlspecialchars(
                            $_POST['stok'] ?? '0'
                        ) ?>"
                        required
                    >
                </div>

                <!-- PILIHAN KONDISI -->
                <div class="mb-3">
                    <label class="form-label">Kondisi</label>

                    <select name="kondisi" class="form-select" required>
                        <option value="">-- Pilih Kondisi --</option>

                        <option
                            value="Baik"
                            <?= (
                                ($_POST['kondisi'] ?? 'Baik') === 'Baik'
                            ) ? 'selected' : '' ?>
                        >
                            Baik
                        </option>

                        <option
                            value="Rusak"
                            <?= (
                                ($_POST['kondisi'] ?? '') === 'Rusak'
                            ) ? 'selected' : '' ?>
                        >
                            Rusak
                        </option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Foto Alat (opsional)</label>

                    <input
                        type="file"
                        name="foto"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small class="text-secondary">
                        Format JPG, PNG, WEBP. Maksimal 2 MB.
                    </small>
                </div>

                <button type="submit"
                        name="simpan"
                        class="btn btn-success">
                    Simpan Alat
                </button>

                <a href="alat.php" class="btn btn-secondary">
                    Kembali
                </a>

            </form>

            <?php } ?>

        </div>
    </div>
</div>

</body>
</html>