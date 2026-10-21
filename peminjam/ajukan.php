```php
<?php
session_start();
include "../koneksi.php";

/* CEK LOGIN PEMINJAM */
if (
    !isset($_SESSION['user_id'], $_SESSION['user_status']) ||
    (int) $_SESSION['user_status'] !== 3
) {
    header("Location: ../index.php");
    exit();
}

$error = "";
$pesan = "";

$nama_lengkap = "";
$nik = "";
$no_telepon = "";
$email = "";
$alamat = "";
$tanggal_pinjam = date('Y-m-d');
$tanggal_rencana_kembali = "";
$catatan = "";

/* FUNGSI */
function e($nilai)
{
    return htmlspecialchars((string)$nilai, ENT_QUOTES, 'UTF-8');
}

function rupiah($nilai)
{
    return "Rp" . number_format((float)$nilai, 0, ',', '.');
}

/* AMBIL DATA ALAT YANG TERSEDIA */
$daftar_alat = [];

$query_alat = mysqli_query($koneksi, "
    SELECT alat_id, nama_alat, stok, harga_sewa, kondisi
    FROM t_alat
    WHERE stok > 0 AND kondisi = 'Baik'
    ORDER BY nama_alat ASC
");

if ($query_alat) {
    while ($row = mysqli_fetch_assoc($query_alat)) {
        $daftar_alat[] = $row;
    }
}

/* PROSES PENGAJUAN */
if (isset($_POST['ajukan'])) {

    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $no_telepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? '';
    $tanggal_rencana_kembali = $_POST['tanggal_rencana_kembali'] ?? '';
    $catatan = trim($_POST['catatan'] ?? '');

    $alat_id_array = $_POST['alat_id'] ?? [];
    $jumlah_array = $_POST['jumlah'] ?? [];

    /* VALIDASI DATA PEMINJAM */
    if (
        $nama_lengkap === '' ||
        $nik === '' ||
        $no_telepon === '' ||
        $alamat === '' ||
        $tanggal_pinjam === '' ||
        $tanggal_rencana_kembali === ''
    ) {
        $error = "Lengkapi semua data yang wajib diisi.";
    } elseif (!preg_match('/^[0-9]{16}$/', $nik)) {
        $error = "NIK harus terdiri dari 16 angka.";
    } elseif (!preg_match('/^[0-9+\-\s]{8,20}$/', $no_telepon)) {
        $error = "Nomor telepon tidak valid.";
    } elseif (
        $email !== '' &&
        !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $error = "Format email tidak valid.";
    } elseif (
        $tanggal_pinjam < date('Y-m-d') ||
        $tanggal_rencana_kembali <= $tanggal_pinjam
    ) {
        $error = "Tanggal kembali harus setelah tanggal pinjam.";
    } elseif (
        !is_array($alat_id_array) ||
        count($alat_id_array) === 0 ||
        !is_array($jumlah_array)
    ) {
        $error = "Pilih minimal satu alat.";
    } else {

        $alat_id_array = array_map('intval', $alat_id_array);

        if (
            in_array(0, $alat_id_array, true) ||
            count($alat_id_array) !== count(array_unique($alat_id_array))
        ) {
            $error = "Pilihan alat tidak valid.";
        }

        /* HITUNG DURASI SEWA */
        $tgl1 = new DateTime($tanggal_pinjam);
        $tgl2 = new DateTime($tanggal_rencana_kembali);
        $durasi = (int)$tgl1->diff($tgl2)->days;

        if ($durasi < 1) {
            $error = "Durasi peminjaman minimal satu hari.";
        }

        $detail = [];
        $total_biaya = 0;

        /*
         * AMBIL HARGA DARI t_alat
         * HITUNG SUBTOTAL DAN TOTAL
         */
        if ($error === "") {

            foreach ($alat_id_array as $alat_id) {

                $jumlah = filter_var(
                    $jumlah_array[$alat_id] ?? null,
                    FILTER_VALIDATE_INT
                );

                if ($jumlah === false || $jumlah < 1) {
                    $error = "Jumlah alat minimal satu.";
                    break;
                }

                $stmt = mysqli_prepare($koneksi, "
                    SELECT nama_alat, stok, harga_sewa, kondisi
                    FROM t_alat
                    WHERE alat_id = ?
                ");

                mysqli_stmt_bind_param($stmt, "i", $alat_id);
                mysqli_stmt_execute($stmt);

                $hasil = mysqli_stmt_get_result($stmt);
                $alat = mysqli_fetch_assoc($hasil);

                mysqli_stmt_close($stmt);

                if (!$alat) {
                    $error = "Data alat tidak ditemukan.";
                    break;
                }

                if ($alat['kondisi'] !== 'Baik') {
                    $error = "Alat " . $alat['nama_alat'] .
                        " tidak dalam kondisi baik.";
                    break;
                }

                if ($jumlah > (int)$alat['stok']) {
                    $error = "Stok alat " . $alat['nama_alat'] .
                        " tidak mencukupi.";
                    break;
                }

                $harga = (float)$alat['harga_sewa'];

                if ($harga < 0) {
                    $error = "Harga sewa alat tidak valid.";
                    break;
                }

                // Subtotal satu jenis alat
                $subtotal = $harga * $jumlah * $durasi;

                // Total seluruh alat
                $total_biaya += $subtotal;

                $detail[] = [
                    'alat_id' => $alat_id,
                    'jumlah' => $jumlah,
                    'subtotal_sewa' => $subtotal
                ];
            }
        }

        /* SIMPAN DATA KE DATABASE */
        if ($error === "") {

            mysqli_begin_transaction($koneksi);

            try {

                /* 1. SIMPAN IDENTITAS PEMINJAM */
                $stmt = mysqli_prepare($koneksi, "
                    INSERT INTO t_peminjam
                    (
                        nama_lengkap,
                        nik,
                        no_telepon,
                        email,
                        alamat
                    )
                    VALUES (?, ?, ?, ?, ?)
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssss",
                    $nama_lengkap,
                    $nik,
                    $no_telepon,
                    $email,
                    $alamat
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }

                $peminjam_id = mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt);

                /* 2. SIMPAN PENGAJUAN
                   total_biaya berada di t_peminjaman
                */
                $kode_peminjaman = "TRX-" . date('Ymd') . "-" .
                    strtoupper(bin2hex(random_bytes(3)));

                $status = "Menunggu Persetujuan";

                $stmt = mysqli_prepare($koneksi, "
                    INSERT INTO t_peminjaman
                    (
                        kode_peminjaman,
                        peminjam_id,
                        tanggal_pinjam,
                        tanggal_rencana_kembali,
                        status,
                        catatan,
                        total_biaya
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");

                mysqli_stmt_bind_param(
                    $stmt,
                    "sissssd",
                    $kode_peminjaman,
                    $peminjam_id,
                    $tanggal_pinjam,
                    $tanggal_rencana_kembali,
                    $status,
                    $catatan,
                    $total_biaya
                );

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }

                $peminjaman_id = mysqli_insert_id($koneksi);
                mysqli_stmt_close($stmt);

                /* 3. SIMPAN DETAIL PEMINJAMAN
                   subtotal_sewa berada di t_detail_peminjaman
                   Tidak memakai kolom harga_sewa di tabel detail
                */
                $stmt = mysqli_prepare($koneksi, "
                    INSERT INTO t_detail_peminjaman
                    (
                        peminjaman_id,
                        alat_id,
                        jumlah,
                        subtotal_sewa
                    )
                    VALUES (?, ?, ?, ?)
                ");

                foreach ($detail as $item) {

                    $id_alat = (int)$item['alat_id'];
                    $jumlah_alat = (int)$item['jumlah'];
                    $subtotal_alat = (float)$item['subtotal_sewa'];

                    mysqli_stmt_bind_param(
                        $stmt,
                        "iiid",
                        $peminjaman_id,
                        $id_alat,
                        $jumlah_alat,
                        $subtotal_alat
                    );

                    if (!mysqli_stmt_execute($stmt)) {
                        throw new Exception(mysqli_stmt_error($stmt));
                    }
                }

                mysqli_stmt_close($stmt);

                /* 4. SIMPAN TRANSAKSI */
                mysqli_commit($koneksi);

                /* 5. NOTIFIKASI DAN PINDAH KE DASHBOARD */
                $_SESSION['pesan_sukses'] =
                    "Pengajuan berhasil dikirim! Kode peminjaman: " .
                    $kode_peminjaman .
                    ". Total biaya: " . rupiah($total_biaya) .
                    ". Menunggu persetujuan petugas.";

                header("Location: dashboard.php");
                exit();

            } catch (Throwable $e) {

                mysqli_rollback($koneksi);

                error_log(
                    "Pengajuan peminjaman gagal: " . $e->getMessage()
                );

                $error = "Pengajuan gagal disimpan: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Peminjaman - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fb;
            font-family: Arial, sans-serif;
            color: #102a43;
        }

        .navbar, .hero {
            background: linear-gradient(135deg, #102a43, #176b78);
        }

        .hero {
            color: white;
            padding: 28px;
            border-radius: 16px;
        }

        .form-card {
            border: none;
            border-radius: 16px;
        }

        .section-title {
            color: #176b78;
            font-weight: bold;
            margin-bottom: 18px;
        }

        .form-control, .form-select {
            border-radius: 9px;
            padding: 10px;
        }

        .btn-camp {
            background: #176b78;
            color: white;
            border: none;
            border-radius: 9px;
        }

        .btn-camp:hover {
            background: #102a43;
            color: white;
        }

        .alat-list {
            max-height: 350px;
            overflow: auto;
        }

        .total-box {
            background: #e8f6f5;
            border: 1px solid #b9e5df;
            border-radius: 12px;
            padding: 18px;
        }

        .total-harga {
            font-size: 26px;
            font-weight: bold;
            color: #176b78;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-dark shadow-sm">
    <div class="container">
        <a href="dashboard.php" class="navbar-brand fw-bold">
            <i class="fa-solid fa-campground me-2"></i>CampRent
        </a>

        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-outline-light btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i>Kembali
            </a>
            <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="hero mb-4">
        <h3 class="fw-bold">
            <i class="fa-solid fa-file-circle-plus me-2"></i>
            Pengajuan Peminjaman
        </h3>
        <p class="mb-0">
            Lengkapi identitas dan pilih alat camping yang ingin dipinjam.
        </p>
    </div>

    <?php if ($error !== ""): ?>
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="ajukan.php" id="formAjukan">
        <div class="card form-card shadow-sm">
            <div class="card-body p-4">
                <div class="row g-4">

                    <div class="col-lg-5">
                        <h5 class="section-title">
                            <i class="fa-solid fa-user me-2"></i>
                            Identitas Peminjam
                        </h5>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Lengkap *</label>
                            <input type="text" name="nama_lengkap" class="form-control"
                                   value="<?= e($nama_lengkap) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">NIK *</label>
                            <input type="text" name="nik" class="form-control"
                                   value="<?= e($nik) ?>" inputmode="numeric"
                                   pattern="[0-9]{16}" maxlength="16" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">No. Telepon *</label>
                            <input type="text" name="no_telepon" class="form-control"
                                   value="<?= e($no_telepon) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= e($email) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Alamat *</label>
                            <textarea name="alamat" class="form-control"
                                      rows="3" required><?= e($alamat) ?></textarea>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <h5 class="section-title">
                            <i class="fa-solid fa-calendar-days me-2"></i>
                            Detail Peminjaman
                        </h5>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Tanggal Pinjam *</label>
                                <input type="date" name="tanggal_pinjam"
                                       class="form-control"
                                       min="<?= date('Y-m-d') ?>"
                                       value="<?= e($tanggal_pinjam) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Rencana Kembali *</label>
                                <input type="date" name="tanggal_rencana_kembali"
                                       class="form-control"
                                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                                       value="<?= e($tanggal_rencana_kembali) ?>" required>
                            </div>
                        </div>

                        <label class="form-label fw-semibold">Pilih Alat *</label>

                        <div class="alat-list border rounded mb-3">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Pilih</th>
                                        <th>Nama Alat</th>
                                        <th>Harga/Hari</th>
                                        <th>Stok</th>
                                        <th>Jumlah</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (count($daftar_alat) > 0): ?>
                                    <?php foreach ($daftar_alat as $alat): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox"
                                                       class="form-check-input alat-check"
                                                       name="alat_id[]"
                                                       value="<?= (int)$alat['alat_id'] ?>">
                                            </td>
                                            <td><?= e($alat['nama_alat']) ?></td>
                                            <td class="text-nowrap">
                                                <?= rupiah($alat['harga_sewa']) ?>
                                                <input type="hidden" class="harga-alat"
                                                       value="<?= (float)$alat['harga_sewa'] ?>">
                                            </td>
                                            <td><?= (int)$alat['stok'] ?></td>
                                            <td style="min-width:85px">
                                                <input type="number"
                                                       class="form-control form-control-sm jumlah-alat"
                                                       name="jumlah[<?= (int)$alat['alat_id'] ?>]"
                                                       value="1" min="1"
                                                       max="<?= (int)$alat['stok'] ?>"
                                                       disabled>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            Tidak ada alat yang tersedia.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="total-box mb-4">
                            <div class="text-secondary small">Durasi Sewa</div>
                            <div class="fw-bold mb-3">
                                <span id="durasi">0</span> hari
                            </div>

                            <div class="text-secondary small">Estimasi Total Biaya</div>
                            <div class="total-harga" id="totalBiaya">Rp0</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Catatan</label>
                            <textarea name="catatan" class="form-control"
                                      rows="2"><?= e($catatan) ?></textarea>
                        </div>

                        <div class="alert alert-info small">
                            Pengajuan menunggu persetujuan petugas. Stok belum dikurangi ketika pengajuan dikirim.
                        </div>

                        <button type="submit" name="ajukan"
                                class="btn btn-camp w-100 py-2 fw-bold">
                            <i class="fa-solid fa-paper-plane me-2"></i>
                            Kirim Pengajuan
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </form>
</div>

<script>
const tanggalPinjam = document.querySelector('[name="tanggal_pinjam"]');
const tanggalKembali = document.querySelector('[name="tanggal_rencana_kembali"]');

function hitungBiaya() {
    let durasi = 0;

    if (tanggalPinjam.value && tanggalKembali.value) {
        const awal = new Date(tanggalPinjam.value + 'T00:00:00');
        const akhir = new Date(tanggalKembali.value + 'T00:00:00');
        durasi = Math.round((akhir - awal) / 86400000);
    }

    durasi = Math.max(durasi, 0);
    document.getElementById('durasi').textContent = durasi;

    let total = 0;

    document.querySelectorAll('.alat-check:checked').forEach(function(cb) {
        const baris = cb.closest('tr');
        const harga = parseFloat(baris.querySelector('.harga-alat').value) || 0;
        const jumlah = parseInt(baris.querySelector('.jumlah-alat').value) || 0;

        total += harga * jumlah * durasi;
    });

    document.getElementById('totalBiaya').textContent =
        'Rp' + Math.round(total).toLocaleString('id-ID');
}

document.querySelectorAll('.alat-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        const jumlah = this.closest('tr').querySelector('.jumlah-alat');
        jumlah.disabled = !this.checked;
        hitungBiaya();
    });
});

document.querySelectorAll('.jumlah-alat').forEach(function(input) {
    input.addEventListener('input', hitungBiaya);
});

function aturTanggalKembali() {
    if (!tanggalPinjam.value) return;

    const tanggal = new Date(tanggalPinjam.value + 'T00:00:00');
    tanggal.setDate(tanggal.getDate() + 1);

    const minTanggal = tanggal.getFullYear() + '-' +
        String(tanggal.getMonth() + 1).padStart(2, '0') + '-' +
        String(tanggal.getDate()).padStart(2, '0');

    tanggalKembali.min = minTanggal;

    if (tanggalKembali.value && tanggalKembali.value < minTanggal) {
        tanggalKembali.value = '';
    }

    hitungBiaya();
}

tanggalPinjam.addEventListener('change', aturTanggalKembali);
tanggalKembali.addEventListener('change', hitungBiaya);

document.getElementById('formAjukan').addEventListener('submit', function(event) {
    if (document.querySelectorAll('.alat-check:checked').length === 0) {
        event.preventDefault();
        alert('Pilih minimal satu alat.');
        return;
    }

    if (
        !tanggalPinjam.value ||
        !tanggalKembali.value ||
        tanggalKembali.value <= tanggalPinjam.value
    ) {
        event.preventDefault();
        alert('Tanggal kembali harus setelah tanggal pinjam.');
        return;
    }

    for (const cb of document.querySelectorAll('.alat-check:checked')) {
        const jumlah = cb.closest('tr').querySelector('.jumlah-alat');

        if (+jumlah.value < 1 || +jumlah.value > +jumlah.max) {
            event.preventDefault();
            alert('Periksa jumlah alat yang dipilih.');
            return;
        }
    }
});

aturTanggalKembali();
hitungBiaya();
</script>

</body>
</html>