
<?php
session_start();
include "../koneksi.php";

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_status']) ||
    (int)$_SESSION['user_status'] !== 2
) {
    header("Location: ../index.php");
    exit;
}

function rupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

// PROSES AKSI PETUGAS
if (isset($_POST['proses'])) {
    $peminjaman_id = (int)($_POST['peminjaman_id'] ?? 0);
    $aksi = $_POST['aksi'] ?? '';

    if (
        $peminjaman_id <= 0 ||
        !in_array($aksi, ['setujui', 'tolak', 'konfirmasi_bayar'], true)
    ) {
        header("Location: peminjaman.php?hasil=" . urlencode("Permintaan tidak valid."));
        exit;
    }

    mysqli_begin_transaction($koneksi);

    try {
        $stmt = mysqli_prepare($koneksi, "
            SELECT status, status_pembayaran
            FROM t_peminjaman
            WHERE peminjaman_id = ?
            FOR UPDATE
        ");
        mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $peminjaman = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$peminjaman) {
            throw new Exception("Data peminjaman tidak ditemukan.");
        }

        // KONFIRMASI PEMBAYARAN
        if ($aksi === 'konfirmasi_bayar') {
            if ($peminjaman['status'] !== 'Dipinjam') {
                throw new Exception("Pembayaran hanya dapat dikonfirmasi jika peminjaman sudah disetujui.");
            }

            if (($peminjaman['status_pembayaran'] ?? 'Belum Dibayar') === 'Lunas') {
                throw new Exception("Pembayaran sudah dikonfirmasi lunas.");
            }

            $stmt = mysqli_prepare($koneksi, "
                UPDATE t_peminjaman
                SET status_pembayaran = 'Lunas'
                WHERE peminjaman_id = ?
                  AND status_pembayaran = 'Belum Dibayar'
            ");
            mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected !== 1) {
                throw new Exception("Status pembayaran gagal diperbarui.");
            }

            mysqli_commit($koneksi);
            header("Location: peminjaman.php?hasil=" . urlencode("Pembayaran berhasil dikonfirmasi LUNAS."));
            exit;
        }

        if ($peminjaman['status'] !== 'Menunggu Persetujuan') {
            throw new Exception("Pengajuan ini sudah pernah diproses.");
        }

        // TOLAK PENGAJUAN
        if ($aksi === 'tolak') {
            $stmt = mysqli_prepare($koneksi, "
                UPDATE t_peminjaman
                SET status = 'Ditolak'
                WHERE peminjaman_id = ?
                  AND status = 'Menunggu Persetujuan'
            ");
            mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected !== 1) {
                throw new Exception("Status gagal diperbarui.");
            }

            mysqli_commit($koneksi);
            header("Location: peminjaman.php?hasil=" . urlencode("Pengajuan berhasil ditolak."));
            exit;
        }

        // AMBIL DETAIL ALAT
        $stmt = mysqli_prepare($koneksi, "
            SELECT alat_id, jumlah
            FROM t_detail_peminjaman
            WHERE peminjaman_id = ?
            FOR UPDATE
        ");
        mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $detail = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $detail[] = $row;
        }
        mysqli_stmt_close($stmt);

        if (empty($detail)) {
            throw new Exception("Detail alat tidak ditemukan.");
        }

        // PERIKSA DAN KURANGI STOK
        foreach ($detail as $item) {
            $alat_id = (int)$item['alat_id'];
            $jumlah = (int)$item['jumlah'];

            if ($alat_id <= 0 || $jumlah <= 0) {
                throw new Exception("Jumlah alat tidak valid.");
            }

            $stmt = mysqli_prepare($koneksi, "
                SELECT stok
                FROM t_alat
                WHERE alat_id = ?
                FOR UPDATE
            ");
            mysqli_stmt_bind_param($stmt, "i", $alat_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $alat = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$alat) {
                throw new Exception("Data alat tidak ditemukan.");
            }

            if ((int)$alat['stok'] < $jumlah) {
                throw new Exception("Stok alat tidak mencukupi.");
            }

            $stmt = mysqli_prepare($koneksi, "
                UPDATE t_alat
                SET stok = stok - ?
                WHERE alat_id = ?
                  AND stok >= ?
            ");
            mysqli_stmt_bind_param($stmt, "iii", $jumlah, $alat_id, $jumlah);
            mysqli_stmt_execute($stmt);
            $affected = mysqli_stmt_affected_rows($stmt);
            mysqli_stmt_close($stmt);

            if ($affected !== 1) {
                throw new Exception("Gagal memperbarui stok alat.");
            }
        }

        // SETUJUI PEMINJAMAN
        $stmt = mysqli_prepare($koneksi, "
            UPDATE t_peminjaman
            SET status = 'Dipinjam',
                status_pembayaran = 'Belum Dibayar'
            WHERE peminjaman_id = ?
              AND status = 'Menunggu Persetujuan'
        ");
        mysqli_stmt_bind_param($stmt, "i", $peminjaman_id);
        mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected !== 1) {
            throw new Exception("Status peminjaman gagal diperbarui.");
        }

        mysqli_commit($koneksi);
        header("Location: peminjaman.php?hasil=" . urlencode("Pengajuan disetujui. Stok berhasil diperbarui."));
        exit;

    } catch (Throwable $e) {
        mysqli_rollback($koneksi);
        header("Location: peminjaman.php?hasil=" . urlencode($e->getMessage()));
        exit;
    }
}

// QUERY DAFTAR PEMINJAMAN
// TOTAL BIAYA DIHITUNG DARI t_alat.harga_sewa
$query = mysqli_query($koneksi, "
    SELECT
        p.peminjaman_id,
        p.kode_peminjaman,
        p.tanggal_pinjam,
        p.tanggal_rencana_kembali,
        p.status,
        COALESCE((
            SELECT SUM(d.jumlah * a.harga_sewa)
            FROM t_detail_peminjaman d
            JOIN t_alat a ON d.alat_id = a.alat_id
            WHERE d.peminjaman_id = p.peminjaman_id
        ), 0) AS total_biaya,
        COALESCE(p.status_pembayaran, 'Belum Dibayar') AS status_pembayaran,
        pm.nama_lengkap,
        (
            SELECT GROUP_CONCAT(
                CONCAT(a.nama_alat, ' (', d.jumlah, ')')
                SEPARATOR ', '
            )
            FROM t_detail_peminjaman d
            JOIN t_alat a ON d.alat_id = a.alat_id
            WHERE d.peminjaman_id = p.peminjaman_id
        ) AS daftar_alat
    FROM t_peminjaman p
    LEFT JOIN t_peminjam pm
        ON p.peminjam_id = pm.peminjam_id
    ORDER BY p.peminjaman_id DESC
");

if (!$query) {
    die("Query gagal: " . htmlspecialchars(mysqli_error($koneksi)));
}

// DETAIL ALAT UNTUK MODAL
$detail_semua = [];

$q_detail = mysqli_query($koneksi, "
    SELECT
        d.peminjaman_id,
        a.nama_alat,
        d.jumlah,
        a.harga_sewa,
        (d.jumlah * a.harga_sewa) AS subtotal_sewa
    FROM t_detail_peminjaman d
    JOIN t_alat a ON d.alat_id = a.alat_id
    ORDER BY d.peminjaman_id DESC
");

if ($q_detail) {
    while ($d = mysqli_fetch_assoc($q_detail)) {
        $detail_semua[(int)$d['peminjaman_id']][] = $d;
    }
}

$rows = [];
while ($row = mysqli_fetch_assoc($query)) {
    $rows[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Peminjaman - CampRent</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        * { box-sizing: border-box; }

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
            width: 215px;
            height: 100vh;
            padding: 22px 15px;
            color: white;
            background: linear-gradient(180deg, #12304a, #176b78);
            overflow-y: auto;
        }

        .brand {
            text-align: center;
            padding-bottom: 24px;
            border-bottom: 1px solid #ffffff35;
            margin-bottom: 22px;
        }

        .brand i {
            display: block;
            font-size: 35px;
            margin-bottom: 5px;
        }

        .brand h4 {
            font-weight: bold;
            margin: 8px 0 2px;
            font-size: 22px;
        }

        .brand small {
            display: block;
            font-size: 12px;
        }

        .menu-title {
            color: #b9e5ee;
            font-size: 10px;
            font-weight: bold;
            margin: 18px 8px 10px;
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
            margin-right: 8px;
        }

        .main {
            margin-left: 215px;
            padding: 25px;
            min-width: 0;
        }

        .topbar,
        .content-card {
            background: white;
            border-radius: 14px;
            padding: 20px;
            box-shadow: 0 5px 18px #102a430b;
            margin-bottom: 20px;
        }

        .topbar h5 {
            font-weight: bold;
            margin-bottom: 4px;
        }

        .muted {
            color: #718096;
            font-size: 13px;
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

        .badge-status {
            display: inline-block;
            border-radius: 20px;
            padding: 7px 10px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .menunggu { background: #fff1c2; color: #995400; }
        .dipinjam { background: #d8f5e5; color: #087443; }
        .ditolak { background: #ffe0e0; color: #a82020; }
        .selesai { background: #dbeafe; color: #1d4ed8; }
        .lainnya { background: #e3eaf2; color: #334e68; }

        .btn-setujui {
            background: #087f8c;
            color: white;
        }

        .btn-setujui:hover {
            background: #066874;
            color: white;
        }

        .amount {
            font-weight: bold;
            color: #087f8c;
            white-space: nowrap;
        }

        @media(max-width: 768px) {
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
        <i class="bi bi-cone-striped"></i>
        <h4>CAMPrent</h4>
        <small>Sistem Peminjaman Alat</small>
    </div>

    <div class="menu-title">MENU PETUGAS</div>
    <a href="dashboard.php"><i class="bi bi-house-fill"></i> Dashboard</a>
    <a href="peminjaman.php" class="active"><i class="bi bi-clipboard-check-fill"></i> Data Peminjaman</a>
    <a href="pengembalian.php"><i class="bi bi-arrow-return-left"></i> Pengembalian</a>
    <a href="laporan.php"><i class="bi bi-file-earmark-text-fill"></i> Laporan</a>

    <div class="menu-title">AKUN</div>
    <a href="logout.php" onclick="return confirm('Yakin ingin logout?')">
        <i class="bi bi-box-arrow-right"></i> Logout
    </a>
</div>

<div class="main">

    <div class="topbar d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5>Data Peminjaman</h5>
            <div class="muted">
                Kelola pengajuan, lihat tagihan, dan konfirmasi pembayaran peminjaman alat camping.
            </div>
        </div>

        <div>
            <i class="bi bi-person-fill-gear"></i>
            <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Petugas', ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    </div>

    <?php if (isset($_GET['hasil'])): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <?= htmlspecialchars($_GET['hasil'], ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="content-card">
        <h5 class="fw-bold mb-1">
            <i class="bi bi-list-check me-2"></i>Daftar Pengajuan
        </h5>

        <p class="muted mb-3">
            Periksa rincian alat dan tagihan. Konfirmasi pembayaran setelah uang benar-benar diterima.
        </p>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Peminjaman</th>
                        <th>Nama Peminjam</th>
                        <th>Detail Alat</th>
                        <th>Tanggal Pinjam</th>
                        <th>Rencana Kembali</th>
                        <th>Total Biaya</th>
                        <th>Status Peminjaman</th>
                        <th>Pembayaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!empty($rows)): ?>
                    <?php $no = 1; ?>

                    <?php foreach ($rows as $data): ?>
                        <?php
                        $id = (int)$data['peminjaman_id'];
                        $status = $data['status'] ?? '';
                        $pembayaran = $data['status_pembayaran'] ?? 'Belum Dibayar';

                        $class = 'lainnya';
                        if ($status === 'Menunggu Persetujuan') {
                            $class = 'menunggu';
                        } elseif ($status === 'Dipinjam') {
                            $class = 'dipinjam';
                        } elseif ($status === 'Ditolak') {
                            $class = 'ditolak';
                        } elseif ($status === 'Selesai') {
                            $class = 'selesai';
                        }
                        ?>

                        <tr>
                            <td><?= $no++ ?></td>

                            <td>
                                <strong><?= htmlspecialchars($data['kode_peminjaman'] ?? '-', ENT_QUOTES, 'UTF-8') ?></strong>
                            </td>

                            <td><?= htmlspecialchars($data['nama_lengkap'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>

                            <td><?= htmlspecialchars($data['daftar_alat'] ?: 'Detail alat belum tersedia', ENT_QUOTES, 'UTF-8') ?></td>

                            <td><?= htmlspecialchars($data['tanggal_pinjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>

                            <td><?= htmlspecialchars($data['tanggal_rencana_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>

                            <td class="amount"><?= rupiah($data['total_biaya']) ?></td>

                            <td>
                                <span class="badge-status <?= $class ?>">
                                    <?= htmlspecialchars($status ?: '-', ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <td>
                                <?php if ($pembayaran === 'Lunas'): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle"></i> Lunas
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Belum Dibayar</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <div class="d-flex flex-wrap gap-1">

                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#detail<?= $id ?>">
                                        <i class="bi bi-receipt"></i> Detail
                                    </button>

                                    <?php if ($status === 'Menunggu Persetujuan'): ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Setujui pengajuan ini? Stok alat akan dikurangi.')">
                                            <input type="hidden" name="peminjaman_id" value="<?= $id ?>">
                                            <input type="hidden" name="aksi" value="setujui">
                                            <button type="submit" name="proses" value="1" class="btn btn-sm btn-setujui">
                                                <i class="bi bi-check-lg"></i> Setujui
                                            </button>
                                        </form>

                                        <form method="POST"
                                              onsubmit="return confirm('Yakin ingin menolak pengajuan ini?')">
                                            <input type="hidden" name="peminjaman_id" value="<?= $id ?>">
                                            <input type="hidden" name="aksi" value="tolak">
                                            <button type="submit" name="proses" value="1" class="btn btn-sm btn-danger">
                                                <i class="bi bi-x-lg"></i> Tolak
                                            </button>
                                        </form>

                                    <?php elseif ($status === 'Dipinjam' && $pembayaran !== 'Lunas'): ?>

                                        <form method="POST"
                                              onsubmit="return confirm('Pastikan pembayaran sudah diterima. Konfirmasi sebagai LUNAS?')">
                                            <input type="hidden" name="peminjaman_id" value="<?= $id ?>">
                                            <input type="hidden" name="aksi" value="konfirmasi_bayar">
                                            <button type="submit" name="proses" value="1" class="btn btn-sm btn-success">
                                                <i class="bi bi-cash-coin"></i> Konfirmasi Bayar
                                            </button>
                                        </form>

                                    <?php else: ?>
                                        <span class="text-secondary small">Tidak ada aksi</span>
                                    <?php endif; ?>

                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center text-secondary py-4">
                            Belum ada data peminjaman.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-center muted">
        &copy; <?= date('Y') ?> CampRent - Sistem Peminjaman Alat Camping
    </div>
</div>

<!-- MODAL DETAIL PEMINJAMAN -->
<?php foreach ($rows as $data): ?>
    <?php
    $id = (int)$data['peminjaman_id'];
    $pembayaran = $data['status_pembayaran'] ?? 'Belum Dibayar';
    ?>

    <div class="modal fade" id="detail<?= $id ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt me-2"></i>Detail Tagihan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <small class="text-secondary">Kode Peminjaman</small>
                            <div class="fw-bold">
                                <?= htmlspecialchars($data['kode_peminjaman'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-secondary">Nama Peminjam</small>
                            <div class="fw-bold">
                                <?= htmlspecialchars($data['nama_lengkap'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-secondary">Tanggal Pinjam</small>
                            <div><?= htmlspecialchars($data['tanggal_pinjam'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>

                        <div class="col-md-6">
                            <small class="text-secondary">Rencana Kembali</small>
                            <div><?= htmlspecialchars($data['tanggal_rencana_kembali'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Nama Alat</th>
                                    <th>Jumlah</th>
                                    <th>Harga Sewa/Hari</th>
                                    <th>Subtotal/Hari</th>
                                </tr>
                            </thead>

                            <tbody>
                            <?php if (!empty($detail_semua[$id])): ?>

                                <?php foreach ($detail_semua[$id] as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nama_alat'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int)$item['jumlah'] ?></td>
                                        <td><?= rupiah($item['harga_sewa']) ?></td>
                                        <td><?= rupiah($item['subtotal_sewa']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-secondary">
                                        Rincian alat belum tersedia.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border rounded p-3">
                        <strong>Total Biaya Sewa per Hari</strong>
                        <span class="amount fs-5"><?= rupiah($data['total_biaya']) ?></span>
                    </div>

                    <div class="mt-3">
                        <strong>Status Pembayaran: </strong>

                        <?php if ($pembayaran === 'Lunas'): ?>
                            <span class="text-success fw-bold">Lunas</span>
                        <?php else: ?>
                            <span class="text-warning fw-bold">Belum Dibayar</span>
                        <?php endif; ?>
                    </div>

                    <small class="text-secondary d-block mt-2">
                        Harga sewa dan subtotal dihitung dari harga alat pada tabel t_alat.
                        Total di atas merupakan total biaya sewa per hari, belum dikalikan lama peminjaman.
                    </small>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                </div>

            </div>
        </div>
    </div>

<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
