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

// AMBIL ID ALAT
$alat_id = (int)($_GET['id'] ?? 0);

if ($alat_id <= 0) {
    header("Location: alat.php");
    exit;
}

// AMBIL DATA ALAT
$stmt = mysqli_prepare(
    $koneksi,
    "SELECT * FROM t_alat WHERE alat_id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $alat_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$alat = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$alat) {
    header("Location: alat.php?pesan=gagal");
    exit;
}

// DATA AWAL FORM
$kode_alat = $alat['kode_alat'];
$nama_alat = $alat['nama_alat'];
$kategori_id = $alat['kategori_id'];
$merk = $alat['merk'] ?? '';
$harga_sewa = $alat['harga_sewa'];
$deskripsi = $alat['deskripsi'] ?? '';
$stok = $alat['stok'];
$kondisi = $alat['kondisi'] ?? 'Baik';
$foto_lama = $alat['foto'] ?? '';

// PROSES UPDATE
if (isset($_POST['simpan'])) {
    $kode_alat = trim($_POST['kode_alat'] ?? '');
    $nama_alat = trim($_POST['nama_alat'] ?? '');
    $kategori_id = (int)($_POST['kategori_id'] ?? 0);
    $merk = trim($_POST['merk'] ?? '');
    $harga_sewa = (float)($_POST['harga_sewa'] ?? 0);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $stok = (int)($_POST['stok'] ?? 0);
    $kondisi = trim($_POST['kondisi'] ?? '');

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

        $hasil = mysqli_stmt_get_result($stmt);
        $kategori_ada = mysqli_num_rows($hasil) > 0;

        mysqli_stmt_close($stmt);

        if (!$kategori_ada) {
            $error = "Kategori tidak ditemukan.";
        } else {
            // CEK KODE ALAT DUPLIKAT
            $stmt = mysqli_prepare(
                $koneksi,
                "SELECT alat_id FROM t_alat
                 WHERE kode_alat = ? AND alat_id != ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "si",
                $kode_alat,
                $alat_id
            );

            mysqli_stmt_execute($stmt);
            $hasil = mysqli_stmt_get_result($stmt);
            $kode_ada = mysqli_num_rows($hasil) > 0;

            mysqli_stmt_close($stmt);

            if ($kode_ada) {
                $error = "Kode alat sudah digunakan alat lain.";
            } else {
                $foto_baru = $foto_lama;
                $folder = "../uploads/alat/";
                $nama_file_baru = "";

                // UPLOAD FOTO BARU JIKA DIPILIH
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

                            $nama_file_baru = uniqid('alat_', true)
                                . '.' . $jenis[$mime];

                            if (
                                move_uploaded_file(
                                    $_FILES['foto']['tmp_name'],
                                    $folder . $nama_file_baru
                                )
                            ) {
                                $foto_baru = $nama_file_baru;
                            } else {
                                $error = "Foto gagal disimpan.";
                            }
                        }
                    }
                }

                // SIMPAN PERUBAHAN
                if ($error === '') {
                    $stmt = mysqli_prepare(
                        $koneksi,
                        "UPDATE t_alat SET
                            kategori_id = ?,
                            kode_alat = ?,
                            nama_alat = ?,
                            merk = ?,
                            harga_sewa = ?,
                            deskripsi = ?,
                            stok = ?,
                            kondisi = ?,
                            foto = ?
                         WHERE alat_id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "isssdsissi",
                        $kategori_id,
                        $kode_alat,
                        $nama_alat,
                        $merk,
                        $harga_sewa,
                        $deskripsi,
                        $stok,
                        $kondisi,
                        $foto_baru,
                        $alat_id
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        // HAPUS FOTO LAMA JIKA FOTO DIGANTI
                        if (
                            $nama_file_baru !== '' &&
                            $foto_lama !== ''
                        ) {
                            $path_foto_lama = $folder
                                . basename($foto_lama);

                            if (is_file($path_foto_lama)) {
                                unlink($path_foto_lama);
                            }
                        }

                        // CATAT AKTIVITAS
                        $user_id = (int)$_SESSION['user_id'];
                        $aktivitas = "Mengubah data alat: " . $nama_alat;

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

                        header("Location: alat.php?pesan=edit");
                        exit;
                    } else {
                        $error = "Data gagal diperbarui: "
                            . mysqli_stmt_error($stmt);
                        mysqli_stmt_close($stmt);
                    }
                }
            }
        }
    }
}

// AMBIL KATEGORI
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

    <title>Edit Alat - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <style>
        body {
            background: #f0f5fa;
            color: #102a43;
            font-family: Arial, sans-serif;
        }

        .card {
            border-radius: 14px;
            overflow: hidden;
        }

        .card-header {
            background: #102a43;
        }

        .form-control,
        .form-select {
            border-radius: 8px;
            padding: 10px 12px;
        }

        .btn-simpan {
            background: #176b78;
            color: white;
        }

        .btn-simpan:hover {
            background: #12304a;
            color: white;
        }

        .foto-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
    </style>
</head>

<body>

<div class="container py-4">

    <div class="card border-0 shadow-sm mx-auto"
         style="max-width: 800px;">

        <div class="card-header text-white p-3">
            <h4 class="mb-0">Edit Data Alat</h4>
        </div>

        <div class="card-body p-4">

            <?php if ($error !== '') { ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php } ?>

            <form method="POST" enctype="multipart/form-data">

                <div class="mb-3">
                    <label class="form-label">Kode Alat</label>
                    <input
                        type="text"
                        name="kode_alat"
                        class="form-control"
                        value="<?= htmlspecialchars($kode_alat) ?>"
                        required
                    >
                </div>

                <div class="mb-3">
                    <label class="form-label">Nama Alat</label>
                    <input
                        type="text"
                        name="nama_alat"
                        class="form-control"
                        value="<?= htmlspecialchars($nama_alat) ?>"
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
                                    $kategori_id == $k['kategori_id']
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
                        value="<?= htmlspecialchars($merk) ?>"
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
                            value="<?= htmlspecialchars((string)$harga_sewa) ?>"
                            required
                        >
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Deskripsi</label>
                    <textarea
                        name="deskripsi"
                        class="form-control"
                        rows="3"
                    ><?= htmlspecialchars($deskripsi) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Stok</label>
                    <input
                        type="number"
                        name="stok"
                        class="form-control"
                        min="0"
                        value="<?= htmlspecialchars((string)$stok) ?>"
                        required
                    >
                </div>

                <!-- KONDISI ALAT -->
                <div class="mb-3">
                    <label class="form-label">Kondisi</label>

                    <select name="kondisi" class="form-select" required>
                        <option value="Baik"
                            <?= $kondisi === 'Baik' ? 'selected' : '' ?>>
                            Baik
                        </option>

                        <option value="Rusak"
                            <?= $kondisi === 'Rusak' ? 'selected' : '' ?>>
                            Rusak
                        </option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Foto Alat Saat Ini</label>
                    <div class="mb-2">
                        <?php
                        $lokasi_foto = "../uploads/alat/"
                            . basename($foto_lama);
                        ?>

                        <?php if (
                            $foto_lama !== '' &&
                            is_file($lokasi_foto)
                        ) { ?>
                            <img
                                src="<?= htmlspecialchars($lokasi_foto) ?>"
                                class="foto-preview"
                                alt="Foto alat"
                            >
                        <?php } else { ?>
                            <p class="text-secondary">
                                Belum ada foto alat.
                            </p>
                        <?php } ?>
                    </div>

                    <input
                        type="file"
                        name="foto"
                        class="form-control"
                        accept=".jpg,.jpeg,.png,.webp"
                    >

                    <small class="text-secondary">
                        Kosongkan jika tidak ingin mengganti foto.
                        JPG, PNG, atau WEBP, maksimal 2 MB.
                    </small>
                </div>

                <div class="d-flex gap-2 mt-4">
                    <button type="submit"
                            name="simpan"
                            class="btn btn-simpan">
                        Simpan Perubahan
                    </button>

                    <a href="alat.php" class="btn btn-secondary">
                        Kembali
                    </a>
                </div>

            </form>

        </div>
    </div>
</div>

</body>
</html>